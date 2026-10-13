<p align="center">
  <img src="https://img.shields.io/badge/Inverys_CMS-Language_Pack-6366f1?style=for-the-badge" alt="Inverys CMS Language Pack">
  <img src="https://img.shields.io/badge/Version-1.0.0-22c55e?style=for-the-badge" alt="Version 1.0.0">
  <img src="https://img.shields.io/badge/License-MIT-blue?style=for-the-badge" alt="MIT License">
</p>

# :de: Deutsch (language-de)

German language pack for [Inverys CMS](https://github.com/tklmn/inverys-cms).

- Registers the locale `de` in the core language registry
- Brings the German UI texts (`lang/de/*.php`, `lang/de.json`), including the
  installer (`lang/de/install.php`)
- Translates the English names and labels from `plugin.json` / `theme.json`
  (plugin names, theme settings, setting descriptions) via `lang/de.json`
- With the [Multilingual](../multilingual/) plugin, German becomes a content
  language: editors offer a German version, visitors reach it under `/de/…`

**German demo content.** `demo/de.json` holds the German version of all bundled
demo content (pages including page-builder blocks, blog posts and categories,
page categories, gallery albums, media alt texts, theme texts and regions such
as the footer, and translatable site texts). The installer applies it
automatically; on a site installed without the pack run `php artisan language-de:demo`
(safe to run repeatedly). `php artisan cms:demo-refresh` applies it again after
renewing the demo pages. Texts are matched by their English original, so edited
pages are left alone, and existing translations are never overwritten. As
translations this needs the [Multilingual](../multilingual/) plugin — without
it the command reports that there is nothing to translate. When the site itself
is installed in German, the originals are replaced instead.

The rich-text editor (TinyMCE) switches to German on its own: its language files
come from the npm package `tinymce-i18n` and are copied to
`public/vendor/tinymce/langs` by `npm run build`. Dates use the German formats
(`24.09.2026`, `24. September 2026`) unless a user sets their own format.

The core itself only speaks English. Missing texts in a pack appear in English.
Plugins keep their own translations in their `lang/de/` folders; they are used
whenever `de` is active — which requires this pack, since only registered
locales can be selected.

---

## :package: Building a language pack (e.g. French)

1. Copy this folder to `plugins/language-fr/`.
2. Rename the namespace and provider: `Plugins\LanguageFr\LanguageFrServiceProvider`
   in `src/`, `plugin.json` and `composer.json`.
3. Set slug, name and locale in `plugin.json`:

   ```json
   {
       "slug": "language-fr",
       "name": "Français",
       "version": "1.0.0",
       "description": "Language pack French.",
       "provider": "Plugins\\LanguageFr\\LanguageFrServiceProvider",
       "type": "language",
       "locale": "fr",
       "native": "Français",
       "is_system": true
   }
   ```

4. Register the locale in the provider's `register()` — early, before any text
   is translated:

   ```php
   $this->app->make(Locales::class)->register(
       code: 'fr',
       name: 'French',
       native: 'Français',
       langPath: $this->getPluginPath().'/lang',
   );
   ```

5. Rename `lang/de/` → `lang/fr/` and `lang/de.json` → `lang/fr.json` and
   translate the values. Keys you leave out fall back to English, so a pack can
   start small. `lang/fr/install.php` makes French selectable in the installer —
   it finds language packs on disk before any plugin is enabled. Add
   `"native": "Français"` to `plugin.json` for the installer's language list.
6. Enable the plugin under *Extensions → Plugins* (the filter *Language packs*
   lists only plugins with `"type": "language"`). French now appears in the
   profile language list, in the default-language selection and — with the
   Multilingual plugin — in every editor and in the frontend language menu.

Translations for plugins belong to the plugins themselves
(`plugins/<plugin>/lang/fr/`), not to the language pack.
