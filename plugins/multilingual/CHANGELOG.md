# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-10-13

First public release of the Multilingual plugin for Inverys CMS.

### Added

- **Multilingual content:** pages, blog posts and categories, menus, forms, gallery albums, media, page categories, theme texts and the settings visitors read (site name, cookie banner and its services, announcement bar). Languages come from language packs such as `language-de`
- **Language prefix and own slugs:** the default language has no prefix, every other language its own (`/de/…`), and every translation its own address
- **Fallback:** empty fields show the default language, then the original; UI texts fall back to English
- **Editors:** a language menu in every editor (`?lang=de`) that shows per language whether it is translated, copied or missing; fields shared by all languages are locked while translating; lists show the translation status per language
- **Copy from another translation** as a starting point; copied items stay marked as *copied, not translated yet* until someone saves them
- **Prefill a language:** *System → Languages* copies all translations of one language into another without touching existing ones, also as `php artisan multilingual:copy de fr`
- **Revisions per language:** every language keeps its own history, and restoring one changes only that language
- **Translate texts:** one page for the translatable settings; plugins register their own with `registerTranslatableSetting()`
- **Frontend:** language menu in the theme navigation that links to the counterpart of the current page, `hreflang` alternates in `<head>`, and site search in the visitor's language
- **Sitemap per language:** with several languages `/sitemap.xml` becomes an index of `/sitemap-{locale}.xml`, each with `hreflang` alternates and `x-default`
- **Default language** chosen from the installed languages under *System → Languages*
- **Language packs** listed with version and state; *Manage language packs* opens the plugin list filtered to language packs
- The Languages page is found by the admin quick search (e.g. *default language*, *prefill*)
