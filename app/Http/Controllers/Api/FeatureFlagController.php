<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FeatureFlag;
use App\Services\FeatureFlagService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * @group Feature Flags
 *
 * APIs for managing feature flags.
 */
class FeatureFlagController extends Controller
{
    public function __construct(
        protected FeatureFlagService $featureFlagService
    ) {}

    /**
     * Display a listing of the feature flags.
     */
    public function index(Request $request)
    {
        $query = FeatureFlag::with(['creator:id,name,email', 'updater:id,name,email']);

        // Filter by enabled status
        if ($request->has('enabled')) {
            $query->where('enabled', $request->boolean('enabled'));
        }

        // Search by key or name
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('key', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            });
        }

        $flags = $query->orderBy('key')->paginate($request->input('per_page', 20));

        return response()->json($flags);
    }

    /**
     * Store a newly created feature flag.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'key' => 'required|string|max:100|unique:feature_flags,key|regex:/^[a-z0-9_]+$/',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'enabled' => 'boolean',
            'conditions' => 'nullable|array',
            'metadata' => 'nullable|array',
        ]);

        $flag = $this->featureFlagService->create($validated, $request->user());

        return response()->json($flag, 201);
    }

    /**
     * Display the specified feature flag.
     */
    public function show(FeatureFlag $featureFlag)
    {
        $featureFlag->load(['creator:id,name,email', 'updater:id,name,email']);
        
        return response()->json($featureFlag);
    }

    /**
     * Update the specified feature flag.
     */
    public function update(Request $request, FeatureFlag $featureFlag)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'enabled' => 'sometimes|boolean',
            'conditions' => 'nullable|array',
            'metadata' => 'nullable|array',
        ]);

        $flag = $this->featureFlagService->update($featureFlag->key, $validated, $request->user());

        return response()->json($flag);
    }

    /**
     * Enable a feature flag.
     */
    public function enable(FeatureFlag $featureFlag)
    {
        $this->featureFlagService->enable($featureFlag->key, request()->user());
        
        return response()->json([
            'message' => 'Feature flag enabled',
            'feature_flag' => $featureFlag->fresh(),
        ]);
    }

    /**
     * Disable a feature flag.
     */
    public function disable(FeatureFlag $featureFlag)
    {
        $this->featureFlagService->disable($featureFlag->key, request()->user());
        
        return response()->json([
            'message' => 'Feature flag disabled',
            'feature_flag' => $featureFlag->fresh(),
        ]);
    }

    /**
     * Remove the specified feature flag.
     */
    public function destroy(FeatureFlag $featureFlag)
    {
        $this->featureFlagService->delete($featureFlag->key);
        
        return response()->json(null, 204);
    }

    /**
     * Check if a feature flag is enabled for current context.
     */
    public function check(Request $request)
    {
        $request->validate([
            'key' => 'required|string',
        ]);

        $enabled = $this->featureFlagService->isEnabled(
            $request->key,
            $request->user(),
            $request->user()?->tenant_id
        );

        return response()->json([
            'key' => $request->key,
            'enabled' => $enabled,
        ]);
    }

    /**
     * Get all enabled flags for current context.
     */
    public function enabledForContext(Request $request)
    {
        $flags = $this->featureFlagService->getEnabledForContext(
            $request->user(),
            $request->user()?->tenant_id
        );

        return response()->json([
            'flags' => $flags,
        ]);
    }

    /**
     * Clear all caches.
     */
    public function clearCache()
    {
        $this->featureFlagService->clearAllCaches();
        
        return response()->json([
            'message' => 'Feature flag caches cleared',
        ]);
    }
}
