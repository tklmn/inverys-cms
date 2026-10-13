# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-10-13

First public release of the Blog plugin for Inverys CMS.

### Added

- **Posts** with the TinyMCE editor: create, edit, publish, draft and schedule; featured posts and featured images from the media library
- **Categories** with slug and description; categories that still contain posts cannot be deleted
- **Preview** of drafts and scheduled posts for users who may edit posts, with a notice bar, `noindex` and `no-store`; everyone else gets a 404
- **Revisions:** earlier versions of a post below its form — compare, preview and restore, as for pages; each language keeps its own history
- **Frontend:** post index as list or grid with a category chip bar, the newest post as a lead, reading time, breadcrumbs and previous/next navigation
- **RSS feed** at `/{prefix}/feed`
- **Sitemap:** the blog index, published posts and categories in `/sitemap.xml`, each group with its own switch under *Settings → SEO*
- **REST API:** public read endpoints and protected write endpoints for posts and categories, with the same publish and schedule permissions as the backend
- **Search:** published posts in the website search; posts and categories in the admin quick search, and the settings page found by topic
- **Multilingual:** with the Multilingual plugin, title, slug, excerpt, content and SEO fields per language with translated addresses and `hreflang` alternates
- **Dashboard:** post count, a *Create post* quick action and a widget with the latest posts
- **Settings:** URL prefix (checked against existing pages and reserved addresses), posts per page, columns, sorting and the elements shown; the blog menu entry follows a change of the prefix
- **Trash integration** for deleted posts, and a warning before an image used as a featured image is deleted
- **Permissions** for posts and categories (view, create, edit, delete, publish, schedule) and settings; editors manage all blog content, the settings are admin only
- Demo posts that `cms:demo-refresh` keeps up to date
- English and German translations; installable bundled, via ZIP or via Composer (`inverys/blog`)
