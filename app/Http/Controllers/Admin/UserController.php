<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Rules\EmailNotReserved;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

#[Middleware('permission:show-user', only: ['index', 'show'])]
#[Middleware('permission:create-user', only: ['create', 'store'])]
#[Middleware('permission:edit-user', only: ['edit', 'update', 'deleteAvatar'])]
#[Middleware('permission:delete-user', only: ['destroy'])]
class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $users = User::with('roles')->latest()->paginate(config('cms.pagination.users'));

        // The list disables the delete button when destroy() would reject it
        // anyway — otherwise the interface offers an action that only ends
        // in an error message.
        $adminCount = $this->adminCount();

        return view('backend.users.index', compact('users', 'adminCount'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        $roles = Role::all();

        return view('backend.users.create', compact('roles'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:users'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users', new EmailNotReserved],
            'phone' => ['nullable', 'string', 'max:20'],
            'job_title' => ['nullable', 'string', 'max:100'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'avatar' => ['nullable', 'image', 'max:'.config('cms.max_avatar_size')],
            'roles' => ['required', 'array'],
            'roles.*' => ['exists:roles,id'],
        ], [], ['name' => __('translation.name')]);

        $this->authorizeRoleAssignment($request->roles);

        // Handle avatar upload
        $avatarPath = null;
        if ($request->hasFile('avatar')) {
            $avatarPath = $request->file('avatar')->store('avatars', 'public');
        }

        $user = DB::transaction(function () use ($request, $avatarPath) {
            $user = User::create([
                'name' => $request->name,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'phone' => $request->phone,
                'job_title' => $request->job_title,
                'bio' => $request->bio,
                'avatar' => $avatarPath,
                'password' => Hash::make($request->password),
                'is_active' => $request->boolean('is_active'),
            ]);

            // Assign roles
            $user->roles()->attach($request->roles);

            return $user;
        });

        return redirect()->route('backend.users.index')
            ->with('success', __('translation.users.created'));
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user): View
    {
        $user->load('roles');

        return view('backend.users.show', compact('user'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $user): View|RedirectResponse
    {
        if ($denied = $this->denyAdminAccountChange($user)) {
            return $denied;
        }

        $roles = Role::all();
        $user->load('roles');

        return view('backend.users.edit', compact('user', 'roles'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        if ($denied = $this->denyAdminAccountChange($user)) {
            return $denied;
        }

        $rules = [
            'name' => ['required', 'string', 'max:255', Rule::unique('users')->ignore($user->id)],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id), new EmailNotReserved($user->id)],
            'phone' => ['nullable', 'string', 'max:20'],
            'job_title' => ['nullable', 'string', 'max:100'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'avatar' => ['nullable', 'image', 'max:'.config('cms.max_avatar_size')],
            'roles' => ['required', 'array'],
            'roles.*' => ['exists:roles,id'],
        ];

        // Only validate password if it's provided
        if ($request->filled('password')) {
            $rules['password'] = ['required', 'confirmed', Rules\Password::defaults()];
        }

        $request->validate($rules, [], ['name' => __('translation.name')]);

        $this->authorizeRoleAssignment($request->roles, $user);

        // Handle avatar upload
        if ($request->hasFile('avatar')) {
            // Delete old avatar if exists
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }
            $avatarPath = $request->file('avatar')->store('avatars', 'public');
        } else {
            $avatarPath = $user->avatar;
        }

        // Prepare update data
        $userData = [
            'name' => $request->name,
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'phone' => $request->phone,
            'job_title' => $request->job_title,
            'bio' => $request->bio,
            'avatar' => $avatarPath,
            'is_active' => $request->boolean('is_active'),
        ];

        // Do not lock out your own account: stay active and keep the admin role
        $roles = $request->roles;
        if ($user->id === auth()->id()) {
            $userData['is_active'] = true;

            $adminRole = $user->roles->firstWhere('name', 'admin');
            if ($adminRole && ! in_array($adminRole->id, $roles)) {
                $roles[] = $adminRole->id;
            }
        }

        // Only update password if one is provided
        if ($request->filled('password')) {
            $userData['password'] = Hash::make($request->password);
        }

        DB::transaction(function () use ($user, $userData, $roles) {
            // Update user
            $user->update($userData);

            // Update roles
            $user->roles()->sync($roles);
        });

        // New password: end the person's existing sessions (your own stays).
        // If someone else sets the password, their API tokens are revoked too
        if ($request->filled('password')) {
            $self = $user->is(auth()->user());
            $user->endOtherSessions($self ? $request->session()->getId() : null);
            if (! $self) {
                $user->revokeApiTokens();
            }
        }

        return redirect()->route('backend.users.edit', $user)
            ->with('success', __('translation.users.updated'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', __('translation.users.error.self_delete'));
        }

        if (! $user->hasRole('admin') && ! auth()->user()->canGrant($user->permissionNames())) {
            return back()->with('error', __('translation.users.error.more_rights'));
        }

        if ($user->hasRole('admin')) {
            if (! auth()->user()->hasRole('admin')) {
                return back()->with('error', __('translation.users.error.admin_delete'));
            }

            if ($this->adminCount() <= 1) {
                return back()->with('error', __('translation.users.error.last_admin'));
            }
        }

        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
        }

        $user->delete();

        return redirect()->route('backend.users.index')
            ->with('success', __('translation.users.deleted'));
    }

    /**
     * Delete the user's avatar.
     */
    public function deleteAvatar(User $user): RedirectResponse
    {
        if ($denied = $this->denyAdminAccountChange($user)) {
            return $denied;
        }

        // Check if user has avatar
        if ($user->avatar) {
            // Delete avatar file
            Storage::disk('public')->delete($user->avatar);

            // Update user record
            $user->avatar = null;
            $user->save();

            return redirect()->back()->with('success', __('translation.users.avatar_deleted'));
        }

        return redirect()->back()->with('error', __('translation.users.no_avatar'));
    }

    /**
     * Number of admins — via the role named exactly 'admin', not via an
     * SQL name comparison (MySQL would also count 'Ádmin').
     */
    private function adminCount(): int
    {
        $adminRole = Role::named('admin');

        return $adminRole ? $adminRole->users()->count() : 0;
    }

    /**
     * Only admins edit admin accounts — otherwise someone with edit-user could
     * change an admin's password or email and take over the account.
     * The same applies to any account with permissions the editor does not
     * hold (e.g. a custom role with manage-plugins).
     */
    private function denyAdminAccountChange(User $user): ?RedirectResponse
    {
        $actor = auth()->user();

        if ($user->hasRole('admin') && ! $actor->hasRole('admin')) {
            return redirect()->route('backend.users.index')
                ->with('error', __('translation.users.error.admin_edit'));
        }

        if (! $user->is($actor) && ! $actor->canGrant($user->permissionNames())) {
            return redirect()->route('backend.users.index')
                ->with('error', __('translation.users.error.more_rights'));
        }

        return null;
    }

    /**
     * Newly assigned roles must not contain permissions the editor does not
     * hold (no privilege escalation via user management).
     *
     * @throws ValidationException
     */
    private function authorizeRoleAssignment(array $roleIds, ?User $user = null): void
    {
        $actor = auth()->user();
        if ($actor->hasRole('admin')) {
            return;
        }

        $current = $user ? $user->roles()->pluck('roles.id')->all() : [];
        $added = Role::with('permissions')
            ->whereIn('id', array_diff(array_map('intval', $roleIds), $current))
            ->get();

        foreach ($added as $role) {
            if ($role->name === 'admin' || ! $actor->canGrant($role->permissions->pluck('name'))) {
                throw ValidationException::withMessages([
                    'roles' => __('translation.users.error.role_not_grantable', ['role' => $role->label]),
                ]);
            }
        }
    }
}
