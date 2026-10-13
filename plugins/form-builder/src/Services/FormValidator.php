<?php

namespace Plugins\FormBuilder\Services;

use Illuminate\Validation\Rule;
use Plugins\FormBuilder\Models\Form;

class FormValidator
{
    public const SAFE_FILE_TYPES = 'pdf,jpg,jpeg,png,gif,webp,doc,docx,xls,xlsx,ppt,pptx,odt,ods,odp,csv,txt,rtf,zip,rar,7z,mp3,mp4,mov,avi,webm';

    public const BLOCKED_EXTENSIONS = ['exe', 'bat', 'cmd', 'sh', 'bash', 'php', 'phtml', 'phar', 'pl', 'py', 'rb', 'js', 'mjs', 'cjs', 'jsp', 'asp', 'aspx', 'cgi', 'com', 'scr', 'msi', 'dll', 'sys', 'vbs', 'vbe', 'wsf', 'wsh', 'ps1', 'psm1', 'reg', 'inf', 'hta', 'jar', 'war', 'svg', 'html', 'htm', 'xhtml', 'xml'];

    /**
     * Validation rules allowed in custom field validation.
     * Prevents form creators from injecting dangerous rules like exists/unique (DB probing).
     */
    public const ALLOWED_CUSTOM_RULES = [
        'min', 'max', 'size', 'between', 'digits', 'digits_between',
        'alpha', 'alpha_num', 'alpha_dash', 'numeric', 'integer',
        'starts_with', 'ends_with', 'in', 'not_in',
        'confirmed', 'same', 'different', 'gt', 'gte', 'lt', 'lte',
        'after', 'after_or_equal', 'before', 'before_or_equal',
        'date_format', 'timezone', 'ip', 'ipv4', 'ipv6', 'json', 'uuid',
    ];

    /**
     * Build Laravel validation rules from form field definitions.
     */
    public static function buildRules(Form $form): array
    {
        $rules = [];

        foreach ($form->getFlatFields() as $field) {
            $fieldName = $field['name'] ?? '';
            if (empty($fieldName)) {
                continue;
            }

            // Skip hidden fields from validation unless they have explicit rules
            $type = $field['type'] ?? 'text';

            $fieldRules = [];

            // Required or nullable
            if (! empty($field['required'])) {
                $fieldRules[] = 'required';
            } else {
                $fieldRules[] = 'nullable';
            }

            // Skip non-input field types and plugin-owned fields (name starts with _).
            // Never file fields — otherwise "_upload" would skip the type/size check.
            if ($type === 'static-text' || (str_starts_with($fieldName, '_') && $type !== 'file')) {
                continue;
            }

            // Type-specific rules
            switch ($type) {
                case 'email':
                    $fieldRules[] = 'email';
                    break;
                case 'number':
                    $fieldRules[] = 'numeric';
                    break;
                case 'url':
                    $fieldRules[] = 'url';
                    break;
                case 'file':
                    $fieldRules[] = 'file';
                    $maxSize = ! empty($field['max_file_size']) ? (int) $field['max_file_size'] : 10240;
                    $fieldRules[] = 'max:'.$maxSize;
                    if (! empty($field['allowed_file_types'])) {
                        $types = explode(',', $field['allowed_file_types']);
                    } else {
                        $types = explode(',', self::SAFE_FILE_TYPES);
                    }
                    // Normalise to lowercase so blocklist matching is case-insensitive
                    $types = array_filter(array_map(fn ($t) => strtolower(trim($t)), $types));
                    // Always strip blocked extensions
                    $types = array_diff($types, self::BLOCKED_EXTENSIONS);
                    // Fail closed: if the field only listed blocked types, fall back to the
                    // safe set instead of emitting no mimes rule (which would accept anything).
                    if (empty($types)) {
                        $types = array_diff(
                            array_map('trim', explode(',', self::SAFE_FILE_TYPES)),
                            self::BLOCKED_EXTENSIONS
                        );
                    }
                    $fieldRules[] = 'mimes:'.implode(',', $types);
                    break;
                case 'tel':
                    $fieldRules[] = 'string';
                    break;
                case 'multi-select':
                case 'checkbox-group':
                    $fieldRules[] = 'array';
                    // Validate each element so a nested array (which would crash on storage
                    // with "Array to string conversion") is rejected, and values are limited
                    // to the field's defined options when available.
                    $optionValues = array_values(array_filter(array_map(
                        fn ($o) => is_array($o) ? ($o['value'] ?? null) : $o,
                        $field['options'] ?? []
                    ), fn ($v) => $v !== null && $v !== ''));
                    $rules[$fieldName.'.*'] = $optionValues
                        ? ['string', Rule::in($optionValues)]
                        : ['string'];
                    break;
                case 'checkbox':
                    // Single checkbox is boolean-like
                    break;
                case 'date':
                    $fieldRules[] = 'date';
                    break;
                case 'time':
                    $fieldRules[] = 'string';
                    break;
                default:
                    $fieldRules[] = 'string';
                    break;
            }

            // Append custom validation if provided (only whitelisted rules)
            if (! empty($field['validation'])) {
                $customRules = array_map('trim', explode('|', $field['validation']));
                $customRules = array_filter($customRules, function ($rule) {
                    $ruleName = explode(':', $rule, 2)[0];

                    return in_array($ruleName, self::ALLOWED_CUSTOM_RULES, true);
                });
                $fieldRules = array_merge($fieldRules, $customRules);
            }

            $rules[$fieldName] = $fieldRules;
        }

        return $rules;
    }

    /**
     * The editor's own error text per field ("error_message"), for every rule
     * of that field. Keys are always "<field>.<rule>": a bare field name as a
     * key would also match any rule of the same name on other fields
     * (a field called "email" vs. the email rule).
     *
     * @param  array<string, array<int, mixed>>  $rules  result of buildRules()
     * @return array<string, string>
     */
    public static function buildMessages(Form $form, array $rules): array
    {
        $messages = [];

        foreach ($form->getFlatFields() as $field) {
            $fieldName = (string) ($field['name'] ?? '');
            $message = trim((string) ($field['error_message'] ?? ''));
            if ($fieldName === '' || $message === '' || ! isset($rules[$fieldName])) {
                continue;
            }

            foreach (array_merge($rules[$fieldName], $rules[$fieldName.'.*'] ?? []) as $rule) {
                if (! is_string($rule)) {
                    // Rule objects (Rule::in for options) — their message is the generic one
                    $messages[$fieldName.'.*.in'] = $message;

                    continue;
                }

                $ruleName = strtolower(explode(':', $rule, 2)[0]);
                if ($ruleName === 'nullable') {
                    continue;
                }

                $messages[$fieldName.'.'.$ruleName] = $message;
                $messages[$fieldName.'.*.'.$ruleName] = $message;
            }
        }

        return $messages;
    }

    /**
     * Field labels as attribute names, so messages say "The Email address
     * field is required." instead of the technical field name.
     *
     * @return array<string, string>
     */
    public static function buildAttributes(Form $form): array
    {
        $attributes = [];

        foreach ($form->getFlatFields() as $field) {
            $fieldName = (string) ($field['name'] ?? '');
            $label = trim(strip_tags((string) ($field['label'] ?? '')));
            if ($fieldName === '' || $label === '') {
                continue;
            }

            $attributes[$fieldName] = $label;
            $attributes[$fieldName.'.*'] = $label;
        }

        return $attributes;
    }
}
