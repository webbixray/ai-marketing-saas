<?php

namespace App\Http\Controllers\Global;

use App\Http\Controllers\Controller;
use App\Services\Global\AdvancedAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AnalyticsController extends Controller
{
    protected AdvancedAnalyticsService $analyticsService;

    public function __construct(AdvancedAnalyticsService $analyticsService)
    {
        $this->analyticsService = $analyticsService;
    }

    public function trackEvent(Request $request): JsonResponse
    {
        $request->validate([
            'event_name' => 'required|string|max:100',
            'properties' => 'nullable|array',
            'context' => 'nullable|array',
        ]);

        $this->analyticsService->trackEvent(
            $request->event_name,
            $request->properties ?? [],
            $request->context ?? []
        );

        return response()->json(['message' => 'Event tracked']);
    }

    public function getRealtimeMetrics(Request $request): JsonResponse
    {
        $request->validate([
            'metrics' => 'nullable|array',
            'metrics.*' => 'string',
            'window_minutes' => 'nullable|integer|min:1|max:60',
        ]);

        $tenantId = auth()->user()->tenant_id;
        $metrics = $request->input('metrics', []);
        $windowMinutes = $request->input('window_minutes', 5);

        return response()->json([
            'data' => $this->analyticsService->getRealtimeMetrics($tenantId, $metrics, $windowMinutes),
        ]);
    }

    public function getDashboardData(string $dashboardId, Request $request): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        $params = $request->only(['period', 'start_date', 'end_date']);

        return response()->json([
            'data' => $this->analyticsService->getDashboardData($tenantId, $dashboardId, $params),
        ]);
    }

    public function getTenantDashboards(): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;

        return response()->json([
            'data' => $this->analyticsService->getTenantDashboards($tenantId),
        ]);
    }

    public function createDashboard(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'widgets' => 'required|array',
            'layout' => 'nullable|array',
            'is_default' => 'boolean',
        ]);

        $tenantId = auth()->user()->tenant_id;
        $dashboard = $this->analyticsService->createDashboard($tenantId, $request->validated());

        return response()->json([
            'message' => 'Dashboard created',
            'data' => $dashboard,
        ], 201);
    }

    public function updateDashboard(string $dashboardId, Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'widgets' => 'nullable|array',
            'layout' => 'nullable|array',
            'is_default' => 'boolean',
        ]);

        $dashboard = $this->analyticsService->updateDashboard($dashboardId, $request->validated());

        return response()->json([
            'message' => 'Dashboard updated',
            'data' => $dashboard,
        ]);
    }

    public function deleteDashboard(string $dashboardId): JsonResponse
    {
        $this->analyticsService->deleteDashboard($dashboardId);

        return response()->json(['message' => 'Dashboard deleted']);
    }

    public function runAnomalyDetection(Request $request): JsonResponse
    {
        $request->validate([
            'metric' => 'required|string',
            'period' => 'nullable|string|in:day,week,month',
        ]);

        $tenantId = auth()->user()->tenant_id;
        $metric = $request->metric;
        $period = $request->period ?? 'day';

        return response()->json([
            'data' => $this->analyticsService->runAnomalyDetection($tenantId, $metric, $period),
        ]);
    }

    public function runForecasting(Request $request): JsonResponse
    {
        $request->validate([
            'metric' => 'required|string',
            'horizon_days' => 'nullable|integer|min:1|max:365',
        ]);

        $tenantId = auth()->user()->tenant_id;
        $metric = $request->metric;
        $horizonDays = $request->input('horizon_days', 30);

        return response()->json([
            'data' => $this->analyticsService->runForecasting($tenantId, $metric, $horizonDays),
        ]);
    }

    public function generateInsights(): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;

        return response()->json([
            'data' => $this->analyticsService->generateInsights($tenantId),
        ]);
    }

    public function exportData(Request $request): JsonResponse
    {
        $request->validate([
            'format' => 'required|in:csv,json,xlsx,pdf',
            'metrics' => 'nullable|array',
            'metrics.*' => 'string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
        ]);

        $tenantId = auth()->user()->tenant_id;
        $params = $request->validated();

        return response()->json(
            $this->analyticsService->exportData($tenantId, $params)
        );
    }

    public function getCostAllocation(Request $request): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        $period = $request->get('period', 'month');
        $dimension = $request->get('dimension', 'tag');

        return response()->json([
            'data' => $this->analyticsService->getCostAllocation($tenantId, $period, $dimension),
        ]);
    }

    public function getIdleResources(): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;

        return response()->json([
            'data' => $this->analyticsService->getIdleResources($tenantId),
        ]);
    }
}
