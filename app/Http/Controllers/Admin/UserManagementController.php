<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

/**
 * Admin user management (web).
 *
 * Guard/role note: RolePermissionSeeder defines roles on the 'api' guard and
 * CheckAdminRole calls hasRole('admin') with no guard argument — Spatie then
 * checks roles across ALL guards, so a web-session admin is simply a user
 * holding the 'admin' Spatie role (api guard). Tenant users managed here
 * receive the same api-guard role namespace, keeping one role set app-wide.
 */
class UserManagementController extends Controller
{
    /**
     * GET /admin/users — paginated, searchable list of all tenant users.
     */
    public function index(Request $request)
    {
        $search = $request->input('search');

        $users = User::query()
            ->with(['tenant', 'roles'])
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('tenant_id'), function ($query) use ($request) {
                $query->where('tenant_id', $request->input('tenant_id'));
            })
            ->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        return view('admin.users', [
            'users' => $users,
            'tenants' => Tenant::orderBy('name')->pluck('name', 'id'),
            'roles' => Role::where('guard_name', 'api')->orderBy('name')->pluck('name'),
            'search' => $search,
        ]);
    }

    /**
     * POST /admin/users — create a user inside a tenant with a role.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'tenant_id' => 'required|exists:tenants,id',
            'role' => ['required', 'string', Rule::exists('roles', 'name')->where('guard_name', 'api')],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'], // hashed via model cast
            'tenant_id' => $validated['tenant_id'],
            'is_active' => true,
        ]);

        $role = Role::where('name', $validated['role'])->where('guard_name', 'api')->first();
        $user->assignRole($role);

        return back()->with('success', "User {$user->name} created.");
    }

    /**
     * POST /admin/users/{user}/toggle-active — flip the is_active flag.
     */
    public function toggleActive(User $user)
    {
        $user->update(['is_active' => ! $user->is_active]);

        return response()->json([
            'id' => $user->id,
            'is_active' => $user->fresh()->is_active,
        ]);
    }

    /**
     * POST /admin/users/{user}/role — change the user's role.
     */
    public function updateRole(Request $request, User $user)
    {
        $validated = $request->validate([
            'role' => ['required', 'string', Rule::exists('roles', 'name')->where('guard_name', 'api')],
        ]);

        $role = Role::where('name', $validated['role'])->where('guard_name', 'api')->first();
        $user->syncRoles([$role]);

        return response()->json([
            'id' => $user->id,
            'roles' => $user->fresh()->getRoleNames()->all(),
        ]);
    }
}
