<p align="center">
  <img src="https://img.shields.io/badge/Inverys_CMS-Plugin-6366f1?style=for-the-badge" alt="Inverys CMS Plugin">
  <img src="https://img.shields.io/badge/Version-1.0.0-22c55e?style=for-the-badge" alt="Version 1.0.0">
  <img src="https://img.shields.io/badge/License-MIT-blue?style=for-the-badge" alt="MIT License">
  <img src="https://img.shields.io/badge/PHP-8.4+-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP 8.4+">
</p>

# :framed_picture: Gallery

A gallery plugin for [Inverys CMS](https://github.com/tklmn/inverys-cms). Manage photo albums with media library integration, display them via the page builder, and present images in responsive grids with lightbox support.

---

## :sparkles: Features

| | Feature | Description |
|---|---|---|
| :camera: | **Albums** | Create and manage photo albums with name, slug, description, and active status |
| :link: | **Media Library Integration** | Images are referenced from the media library (not duplicated) — edit properties in one place |
| :art: | **Per-Album Settings** | Override global defaults per album: columns, gap, aspect ratio, lightbox, captions, pagination |
| :jigsaw: | **Page Builder Block** | Embed any album in a page via the drag-and-drop page builder |
| :mag_right: | **GLightbox** | Fullscreen image viewing with keyboard navigation (bundled via Vite, no CDN) |
| :bar_chart: | **Responsive Grid** | Configurable 2-6 column grid with responsive breakpoints |
| :arrow_up_down: | **Drag-and-Drop Reorder** | Reorder album images with native HTML5 drag-and-drop |
| :pencil2: | **Inline Captions** | Edit image captions directly in the album editor with auto-save |
| :page_facing_up: | **Pagination** | Configurable images per page with page navigation |
| :sleeping: | **Lazy Loading** | Images below the fold load on scroll for better performance |
| :mag: | **Search Integration** | Albums in the website search and in the admin quick search (<kbd>Ctrl</kbd>+<kbd>K</kbd>); the settings page is found by topic, e.g. *thumbnails* or *lightbox* |
| :link: | **Media Usage** | The media library lists the albums that use an image and warns before it is deleted |
| :chart_with_upwards_trend: | **Dashboard** | Album count on the admin dashboard |
| :earth_africa: | **Multilingual** | With the [Multilingual](../multilingual/) plugin: album name, description and captions per language |
| :wastebasket: | **Trash Integration** | Soft-deleted albums appear in the CMS trash manager |
| :lock: | **Permissions** | Granular permissions: show, create, edit, delete albums, manage settings |
| :earth_americas: | **i18n** | Full English and German translations |

---

## :zap: Quick Start

### Bundled with Inverys CMS

The gallery plugin is included with the CMS and enabled by default during installation.

### Standalone via Composer

```bash
composer require inverys/gallery
```

Enable the plugin under **Extensions > Plugins**. The first enable creates its tables, permissions, default settings and the *Gallery* block type.

---

## :wrench: Configuration

The albums are under **Content > Gallery**. For the default settings, open **Extensions > Plugins** and click **Settings** next to the plugin — or use the quick search (<kbd>Ctrl</kbd>+<kbd>K</kbd>):

| Setting | Description | Default |
|---------|-------------|---------|
| **Columns** | Number of columns in the gallery grid (2-6) | `3` |
| **Grid Gap** | Spacing between images (narrow/normal/wide/extra wide) | Normal |
| **Aspect Ratio** | Cover (crop), Contain (fit), or Auto (original) | Cover |
| **Thumbnail Size** | Small, medium, or large thumbnails | Medium |
| **Lightbox** | Open images in fullscreen lightbox overlay | Enabled |
| **Show Captions** | Display image captions below thumbnails | Enabled |
| **Lazy Loading** | Load images only when scrolled into view | Enabled |
| **Pagination** | Show pagination controls below gallery | Enabled |
| **Images per Page** | Number of images per page (0 = show all) | `12` |

Each setting can be overridden per album in the album editor.

---

## :open_file_folder: File Structure

```
gallery/
├── plugin.json
├── composer.json
├── lang/
│   ├── en/gallery.php
│   └── de/gallery.php
├── src/
│   ├── GalleryServiceProvider.php
│   ├── Models/
│   │   └── GalleryAlbum.php
│   ├── Search/
│   │   ├── GallerySearchProvider.php   (website search)
│   │   └── AlbumAdminSearch.php        (quick search)
│   ├── Services/
│   │   └── GalleryMediaUsageChecker.php
│   └── Http/Controllers/Admin/
│       ├── AlbumController.php
│       └── SettingsController.php
└── views/
    ├── admin/
    │   ├── index.blade.php
    │   ├── create.blade.php
    │   ├── edit.blade.php
    │   ├── settings.blade.php
    │   └── block-type-script.blade.php
    └── frontend/
        ├── blocks/gallery.blade.php
        └── lightbox-assets.blade.php
```

---

## :page_facing_up: Requirements

- **Inverys CMS** 1.0 or newer
- **PHP** >= 8.4

## :handshake: Author

**Tom Kilimann** — [tkilimann@icloud.com](mailto:tkilimann@icloud.com)

## :scroll: License

MIT
