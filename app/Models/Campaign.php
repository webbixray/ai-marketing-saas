<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Scopes\TenantScope;
use App\Traits\Auditable;

class Campaign extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'tenant_id',
        'client_id',
        'name',
        'slug',
        'description',
        'status',
        'approval_status',
        'type',
        'platform',
        'channels',
        'budget',
        'spent',
        'daily_budget',
        'lifetime_budget',
        'bidding_strategy',
        'bid_amount',
        'start_date',
        'end_date',
        'schedule',
        'automation_rules',
        'is_evergreen',
        'targeting',
        'audience_targeting',
        'geo_targeting',
        'device_targeting',
        'placement_targeting',
        'settings',
        'creatives',
        'ad_copy_variants',
        'landing_page_url',
        'utm_parameters',
        'impressions',
        'clicks',
        'conversions',
        'ctr',
        'cpc',
        'cpm',
        'cpa',
        'roas',
        'conversion_rate',
        'assigned_to',
        'collaborators',
        'external_id',
        'external_account_id',
        'last_synced_at',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'tags',
        'campaign_group',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'budget' => 'decimal:2',
        'spent' => 'decimal:2',
        'daily_budget' => 'decimal:2',
        'lifetime_budget' => 'decimal:2',
        'bid_amount' => 'decimal:2',
        'targeting' => 'array',
        'audience_targeting' => 'array',
        'geo_targeting' => 'array',
        'device_targeting' => 'array',
        'placement_targeting' => 'array',
        'settings' => 'array',
        'schedule' => 'array',
        'automation_rules' => 'array',
        'creatives' => 'array',
        'ad_copy_variants' => 'array',
        'utm_parameters' => 'array',
        'collaborators' => 'array',
        'tags' => 'array',
        'is_evergreen' => 'boolean',
        'ctr' => 'decimal:2',
        'cpc' => 'decimal:2',
        'cpm' => 'decimal:2',
        'cpa' => 'decimal:2',
        'roas' => 'decimal:2',
        'conversion_rate' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
        'approved_at' => 'datetime',
        'last_synced_at' => 'datetime',
    ];

    /**
     * Boot the model and apply global scopes.
     */
    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope(new TenantScope);
        static::bootAuditable();
    }

    /**
     * Get the tenant that owns the campaign.
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the client that owns the campaign.
     */
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Get the user assigned to the campaign.
     */
    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Get the user who approved the campaign.
     */
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Scope a query to only include active campaigns.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope a query to only include approved campaigns.
     */
    public function scopeApproved($query)
    {
        return $query->where('approval_status', 'approved');
    }

    /**
     * Scope a query to filter by platform.
     */
    public function scopePlatform($query, $platform)
    {
        return $query->where('platform', $platform);
    }

    /**
     * Scope a query to filter by client.
     */
    public function scopeForClient($query, $clientId)
    {
        return $query->where('client_id', $clientId);
    }

    /**
     * Calculate and update performance metrics.
     */
    public function updateMetrics(array $metrics): void
    {
        $this->update([
            'impressions' => $metrics['impressions'] ?? $this->impressions,
            'clicks' => $metrics['clicks'] ?? $this->clicks,
            'conversions' => $metrics['conversions'] ?? $this->conversions,
            'ctr' => $metrics['ctr'] ?? $this->ctr,
            'cpc' => $metrics['cpc'] ?? $this->cpc,
            'cpm' => $metrics['cpm'] ?? $this->cpm,
            'cpa' => $metrics['cpa'] ?? $this->cpa,
            'roas' => $metrics['roas'] ?? $this->roas,
            'conversion_rate' => $metrics['conversion_rate'] ?? $this->conversion_rate,
            'spent' => $metrics['spent'] ?? $this->spent,
        ]);
    }

    /**
     * Check if campaign is currently running.
     */
    public function isRunning(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        $now = now();
        if ($this->start_date && $now->lt($this->start_date)) {
            return false;
        }
        if ($this->end_date && $now->gt($this->end_date)) {
            return false;
        }

        return true;
    }
}
