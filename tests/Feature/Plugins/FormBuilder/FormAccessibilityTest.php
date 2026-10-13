<?php

namespace Tests\Feature\Plugins\FormBuilder;

use App\Models\Plugin;
use App\Services\PluginManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Plugins\FormBuilder\FormBuilderServiceProvider;
use Plugins\FormBuilder\Models\Form;
use Plugins\FormBuilder\Services\FormRenderer;
use Tests\TestCase;

/**
 * WCAG 2.2 AA of the rendered forms: names, descriptions, groups, unique ids,
 * error handling, autofill and focus targets.
 */
class FormAccessibilityTest extends TestCase
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
        $provider = new FormBuilderServiceProvider(app(), $plugin, $manager->get('form-builder'));
        $provider->register();
        $provider->boot();
        $provider->install();

        Route::getRoutes()->refreshNameLookups();
        Route::getRoutes()->refreshActionLookups();
    }

    private function makeForm(array $overrides = []): Form
    {
        return Form::create(array_merge([
            'name' => 'Contact Form',
            'slug' => 'contact-form',
            'fields' => [
                ['id' => 'f1', 'type' => 'text', 'name' => 'full_name', 'label' => 'Name', 'required' => true, 'help_text' => 'First and last name'],
                ['id' => 'f2', 'type' => 'email', 'name' => 'email', 'label' => 'Email', 'required' => true],
                ['id' => 'f3', 'type' => 'radio', 'name' => 'topic', 'label' => 'Topic', 'required' => true, 'options' => [
                    ['label' => 'Sales', 'value' => 'sales'],
                    ['label' => 'Support', 'value' => 'support'],
                ]],
                ['id' => 'f4', 'type' => 'checkbox-group', 'name' => 'interests', 'label' => 'Interests', 'required' => true, 'options' => [
                    ['label' => 'News', 'value' => 'news'],
                    ['label' => 'Events', 'value' => 'events'],
                ]],
                ['id' => 'f5', 'type' => 'textarea', 'name' => 'message', 'label' => 'Message'],
            ],
            'settings' => ['honeypot' => true],
            'is_active' => true,
        ], $overrides));
    }

    /** Errors as they arrive after a failed submit of the given copy of a form */
    private function flashErrors(array $errors, ?string $instance = 'contact-form'): void
    {
        session()->put('errors', (new ViewErrorBag)->put('default', new MessageBag($errors)));
        session()->put('_old_input', $instance !== null ? ['_fb_instance' => $instance] : []);
    }

    /** Raw opening tag of the element with the given name (duplicate attributes stay visible) */
    private function tag(string $html, string $element, string $name): string
    {
        $this->assertSame(1, preg_match('/<'.$element.'\b[^>]*\bname="'.preg_quote($name, '/').'"[^>]*>/s', $html, $m), "No <$element name=\"$name\">");

        return $m[0];
    }

    private function attr(string $tag, string $attribute): ?string
    {
        return preg_match('/\s'.preg_quote($attribute, '/').'="([^"]*)"/', $tag, $m) ? $m[1] : null;
    }

    public function test_help_text_and_error_share_one_aria_describedby(): void
    {
        $form = $this->makeForm();
        $this->flashErrors(['full_name' => ['The Name field is required.']]);

        $tag = $this->tag(FormRenderer::render($form), 'input', 'full_name');

        $this->assertSame(1, substr_count($tag, 'aria-describedby='));
        $this->assertSame('fb-contact-form-full_name-help fb-contact-form-full_name-error', $this->attr($tag, 'aria-describedby'));
    }

    public function test_described_by_is_omitted_without_help_or_error(): void
    {
        $tag = $this->tag(FormRenderer::render($this->makeForm()), 'textarea', 'message');

        $this->assertStringNotContainsString('aria-describedby', $tag);
        $this->assertStringNotContainsString('aria-invalid', $tag);
    }

    public function test_radio_and_checkbox_groups_use_fieldset_and_legend(): void
    {
        $html = FormRenderer::render($this->makeForm());

        $this->assertMatchesRegularExpression('/<fieldset id="fb-contact-form-topic">\s*<legend[^>]*>\s*Topic/s', $html);
        $this->assertMatchesRegularExpression('/<fieldset id="fb-contact-form-interests">\s*<legend[^>]*>\s*Interests/s', $html);
        $this->assertStringContainsString('id="fb-contact-form-topic-0"', $html);
        $this->assertStringContainsString('for="fb-contact-form-topic-0"', $html);

        // "required" on checkboxes would force every box — the legend says it instead
        $this->assertMatchesRegularExpression('/Interests\s*<span class="text-red-600" aria-hidden="true">\*<\/span><span class="sr-only">\(required\)<\/span>/', $html);
    }

    public function test_ids_are_unique_with_two_forms_on_one_page(): void
    {
        $contact = $this->makeForm();
        $newsletter = $this->makeForm(['name' => 'Newsletter', 'slug' => 'newsletter']);

        // Two different forms and a second copy of the first one
        $html = FormRenderer::render($contact).FormRenderer::render($newsletter).FormRenderer::render($contact);

        preg_match_all('/\sid="([^"]+)"/', $html, $m);
        $duplicates = array_keys(array_filter(array_count_values($m[1]), fn ($n) => $n > 1));
        $this->assertSame([], $duplicates, 'Duplicate ids: '.implode(', ', $duplicates));

        $this->assertStringContainsString('id="fb-contact-form-email"', $html);
        $this->assertStringContainsString('id="fb-newsletter-email"', $html);
        $this->assertStringContainsString('id="fb-contact-form-2-email"', $html);
        $this->assertStringContainsString('id="form-contact-form-2"', $html);

        // Every label points at an existing control
        preg_match_all('/<label for="([^"]+)"/', $html, $labels);
        foreach ($labels[1] as $for) {
            $this->assertContains($for, $m[1], "Label for=\"$for\" has no target");
        }
    }

    public function test_errors_stay_with_the_copy_of_the_form_that_was_sent(): void
    {
        $contact = $this->makeForm();
        $newsletter = $this->makeForm(['name' => 'Newsletter', 'slug' => 'newsletter']);
        $this->flashErrors(['email' => ['The Email field must be a valid email address.']], 'newsletter');

        $contactHtml = FormRenderer::render($contact);
        $newsletterHtml = FormRenderer::render($newsletter);

        $this->assertStringNotContainsString('aria-invalid', $contactHtml);
        $this->assertStringNotContainsString('form-errors-', $contactHtml);
        $this->assertSame('true', $this->attr($this->tag($newsletterHtml, 'input', 'email'), 'aria-invalid'));
    }

    public function test_autocomplete_is_rendered(): void
    {
        $form = $this->makeForm(['fields' => [
            ['id' => 'a', 'type' => 'text', 'name' => 'first', 'label' => 'First name', 'autocomplete' => 'given-name'],
            ['id' => 'b', 'type' => 'email', 'name' => 'mail', 'label' => 'Email'],
            ['id' => 'c', 'type' => 'tel', 'name' => 'phone', 'label' => 'Phone', 'autocomplete' => 'off'],
            ['id' => 'd', 'type' => 'text', 'name' => 'evil', 'label' => 'Evil', 'autocomplete' => '" onfocus="x'],
            ['id' => 'e', 'type' => 'text', 'name' => 'plain', 'label' => 'Plain'],
            ['id' => 'f', 'type' => 'select', 'name' => 'country', 'label' => 'Country', 'autocomplete' => 'country-name', 'options' => [['label' => 'Germany', 'value' => 'de']]],
        ]]);

        $html = FormRenderer::render($form);

        $this->assertSame('given-name', $this->attr($this->tag($html, 'input', 'first'), 'autocomplete'));
        // Type default when the editor chose "automatic"
        $this->assertSame('email', $this->attr($this->tag($html, 'input', 'mail'), 'autocomplete'));
        $this->assertSame('off', $this->attr($this->tag($html, 'input', 'phone'), 'autocomplete'));
        // Unknown tokens are dropped
        $this->assertNull($this->attr($this->tag($html, 'input', 'evil'), 'autocomplete'));
        $this->assertStringNotContainsString('onfocus', $html);
        $this->assertNull($this->attr($this->tag($html, 'input', 'plain'), 'autocomplete'));
        $this->assertSame('country-name', $this->attr($this->tag($html, 'select', 'country'), 'autocomplete'));
    }

    public function test_invalid_fields_get_aria_invalid_and_an_error_summary(): void
    {
        $form = $this->makeForm();
        $this->flashErrors([
            'full_name' => ['The Name field is required.'],
            'topic' => ['The Topic field is required.'],
            'interests.0' => ['The selected Interests is invalid.'],
        ]);

        $html = FormRenderer::render($form);

        $this->assertSame('true', $this->attr($this->tag($html, 'input', 'full_name'), 'aria-invalid'));
        $this->assertNull($this->attr($this->tag($html, 'input', 'email'), 'aria-invalid'));
        $this->assertStringContainsString('id="fb-contact-form-full_name-error"', $html);

        // Summary: focusable, linked to the fields (groups link to their first option), in field order
        $this->assertMatchesRegularExpression('/<div class="form-builder-errors[^"]*" id="form-errors-contact-form" tabindex="-1">/', $html);
        $this->assertStringContainsString('There are 3 problems with your input:', $html);
        $this->assertMatchesRegularExpression('/href="#fb-contact-form-full_name">The Name field is required\.<\/a>.*href="#fb-contact-form-topic-0">.*href="#fb-contact-form-interests-0">/s', $html);

        // Focus moves to the summary after the redirect
        $this->assertMatchesRegularExpression('/getElementById\("form-errors-contact-form"\)/', $html);

        // Inline errors are no extra live regions
        $this->assertStringNotContainsString('role="alert"', $html);
    }

    public function test_summary_names_the_field_when_a_custom_message_does_not(): void
    {
        $form = $this->makeForm();
        $this->flashErrors(['email' => ['Please check this address.']]);

        $this->assertStringContainsString('href="#fb-contact-form-email">Email: Please check this address.</a>', FormRenderer::render($form));
    }

    public function test_required_note_is_shown_only_for_forms_with_required_fields(): void
    {
        $html = FormRenderer::render($this->makeForm());
        $this->assertStringContainsString('Fields marked with * are required.', $html);
        // The asterisk is decoration; the input carries "required"
        $this->assertStringContainsString('<span class="text-red-600" aria-hidden="true">*</span>', $html);
        $this->assertStringNotContainsString('<span class="text-red-500">*</span>', $html);
        $this->assertStringContainsString('required', $this->tag($html, 'input', 'full_name'));

        $optional = $this->makeForm(['slug' => 'optional', 'fields' => [
            ['id' => 'x', 'type' => 'text', 'name' => 'note', 'label' => 'Note'],
            ['id' => 'y', 'type' => 'hidden', 'name' => 'source', 'required' => true],
        ]]);
        $this->assertStringNotContainsString('Fields marked with * are required.', FormRenderer::render($optional));
    }

    public function test_required_note_is_translated(): void
    {
        $translated = trans('form-builder::forms.required_note', [], 'de');
        $this->assertNotSame('Fields marked with * are required.', $translated);

        app()->setLocale('de');

        $this->assertStringContainsString(e($translated), FormRenderer::render($this->makeForm()));
    }

    public function test_form_has_an_accessible_name(): void
    {
        $this->assertStringContainsString('aria-label="Contact Form"', FormRenderer::render($this->makeForm()));

        $named = $this->makeForm(['slug' => 'named', 'settings' => ['accessible_name' => 'Write to us']]);
        $this->assertStringContainsString('aria-label="Write to us"', FormRenderer::render($named));
    }

    public function test_honeypot_is_hidden_from_assistive_technology(): void
    {
        $html = FormRenderer::render($this->makeForm());

        $this->assertMatchesRegularExpression('/<div style="display:none" aria-hidden="true">\s*<input type="text" name="_honeypot" value="" tabindex="-1" autocomplete="off" \/>/', $html);
    }

    public function test_success_message_receives_focus(): void
    {
        $form = $this->makeForm();
        session()->put('form_success_contact-form', 'Thanks!');
        session()->put('form_builder_instance', 'contact-form');

        $html = FormRenderer::render($form);

        $this->assertMatchesRegularExpression('/role="status" id="form-success-contact-form" tabindex="-1"/', $html);
        $this->assertStringContainsString('getElementById("form-success-contact-form")', $html);

        // The second copy of the same form keeps its fields
        $second = FormRenderer::render($form);
        $this->assertStringNotContainsString('Thanks!', $second);
        $this->assertStringContainsString('name="full_name"', $second);
    }

    public function test_old_forms_without_new_options_still_render(): void
    {
        $form = $this->makeForm(['fields' => [
            ['type' => 'text', 'name' => 'legacy', 'label' => 'Legacy'],
            ['type' => 'row', 'children' => [
                ['type' => 'column', 'children' => [['type' => 'select', 'name' => 'pick', 'label' => 'Pick', 'options' => [['label' => 'A', 'value' => 'a']]]]],
                ['type' => 'column', 'children' => [['type' => 'checkbox', 'name' => 'agree', 'label' => 'Agree']]],
            ]],
        ]]);

        $html = FormRenderer::render($form);

        $this->assertStringContainsString('id="fb-contact-form-legacy"', $html);
        $this->assertStringContainsString('for="fb-contact-form-pick"', $html);
        $this->assertStringContainsString('<option value="">Please select</option>', $html);
        $this->assertStringContainsString('id="fb-contact-form-agree"', $html);
    }

    public function test_submit_uses_labels_and_custom_error_messages(): void
    {
        $form = $this->makeForm(['fields' => [
            ['id' => 'f1', 'type' => 'text', 'name' => 'full_name', 'label' => 'Your name', 'required' => true, 'error_message' => 'Please tell us your name.'],
            ['id' => 'f2', 'type' => 'email', 'name' => 'email', 'label' => 'Email address', 'required' => true],
        ]]);

        $this->post(route('forms.submit', $form->slug), [
            '_fb_instance' => 'contact-form',
            'full_name' => '',
            'email' => 'not-an-email',
        ])->assertSessionHasErrors([
            'full_name' => 'Please tell us your name.',
        ]);

        $this->assertStringContainsString('Email address', session('errors')->first('email'));
        $this->assertSame('contact-form', session()->getOldInput('_fb_instance'));
    }

    public function test_a_field_named_like_a_rule_does_not_leak_its_message(): void
    {
        // Message keys are "<field>.<rule>" — a field called "email" must not
        // hijack the email rule of another field
        $form = $this->makeForm(['fields' => [
            ['id' => 'f1', 'type' => 'text', 'name' => 'email', 'label' => 'Code', 'required' => true, 'error_message' => 'Custom code message'],
            ['id' => 'f2', 'type' => 'email', 'name' => 'contact', 'label' => 'Contact', 'required' => true],
        ]]);

        $this->post(route('forms.submit', $form->slug), ['email' => '', 'contact' => 'nope'])
            ->assertSessionHasErrors(['email' => 'Custom code message']);

        $this->assertNotSame('Custom code message', session('errors')->first('contact'));
    }

    public function test_success_redirect_remembers_the_sent_copy(): void
    {
        $form = $this->makeForm(['fields' => [
            ['id' => 'f1', 'type' => 'text', 'name' => 'full_name', 'label' => 'Name'],
        ]]);

        $this->post(route('forms.submit', $form->slug), ['_fb_instance' => 'contact-form-2', 'full_name' => 'Ada'])
            ->assertSessionHas('form_builder_instance', 'contact-form-2');

        // The first copy on the page shows its fields, the second the message
        $this->assertStringContainsString('name="full_name"', FormRenderer::render($form));
        $this->assertStringContainsString('id="form-success-contact-form-2"', FormRenderer::render($form));
    }

    public function test_admin_can_set_the_accessible_form_name(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)->post(route('backend.forms.store'), [
            'name' => 'Contact Form',
            'slug' => 'contact-form',
            'fields' => json_encode([['id' => 'f1', 'type' => 'text', 'name' => 'full_name', 'label' => 'Name', 'autocomplete' => 'name', 'error_message' => 'Name please']]),
            'is_active' => true,
            'settings_accessible_name' => 'Write to us',
        ])->assertRedirect();

        $form = Form::where('slug', 'contact-form')->firstOrFail();
        $this->assertSame('Write to us', $form->settings['accessible_name']);
        // New field options are stored as they come from the editor
        $this->assertSame('name', $form->fields[0]['autocomplete']);
        $this->assertSame('Name please', $form->fields[0]['error_message']);

        $this->actingAs($admin)->get(route('backend.forms.edit', $form))
            ->assertOk()
            ->assertSee('settings_accessible_name', false)
            ->assertSee('AUTOCOMPLETE_TOKENS', false);
    }
}
