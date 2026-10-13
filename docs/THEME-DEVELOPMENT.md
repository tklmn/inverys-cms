# 🎨 Theme Development Guide

A comprehensive guide to building, customizing, and distributing themes for **Inverys CMS**. This document covers the theme system architecture, the `theme.json` manifest, Blade views, helper functions, regions, plugin view overrides, custom CSS injection, and distribution via ZIP or Composer.

---

## 📑 Table of Contents

1. [Overview](#1--overview)
2. [Directory Structure](#2--directory-structure)
3. [Theme Manifest (theme.json)](#3--theme-manifest-themejson)
4. [composer.json for Distribution](#4--composerjson-for-distribution)
5. [Setting Types](#5--setting-types)
6. [Blade Views](#6--blade-views)
7. [Helper Functions](#7--helper-functions)
8. [Regions and the Block Editor](#8--regions-and-the-block-editor)
9. [Overriding Plugin Views](#9--overriding-plugin-views)
10. [Custom CSS Injection](#10--custom-css-injection)
11. [Complete Minimal Theme Example](#11--complete-minimal-theme-example)
12. [Distribution](#12--distribution)

---

## 1. 🌐 Overview

Inverys CMS supports fully swappable frontend themes. Each theme is a self-contained directory inside `themes/` at the project root. A theme consists of a **JSON manifest** that declares metadata, configurable settings, and content regions, plus a set of **Blade views** that control the frontend markup.

### How Themes Are Activated

The active theme is stored in the `settings` database table under the key `active_theme`. Admins switch themes from **Extensions > Themes** in the admin panel. Users may also select a personal frontend theme via their profile settings; the personal selection takes precedence on the frontend for that user.

### ThemeManager

The `ThemeManager` service (`app/Services/ThemeManager.php`) is the central class that powers the theme system. On every request its `boot()` method:

1. **Discovers** all themes by scanning every subdirectory of `themes/` (and Composer packages of type `inverys-theme`) for a valid `theme.json` file. A theme's `lang/` folder is registered for JSON translations (`lang/de.json` …) of its manifest texts.
2. **Reads the active theme** from the database (defaulting to `default`).
3. **Prepends the active theme's view directory** to Laravel's view resolution order (including the default theme), so that the theme's Blade files are found before the core fallback views.

#### ThemeManager Public API

| Method | Description |
|:-------|:------------|
| `discover()` | Scans `themes/` and loads all manifests into memory |
| `all()` | Returns an array of all discovered theme manifests |
| `has($slug)` | Checks whether a theme with the given slug exists |
| `get($slug)` | Returns the full manifest array for a theme, or `null` |
| `getActive()` | Returns the slug of the globally active theme |
| `getActiveForUser()` | Returns the active theme for the current user (respects per-user override) |
| `activate($slug)` | Sets the given theme as the globally active theme |
| `getThemePath($slug)` | Returns the absolute filesystem path to the theme directory |
| `getAssetUrl($slug, $path)` | Returns the URL of a file in the theme's `assets/` (`/ext-assets/theme/{slug}/{path}?v={version}`), see [Static Files](#static-files-assets) |
| `getSettingsSchema($slug)` | Returns the `settings` object from the manifest |
| `getRegionsSchema($slug)` | Returns the `regions` object from the manifest |
| `getSavedSettings($slug)` | Returns all saved setting values for a theme (cached) |
| `getSavedRegions($slug)` | Returns all saved region block data for a theme (cached) |
| `getCustomCss($slug)` | Returns the saved custom CSS string for a theme (cached) |
| `isEditable($slug)` | Returns whether the theme allows file editing via the admin editor |
| `getEditablePatterns($slug)` | Returns the glob patterns that define which files are editable |
| `getEditableFiles($slug)` | Lists all editable files for a theme, filtered by patterns and extension whitelist |
| `validateFilePath($slug, $path)` | Validates that a file path is safe and editable within the theme |
| `saveSetting($slug, $key, $value)` | Persists a single theme setting and clears cache |
| `saveRegion($slug, $key, $jsonValue)` | Persists region block JSON and clears cache |
| `saveCustomCss($slug, $css)` | Sanitizes and persists custom CSS, then clears cache |
| `clearCache($slug)` | Clears all cached data for the given theme |

### View Resolution Order

The ThemeManager prepends the active theme's `views/` directory (including the default theme) to Laravel's view paths via the `theme::` namespace:

```
1. themes/{active-theme}/views/   ← checked first (theme:: namespace)
2. resources/views/frontend/       ← fallback (core views)
```

Controllers and layout components resolve views with theme-first fallback:
- `theme::page` → `frontend.page`
- `theme::layouts.app` → `frontend.layouts.app`
- `theme::blocks.{type}` → `frontend.blocks.{type}`

A theme only needs to include the views it wants to override — all others fall back to the core automatically.

For plugin view namespaces, a similar override applies (see [Section 9](#9--overriding-plugin-views)).

### Bundled Themes

Two themes ship with the CMS out of the box, a third comes as a Composer package:

| Theme | Directory | Description |
|:------|:----------|:------------|
| **Default** | `themes/default/` | Clean, minimal design with primary color, branding, navigation toggles, and footer settings |
| **Modern** | `themes/modern/` | Bold, magazine-style theme with gradient accents, centered navigation, and configurable max width |
| **Starter** | `vendor/inverys/starter-theme/` | Minimal dark theme with amber accents (Composer package `inverys/starter-theme`) |

> **📌 Note:** Both bundled themes support dark mode (`"supports_dark_mode": true`) and include a `footer_content` region with a default three-column block layout.

---

## 2. 📂 Directory Structure

Every theme lives in `themes/{slug}/` and must contain at least a `theme.json` manifest file. Here is the typical layout:

```
themes/
  my-theme/
    theme.json                    ← Required: manifest file
    views/
      layouts/
        app.blade.php             ← Main layout (wraps all pages)
        navigation.blade.php      ← Top navigation bar
        footer.blade.php          ← Footer partial
      home.blade.php              ← Homepage template
      page.blade.php              ← Standard page template
      partials/
        menu.blade.php            ← Navigation menu partial
        hero.blade.php            ← Hero section partial
      plugins/
        blog/                     ← Plugin view overrides (optional)
          index.blade.php
          show.blade.php
    assets/
      screenshot.png              ← Theme preview image (shown in admin)
      css/                        ← Optional: theme-specific CSS
      js/                         ← Optional: theme-specific JS
    lang/
      de.json                     ← Optional: translations of the manifest texts
```

### Required Files

| File | Purpose |
|:-----|:--------|
| `theme.json` | Manifest file. Without it, ThemeManager will not discover the theme. |

### Recommended Files

| File | Purpose |
|:-----|:--------|
| `views/layouts/app.blade.php` | Main layout that wraps every page |
| `views/layouts/navigation.blade.php` | Navigation bar |
| `views/layouts/footer.blade.php` | Footer |
| `views/home.blade.php` | Homepage template |
| `views/page.blade.php` | Standard page template |

### Optional Files

| File / Directory | Purpose |
|:-----------------|:--------|
| `assets/screenshot.png` | Preview image in admin theme selector (referenced via `screenshot` field) |
| `assets/css/`, `assets/js/` | Static CSS and JS files for the theme — linked with `theme_asset('css/theme.css')` |
| `views/partials/` | Reusable Blade partials |
| `views/plugins/{plugin-slug}/` | Override directories for plugin frontend views |
| `lang/{locale}.json` | Translations of the English texts in `theme.json` (name, description, labels, groups, options) |

### Static Files (`assets/`)

Files in `assets/` are served at `/ext-assets/theme/{slug}/{path}` — no build step on the server, so a theme installed as a ZIP can bring its own CSS and JS. Link them with `theme_asset()`; the URL carries the theme version, so browsers cache the file for a year and fetch it again after an update:

```blade
<link rel="stylesheet" href="{{ theme_asset('css/theme.css') }}">
```

Only `assets/` is served, only these types: css, js, mjs, woff2, woff, svg, png, jpg, jpeg, webp, avif, gif, ico. Hidden files and folders are never served, and nothing outside `assets/`, not even through a symlink. With `?v=` the response is cached for a year (`immutable`), without it for one hour; SVG files get their own sandboxing CSP. `theme_asset()` uses the theme the visitor sees (a signed-in user's personal frontend theme, otherwise the active one) — `ThemeManager::getAssetUrl($slug, $path)` builds the same URL for a given theme. Keep only values from theme settings (colours, widths) in an inline `<style>`; everything else belongs in a file.

---

## 3. 🏷️ Theme Manifest (`theme.json`)

The `theme.json` file is the single source of truth for a theme's metadata, configurable settings, and content regions. It must be valid JSON and include at least the required top-level fields.

### Full Schema Example

```json
{
    "slug": "my-theme",
    "name": "My Theme",
    "version": "1.0.0",
    "description": "A custom theme for Inverys CMS.",
    "author": "Your Name",
    "screenshot": "assets/screenshot.png",
    "supports_dark_mode": true,
    "settings": { },
    "regions": { }
}
```

### Top-Level Fields

| Field | Type | Required | Description |
|:------|:-----|:--------:|:------------|
| `slug` | `string` | Yes | Unique identifier. Must match the directory name exactly (e.g., `my-theme` = `themes/my-theme/`). Lowercase letters, numbers, and hyphens only. |
| `name` | `string` | Yes | Human-readable display name shown in the admin theme selector. |
| `version` | `string` | Yes | Semantic version string (e.g., `1.0.0`). |
| `description` | `string` | Yes | Brief description shown in the admin theme list. |
| `author` | `string` | Yes | Theme author name. |
| `screenshot` | `string` | No | Relative path (from the theme root) to a preview image. |
| `supports_dark_mode` | `boolean` | No | Whether the theme supports dark mode. Defaults to `false`. |
| `settings` | `object` | No | Map of setting key to setting definition. Each key becomes a configurable field in the admin customizer. |
| `regions` | `object` | No | Map of region key to region definition. Regions are block-editable content areas. |
| `repository` | `string` | No | Update source: a GitHub repository as `owner/repo`. When it has a newer release, the theme list shows an **Update** button — the current folder is backed up first and can be restored. Releases are tagged with the version (`1.2.0` or `v1.2.0`). |
| `editable` | `boolean\|array` | No | Controls whether the admin theme editor can edit this theme's files. `false` or omitted = locked (default). `true` = all files editable. Array of glob patterns (e.g., `["views/**/*.blade.php"]`) = only matching files editable. |

### Settings Object

The `settings` object is a map where each key is the setting identifier and each value is a setting definition. The key is what you pass to the `theme()` helper in your Blade templates.

```json
"settings": {
    "primary_color": {
        "type": "color",
        "label": "Primary Color",
        "group": "Colors",
        "default": "#4f46e5"
    }
}
```

#### Setting Definition Fields

| Property | Type | Required | Description |
|:---------|:-----|:--------:|:------------|
| `type` | `string` | Yes | Input type. One of: `boolean`, `color`, `select`, `image`, `text`, `textarea` |
| `label` | `string` | Yes | Human-readable label in the admin customizer |
| `group` | `string` | Yes | Groups settings into sections/tabs in the admin UI |
| `default` | `mixed` | Yes | Default value when no saved value exists |
| `options` | `object` | Conditional | **Required for `select` type only.** Key-value pairs where keys are stored values and values are display labels |
| `description` | `string` | No | Help text shown below the field |

Write all texts in `theme.json` (name, description, labels, groups, options) in **English**; the admin area shows them through `__()`, so a language pack or the theme's own `lang/{locale}.json` translates them. With the multilingual plugin, `text` and `textarea` settings can be translated per language in the customizer, and `theme()` returns the visitor's language.

#### Setting Groups

The `group` field controls how settings are organized in the admin customizer. All settings with the same group value appear together under a shared heading. Common conventions:

| Group Name | Typical Settings |
|:-----------|:-----------------|
| `"Colors"` | Color pickers for brand colors, gradients, backgrounds |
| `"Branding"` | Logo, favicon, site tagline |
| `"Layout"` | Structural toggles: breadcrumbs, hero sections, max width, scroll-to-top |
| `"Navigation"` | Navigation bar toggles: search, profile link, logout button |
| `"Footer"` | Footer text and related settings |
| `"General"` | Catch-all for miscellaneous settings |

> **💡 Tip:** You can use any string as a group name. The admin UI creates sections dynamically based on the group values it finds.

### Regions Object

The `regions` object defines content areas that admins can populate using the page builder's block editor. See [Section 8](#8--regions-and-the-block-editor) for full details.

```json
"regions": {
    "footer_content": {
        "label": "Footer Content",
        "description": "Additional block content displayed in the footer area.",
        "default": []
    }
}
```

#### Region Definition Fields

| Property | Type | Required | Description |
|:---------|:-----|:--------:|:------------|
| `label` | `string` | Yes | Display name shown in the admin region editor |
| `description` | `string` | No | Help text displayed below the label |
| `default` | `array` | No | Default block JSON array, used as a starting point before admin saves custom content |

### How Settings Are Stored

Settings, regions, and custom CSS are persisted in the `settings` database table:

| Data | Key Format | Example |
|:-----|:-----------|:--------|
| Theme setting | `theme.{slug}.{setting_key}` | `theme.modern.primary_color` |
| Region data | `theme.{slug}.region.{region_key}` | `theme.modern.region.footer_content` |
| Custom CSS | `theme.{slug}.custom_css` | `theme.modern.custom_css` |

---

## 4. 📦 `composer.json` for Distribution

Third-party themes can be distributed as Composer packages. This enables installation via `composer require` and automatic discovery by the ThemeManager.

### Full Example

Below is a `composer.json` modelled on the starter theme package (`inverys/starter-theme`):

```json
{
    "name": "inverys/starter-theme",
    "description": "A minimal starter theme for Inverys CMS — clean, lightweight, and easy to customize.",
    "type": "inverys-theme",
    "version": "1.0.0",
    "license": "MIT",
    "authors": [
        { "name": "Third-Party Dev", "email": "dev@example.com" }
    ],
    "extra": {
        "inverys": {
            "slug": "starter"
        }
    }
}
```

### Required Fields

| Field | Value | Purpose |
|:------|:------|:--------|
| `type` | `"inverys-theme"` | Tells the CMS this is a theme package (not a plugin or regular library) |
| `extra.inverys.slug` | e.g. `"starter"` | The theme slug. Must match the `slug` field in your `theme.json` |

### How CMS Discovers Vendor Packages

The `ThemeManager` scans Composer's `vendor/` directory using the `installed.json` metadata:

1. It reads `vendor/composer/installed.json` to find all installed packages.
2. Packages with `"type": "inverys-theme"` are identified as themes.
3. The `extra.inverys.slug` value (or, without it, the package name after the `/`) is used to match the theme to its `theme.json` manifest inside the package directory.
4. No custom Composer installer is needed -- the package stays in `vendor/` and is resolved automatically.

> **📌 Note:** The theme package **must** still contain a valid `theme.json` manifest at its root. The `composer.json` is for Composer distribution; the `theme.json` is for the CMS.

> **⚠️ Warning:** If a theme exists both in `themes/{slug}/` (uploaded or manually placed) **and** in `vendor/` (Composer-installed), the local `themes/` directory takes precedence. This allows site-specific overrides of Composer-installed themes.

### Installation

Users install Composer-distributed themes with:

```bash
composer require inverys/starter-theme
```

The theme will appear automatically in **Extensions > Themes** in the admin panel.

---

## 5. ⚙️ Setting Types

Six setting types are available. Each type determines the input control rendered in the admin customizer.

### `boolean`

A toggle switch. Stored as `"1"` (true) or `"0"` (false) in the database. The `default` value in the manifest should be `true` or `false`.

```json
"show_hero": {
    "type": "boolean",
    "label": "Show Hero Section",
    "group": "Layout",
    "default": true
}
```

```blade
@if(theme('show_hero'))
    @include('theme::partials.hero')
@endif
```

---

### `color`

A color picker input. Stored as a hex color string.

```json
"primary_color": {
    "type": "color",
    "label": "Primary Color",
    "group": "Colors",
    "default": "#4f46e5"
}
```

```blade
<style>
    :root { --primary: {{ theme('primary_color', '#4f46e5') }}; }
</style>
```

> **💡 Tip:** Define multiple color settings to give admins control over gradients:
>
> ```json
> "gradient_start": {
>     "type": "color",
>     "label": "Gradient Start",
>     "group": "Colors",
>     "default": "#667eea"
> },
> "gradient_end": {
>     "type": "color",
>     "label": "Gradient End",
>     "group": "Colors",
>     "default": "#764ba2"
> }
> ```

---

### `select`

A dropdown menu. Requires an `options` object where keys are stored values and values are display labels.

```json
"max_width": {
    "type": "select",
    "label": "Content Max Width",
    "group": "Layout",
    "options": {
        "max-w-4xl": "Small (4xl)",
        "max-w-5xl": "Medium (5xl)",
        "max-w-6xl": "Large (6xl)",
        "max-w-7xl": "Extra Large (7xl)"
    },
    "default": "max-w-5xl"
}
```

```blade
<div class="{{ theme('max_width', 'max-w-5xl') }} mx-auto px-4">
    {{ $slot }}
</div>
```

---

### `image`

A media library browser that lets the admin select or upload an image. Stored as a URL string (or path).

```json
"logo_url": {
    "type": "image",
    "label": "Logo",
    "group": "Branding",
    "default": "images/logo.svg"
}
```

```blade
@if(theme('logo_url'))
    <img src="{{ theme('logo_url') }}" alt="Site Logo" class="h-8">
@else
    <span class="font-bold">{{ __('translation.app_name') }}</span>
@endif
```

---

### `text`

A single-line text input. Stored as a string.

```json
"footer_text": {
    "type": "text",
    "label": "Copyright Text",
    "group": "Footer",
    "default": "All rights reserved."
}
```

```blade
<p class="text-sm text-gray-500">{{ theme('footer_text', 'All rights reserved.') }}</p>
```

---

### `textarea`

A multi-line text area. Stored as a string. Useful for longer content, descriptions, or code snippets.

```json
"about_text": {
    "type": "textarea",
    "label": "About Text",
    "group": "General",
    "default": ""
}
```

```blade
@if(theme('about_text'))
    <div class="about-section">
        {!! nl2br(e(theme('about_text'))) !!}
    </div>
@endif
```

---

### Setting Types at a Glance

| Type | Input Control | Stored As | `default` Type | Requires `options` |
|:-----|:-------------|:----------|:---------------|:------------------:|
| `boolean` | Toggle switch | `"1"` / `"0"` | `true` / `false` | No |
| `color` | Color picker | Hex string | String | No |
| `select` | Dropdown | Selected key | String | Yes |
| `image` | Media browser | URL / path | String | No |
| `text` | Text input | String | String | No |
| `textarea` | Text area | String | String | No |

---

## 6. 🖼️ Blade Views

Themes use standard Laravel Blade templates. The views directory structure mirrors the default frontend views, and the ThemeManager ensures the active theme's views take precedence.

### Layout: `views/layouts/app.blade.php`

The main layout wraps every page. It contains the HTML skeleton, asset loading, navigation include, footer include, and content slots.

```blade
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ isset($title) ? $title . ' - ' . __('translation.app_name') : __('translation.app_name') }}</title>

        @isset($meta)
            {{ $meta }}
        @endisset

        <link rel="stylesheet" href="{{ asset('css/bootstrap-icons.css') }}">
        @vite(['resources/css/frontend/app.css', 'resources/js/frontend/app.js'])

        {{-- Theme-specific CSS variables --}}
        <style>
            :root { --primary: {{ theme('primary_color', '#4f46e5') }}; }
        </style>

        {{-- Custom CSS injection (must come last) --}}
        @if(theme_custom_css())
            <style id="theme-custom-css">{!! theme_custom_css() !!}</style>
        @endif
    </head>
    <body>
        @include('theme::layouts.navigation')

        @isset($header)
            <header>{{ $header }}</header>
        @endisset

        <main id="main-content">{{ $slot }}</main>

        @include('theme::layouts.footer')
    </body>
</html>
```

> **📌 Note:** Use the `theme::` namespace prefix when including partials from the same theme. This ensures correct view resolution regardless of which theme is active.

> **💡 Tip:** The `$slot` variable holds the page content. The `$title`, `$meta`, and `$header` variables are named slots passed from page templates. Always place the `theme_custom_css()` output after all other stylesheets so admin overrides take precedence.

---

### Styles: Tailwind v4 entry point

The frontend stylesheet is `resources/css/frontend/app.css` (Tailwind v4, CSS-first — there is
no SCSS pipeline). Load it with `@vite` exactly as in the layout above.

- **Utility classes in your views are generated automatically** after `npm run build`: Tailwind
  scans `themes/*` and — via `@source` — Composer-installed packages under `vendor/inverys/*`.
- **Reference only `resources/css/frontend/app.css`** in `@vite`: an entry that is not in the
  Vite manifest makes every page return a 500 ("Unable to locate file in Vite manifest").
- **Keep inline `<style>` rules in a layer** (`@layer components { … }`) or scope them tightly:
  unlayered CSS beats every Tailwind utility, so e.g. a `.btn { display: inline-flex }` rule
  silently overrides `md:hidden` on the same element.

### Homepage: `views/home.blade.php`

The homepage template. Uses the `<x-frontend-app-layout>` Blade component, which resolves to the theme's `layouts/app.blade.php`.

```blade
<x-frontend-app-layout>
    <x-slot name="header">
        <h1 class="text-3xl font-bold">{{ __('translation.app_name') }}</h1>
    </x-slot>

    @if(theme('show_hero'))
        @include('theme::partials.hero')
    @endif

    <div class="prose max-w-none">
        <p>Welcome to your site.</p>
    </div>
</x-frontend-app-layout>
```

---

### Page Template: `views/page.blade.php`

Renders standard content pages. The template receives a `$page` Eloquent model with properties including `title`, `content`, `content_type`, `blocks`, `meta_title`, `meta_description`, and `children`.

```blade
<x-frontend-app-layout>
    <x-slot name="title">{{ $page->meta_title ?? $page->title }}</x-slot>
    <x-slot name="meta">
        @if($page->meta_description)
            <meta name="description" content="{{ $page->meta_description }}">
        @endif
    </x-slot>

    <x-slot name="header">
        @if(theme('show_page_title', true))
            <h1 class="text-3xl font-bold">{{ $page->title }}</h1>
        @endif
    </x-slot>

    <article class="prose max-w-none">
        @if(($page->content_type ?? 'html') === 'blocks' && !empty($page->blocks))
            {!! app(\App\Services\BlockRenderer::class)->render($page->blocks) !!}
        @else
            {!! \App\Helpers\HtmlHelper::clean($page->content) !!}
        @endif
    </article>
</x-frontend-app-layout>
```

> **📌 Note:** Pages always consist of blocks (`content_type` is `blocks`): render them through the `BlockRenderer` service. HTML that arrives through the REST API is turned into a **Text** block on save. `content` then holds the plain text of the blocks for search and short descriptions — don't output it as the page body. The `html` branch above only stays as a fallback for pages without blocks.
>
> The page editor shows your `page.blade.php` in its preview (`backend/pages/{id}/canvas`), including drafts. Links and forms don't navigate there, and `<html>` carries a `data-editor-canvas` attribute if a theme script needs to behave differently in the editor.

---

### Navigation: `views/layouts/navigation.blade.php`

The top navigation bar. Queries active, visible pages and renders them as links.

```blade
<nav class="border-b bg-white dark:bg-gray-900">
    <div class="max-w-5xl mx-auto px-4 flex items-center justify-between h-14">
        <a href="{{ url('/') }}" class="font-bold text-lg">
            @if(theme('logo_url'))
                <img src="{{ theme('logo_url') }}" alt="{{ __('translation.app_name') }}" class="h-8">
            @else
                {{ __('translation.app_name') }}
            @endif
        </a>
        <div class="flex items-center space-x-4 text-sm">
            @php
                $menuPages = \App\Models\Page::whereNull('parent_id')
                    ->where('is_active', true)
                    ->where('hide_in_menu', false)
                    ->orderBy('order')
                    ->get();
            @endphp
            @foreach($menuPages as $page)
                <a href="{{ $page->is_root ? route('home') : route('page.show', $page->slug) }}">
                    {{ $page->title }}
                </a>
            @endforeach
        </div>
    </div>
</nav>
```

---

### Footer: `views/layouts/footer.blade.php`

The footer partial. Typically renders a footer region (if configured) and a copyright line.

```blade
<footer class="border-t bg-gray-900 text-gray-300">
    <div class="max-w-5xl mx-auto px-4 py-8">
        @if(theme_region('footer_content'))
            <div class="mb-6">{!! theme_region('footer_content') !!}</div>
        @endif
        <p class="text-sm text-gray-500">
            {{ theme('footer_text', 'All rights reserved.') }}
        </p>
    </div>
</footer>
```

---

### Partials: `views/partials/`

Extract reusable components into `views/partials/`. Include them with the `theme::` namespace:

```blade
@include('theme::partials.hero')
@include('theme::partials.sidebar')
@include('theme::partials.breadcrumbs')
```

Any Blade file can be placed in `partials/` and included from layouts or page templates.

---

### Inline Scripts & Content Security Policy

The CMS ships a strict, **nonce-based** Content Security Policy. Any inline
`<script>` in a theme layout, page template, or partial **must carry the
per-request nonce**, or the browser will refuse to execute it once the policy is
enforced:

```blade
{{-- Blade directive (preferred) --}}
<script @cspNonce>
    // scroll-to-top, mobile nav toggle, etc.
</script>

{{-- Equivalent explicit form --}}
<script nonce="{{ csp_nonce() }}"> /* ... */ </script>
```

- **Inline event handlers are forbidden** (`onclick="…"` and friends) — a nonce
  cannot cover them. Use `addEventListener` inside a nonced `<script>`.
- **Inline styles stay allowed** — `<style>` blocks and `style="…"` attributes
  are fine (`style-src` keeps `'unsafe-inline'`), so the `theme_custom_css()`
  and `:root` variable blocks above need no change.
- **Self-host external assets.** Fonts/scripts from a third-party origin (e.g.
  `fonts.bunny.net`) are blocked by `default-src 'self'`; bundle them under the
  theme's `assets/` instead of loading from a CDN. Code that really needs a
  third-party source for one page can allow it for that response with
  `app(\App\Support\CspSources::class)->allow('font-src', 'https://fonts.example.com')`
  (only `https://` origins; directives `script-src`, `connect-src`, `img-src`,
  `frame-src`, `font-src`, `style-src`, `media-src`).

> **💡 Tip:** The strict policy is **enforced** (`Content-Security-Policy`, not
> `Report-Only`). An un-nonced inline script or an inline event handler is
> **blocked** by the browser — test your theme with the console open.

---

### Post-processing of the Finished Page

After the theme and all plugins have rendered, the core changes every complete
HTML response (one that contains `<html`). Your layout cannot opt out of these
steps, so plan for them:

- **Copyright notice.** `<!-- Copyright © {year} Inverys · Inverys CMS -->` is
  inserted right after the doctype — on frontend, backend and error pages. A theme
  can neither change nor remove it; don't put markup before `<!DOCTYPE html>`.
- **Minify HTML** (Settings → General, setting `html.minify`, on by default; not
  in the page-builder preview):
  - HTML comments are removed — except conditional comments, comments starting
    with `[` or `]`, Livewire markers and the copyright notice. Don't rely on
    comments in the output (Blade `{{-- --}}` comments never reach it anyway).
  - Line breaks and indentation next to tags become one space; inside text a run
    of line breaks and indentation becomes a single line break, other spaces in
    text stay. Attribute values stay byte for byte.
  - `<pre>` and `<textarea>` stay unchanged. For whitespace-sensitive output use
    them — `white-space: pre` on another element loses its indentation.
  - Inline `<style>` and `<script>` (no `src`; no type, `module`, `text/javascript`
    or `application/javascript`) are minified with
    [matthiasmullie/minify](https://github.com/matthiasmullie/minify): comments go,
    so inline scripts must be valid JavaScript and must not read their own source.
    JSON scripts (`application/json`, `application/ld+json`) are re-encoded
    compactly; invalid JSON and other types (templates) stay as they are.
- **Protect email addresses** (Settings → General, setting `html.protect_emails`,
  on by default; only for visitors who are not signed in, only inside `<body>`).
  `mailto:` links get `href="#"` and the encoded address in `data-cms-email`; an
  address in text shows its `@` through `<span class="cms-at"></span>` (styled by
  an injected `<style>`); in `title`, `aria-label` and `alt` the `@` becomes
  `(at)`. The deferred script `public/js/email-protection.js`, added before
  `</body>`, restores everything once the DOM is ready. Theme scripts therefore
  should not select `a[href^="mailto:"]` or read addresses from the markup before
  `DOMContentLoaded`, and HTML inserted later by script is not protected. There
  is no per-element opt-out: addresses in `<script>`, `<style>`, `<textarea>`,
  `<select>`, `<svg>`, `<title>`, comments and other attributes (`value`,
  `data-*` …) are left alone; admins can switch the protection off.
- **Icon sprite.** Icons rendered with `<x-ui.icon>` / `App\Support\Icon` appear
  once as `<symbol>` elements before `</body>`; each occurrence is
  `<svg><use href="#ui-i-…"></use></svg>`. Keep `</body>` in your layout.

---

## 7. 🔧 Helper Functions

The following global helper functions are available in all Blade templates. They are defined in `app/Helpers/theme_helpers.php` and registered via Composer autoload. The same file provides `cms()` and `registration_open()` (may visitors create an account?); dates go through `format_date()`, `format_datetime()` and `format_date_long()` from `app/Helpers/date_helpers.php`.

### `theme($key, $default = null)`

Retrieves a setting value for the active theme. Resolves the active theme slug (respecting per-user overrides on the frontend; in the backend the globally active theme), then looks up the setting from the database. If it was never saved (e.g. the customizer was never opened), the `default` from `theme.json` is used, and only then `$default`. With the multilingual plugin, `text` and `textarea` values come in the visitor's language. Settings are cached per request.

```php
function theme(string $key, mixed $default = null): mixed
```

The database key queried is `theme.{active_slug}.{$key}`.

**Examples:**

```blade
{{-- Color setting with fallback --}}
<div style="color: {{ theme('primary_color', '#4f46e5') }}">

{{-- Boolean setting --}}
@if(theme('show_hero'))
    @include('theme::partials.hero')
@endif

{{-- Select setting --}}
<div class="{{ theme('max_width', 'max-w-5xl') }} mx-auto">

{{-- Image setting --}}
@if(theme('logo_url'))
    <img src="{{ theme('logo_url') }}" alt="Logo">
@endif

{{-- Text setting --}}
<p>{{ theme('footer_text', 'All rights reserved.') }}</p>
```

---

### `setting($key, $default = null)`

Retrieves a **global** (non-theme-specific) setting value from the `settings` table. This is for CMS-wide configuration values, not theme settings.

```php
function setting(string $key, mixed $default = null): mixed
```

**Examples:**

```blade
{{-- Global CMS setting --}}
<meta name="author" content="{{ setting('site_author', 'Inverys') }}">

{{-- Check a global feature flag --}}
@if(setting('maintenance_mode'))
    <div class="alert">Site is in maintenance mode.</div>
@endif
```

---

### `theme_region($key)`

Renders a block-based content region for the active theme. Loads the saved block JSON from the database, passes it through the `BlockRenderer`, and returns the rendered HTML string. Returns an empty string if the region has no saved content.

```php
function theme_region(string $key): string
```

The database key queried is `theme.{active_slug}.region.{$key}`.

**Examples:**

```blade
{{-- Render footer blocks --}}
@if(theme_region('footer_content'))
    <div class="footer-blocks">
        {!! theme_region('footer_content') !!}
    </div>
@endif

{{-- Render a sidebar region --}}
{!! theme_region('sidebar_widgets') !!}
```

> **⚠️ Warning:** Always use `{!! !!}` (unescaped output) since the region content is pre-rendered HTML from the block renderer. Wrap in an `@if` check to avoid rendering empty containers.

---

### `theme_custom_css()`

Returns the custom CSS string for the active theme. Returns an empty string if no custom CSS has been saved.

```php
function theme_custom_css(): string
```

The database key queried is `theme.{active_slug}.custom_css`.

**Example:**

```blade
@if(theme_custom_css())
    <style id="theme-custom-css">{!! theme_custom_css() !!}</style>
@endif
```

> **💡 Tip:** Place this in the `<head>` of your layout, **after** all other stylesheets, so custom CSS takes precedence over theme defaults.

---

### `theme_asset($path)` and `plugin_asset($slug, $path)`

Return the URL of a file in the `assets/` folder of the active theme or of a plugin, with the version as `?v=` for long browser caching (see [Static Files](#static-files-assets)).

```php
function theme_asset(string $path): string
function plugin_asset(string $slug, string $path): string
```

```blade
<link rel="stylesheet" href="{{ theme_asset('css/theme.css') }}">
<script src="{{ theme_asset('js/theme.js') }}" defer></script>
```

---

### Helper Functions at a Glance

| Function | Returns | Scope | DB Key Pattern |
|:---------|:--------|:------|:---------------|
| `theme($key, $default)` | `mixed` | Active theme setting | `theme.{slug}.{key}` |
| `setting($key, $default)` | `mixed` | Global CMS setting | `{key}` |
| `theme_region($key)` | `string` (HTML) | Active theme region | `theme.{slug}.region.{key}` |
| `theme_custom_css()` | `string` (CSS) | Active theme CSS | `theme.{slug}.custom_css` |
| `theme_asset($path)` | `string` (URL) | Active theme's `assets/` | — |
| `plugin_asset($slug, $path)` | `string` (URL) | A plugin's `assets/` | — |

---

## 8. 🧩 Regions and the Block Editor

Regions are named content areas within a theme that admins can populate using the same drag-and-drop block editor that powers the page builder. Common use cases include footer content, sidebars, promotional banners, and call-to-action sections.

### Defining Regions in `theme.json`

Add entries to the `regions` object in your manifest. Each key is the region identifier used in your templates.

```json
"regions": {
    "footer_content": {
        "label": "Footer Content",
        "description": "Block content displayed in the footer area."
    },
    "sidebar_widgets": {
        "label": "Sidebar Widgets",
        "description": "Optional sidebar content."
    }
}
```

### Rendering Regions in Templates

Use the `theme_region()` helper to render a region's block content as HTML:

```blade
@if(theme_region('footer_content'))
    <div class="footer-region">
        {!! theme_region('footer_content') !!}
    </div>
@endif
```

### Providing Default Region Content

You can ship a theme with pre-populated region content by specifying a `default` array of blocks. This default is used in the admin region editor as a starting point before the admin saves any custom content.

The following example defines a three-column footer layout with headings, text, and a link group:

```json
"regions": {
    "footer_content": {
        "label": "Footer Content",
        "description": "Footer area with default three-column layout.",
        "default": [
            {
                "id": "footer-cols",
                "type": "columns",
                "settings": { "columns": "3", "gap": "gap-8", "class": "" },
                "children": [
                    {
                        "id": "col-1",
                        "type": "column",
                        "settings": { "class": "" },
                        "children": [
                            {
                                "id": "heading-1",
                                "type": "heading",
                                "data": { "text": "About Us", "level": "h3", "alignment": "left" },
                                "settings": { "class": "text-white" }
                            },
                            {
                                "id": "text-1",
                                "type": "text",
                                "data": { "content": "<p>Your company description here.</p>" },
                                "settings": { "class": "text-gray-300" }
                            }
                        ]
                    },
                    {
                        "id": "col-2",
                        "type": "column",
                        "settings": { "class": "" },
                        "children": [
                            {
                                "id": "heading-2",
                                "type": "heading",
                                "data": { "text": "Quick Links", "level": "h3", "alignment": "left" },
                                "settings": { "class": "text-white" }
                            },
                            {
                                "id": "links-1",
                                "type": "link-group",
                                "data": { "links": [
                                    { "text": "Home", "url": "/", "target": "_self" },
                                    { "text": "About", "url": "/about", "target": "_self" },
                                    { "text": "Contact", "url": "/contact", "target": "_self" }
                                ]},
                                "settings": { "class": "flex-col text-gray-300" }
                            }
                        ]
                    },
                    {
                        "id": "col-3",
                        "type": "column",
                        "settings": { "class": "" },
                        "children": [
                            {
                                "id": "heading-3",
                                "type": "heading",
                                "data": { "text": "Contact", "level": "h3", "alignment": "left" },
                                "settings": { "class": "text-white" }
                            },
                            {
                                "id": "contact-text",
                                "type": "text",
                                "data": { "content": "<p>123 Main Street<br>City, State 12345</p><p><a href=\"mailto:info@example.com\">info@example.com</a></p>" },
                                "settings": { "class": "text-gray-300" }
                            }
                        ]
                    }
                ]
            }
        ]
    }
}
```

### Block Structure Reference

Each block in a region is a JSON object with these fields:

| Field | Type | Description |
|:------|:-----|:------------|
| `id` | `string` | Unique identifier for the block instance |
| `type` | `string` | Block type name (e.g., `heading`, `text`, `columns`) |
| `data` | `object` | Block-specific content data (e.g., `text`, `content`, `links`) |
| `settings` | `object` | Block-specific settings (e.g., `class`, `columns`, `gap`) |
| `children` | `array` | Nested child blocks (used by layout blocks like `columns` and `column`) |

### Available Block Types

Regions support the same block types available in the page builder:

| Category | Block Types |
|:---------|:------------|
| **Layout** | Container, Columns, Column, Section, Spacer, Divider |
| **Content** | Heading, Text, Image, Video, Quote, Code, List |
| **Interactive** | Accordion, Tabs, Button, Link Group, Alert, Card |

### How Region Data Is Stored

Region block data is stored in the `settings` table with the key `theme.{slug}.region.{region_key}` as a JSON-encoded block array. The ThemeManager provides methods for reading and writing:

- `ThemeManager::getSavedRegions($slug)` -- returns all regions as decoded arrays
- `ThemeManager::saveRegion($slug, $key, $jsonValue)` -- saves region block data and clears cache

---

## 9. 🔌 Overriding Plugin Views

Themes can override any plugin's frontend views by placing matching Blade files in the theme's `views/` directory under a subdirectory named after the plugin slug.

### Resolution Order

When a plugin renders a frontend view (e.g., `blog::index`), the system checks:

```
1. themes/{active-theme}/views/plugins/{plugin-slug}/   ← checked first
2. plugins/{plugin-slug}/views/frontend/         ← fallback
```

If the active theme provides a matching file, it is used instead of the plugin's default.

### Step-by-Step: Overriding the Blog Listing Page

**Step 1.** Create the override directory in your theme:

```bash
mkdir -p themes/my-theme/views/plugins/blog/
```

**Step 2.** Copy the original template from the plugin:

```bash
cp plugins/blog/views/frontend/index.blade.php themes/my-theme/views/plugins/blog/index.blade.php
```

**Step 3.** Edit the copied file to match your theme's design.

The blog plugin will now use your theme's version of `index.blade.php` on the frontend.

### Common Plugin View Overrides

Check each plugin's `views/frontend/` directory to see which templates can be overridden. For the Blog plugin:

| Plugin View | Override Path |
|:------------|:--------------|
| `plugins/blog/views/frontend/index.blade.php` | `themes/{theme}/views/plugins/blog/index.blade.php` |
| `plugins/blog/views/frontend/show.blade.php` | `themes/{theme}/views/plugins/blog/show.blade.php` |
| `plugins/blog/views/frontend/feed.blade.php` | `themes/{theme}/views/plugins/blog/feed.blade.php` |

### Limitations

> **⚠️ Warning:** The override file must have the same filename as the original, and the subdirectory structure of the plugin's views must be mirrored in the theme's override directory.

> **📌 Note:** Overrides work in every theme, the bundled `default` theme included. On the website a signed-in user's own theme (Profile) applies, plugin views included; the admin area uses the globally active theme. The directory name under `views/plugins/` is the plugin's view namespace — for frontend views of the bundled plugins the same as the slug; admin views use `{slug}-admin` (e.g. `views/plugins/blog-admin/`).

---

## 10. 🎛️ Custom CSS Injection

### Site Tokens and Building Blocks

The frontend stylesheet (`resources/css/frontend/components/site.css`) provides
design tokens and components that page-builder blocks and the default theme use —
so blocks look right in every theme and in light and dark mode:

| Token | Meaning |
|---|---|
| `--primary` | Brand colour — set it in your layout from `theme('primary_color')` |
| `--primary-soft`, `--primary-text`, `--primary-ring` | Derived accent colours (tints, readable text, focus ring) |
| `--site-bg`, `--site-bg-soft`, `--site-surface`, `--site-surface-raised` | Page, alternating bands, cards, menus |
| `--site-text`, `--site-heading`, `--site-muted`, `--site-subtle` | Text hierarchy |
| `--site-border`, `--site-border-strong`, `--site-shadow`, `--site-radius` | Lines, depth, corners |

Classes: `site-card`, `site-btn site-btn-{primary|secondary|outline|ghost|light}`,
`site-alert`, `site-quote`, `site-tabs-list`/`site-tab`, `site-prose`,
`site-icon-btn`, `site-badge`, `site-page-title`, `site-container`.
All tokens switch automatically under `.dark`. A theme that sets only
`:root { --primary: … }` already gets consistent buttons, links and accents.

### Design Options (`site-design.css`)

`resources/css/frontend/components/site-design.css` turns the page-builder
options into classes — editors choose from lists, themes style the classes:

| Option | Classes |
|---|---|
| Background | `site-surface-{soft,tint,glow,gradient,dark}`; gradient and dark add `site-inverted` (all `--site-*` colours switch to light text) |
| Alignment | `site-text-center`, `site-text-right` |
| Blocks | `site-hero` (+ `-split`, `-centered`, `-large`, `-compact`, `-grid`), `site-stats` / `site-stat`, `site-icon-badge(-gradient)`, `site-card-{plain,tinted,center}`, `site-btn-{sm,lg}`, `site-lead`, `site-text-gradient` |

More options and blocks: background image `site-has-bg` + `site-bg-{dark,light,brand}`
(the image is an `<img class="site-bg-media">`, not an inline style), block spacing
`site-gap-{none,sm,md,lg}`, device visibility `site-only-{desktop,mobile}` (wrapped by
`BlockRenderer`), `site-pricing` / `site-plan`, `site-logos`, `site-btn-group`.

`App\Support\BlockDesign` maps option values to these classes (unknown values
produce no class), `App\Support\ContentIcons` lists the icons editors may pick.

Error pages (403, 404, 419, 429, 500, 503) live in `resources/views/errors` and
use the frontend stylesheet and the theme's colour and logo. They do not use the
theme layout on purpose: on 500/503 the database may be unavailable.

The default theme exposes its customizer settings as attributes on `<body>`
(`data-header`, `data-footer`, `data-radius`, `data-font`, `data-glow`,
`data-dark`) and as CSS variables (`--primary`, `--accent`, `--site-max-width`).
The layout lets only known values through. Another theme can reuse the same
attributes or ignore them.

Icons: `<x-ui.icon name="search" />` renders a Lucide icon; add missing names
to `copy-icons.cjs`. The Inter font is bundled (`@fontsource-variable/inter`),
no external font request.

### Custom CSS

Every theme supports custom CSS through the admin customizer. This allows admins to add CSS overrides without modifying theme files.

### How It Works

1. The admin navigates to **Extensions > Themes > Customize** and enters CSS in the "Custom CSS" field.
2. The CSS is sanitized server-side by `HtmlHelper::cleanCss()`. Dangerous patterns are replaced with `/* removed */`:
   - `expression()` calls
   - `javascript:` and `vbscript:` (also inside `url()`)
   - `@import`
   - `behavior:` and `-moz-binding:`
   - `<style>` and `<script>` tags
3. The sanitized CSS is stored in the `settings` table with the key `theme.{slug}.custom_css`.
4. On each request, the layout template injects the CSS via the `theme_custom_css()` helper, which sanitizes it again on output.

### Template Integration

Add the following to the `<head>` section of your layout, **after** all other stylesheets:

```blade
@if(theme_custom_css())
    <style id="theme-custom-css">{!! theme_custom_css() !!}</style>
@endif
```

> **💡 Tip:** The `id="theme-custom-css"` attribute is a convention that makes it easy to identify the custom CSS block in the browser's developer tools.

### Programmatic Access

From PHP code, use the ThemeManager:

```php
// Read custom CSS
$css = app(ThemeManager::class)->getCustomCss('my-theme');

// Save custom CSS (sanitization is applied automatically)
app(ThemeManager::class)->saveCustomCss('my-theme', $cssString);
```

---

## 11. 🚀 Complete Minimal Theme Example

This section walks through creating a fully working theme from scratch. The theme will have a primary color setting, boolean toggles, a text setting, a footer region, and full page rendering.

### Step 1: Create the Directory

```bash
mkdir -p themes/minimal/views/layouts
mkdir -p themes/minimal/views/partials
```

### Step 2: Write `theme.json`

Create `themes/minimal/theme.json`:

```json
{
    "slug": "minimal",
    "name": "Minimal Theme",
    "version": "1.0.0",
    "description": "A clean, minimal theme with simple typography and a narrow content column.",
    "author": "Your Name",
    "settings": {
        "accent_color": {
            "type": "color",
            "label": "Accent Color",
            "group": "Colors",
            "default": "#2563eb"
        },
        "show_breadcrumbs": {
            "type": "boolean",
            "label": "Show Breadcrumbs",
            "group": "Layout",
            "default": true
        },
        "show_page_title": {
            "type": "boolean",
            "label": "Show Page Title",
            "group": "Layout",
            "default": true
        },
        "footer_text": {
            "type": "text",
            "label": "Footer Text",
            "group": "Footer",
            "default": "Built with Inverys CMS."
        }
    },
    "regions": {
        "footer_content": {
            "label": "Footer Content",
            "description": "Block content displayed above the copyright line."
        }
    }
}
```

### Step 3: Create the Layout

Create `themes/minimal/views/layouts/app.blade.php`:

```blade
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ isset($title) ? $title . ' - ' . __('translation.app_name') : __('translation.app_name') }}</title>

        @isset($meta)
            {{ $meta }}
        @endisset

        <link rel="stylesheet" href="{{ asset('css/bootstrap-icons.css') }}">
        @vite(['resources/css/frontend/app.css', 'resources/js/frontend/app.js'])

        <style>
            :root {
                --accent: {{ theme('accent_color', '#2563eb') }};
            }
            a { color: var(--accent); }
            a:hover { opacity: 0.8; }
        </style>

        @if(theme_custom_css())
            <style id="theme-custom-css">{!! theme_custom_css() !!}</style>
        @endif
    </head>
    <body class="bg-white text-gray-900 dark:bg-gray-950 dark:text-gray-100">
        @include('theme::layouts.navigation')

        @isset($header)
            <header class="border-b py-8">
                <div class="max-w-3xl mx-auto px-4">{{ $header }}</div>
            </header>
        @endisset

        <main id="main-content" class="max-w-3xl mx-auto px-4 py-8">
            {{ $slot }}
        </main>

        @include('theme::layouts.footer')
    </body>
</html>
```

### Step 4: Create the Navigation

Create `themes/minimal/views/layouts/navigation.blade.php`:

```blade
<nav class="border-b">
    <div class="max-w-3xl mx-auto px-4 flex items-center justify-between h-14">
        <a href="{{ url('/') }}" class="font-bold text-lg no-underline">
            {{ __('translation.app_name') }}
        </a>
        <div class="flex items-center space-x-4 text-sm">
            @php
                $menuPages = \App\Models\Page::whereNull('parent_id')
                    ->where('is_active', true)
                    ->where('hide_in_menu', false)
                    ->orderBy('order')
                    ->get();
            @endphp
            @foreach($menuPages as $page)
                <a href="{{ $page->is_root ? route('home') : route('page.show', $page->slug) }}">
                    {{ $page->title }}
                </a>
            @endforeach
        </div>
    </div>
</nav>
```

### Step 5: Create the Footer

Create `themes/minimal/views/layouts/footer.blade.php`:

```blade
<footer class="border-t mt-12">
    <div class="max-w-3xl mx-auto px-4 py-8">
        @if(theme_region('footer_content'))
            <div class="mb-6">
                {!! theme_region('footer_content') !!}
            </div>
        @endif
        <p class="text-sm text-gray-500">
            {{ theme('footer_text', 'Built with Inverys CMS.') }}
        </p>
    </div>
</footer>
```

### Step 6: Create the Homepage

Create `themes/minimal/views/home.blade.php`:

```blade
<x-frontend-app-layout>
    <x-slot name="header">
        <h1 class="text-3xl font-bold">{{ __('translation.app_name') }}</h1>
    </x-slot>

    <div class="prose max-w-none">
        <p>Welcome to your site. Edit this homepage in the admin panel.</p>
    </div>
</x-frontend-app-layout>
```

### Step 7: Create the Page Template

Create `themes/minimal/views/page.blade.php`:

```blade
<x-frontend-app-layout>
    <x-slot name="title">{{ $page->meta_title ?? $page->title }}</x-slot>
    <x-slot name="meta">
        @if($page->meta_description)
            <meta name="description" content="{{ $page->meta_description }}">
        @endif
    </x-slot>

    <x-slot name="header">
        @if(theme('show_page_title', true))
            <h1 class="text-3xl font-bold">{{ $page->title }}</h1>
        @endif
    </x-slot>

    <article class="prose max-w-none">
        @if(($page->content_type ?? 'html') === 'blocks' && !empty($page->blocks))
            {!! app(\App\Services\BlockRenderer::class)->render($page->blocks) !!}
        @else
            {!! \App\Helpers\HtmlHelper::clean($page->content) !!}
        @endif
    </article>
</x-frontend-app-layout>
```

### Step 8: Activate the Theme

1. Navigate to **Extensions > Themes** in the admin panel.
2. The "Minimal Theme" should appear in the list (ThemeManager discovers it automatically).
3. Click **Activate** to make it the active frontend theme.
4. Optionally click **Customize** to adjust the accent color, toggle breadcrumbs, or edit the footer text.
5. Visit the frontend to see your theme in action.

### Final Directory Structure

```
themes/minimal/
    theme.json
    views/
        layouts/
            app.blade.php
            navigation.blade.php
            footer.blade.php
        home.blade.php
        page.blade.php
```

> **💡 Tip:** From here, extend the theme by adding more settings, additional regions, plugin view overrides in `views/plugins/blog/`, partials in `views/partials/`, or static assets in `assets/`.

---

## 12. 📤 Distribution

Themes can be distributed in two ways: as a **ZIP file** for upload through the admin panel, or as a **Composer package** for installation via the command line.

### 12.1 🗂️ ZIP Upload

Package your theme directory as a ZIP file. The ZIP must contain a valid `theme.json` at the root (or one directory level deep if the ZIP contains a wrapper folder).

#### Packaging

```bash
cd themes/my-theme
zip -r my-theme.zip .
```

#### Upload Process

Administrators install the ZIP at **Extensions > Themes > Upload Package**. The CMS validates the package (manifest, slug format, security scan) and extracts it to `themes/`.

#### Security Checks

The following checks are performed during upload:

| Check | Details |
|:------|:--------|
| Path traversal | No `..` or absolute paths allowed |
| Blocked files | `.htaccess`, `.htpasswd`, `.env`, `web.config` |
| Blocked extensions | `.phar`, `.sh`, `.bash`, `.bat`, `.cmd`, `.exe`, `.com`, `.msi`, `.dll`, `.so` |
| Manifest | `theme.json` at the root or one folder deep, valid JSON with a `slug` |
| Slug format | Lowercase letters, numbers, and hyphens only (starting and ending with a letter or number) |
| Existing theme | A theme with the same slug is updated, not duplicated: an older `version` is rejected, otherwise the current folder is backed up first and can be restored |

> **⚠️ Warning:** Ensure your ZIP does not contain any of the blocked file types listed above. The upload will be rejected if any are detected.

---

### 12.2 📦 Composer Package

Third-party themes can be distributed as Composer packages for seamless installation and dependency management.

#### Full `composer.json` Example

```json
{
    "name": "inverys/starter-theme",
    "description": "A minimal starter theme for Inverys CMS — clean, lightweight, and easy to customize.",
    "type": "inverys-theme",
    "version": "1.0.0",
    "license": "MIT",
    "authors": [
        { "name": "Third-Party Dev", "email": "dev@example.com" }
    ],
    "extra": {
        "inverys": {
            "slug": "starter"
        }
    }
}
```

#### Required Fields

| Field | Value | Purpose |
|:------|:------|:--------|
| `type` | `"inverys-theme"` | Identifies the package as a Inverys CMS theme |
| `extra.inverys.slug` | e.g. `"starter"` | Must match the `slug` field in your `theme.json` |

#### How Discovery Works

1. The `ThemeManager` reads `vendor/composer/installed.json` to find all installed packages.
2. Packages with `"type": "inverys-theme"` are identified as themes.
3. The `extra.inverys.slug` value maps the package to its `theme.json` manifest.
4. No custom Composer installer is needed -- the package stays in `vendor/` and is resolved automatically.

#### Install Path

Composer-installed themes remain in `vendor/`. The ThemeManager resolves the theme path from Composer metadata, so no manual copying is required.

#### Installation Command

```bash
composer require inverys/starter-theme
```

The theme will appear automatically in **Extensions > Themes** in the admin panel.

> **📌 Note:** The package **must** still contain a valid `theme.json` manifest at its root. The `composer.json` handles Composer distribution; the `theme.json` is what the CMS reads.

> **💡 Tip:** If a theme exists both in `themes/{slug}/` (uploaded or manually placed) **and** in `vendor/` (Composer-installed), the local `themes/` directory takes precedence. This allows site-specific customizations to override Composer-installed themes.
