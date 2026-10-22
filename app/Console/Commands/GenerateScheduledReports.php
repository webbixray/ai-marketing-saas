<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Campaign;
use App\Models\Task;
use Laravel\Cashier\Subscription;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

#[Signature('reports:generate-scheduled')]
#[Description('Generate scheduled reports for all tenants')]
class GenerateScheduledReports extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting scheduled report generation...');

        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            try {
                $this->generateTenantReports($tenant);
                $this->info("Reports generated for tenant: {$tenant->name} (ID: {$tenant->id})");
            } catch (\Throwable $e) {
                $this->error("Failed to generate reports for tenant {$tenant->id}: {$e->getMessage()}");
                Log::error('Scheduled report generation failed', [
                    'tenant_id' => $tenant->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        $this->info('Scheduled report generation completed.');
        return self::SUCCESS;
    }

    /**
     * Generate reports for a specific tenant.
     */
    protected function generateTenantReports(Tenant $tenant): void
    {
        $adminUsers = User::where('tenant_id', $tenant->id)
            ->whereHas('roles', function ($query) {
                $query->where('name', 'admin');
            })
            ->get();

        if ($adminUsers->isEmpty()) {
            $this->warn("No admin users found for tenant {$tenant->id}, skipping report delivery.");
            return;
        }

        // Generate report data
        $reportData = [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
            ],
            'generated_at' => now()->toIso8601String(),
            'period' => now()->subMonth()->format('Y-m'),
            'revenue' => $this->getRevenueReport($tenant),
            'user_growth' => $this->getUserGrowthReport($tenant),
            'task_stats' => $this->getTaskStatsReport($tenant),
            'campaign_stats' => $this->getCampaignStatsReport($tenant),
            'top_campaigns' => $this->getTopCampaignsReport($tenant),
        ];

        // Send to admin users
        foreach ($adminUsers as $admin) {
            Notification::send($admin, new \App\Notifications\ScheduledReport($reportData));
        }

        // Store report for historical access
        $this->storeReport($tenant, $reportData);
    }

    /**
     * Get revenue report.
     */
    protected function getRevenueReport(Tenant $tenant): array
    {
        $activeSubscriptions = Subscription::where('tenant_id', $tenant->id)
            ->whereNotNull('ends_at')
            ->where('ends_at', '>=', now())
            ->orWhereNull('ends_at')
            ->get();

        $mrr = 0;
        $planBreakdown = [];

        foreach ($activeSubscriptions as $sub) {
            $price = $sub->stripe_price ?? 0;
            $amount = $price / 100;
            $mrr += $amount;

            $planName = $sub->stripe_plan ?? 'unknown';
            if (!isset($planBreakdown[$planName])) {
                $planBreakdown[$planName] = ['count' => 0, 'amount' => 0];
            }
            $planBreakdown[$planName]['count'] += 1;
            $planBreakdown[$planName]['amount'] += $amount;
        }

        $formattedBreakdown = [];
        foreach ($planBreakdown as $plan => $data) {
            $formattedBreakdown[] = [
                'plan' => $plan,
                'count' => $data['count'],
                'amount' => round($data['amount'], 2),
            ];
        }

        return [
            'mrr' => round($mrr, 2),
            'currency' => 'USD',
            'plan_breakdown' => $formattedBreakdown,
        ];
    }

    /**
     * Get user growth report.
     */
    protected function getUserGrowthReport(Tenant $tenant): array
    {
        $driver = DB::connection()->getConfig('driver');
        $datePart = $driver === 'sqlite'
            ? "strftime('%Y', created_at) as year, strftime('%m', created_at) as month"
            : "DATE_PART('year', created_at) as year, DATE_PART('month', created_at) as month";

        $usersByMonth = User::where('tenant_id', $tenant->id)
            ->where('created_at', '>=', Carbon::now()->subMonths(6))
            ->selectRaw($datePart . ', COUNT(*) as count')
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get()
            ->map(function ($row) {
                return [
                    'period' => sprintf('%04d-%02d', $row->year, $row->month),
                    'new_users' => (int) $row->count,
                ];
            });

        return [
            'user_growth' => $usersByMonth,
        ];
    }

    /**
     * Get task stats report.
     */
    protected function getTaskStatsReport(Tenant $tenant): array
    {
        $taskStats = Task::where('tenant_id', $tenant->id)
            ->selectRaw(
                "COUNT(*) as total,
                 SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                 SUM(CASE WHEN status != 'completed' THEN 1 ELSE 0 END) as pending"
            )
            ->first();

        return [
            'task_stats' => [
                'total' => (int) $taskStats->total,
                'completed' => (int) $taskStats->completed,
                'pending' => (int) $taskStats->pending,
                'completion_rate' => $taskStats->total > 0
                    ? round((($taskStats->completed) / $taskStats->total) * 100, 2)
                    : 0,
            ],
        ];
    }

    /**
     * Get campaign stats report.
     */
    protected function getCampaignStatsReport(Tenant $tenant): array
    {
        $campaignStats = Campaign::where('tenant_id', $tenant->id)
            ->selectRaw(
                "COUNT(*) as total,
                 SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                 SUM(CASE WHEN status = 'paused' THEN 1 ELSE 0 END) as paused,
                 SUM(CASE WHEN status IN ('completed', 'cancelled', 'archived') THEN 1 ELSE 0 END) as archived"
            )
            ->first();

        return [
            'campaign_stats' => [
                'total' => (int) $campaignStats->total,
                'active' => (int) $campaignStats->active,
                'paused' => (int) $campaignStats->paused,
                'archived' => (int) $campaignStats->archived,
            ],
        ];
    }

    /**
     * Get top campaigns by performance.
     */
    protected function getTopCampaignsReport(Tenant $tenant): array
    {
        $topCampaigns = Campaign::where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->orderByDesc('roas')
            ->limit(5)
            ->get(['id', 'name', 'platform', 'status', 'budget', 'spent', 'roas', 'conversions', 'clicks', 'impressions'])
            ->map(function ($campaign) {
                return [
                    'id' => $campaign->id,
                    'name' => $campaign->name,
                    'platform' => $campaign->platform,
                    'status' => $campaign->status,
                    'budget' => $campaign->budget,
                    'spent' => $campaign->spent,
                    'roas' => $campaign->roas,
                    'conversions' => $campaign->conversions,
                    'clicks' => $campaign->clicks,
                    'impressions' => $campaign->impressions,
                ];
            });

        return [
            'top_campaigns' => $topCampaigns,
        ];
    }

    /**
     * Store report for historical access.
     */
    protected function storeReport(Tenant $tenant, array $reportData): void
    {
        $filename = "reports/tenant_{$tenant->id}/" . now()->format('Y/m/d/H-i-s') . '.json';
        
        \Illuminate\Support\Facades\Storage::disk('local')->put(
            $filename,
            json_encode($reportData, JSON_PRETTY_PRINT)
        );
    }
}
