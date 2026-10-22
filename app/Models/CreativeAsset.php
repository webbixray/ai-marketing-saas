<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Scopes\TenantScope;
use App\Traits\Auditable;

class CreativeAsset extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    protected $fillable = [
        'tenant_id',
        'client_id',
        'campaign_id',
        'name',
        'type',
        'format',
        'media_url',
        'elements',
        'performance_metrics',
        'frequency',
        'status',
        'metadata',
        'archived_at',
        'created_by',
    ];

    protected $casts = [
        'elements' => 'array',
        'performance_metrics' => 'array',
        'metadata' => 'array',
        'frequency' => 'decimal:2',
        'archived_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        static::addGlobalScope(new TenantScope);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function performanceDaily()
    {
        return $this->hasMany(CreativePerformanceDaily::class, 'creative_id');
    }

    public function getPerformanceScoreAttribute(): float
    {
        $metrics = $this->performance_metrics ?? [];
        
        $ctr = $metrics['ctr'] ?? 0;
        $cvr = $metrics['cvr'] ?? 0;
        $roas = $metrics['roas'] ?? 0;
        $impressions = $metrics['impressions'] ?? 0;

        $ctrScore = min(100, ($ctr / 2.0) * 100);
        $cvrScore = min(100, ($cvr / 5.0) * 100);
        $roasScore = min(100, ($roas / 4.0) * 100);
        $volumeScore = min(100, ($impressions / 100000) * 100);

        return round(($ctrScore * 0.3 + $cvrScore * 0.3 + $roasScore * 0.3 + $volumeScore * 0.1), 2);
    }

    public function isFatigued(array $config = []): array
    {
        $frequency = $this->frequency ?? 0;
        $ctrTrend = $this->getCtrTrend();
        
        $frequencyThreshold = $config['fatigue_threshold_frequency'] ?? 3.0;
        $ctrDeclineThreshold = $config['fatigue_threshold_ctr_decline'] ?? 0.3;

        $isFatigued = false;
        $severity = 'low';
        $reasons = [];

        if ($frequency > $frequencyThreshold) {
            $isFatigued = true;
            $severity = 'medium';
            $reasons[] = "Frequency ({$frequency}) exceeds threshold ({$frequencyThreshold})";
        }

        if ($ctrTrend < -$ctrDeclineThreshold) {
            $isFatigued = true;
            $severity = 'high';
            $reasons[] = "CTR declined by " . abs($ctrTrend) * 100 . "%";
        }

        $age = $this->created_at->diffInDays(now());
        if ($age > 60 && $ctrTrend < -0.1) {
            $isFatigued = true;
            $severity = 'high';
            $reasons[] = "Creative age: {$age} days with declining CTR";
        }

        return [
            'is_fatigued' => $isFatigued,
            'severity' => $severity,
            'reasons' => $reasons,
            'frequency' => $frequency,
            'ctr_trend' => $ctrTrend,
            'age_days' => $age,
            'message' => $isFatigued ? implode('; ', $reasons) : 'No fatigue detected',
        ];
    }

    public function getCtrTrend(): float
    {
        $data = $this->performanceDaily()
            ->where('date', '>=', now()->subDays(14))
            ->orderBy('date', 'asc')
            ->pluck('ctr');

        if ($data->count() < 3) {
            return 0;
        }

        $firstHalf = $data->slice(0, (int)($data->count() / 2))->avg();
        $secondHalf = $data->slice((int)($data->count() / 2))->avg();

        if ($firstHalf == 0) {
            return 0;
        }

        return ($secondHalf - $firstHalf) / $firstHalf;
    }

    public function generateVariantRecommendations(): array
    {
        $variants = [];
        $elements = $this->elements ?? [];

        if (!empty($elements['headline'])) {
            $variants[] = [
                'type' => 'headline_variant',
                'original' => $elements['headline'],
                'suggestions' => $this->generateHeadlineVariants($elements['headline']),
                'test_type' => 'A/B',
            ];
        }

        if (!empty($elements['media_url'])) {
            $variants[] = [
                'type' => 'media_variant',
                'original' => $elements['media_url'],
                'suggestions' => [
                    'Try different color scheme',
                    'Test with people vs product only',
                    'Try carousel vs single image',
                    'Test video vs static',
                ],
                'test_type' => 'multivariate',
            ];
        }

        if (!empty($elements['cta'])) {
            $variants[] = [
                'type' => 'cta_variant',
                'original' => $elements['cta'],
                'suggestions' => $this->generateCtaVariants($elements['cta']),
                'test_type' => 'A/B',
            ];
        }

        $variants[] = [
            'type' => 'audience_variant',
            'description' => 'Test with lookalike audience expansion',
            'test_type' => 'audience_split',
        ];

        return $variants;
    }

    protected function generateHeadlineVariants(string $headline): array
    {
        return [
            "Transform Your {$headline}",
            "Ready to {$headline}?",
            "Start {$headline} Today",
            "Join 10,000+ Who {$headline}",
            "Get Results: {$headline} in 30 Days",
        ];
    }

    protected function generateCtaVariants(string $cta): array
    {
        $actionVerbs = ['Get', 'Start', 'Join', 'Discover', 'Learn', 'Try', 'Claim'];
        $variants = [];

        foreach ($actionVerbs as $verb) {
            $variants[] = "{$verb} {$cta}";
        }

        $variants[] = "Get Free {$cta}";
        $variants[] = "Start Free Trial";
        $variants[] = "See How It Works";

        return $variants;
    }

    public function shouldArchive(): bool
    {
        $age = $this->created_at->diffInDays(now());
        $lastImpression = $this->performanceDaily()->orderBy('date', 'desc')->value('date');

        if ($age > 90 && $lastImpression && \Carbon\Carbon::parse($lastImpression)->lt(now()->subDays(30))) {
            return true;
        }

        if (($this->metadata['archive_requested'] ?? false) === true) {
            return true;
        }

        return false;
    }

    public function archive(): void
    {
        $this->update([
            'status' => 'archived',
            'archived_at' => now(),
            'metadata' => array_merge(
                $this->metadata ?? [],
                ['archived_reason' => 'auto_archived', 'archived_by' => 'creative_intelligence_agent']
            ),
        ]);
    }
}
