<p align="center">
  <img src="https://img.shields.io/badge/Inverys_CMS-Plugin-6366f1?style=for-the-badge" alt="Inverys CMS Plugin">
  <img src="https://img.shields.io/badge/Version-1.0.0-22c55e?style=for-the-badge" alt="Version 1.0.0">
  <img src="https://img.shields.io/badge/License-MIT-blue?style=for-the-badge" alt="MIT License">
  <img src="https://img.shields.io/badge/PHP-8.4+-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP 8.4+">
</p>

# :newspaper: Blog

A full-featured blog plugin for [Inverys CMS](https://github.com/tklmn/inverys-cms). Create and manage posts, organize with categories, publish an RSS feed, and expose content via a REST API.

---

## :sparkles: Features

| | Feature | Description |
|---|---|---|
| :pencil2: | **Posts** | Create, edit, publish, draft, and schedule blog posts with the TinyMCE editor |
| :bookmark_tabs: | **Categories** | Organize posts with categories, each with slug and description |
| :framed_picture: | **Featured Images** | Cover images for posts, chosen from the media library |
| :eyes: | **Preview** | Users who may edit posts open drafts and scheduled posts on the website with a notice bar (not cached, not indexed) |
| :clock3: | **Revisions** | Earlier versions of a post below its form — compare, preview and restore, as for pages; each language keeps its own history |
| :satellite: | **RSS Feed** | Auto-generated RSS feed at `/{prefix}/feed` |
| :world_map: | **Sitemap** | Published posts (with the blog index) and categories in `/sitemap.xml`, each group with its own switch under *Settings > SEO* |
| :globe_with_meridians: | **REST API** | Public read endpoints + protected write endpoints for posts and categories |
| :mag: | **Search Integration** | Published posts in the website search; posts (drafts included) and categories in the admin quick search (<kbd>Ctrl</kbd>+<kbd>K</kbd>) |
| :earth_africa: | **Multilingual** | With the [Multilingual](../multilingual/) plugin: title, slug, excerpt, content and SEO fields per language, with translated addresses |
| :chart_with_upwards_trend: | **Dashboard** | Post count, a *Create post* quick action and a widget with the latest posts |
| :gear: | **Configurable** | URL prefix, posts per page, columns, sorting and which elements are shown — all via admin settings |
| :earth_americas: | **i18n** | Full English and German translations |
| :wastebasket: | **Trash Integration** | Soft-deleted posts appear in the CMS trash manager |
| :link: | **Media Usage** | Deleting an image that is a post's featured image shows a warning |
| :lock: | **Permissions** | Granular permissions: view/create/edit/delete posts and categories, publish, schedule, draft, settings |

---

## :zap: Quick Start

### Bundled with Inverys CMS

The blog plugin is included with the CMS and enabled by default during installation.

### Standalone via Composer

```bash
composer require inverys/blog
```

Enable the plugin under **Extensions > Plugins**. The first enable creates its tables, permissions and default settings.

---

## :wrench: Configuration

Open **Extensions > Plugins** and click **Settings** next to the blog — or find the page with the quick search (<kbd>Ctrl</kbd>+<kbd>K</kbd>), e.g. *posts per page* or *URL prefix*:

| Setting | Description | Default |
|---------|-------------|---------|
| **URL Prefix** | Base URL path for the blog (e.g. `/blog`). The *Blog* menu entry follows it; a prefix already used by a page is refused | `blog` |
| **Posts Per Page** | Pagination count for the blog index (1–100) | `10` |
| **Columns** | Number of columns in the listing (1–6) | `1` |
| **Sort by / Direction** | Publication date, title or creation date; ascending or descending | Publication date, descending |
| **Show Pagination** | Pagination links below the listing | Enabled |
| **Show Featured Image / Excerpt / Author / Date / Categories** | Elements of the post cards and the single post | Enabled |
| **Show Read More** | *Read more* link on the cards, with an optional own text | Enabled |
| **Show Breadcrumbs / Previous/Next** | Navigation on the single post | Enabled |
| **Show Sidebar / RSS Link** | Sidebar with categories and the feed link on the listing | Enabled |

Settings are stored with the `blog.` prefix. Which post fields the website search looks at is set under *Settings > Search*.

---

## :clock3: Revisions

Every save of an existing post keeps its previous state — one revision per editing session and person, as for pages. The **Revisions** card below the post form lists them: compare with the current state or the next version, preview the post as it was (nothing is saved) and restore (the current state is kept as *Before restore*). Needs the `edit-post` permission.

The post is registered with the core as revisionable type `post` (`registerRevisionable()` in `BlogServiceProvider`), so its addresses are `backend/revisions/post/{id}/…`. It is the reference for plugins that want revisions for their own content — see [Revisions](../../docs/PLUGIN-DEVELOPMENT.md#16a--revisions) in the plugin development guide.

---

## :globe_with_meridians: REST API

### Public Endpoints (no auth required)

| Endpoint | Description |
|----------|-------------|
| `GET /api/v1/blog/posts` | List published posts (`?category=`, `?search=`, `?featured=` (with featured image), `?sort=`, `?order=`, `?per_page=` 1–100) |
| `GET /api/v1/blog/posts/{id\|slug}` | Single published post with author and categories |
| `GET /api/v1/blog/categories` | All categories with post counts |
| `GET /api/v1/blog/categories/{id\|slug}` | Category detail with paginated posts |

### Protected Endpoints (Bearer token + permissions)

| Endpoint | Permission |
|----------|------------|
| `POST /api/v1/blog/posts` | `create-post` |
| `PUT /api/v1/blog/posts/{id}` | `edit-post` |
| `DELETE /api/v1/blog/posts/{id}` | `delete-post` |
| `POST /api/v1/blog/categories` | `create-blog-category` |
| `PUT /api/v1/blog/categories/{id}` | `edit-blog-category` |
| `DELETE /api/v1/blog/categories/{id}` | `delete-blog-category` |

---

## :open_file_folder: File Structure

```
blog/
├── plugin.json
├── composer.json
├── lang/
│   ├── en/blog.php
│   └── de/blog.php
├── src/
│   ├── BlogServiceProvider.php
│   ├── Models/
│   │   ├── Post.php
│   │   └── BlogCategory.php
│   ├── Search/
│   │   ├── BlogSearchProvider.php      (website search)
│   │   ├── PostAdminSearch.php         (quick search)
│   │   └── CategoryAdminSearch.php     (quick search)
│   ├── Sitemap/
│   │   ├── BlogSitemapProvider.php
│   │   └── BlogCategorySitemapProvider.php
│   ├── Services/
│   │   └── BlogMediaUsageChecker.php
│   └── Http/
│       ├── Controllers/
│       │   ├── BlogController.php          (frontend, preview)
│       │   ├── Admin/PostController.php
│       │   ├── Admin/CategoryController.php
│       │   ├── Admin/SettingsController.php
│       │   ├── Api/PostController.php
│       │   └── Api/CategoryController.php
│       └── Resources/
│           ├── PostResource.php
│           └── CategoryResource.php
└── views/
    ├── frontend/
    │   ├── index.blade.php
    │   ├── show.blade.php
    │   ├── feed.blade.php
    │   └── partials/preview-banner.blade.php
    └── admin/
        ├── index.blade.php
        ├── create.blade.php
        ├── edit.blade.php
        ├── settings.blade.php
        ├── partials/
        │   ├── post-form.blade.php
        │   └── dashboard-widget.blade.php
        └── categories/
            ├── index.blade.php
            ├── create.blade.php
            └── edit.blade.php
```

---

## :page_facing_up: Requirements

- **Inverys CMS** 1.0 or newer
- **PHP** >= 8.4

## :handshake: Author

**Tom Kilimann** — [tkilimann@icloud.com](mailto:tkilimann@icloud.com)

## :scroll: License

MIT
