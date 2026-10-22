<?php

namespace App\Http\Controllers\Global;

use App\Http\Controllers\Controller;
use App\Services\Global\DisasterRecoveryService;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DisasterRecoveryController extends Controller
{
    protected DisasterRecoveryService $drService;

    public function __construct(DisasterRecoveryService $drService)
    {
        $this->drService = $drService;
    }

    public function getDashboard(): JsonResponse
    {
        $tenant = auth()->user()->tenant;
        
        return response()->json([
            'data' => $this->drService->getDashboard($tenant),
        ]);
    }

    public function createBackupPlan(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'databases' => 'nullable|array',
            'databases.*' => 'string',
            'tables' => 'nullable|array',
            'tables.*' => 'string',
            'exclude_tables' => 'nullable|array',
            'exclude_tables.*' => 'string',
            'storage' => 'nullable|string',
            'retention' => 'nullable|array',
            'compression' => 'boolean',
            'encryption' => 'boolean',
            'schedule' => 'nullable|string',
            'verify' => 'boolean',
        ]);

        $tenant = auth()->user()->tenant;
        $plan = $this->drService->createBackupPlan($tenant, $request->validated());

        return response()->json([
            'message' => 'Backup plan created',
            'data' => $plan,
        ], 201);
    }

    public function getBackupPlans(): JsonResponse
    {
        $tenant = auth()->user()->tenant;
        
        return response()->json([
            'data' => $this->drService->getBackupPlans($tenant),
        ]);
    }

    public function runBackup(string $planId): JsonResponse
    {
        $result = $this->drService->runBackup($planId);
        
        $statusCode = $result['success'] ? 200 : 400;
        return response()->json($result, $statusCode);
    }

    public function listBackups(Request $request): JsonResponse
    {
        $tenant = auth()->user()->tenant;
        $filters = $request->only(['status', 'plan_id', 'start_date', 'end_date', 'page', 'per_page']);
        
        return response()->json(
            $this->drService->listBackups($tenant, $filters)
        );
    }

    public function getBackup(string $backupId): JsonResponse
    {
        $backup = $this->drService->getBackup($backupId);
        
        if (!$backup) {
            return response()->json(['error' => 'Backup not found'], 404);
        }

        return response()->json(['data' => $backup]);
    }

    public function verifyBackup(string $backupId): JsonResponse
    {
        $result = $this->drService->verifyBackup($backupId);
        
        return response()->json($result);
    }

    public function restoreBackup(Request $request): JsonResponse
    {
        $request->validate([
            'backup_id' => 'required|exists:dr_backups,id',
            'target_database' => 'nullable|string',
            'point_in_time' => 'nullable|date',
            'tables' => 'nullable|array',
            'tables.*' => 'string',
        ]);

        $result = $this->drService->restoreBackup($request->backup_id, $request->only(['target_database', 'point_in_time', 'tables']));
        
        $statusCode = $result['success'] ? 200 : 400;
        return response()->json($result, $statusCode);
    }

    public function getRestoreStatus(string $restoreId): JsonResponse
    {
        $status = $this->drService->getRestoreStatus($restoreId);
        
        if (!$status) {
            return response()->json(['error' => 'Restore not found'], 404);
        }

        return response()->json(['data' => $status]);
    }

    public function initiateFailover(Request $request): JsonResponse
    {
        $request->validate([
            'primary_region' => 'required|string',
            'backup_region' => 'required|string',
            'reason' => 'nullable|string|max:500',
        ]);

        $result = $this->drService->initiateFailover(
            $request->primary_region,
            $request->backup_region,
            $request->reason ?? 'Manual failover'
        );
        
        $statusCode = $result['success'] ? 200 : 400;
        return response()->json($result, $statusCode);
    }

    public function getFailoverHistory(): JsonResponse
    {
        return response()->json([
            'data' => $this->drService->getDrDrillHistory(), // Reusing for failover history
        ]);
    }

    public function initiateDrDrill(Request $request): JsonResponse
    {
        $request->validate([
            'scope' => 'nullable|in:full,partial,tabletop',
            'regions' => 'nullable|array',
            'regions.*' => 'string',
            'scheduled_at' => 'nullable|date',
        ]);

        $options = $request->only(['scope', 'regions', 'scheduled_at']);
        $result = $this->drService->initiateDrDrill($options);
        
        $statusCode = $result['success'] ? 200 : 400;
        return response()->json($result, $statusCode);
    }

    public function startDrDrill(string $drillId): JsonResponse
    {
        $result = $this->drService->startDrDrill($drillId);
        
        $statusCode = $result['success'] ? 200 : 400;
        return response()->json($result, $statusCode);
    }

    public function getDrDrillHistory(): JsonResponse
    {
        return response()->json([
            'data' => $this->drService->getDrDrillHistory(),
        ]);
    }
}
