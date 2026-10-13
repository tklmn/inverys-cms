<?php

namespace Tests\Feature\Plugins\FormBuilder;

use App\Models\Permission;
use App\Models\Plugin;
use App\Models\Role;
use App\Models\User;
use App\Services\PluginManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Plugins\FormBuilder\FormBuilderServiceProvider;
use Plugins\FormBuilder\Models\Form;
use Tests\TestCase;

class AdminFormControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->markAsInstalled();

        $plugin = Plugin::create([
            'slug' => 'form-builder',
            'name' => 'Form Builder',
            'version' => '1.0.0',
            'is_enabled' => true,
            'is_system' => true,
        ]);

        $manager = app(PluginManager::class);
        $manager->discover();
        $manifest = $manager->get('form-builder');
        $provider = new FormBuilderServiceProvider(app(), $plugin, $manifest);
        $provider->register();
        $provider->boot();
        $provider->install();

        // Refresh route name lookups so route() helper works for late-registered plugin routes
        Route::getRoutes()->refreshNameLookups();
        Route::getRoutes()->refreshActionLookups();
    }

    private function validFormData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Contact Form',
            'slug' => 'contact-form',
            'description' => 'A contact form',
            'fields' => json_encode([
                ['id' => 'f1', 'type' => 'text', 'name' => 'full_name', 'label' => 'Name', 'required' => true, 'width' => 'full'],
                ['id' => 'f2', 'type' => 'email', 'name' => 'email', 'label' => 'Email', 'required' => true, 'width' => 'full'],
                ['id' => 'f3', 'type' => 'textarea', 'name' => 'message', 'label' => 'Message', 'required' => false, 'width' => 'full'],
            ]),
            'is_active' => true,
            'settings_submit_label' => 'Send',
            'settings_success_message' => 'Thank you!',
            'settings_honeypot' => true,
            'settings_send_email' => false,
            'settings_send_copy_to_sender' => false,
            'settings_show_confirmation' => false,
        ], $overrides);
    }

    public function test_admin_can_view_forms_index(): void
    {
        $admin = $this->createAdmin();

        Form::create([
            'name' => 'Test Form',
            'fields' => [],
            'settings' => [],
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('backend.forms.index'))
            ->assertOk();
    }

    public function test_admin_can_view_create_form(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->get(route('backend.forms.create'))
            ->assertOk();
    }

    public function test_admin_can_store_a_new_form(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->post(route('backend.forms.store'), $this->validFormData())
            ->assertRedirect();

        $this->assertDatabaseHas('forms', [
            'name' => 'Contact Form',
            'slug' => 'contact-form',
        ]);
    }

    public function test_store_takes_over_builder_draft_instead_of_leaving_an_orphan(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin);

        // The builder saves a draft on the first field
        $draftId = $this->postJson(route('backend.forms.store-draft'), ['fields' => '[]'])
            ->assertOk()
            ->json('form_id');

        $this->post(route('backend.forms.store'), $this->validFormData(['draft_id' => $draftId]))
            ->assertRedirect(route('backend.forms.edit', $draftId));

        $this->assertSame(1, Form::count());
        $this->assertDatabaseHas('forms', ['id' => $draftId, 'name' => 'Contact Form', 'slug' => 'contact-form']);
    }

    public function test_store_ignores_foreign_draft_id(): void
    {
        $admin = $this->createAdmin();
        $foreign = Form::create(['name' => 'Foreign', 'slug' => 'draft-abcdefgh', 'fields' => [], 'is_active' => false, 'settings' => []]);

        $this->actingAs($admin)
            ->post(route('backend.forms.store'), $this->validFormData(['draft_id' => $foreign->id]))
            ->assertRedirect();

        $this->assertSame(2, Form::count());
        $this->assertDatabaseHas('forms', ['id' => $foreign->id, 'name' => 'Foreign']);
    }

    public function test_admin_can_edit_a_form(): void
    {
        $admin = $this->createAdmin();

        $form = Form::create([
            'name' => 'Test Form',
            'fields' => [
                ['id' => 'f1', 'type' => 'text', 'name' => 'full_name', 'label' => 'Name', 'required' => true, 'width' => 'full'],
            ],
            'settings' => ['submit_label' => 'Send'],
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('backend.forms.edit', $form))
            ->assertOk();
    }

    public function test_admin_can_update_a_form(): void
    {
        $admin = $this->createAdmin();

        $form = Form::create([
            'name' => 'Old Form',
            'slug' => 'old-form',
            'fields' => [
                ['id' => 'f1', 'type' => 'text', 'name' => 'full_name', 'label' => 'Name', 'required' => true, 'width' => 'full'],
            ],
            'settings' => ['submit_label' => 'Send'],
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->put(route('backend.forms.update', $form), $this->validFormData([
                'name' => 'Updated Form',
                'slug' => 'old-form',
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('forms', [
            'id' => $form->id,
            'name' => 'Updated Form',
        ]);
    }

    public function test_admin_can_delete_a_form(): void
    {
        $admin = $this->createAdmin();

        $form = Form::create([
            'name' => 'Deletable Form',
            'fields' => [],
            'settings' => [],
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->delete(route('backend.forms.destroy', $form))
            ->assertRedirect();

        $this->assertSoftDeleted('forms', ['id' => $form->id]);
    }

    public function test_editor_can_delete_form(): void
    {
        $editor = $this->createEditor();

        $form = Form::create([
            'name' => 'Editor Form',
            'fields' => [],
            'settings' => [],
            'is_active' => true,
        ]);

        // syncPermissions() grants the editor role delete-form (content manager)
        $this->actingAs($editor)
            ->delete(route('backend.forms.destroy', $form))
            ->assertRedirect();

        $this->assertSoftDeleted('forms', ['id' => $form->id]);
    }

    public function test_user_without_delete_form_gets_403_on_delete(): void
    {
        $role = Role::create(['name' => 'form-reviewer', 'is_system' => false]);
        $role->permissions()->attach(Permission::whereIn('name', ['access-dashboard', 'show-form', 'edit-form'])->pluck('id'));
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        $form = Form::create([
            'name' => 'Protected Form',
            'fields' => [],
            'settings' => [],
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->delete(route('backend.forms.destroy', $form))
            ->assertForbidden();

        $this->assertNotSoftDeleted('forms', ['id' => $form->id]);
    }

    public function test_editor_cannot_open_form_settings(): void
    {
        $editor = $this->createEditor();

        $this->actingAs($editor)->get(route('backend.forms.settings'))->assertForbidden();
        $this->actingAs($editor)->put(route('backend.forms.settings.update'), [])->assertForbidden();
    }

    public function test_store_requires_name_validation(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->post(route('backend.forms.store'), $this->validFormData(['name' => '']))
            ->assertSessionHasErrors('name');
    }

    /**
     * The slug field is nullable; when it is omitted entirely from the request
     * the controller must fall back to slugging the name instead of 500ing on an
     * undefined "slug" array key.
     */
    public function test_admin_can_store_form_without_slug(): void
    {
        $admin = $this->createAdmin();

        $data = $this->validFormData();
        unset($data['slug']);

        $response = $this->actingAs($admin)->post(route('backend.forms.store'), $data);

        $this->assertNotSame(500, $response->getStatusCode());
        $response->assertRedirect();
        $this->assertDatabaseHas('forms', [
            'name' => 'Contact Form',
            'slug' => 'contact-form',
        ]);
    }
}
