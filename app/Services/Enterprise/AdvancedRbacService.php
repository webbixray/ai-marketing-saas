<?php

namespace App\Services\Enterprise;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Team;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class AdvancedRbacService
{
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'enable_resource_level_permissions' => true,
            'enable_attribute_based_access' => true,
            'enable_time_based_access' => true,
            'enable_delegation' => true,
            'enable_emergency_access' => true,
            'max_delegation_depth' => 3,
            'emergency_access_duration_hours' => 4,
            'access_review_frequency_days' => 90,
            'auto_revoke_expired' => true,
            'conflict_detection' => true,
        ], []);
    }

    /**
     * Define a resource with fine-grained permissions.
     */
    public function defineResource(string $resource, array $actions, array $attributes = []): array
    {
        $permissions = [];
        
        foreach ($actions as $action) {
            $permName = "{$resource}.{$action}";
            
            $permission = Permission::firstOrCreate([
                'name' => $permName,
                'guard_name' => 'api',
            ], [
                'description' => ucfirst($action) . ' ' . ucfirst($resource),
            ]);

            $permissions[] = $permission;
        }

        return [
            'resource' => $resource,
            'actions' => $actions,
            'attributes' => $attributes,
            'permissions' => $permissions,
        ];
    }

    /**
     * Create a role with specific permissions.
     */
    public function createRole(string $name, array $permissions, array $metadata = []): Role
    {
        $role = Role::create([
            'name' => $name,
            'guard_name' => 'api',
            'description' => $metadata['description'] ?? '',
        ]);

        $role->givePermissionTo($permissions);

        // Store metadata
        if (!empty($metadata)) {
            $role->metadata = $metadata;
            $role->save();
        }

        return $role;
    }

    /**
     * Create a composite role (role composed of other roles).
     */
    public function createCompositeRole(string $name, array $childRoles, array $metadata = []): Role
    {
        $role = Role::create([
            'name' => $name,
            'guard_name' => 'api',
            'description' => $metadata['description'] ?? '',
        ]);

        foreach ($childRoles as $childRoleName) {
            $childRole = Role::where('name', $childRoleName)->where('guard_name', 'api')->first();
            if ($childRole) {
                $role->givePermissionTo($childRole->permissions);
            }
        }

        return $role;
    }

    /**
     * Assign role with conditions (ABAC).
     */
    public function assignRoleWithConditions(User $user, string $roleName, array $conditions = [], ?User $assignedBy = null): array
    {
        $role = Role::where('name', $roleName)->where('guard_name', 'api')->firstOrFail();

        // Store assignment with conditions
        $assignment = DB::table('role_assignments')->insertGetId([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'conditions' => json_encode($conditions),
            'assigned_by' => $assignedBy?->id,
            'assigned_at' => now(),
            'expires_at' => $conditions['expires_at'] ?? null,
            'tenant_id' => auth()->user()->tenant_id,
        ]);

        // Assign the role
        $user->assignRole($role);

        // Store conditions in model
        $user->role_conditions()->create([
            'role_id' => $role->id,
            'conditions' => $conditions,
            'assigned_by' => $assignedBy?->id,
            'expires_at' => $conditions['expires_at'] ?? null,
        ]);

        return [
            'assignment_id' => $assignment,
            'user_id' => $user->id,
            'role' => $role->name,
            'conditions' => $conditions,
            'expires_at' => $conditions['expires_at'] ?? null,
        ];
    }

    /**
     * Check if user has permission with conditions.
     */
    public function checkPermission(User $user, string $permission, array $context = []): bool
    {
        // Check direct permission
        if ($user->hasPermissionTo($permission)) {
            // Check conditions if any
            $conditions = $this->getUserRoleConditions($user, $permission);
            if (!empty($conditions)) {
                return $this->evaluateConditions($conditions, $context);
            }
            return true;
        }

        // Check role-based permissions with conditions
        $roles = $user->getRoleNames();
        foreach ($roles as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if ($role && $role->hasPermissionTo($permission)) {
                $conditions = $this->getRoleConditions($user, $role->id, $permission);
                if (!empty($conditions)) {
                    return $this->evaluateConditions($conditions, $context);
                }
                return true;
            }
        }

        return false;
    }

    /**
     * Get user's role conditions for a permission.
     */
    protected function getUserRoleConditions(User $user, string $permission): array
    {
        $conditions = [];
        
        $roleConditions = DB::table('user_role_conditions')
            ->where('user_id', $user->id)
            ->get();

        foreach ($roleConditions as $rc) {
            $conditions = json_decode($rc->conditions, true);
            // Check if this condition applies to the permission
            if ($this->conditionAppliesToPermission($conditions, $permission)) {
                $conditions[] = $conditions;
            }
        }

        return $conditions;
    }

    /**
     * Get role conditions for a permission.
     */
    protected function getRoleConditions(User $user, int $roleId, string $permission): array
    {
        return DB::table('user_role_conditions')
            ->where('user_id', $user->id)
            ->where('role_id', $roleId)
            ->where(function ($query) use ($permission) {
                $conditions = json_decode($permission, true);
                // Simplified check
            })
            ->get()
            ->pluck('conditions')
            ->map(fn($c) => json_decode($c, true))
            ->toArray();
    }

    /**
     * Evaluate conditions against context.
     */
    protected function evaluateConditions(array $conditions, array $context): bool
    {
        foreach ($conditions as $condition) {
            if (!$this->evaluateCondition($condition, $context)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Evaluate single condition.
     */
    protected function evaluateCondition(array $condition, array $context): bool
    {
        $field = $condition['field'] ?? '';
        $operator = $condition['operator'] ?? 'eq';
        $value = $condition['value'] ?? null;
        $dataSource = $condition['data_source'] ?? 'context';

        $actualValue = $this->getContextValue($dataSource, $field, $context);

        return match ($operator) {
            'eq' => $actualValue == $value,
            'neq' => $actualValue != $value,
            'gt' => $actualValue > $value,
            'gte' => $actualValue >= $value,
            'lt' => $actualValue < $value,
            'lte' => $actualValue <= $value,
            'in' => in_array($actualValue, (array)$value),
            'not_in' => !in_array($actualValue, (array)$value),
            'contains' => is_string($actualValue) && str_contains($actualValue, $value),
            'starts_with' => is_string($actualValue) && str_starts_with($actualValue, $value),
            'ends_with' => is_string($actualValue) && str_ends_with($actualValue, $value),
            'between' => $actualValue >= ($value[0] ?? 0) && $actualValue <= ($value[1] ?? PHP_INT_MAX),
            'time_between' => $this->checkTimeBetween($actualValue, $value),
            'ip_in_range' => $this->checkIpInRange($actualValue, $value),
            'resource_owner' => $this->checkResourceOwner($actualValue, $value),
            'team_member' => $this->checkTeamMember($actualValue, $value),
            default => false,
        };
    }

    /**
     * Delegate permission to another user.
     */
    public function delegatePermission(User $delegator, User $delegatee, string $permission, array $options = []): array
    {
        // Check if delegator has permission to delegate
        if (!$this->canDelegate($delegator, $permission)) {
            return ['success' => false, 'error' => 'Insufficient privileges to delegate'];
        }

        // Check delegation depth
        $depth = $this->getDelegationDepth($delegator, $permission);
        if ($depth >= ($this->config['max_delegation_depth'] ?? 3)) {
            return ['success' => false, 'error' => 'Maximum delegation depth reached'];
        }

        // Check if delegatee already has permission
        if ($delegatee->hasPermissionTo($permission)) {
            return ['success' => false, 'error' => 'User already has this permission'];
        }

        // Create delegation record
        $delegation = DB::table('permission_delegations')->insertGetId([
            'delegator_id' => $delegator->id,
            'delegatee_id' => $delegatee->id,
            'permission' => $permission,
            'conditions' => json_encode($options['conditions'] ?? []),
            'expires_at' => $options['expires_at'] ?? now()->addDays(30),
            'created_at' => now(),
            'tenant_id' => auth()->user()->tenant_id,
        });

        // Grant temporary permission
        $permissionModel = \Spatie\Permission\Models\Permission::where('name', $permission)->first();
        if ($permissionModel) {
            $delegatee->givePermissionTo($permissionModel);
        }

        // Store delegation metadata
        DB::table('delegation_metadata')->insert([
            'delegation_id' => $delegation,
            'max_depth' => $this->config['max_delegation_depth'] ?? 3,
            'revocable' => $options['revocable'] ?? true,
            'created_by' => auth()->id(),
        );

        return [
            'success' => true,
            'delegation_id' => $delegation,
            'delegatee' => $delegatee->id,
            'permission' => $permission,
            'expires_at' => $options['expires_at'] ?? now()->addDays(30)->toISOString(),
        ];
    }

    /**
     * Revoke delegation.
     */
    public function revokeDelegation(int $delegationId, User $revoker): bool
    {
        $delegation = DB::table('permission_delegations')
            ->where('id', $delegationId)
            ->first();

        if (!$delegation) {
            return false;
        }

        // Check if revoker has authority
        if ($revoker->id !== $delegation->delegator_id && !$revoker->hasPermissionTo('manage_delegations')) {
            return false;
        }

        // Revoke permission
        $delegatee = User::find($delegation->delegatee_id);
        $permission = \Spatie\Permission\Models\Permission::where('name', $delegation->permission)->first();
        
        if ($delegatee && $permission) {
            $delegatee->revokePermissionTo($permission);
        }

        // Mark delegation as revoked
        DB::table('permission_delegations')
            ->where('id', $delegationId)
            ->update([
                'revoked_at' => now(),
                'revoked_by' => $revoker->id,
            ]);

        return true;
    }

    /**
     * Grant emergency access.
     */
    public function grantEmergencyAccess(User $requester, User $targetUser, string $permission, array $options = []): array
    {
        if (!$this->config['enable_emergency_access']) {
            return ['success' => false, 'error' => 'Emergency access not enabled'];
        }

        // Check if requester has approval authority
        if (!$requester->hasPermissionTo('grant_emergency_access')) {
            return ['success' => false, 'error' => 'Insufficient privileges'];
        }

        $duration = $options['duration_hours'] ?? $this->config['emergency_access_duration_hours'] ?? 4;
        $reason = $options['reason'] ?? 'Emergency access requested';

        // Create emergency access record
        $accessId = DB::table('emergency_access')->insertGetId([
            'requester_id' => $requester->id,
            'target_user_id' => $targetUser->id,
            'permission' => $permission,
            'reason' => $reason,
            'granted_at' => now(),
            'expires_at' => now()->addHours($duration),
            'status' => 'active',
            'tenant_id' => auth()->user()->tenant_id,
        );

        // Grant permission
        $permissionModel = \Spatie\Permission\Models\Permission::where('name', $permission)->first();
        if ($permissionModel) {
            auth()->user()->givePermissionTo($permissionModel);
        }

        // Notify security team
        $this->notifySecurityTeam('emergency_access_granted', [
            'access_id' => $accessId,
            'requester' => $requester->id,
            'target' => $targetUser->id,
            'permission' => $permission,
            'expires_at' => now()->addHours($duration)->toISOString(),
        ]);

        return [
            'success' => true,
            'access_id' => $accessId,
            'expires_at' => now()->addHours($duration)->toISOString(),
        ];
    }

    /**
     * Revoke emergency access.
     */
    public function revokeEmergencyAccess(int $accessId, User $revoker): bool
    {
        $access = DB::table('emergency_access')
            ->where('id', $accessId)
            ->first();

        if (!$access) {
            return false;
        }

        if ($access->status !== 'active') {
            return false;
        }

        // Revoke permission
        $targetUser = User::find($access->target_user_id);
        $permissionModel = \Spatie\Permission\Models\Permission::where('name', $access->permission)->first();
        
        if ($targetUser && $permissionModel) {
            $targetUser->revokePermissionTo($permissionModel);
        }

        // Update access record
        DB::table('emergency_access')
            ->where('id', $accessId)
            ->update([
                'status' => 'revoked',
                'revoked_at' => now(),
                'revoked_by' => $revoker->id,
            ]);

        return true;
    }

    /**
     * Perform access review.
     */
    public function performAccessReview(Tenant $tenant, array $options = []): array
    {
        $reviewId = Str::uuid()->toString();
        
        $users = User::where('tenant_id', $tenant->id)
            ->where('active', true)
            ->with(['roles', 'permissions'])
            ->get();

        $findings = [];
        $reviewed = 0;

        foreach ($users as $user) {
            $userFindings = $this->reviewUserAccess($user);
            if (!empty($userFindings)) {
                $findings[$user->id] = [
                    'user' => $user->only(['id', 'name', 'email']),
                    'findings' => $userFindings,
                ];
            }
            $reviewed++;
        }

        // Store review
        DB::table('access_reviews')->insert([
            'id' => $reviewId,
            'tenant_id' => $tenant->id,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'users_reviewed' => $reviewed,
            'findings_count' => count($findings),
            'status' => 'completed',
            'findings' => json_encode($findings),
        );

        return [
            'review_id' => $reviewId,
            'users_reviewed' => $reviewed,
            'findings_count' => count($findings),
            'findings' => $findings,
        ];
    }

    /**
     * Review individual user access.
     */
    protected function reviewUserAccess(User $user): array
    {
        $findings = [];

        // Check for excessive permissions
        $permissionCount = $user->getAllPermissions()->count();
        if ($permissionCount > 20) {
            $this->addFinding($findings, 'excessive_permissions', 'high', 
                "User has {$permissionCount} direct permissions", 
                "Review and consolidate permissions");
        }

        // Check for conflicting roles
        $roles = $user->getRoleNames();
        $conflicts = $this->checkRoleConflicts($roles);
        if (!empty($conflicts)) {
            $this->addFinding($findings, 'role_conflict', 'high',
                "Conflicting roles detected: " . implode(', ', $conflicts),
                "Review and consolidate roles");
        }

        // Check for expired delegations
        $expiredDelegations = DB::table('permission_delegations')
            ->where('delegatee_id', $this->user->id)
            ->where('expires_at', '<', now())
            ->whereNull('revoked_at')
            ->count();

        if ($expiredDelegations > 0) {
            $this->addFinding($findings, 'expired_delegations', 'medium',
                "{$expiredDelegations} expired delegations still active",
                "Revoke expired delegations");
        }

        // Check for emergency access
        $activeEmergency = DB::table('emergency_access')
            ->where('target_user_id', $this->user->id)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->count();

        if ($activeEmergency > 0) {
            $this->addFinding($findings, 'emergency_access', 'high',
                "{$activeEmergency} active emergency access grants",
                "Review and revoke if no longer needed");
        }

        // Check role expiration
        $expiredRoles = DB::table('user_role_conditions')
            ->where('user_id', $this->user->id)
            ->where('expires_at', '<', now())
            ->whereNotNull('expires_at')
            ->count();

        if ($expiredRoles > 0) {
            $this->addFinding($findings, 'expired_roles', 'medium',
                "{$expiredRoles} roles have expired conditions",
                "Review and update role assignments");
        }

        return $findings;
    }

    /**
     * Check for role conflicts.
     */
    protected function checkRoleConflicts(array $roles): array
    {
        $conflicts = [
            ['admin', 'viewer'],
            ['finance_admin', 'hr_admin'],
            ['security_admin', 'content_editor'],
            ['super_admin', 'any_other_admin'],
        ];

        $conflictsFound = [];
        foreach ($conflicts as $conflict) {
            $intersection = array_intersect($roles, $conflict);
            if (count($intersection) > 1) {
                $conflictsFound[] = implode(' + ', $intersection);
            }
        }

        return $conflictsFound;
    }

    /**
     * Add finding to review.
     */
    protected function addFinding(array &$findings, string $type, string $severity, string $description, string $recommendation): void
    {
        $findings[] = [
            'type' => $type,
            'severity' => $severity,
            'description' => $description,
            'recommendation' => $recommendation,
            'detected_at' => now()->toISOString(),
        ];
    }

    /**
     * Revoke expired access.
     */
    public function revokeExpiredAccess(): int
    {
        $revoked = 0;

        // Revoke expired delegations
        $expiredDelegations = DB::table('permission_delegations')
            ->where('expires_at', '<', now())
            ->whereNull('revoked_at')
            ->get();

        foreach ($expiredDelegations as $delegation) {
            $this->revokeDelegation($delegation->id, User::find($delegation->delegator_id));
            $revoked++;
        }

        // Revoke expired emergency access
        $expiredEmergency = DB::table('emergency_access')
            ->where('expires_at', '<', now())
            ->where('status', 'active')
            ->get();

        foreach ($expiredEmergency as $access) {
            $this->revokeEmergencyAccess($access->id, User::find($access->requester_id));
            $revoked++;
        }

        // Revoke expired role conditions
        $expiredConditions = DB::table('user_role_conditions')
            ->where('expires_at', '<', now())
            ->whereNotNull('expires_at')
            ->get();

        foreach ($expiredConditions as $condition) {
            $user = User::find($condition->user_id);
            $role = \Spatie\Permission\Models\Role::find($condition->role_id);
            if ($user && $role) {
                $user->removeRole($role);
                $revoked++;
            }
        }

        return $revoked;
    }

    /**
     * Get user's effective permissions.
     */
    public function getEffectivePermissions(User $user): array
    {
        $permissions = [];

        // Direct permissions
        foreach ($user->getDirectPermissions() as $perm) {
            $permissions[] = [
                'name' => $perm->name,
                'source' => 'direct',
                'conditions' => [],
            ];
        }

        // Role permissions
        foreach ($user->roles as $role) {
            foreach ($role->permissions as $perm) {
                $conditions = DB::table('user_role_conditions')
                    ->where('user_id', auth()->id())
                    ->where('role_id', $role->id)
                    ->first();

                $permissions[] = [
                    'name' => $perm->name,
                    'source' => "role:{$role->name}",
                    'conditions' => $conditions ? json_decode($conditions->conditions, true) : [],
                ];
            }
        }

        // Delegated permissions
        $delegations = DB::table('permission_delegations')
            ->where('delegatee_id', auth()->id())
            ->where('expires_at', '>', now())
            ->whereNull('revoked_at')
            ->get();

        foreach ($delegations as $delegation) {
            $permissions[] = [
                'name' => $delegation->permission,
                'source' => 'delegation',
                'delegator' => User::find($delegation->delegator_id)?->name,
                'expires_at' => $delegation->expires_at,
                'conditions' => json_decode($delegation->conditions, true),
            ];
        }

        // Emergency access
        $emergency = DB::table('emergency_access')
            ->where('target_user_id', auth()->id())
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->get();

        foreach ($emergency as $access) {
            $permissions[] = [
                'name' => $access->permission,
                'source' => 'emergency',
                'requester' => User::find($access->requester_id)?->name,
                'expires_at' => $access->expires_at,
                'reason' => $access->reason,
            ];
        }

        return $permissions;
    }

    /**
     * Check if user can delegate permission.
     */
    protected function canDelegate(User $user, string $permission): bool
    {
        // User must have the permission directly or via role (not delegation)
        if (!$user->hasPermissionTo($permission)) {
            return false;
        }

        // Check if user has delegation permission
        if (!$user->hasPermissionTo('delegate_permissions')) {
            // Check if they have admin role
            if (!$user->hasRole('admin') && !$user->hasRole('super_admin')) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get delegation depth.
     */
    protected function getDelegationDepth(User $user, string $permission): int
    {
        $depth = 0;
        $current = $user;

        while (true) {
            $delegation = DB::table('permission_delegations')
                ->where('delegatee_id', $current->id)
                ->where('permission', $permission)
                ->where('revoked_at', null)
                ->where('expires_at', '>', now())
                ->first();

            if (!$delegation) {
                break;
            }

            $depth++;
            $current = User::find($delegation->delegator_id);
            
            if (!$current || $depth > 10) {
                break;
            }
        }

        return $depth;
    }

    /**
     * Check if user has role condition that applies to permission.
     */
    protected function conditionAppliesToPermission(array $conditions, string $permission): bool
    {
        if (empty($conditions)) return true;
        
        // Check if condition specifies permissions
        if (isset($conditions['permissions'])) {
            return in_array($permission, (array)$conditions['permissions']);
        }

        // Check if condition specifies resources
        if (isset($conditions['resources'])) {
            $resource = explode('.', $permission)[0] ?? '';
            return in_array($resource, (array)$conditions['resources']);
        }

        return true;
    }
}
