# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-10-13

First public release of the German language pack for Inverys CMS.

### Added

- **German user interface:** registers the locale `de` and brings the German UI texts (`lang/de/*.php`, `lang/de.json`) in the formal form of address, including authentication, validation, pagination and password messages
- **German installer:** German can be selected in the web installer, including the requirement checks; a German installation creates German content throughout
- **Labels from manifests:** plugin names, theme settings and setting descriptions from `plugin.json` / `theme.json` are translated via `lang/de.json`
- **German content language:** with the Multilingual plugin, editors offer a German version and visitors reach it under `/de/…`
- **German demo content:** `demo/de.json` holds the German version of all bundled demo content (pages with their blocks, blog posts and categories, page categories, gallery albums, media alt texts, theme texts and regions, site texts). The installer applies it, and `php artisan language-de:demo` applies it later; it is safe to run repeatedly and never overwrites existing translations
- **TinyMCE and dates:** the rich-text editor switches to German, and dates use the German formats unless a user sets their own
- A template for further language packs (see README)
