<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Registry of settings whose text visitors see and which can therefore be
 * maintained per language — the site name, the cookie banner text,
 * the announcement bar message …
 *
 * Core and plugins register them (ExtensionRegistrar::registerTranslatableSetting),
 * the multilingual plugin shows them on a page for translating.
 * In the frontend, the settings store then returns the value in the visitor's language.
 *
 * Types:
 *   text, textarea   a single value
 *   list             JSON list of entries (e.g. cookie services); the fields under
 *                    "fields" are translated, matched via "item_key"
 */
final class TranslatableSettings
{
    /** @var array<string, array{label: string, group: string, type: string, fields: array<string, string>, item_key: string, item_label: string}> */
    private array $settings = [];

    /**
     * Labels (label, group, fields) are translation keys or English text.
     *
     * @param  array{label?: string, group?: string, type?: string, fields?: array<string, string>, item_key?: string, item_label?: string}  $options
     */
    public function register(string $key, array $options = []): void
    {
        $this->settings[$key] = [
            'label' => $options['label'] ?? $key,
            'group' => $options['group'] ?? 'General',
            'type' => $options['type'] ?? 'text',
            'fields' => $options['fields'] ?? [],
            'item_key' => $options['item_key'] ?? 'id',
            'item_label' => $options['item_label'] ?? 'name',
        ];
    }

    public function has(string $key): bool
    {
        return isset($this->settings[$key]);
    }

    /** @return array<string, array{label: string, group: string, type: string, fields: array<string, string>, item_key: string, item_label: string}> */
    public function all(): array
    {
        return $this->settings;
    }

    /**
     * Value for the frontend: in the visitor's language, otherwise the original.
     * Lists are overlaid entry by entry — anything missing stays original.
     */
    public function apply(string $key, mixed $value): mixed
    {
        if (! isset($this->settings[$key]) || ! SettingTranslations::translating()) {
            return $value;
        }

        $translated = SettingTranslations::lookup($key);
        if ($translated === null) {
            return $value;
        }

        if ($this->settings[$key]['type'] !== 'list') {
            return $translated;
        }

        return self::mergeList($this->settings[$key], $value, $translated);
    }

    /**
     * Overlay a list's translated texts onto the original entries.
     * Translation: {"<item_key>": {"<field>": "…"}}.
     */
    public static function mergeList(array $definition, mixed $original, string $translated): mixed
    {
        $items = is_string($original) ? json_decode($original, true) : $original;
        $texts = json_decode($translated, true);

        if (! is_array($items) || ! is_array($texts)) {
            return $original;
        }

        foreach ($items as $i => $item) {
            $id = (string) ($item[$definition['item_key']] ?? $i);

            foreach (array_keys($definition['fields']) as $field) {
                if (filled($texts[$id][$field] ?? null)) {
                    $items[$i][$field] = $texts[$id][$field];
                }
            }
        }

        return is_string($original) ? json_encode($items) : $items;
    }
}
