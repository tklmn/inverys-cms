<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Support\CodeAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

#[Middleware('permission:show-role', only: ['index', 'show'])]
#[Middleware('permission:create-role', only: ['create', 'store'])]
#[Middleware('permission:edit-role', only: ['edit', 'update'])]
#[Middleware('permission:delete-role', only: ['destroy'])]
class RoleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $roles = Role::with('permissions')->withCount('users')->orderBy('name')->get();

        return view('backend.roles.index', compact('roles'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        $permissions = Permission::all();

        return view('backend.roles.create', compact('permissions'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles',
            'permissions' => 'array',
            'permissions.*' => 'integer|exists:permissions,id',
        ]);

        $this->authorizeCodeName($validated['name']);
        $this->authorizePermissionGrant($validated['permissions'] ?? []);

        $role = Role::create(['name' => $validated['name']]);

        // Assign permissions if they exist
        if (isset($validated['permissions'])) {
            $role->permissions()->sync($validated['permissions']);
        }

        return redirect()->route('backend.roles.index')
            ->with('success', __('translation.roles.success.created'));
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): View
    {
        $role = Role::with('permissions')->findOrFail($id);
        $users = $role->users()->orderBy('name')->get();

        return view('backend.roles.show', compact('role', 'users'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id): View
    {
        $role = Role::with('permissions')->findOrFail($id);
        $permissions = Permission::all();

        return view('backend.roles.edit', compact('role', 'permissions'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): RedirectResponse
    {
        $role = Role::findOrFail($id);

        // Only admins may change the admin role
        if ($role->name === 'admin' && ! auth()->user()->hasRole('admin')) {
            return redirect()->route('backend.roles.index')
                ->with('error', __('translation.roles.error.admin_role'));
        }

        $validated = $request->validate([
            'name' => $role->is_system ? 'nullable' : 'required|string|max:255|unique:roles,name,'.$id,
            'permissions' => 'array',
            'permissions.*' => 'integer|exists:permissions,id',
        ]);

        if (! $role->is_system && $validated['name'] !== $role->name) {
            $this->authorizeCodeName($role->name);
            $this->authorizeCodeName($validated['name']);
        }

        $this->authorizePermissionGrant($validated['permissions'] ?? [], $role);

        // System roles keep their name — the code checks it (hasRole('admin'))
        if (! $role->is_system) {
            $role->update(['name' => $validated['name']]);
        }

        // Update this role's permissions
        if (isset($validated['permissions'])) {
            $role->permissions()->sync($validated['permissions']);
        } else {
            // If no permissions were selected, remove all
            $role->permissions()->sync([]);
        }

        return redirect()->route('backend.roles.edit', $role)
            ->with('success', __('translation.roles.success.updated'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): RedirectResponse
    {
        $role = Role::findOrFail($id);

        // Check if this is a system role
        if ($role->is_system) {
            return redirect()->route('backend.roles.index')
                ->with('error', __('translation.roles.error.system_role'));
        }

        // Check if users exist with this role
        $usersWithRole = $role->users()->count();

        if ($usersWithRole > 0) {
            return redirect()->route('backend.roles.index')
                ->with('error', __('translation.roles.error.has_users', ['count' => $usersWithRole]));
        }

        // Remove all permissions from this role before deleting it
        $role->permissions()->sync([]);

        $role->delete();

        return redirect()->route('backend.roles.index')
            ->with('success', __('translation.roles.success.deleted'));
    }

    /**
     * Names through which the code grants permissions (Role::CODE_NAMES) are
     * assigned and changed by admins only. Otherwise edit-role renames 'editor',
     * gives its own role that name and receives the editor permissions of the
     * next plugin that gets enabled — bypassing canGrant().
     *
     * @throws ValidationException
     */
    private function authorizeCodeName(?string $name): void
    {
        if (Role::isCodeName($name) && ! auth()->user()->hasRole('admin')) {
            throw ValidationException::withMessages([
                'name' => __('translation.roles.error.code_name'),
            ]);
        }
    }

    /**
     * Non-admins may only add permissions to a role that they hold
     * themselves — otherwise edit-role would grant itself (via its own role)
     * every permission. Existing permissions stay untouched.
     *
     * @throws ValidationException
     */
    private function authorizePermissionGrant(array $permissionIds, ?Role $role = null): void
    {
        $current = $role ? $role->permissions()->pluck('permissions.id')->all() : [];
        $added = Permission::whereIn('id', array_diff(array_map('intval', $permissionIds), $current))->pluck('name');

        // Code permissions (theme files, plugins) exist only for the admin role
        if ($role?->name !== 'admin' && $added->contains(fn ($name) => CodeAccess::isAdminOnly($name))) {
            throw ValidationException::withMessages([
                'permissions' => __('translation.code_access.role_admin_only'),
            ]);
        }

        if (! auth()->user()->canGrant($added)) {
            throw ValidationException::withMessages([
                'permissions' => __('translation.roles.error.permission_not_grantable'),
            ]);
        }
    }
}
