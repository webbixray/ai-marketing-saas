<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Scopes\TenantScope;
use App\Traits\Auditable;
use Carbon\Carbon;

class Task extends Model
{
    use HasFactory, Auditable;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'title',
        'description',
        'status',
        'priority',
        'due_date',
        'assigned_to',
        'tenant_id',
        // SLA fields
        'sla_started_at',
        'sla_due_at',
        'first_response_at',
        'resolved_at',
        'sla_response_minutes',
        'sla_resolution_minutes',
        'sla_response_breached',
        'sla_resolution_breached',
        'sla_pauses',
        'sla_paused_minutes',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'due_date' => 'date',
        'completed_at' => 'datetime',
        'sla_started_at' => 'datetime',
        'sla_due_at' => 'datetime',
        'first_response_at' => 'datetime',
        'resolved_at' => 'datetime',
        'sla_response_minutes' => 'integer',
        'sla_resolution_minutes' => 'integer',
        'sla_response_breached' => 'boolean',
        'sla_resolution_breached' => 'boolean',
        'sla_pauses' => 'array',
        'sla_paused_minutes' => 'integer',
    ];

    /**
     * Boot the model and apply global scopes.
     */
    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope(new TenantScope);
        static::bootAuditable();

        // Auto-set SLA when task is created
        static::created(function ($task) {
            $task->initializeSla();
        });

        // Track SLA when status changes
        static::updated(function ($task) {
            if ($task->isDirty('status')) {
                $task->handleStatusChange($task->getOriginal('status'));
            }
        });
    }

    /**
     * Get the user that assigned the task.
     */
    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Scope a query to only include active tasks.
     */
    public function scopeActive($query)
    {
        return $this->where('status', '!=', 'completed');
    }

    /**
     * Scope for tasks with breached SLA.
     */
    public function scopeSlaBreached($query)
    {
        return $query->where('sla_response_breached', true)
            ->orWhere('sla_resolution_breached', true);
    }

    /**
     * Scope for tasks approaching SLA breach (within 30 minutes).
     */
    public function scopeSlaAtRisk($query)
    {
        return $query->where('sla_due_at', '<=', now()->addMinutes(30))
            ->where('sla_response_breached', false)
            ->where('sla_resolution_breached', false);
    }

    /**
     * Initialize SLA based on priority.
     */
    public function initializeSla(): void
    {
        $slaConfig = $this->getSlaConfig();
        
        $this->updateQuietly([
            'sla_started_at' => now(),
            'sla_due_at' => now()->addMinutes($slaConfig['resolution']),
            'sla_response_minutes' => $slaConfig['response'],
            'sla_resolution_minutes' => $slaConfig['resolution'],
        ]);
    }

    /**
     * Get SLA configuration based on priority.
     */
    protected function getSlaConfig(): array
    {
        return match($this->priority) {
            'urgent' => ['response' => 15, 'resolution' => 60],      // 15min response, 1hr resolution
            'high' => ['response' => 30, 'resolution' => 240],       // 30min response, 4hr resolution
            'medium' => ['response' => 120, 'resolution' => 480],    // 2hr response, 8hr resolution
            'low' => ['response' => 480, 'resolution' => 1440],      // 8hr response, 24hr resolution
            default => ['response' => 240, 'resolution' => 720],     // 4hr response, 12hr resolution
        };
    }

    /**
     * Handle status change for SLA tracking.
     */
    public function handleStatusChange(string $oldStatus): void
    {
        $newStatus = $this->status;
        $now = now();

        // First response tracking (when moving from todo to in_progress)
        if ($oldStatus === 'todo' && $newStatus === 'in_progress' && !$this->first_response_at) {
            $this->updateQuietly(['first_response_at' => $now]);
            $this->checkResponseSla($now);
        }

        // Resolution tracking (when moving to completed)
        if ($newStatus === 'completed' && !$this->resolved_at) {
            $this->updateQuietly(['resolved_at' => $now]);
            $this->checkResolutionSla($now);
        }

        // Pause SLA if needed (e.g., waiting on client)
        if (in_array($newStatus, ['waiting_client', 'on_hold'])) {
            $this->pauseSla($now);
        } elseif ($oldStatus === 'waiting_client' || $oldStatus === 'on_hold') {
            $this->resumeSla($now);
        }
    }

    /**
     * Check if response SLA was breached.
     */
    protected function checkResponseSla(Carbon $responseTime): void
    {
        if (!$this->sla_started_at || !$this->sla_response_minutes) {
            return;
        }

        $expectedResponse = $this->sla_started_at->copy()->addMinutes($this->sla_response_minutes);
        $breached = $responseTime->gt($expectedResponse);

        if ($breached && !$this->sla_response_breached) {
            $this->updateQuietly(['sla_response_breached' => true]);
            
            // Notify assignee/manager
            $this->notifySlaBreach('response');
        }
    }

    /**
     * Check if resolution SLA was breached.
     */
    protected function checkResolutionSla(Carbon $resolutionTime): void
    {
        if (!$this->sla_started_at || !$this->sla_resolution_minutes) {
            return;
        }

        $expectedResolution = $this->sla_started_at->copy()->addMinutes($this->sla_resolution_minutes);
        $breached = $resolutionTime->gt($expectedResolution);

        if ($breached && !$this->sla_resolution_breached) {
            $this->updateQuietly(['sla_resolution_breached' => true]);
            
            // Notify assignee/manager
            $this->notifySlaBreach('resolution');
        }
    }

    /**
     * Pause SLA clock.
     */
    public function pauseSla(Carbon $at = null): void
    {
        $at = $at ?? now();
        $pauses = $this->sla_pauses ?? [];
        $pauses[] = ['started_at' => $at->toISOString()];
        $this->updateQuietly(['sla_pauses' => $pauses]);
    }

    /**
     * Resume SLA clock.
     */
    public function resumeSla(Carbon $at = null): void
    {
        $at = $at ?? now();
        $pauses = $this->sla_pauses ?? [];
        
        // Find the last pause that hasn't been closed
        $lastPause = collect($pauses)->lastWhere('ended_at', null);
        
        if ($lastPause) {
            $started = Carbon::parse($lastPause['started_at']);
            $pausedMinutes = $started->diffInMinutes($at);
            
            $lastPause['ended_at'] = $at->toISOString();
            $lastPause['duration_minutes'] = $pausedMinutes;
            
            $this->updateQuietly([
                'sla_pauses' => $pauses,
                'sla_paused_minutes' => $this->sla_paused_minutes + $pausedMinutes,
            ]);

            // Adjust SLA due date by paused duration
            if ($this->sla_due_at) {
                $this->updateQuietly([
                    'sla_due_at' => $this->sla_due_at->copy()->addMinutes($pausedMinutes),
                ]);
            }
        }
    }

    /**
     * Notify SLA breach.
     */
    protected function notifySlaBreach(string $type): void
    {
        if ($this->assigned_to) {
            $this->assignedUser->notify(new \App\Notifications\SlaBreached($this, $type));
        }
    }

    /**
     * Get remaining SLA time in minutes.
     */
    public function getSlaRemainingMinutes(): ?int
    {
        if (!$this->sla_due_at) {
            return null;
        }

        $remaining = now()->diffInMinutes($this->sla_due_at, false);
        return max(0, $remaining);
    }

    /**
     * Check if SLA is at risk.
     */
    public function isSlaAtRisk(): bool
    {
        if (!$this->sla_due_at) {
            return false;
        }

        return $this->getSlaRemainingMinutes() <= 30 && !$this->sla_response_breached && !$this->sla_resolution_breached;
    }

    /**
     * Get SLA metrics.
     */
    public function getSlaMetrics(): array
    {
        return [
            'response_time_minutes' => $this->first_response_at && $this->sla_started_at
                ? $this->sla_started_at->diffInMinutes($this->first_response_at)
                : null,
            'resolution_time_minutes' => $this->resolved_at && $this->sla_started_at
                ? $this->sla_started_at->diffInMinutes($this->resolved_at)
                : null,
            'sla_response_minutes' => $this->sla_response_minutes,
            'sla_resolution_minutes' => $this->sla_resolution_minutes,
            'response_breached' => $this->sla_response_breached,
            'resolution_breached' => $this->sla_resolution_breached,
            'remaining_minutes' => $this->getSlaRemainingMinutes(),
            'at_risk' => $this->isSlaAtRisk(),
        ];
    }
}