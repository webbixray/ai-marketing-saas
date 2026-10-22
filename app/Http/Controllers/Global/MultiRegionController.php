<?php

namespace App\Http\Controllers\Global;

use App\Http\Controllers\Controller;
use App\Services\Global\MultiRegionService;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MultiRegionController extends Controller
{
    protected MultiRegionService $multiRegionService;

    public function __construct(MultiRegionService $multiRegionService)
    {
        $this->multiRegionService = $multiRegionService;
    }

    public function getRegions(): JsonResponse
    {
        return response()->json([
            'data' => $this->multiRegionService->getRegions(),
        ]);
    }

    public function getActiveRegions(): JsonResponse
    {
        return response()->json([
            'data' => $this->multiRegionService->getActiveRegions(),
        ]);
    }

    public function getRegion(string $regionCode): JsonResponse
    {
        $region = $this->multiRegionService->getRegion($regionCode);
        
        if (!$region) {
            return response()->json(['error' => 'Region not found'], 404);
        }

        return response()->json(['data' => $region]);
    }

    public function getRegionStatus(): JsonResponse
    {
        return response()->json([
            'data' => $this->multiRegionService->getRegionStatus(),
        ]);
    }

    public function performHealthChecks(): JsonResponse
    {
        $results = $this->multiRegionService->performHealthChecks();
        
        return response()->json([
            'data' => $results,
        ]);
    }

    public function getReplicationStatus(): JsonResponse
    {
        return response()->json([
            'data' => $this->multiRegionService->getReplicationStatus(),
        ]);
    }

    public function assignTenantToRegion(Request $request): JsonResponse
    {
        $request->validate([
            'tenant_id' => 'required|exists:tenants,id',
            'requirements' => 'nullable|array',
            'requirements.preferred_region' => 'nullable|string',
            'requirements.data_residency' => 'nullable|array',
            'requirements.compliance' => 'nullable|array',
            'requirements.latency_optimize' => 'boolean',
        ]);

        $tenant = Tenant::findOrFail($request->tenant_id);
        $result = $this->multiRegionService->assignTenantToRegion($tenant, $request->input('requirements', []));

        return response()->json($result);
    }

    public function getTenantMigrations(): JsonResponse
    {
        $tenant = auth()->user()->tenant;
        $migrations = $this->multiRegionService->getTenantMigrations($tenant->id);

        return response()->json([
            'data' => $migrations,
        ]);
    }

    public function getMigrationStatus(string $migrationId): JsonResponse
    {
        $status = $this->multiRegionService->getMigrationStatus($migrationId);
        
        if (!$status) {
            return response()->json(['error' => 'Migration not found'], 404);
        }

        return response()->json(['data' => $status]);
    }

    public function getRoutingConfig(Request $request): JsonResponse
    {
        $context = $request->only(['country', 'user_id', 'session_id']);
        $config = $this->multiRegionService->getRoutingConfig($context);

        return response()->json([
            'data' => $config,
        ]);
    }

    public function initiateDrDrill(): JsonResponse
    {
        $result = $this->multiRegionService->initiateDrDrill();

        return response()->json($result);
    }

    public function getDrDrillHistory(): JsonResponse
    {
        return response()->json([
            'data' => $this->multiRegionService->getDrDrillHistory(),
        ]);
    }
}
