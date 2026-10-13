<?php

namespace Tests\Feature\Security;

use App\Contracts\PermissionManager;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Ways in which a non-admin could gain more permissions or touch other
 * administration areas.
 */
class PrivilegeEscalationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->markAsInstalled();
    }

    /** User with a role of their own made of the given permissions */
    private function userWith(array $permissions): User
    {
        $role = Role::create(['name' => 'custom-'.uniqid()]);
        $role->permissions()->sync(Permission::whereIn('name', $permissions)->pluck('id'));

        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }

    private function userManager(): User
    {
        return $this->userWith(['access-dashboard', 'show-user', 'create-user', 'edit-user']);
    }

    // --- User management ----------------------------------------------------

    public function test_user_manager_cannot_grant_himself_the_admin_role(): void
    {
        $manager = $this->userManager();
        $adminRole = Role::where('name', 'admin')->first();

        $this->actingAs($manager)
            ->put(route('backend.users.update', $manager), [
                'name' => $manager->name,
                'first_name' => 'A',
                'last_name' => 'B',
                'email' => $manager->email,
                'roles' => [...$manager->roles->pluck('id')->all(), $adminRole->id],
            ])
            ->assertSessionHasErrors('roles');

        $manager->clearRoleCache();
        $this->assertFalse($manager->hasRole('admin'));
    }

    public function test_user_manager_cannot_create_an_admin(): void
    {
        $manager = $this->userManager();

        $this->actingAs($manager)
            ->post(route('backend.users.store'), [
                'name' => 'evil',
                'first_name' => 'E',
                'last_name' => 'Vil',
                'email' => 'evil@example.com',
                'password' => 'Secret-Passw0rd!',
                'password_confirmation' => 'Secret-Passw0rd!',
                'roles' => [Role::where('name', 'admin')->value('id')],
            ])
            ->assertSessionHasErrors('roles');

        $this->assertDatabaseMissing('users', ['email' => 'evil@example.com']);
    }

    public function test_user_manager_cannot_assign_role_with_foreign_permissions(): void
    {
        $manager = $this->userManager();
        $target = $this->createUser();
        $editorRole = Role::where('name', 'editor')->first();

        $this->actingAs($manager)
            ->put(route('backend.users.update', $target), [
                'name' => $target->name,
                'first_name' => 'A',
                'last_name' => 'B',
                'email' => $target->email,
                'roles' => [$editorRole->id],
            ])
            ->assertSessionHasErrors('roles');

        $this->assertFalse($target->fresh()->hasRole('editor'));
    }

    public function test_user_manager_cannot_take_over_an_admin_account(): void
    {
        $manager = $this->userManager();
        $admin = $this->createAdmin();
        $oldHash = $admin->password;

        $this->actingAs($manager)
            ->put(route('backend.users.update', $admin), [
                'name' => $admin->name,
                'first_name' => 'A',
                'last_name' => 'B',
                'email' => 'attacker@example.com',
                'password' => 'Secret-Passw0rd!',
                'password_confirmation' => 'Secret-Passw0rd!',
                'roles' => $admin->roles->pluck('id')->all(),
            ])
            ->assertRedirect(route('backend.users.index'))
            ->assertSessionHas('error');

        $admin->refresh();
        $this->assertSame($oldHash, $admin->password);
        $this->assertNotSame('attacker@example.com', $admin->email);

        $this->actingAs($manager)->get(route('backend.users.edit', $admin))
            ->assertRedirect(route('backend.users.index'));
    }

    public function test_user_manager_cannot_take_over_an_account_with_more_rights(): void
    {
        $manager = $this->userManager();
        // Not an admin, but a custom role that can put code on the server
        $powerful = $this->userWith(['access-dashboard', 'manage-plugins']);
        $oldHash = $powerful->password;
        $payload = [
            'name' => $powerful->name,
            'first_name' => 'A',
            'last_name' => 'B',
            'email' => 'attacker@example.com',
            'password' => 'Secret-Passw0rd!',
            'password_confirmation' => 'Secret-Passw0rd!',
            'roles' => $powerful->roles->pluck('id')->all(),
        ];

        $this->actingAs($manager)->get(route('backend.users.edit', $powerful))->assertRedirect(route('backend.users.index'));
        $this->actingAs($manager)->put(route('backend.users.update', $powerful), $payload)
            ->assertRedirect(route('backend.users.index'))
            ->assertSessionHas('error');

        $powerful->refresh();
        $this->assertSame($oldHash, $powerful->password);
        $this->assertNotSame('attacker@example.com', $powerful->email);

        $deleter = $this->userWith(['access-dashboard', 'show-user', 'delete-user']);
        $this->actingAs($deleter)->delete(route('backend.users.destroy', $powerful))->assertSessionHas('error');
        $this->assertModelExists($powerful);
    }

    public function test_code_permissions_work_only_for_administrators(): void
    {
        // A custom role that carries the code permissions
        $designer = $this->userWith(['access-dashboard', 'manage-themes', 'edit-theme-files', 'manage-plugins']);

        $this->actingAs($designer)->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('backend.plugins.index'))->assertForbidden();
        $this->actingAs($designer)->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('backend.themes.editor', 'default'))->assertForbidden();
        $this->actingAs($designer)->post(route('backend.themes.upload'))->assertForbidden();

        // Customizing stays allowed
        $this->actingAs($designer)->get(route('backend.themes.index'))->assertOk();
    }

    public function test_code_permissions_are_only_granted_to_the_admin_role(): void
    {
        $role = Role::create(['name' => 'designer']);
        $permission = Permission::firstOrCreate(['name' => 'edit-theme-files']);

        $this->actingAs($this->createAdmin())
            ->put(route('backend.roles.update', $role), ['name' => 'designer', 'permissions' => [$permission->id]])
            ->assertSessionHasErrors('permissions');

        $this->assertFalse($role->permissions()->where('name', 'edit-theme-files')->exists());
    }

    public function test_code_editing_can_be_switched_off_per_server(): void
    {
        config(['cms.code_editing' => false]);
        $admin = $this->createAdmin();

        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('backend.themes.editor', 'default'))->assertForbidden();
        $this->actingAs($admin)->post(route('backend.plugins.upload'))->assertForbidden();
        $this->actingAs($admin)->get(route('backend.themes.index'))
            ->assertOk()->assertSee(__('translation.code_access.disabled'));
    }

    public function test_user_manager_still_edits_accounts_with_fewer_rights(): void
    {
        $manager = $this->userManager();
        $plain = $this->userWith(['access-dashboard']);

        $this->actingAs($manager)->get(route('backend.users.edit', $plain))->assertOk();
    }

    public function test_admin_can_still_assign_admin_role(): void
    {
        $admin = $this->createAdmin();
        $target = $this->createUser();
        $adminRole = Role::where('name', 'admin')->first();

        $this->actingAs($admin)
            ->put(route('backend.users.update', $target), [
                'name' => $target->name,
                'first_name' => 'A',
                'last_name' => 'B',
                'email' => $target->email,
                'roles' => [$adminRole->id],
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue($target->fresh()->hasRole('admin'));
    }

    // --- Role management ----------------------------------------------------

    public function test_role_editor_cannot_add_permissions_he_does_not_have(): void
    {
        $roleEditor = $this->userWith(['access-dashboard', 'show-role', 'edit-role']);
        $ownRole = $roleEditor->roles->first();
        $manageThemes = Permission::where('name', 'manage-themes')->first();

        $this->actingAs($roleEditor)
            ->put(route('backend.roles.update', $ownRole), [
                'name' => $ownRole->name,
                'permissions' => [...$ownRole->permissions->pluck('id')->all(), $manageThemes->id],
            ])
            ->assertSessionHasErrors('permissions');

        $this->assertFalse($ownRole->permissions()->where('name', 'manage-themes')->exists());
    }

    public function test_role_editor_cannot_change_the_admin_role(): void
    {
        $roleEditor = $this->userWith(['access-dashboard', 'show-role', 'edit-role']);
        $adminRole = Role::where('name', 'admin')->first();
        $before = $adminRole->permissions()->count();

        $this->actingAs($roleEditor)
            ->put(route('backend.roles.update', $adminRole), ['permissions' => []])
            ->assertRedirect(route('backend.roles.index'));

        $this->assertSame($before, $adminRole->permissions()->count());
    }

    public function test_role_editor_may_keep_and_remove_permissions(): void
    {
        $roleEditor = $this->userWith(['access-dashboard', 'show-role', 'edit-role']);
        $other = Role::create(['name' => 'other']);
        $other->permissions()->sync(Permission::whereIn('name', ['manage-themes', 'show-role'])->pluck('id'));

        // manage-themes stays (was already there), show-role is dropped — allowed
        $this->actingAs($roleEditor)
            ->put(route('backend.roles.update', $other), [
                'name' => 'other',
                'permissions' => [Permission::where('name', 'manage-themes')->value('id')],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(['manage-themes'], $other->permissions()->pluck('name')->all());
    }

    public function test_role_editor_cannot_take_over_the_editor_role_name(): void
    {
        // On activation, plugins give their permissions to the role named 'editor'
        // (assignToRole) — whoever names their role that would bypass canGrant()
        $roleEditor = $this->userWith(['access-dashboard', 'show-role', 'create-role', 'edit-role']);
        $ownRole = $roleEditor->roles->first();
        $editorRole = Role::where('name', 'editor')->first();

        $this->actingAs($roleEditor)
            ->put(route('backend.roles.update', $editorRole), [
                'name' => 'editor-alt',
                'permissions' => $editorRole->permissions->pluck('id')->all(),
            ])
            ->assertSessionHasErrors('name');
        $this->assertSame('editor', $editorRole->fresh()->name);

        // Even if the name is free (renamed by the admin): not for non-admins
        $editorRole->update(['name' => 'newsroom']);

        $this->actingAs($roleEditor)
            ->put(route('backend.roles.update', $ownRole), [
                'name' => 'Editor',
                'permissions' => $ownRole->permissions->pluck('id')->all(),
            ])
            ->assertSessionHasErrors('name');
        $this->assertSame($ownRole->name, $ownRole->fresh()->name);

        $this->actingAs($roleEditor)
            ->post(route('backend.roles.store'), ['name' => 'editor', 'permissions' => []])
            ->assertSessionHasErrors('name');
        $this->assertFalse(Role::where('name', 'editor')->exists());

        // Plugin permissions thus still only go to the real editor role
        Permission::firstOrCreate(['name' => 'show-form-submission']);
        app(PermissionManager::class)->assignToRole('editor', ['show-form-submission']);
        $this->assertFalse($roleEditor->fresh()->hasPermission('show-form-submission'));
    }

    public function test_admin_may_still_rename_the_editor_role(): void
    {
        $editorRole = Role::where('name', 'editor')->first();

        $this->actingAs($this->createAdmin())
            ->put(route('backend.roles.update', $editorRole), [
                'name' => 'newsroom',
                'permissions' => $editorRole->permissions->pluck('id')->all(),
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('newsroom', $editorRole->fresh()->name);
    }

    // --- Trash --------------------------------------------------------------

    public function test_editor_cannot_restore_or_purge_trashed_users(): void
    {
        $editor = $this->createEditor();
        $victim = $this->createAdmin();
        $victim->delete();

        $this->actingAs($editor)
            ->post(route('backend.trash.restore', ['type' => 'user', 'id' => $victim->id]))
            ->assertForbidden();
        $this->actingAs($editor)
            ->delete(route('backend.trash.forceDelete', ['type' => 'user', 'id' => $victim->id]))
            ->assertForbidden();

        $this->assertSoftDeleted('users', ['id' => $victim->id]);
    }

    public function test_user_manager_cannot_restore_or_purge_a_trashed_admin(): void
    {
        // As with deleting (UserController::destroy): admin accounts only by admins
        $manager = $this->userWith(['access-dashboard', 'show-user', 'delete-user', 'manage-trash']);
        $this->createAdmin();
        $victim = $this->createAdmin();
        $victim->delete();

        $this->actingAs($manager)
            ->post(route('backend.trash.restore', ['type' => 'user', 'id' => $victim->id]))
            ->assertSessionHas('error', __('translation.users.error.admin_trash'));
        $this->actingAs($manager)
            ->delete(route('backend.trash.forceDelete', ['type' => 'user', 'id' => $victim->id]))
            ->assertSessionHas('error', __('translation.users.error.admin_trash'));

        $this->assertSoftDeleted('users', ['id' => $victim->id]);
    }

    public function test_user_manager_cannot_restore_or_purge_an_account_with_more_rights(): void
    {
        $manager = $this->userWith(['access-dashboard', 'show-user', 'delete-user', 'manage-trash']);
        $victim = $this->userWith(['access-dashboard', 'manage-plugins']);
        $victim->delete();

        $this->actingAs($manager)
            ->post(route('backend.trash.restore', ['type' => 'user', 'id' => $victim->id]))
            ->assertSessionHas('error', __('translation.users.error.more_rights'));
        $this->actingAs($manager)
            ->delete(route('backend.trash.forceDelete', ['type' => 'user', 'id' => $victim->id]))
            ->assertSessionHas('error', __('translation.users.error.more_rights'));

        $this->assertSoftDeleted('users', ['id' => $victim->id]);
    }

    public function test_user_manager_still_restores_accounts_with_fewer_rights(): void
    {
        $manager = $this->userWith(['access-dashboard', 'show-user', 'delete-user', 'manage-trash']);
        $victim = $this->userWith(['access-dashboard']);
        $victim->delete();

        $this->actingAs($manager)
            ->post(route('backend.trash.restore', ['type' => 'user', 'id' => $victim->id]))
            ->assertSessionHasNoErrors()
            ->assertSessionMissing('error');

        $this->assertNotSoftDeleted('users', ['id' => $victim->id]);
    }

    public function test_admin_may_restore_a_trashed_admin(): void
    {
        $victim = $this->createAdmin();
        $victim->delete();

        $this->actingAs($this->createAdmin())
            ->post(route('backend.trash.restore', ['type' => 'user', 'id' => $victim->id]))
            ->assertSessionMissing('error');

        $this->assertNotSoftDeleted('users', ['id' => $victim->id]);
    }

    public function test_plugin_permissions_only_go_to_the_role_with_the_exact_name(): void
    {
        // MySQL/MariaDB (utf8mb4_unicode_ci) compare ignoring case and accents:
        // 'Éditor' would match where('name', 'editor') there. SQLite compares
        // exactly — NOCASE reproduces that behaviour for the test.
        Schema::table('roles', fn (Blueprint $table) => $table->string('name')->collation('NOCASE')->change());

        Role::where('name', 'editor')->first()->update(['name' => 'newsroom']);
        $foreign = Role::create(['name' => 'EDITOR']);
        $this->assertTrue(Role::where('name', 'editor')->exists());

        Permission::firstOrCreate(['name' => 'show-form-submission']);
        app(PermissionManager::class)->assignToRole('editor', ['show-form-submission']);

        $this->assertSame([], $foreign->permissions()->pluck('name')->all());
        $this->assertNull(Role::named('editor'));
    }

    public function test_editor_cannot_restore_roles_permissions_or_themes(): void
    {
        $editor = $this->createEditor();
        $role = Role::create(['name' => 'gone']);
        $role->delete();
        Setting::create(['key' => 'theme.modern.footer_text', 'value' => 'x', 'type' => 'theme_setting'])->delete();

        $this->actingAs($editor)
            ->post(route('backend.trash.restore', ['type' => 'role', 'id' => $role->id]))
            ->assertForbidden();
        $this->actingAs($editor)
            ->post(route('backend.trash.restore', ['type' => 'theme', 'id' => 'modern']))
            ->assertForbidden();

        $this->assertSoftDeleted('roles', ['id' => $role->id]);
    }

    public function test_editor_trash_only_lists_his_types(): void
    {
        $editor = $this->createEditor();
        $victim = $this->createUser(['email' => 'hidden-in-trash@example.com']);
        $victim->delete();

        $this->actingAs($editor)
            ->get(route('backend.trash.index', ['tab' => 'users']))
            ->assertOk()
            ->assertDontSee('hidden-in-trash@example.com');
    }

    public function test_trash_theme_id_cannot_be_a_like_wildcard(): void
    {
        $admin = $this->createAdmin();
        Setting::create(['key' => 'theme.modern.footer_text', 'value' => 'x', 'type' => 'theme_setting'])->delete();

        $this->actingAs($admin)
            ->post(route('backend.trash.restore', ['type' => 'theme', 'id' => '%']))
            ->assertNotFound();

        $this->assertSoftDeleted('settings', ['key' => 'theme.modern.footer_text']);
    }

    // --- Settings -----------------------------------------------------------

    public function test_general_settings_cannot_edit_security_or_plugin_settings_by_id(): void
    {
        $admin = $this->createAdmin();
        $security = Setting::where('key', 'security.two_factor_enabled')->first();
        $secret = Setting::create(['key' => 'mail.password', 'value' => 'top-secret', 'type' => 'string']);

        $this->actingAs($admin)->get(route('backend.settings.edit', $security))->assertNotFound();
        $this->actingAs($admin)->get(route('backend.settings.edit', $secret))->assertNotFound();
        $this->actingAs($admin)
            ->put(route('backend.settings.update', $security), ['value' => 'on'])
            ->assertNotFound();

        $this->assertSame('off', $security->fresh()->value);
    }

    // --- Profile ------------------------------------------------------------

    public function test_frontend_user_cannot_view_foreign_profiles(): void
    {
        $user = $this->createUser();
        $other = $this->createUser();

        $this->actingAs($user)->get(route('profile.show', $other))->assertForbidden();
        $this->actingAs($user)->get(route('profile.show', $user))->assertOk();
        $this->actingAs($this->createAdmin())->get(route('profile.show', $other))->assertOk();
    }

    // --- Themes -------------------------------------------------------------

    public function test_theme_destroy_rejects_unknown_or_traversal_slugs(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->delete(route('backend.themes.destroy', ['slug' => 'does-not-exist']))
            ->assertRedirect(route('backend.themes.index'))
            ->assertSessionHasErrors('slug');

        $this->assertDirectoryExists(base_path('themes'));
    }

    public function test_passwords_are_never_mass_assignable_through_profile(): void
    {
        $user = $this->createUser();
        $hash = $user->password;

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'first_name' => 'A',
            'last_name' => 'B',
            'password' => 'Hijacked-Passw0rd!',
            'is_active' => 0,
        ]);

        $user->refresh();
        $this->assertSame($hash, $user->password);
        $this->assertFalse(Hash::check('Hijacked-Passw0rd!', $user->password));
        $this->assertTrue($user->is_active);
    }
}
