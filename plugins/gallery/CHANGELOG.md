# Gallery Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-10-13

First public release of the Gallery plugin for Inverys CMS.

### Added

- **Albums** with name, slug, description and active status
- **Media library integration:** images are referenced, not copied; add them from the library or upload new ones
- **Album editor** with drag-and-drop ordering and inline captions that save automatically
- **Page builder block** to embed an album in any page
- **Display settings** with global defaults and per-album overrides: columns (2–6), gap, aspect ratio, thumbnail size, lightbox, captions, lazy loading and pagination
- **Lightbox** (GLightbox, bundled, no CDN) with keyboard navigation and translated buttons
- **Multilingual:** with the Multilingual plugin, album name, description and captions per language
- **Search:** albums in the website search and the admin quick search; the settings page found by topic
- **Dashboard** stat card with the album count
- **Media usage:** the media library lists the albums that use an image and warns before it is deleted
- **Trash integration** for deleted albums
- **Permissions** to show, create, edit and delete albums and to manage settings; editors manage albums, the settings are admin only
- English and German translations
