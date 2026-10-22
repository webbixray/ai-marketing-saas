<?php

namespace App\Services;

use App\Models\FeatureFlag;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class FeatureFlagService
{
    protected const CACHE_TTL = 300; // 5 minutes
    protected const CACHE_PREFIX = 'feature_flag:';

    /**
     * Check if a feature flag is enabled for the current context.
     */
    public function isEnabled(string $key, ?User $user = null, ?int $tenantId = null): bool
    {
        $flag = $this->getFlag($key);
        
        if (!$flag) {
            return false;
        }

        return $flag->isActive($user, $tenantId);
    }

    /**
     * Get a feature flag by key with caching.
     */
    public function getFlag(string $key): ?FeatureFlag
    {
        return Cache::remember(self::CACHE_PREFIX . $key, self::CACHE_TTL, function () use ($key) {
            return FeatureFlag::where('key', $key)->first();
        });
    }

    /**
     * Get all feature flags.
     */
    public function getAllFlags(): array
    {
        return FeatureFlag::orderBy('key')->get()->keyBy('key')->toArray();
    }

    /**
     * Enable a feature flag.
     */
    public function enable(string $key, ?User $user = null): bool
    {
        $flag = FeatureFlag::where('key', $key)->first();
        
        if (!$flag) {
            return false;
        }

        $flag->update([
            'enabled' => true,
            'enabled_at' => now(),
            'updated_by' => $user?->id,
        ]);

        $this->clearCache($key);
        
        Log::info('Feature flag enabled', ['key' => $key, 'by' => $user?->id]);
        
        return true;
    }

    /**
     * Disable a feature flag.
     */
    public function disable(string $key, ?User $user = null): bool
    {
        $flag = FeatureFlag::where('key', $key)->first();
        
        if (!$flag) {
            return false;
        }

        $flag->update([
            'enabled' => false,
            'disabled_at' => now(),
            'updated_by' => $user?->id,
        ]);

        $this->clearCache($key);
        
        Log::info('Feature flag disabled', ['key' => $key, 'by' => $user?->id]);
        
        return true;
    }

    /**
     * Create a new feature flag.
     */
    public function create(array $data, ?User $user = null): FeatureFlag
    {
        $data['created_by'] = $user?->id;
        $data['updated_by'] = $user?->id;
        
        $flag = FeatureFlag::create($data);
        
        $this->clearCache($flag->key);
        
        return $flag;
    }

    /**
     * Update a feature flag.
     */
    public function update(string $key, array $data, ?User $user = null): ?FeatureFlag
    {
        $flag = FeatureFlag::where('key', $key)->first();
        
        if (!$flag) {
            return null;
        }

        $data['updated_by'] = $user?->id;
        $flag->update($data);
        
        $this->clearCache($key);
        
        return $flag->fresh();
    }

    /**
     * Delete a feature flag.
     */
    public function delete(string $key): bool
    {
        $flag = FeatureFlag::where('key', $key)->first();
        
        if (!$flag) {
            return false;
        }

        $flag->delete();
        
        $this->clearCache($key);
        
        return true;
    }

    /**
     * Clear cache for a specific flag.
     */
    protected function clearCache(string $key): void
    {
        Cache::forget(self::CACHE_PREFIX . $key);
    }

    /**
     * Clear all feature flag caches.
     */
    public function clearAllCaches(): void
    {
        $flags = FeatureFlag::pluck('key')->toArray();
        foreach ($flags as $key) {
            $this->clearCache($key);
        }
    }

    /**
     * Get flags enabled for a specific tenant/user context.
     */
    public function getEnabledForContext(?User $user = null, ?int $tenantId = null): array
    {
        $flags = FeatureFlag::enabled()->get();
        
        return $flags->filter(function ($flag) use ($user, $tenantId) {
            return $flag->isActive($user, $tenantId);
        })->keyBy('key')->toArray();
    }
}
