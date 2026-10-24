<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Campaign;
use App\Models\Task;
use App\Models\Plan;
use Laravel\Cashier\Subscription;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

/**
 * @group Reporting & Analytics
 *
 * APIs for business intelligence, analytics, and reporting.
 */
class ReportingController extends Controller
{
    /**
     * Cache TTL for reports (5 minutes).
     */
    protected int $cacheTtl = 300;

    /**
     * Get revenue report (MRR and breakdown by plan).
     */
    public function revenue(Request $request)
    {
        $user = $request->user();

        $cacheKey = "report:revenue:tenant:{$user->tenant_id}";

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($user) {
            // Get active subscriptions, scoped to the requesting tenant.
            // (subscriptions carries tenant_id directly; tenant-less platform
            // admins are unscoped and see the whole platform.)
            $activeSubscriptions = Subscription::query()
                ->when($user->tenant_id, function ($query) use ($user) {
                    $query->where('tenant_id', $user->tenant_id);
                })
                ->where(function ($query) {
                    $query->whereNull('ends_at')
                        ->orWhere('ends_at', '>=', now());
                })
                ->get();

            // Calculate MRR and plan breakdown.
            //
            // DECISION: the monthly price is derived from the local plans
            // table, NOT from subscriptions.stripe_price. That column stores a
            // Stripe price ID string (e.g. "price_1AbCdEf…") — the old code
            // did `stripe_price / 100` assuming cents, which is a division of
            // a non-numeric string (ValueError on PHP 8) or a 0 — i.e. the
            // previous MRR was always 0 (or a 500) even with real subs.
            // Plans now carry amount/currency/interval (persisted by
            // PlanController), and Plan::monthlyAmount() normalizes any
            // billing interval to a monthly figure.
            $mrr = 0;
            $planBreakdown = [];

            foreach ($activeSubscriptions as $sub) {
                $plan = $this->resolvePlan($sub);
                // Unmatched subscriptions contribute 0 — no fake math.
                $amount = $plan ? $plan->monthlyAmount() : 0.0;
                $mrr += $amount;

                $planName = $plan ? $plan->name : 'unknown';
                if (!isset($planBreakdown[$planName])) {
                    $planBreakdown[$planName] = ['count' => 0, 'amount' => 0];
                }
                $planBreakdown[$planName]['count'] += 1;
                $planBreakdown[$planName]['amount'] += $amount;
            }

            // Format plan breakdown for response
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
        });
    }

    /**
     * Resolve the local Plan for a Cashier subscription.
     *
     * Primary match: subscriptions.stripe_price = plans.stripe_price_id
     * (both hold the Stripe price ID). Fallback: plan name matching the
     * subscription's name/type. Returns null when no local plan matches —
     * callers contribute 0 to MRR rather than inventing a number.
     */
    private function resolvePlan(Subscription $sub): ?Plan
    {
        if (! empty($sub->stripe_price)) {
            $plan = Plan::where('stripe_price_id', $sub->stripe_price)->first();

            if ($plan) {
                return $plan;
            }
        }

        $names = array_filter([$sub->name, $sub->type]);

        foreach ($names as $name) {
            $plan = Plan::whereRaw('LOWER(name) = ?', [strtolower((string) $name)])->first();

            if ($plan) {
                return $plan;
            }
        }

        return null;
    }

    /**
     * Get the date part selection based on the database driver.
     *
     * @param  string  $column
     * @param  string  $part   'year' or 'month'
     * @return string
     */
    private function datePartSelect(string $column, string $part): string
    {
        $driver = DB::connection()->getConfig('driver');

        if ($driver === 'sqlite') {
            // SQLite uses strftime
            $format = $part === 'year' ? '%Y' : '%m';
            return "strftime('{$format}', {$column})";
        }

        // For PostgreSQL and MySQL, DATE_PART works (MySQL also supports it as a synonym for EXTRACT)
        return "DATE_PART('{$part}', {$column})";
    }

    /**
     * Get user growth over the last 6 months.
     */
    public function userGrowth(Request $request)
    {
        $user = $request->user();

        $cacheKey = "report:user_growth:tenant:{$user->tenant_id}";

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($user) {
            $sixMonthsAgo = Carbon::now()->subMonths(6);

            $usersByMonth = User::where('created_at', '>=', $sixMonthsAgo)
                ->selectRaw(
                    $this->datePartSelect('created_at', 'year') . ' as year, ' .
                    $this->datePartSelect('created_at', 'month') . ' as month, ' .
                    'COUNT(*) as count'
                )
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
        });
    }

    /**
     * Get subscription growth over the last 6 months.
     */
    public function subscriptionGrowth(Request $request)
    {
        $user = $request->user();

        $cacheKey = "report:subscription_growth:tenant:{$user->tenant_id}";

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($user) {
            $sixMonthsAgo = Carbon::now()->subMonths(6);

            $subscriptionsByMonth = Subscription::where('created_at', '>=', $sixMonthsAgo)
                ->selectRaw(
                    $this->datePartSelect('created_at', 'year') . ' as year, ' .
                    $this->datePartSelect('created_at', 'month') . ' as month, ' .
                    'COUNT(*) as count'
                )
                ->groupBy('year', 'month')
                ->orderBy('year')
                ->orderBy('month')
                ->get()
                ->map(function ($row) {
                    return [
                        'period' => sprintf('%04d-%02d', $row->year, $row->month),
                        'new_subscriptions' => (int) $row->count,
                    ];
                });

            return [
                'subscription_growth' => $subscriptionsByMonth,
            ];
        });
    }

    /**
     * Get revenue trend (MRR) over the last 6 months.
     * Approximation: sum of subscription prices active each month.
     * For simplicity, we'll compute MRR at the end of each month.
     */
    public function revenueTrend(Request $request)
    {
        $user = $request->user();

        $cacheKey = "report:revenue_trend:tenant:{$user->tenant_id}";

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($user) {
            $sixMonthsAgo = Carbon::now()->subMonths(5); // inclusive of current month
            $trend = [];

            for ($i = 0; $i < 6; $i++) {
                $month = Carbon::now()->subMonths($i)->startOfDay();
                $nextMonth = $month->copy()->addMonth()->startOfDay();

                // Subscriptions active during this month: those that started before nextMonth and (ended after month or no end)
                $active = Subscription::query()
                    ->when($user->tenant_id, function ($query) use ($user) {
                        $query->where('tenant_id', $user->tenant_id);
                    })
                    ->where('created_at', '<', $nextMonth)
                    ->where(function ($query) use ($month) {
                        $query->whereNull('ends_at')
                            ->orWhere('ends_at', '>=', $month);
                    })
                    ->get();

                $mrr = 0;
                foreach ($active as $sub) {
                    // Same pricing decision as revenue(): local plans table is
                    // the source of truth; subscriptions.stripe_price is a
                    // price ID string, not a number.
                    $plan = $this->resolvePlan($sub);
                    $mrr += $plan ? $plan->monthlyAmount() : 0.0;
                }

                $trend[] = [
                    'period' => $month->format('Y-m'),
                    'mrr' => round($mrr, 2),
                ];
            }

            // Reverse to show oldest first
            $trend = array_reverse($trend);

            return [
                'revenue_trend' => $trend,
            ];
        });
    }

    /**
     * Get task statistics.
     */
    public function taskStats(Request $request)
    {
        $user = $request->user();

        $cacheKey = "report:task_stats:tenant:{$user->tenant_id}";

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($user) {
            $taskStats = Task::selectRaw(
                        "COUNT(*) as total,
                         SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                         SUM(CASE WHEN status != 'completed' THEN 1 ELSE 0 END) as pending"
                    )->first();

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
        });
    }

    /**
     * Get task completion trend over the last 6 months.
     */
    public function taskCompletionTrend(Request $request)
    {
        $user = $request->user();

        $cacheKey = "report:task_completion_trend:tenant:{$user->tenant_id}";

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($user) {
            $sixMonthsAgo = Carbon::now()->subMonths(5);
            $trend = [];

            for ($i = 0; $i < 6; $i++) {
                $month = Carbon::now()->subMonths($i)->startOfDay();
                $nextMonth = $month->copy()->addMonth()->startOfDay();

                $completed = Task::where('status', 'completed')
                    ->where('updated_at', '>=', $month)
                    ->where('updated_at', '<', $nextMonth)
                    ->count();

                $trend[] = [
                    'period' => $month->format('Y-m'),
                    'completed_tasks' => (int) $completed,
                ];
            }

            $trend = array_reverse($trend);

            return [
                'task_completion_trend' => $trend,
            ];
        });
    }

    /**
     * Get campaign statistics.
     */
    public function campaignStats(Request $request)
    {
        $user = $request->user();

        $cacheKey = "report:campaign_stats:tenant:{$user->tenant_id}";

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($user) {
            $campaignStats = Campaign::selectRaw(
                        "COUNT(*) as total,
                         SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                         SUM(CASE WHEN status = 'paused' THEN 1 ELSE 0 END) as paused,
                         SUM(CASE WHEN status IN ('completed', 'cancelled', 'archived') THEN 1 ELSE 0 END) as archived"
                    )->first();

            return [
                'campaign_stats' => [
                    'total' => (int) $campaignStats->total,
                    'active' => (int) $campaignStats->active,
                    'paused' => (int) $campaignStats->paused,
                    'archived' => (int) $campaignStats->archived,
                ],
            ];
        });
    }

    /**
     * Get campaign creation trend over the last 6 months.
     */
    public function campaignCreationTrend(Request $request)
    {
        $user = $request->user();

        $cacheKey = "report:campaign_creation_trend:tenant:{$user->tenant_id}";

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($user) {
            $sixMonthsAgo = Carbon::now()->subMonths(5);
            $trend = [];

            for ($i = 0; $i < 6; $i++) {
                $month = Carbon::now()->subMonths($i)->startOfDay();
                $nextMonth = $month->copy()->addMonth()->startOfDay();

                $created = Campaign::where('created_at', '>=', $month)
                    ->where('created_at', '<', $nextMonth)
                    ->count();

                $trend[] = [
                    'period' => $month->format('Y-m'),
                    'created_campaigns' => (int) $created,
                ];
            }

            $trend = array_reverse($trend);

            return [
                'campaign_creation_trend' => $trend,
            ];
        });
    }

    /**
     * Get user activity: count of users who have updated their profile in the last 30 days.
     */
    public function userActivity(Request $request)
    {
        $user = $request->user();

        $cacheKey = "report:user_activity:tenant:{$user->tenant_id}";

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($user) {
            $thirtyDaysAgo = Carbon::now()->subDays(30);

            $activeUsers = User::where('updated_at', '>=', $thirtyDaysAgo)->count();

            return [
                'active_users_last_30_days' => (int) $activeUsers,
            ];
        });
    }
}
