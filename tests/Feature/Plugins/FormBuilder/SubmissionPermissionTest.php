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
use Plugins\FormBuilder\Models\FormSubmission;
use Tests\TestCase;

class SubmissionPermissionTest extends TestCase
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

        Route::getRoutes()->refreshNameLookups();
        Route::getRoutes()->refreshActionLookups();
    }

    private function makeForm(): Form
    {
        return Form::create([
            'name' => 'Contact',
            'slug' => 'contact',
            'fields' => [],
            'settings' => [],
            'is_active' => true,
        ]);
    }

    private function makeSubmission(Form $form, bool $read = false): FormSubmission
    {
        return FormSubmission::create([
            'form_id' => $form->id,
            'data' => ['name' => 'Alice'],
            'is_read' => $read,
        ]);
    }

    /**
     * A user with read-only access to submissions must not be able to mutate
     * them. toggleRead flips is_read/read_at, so it requires the write-level
     * edit-form-submission permission — not show-form-submission.
     */
    public function test_read_only_user_cannot_toggle_read(): void
    {
        $role = Role::create(['name' => 'submission-viewer', 'is_system' => false]);
        $role->permissions()->attach(Permission::where('name', 'show-form-submission')->value('id'));

        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        $form = $this->makeForm();
        $submission = $this->makeSubmission($form);

        $this->actingAs($user)
            ->post(route('backend.forms.submissions.toggle-read', [$form, $submission]))
            ->assertForbidden();

        $this->assertDatabaseHas('form_submissions', [
            'id' => $submission->id,
            'is_read' => false,
        ]);
    }

    public function test_admin_can_toggle_read(): void
    {
        $admin = $this->createAdmin();
        $form = $this->makeForm();
        $submission = $this->makeSubmission($form);

        $this->actingAs($admin)
            ->post(route('backend.forms.submissions.toggle-read', [$form, $submission]))
            ->assertRedirect();

        $this->assertDatabaseHas('form_submissions', [
            'id' => $submission->id,
            'is_read' => true,
        ]);
    }

    public function test_editor_can_toggle_read(): void
    {
        // syncPermissions() grants the editor role edit-form-submission.
        $editor = $this->createEditor();
        $form = $this->makeForm();
        $submission = $this->makeSubmission($form);

        $this->actingAs($editor)
            ->post(route('backend.forms.submissions.toggle-read', [$form, $submission]))
            ->assertRedirect();

        $this->assertDatabaseHas('form_submissions', [
            'id' => $submission->id,
            'is_read' => true,
        ]);
    }

    public function test_editor_can_delete_submission(): void
    {
        // syncPermissions() grants the editor role delete-form-submission.
        $editor = $this->createEditor();
        $form = $this->makeForm();
        $submission = $this->makeSubmission($form);

        $this->actingAs($editor)
            ->delete(route('backend.forms.submissions.destroy', [$form, $submission]))
            ->assertRedirect();

        $this->assertDatabaseMissing('form_submissions', ['id' => $submission->id]);
    }
}
