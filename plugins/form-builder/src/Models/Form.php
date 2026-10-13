<?php

namespace Plugins\FormBuilder\Models;

use App\Models\Concerns\HasTranslations;
use App\Support\Locales;
use App\Traits\HasUniqueSlug;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Form extends Model
{
    use HasTranslations {
        getAttribute as translatedAttribute;
    }
    use HasUniqueSlug;
    use SoftDeletes;

    /**
     * Multilingual: only the TEXTS are translated, not the field list.
     * The structure (types, names, required fields) applies to all languages —
     * otherwise every translation would drift apart with the next new field.
     *
     *   field_texts: { "<field ID>": { label, placeholder, help_text, content, options: { "<value>": "<text>" } } }
     *
     * `field_texts`, `submit_label` and `success_message` are not columns,
     * they only live in the translation store.
     */
    protected array $translatable = ['field_texts', 'submit_label', 'success_message'];

    /** Texts per field that a translation may replace */
    public const TRANSLATABLE_FIELD_KEYS = ['label', 'placeholder', 'help_text', 'content'];

    public function getAttribute($key)
    {
        $value = $this->translatedAttribute($key);

        // Frontend in a translation: lay the texts over the original structure
        if (($key === 'fields' || $key === 'settings') && is_array($value) && app(Locales::class)->content() !== null) {
            return $key === 'fields'
                ? $this->applyFieldTexts($value, $this->fieldTexts())
                : $this->applySettingTexts($value);
        }

        return $value;
    }

    /** @return array<string, array<string, mixed>> */
    public function fieldTexts(): array
    {
        $texts = $this->translatedAttribute('field_texts');

        if (is_string($texts)) {
            $texts = json_decode($texts, true);
        }

        return is_array($texts) ? $texts : [];
    }

    /** Key of a field for its texts: the permanent ID, otherwise the name */
    public static function textKey(array $field): ?string
    {
        $key = $field['id'] ?? $field['name'] ?? null;

        return $key !== null ? (string) $key : null;
    }

    private function applyFieldTexts(array $items, array $texts): array
    {
        foreach ($items as $i => $item) {
            if (! empty($item['children']) && is_array($item['children'])) {
                $items[$i]['children'] = $this->applyFieldTexts($item['children'], $texts);
            }

            $key = static::textKey($item);
            if ($key === null || empty($texts[$key])) {
                continue;
            }

            foreach (self::TRANSLATABLE_FIELD_KEYS as $textKey) {
                if (filled($texts[$key][$textKey] ?? null)) {
                    $items[$i][$textKey] = $texts[$key][$textKey];
                }
            }

            foreach ($item['options'] ?? [] as $o => $option) {
                $translated = $texts[$key]['options'][(string) ($option['value'] ?? '')] ?? null;
                if (filled($translated)) {
                    $items[$i]['options'][$o]['label'] = $translated;
                }
            }
        }

        return $items;
    }

    private function applySettingTexts(array $settings): array
    {
        foreach (['submit_label', 'success_message'] as $key) {
            $translated = $this->translatedAttribute($key);
            if (filled($translated)) {
                $settings[$key] = $translated;
            }
        }

        return $settings;
    }

    public function getSlugSource(): string
    {
        return 'name';
    }

    /**
     * On permanent deletion, remove the whole upload directory for this form.
     * (Soft delete keeps it so a restored form still has its submission files.)
     */
    protected static function booted(): void
    {
        static::forceDeleted(function (Form $form) {
            if ($form->slug) {
                Storage::deleteDirectory('form-submissions/'.$form->slug);
            }
        });
    }

    protected $table = 'forms';

    protected $fillable = [
        'name', 'slug', 'description', 'fields', 'settings',
        'is_active', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'fields' => 'array',
        'settings' => 'array',
        'is_active' => 'boolean',
    ];

    public function submissions()
    {
        return $this->hasMany(FormSubmission::class);
    }

    public function creator()
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'updated_by');
    }

    /**
     * Get all input fields flattened (recursing into row/column containers).
     */
    public function getFlatFields(): array
    {
        return static::flattenFields($this->fields ?? []);
    }

    /**
     * Fields whose values a submission carries — without display elements
     * (static text) and internal fields such as the captcha ("_captcha").
     * For the submission list, detail view, export and notification.
     */
    public function getDataFields(): array
    {
        return array_values(array_filter($this->getFlatFields(), function (array $field) {
            $name = $field['name'] ?? '';
            $type = $field['type'] ?? '';

            // Same rule as FormValidator: "_" fields are internal, except files
            return $name !== '' && $type !== 'static-text' && ! (str_starts_with($name, '_') && $type !== 'file');
        }));
    }

    /**
     * Recursively extract input fields from a nested structure.
     */
    public static function flattenFields(array $items): array
    {
        $result = [];
        foreach ($items as $item) {
            $type = $item['type'] ?? '';
            if ($type === 'row' || $type === 'column') {
                $result = array_merge($result, static::flattenFields($item['children'] ?? []));
            } else {
                $result[] = $item;
            }
        }

        return $result;
    }

    /**
     * Find a field definition by its name attribute.
     */
    public function getFieldByName(string $name): ?array
    {
        foreach ($this->getFlatFields() as $field) {
            if (($field['name'] ?? '') === $name) {
                return $field;
            }
        }

        return null;
    }
}
