<p align="center">
  <img src="https://img.shields.io/badge/Inverys_CMS-System_Plugin-6366f1?style=for-the-badge" alt="Inverys CMS System Plugin">
  <img src="https://img.shields.io/badge/Version-1.0.0-22c55e?style=for-the-badge" alt="Version 1.0.0">
  <img src="https://img.shields.io/badge/License-MIT-blue?style=for-the-badge" alt="MIT License">
</p>

# :globe_with_meridians: Multilingual

Makes the **content** of [Inverys CMS](https://github.com/tklmn/inverys-cms) multilingual:
pages, blog posts and categories, menus, forms, gallery albums, media, page
categories, theme texts and the texts in settings that visitors see (site name,
cookie banner, announcement bar).

The plugin does not ship any language itself. Languages come as **language packs**
(e.g. [`language-de`](../language-de/)), which register a locale and bring the UI
texts for it. Install a French pack and every editor offers *EN · DE · FR*.

---

## :sparkles: Features

| | Feature | Description |
|---|---|---|
| :link: | **Language prefix + own slugs** | `/about-us` (default language), `/de/ueber-uns`, `/fr/a-propos` — every translation has its own address | <!-- german-ok -->
| :arrows_counterclockwise: | **Fallback** | Empty fields show the default language, then the original; UI texts always fall back to English |
| :pencil2: | **Language menu in editors** | Pages (page builder), blog, forms, gallery, media, page categories, theme customizer — `?lang=de`; shows per language whether it is translated, copied or missing |
| :clock3: | **Revisions per language** | Pages and blog posts keep a separate history for every language; translation revisions carry the language code, and restoring one changes only that language |
| :clipboard: | **Copy from another translation** | In the editor: *Copy from Deutsch* as a starting point for a new language. Under *System → Languages*: prefill a whole language (`php artisan multilingual:copy de fr`). Copied items stay marked until someone saves them |
| :memo: | **Translate texts** | One page for the settings visitors read: site name, cookie banner and its services, announcement bar |
| :mag_right: | **Search per language** | The site search also finds the visitor's language and links to the translated address |
| :bookmark: | **Status in lists** | Pages, posts, categories, forms, albums and media show which languages are done (`DE` filled = translated) |
| :lock: | **Shared fields stay shared** | Status, parent, images, colors … apply to all languages and are locked while translating |
| :compass: | **Language menu** | Rendered into the theme navigation; links to the counterpart of the current page |
| :mag: | **hreflang** | Alternate links in `<head>` for search engines |
| :world_map: | **Sitemap per language** | `/sitemap.xml` becomes an index of `/sitemap-{locale}.xml`, each with hreflang alternates |
| :gear: | **Default language** | Chosen from the installed languages under *System → Languages* |
| :package: | **Language packs** | *System → Languages* lists the installed packs with version and state; *Manage language packs* opens the plugin list filtered to language packs (`?type=language`) |
| :mag: | **Quick search** | The admin quick search (<kbd>Ctrl</kbd>+<kbd>K</kbd>) finds the Languages page, e.g. with *default language*, *language packs* or *prefill* |

---

## :brain: Concepts

**Base language** — the language the content in the core tables was written in.
It is recorded once on install (`ml.base_locale`) and does not change when the
default language changes; otherwise originals would be labelled wrongly.

**Default language** — what visitors get without a prefix, and the first
fallback for missing translations. Setting `language`, selectable on the
*Languages* page (`/backend/multilingual/settings`, permission
`multilingual-settings`, admin only); *Settings > General* shows it locked.

**UI language vs. content language** — the backend language is set per user;
the content language is only set in the frontend, from the URL. An editor with
an English UI still edits the German version of a page.

**Lookup order** for a visitor reading French:

1. French translation of the field
2. Translation in the default language (if it is not the base language)
3. The original value

---

## :clipboard: Copying a translation

A new language does not have to start from scratch. Copying takes every
translated field of the source language that is still empty in the target —
never the slug (the address is created from the translated title on save) and
never the original (empty fields show it anyway; a copy would hide later
changes).

A copied item gets a marker row (`field = '_copied_from'`, value = source
language). Lists show a dashed language tag, the editor a note and the language
menu *copied from …*. Saving the item in the editor removes the marker. The
page builder's auto-save does not (`saveTranslations(..., reviewed: false)`).
Site texts (settings) are copied without a marker — they have no list.

```php
app(\App\Contracts\ContentTranslator::class)->copy($page, 'de', 'fr'); // one item
app(\App\Contracts\ContentTranslator::class)->copyLocale('de', 'fr');  // everything (DatabaseContentTranslator)
```

---

## :card_file_box: Storage

All translations live in one table, `ml_translations`
(`translatable_type`, `translatable_id`, `locale`, `field`, `value`).
Nothing is added to the core tables — disabling the plugin leaves the site in
its base language, uninstalling drops the table.

Short fields (titles, slugs …) are loaded in bulk per model type and language;
long fields (`content`, `blocks`, `captions` …) are loaded one by one when read.

---

## :hammer_and_wrench: Making your own models translatable

Core and plugins use the same trait. Add it to the model and list the fields:

```php
use App\Models\Concerns\HasTranslations;

class Event extends Model
{
    use HasTranslations;

    protected array $translatable = ['title', 'slug', 'description'];
}
```

Reading a translatable attribute in the frontend then returns the translation of
the visitor's language. Without this plugin, `HasTranslations` is a no-op
(`NullContentTranslator`), so your plugin works with and without it.

**Frontend lookup by slug** — use the translated slug instead of `where('slug')`:

```php
$event = Event::findByTranslatedSlug(Event::query()->where('is_active', true), $slug);
```

**Language menu and hreflang** — tell the core how a model's URL is built:

```php
app(\App\Support\Locales::class)->pathUsing(Event::class, fn ($event, $slug) => 'events/'.$slug);
```

**Editor** — show the language switch and edit the translation instead of the
original:

```blade
@php($i18n = \App\Support\EditLocale::form($event))

<x-slot:actions><x-ui.locale-switch :model="$event" /></x-slot:actions>
<x-ui.translation-note :form="$i18n" />
@if($i18n->locale) <input type="hidden" name="_lang" value="{{ $i18n->locale }}"> @endif

<x-ui.input name="title" :value="$i18n->value('title')" :placeholder="$i18n->placeholder('title')" />
<x-ui.toggle name="is_active" :disabled="$i18n->shared" />
```

**Revisions** — if the model also uses `App\Traits\HasRevisions`, saving a
translation keeps its previous state as a revision of that language (one per
editing session). See [Revisions](../../docs/PLUGIN-DEVELOPMENT.md#16a--revisions)
in the plugin development guide.

**Controller** — save the translation and leave the original untouched:

```php
if ($locale = \App\Support\EditLocale::current($request)) {
    \App\Support\Translations::saveFromRequest($request, $event, $locale, [
        'title' => 'nullable|string|max:255',
        'slug' => 'nullable|string|max:255',
        'description' => 'nullable|string',
    ]);

    return redirect()->route('backend.events.edit', ['event' => $event, 'lang' => $locale]);
}
```

`saveFromRequest()` derives the slug from the title when it is left empty
(with the language's transliteration: *Über uns* → `ueber-uns`) and rejects <!-- german-ok -->
slugs that are already taken in that language.

---

## :memo: Translatable settings

Some settings hold text visitors read — the site name, the cookie banner, the
announcement bar. They are listed in a registry and translated on one page,
*Languages → Translate texts* (`/backend/multilingual/texts`); the original stays
in its own settings page. In the frontend the setting store returns the
visitor's language, so code that reads `setting('site_name')` or
`cms()->settings()->get(...)` needs no change.

Register your plugin's settings in `boot()`:

```php
$this->extensions()->registerTranslatableSetting('shop.notice', [
    'label' => 'shop::shop.notice',       // translation key or English text
    'group' => 'shop::shop.title',
    'type' => 'textarea',                 // text | textarea | list
]);

// A JSON list (e.g. cookie services): translate single fields per entry
$this->extensions()->registerTranslatableSetting('shop.payment_methods', [
    'type' => 'list',
    'fields' => ['description' => 'shop::shop.description'],
    'item_key' => 'id',
    'item_label' => 'name',
]);
```

Bundled packages that do not register themselves yet are listed in
`config('cms.translatable_settings')`.

## :mag_right: Search

Search providers match the visitor's language with one extra condition inside
their `or` group:

```php
$q->orWhere('title', 'LIKE', $pattern)
  ->orWhereTranslation(['title', 'content'], $pattern);
```

Outside a translation (backend, base language) it adds nothing.

## :no_entry_sign: Paths without a language

Backend, API, login and profile pages, theme assets and files (`robots.txt`,
`sitemap.xml` …) keep their address and never get a prefix — see
[`src/Support/Paths.php`](src/Support/Paths.php).

## :construction: Not covered yet

- The marketplace packages `inverys/announcement-bar` and
  `inverys/ember-theme` still carry one English screen-reader label each
  (*Dismiss*, *Toggle navigation*); the announcement text itself is translatable.
  Fixing the labels needs a release of those packages.
- A form's internal name (used in the subject of the copy sent to the visitor)
  is not translatable; its fields, button and messages are.
