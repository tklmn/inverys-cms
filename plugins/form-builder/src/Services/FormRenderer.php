<?php

namespace Plugins\FormBuilder\Services;

use Illuminate\Support\Facades\View;
use Illuminate\Support\MessageBag;
use Plugins\FormBuilder\Models\Form;

class FormRenderer
{
    /**
     * Autocomplete tokens an editor may pick per field (WCAG 1.3.5, identify
     * input purpose). Keys are the HTML tokens, the editor shows a translated
     * label for each ("autocomplete_<token>").
     */
    public const AUTOCOMPLETE_TOKENS = [
        'name', 'honorific-prefix', 'given-name', 'additional-name', 'family-name', 'nickname',
        'email', 'tel', 'organization', 'organization-title',
        'street-address', 'address-line1', 'address-line2', 'postal-code',
        'address-level2', 'address-level1', 'country-name',
        'bday', 'sex', 'url', 'language', 'off',
    ];

    /** Field types that accept an autocomplete attribute */
    public const AUTOCOMPLETE_TYPES = ['text', 'email', 'tel', 'url', 'number', 'date', 'textarea', 'select'];

    /** Used when the editor left the choice on "automatic" */
    public const AUTOCOMPLETE_DEFAULTS = ['email' => 'email', 'tel' => 'tel', 'url' => 'url'];

    /** Field types without a value of their own */
    protected const DISPLAY_TYPES = ['static-text', 'hidden'];

    /**
     * Render a complete form HTML on the frontend.
     */
    public static function render(Form $form): string
    {
        $fields = $form->fields ?? [];
        $instance = static::nextInstance((string) $form->slug);

        $context = [
            'prefix' => 'fb-'.$instance,
            // Errors and old input belong to the copy of the form that was sent
            'active' => static::isActiveInstance($instance),
            'errors' => static::errorBag(),
        ];

        $fieldsHtml = static::renderItems($fields, $context);

        return View::make('form-builder::form', [
            'form' => $form,
            'fieldsHtml' => $fieldsHtml,
            'instance' => $instance,
            'isActive' => $context['active'],
            'errorSummary' => $context['active'] ? static::errorSummary($form, $context) : [],
            'hasRequired' => static::hasRequiredFields($form),
            'accessibleName' => static::accessibleName($form),
        ])->render();
    }

    /**
     * Key of this copy of the form on the page: the slug, and from the second
     * copy of the same form on one page the slug plus a counter — so ids
     * (label for, aria-describedby, error links) never collide.
     */
    protected static function nextInstance(string $slug): string
    {
        $slug = static::sanitizeId($slug) ?: 'form';
        $request = request();
        $counts = $request->attributes->get('form-builder.instances', []);
        $counts[$slug] = ($counts[$slug] ?? 0) + 1;
        $request->attributes->set('form-builder.instances', $counts);

        return $counts[$slug] === 1 ? $slug : $slug.'-'.$counts[$slug];
    }

    /**
     * Whether errors, old input and messages after a redirect belong to this
     * copy. Submissions from before the instance field existed (no
     * "_fb_instance") count for every copy, as before.
     */
    public static function isActiveInstance(string $instance): bool
    {
        $sent = session()->getOldInput('_fb_instance', session('form_builder_instance'));

        return $sent === null || $sent === '' || $sent === $instance;
    }

    protected static function errorBag(): MessageBag
    {
        $errors = session('errors');

        return $errors && method_exists($errors, 'getBag') ? $errors->getBag('default') : new MessageBag;
    }

    /**
     * Render a list of items (fields, rows, columns) recursively.
     */
    protected static function renderItems(array $items, array $context): string
    {
        $html = '';

        foreach ($items as $item) {
            $type = $item['type'] ?? 'text';

            if ($type === 'row') {
                $html .= static::renderRow($item, $context);
            } elseif ($type === 'column') {
                // Columns are rendered inside rows
                continue;
            } else {
                $width = $item['width'] ?? 'full';
                $widthClass = $width === 'half' ? 'md:col-span-1' : 'md:col-span-2';

                $html .= '<div class="'.$widthClass.'">';
                $html .= static::renderField($item, $context);
                $html .= '</div>';
            }
        }

        return $html;
    }

