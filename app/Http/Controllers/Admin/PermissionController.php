<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\View\View;

#[Middleware('permission:show-permission', only: ['index', 'show'])]
#[Middleware('permission:create-permission', only: ['create', 'store'])]
#[Middleware('permission:edit-permission', only: ['edit', 'update'])]
#[Middleware('permission:delete-permission', only: ['destroy'])]
class PermissionController extends Controller
{
    public function index(): View
    {
        $permissions = Permission::with('roles')->orderBy('name')->get();

        return view('backend.permissions.index', compact('permissions'));
    }

    public function show(Permission $permission): View
    {
        $permission->load('roles');

        return view('backend.permissions.show', compact('permission'));
    }

    public function create(): View
    {
        return view('backend.permissions.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:permissions',
        ]);
        $permission = Permission::create($validated);

        return redirect()->route('backend.permissions.index')->with('success', __('translation.permissions.success.created'));
    }

    public function edit(Permission $permission): View
    {
        return view('backend.permissions.edit', compact('permission'));
    }

    public function update(Request $request, Permission $permission): RedirectResponse
    {
        if ($permission->is_system) {
            return back()->with('error', __('translation.permissions.system_locked'));
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:permissions,name,'.$permission->id,
        ]);
        $permission->update($validated);

        return redirect()->route('backend.permissions.edit', $permission)->with('success', __('translation.permissions.success.updated'));
    }

    public function destroy(Permission $permission): RedirectResponse
    {
        if ($permission->is_system) {
            return back()->with('error', __('translation.permissions.system_locked'));
        }

        $permission->delete();

        return redirect()->route('backend.permissions.index')->with('success', __('translation.permissions.success.deleted'));
    }
}
