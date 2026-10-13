<?php

namespace Plugins\FormBuilder\Http\Controllers;

use App\Support\Locales;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Plugins\FormBuilder\Mail\FormSubmissionNotification;
use Plugins\FormBuilder\Models\Form;
use Plugins\FormBuilder\Models\FormSubmission;
use Plugins\FormBuilder\Services\FormValidator;

class FormSubmitController extends Controller
{
    public function submit(Request $request, string $slug)
    {
        $form = Form::where('slug', $slug)->where('is_active', true)->firstOrFail();

        // Which copy of the form on the page was sent (two copies of one form
        // may share a page) — the success message is shown only there
        $instance = substr((string) $request->input('_fb_instance', ''), 0, 255);

        // Rate limiting
        $rateLimit = (int) cms()->settings()->get('form_builder.rate_limit', 5);
        $rateLimitKey = 'form-submit:'.$request->ip().':'.$form->id;

        if (RateLimiter::tooManyAttempts($rateLimitKey, $rateLimit)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => __('form-builder::forms.rate_limited')], 429);
            }

            // Keep the input — nobody should have to type everything again
            return redirect()->back()->withInput()->with('form_error_'.$form->slug, __('form-builder::forms.rate_limited'));
        }

        RateLimiter::hit($rateLimitKey, 60);

        // Honeypot check — silently accept but don't store
        if ($request->filled('_honeypot')) {
            if ($request->expectsJson()) {
                return response()->json(['message' => __('form-builder::forms.submission_success')]);
            }
            $successMessage = ! empty($form->settings['success_message']) ? $form->settings['success_message'] : __('form-builder::forms.submission_success');

            return redirect()->back()
                ->with('form_success_'.$form->slug, $successMessage)
                ->with('form_builder_instance', $instance);
        }

        // Pre-submission validation hook — plugins (e.g. captcha) can listen
        // and return an error message string to block submission.
        $preErrors = array_filter(event('form-builder.validating', [$form, $request]));
        if (! empty($preErrors)) {
            $errorMessage = reset($preErrors);
            if ($request->expectsJson()) {
                return response()->json(['message' => $errorMessage], 422);
            }

            return redirect()->back()->withInput()
                ->with('form_error_'.$form->slug, $errorMessage);
        }

        // Build and apply validation rules
        // Messages name the field by its label and use the editor's own error text
        $rules = FormValidator::buildRules($form);
        $validated = $request->validate(
            $rules,
            FormValidator::buildMessages($form, $rules),
            FormValidator::buildAttributes($form),
        );

        // Handle file uploads
        $files = [];
        $submissionData = [];

        foreach ($form->getFlatFields() as $field) {
            $fieldName = $field['name'] ?? '';
            if (empty($fieldName)) {
                continue;
            }

            if (($field['type'] ?? '') === 'file' && $request->hasFile($fieldName)) {
                $uploadedFile = $request->file($fieldName);
                $path = $uploadedFile->store('form-submissions/'.$form->slug);
                $files[$fieldName] = $path;
                $submissionData[$fieldName] = $uploadedFile->getClientOriginalName();
            } elseif (isset($validated[$fieldName])) {
                $value = $validated[$fieldName];
                // Handle arrays (multi-select, checkbox-group)
                $submissionData[$fieldName] = is_array($value) ? implode(', ', $value) : $value;
            }
        }

        // Store submission
        $submission = FormSubmission::create([
            'form_id' => $form->id,
            'data' => $submissionData,
            'files' => ! empty($files) ? $files : null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        // Send email notification
        $settings = $form->settings ?? [];
        if (! empty($settings['send_email'])) {
            // Per-form recipients, then global default
            $recipients = ! empty($settings['recipients'])
                ? $settings['recipients']
                : cms()->settings()->get('form_builder.default_recipients', '');

            if (! empty($recipients)) {
                $recipientList = array_map('trim', explode(',', $recipients));
                $recipientList = array_filter($recipientList, fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL));

                if (! empty($recipientList)) {
                    try {
                        // The notification goes to the site operators — in the website's
                        // default language, not the visitor's language
                        Mail::to($recipientList)->locale(app(Locales::class)->default())->send(new FormSubmissionNotification($form, $submission));
                    } catch (\Exception $e) {
                        // Log but don't fail the submission
                        \Log::error('Form submission notification failed: '.$e->getMessage());
                    }
                }
            }
        }

        // Send copy to sender if enabled and an email field exists
        if (! empty($settings['send_copy_to_sender'])) {
            $senderEmail = null;
            foreach ($form->getFlatFields() as $field) {
                if (($field['type'] ?? '') === 'email' && ! empty($submissionData[$field['name'] ?? ''])) {
                    $senderEmail = $submissionData[$field['name']];
                    break;
                }
            }
            // At most 3 confirmations per address and hour — no mail flood to someone else's address
            $limitKey = 'form-copy:'.sha1(strtolower((string) $senderEmail));
            if ($senderEmail && filter_var($senderEmail, FILTER_VALIDATE_EMAIL) && RateLimiter::attempt($limitKey, 3, fn () => true, 3600)) {
                try {
                    Mail::to($senderEmail)->send(new FormSubmissionNotification($form, $submission, true));
                } catch (\Exception $e) {
                    \Log::error('Form sender copy failed: '.$e->getMessage());
                }
            }
        }

        // Response
        $successMessage = ! empty($settings['success_message']) ? $settings['success_message'] : __('form-builder::forms.submission_success');

        if ($request->expectsJson()) {
            return response()->json(['message' => $successMessage]);
        }

        return redirect()->back()
            ->with('form_success_'.$form->slug, $successMessage)
            ->with('form_builder_instance', $instance);
    }
}
