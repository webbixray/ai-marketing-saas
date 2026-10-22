<?php

namespace App\Observers;

use App\Models\Task;
use Illuminate\Support\Facades\Cache;

class TaskObserver
{
    /**
     * Handle the Task "created" event.
     */
    public function created(Task $task): void
    {
        $this->clearReportCaches($task->tenant_id);
    }

    /**
     * Handle the Task "updated" event.
     */
    public function updated(Task $task): void
    {
        $this->clearReportCaches($task->tenant_id);
    }

    /**
     * Handle the Task "deleted" event.
     */
    public function deleted(Task $task): void
    {
        $this->clearReportCaches($task->tenant_id);
    }

    /**
     * Handle the Task "restored" event.
     */
    public function restored(Task $task): void
    {
        $this->clearReportCaches($task->tenant_id);
    }

    /**
     * Handle the Task "force deleted" event.
     */
    public function forceDeleted(Task $task): void
    {
        $this->clearReportCaches($task->tenant_id);
    }

    /**
     * Clear all report caches for a tenant.
     */
    protected function clearReportCaches(int $tenantId): void
    {
        $keys = [
            "report:task_stats:tenant:{$tenantId}",
            "report:task_completion_trend:tenant:{$tenantId}",
            "report:user_activity:tenant:{$tenantId}",
        ];

        foreach ($keys as $key) {
            Cache::forget($key);
        }
    }
}
