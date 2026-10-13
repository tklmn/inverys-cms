<?php

namespace Plugins\LanguageDe;

use App\Contracts\ContentTranslator;
use App\Contracts\SettingRepository;
use App\Models\Media;
use App\Models\Page;
use App\Models\PageCategory;
use App\Services\ThemeManager;
use App\Support\Locales;
use App\Support\SettingTranslations;
use App\Support\TranslatableSettings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Plugins\Blog\Models\BlogCategory;
use Plugins\Blog\Models\Post;
use Plugins\Gallery\Models\GalleryAlbum;

/**
 * German version of the bundled demo content.
 *
 * The demo content (pages, posts, categories, media, theme texts) is English.
 * This class lays the German texts from demo/de.json over it:
 *
 *   - base language English → as a translation (/de/{translated-slug} …)
 *   - base language German (installed in German) → straight into the originals
 *
 * Texts are matched by their English wording, not by IDs: pages with edited
 * texts stay untouched, existing translations are never overwritten. Blocks
 * keep their structure and IDs, only their texts change.
 */
final class DemoTranslations
{
    private const LOCALE = 'de';

    /** Text fields in block data */
    private const BLOCK_TEXT = ['text', 'content', 'title', 'author', 'cite', 'caption', 'alt', 'label', 'subtitle', 'description', 'eyebrow', 'primary_text', 'secondary_text', 'image_alt', 'link_text', 'value', 'name', 'period', 'features', 'button_text', 'badge', 'price'];

    /** Lists in block data whose entries contain texts */
    private const BLOCK_LISTS = ['items', 'links', 'slides', 'tabs', 'plans'];

    /** @var array<string, string> */
    private array $texts;

    /** @var array<string, array<string, string>> */
    private array $slugs;

    private bool $intoOriginals;

    /** @var array<string, int> */
    private array $counts = [];

    public function __construct(?string $file = null)
    {
        $data = json_decode((string) file_get_contents($file ?? dirname(__DIR__).'/demo/de.json'), true) ?: [];
        $this->texts = $data['texts'] ?? [];
        $this->slugs = $data['slugs'] ?? [];
    }

    /**
     * Apply the translations. Without the Multilingual plugin only when the
     * installation itself is German (the originals are replaced then).
     *
     * @return array<string, int> count per kind
     */
    public function apply(): array
    {
        $this->counts = [];
        $this->intoOriginals = app(Locales::class)->base() === self::LOCALE;

        if (! $this->intoOriginals && ! app(ContentTranslator::class)->enabled()) {
            return [];
        }

        foreach (Page::all() as $page) {
            $this->model($page, 'pages', ['title', 'nav_title', 'subtitle', 'meta_title', 'meta_description', 'og_title', 'og_description', 'aria_label', 'content'], 'page');
        }

        if ($this->available(Post::class)) {
            foreach (Post::all() as $post) {
                $this->model($post, 'posts', ['title', 'excerpt', 'content', 'meta_title', 'meta_description'], 'post');
            }
            foreach (BlogCategory::all() as $category) {
                $this->model($category, 'blog categories', ['name', 'description'], 'blog_category');
            }
        }

        foreach (PageCategory::all() as $category) {
            $this->model($category, 'page categories', ['name', 'description']);
        }

        foreach (Media::all() as $media) {
            $this->model($media, 'media', ['alt_text', 'description']);
        }

        if ($this->available(GalleryAlbum::class)) {
            foreach (GalleryAlbum::all() as $album) {
                $this->model($album, 'albums', ['name', 'description']);
            }
        }

        $this->themes();
        $this->settings();

        return array_filter($this->counts);
    }

    /** Plugin model usable? Class known and table created (plugin active) */
    private function available(string $class): bool
    {
        return class_exists($class) && Schema::hasTable((new $class)->getTable());
    }

