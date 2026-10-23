<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class DashboardController extends Controller
{
    /**
     * Render the admin dashboard (stats + charts + funnel).
     */
    public function index()
    {
        return view('admin.dashboard', [
            'stats' => $this->stats(),
            'charts' => $this->charts(),
            'funnel' => $this->funnel(),
        ]);
    }

    /**
     * Platform stats scoped to the admin's tenant when they have one,
     * otherwise platform-wide (super admin without a tenant).
     *
     * @return array<string, mixed>
     */
    public function stats(): array
    {
        $tenantId = auth()->user()?->tenant_id;

        $campaignCount = (int) \App\Models\Campaign::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->count();

        $budget = (float) \App\Models\Campaign::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->sum('budget');

        $conversionQuery = fn () => \App\Models\ConversionEvent::query()
            ->when($tenantId !== null, fn ($q) => $q->whereHas('campaign', fn ($cq) => $cq->where('tenant_id', $tenantId)));

        $revenue = (float) $conversionQuery()->sum('revenue');
        $conversionCount = (int) $conversionQuery()->count();

        $clients = \App\Models\Client::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId));

        return [
            'total_campaigns' => $campaignCount,
            'active_campaigns' => (int) \App\Models\Campaign::query()
                ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
                ->where('status', 'active')
                ->count(),
            'total_clients' => (int) $clients->count(),
            'total_spend' => $budget,
            'total_revenue' => $revenue,
            'cac' => number_format($campaignCount > 0 ? $budget / $campaignCount : 0, 2),
            'roas' => $budget > 0 ? number_format($revenue / $budget, 2) : 0,
            'conversion_rate' => number_format($campaignCount > 0 ? $conversionCount / $campaignCount * 100 : 0, 2),
        ];
    }

    /**
     * Chart data for the dashboard (portable across SQLite/MySQL).
     *
     * @return array<string, mixed>
     */
    public function charts(string $period = '30d'): array
    {
        $tenantId = auth()->user()?->tenant_id;

        $days = match ($period) {
            '7d' => 7,
            '90d' => 90,
            default => 30,
        };
        $since = now()->subDays($days);

        $campaigns = \App\Models\Campaign::query()
            ->where('created_at', '>=', $since)
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId));

        return [
            'spend_over_time' => (clone $campaigns)
                ->selectRaw('DATE(created_at) as date, SUM(budget) as daily_spend')
                ->groupBy('date')
                ->orderBy('date')
                ->get()
                ->toArray(),
            'campaigns_by_channel' => (clone $campaigns)
                ->selectRaw('platform, COUNT(*) as count')
                ->whereNotNull('platform')
                ->groupBy('platform')
                ->get()
                ->keyBy('platform')
                ->toArray(),
            'top_campaigns' => \App\Models\Campaign::query()
                ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
                ->with('client')
                ->orderBy('budget', 'desc')
                ->take(5)
                ->get()
                ->toArray(),
        ];
    }

    /**
     * Lead funnel metrics.
     *
     * @return array<string, int>
     */
    public function funnel(): array
    {
        $tenantId = auth()->user()?->tenant_id;

        $scoping = fn ($q) => $q->when($tenantId !== null, fn ($c) => $c->where('tenant_id', $tenantId));

        // Touchpoints have no type discriminator; treat every touchpoint as an
        // impression and every recorded conversion as a click/conversion.
        $impressions = (int) \App\Models\Touchpoint::query()->tap($scoping)->count();
        $clicks = (int) \App\Models\ConversionEvent::query()->tap($scoping)->count();

        $clients = \App\Models\Client::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId));

        return [
            'impressions' => $impressions,
            'clicks' => $clicks,
            'leads' => (int) $clients->count(),
            'opportunities' => (int) (clone $clients)->where('health_score', '>=', 80)->count(),
            'closed_won' => (int) (clone $clients)->where('status', 'active')->count(),
        ];
    }
}
