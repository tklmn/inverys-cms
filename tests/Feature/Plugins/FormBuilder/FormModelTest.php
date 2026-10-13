<?php

namespace Tests\Feature\Plugins\FormBuilder;

use App\Models\Plugin;
use App\Services\PluginManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Plugins\FormBuilder\FormBuilderServiceProvider;
use Plugins\FormBuilder\Models\Form;
use Plugins\FormBuilder\Models\FormSubmission;
use Tests\TestCase;

class FormModelTest extends TestCase
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

    public function test_can_create_a_form(): void
    {
        $form = Form::create([
            'name' => 'Contact Form',
            'slug' => 'contact-form',
            'description' => 'A simple contact form',
            'fields' => [
                ['id' => 'f1', 'type' => 'text', 'name' => 'full_name', 'label' => 'Name', 'required' => true, 'width' => 'full'],
            ],
            'settings' => ['submit_label' => 'Send'],
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('forms', [
            'id' => $form->id,
            'name' => 'Contact Form',
            'slug' => 'contact-form',
        ]);
    }

    public function test_fields_cast_to_array(): void
    {
        $fields = [
            ['id' => 'f1', 'type' => 'text', 'name' => 'full_name', 'label' => 'Name', 'required' => true, 'width' => 'full'],
            ['id' => 'f2', 'type' => 'email', 'name' => 'email', 'label' => 'Email', 'required' => true, 'width' => 'full'],
        ];

        $form = Form::create([
            'name' => 'Test Form',
            'fields' => $fields,
            'settings' => [],
            'is_active' => true,
        ]);

        $form->refresh();

        $this->assertIsArray($form->fields);
        $this->assertCount(2, $form->fields);
        $this->assertSame('full_name', $form->fields[0]['name']);
    }

    public function test_settings_cast_to_array(): void
    {
        $settings = [
            'submit_label' => 'Send',
            'success_message' => 'Thank you!',
            'honeypot' => true,
            'send_email' => false,
        ];

        $form = Form::create([
            'name' => 'Test Form',
            'fields' => [],
            'settings' => $settings,
            'is_active' => true,
        ]);

        $form->refresh();

        $this->assertIsArray($form->settings);
        $this->assertSame('Send', $form->settings['submit_label']);
        $this->assertSame('Thank you!', $form->settings['success_message']);
    }

    public function test_is_active_cast_to_boolean(): void
    {
        $form = Form::create([
            'name' => 'Test Form',
            'fields' => [],
            'settings' => [],
            'is_active' => 1,
        ]);

        $form->refresh();

        $this->assertIsBool($form->is_active);
        $this->assertTrue($form->is_active);
    }

    public function test_soft_delete_works(): void
    {
        $form = Form::create([
            'name' => 'Test Form',
            'fields' => [],
            'settings' => [],
            'is_active' => true,
        ]);

        $form->delete();

        $this->assertSoftDeleted('forms', ['id' => $form->id]);
        $this->assertNull(Form::find($form->id));
        $this->assertNotNull(Form::withTrashed()->find($form->id));
    }

    public function test_submissions_relationship_works(): void
    {
        $form = Form::create([
            'name' => 'Test Form',
            'fields' => [
                ['id' => 'f1', 'type' => 'text', 'name' => 'full_name', 'label' => 'Name', 'required' => true, 'width' => 'full'],
            ],
            'settings' => [],
            'is_active' => true,
        ]);

        FormSubmission::create([
            'form_id' => $form->id,
            'data' => ['full_name' => 'John Doe'],
        ]);

        FormSubmission::create([
            'form_id' => $form->id,
            'data' => ['full_name' => 'Jane Doe'],
        ]);

        $this->assertCount(2, $form->submissions);
        $this->assertInstanceOf(FormSubmission::class, $form->submissions->first());
    }

    public function test_flat_fields_returns_flat_array(): void
    {
        $form = Form::create([
            'name' => 'Test Form',
            'fields' => [
                ['id' => 'f1', 'type' => 'text', 'name' => 'full_name', 'label' => 'Name', 'required' => true, 'width' => 'full'],
                [
                    'type' => 'row',
                    'children' => [
                        [
                            'type' => 'column',
                            'children' => [
                                ['id' => 'f2', 'type' => 'email', 'name' => 'email', 'label' => 'Email', 'required' => true, 'width' => 'full'],
                            ],
                        ],
                        [
                            'type' => 'column',
                            'children' => [
                                ['id' => 'f3', 'type' => 'tel', 'name' => 'phone', 'label' => 'Phone', 'required' => false, 'width' => 'full'],
                            ],
                        ],
                    ],
                ],
            ],
            'settings' => [],
            'is_active' => true,
        ]);

        $flatFields = $form->getFlatFields();

        $this->assertCount(3, $flatFields);
        $names = array_column($flatFields, 'name');
        $this->assertSame(['full_name', 'email', 'phone'], $names);
    }

    public function test_get_field_by_name_returns_correct_field(): void
    {
        $form = Form::create([
            'name' => 'Test Form',
            'fields' => [
                ['id' => 'f1', 'type' => 'text', 'name' => 'full_name', 'label' => 'Name', 'required' => true, 'width' => 'full'],
                ['id' => 'f2', 'type' => 'email', 'name' => 'email', 'label' => 'Email', 'required' => true, 'width' => 'full'],
                ['id' => 'f3', 'type' => 'textarea', 'name' => 'message', 'label' => 'Message', 'required' => false, 'width' => 'full'],
            ],
            'settings' => [],
            'is_active' => true,
        ]);

        $field = $form->getFieldByName('email');

        $this->assertNotNull($field);
        $this->assertSame('email', $field['name']);
        $this->assertSame('Email', $field['label']);
        $this->assertSame('email', $field['type']);

        $this->assertNull($form->getFieldByName('nonexistent'));
    }

    public function test_data_fields_skip_captcha_and_static_text(): void
    {
        $form = Form::create([
            'name' => 'Contact',
            'slug' => 'contact',
            'fields' => [
                ['id' => 'a', 'type' => 'text', 'name' => 'full_name', 'label' => 'Name'],
                ['id' => 'b', 'type' => 'static-text', 'label' => 'Note'],
                ['id' => 'c', 'type' => 'row', 'children' => [
                    ['id' => 'd', 'type' => 'column', 'children' => [
                        ['id' => 'e', 'type' => 'email', 'name' => 'email', 'label' => 'Email'],
                    ]],
                ]],
                ['id' => 'f', 'type' => 'captcha', 'name' => '_captcha', 'label' => 'Captcha'],
            ],
        ]);

        // Captcha and static text carry no values — neither column nor row
        $this->assertSame(['full_name', 'email'], array_column($form->getDataFields(), 'name'));
    }
}
