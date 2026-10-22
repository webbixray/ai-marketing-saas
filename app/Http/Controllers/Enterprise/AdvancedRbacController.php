<?php

namespace App\Http\Controllers\Enterprise;

use App\Http\Controllers\Controller;
use App\Http\Requests\Enterprise\AssignRoleWithConditionsRequest;
use App\Http\Requests\Enterprise\DelegatePermissionRequest;
use App\Http\Requests\Enterprise\GrantEmergencyAccessRequest;
use App\Http\Requests\Enterprise\PerformAccessReviewRequest;
use App\Services\Enterprise\AdvancedRbacService;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AdvancedRbacController extends Controller
{
    protected AdvancedRbacService $rbacService;

    public function __construct(AdvancedRbacService $rbacService)
    {
        $this->rbacService = $rbacService;
    }

    /**
     * Define a resource with fine-grained permissions.
     */
    public function defineResource(Request $request): JsonResponse
    {
        $request->validate([
            'resource' => 'required|string|max:100',
            'actions' => 'required|array|min:1',
            'actions.*' => 'string|max:50',
            'attributes' => 'nullable|array',
        ]);

        $result = $this->rbacService->defineResource(
            $request->resource,
            $request->actions,
            $request->attributes ?? []
        );

        return response()->json([
            'message' => 'Resource defined',
            'data' => $result,
        ]);
    }

    /**
     * Create a role.
     */
    public function createRole(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:100|unique:roles,name',
            'permissions' => 'required|array',
            'permissions.*' => 'string',
            'metadata' => 'nullable|array',
        ]);

        $role = $this->rbacService->createRole(
            $request->name,
            $request->permissions,
            $request->metadata ?? []
        );

        return response()->json([
            'message' => 'Role created',
            'data' => $role,
        ], 201);
    }

    /**
     * Create a composite role.
     */
    public function createCompositeRole(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:100|unique:roles,name',
            'child_roles' => 'required|array|min:1',
            'child_roles.*' => 'string|exists:roles,name',
            'metadata' => 'nullable|array',
        ]);

        $role = $this->rbacService->createCompositeRole(
            $request->name,
            $request->child_roles,
            $request->metadata ?? []
        );

        return response()->json([
            'message' => 'Composite role created',
            'data' => $role,
        ], 201);
    }

    /**
     * Assign role with conditions (ABAC).
     */
    public function assignRoleWithConditions(AssignRoleWithConditionsRequest $request): JsonResponse
    {
        $user = User::findOrFail($request->input('user_id'));
        $roleName = $request->input('role');
        $conditions = $request->input('conditions', []);
        $assignedBy = auth()->user();

        $result = $this->rbacService->assignRoleWithConditions($user, $roleName, $conditions, $assignedBy);

        return response()->json([
            'message' => 'Role assigned',
            'data' => $result,
        ]);
    }

    /**
     * Check if user has permission.
     */
    public function checkPermission(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'permission' => 'required|string',
            'context' => 'nullable|array',
        ]);

        $user = User::findOrFail($request->input('user_id'));
        $permission = $request->input('permission');
        $context = $request->input('context', []);

        $hasPermission = $this->rbacService->checkPermission($user, $permission, $context);

        return response()->json([
            'has_permission' => $hasPermission,
        ]);
    }

    /**
     * Delegate permission.
     */
    public function delegatePermission(DelegatePermissionRequest $request): JsonResponse
    {
        $delegator = auth()->user();
        $delegatee = User::findOrFail($request->input('delegatee_id'));
        $permission = $request->input('permission');
        $options = $request->only(['conditions', 'expires_at', 'revocable']);

        $result = $this->rbacService->delegatePermission($delegator, $delegatee, $permission, $options);

        return response()->json($result);
    }

    /**
     * Revoke delegation.
     */
    public function revokeDelegation(int $delegationId): JsonResponse
    {
        $revoker = auth()->user();
        $result = $this->rbacService->revokeDelegation($delegationId, $revoker);

        if (!$result) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to revoke delegation',
            ], 400);
        }

        return response()->json([
            'message' => 'Delegation revoked',
        ]);
    }

    /**
     * Get user's delegations.
     */
    public function getUserDelegations(int $userId): JsonResponse
    {
        $delegations = \DB::table('permission_delegations')
            ->where('delegatee_id', $userId)
            ->where('expires_at', '>', now())
            ->whereNull('revoked_at')
            ->with(['delegator:id,name,email'])
            ->get();

        return response()->json([
            'data' => $delegations,
        ]);
    }

    /**
     * Grant emergency access.
     */
    public function grantEmergencyAccess(GrantEmergencyAccessRequest $request): JsonResponse
    {
        $requester = auth()->user();
        $targetUser = User::findOrFail($request->input('target_user_id'));
        $permission = $request->input('permission');
        $options = $request->only(['duration_hours', 'reason']);

        $result = $this->rbacService->grantEmergencyAccess($requester, $targetUser, $permission, $options);

        return response()->json($result);
    }

    /**
     * Revoke emergency access.
     */
    public function revokeEmergencyAccess(int $accessId): JsonResponse
    {
        $revoker = auth()->user();
        $result = $this->rbacService->revokeEmergencyAccess($accessId, $revoker);

        if (!$result) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to revoke emergency access',
            ], 400);
        }

        return response()->json([
            'message' => 'Emergency access revoked',
        ]);
    }

    /**
     * Get active emergency access for user.
     */
    public function getEmergencyAccess(int $userId): JsonResponse
    {
        $access = \DB::table('emergency_access')
            ->where('target_user_id', $userId)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->with(['requester:id,name,email'])
            ->get();

        return response()->json([
            'data' => $access,
        ]);
    }

    /**
     * Perform access review.
     */
    public function performAccessReview(PerformAccessReviewRequest $request): JsonResponse
    {
        $tenant = auth()->user()->tenant;
        $options = $request->validated();

        $result = $this->rbacService->performAccessReview($tenant, $options);

        return response()->json([
            'message' => 'Access review completed',
            'data' => $result,
        ]);
    }

    /**
     * Get access review history.
     */
    public function getAccessReviewHistory(): JsonResponse
    {
        $tenant = auth()->user()->tenant;
        
        $reviews = \DB::table('access_reviews')
            ->where('tenant_id', $tenant->id)
            ->orderBy('reviewed_at', 'desc')
            ->paginate(request()->get('per_page', 20));

        return response()->json($reviews);
    }

    /**
     * Revoke expired access.
     */
    public function revokeExpiredAccess(): JsonResponse
    {
        $revoked = $this->rbacService->revokeExpiredAccess();

        return response()->json([
            'message' => 'Expired access revoked',
            'revoked_count' => $revoked,
        ]);
    }

    /**
     * Get user's effective permissions.
     */
    public function getEffectivePermissions(int $userId): JsonResponse
    {
        $user = User::findOrFail($userId);
        $permissions = $this->rbacService->getEffectivePermissions($user);

        return response()->json([
            'data' => $permissions,
        ]);
    }

    /**
     * Get user's role assignments with conditions.
     */
    public function getUserRoleAssignments(int $userId): JsonResponse
    {
        $assignments = \DB::table('user_role_conditions')
            ->where('user_id', $userId)
            ->with(['role:id,name'])
            ->get();

        return response()->json([
            'data' => $assignments,
        ]);
    }
}
