<?php

namespace Plugins\FormBuilder\Http\Controllers\Admin;

use App\Helpers\SearchHelper;
use App\Support\EditLocale;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Plugins\FormBuilder\Models\Form;

#[Middleware('permission:show-form', only: ['index'])]
#[Middleware('permission:create-form', only: ['create', 'store', 'storeDraft'])]
#[Middleware('permission:edit-form', only: ['edit', 'update', 'saveFields'])]
#[Middleware('permission:delete-form', only: ['destroy'])]
class FormController extends Controller
{
    public function index(Request $request): View
    {
        $forms = Form::withCount('submissions')
            ->when($request->search, function ($query, $search) {
                $search = SearchHelper::escapeLike($search);
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return view('form-builder-admin::index', [
            'forms' => $forms,
        ]);
    }

    public function create(): View
    {
        return view('form-builder-admin::create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->validationRules());

        $attributes = [
            'name' => $validated['name'],
            'slug' => ($validated['slug'] ?? '') ?: Str::slug($validated['name']),
            'description' => $validated['description'] ?? null,
            'fields' => json_decode($validated['fields'], true),
            'is_active' => $request->boolean('is_active'),
            'settings' => $this->buildSettings($request, $validated),
            'updated_by' => auth()->id(),
        ];

        // If the builder already saved a draft while creating (storeDraft),
        // it becomes the new form — otherwise it would be left orphaned
        if ($form = $this->pendingDraft($request)) {
            $form->update($attributes);
            $request->session()->put('form_drafts', array_values(array_diff($request->session()->get('form_drafts', []), [$form->id])));
        } else {
            $form = Form::create($attributes + ['created_by' => auth()->id()]);
        }

        return redirect()
            ->route('backend.forms.edit', $form)
            ->with('success', __('form-builder::forms.form_created'));
    }

    /**
     * Draft the builder created in this session while creating the form.
     */
    protected function pendingDraft(Request $request): ?Form
    {
        $id = (int) $request->input('draft_id');

        if (! $id || ! in_array($id, $request->session()->get('form_drafts', []), true)) {
            return null;
        }

        return Form::whereKey($id)->where('is_active', false)->where('slug', 'like', 'draft-%')->first();
    }

    public function edit(Form $form): View
    {
        return view('form-builder-admin::edit', [
            'form' => $form,
        ]);
    }

    public function update(Request $request, Form $form)
    {
        // Translation of the form texts (Multilingual plugin)
        if ($locale = EditLocale::current($request)) {
            $data = $request->validate([
                'texts' => 'nullable|array',
                'submit_label' => 'nullable|string|max:255',
                'success_message' => 'nullable|string|max:1000',
                'accessible_name' => 'nullable|string|max:255',
            ]);

            $form->saveTranslations($locale, [
                'field_texts' => $this->cleanTexts($data['texts'] ?? []),
                'submit_label' => $data['submit_label'] ?? null,
                'success_message' => $data['success_message'] ?? null,
                'accessible_name' => $data['accessible_name'] ?? null,
            ]);

            return redirect()
                ->route('backend.forms.edit', ['form' => $form, 'lang' => $locale])
                ->with('success', __('form-builder::forms.form_updated'));
        }

        $validated = $request->validate($this->validationRules($form->id));

        $form->update([
            'name' => $validated['name'],
            'slug' => ($validated['slug'] ?? '') ?: Str::slug($validated['name']),
            'description' => $validated['description'] ?? null,
            'fields' => json_decode($validated['fields'], true),
            'is_active' => $request->boolean('is_active'),
            'settings' => $this->buildSettings($request, $validated),
            'updated_by' => auth()->id(),
        ]);

        return redirect()
            ->route('backend.forms.edit', $form)
            ->with('success', __('form-builder::forms.form_updated'));
    }

    /**
     * Remove empty input — an empty translation should not be stored as
     * "intentionally empty" but fall back to the original.
     */
    protected function cleanTexts(array $texts): ?array
    {
        $clean = [];

        foreach ($texts as $key => $values) {
            if (! is_array($values)) {
                continue;
            }

            foreach (Form::TRANSLATABLE_FIELD_KEYS as $textKey) {
                if (filled($values[$textKey] ?? null)) {
                    $clean[$key][$textKey] = (string) $values[$textKey];
                }
            }

            foreach (($values['options'] ?? []) as $value => $label) {
                if (filled($label)) {
                    $clean[$key]['options'][(string) $value] = (string) $label;
                }
            }
        }

        return $clean ?: null;
    }

    public function storeDraft(Request $request)
    {
        $request->validate([
            'fields' => 'required|json',
        ]);

        $form = Form::create([
            'name' => __('form-builder::forms.form').' '.now()->format('Y-m-d H:i'),
            'slug' => 'draft-'.Str::lower(Str::random(8)),
            'fields' => json_decode($request->input('fields'), true),
            'is_active' => false,
            'settings' => [],
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        // store() may later only take over drafts from this session
        $request->session()->push('form_drafts', $form->id);

        return response()->json([
            'ok' => true,
            'form_id' => $form->id,
            'save_url' => route('backend.forms.save-fields', $form),
            'edit_url' => route('backend.forms.edit', $form),
        ]);
    }

    public function saveFields(Request $request, Form $form)
    {
        $validated = $request->validate([
            'fields' => 'required|json',
        ]);

        $form->update([
            'fields' => json_decode($validated['fields'], true),
            'updated_by' => auth()->id(),
        ]);

        return response()->json(['ok' => true]);
    }

    public function destroy(Form $form)
    {
        $form->delete();

        return redirect()
            ->route('backend.forms.index')
            ->with('success', __('form-builder::forms.form_deleted'));
    }

    private function validationRules(?int $ignoreId = null): array
    {
        return [
            'name' => 'required|string|max:255',
            'slug' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('forms')->ignore($ignoreId)],
            'description' => 'nullable|string',
            'fields' => 'required|json',
            'is_active' => 'boolean',
            'settings_recipients' => ['nullable', 'string', 'max:500', function ($attribute, $value, $fail) {
                if ($value) {
                    foreach (array_filter(array_map('trim', explode(',', $value))) as $email) {
                        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                            $fail(__('form-builder::forms.invalid_recipient', ['email' => $email]));
                        }
                    }
                }
            }],
            'settings_reply_to' => 'nullable|email|max:255',
            'settings_from_name' => 'nullable|string|max:255',
            'settings_from_email' => 'nullable|email|max:255',
            'settings_email_subject' => 'nullable|string|max:255',
            'settings_success_message' => 'nullable|string|max:500',
            'settings_submit_label' => 'nullable|string|max:255',
            'settings_accessible_name' => 'nullable|string|max:255',
            'settings_honeypot' => 'boolean',
            'settings_send_email' => 'boolean',
            'settings_send_copy_to_sender' => 'boolean',
            'settings_copy_message' => 'nullable|string|max:1000',
            'settings_show_confirmation' => 'boolean',
        ];
    }

    private function buildSettings(Request $request, array $validated): array
    {
        return [
            'recipients' => $validated['settings_recipients'] ?? '',
            'reply_to' => $validated['settings_reply_to'] ?? '',
            'from_name' => $validated['settings_from_name'] ?? '',
            'from_email' => $validated['settings_from_email'] ?? '',
            'email_subject' => $validated['settings_email_subject'] ?? '',
            'success_message' => $validated['settings_success_message'] ?? '',
            'submit_label' => $validated['settings_submit_label'] ?? '',
            'accessible_name' => $validated['settings_accessible_name'] ?? '',
            'honeypot' => $request->boolean('settings_honeypot'),
            'send_email' => $request->boolean('settings_send_email'),
            'send_copy_to_sender' => $request->boolean('settings_send_copy_to_sender'),
            'copy_message' => $validated['settings_copy_message'] ?? '',
            'show_confirmation' => $request->boolean('settings_show_confirmation'),
        ];
    }
}