    /**
     * @param  list<string>  $fields
     */
    private function model(Model $model, string $label, array $fields, ?string $slugType = null): void
    {
        $original = fn (string $field) => method_exists($model, 'getOriginalValue') ? $model->getOriginalValue($field) : $model->getAttribute($field);
        $values = [];

        foreach ($fields as $field) {
            $translated = $this->text($original($field));
            if ($translated !== null) {
                $values[$field] = $translated;
            }
        }

        // Page builder: texts inside the blocks, the structure stays
        if (in_array('blocks', $model->translatableFields(), true)) {
            $blocks = $original('blocks');
            if (is_array($blocks) && $blocks !== []) {
                $translated = $this->blocks($blocks, $changed);
                if ($changed) {
                    $values['blocks'] = $translated;
                }
            }
        }

        // Own addresses only for the translation — in the original, links such as
        // /contact in the demo texts would otherwise lead nowhere
        if (! $this->intoOriginals && $slugType && isset($this->slugs[$slugType][(string) $original('slug')]) && ! ($model->is_root ?? false)) {
            $values['slug'] = $this->slugs[$slugType][(string) $original('slug')];
        }

        if ($values === []) {
            return;
        }

        if ($this->intoOriginals) {
            $model->forceFill($values)->saveQuietly();
        } else {
            // Existing translations win — overwrite nothing
            $existing = array_filter(app(ContentTranslator::class)->all($model, self::LOCALE), fn ($v) => $v !== null && $v !== '');
            $values = array_diff_key($values, $existing);
            if ($values === []) {
                return;
            }
            app(ContentTranslator::class)->save($model, self::LOCALE, $values);
        }

        $this->counts[$label] = ($this->counts[$label] ?? 0) + 1;
    }

    /** Theme settings (texts) and block regions of all themes */
    private function themes(): void
    {
        $themes = app(ThemeManager::class);

        foreach (array_keys($themes->all()) as $slug) {
            $saved = $themes->getSavedSettings($slug);
            foreach ($themes->getSettingsSchema($slug) as $key => $schema) {
                if (! in_array($schema['type'] ?? 'text', ['text', 'textarea'], true)) {
                    continue;
                }
                $value = (string) ($saved[$key] ?? $schema['default'] ?? '');
                if (($translated = $this->text($value)) !== null) {
                    $this->setting("theme.{$slug}.{$key}", $value, $translated, 'theme_setting');
                }
            }

            $regions = $themes->getSavedRegions($slug);
            foreach ($themes->getRegionsSchema($slug) as $key => $schema) {
                $blocks = $regions[$key] ?? ($schema['default'] ?? []);
                $blocks = is_string($blocks) ? (json_decode($blocks, true) ?: []) : $blocks;
                if (! $blocks) {
                    continue;
                }
                $translated = $this->blocks($blocks, $changed);
                if ($changed) {
                    $this->setting("theme.{$slug}.region.{$key}", json_encode($blocks), json_encode($translated), 'theme_region');
                }
            }

            $themes->clearCache($slug);
        }
    }

    /** Settings from the registry (site name, announcement bar …) */
    private function settings(): void
    {
        $repository = app(SettingRepository::class);

        foreach (app(TranslatableSettings::class)->all() as $key => $definition) {
            if ($definition['type'] === 'list' || ! $repository->has($key)) {
                continue;
            }
            $value = (string) $repository->get($key);
            if (($translated = $this->text($value)) !== null) {
                $this->setting($key, $value, $translated, 'plugin_setting');
            }
        }
    }

    private function setting(string $key, string $original, string $translated, string $type): void
    {
        if ($this->intoOriginals) {
            app(SettingRepository::class)->set($key, $translated, $type);
        } elseif (SettingTranslations::translated($key, self::LOCALE) === null) {
            SettingTranslations::save($key, self::LOCALE, $translated, $original, $type);
        } else {
            return;
        }

        $this->counts['settings'] = ($this->counts['settings'] ?? 0) + 1;
    }

    private function text(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? ($this->texts[$value] ?? null) : null;
    }

    /**
     * Replace texts in blocks, recursively through children and lists.
     *
     * @param  array<int, array<string, mixed>>  $blocks
     * @return array<int, array<string, mixed>>
     */
    private function blocks(array $blocks, ?bool &$changed = false): array
    {
        $changed = false;

        foreach ($blocks as $i => $block) {
            foreach (($block['data'] ?? []) as $key => $value) {
                if (in_array($key, self::BLOCK_TEXT, true) && ($t = $this->text($value)) !== null) {
                    $blocks[$i]['data'][$key] = $t;
                    $changed = true;
                } elseif (in_array($key, self::BLOCK_LISTS, true) && is_array($value)) {
                    foreach ($value as $j => $item) {
                        if (is_string($item) && ($t = $this->text($item)) !== null) {
                            $blocks[$i]['data'][$key][$j] = $t;
                            $changed = true;
                        } elseif (is_array($item)) {
                            foreach (self::BLOCK_TEXT as $field) {
                                if (($t = $this->text($item[$field] ?? null)) !== null) {
                                    $blocks[$i]['data'][$key][$j][$field] = $t;
                                    $changed = true;
                                }
                            }
                        }
                    }
                }
            }

            if (! empty($block['children']) && is_array($block['children'])) {
                $blocks[$i]['children'] = $this->blocks($block['children'], $childChanged);
                $changed = $changed || $childChanged;
            }
        }

        return $blocks;
    }
}