    /**
     * Render a row container with columns.
     */
    protected static function renderRow(array $row, array $context): string
    {
        $children = $row['children'] ?? [];
        $colCount = count($children);
        if ($colCount < 1) {
            return '';
        }

        $gridCols = [1 => '1', 2 => '2', 3 => '3', 4 => '4'];
        $gridClass = 'md:grid-cols-'.($gridCols[$colCount] ?? $colCount);

        $html = '<div class="md:col-span-2 grid grid-cols-1 '.$gridClass.' gap-4">';

        foreach ($children as $column) {
            $html .= '<div>';
            foreach ($column['children'] ?? [] as $field) {
                $html .= '<div class="mb-4 last:mb-0">';
                $html .= static::renderField($field, $context);
                $html .= '</div>';
            }
            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Render a single field.
     */
    protected static function renderField(array $field, array $context): string
    {
        $type = $field['type'] ?? 'text';

        $viewName = "form-builder::fields.{$type}";
        if (! View::exists($viewName)) {
            $viewName = 'form-builder::fields.text';
        }

        return View::make($viewName, [
            'field' => $field,
            'fb' => static::fieldContext($field, $context),
        ])->render();
    }

    /**
     * Everything a field view needs to be accessible: a page-unique id, the
     * ids of help and error text, one combined aria-describedby, the error
     * message and the autocomplete token.
     *
     * @return array{id: string, helpId: string, errorId: string, error: ?string, describedBy: string, autocomplete: ?string, useOld: bool}
     */
    public static function fieldContext(array $field, array $context = []): array
    {
        $prefix = $context['prefix'] ?? 'fb';
        $name = (string) ($field['name'] ?? '');
        $id = $prefix.'-'.(static::sanitizeId($name) ?: static::sanitizeId((string) ($field['id'] ?? '')) ?: 'field');

        $error = null;
        if (($context['active'] ?? true) && $name !== '' && isset($context['errors'])) {
            $error = static::firstError($context['errors'], $name);
        }

        $helpId = $id.'-help';
        $errorId = $id.'-error';
        $describedBy = array_filter([
            filled($field['help_text'] ?? null) ? $helpId : null,
            $error !== null ? $errorId : null,
        ]);

        return [
            'id' => $id,
            'helpId' => $helpId,
            'errorId' => $errorId,
            'error' => $error,
            'describedBy' => implode(' ', $describedBy),
            'autocomplete' => static::autocompleteFor($field),
            'useOld' => (bool) ($context['active'] ?? true),
        ];
    }

    /**
     * Autocomplete token for a field: the editor's choice if it is a known
     * token, otherwise the default for the field type (email, tel, url).
     */
    public static function autocompleteFor(array $field): ?string
    {
        $type = $field['type'] ?? 'text';
        if (! in_array($type, self::AUTOCOMPLETE_TYPES, true)) {
            return null;
        }

        $token = trim((string) ($field['autocomplete'] ?? ''));
        if ($token !== '' && in_array($token, self::AUTOCOMPLETE_TOKENS, true)) {
            return $token;
        }

        return self::AUTOCOMPLETE_DEFAULTS[$type] ?? null;
    }

    /** First message for a field, including errors of its array items (name.0 …) */
    protected static function firstError(MessageBag $errors, string $name): ?string
    {
        if ($errors->has($name)) {
            return $errors->first($name);
        }

        return $errors->has($name.'.*') ? $errors->first($name.'.*') : null;
    }

    /**
     * Errors of this form in field order, each with the id to link to.
     *
     * @return list<array{id: string, label: string, message: string}>
     */
    protected static function errorSummary(Form $form, array $context): array
    {
        $summary = [];

        foreach ($form->getFlatFields() as $field) {
            $name = (string) ($field['name'] ?? '');
            if ($name === '' || in_array($field['type'] ?? '', self::DISPLAY_TYPES, true)) {
                continue;
            }

            $message = static::firstError($context['errors'], $name);
            if ($message === null) {
                continue;
            }

            $fieldContext = static::fieldContext($field, $context);
            // Groups: the link targets the first option
            $target = in_array($field['type'] ?? '', ['radio', 'checkbox-group'], true)
                ? $fieldContext['id'].'-0'
                : $fieldContext['id'];

            $summary[] = [
                'id' => $target,
                'label' => (string) ($field['label'] ?? $name),
                'message' => $message,
            ];
        }

        return $summary;
    }

    /** Whether a visible field is required — then the form explains the asterisk */
    protected static function hasRequiredFields(Form $form): bool
    {
        foreach ($form->getFlatFields() as $field) {
            if (! empty($field['required']) && ! in_array($field['type'] ?? '', self::DISPLAY_TYPES, true)) {
                return true;
            }
        }

        return false;
    }

    /** Name of the form for assistive technology: the editor's text, otherwise the form name */
    protected static function accessibleName(Form $form): string
    {
        $name = trim((string) ($form->settings['accessible_name'] ?? ''));

        return $name !== '' ? $name : (string) $form->name;
    }

    protected static function sanitizeId(string $value): string
    {
        return trim((string) preg_replace('/[^A-Za-z0-9_-]+/', '-', $value), '-');
    }
}
