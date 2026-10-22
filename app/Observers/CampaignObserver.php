<?php

namespace App\Observers;

use App\Models\Campaign;
use Illuminate\Support\Facades\Cache;

class CampaignObserver
{
    /**
     * Handle the Campaign "created" event.
     */
    public function created(Campaign $campaign): void
    {
        $this->clearReportCaches($campaign->tenant_id);
    }

    /**
     * Handle the Campaign "updated" event.
     */
    public function updated(Campaign $campaign): void
    {
        $this->clearReportCaches($campaign->tenant_id);
    }

    /**
     * Handle the Campaign "deleted" event.
     */
    public function deleted(Campaign $campaign): void
    {
        $this->clearReportCaches($campaign->tenant_id);
    }

    /**
     * Handle the Campaign "restored" event.
     */
    public function restored(Campaign $campaign): void
    {
        $this->clearReportCaches($campaign->tenant_id);
    }

    /**
     * Handle the Campaign "force deleted" event.
     */
    public function forceDeleted(Campaign $campaign): void
    {
        $this->clearReportCaches($campaign->tenant_id);
    }

    /**
     * Clear all report caches for a tenant.
     */
    protected function clearReportCaches(int $tenantId): void
    {
        $keys = [
            "report:revenue:tenant:{$tenantId}",
            "report:user_growth:tenant:{$tenantId}",
            "report:subscription_growth:tenant:{$tenantId}",
            "report:revenue_trend:tenant:{$tenantId}",
            "report:task_stats:tenant:{$tenantId}",
            "report:task_completion_trend:tenant:{$tenantId}",
            "report:campaign_stats:tenant:{$tenantId}",
            "report:campaign_creation_trend:tenant:{$tenantId}",
            "report:user_activity:tenant:{$tenantId}",
        ];

        foreach ($keys as $key) {
            Cache::forget($key);
        }
    }
}
