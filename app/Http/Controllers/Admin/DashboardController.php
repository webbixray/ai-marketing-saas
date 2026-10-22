<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = $this->stats();
        $charts = $this->charts();
        $funnel = $this->funnel();
        
        return view('admin.dashboard', [
            'stats' => $stats,
            'charts' => $charts,
            'funnel' => $funnel,
        ]);
    }

    public function stats()
    {
        $tenant = auth()->user()->tenant;
        
        $stats = [
            'total_campaigns' => \App\Models\Campaign::where('tenant_id', $tenant->id)->count(),
            'active_campaigns' => \App\Models\Campaign::where('tenant_id', $tenant->id)
                ->where('status', 'active')->count(),
            'total_clients' => \App\Models\Client::where('tenant_id', $tenant->id)->count(),
            'total_spend' => \App\Models\Campaign::where('tenant_id', $tenant->id)
                ->sum('budget') ?? 0,
            'total_revenue' => \App\Models\ConversionEvent::whereHas('campaign', function($q) use ($tenant) {
                $q->where('campaigns.tenant_id', $tenant->id);
            })->sum('revenue') ?? 0,
            'cac' => \App\Models\Campaign::where('tenant_id', $tenant->id)->avg('cac') ?? 0,
            'roas' => number_format((\App\Models\Campaign::where('tenant_id', $tenant->id)->sum('budget') ?? 1) > 0 
                ? (\App\Models\ConversionEvent::whereHas('campaign', function($q) use ($tenant) {
                    $q->where('campaigns.tenant_id', $tenant->id);
                })->sum('revenue') ?? 0) / \App\Models\Campaign::where('tenant_id', $tenant->id)->sum('budget') ?? 1 
                : 0, 2),
            'conversion_rate' => number_format(\App\Models\ConversionEvent::whereHas('campaign', function($q) use ($tenant) {
                $q->where('campaigns.tenant_id', $tenant->id);
            })->count() / max(\App\Models\Campaign::where('tenant_id', $tenant->id)->count(), 1) * 100, 2),
        ];
        
        return $stats;
    }

    public function charts($period = '30d')
    {
        $dateFilter = match($period) {
            '7d' => DB::raw('created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)'),
            '30d' => DB::raw('created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)'),
            '90d' => DB::raw('created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)'),
            default => DB::raw('created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)')
        };
        
        $charts = [
            'spend_over_time' => \App\Models\Campaign::where('tenant_id', auth()->user()->tenant->id)
                ->selectRaw('DATE(created_at) as date, SUM(budget) as daily_spend')
                ->where($dateFilter)
                ->groupBy('date')
                ->orderBy('date')
                ->get()
                ->toArray(),
            'campaigns_by_channel' => \App\Models\Campaign::where('tenant_id', auth()->user()->tenant->id)
                ->selectRaw('channel, COUNT(*) as count')
                ->groupBy('channel')
                ->get()
                ->keyBy('channel')
                ->toArray(),
            'top_campaigns' => \App\Models\Campaign::where('tenant_id', auth()->user()->tenant->id)
                ->with('client')
                ->orderBy('budget', 'desc')
                ->take(5)
                ->get()
                ->toArray(),
        ];
        
        return $charts;
    }

    public function funnel()
    {
        $funnel = [
            'impressions' => 0,  // Would need touchpoints table
            'clicks' => 0,
            'leads' => \App\Models\Client::where('tenant_id', auth()->user()->tenant->id)->count(),
            'opportunities' => \App\Models\Client::where('health_score', '>=', 80)
                ->where('tenant_id', auth()->user()->tenant->id)->count(),
            'closed_won' => \App\Models\Client::where('status', 'active')
                ->where('tenant_id', auth()->user()->tenant->id)->count(),
        ];
        
        return $funnel;
    }
}
