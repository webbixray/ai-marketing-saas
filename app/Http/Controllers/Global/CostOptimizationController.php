<?php

namespace App\Http\Controllers\Global;

use App\Http\Controllers\Controller;
use App\Services\Global\CostOptimizationService;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CostOptimizationController extends Controller
{
    protected CostOptimizationService $costService;

    public function __construct(CostOptimizationService $costService)
    {
        $this->costService = $costService;
    }

    public function getCostOverview(Request $request): JsonResponse
    {
        $request->validate([
            'period' => 'nullable|in:day,week,month,quarter,year',
        ]);

        $tenant = auth()->user()->tenant;
        $period = $request->period ?? 'month';
        
        return response()->json([
            'data' => $this->costService->getCostOverview($tenant, $period),
        ]);
    }

    public function detectAnomalies(Request $request): JsonResponse
    {
        $request->validate([
            'period' => 'nullable|in:day,week,month,quarter,year',
        ]);

        $tenant = auth()->user()->tenant;
        $period = $request->period ?? 'month';
        
        return response()->json([
            'data' => $this->costService->detectAnomalies($tenant, $period),
        ]);
    }

    public function generateRightsizingRecommendations(): JsonResponse
    {
        $tenant = auth()->user()->tenant;
        
        return response()->json([
            'data' => $this->costService->generateRightsizingRecommendations($tenant),
        ]);
    }

    public function generateRiRecommendations(): JsonResponse
    {
        $tenant = auth()->user()->tenant;
        
        return response()->json([
            'data' => $this->costService->generateRiRecommendations($tenant),
        ]);
    }

    public function generateSavingsPlansRecommendations(): JsonResponse
    {
        $tenant = auth()->user()->tenant;
        
        return response()->json([
            'data' => $this->costService->generateSavingsPlansRecommendations($tenant),
        ]);
    }

    public function generateSpotRecommendations(): JsonResponse
    {
        $tenant = auth()->user()->tenant;
        
        return response()->json([
            'data' => $this->costService->generateSpotRecommendations($tenant),
        ]);
    }

    public function getAllRecommendations(): JsonResponse
    {
        $tenant = auth()->user()->tenant;
        
        return response()->json([
            'data' => $this->costService->getAllRecommendations($tenant),
        ]);
    }

    public function applyRecommendation(string $recommendationId, Request $request): JsonResponse
    {
        $request->validate([
            'approved' => 'boolean',
        ]);

        $tenant = auth()->user()->tenant;
        $result = $this->costService->applyRecommendation($tenant, $recommendationId, $request->only(['approved']));
        
        $statusCode = $result['success'] ? 200 : 400;
        return response()->json($result, $statusCode);
    }

    public function createBudget(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'period' => 'required|in:daily,weekly,monthly,quarterly,yearly',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
            'filters' => 'nullable|array',
            'alert_thresholds' => 'nullable|array',
            'alert_thresholds.*' => 'numeric|between:0,1',
            'alert_channels' => 'nullable|array',
            'alert_channels.*' => 'string',
        ]);

        $tenant = auth()->user()->tenant;
        $budget = $this->costService->createBudget($tenant, $request->validated());

        return response()->json([
            'message' => 'Budget created',
            'data' => $budget,
        ], 201);
    }

    public function getBudgets(): JsonResponse
    {
        $tenant = auth()->user()->tenant;
        
        return response()->json([
            'data' => $this->costService->getBudgets($tenant),
        ]);
    }

    public function checkBudgets(): JsonResponse
    {
        $tenant = auth()->user()->tenant;
        
        return response()->json([
            'data' => $this->costService->checkBudgets($tenant),
        ]);
    }

    public function generateCostReport(Request $request): JsonResponse
    {
        $request->validate([
            'period' => 'nullable|in:day,week,month,quarter,year',
            'format' => 'nullable|in:json,csv,pdf',
        ]);

        $tenant = auth()->user()->tenant;
        $period = $request->period ?? 'month';
        $options = $request->only(['format']);
        
        return response()->json([
            'data' => $this->costService->generateCostReport($tenant, $period, $options),
        ]);
    }

    public function getCostAllocation(Request $request): JsonResponse
    {
        $request->validate([
            'period' => 'nullable|in:day,week,month,quarter,year',
            'dimension' => 'nullable|string',
        ]);

        $tenant = auth()->user()->tenant;
        $period = $request->period ?? 'month';
        $dimension = $request->dimension ?? 'tag';
        
        return response()->json([
            'data' => $this->costService->getCostAllocation($tenant, $period, $dimension),
        ]);
    }

    public function getIdleResources(): JsonResponse
    {
        $tenant = auth()->user()->tenant;
        
        return response()->json([
            'data' => $this->costService->getIdleResources($tenant),
        ]);
    }
}
