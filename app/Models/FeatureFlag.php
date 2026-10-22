<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class FeatureFlag extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'key',
        'name',
        'description',
        'enabled',
        'conditions',
        'metadata',
        'enabled_at',
        'disabled_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'conditions' => 'array',
        'metadata' => 'array',
        'enabled_at' => 'datetime',
        'disabled_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($flag) {
            $flag->created_by = Auth::id();
            if ($flag->enabled && !$flag->enabled_at) {
                $flag->enabled_at = now();
            }
        });

        static::updating(function ($flag) {
            $flag->updated_by = Auth::id();
            if ($flag->isDirty('enabled')) {
                if ($flag->enabled && !$flag->enabled_at) {
                    $flag->enabled_at = now();
                } elseif (!$flag->enabled && !$flag->disabled_at) {
                    $flag->disabled_at = now();
                }
            }
        });
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    public function scopeForTenant($query, int $tenantId)
    {
        // Feature flags are global, but can be scoped via conditions
        return $query;
    }

    /**
     * Check if the feature flag is active for the current context.
     */
    public function isActive(?User $user = null, ?int $tenantId = null): bool
    {
        if (!$this->enabled) {
            return false;
        }

        if (!$this->conditions) {
            return true;
        }

        $conditions = $this->conditions;

        // Check tenant restriction
        if (isset($conditions['tenant_ids']) && is_array($conditions['tenant_ids'])) {
            if ($tenantId && !in_array($tenantId, $conditions['tenant_ids'])) {
                return false;
            }
        }

        // Check role restriction
        if (isset($conditions['user_roles']) && is_array($conditions['user_roles']) && $user) {
            $userRoles = $user->getRoleNames()->toArray();
            $hasRole = false;
            foreach ($conditions['user_roles'] as $role) {
                if (in_array($role, $userRoles)) {
                    $hasRole = true;
                    break;
                }
            }
            if (!$hasRole) {
                return false;
            }
        }

        // Check percentage rollout
        if (isset($conditions['percentage']) && is_numeric($conditions['percentage'])) {
            $percentage = (int) $conditions['percentage'];
            if ($percentage < 100) {
                // Use user ID or tenant ID for consistent hashing
                $seed = $user?->id ?? $tenantId ?? 0;
                $hash = crc32($this->key . $seed);
                $bucket = ($hash % 100) + 1; // 1-100
                if ($bucket > $percentage) {
                    return false;
                }
            }
        }

        return true;
    }
}
