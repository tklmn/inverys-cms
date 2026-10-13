<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Revision;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Comparison of two states of a piece of content (revision against revision or
 * against the current state) and a summary for the revision list — for
 * every model with HasRevisions (pages, blog posts, plugin content).
 *
 * Revisions in the original language hold the model's fields; revisions of a
 * translation additionally hold "_locale" and only the translatable fields.
 */
final class RevisionDiff
{
    /** Field => translation key of the label (pages; other types bring their own) */
    private const LABELS = [
        'title' => 'translation.pages.field_title',
        'subtitle' => 'translation.pages.subtitle',
        'slug' => 'translation.pages.slug',
        'nav_title' => 'translation.pages.nav_title',
        'external_url' => 'translation.pages.external_url',
        'meta_title' => 'translation.pages.meta_title',
        'meta_description' => 'translation.pages.meta_description',
        'meta_author' => 'translation.pages.meta_author',
        'meta_robots' => 'translation.pages.meta_robots',
        'aria_label' => 'translation.pages.aria_label',
        'template' => 'translation.pages.template',
        'header_image' => 'translation.pages.header_image',
        'extra_css' => 'translation.pages.extra_css',
        'content' => 'translation.pages.content_area',
        'blocks' => 'translation.pages.content_area',
        'is_active' => 'translation.pages.is_active',
        'hide_in_menu' => 'translation.pages.hide_in_menu',
    ];

    /** @param  array<string, string>  $labels  labels of the content type (Revisionables) */
    public static function label(string $field, array $labels = []): string
    {
        $key = $labels[$field] ?? self::LABELS[$field] ?? null;

        return $key !== null ? (string) __($key) : Str::headline($field);
    }

    /** Language of a revision (null = original language) */
    public static function locale(Revision $revision): ?string
    {
        $locale = $revision->data['_locale'] ?? null;

        return is_string($locale) && $locale !== '' ? $locale : null;
    }

    /**
     * Current state of the content in the revision's language.
     *
     * @return array<string, mixed>
     */
    public static function current(Model $model, ?string $locale): array
    {
        if ($locale !== null) {
            return method_exists($model, 'translationsFor') ? $model->translationsFor($locale) : [];
        }

        $state = [];
        foreach ($model->getRevisionableFields() as $field) {
            $state[$field] = method_exists($model, 'getOriginalValue') ? $model->getOriginalValue($field) : $model->getAttribute($field);
        }

        return $state;
    }

    /**
     * Differences from $from to $to, per field with its label.
     *
     * @param  array<string, string>  $labels  labels of the content type
     * @return array<string, array>
     */
    public static function fields(Model $model, array $from, array $to, ?string $locale, array $labels = []): array
    {
        $fields = $locale !== null && method_exists($model, 'translatableFields')
            ? $model->translatableFields()
            : $model->getRevisionableFields();

        $diff = [];
        foreach ($fields as $field) {
            if ($field === 'blocks') {
                $blocks = BlockDiff::compare($from['blocks'] ?? null, $to['blocks'] ?? null);
                if ($blocks !== null) {
                    $diff['blocks'] = ['kind' => 'blocks', 'label' => self::label('blocks', $labels)] + $blocks;
                }

                continue;
            }

            // For block content, content is merely derived from the blocks
            if ($field === 'content' && $model->getAttribute('content_type') === 'blocks') {
                continue;
            }

            $old = self::normalize($from[$field] ?? null);
            $new = self::normalize($to[$field] ?? null);
            if ($old !== $new) {
                $diff[$field] = ['label' => self::label($field, $labels), 'revision' => $old, 'current' => $new];
            }
        }

        return $diff;
    }

    /** Summary of a diff, e.g. "Hero changed · 2 blocks added · Title" */
    public static function summary(array $diff): string
    {
        if ($diff === []) {
            return (string) __('translation.pages.revisions.summary_none');
        }

        $parts = [];
        if (isset($diff['blocks'])) {
            $blocks = $diff['blocks'];
            $names = array_values(array_unique(array_column($blocks['changed'], 'label')));
            if ($names !== []) {
                $shown = array_slice($names, 0, 2);
                $more = count($names) - count($shown);
                $parts[] = __('translation.pages.revisions.summary_changed', ['blocks' => implode(', ', $shown).($more > 0 ? ' +'.$more : '')]);
            }
            if ($blocks['added'] !== []) {
                $parts[] = trans_choice('translation.pages.revisions.summary_added', count($blocks['added']), ['count' => count($blocks['added'])]);
            }
            if ($blocks['removed'] !== []) {
                $parts[] = trans_choice('translation.pages.revisions.summary_removed', count($blocks['removed']), ['count' => count($blocks['removed'])]);
            }
            if ($blocks['reordered'] && $parts === []) {
                $parts[] = __('translation.pages.revisions.summary_reordered');
            }
            unset($diff['blocks']);
        }

        foreach ($diff as $entry) {
            $parts[] = $entry['label'];
        }

        return implode(' · ', $parts);
    }

    private static function normalize(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        // Date: text in the revision, an object in the current state. Text is app time
        // (as in the database) — read as a date, both show the same local time
        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}(:\d{2}(\.\d+)?)?(Z|[+-]\d{2}:?\d{2})?$/', $value)) {
            $value = Carbon::parse($value);
        }
        if ($value instanceof \DateTimeInterface) {
            return format_datetime($value);
        }
        if (is_array($value) || is_object($value)) {
            return (string) json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }

        return (string) $value;
    }
}
