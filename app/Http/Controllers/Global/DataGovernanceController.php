<?php

namespace App\Http\Controllers\Global;

use App\Http\Controllers\Controller;
use App\Services\Global\DataGovernanceService;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DataGovernanceController extends Controller
{
    protected DataGovernanceService $dataGovernanceService;

    public function __construct(DataGovernanceService $dataGovernanceService)
    {
        $this->dataGovernanceService = $dataGovernanceService;
    }

    public function getDashboard(): JsonResponse
    {
        $tenant = auth()->user()->tenant;
        
        return response()->json([
            'data' => $this->dataGovernanceService->getDashboard($tenant),
        ]);
    }

    public function registerAsset(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:table,view,file,api,stream',
            'source' => 'required|string|max:500',
            'schema' => 'nullable|array',
            'classification' => 'nullable|in:public,internal,confidential,restricted',
            'steward' => 'nullable|exists:users,id',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'retention_policy' => 'nullable|array',
        ]);

        $tenant = auth()->user()->tenant;
        $asset = $this->dataGovernanceService->registerAsset($tenant, $request->validated());

        return response()->json([
            'message' => 'Asset registered',
            'data' => $asset,
        ], 201);
    }

    public function getAssets(Request $request): JsonResponse
    {
        $tenant = auth()->user()->tenant;
        
        $filters = $request->only(['type', 'classification', 'search', 'tags', 'owner_id']);
        $pagination = $request->only(['page', 'per_page', 'sort_by', 'sort_order']);

        return response()->json(
            $this->dataGovernanceService->getAssets($tenant, $filters, $pagination)
        );
    }

    public function getAsset(string $assetId): JsonResponse
    {
        $asset = $this->dataGovernanceService->getAsset($assetId);
        
        if (!$asset) {
            return response()->json(['error' => 'Asset not found'], 404);
        }

        return response()->json(['data' => $asset]);
    }

    public function classifyAsset(string $assetId): JsonResponse
    {
        $result = $this->dataGovernanceService->classifyAsset($assetId);
        
        if (!$result['success']) {
            return response()->json(['error' => $result['error']], 404);
        }

        return response()->json($result);
    }

    public function getLineage(string $assetId, Request $request): JsonResponse
    {
        $depth = $request->get('depth', 3);
        
        return response()->json([
            'data' => $this->dataGovernanceService->getLineage($assetId, $depth),
        ]);
    }

    public function createQualityRule(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'asset_id' => 'nullable|exists:data_catalog,id',
            'type' => 'required|in:completeness,uniqueness,validity,accuracy,consistency,timeliness',
            'dimension' => 'required|string|max:100',
            'condition' => 'nullable|array',
            'threshold' => 'nullable|numeric|between:0,1',
            'severity' => 'nullable|in:info,warning,critical',
            'schedule' => 'nullable|in:hourly,daily,weekly,manual',
        ]);

        $tenant = auth()->user()->tenant;
        $rule = $this->dataGovernanceService->createQualityRule($tenant, $request->validated());

        return response()->json([
            'message' => 'Quality rule created',
            'data' => $rule,
        ], 201);
    }

    public function runQualityChecks(Request $request): JsonResponse
    {
        $request->validate([
            'asset_id' => 'nullable|exists:data_catalog,id',
        ]);

        $tenant = auth()->user()->tenant;
        $assetId = $request->asset_id;

        return response()->json([
            'data' => $this->dataGovernanceService->runQualityChecks($tenant->id, $assetId),
        ]);
    }

    public function applyMasking(Request $request): JsonResponse
    {
        $request->validate([
            'asset_id' => 'required|exists:data_catalog,id',
            'rules' => 'required|array|min:1',
            'rules.*.field' => 'required|string',
            'rules.*.type' => 'required|in:mask,hash,encrypt,tokenize,redact,partial',
            'rules.*.config' => 'nullable|array',
        ]);

        $tenant = auth()->user()->tenant;
        $result = $this->dataGovernanceService->applyMasking(
            $tenant,
            $request->asset_id,
            $request->rules
        );

        return response()->json($result);
    }

    public function getMaskingRules(string $assetId): JsonResponse
    {
        return response()->json([
            'data' => $this->dataGovernanceService->getMaskingRules($assetId),
        ]);
    }

    public function createRetentionPolicy(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'asset_types' => 'nullable|array',
            'asset_types.*' => 'string',
            'classification' => 'nullable|array',
            'classification.*' => 'in:public,internal,confidential,restricted',
            'retention_days' => 'required|integer|min:1',
            'action' => 'required|in:delete,archive,anonymize',
            'archive_location' => 'nullable|string',
            'legal_hold_exempt' => 'boolean',
        ]);

        $tenant = auth()->user()->tenant;
        $policy = $this->dataGovernanceService->createRetentionPolicy($tenant, $request->validated());

        return response()->json([
            'message' => 'Retention policy created',
            'data' => $policy,
        ], 201);
    }

    public function applyRetentionPolicies(): JsonResponse
    {
        $tenant = auth()->user()->tenant;
        
        return response()->json([
            'data' => $this->dataGovernanceService->applyRetentionPolicies($tenant),
        ]);
    }

    public function handleDataSubjectRequest(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|in:access,rectification,erasure,portability,restriction,objection',
            'email' => 'required|email',
            'user_id' => 'nullable|exists:users,id',
            'corrections' => 'nullable|array',
        ]);

        $tenant = auth()->user()->tenant;
        $result = $this->dataGovernanceService->handleDataSubjectRequest(
            $tenant,
            $request->type,
            $request->all()
        );

        return response()->json($result);
    }
}
