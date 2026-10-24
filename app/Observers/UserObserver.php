<?php

namespace App\Observers;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

class UserObserver
{
    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        $this->clearReportCaches($user->tenant_id);
    }

    /**
     * Handle the User "updated" event.
     */
    public function updated(User $user): void
    {
        $this->clearReportCaches($user->tenant_id);
    }

    /**
     * Handle the User "deleted" event.
     */
    public function deleted(User $user): void
    {
        $this->clearReportCaches($user->tenant_id);
    }

    /**
     * Handle the User "restored" event.
     */
    public function restored(User $user): void
    {
        $this->clearReportCaches($user->tenant_id);
    }

    /**
     * Handle the User "force deleted" event.
     */
    public function forceDeleted(User $user): void
    {
        $this->clearReportCaches($user->tenant_id);
    }

    /**
     * Clear all report caches for a tenant.
     *
     * Platform administrators have no tenant (null); their report caches
     * are tenant-wide and are not keyed per tenant, so there is nothing
     * tenant-specific to clear.
     */
    protected function clearReportCaches(?int $tenantId): void
    {
        if ($tenantId === null) {
            return;
        }

        $keys = [
            "report:user_growth:tenant:{$tenantId}",
            "report:user_activity:tenant:{$tenantId}",
        ];

        foreach ($keys as $key) {
            Cache::forget($key);
        }
    }
}
