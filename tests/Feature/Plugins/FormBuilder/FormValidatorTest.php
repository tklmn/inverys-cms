<?php

namespace Tests\Feature\Plugins\FormBuilder;

use App\Models\Plugin;
use App\Services\PluginManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Plugins\FormBuilder\FormBuilderServiceProvider;
use Plugins\FormBuilder\Models\Form;
use Plugins\FormBuilder\Services\FormValidator;
use Tests\TestCase;

class FormValidatorTest extends TestCase
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

    public function test_builds_required_rule_for_required_field(): void
    {
        $form = Form::create([
            'name' => 'Test Form',
            'fields' => [
                ['id' => 'f1', 'type' => 'text', 'name' => 'full_name', 'label' => 'Name', 'required' => true, 'width' => 'full'],
            ],
            'settings' => [],
            'is_active' => true,
        ]);

        $rules = FormValidator::buildRules($form);

        $this->assertArrayHasKey('full_name', $rules);
        $this->assertContains('required', $rules['full_name']);
    }

    public function test_builds_nullable_rule_for_optional_field(): void
    {
        $form = Form::create([
            'name' => 'Test Form',
            'fields' => [
                ['id' => 'f1', 'type' => 'text', 'name' => 'notes', 'label' => 'Notes', 'required' => false, 'width' => 'full'],
            ],
            'settings' => [],
            'is_active' => true,
        ]);

        $rules = FormValidator::buildRules($form);

        $this->assertArrayHasKey('notes', $rules);
        $this->assertContains('nullable', $rules['notes']);
        $this->assertNotContains('required', $rules['notes']);
    }

    public function test_builds_email_rule_for_email_field_type(): void
    {
        $form = Form::create([
            'name' => 'Test Form',
            'fields' => [
                ['id' => 'f1', 'type' => 'email', 'name' => 'email', 'label' => 'Email', 'required' => true, 'width' => 'full'],
            ],
            'settings' => [],
            'is_active' => true,
        ]);

        $rules = FormValidator::buildRules($form);

        $this->assertArrayHasKey('email', $rules);
        $this->assertContains('email', $rules['email']);
    }

    public function test_builds_file_rules_for_file_field_type(): void
    {
        $form = Form::create([
            'name' => 'Test Form',
            'fields' => [
                ['id' => 'f1', 'type' => 'file', 'name' => 'attachment', 'label' => 'Attachment', 'required' => false, 'width' => 'full'],
            ],
            'settings' => [],
            'is_active' => true,
        ]);

        $rules = FormValidator::buildRules($form);

        $this->assertArrayHasKey('attachment', $rules);
        $this->assertContains('file', $rules['attachment']);

        // Should have a max rule
        $hasMax = false;
        $hasMimes = false;
        foreach ($rules['attachment'] as $rule) {
            if (is_string($rule) && str_starts_with($rule, 'max:')) {
                $hasMax = true;
            }
            if (is_string($rule) && str_starts_with($rule, 'mimes:')) {
                $hasMimes = true;
            }
        }
        $this->assertTrue($hasMax, 'File field should have a max size rule');
        $this->assertTrue($hasMimes, 'File field should have a mimes rule');
    }

    public function test_custom_validation_rules_are_filtered_through_whitelist(): void
    {
        $form = Form::create([
            'name' => 'Test Form',
            'fields' => [
                [
                    'id' => 'f1',
                    'type' => 'text',
                    'name' => 'age',
                    'label' => 'Age',
                    'required' => true,
                    'width' => 'full',
                    'validation' => 'min:1|max:120',
                ],
            ],
            'settings' => [],
            'is_active' => true,
        ]);

        $rules = FormValidator::buildRules($form);

        $this->assertArrayHasKey('age', $rules);
        $this->assertContains('min:1', $rules['age']);
        $this->assertContains('max:120', $rules['age']);
    }

    public function test_dangerous_rules_are_blocked(): void
    {
        $form = Form::create([
            'name' => 'Test Form',
            'fields' => [
                [
                    'id' => 'f1',
                    'type' => 'text',
                    'name' => 'username',
                    'label' => 'Username',
                    'required' => true,
                    'width' => 'full',
                    'validation' => 'exists:users,email|unique:users,email|min:3',
                ],
            ],
            'settings' => [],
            'is_active' => true,
        ]);

        $rules = FormValidator::buildRules($form);

        $this->assertArrayHasKey('username', $rules);

        // min:3 should pass the whitelist
        $this->assertContains('min:3', $rules['username']);

        // exists and unique should be blocked
        $ruleString = implode('|', $rules['username']);
        $this->assertStringNotContainsString('exists:', $ruleString);
        $this->assertStringNotContainsString('unique:', $ruleString);
    }

    public function test_empty_fields_array_returns_empty_rules(): void
    {
        $form = Form::create([
            'name' => 'Empty Form',
            'fields' => [],
            'settings' => [],
            'is_active' => true,
        ]);

        $rules = FormValidator::buildRules($form);

        $this->assertIsArray($rules);
        $this->assertEmpty($rules);
    }
}
