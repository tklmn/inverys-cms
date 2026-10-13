<?php

namespace Tests\Feature\Plugins\FormBuilder;

use App\Models\Plugin;
use App\Services\PluginManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Plugins\FormBuilder\FormBuilderServiceProvider;
use Plugins\FormBuilder\Mail\FormSubmissionNotification;
use Plugins\FormBuilder\Models\Form;
use Plugins\FormBuilder\Models\FormSubmission;
use Tests\TestCase;

class FormSubmitControllerTest extends TestCase
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

    private function createActiveForm(array $overrides = []): Form
    {
        return Form::create(array_merge([
            'name' => 'Contact Form',
            'slug' => 'contact-form',
            'description' => 'A test form',
            'fields' => [
                ['id' => 'f1', 'type' => 'text', 'name' => 'full_name', 'label' => 'Name', 'required' => true, 'width' => 'full'],
                ['id' => 'f2', 'type' => 'email', 'name' => 'email', 'label' => 'Email', 'required' => true, 'width' => 'full'],
                ['id' => 'f3', 'type' => 'textarea', 'name' => 'message', 'label' => 'Message', 'required' => false, 'width' => 'full'],
            ],
            'settings' => [
                'submit_label' => 'Send',
                'success_message' => 'Thank you!',
                'honeypot' => true,
                'send_email' => false,
            ],
            'is_active' => true,
        ], $overrides));
    }

    public function test_can_submit_a_form_with_valid_data(): void
    {
        $form = $this->createActiveForm();

        $this->post(route('forms.submit', $form->slug), [
            'full_name' => 'John Doe',
            'email' => 'john@example.com',
            'message' => 'Hello there!',
        ])->assertRedirect();
    }

    public function test_submission_is_stored_in_database(): void
    {
        $form = $this->createActiveForm();

        $this->post(route('forms.submit', $form->slug), [
            'full_name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'message' => 'Testing submission storage',
        ]);

        $this->assertDatabaseHas('form_submissions', [
            'form_id' => $form->id,
        ]);

        $submission = FormSubmission::where('form_id', $form->id)->first();
        $this->assertNotNull($submission);
        $this->assertSame('Jane Smith', $submission->data['full_name']);
        $this->assertSame('jane@example.com', $submission->data['email']);
    }

    public function test_required_field_validation_works(): void
    {
        $form = $this->createActiveForm();

        $this->post(route('forms.submit', $form->slug), [
            // full_name and email are required but missing
            'message' => 'Just a message',
        ])->assertSessionHasErrors(['full_name', 'email']);

        $this->assertDatabaseMissing('form_submissions', [
            'form_id' => $form->id,
        ]);
    }

    public function test_inactive_form_returns_404(): void
    {
        $form = $this->createActiveForm(['is_active' => false]);

        $this->post(route('forms.submit', $form->slug), [
            'full_name' => 'John Doe',
            'email' => 'john@example.com',
        ])->assertNotFound();
    }

    public function test_honeypot_field_filled_silently_rejects(): void
    {
        $form = $this->createActiveForm();

        // The honeypot field name is `_honeypot` based on FormSubmitController
        $this->post(route('forms.submit', $form->slug), [
            'full_name' => 'Spam Bot',
            'email' => 'bot@spam.com',
            'message' => 'Buy stuff!',
            '_honeypot' => 'I am a bot',
        ])->assertRedirect();

        // Submission should NOT be stored
        $this->assertDatabaseMissing('form_submissions', [
            'form_id' => $form->id,
        ]);
    }

    public function test_sender_confirmation_never_contains_the_submitted_text(): void
    {
        Mail::fake();
        $form = $this->createActiveForm(['settings' => ['send_copy_to_sender' => true, 'copy_message' => 'Danke, wir melden uns.']]);

        $this->post(route('forms.submit', $form->slug), [
            'full_name' => 'Spammer',
            'email' => 'victim@example.com',
            'message' => 'Buy cheap pills at evil.example',
        ])->assertRedirect();

        Mail::assertSent(FormSubmissionNotification::class, function ($mail) {
            $html = $mail->render();

            return $mail->hasTo('victim@example.com')
                && str_contains($html, 'Danke, wir melden uns.')
                && ! str_contains($html, 'evil.example')
                && ! str_contains($html, 'Spammer');
        });
    }

    public function test_sender_confirmations_are_limited_per_address(): void
    {
        Mail::fake();
        $form = $this->createActiveForm(['settings' => ['send_copy_to_sender' => true]]);

        for ($i = 0; $i < 5; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.'.$i])
                ->post(route('forms.submit', $form->slug), ['full_name' => 'X', 'email' => 'victim@example.com']);
        }

        Mail::assertSent(FormSubmissionNotification::class, 3);
    }
}
