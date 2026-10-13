<?php

namespace Tests\Feature\Plugins\FormBuilder;

use App\Models\Plugin;
use App\Services\PluginManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Plugins\FormBuilder\FormBuilderServiceProvider;
use Plugins\FormBuilder\Models\Form;
use Plugins\FormBuilder\Models\FormSubmission;
use Tests\TestCase;

class FormSubmissionModelTest extends TestCase
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
    }

    public function test_can_create_a_submission(): void
    {
        $form = Form::create([
            'name' => 'Contact Form',
            'fields' => [
                ['id' => 'f1', 'type' => 'text', 'name' => 'full_name', 'label' => 'Name', 'required' => true, 'width' => 'full'],
            ],
            'settings' => [],
            'is_active' => true,
        ]);

        $submission = FormSubmission::create([
            'form_id' => $form->id,
            'data' => ['full_name' => 'John Doe'],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
        ]);

        $this->assertDatabaseHas('form_submissions', [
            'id' => $submission->id,
            'form_id' => $form->id,
            'ip_address' => '127.0.0.1',
        ]);
    }

    public function test_data_cast_to_array(): void
    {
        $form = Form::create([
            'name' => 'Test Form',
            'fields' => [
                ['id' => 'f1', 'type' => 'text', 'name' => 'full_name', 'label' => 'Name', 'required' => true, 'width' => 'full'],
                ['id' => 'f2', 'type' => 'email', 'name' => 'email', 'label' => 'Email', 'required' => true, 'width' => 'full'],
            ],
            'settings' => [],
            'is_active' => true,
        ]);

        $submission = FormSubmission::create([
            'form_id' => $form->id,
            'data' => ['full_name' => 'John Doe', 'email' => 'john@example.com'],
        ]);

        $submission->refresh();

        $this->assertIsArray($submission->data);
        $this->assertSame('John Doe', $submission->data['full_name']);
        $this->assertSame('john@example.com', $submission->data['email']);
    }

    public function test_is_read_cast_to_boolean(): void
    {
        $form = Form::create([
            'name' => 'Test Form',
            'fields' => [],
            'settings' => [],
            'is_active' => true,
        ]);

        $submission = FormSubmission::create([
            'form_id' => $form->id,
            'data' => ['test' => 'value'],
            'is_read' => 1,
        ]);

        $submission->refresh();

        $this->assertIsBool($submission->is_read);
        $this->assertTrue($submission->is_read);
    }

    public function test_form_relationship_works(): void
    {
        $form = Form::create([
            'name' => 'Test Form',
            'fields' => [
                ['id' => 'f1', 'type' => 'text', 'name' => 'full_name', 'label' => 'Name', 'required' => true, 'width' => 'full'],
            ],
            'settings' => [],
            'is_active' => true,
        ]);

        $submission = FormSubmission::create([
            'form_id' => $form->id,
            'data' => ['full_name' => 'Jane Doe'],
        ]);

        $this->assertInstanceOf(Form::class, $submission->form);
        $this->assertSame($form->id, $submission->form->id);
        $this->assertSame('Test Form', $submission->form->name);
    }
}
