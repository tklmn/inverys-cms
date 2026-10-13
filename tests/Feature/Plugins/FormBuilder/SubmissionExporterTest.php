<?php

namespace Tests\Feature\Plugins\FormBuilder;

use App\Models\Plugin;
use App\Services\PluginManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Plugins\FormBuilder\FormBuilderServiceProvider;
use Plugins\FormBuilder\Models\Form;
use Plugins\FormBuilder\Models\FormSubmission;
use Plugins\FormBuilder\Services\SubmissionExporter;
use Tests\TestCase;

class SubmissionExporterTest extends TestCase
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

    public function test_exports_csv_with_headers_from_form_fields(): void
    {
        $form = Form::create([
            'name' => 'Test Form',
            'slug' => 'test-form',
            'fields' => [
                ['id' => 'f1', 'type' => 'text', 'name' => 'full_name', 'label' => 'Name', 'required' => true, 'width' => 'full'],
                ['id' => 'f2', 'type' => 'email', 'name' => 'email', 'label' => 'Email', 'required' => true, 'width' => 'full'],
            ],
            'settings' => [],
            'is_active' => true,
        ]);

        FormSubmission::create([
            'form_id' => $form->id,
            'data' => ['full_name' => 'John Doe', 'email' => 'john@example.com'],
            'ip_address' => '127.0.0.1',
        ]);

        $submissions = $form->submissions()->get();
        $response = SubmissionExporter::export($form, $submissions);

        // Capture the streamed output
        ob_start();
        $response->sendContent();
        $csv = ob_get_clean();

        // Strip BOM
        $csv = ltrim($csv, "\xEF\xBB\xBF");
        $lines = array_filter(explode("\n", trim($csv)));

        $this->assertNotEmpty($lines);
        // Header row should contain field labels and metadata columns
        $this->assertStringContainsString('Name', $lines[0]);
        $this->assertStringContainsString('Email', $lines[0]);
        $this->assertStringContainsString('Submitted At', $lines[0]);
        $this->assertStringContainsString('IP Address', $lines[0]);
    }

    public function test_exports_submission_data_rows(): void
    {
        $form = Form::create([
            'name' => 'Test Form',
            'slug' => 'test-form',
            'fields' => [
                ['id' => 'f1', 'type' => 'text', 'name' => 'full_name', 'label' => 'Name', 'required' => true, 'width' => 'full'],
                ['id' => 'f2', 'type' => 'email', 'name' => 'email', 'label' => 'Email', 'required' => true, 'width' => 'full'],
            ],
            'settings' => [],
            'is_active' => true,
        ]);

        FormSubmission::create([
            'form_id' => $form->id,
            'data' => ['full_name' => 'John Doe', 'email' => 'john@example.com'],
            'ip_address' => '10.0.0.1',
        ]);

        FormSubmission::create([
            'form_id' => $form->id,
            'data' => ['full_name' => 'Jane Smith', 'email' => 'jane@example.com'],
            'ip_address' => '10.0.0.2',
        ]);

        $submissions = $form->submissions()->get();
        $response = SubmissionExporter::export($form, $submissions);

        ob_start();
        $response->sendContent();
        $csv = ob_get_clean();

        $csv = ltrim($csv, "\xEF\xBB\xBF");
        $lines = array_filter(explode("\n", trim($csv)));

        // 1 header row + 2 data rows
        $this->assertCount(3, $lines);
        $this->assertStringContainsString('John Doe', $lines[1]);
        $this->assertStringContainsString('john@example.com', $lines[1]);
        $this->assertStringContainsString('Jane Smith', $lines[2]);
        $this->assertStringContainsString('jane@example.com', $lines[2]);
    }

    public function test_handles_empty_submissions(): void
    {
        $form = Form::create([
            'name' => 'Empty Form',
            'slug' => 'empty-form',
            'fields' => [
                ['id' => 'f1', 'type' => 'text', 'name' => 'full_name', 'label' => 'Name', 'required' => true, 'width' => 'full'],
            ],
            'settings' => [],
            'is_active' => true,
        ]);

        $submissions = $form->submissions()->get();
        $response = SubmissionExporter::export($form, $submissions);

        ob_start();
        $response->sendContent();
        $csv = ob_get_clean();

        $csv = ltrim($csv, "\xEF\xBB\xBF");
        $lines = array_filter(explode("\n", trim($csv)));

        // Only the header row
        $this->assertCount(1, $lines);
        $this->assertStringContainsString('Name', $lines[0]);
    }
}
