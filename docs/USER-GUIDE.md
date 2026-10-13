# 📘 Inverys CMS — User Guide

A comprehensive guide for content editors and administrators using **Inverys CMS**, built on Laravel 13. This guide covers installation, the admin dashboard, page and content management, the page builder, blog, media library, user management, and site configuration.

---

## 📑 Table of Contents

- [🚀 Installation](#-installation)
  - [Prerequisites](#prerequisites)
  - [Method 1: Clone, Install, Serve](#method-1-clone-install-serve)
  - [Method 2: Shared Hosting Upload](#method-2-shared-hosting-upload)
  - [Method 3: Pre-Boot Bootstrap Page](#method-3-pre-boot-bootstrap-page)
  - [Manual Setup (CLI)](#manual-setup-cli)
  - [Web Installer Wizard](#-web-installer-wizard)
  - [Security After Installation](#-security-after-installation)
  - [Refreshing the Demo Content](#-refreshing-the-demo-content)
- [📊 Dashboard Overview](#-dashboard-overview)
  - [Customizable Layout](#customizable-layout)
  - [Default Widgets](#default-widgets)
  - [Notifications](#notifications)
  - [Quick Search (Ctrl K)](#quick-search-ctrl-k)
  - [Sidebar Navigation](#sidebar-navigation)
- [📄 Managing Pages](#-managing-pages)
  - [Creating a Page](#creating-a-page)
  - [The Page Editor](#the-page-editor)
  - [Undo, Redo and Keyboard Shortcuts](#undo-redo-and-keyboard-shortcuts)
  - [Working in the Preview](#working-in-the-preview)
  - [Editing a Page](#editing-a-page)
  - [Translating a Page](#translating-a-page)
  - [Deleting a Page](#deleting-a-page)
  - [Reordering Pages (Drag-and-Drop Tree)](#reordering-pages-drag-and-drop-tree)
  - [Page Templates](#page-templates)
  - [Page Categories](#page-categories)
  - [Parent/Child Hierarchy](#parentchild-hierarchy)
- [🧱 The Page Builder](#-the-page-builder)
  - [Block Types](#block-types)
  - [Adding, Moving and Nesting Blocks](#adding-moving-and-nesting-blocks)
  - [Block Settings](#block-settings)
  - [Accessibility](#accessibility)
  - [Auto-Save](#auto-save)
  - [Revisions](#revisions)
- [✍️ Blog](#️-blog)
  - [Creating and Editing Posts](#creating-and-editing-posts)
  - [Categories](#categories)
  - [Blog Settings](#blog-settings)
  - [RSS Feed](#rss-feed)
- [🖼️ Media Library](#️-media-library)
  - [Uploading Files](#uploading-files)
  - [Responsive images](#responsive-images)
  - [Focal point](#focal-point)
  - [Browsing and Searching](#browsing-and-searching)
  - [Global Picker Modal](#global-picker-modal)
- [👥 User Management](#-user-management)
  - [Creating Users](#creating-users)
  - [Editing and Deactivating Users](#editing-and-deactivating-users)
  - [Deleting Users](#deleting-users)
  - [Roles and Permissions](#-roles-and-permissions)
- [⚙️ Site Settings](#️-site-settings)
  - [Where is what configured](#where-is-what-configured)
  - [Precedence: profile before site default](#precedence-profile-before-site-default)
  - [Lean page source](#lean-page-source)
  - [Email addresses and spam bots](#email-addresses-and-spam-bots)
- [🌐 Languages](#-languages)
- [🎨 Theme Management](#-theme-management)
  - [Switching Themes](#switching-themes)
  - [Customizing a Theme](#customizing-a-theme)
  - [Theme File Editor](#theme-file-editor)
  - [Uploading Themes](#uploading-themes)
  - [Installing Themes via Composer](#installing-themes-via-composer)
- [🔌 Plugin Management](#-plugin-management)
  - [Enabling and Disabling Plugins](#enabling-and-disabling-plugins)
  - [Uploading Plugins](#uploading-plugins)
  - [Installing Plugins via Composer](#installing-plugins-via-composer)
  - [Plugin Settings](#plugin-settings)
  - [Updates](#updates)
  - [Updating the CMS](#updating-the-cms)
- [📋 Form Builder](#-form-builder)
- [📜 Activity Log](#-activity-log)
- [🗑️ Trash Management](#️-trash-management)
- [🔔 Notifications](#-notifications)
- [📧 Mail Providers](#-mail-providers)
- [🔍 SEO Settings](#-seo-settings)
- [🔎 Search](#-search)
- [🛡️ Security Settings](#️-security-settings)
- [🖥️ System Information](#️-system-information)
  - [Components (SBOM)](#components-sbom)

---

## 🚀 Installation

Inverys CMS provides three methods to get running via the web installer, plus a fully manual CLI option.

### Prerequisites

| Requirement | Details |
|:------------|:--------|
| **PHP** | >= 8.4 with extensions: PDO, Mbstring, OpenSSL, Tokenizer, Ctype, JSON, Fileinfo, GD (thumbnails) and DOM — the installer checks them |
| **Composer** | 2.x (not needed on the server with the release package) |
| **Node.js** | 20.19+ or 22.12+, only for building the frontend assets (not needed with the release package) |
| **Database** | SQLite, MySQL/MariaDB, or PostgreSQL |

---

### Method 1: Clone, Install, Serve

The standard local development method. Run all dependencies locally and start the built-in PHP server.

```bash
git clone <repo-url> inverys-cms && cd inverys-cms
composer install && npm install && npm run build
php artisan serve
```

Visit `http://localhost:8000`. You are automatically redirected to `/install`, the web installer wizard. See [Web Installer Wizard](#-web-installer-wizard) below for details.

---

### Method 2: Shared Hosting Upload

Use this method when you do not have terminal access on your web host.

1. **Get a ready-to-run copy** — either download `inverys-cms-<version>.zip` from the GitHub release (it already contains `vendor/` and the built assets), or build one locally:
   ```bash
   git clone <repo-url> inverys-cms && cd inverys-cms
   composer install && npm install && npm run build
   ```
2. **Upload** all files to your web host (e.g. via FTP), including the `vendor/` and `public/build/` directories.
3. **Point your domain** to the `public/` directory. If the host does not allow that, the `.htaccess` in the project folder (Apache) serves only `public/` and blocks everything else, such as `.env` and the logs.
4. **Visit your domain.** The installer automatically creates the `.env` file and generates `APP_KEY` on first visit, then redirects you to the web installer wizard.

FTP clients often leave out empty folders and symbolic links. The CMS recreates missing `storage/` folders on the next request and, once installed, the `public/storage` link as well (relative, so it survives a move to another folder or server; not on Windows servers). Pages render and uploaded images show without SSH access. An existing `public/storage` folder is left alone.

> **💡 Tip:** Make sure your hosting provider's PHP version meets the minimum requirements before uploading.

---

### Method 3: Pre-Boot Bootstrap Page

If you upload the source files to your server **without** running `composer install` first, Inverys CMS detects that the `vendor/` directory is missing. Instead of showing an error, it serves a pre-boot bootstrap page.

This page provides a single-click interface that:

1. Runs `composer install --no-dev --optimize-autoloader` on the server.
2. Detects whether built frontend assets are missing and, if Node.js/npm is available, runs `npm install && npm run build`.
3. Reports success or failure for each step in real time.

Once the bootstrap completes, the browser redirects to the web installer wizard. The bootstrap page supports the same language switcher as the installer (English plus every bundled language pack, e.g. German).

> **📌 Note:** This method requires that your server's PHP user can execute `composer` (either globally or via a `composer.phar` file in the project root).

---

### Manual Setup (CLI)

If you prefer full control, skip the web installer entirely:

```bash
git clone <repo-url> inverys-cms && cd inverys-cms
composer install && npm install
cp .env.example .env
php artisan key:generate
```

Edit `.env` and configure your database connection (`DB_*` variables), then:

```bash
php artisan migrate --seed
php artisan storage:link
npm run build
php artisan serve
```

> **💡 Tip:** The `--seed` option runs all seeders: roles and permissions, default settings, page categories and the sample pages (with their demo images), and it enables the bundled system plugins (blog, forms, gallery, mail providers, languages …). A default admin account (`admin@example.com`) is created by `DefaultAdminUserSeeder` with a **random password printed once to the console** — copy it from the output. Outside `APP_ENV=production` it also creates demo users (see [Security After Installation](#-security-after-installation)).

---

### 🧙 Web Installer Wizard

Regardless of which method you used to reach it, the web installer wizard at `/install` walks you through five steps:

| Step | What It Does |
|:-----|:-------------|
| **1. System Requirements** | Verifies PHP version, required extensions, and directory permissions (`storage/app`, `storage/framework`, `storage/logs`, `bootstrap/cache`, `.env`). |
| **2. Database Configuration** | Choose your database driver (SQLite, MySQL/MariaDB, or PostgreSQL). SQLite needs no configuration. For the others, enter the server details and click **Connect & Load Databases**, then pick an **empty** database (create one on your server first). |
| **3. Site Settings** | Configure your site name, URL, and language (English or a bundled language pack such as German). Select optional demo content (see below). |
| **4. Admin Account** | Create the first administrator account with first and last name, username, email, and password (at least 10 characters with upper and lower case, a number and a special character). |
| **5. Installation** | Each step executes sequentially with live progress feedback. |

#### Demo Content Options (Step 3)

| Option | Description |
|:-------|:------------|
| **Sample pages** | Home, About, Contact, Services, and more — with their demo images in the media library. |
| **Page categories** | General, Blog, Documentation, etc. |
| **Blog demo posts** | Sample blog articles with featured images and categories. |

Without sample pages the site still starts with a ready home page and a footer that only links to pages that exist.

> **📌 Note:** The core system (roles, permissions, settings and the bundled system plugins such as blog, forms, gallery, mail providers and languages) is always installed, regardless of your demo content selections. The installer writes `APP_ENV=production` and creates no demo users.

#### Installation Progress (Step 5)

The final step executes these operations in sequence:

- ✅ Writing environment configuration (`.env`)
- ✅ Generating application key
- ✅ Setting up database (migrations, roles, permissions, settings, system plugins, home page)
- ✅ Creating the administrator account
- ✅ Installing the selected demo content (sample pages, page categories, blog demo content)
- ✅ Linking the storage directory
- ✅ Finalizing

Each step shows a spinner while running and a checkmark when complete. If the browser reloads during installation, the wizard **resumes from the last completed step**. If a step fails, a retry button appears.

After installation, a button leads you to the login page.

### 🔐 Security After Installation

Before putting the site into production, review the accounts that exist:

- **Your admin account** — created in Step 4 of the web installer from the e-mail and password *you* entered. There is **no hard-coded default admin** on the web-installer path, so nothing needs to be changed here.
- **Demo users** — the web installer never creates them. Only `php artisan migrate --seed` in an environment other than `APP_ENV=production` creates `editor@example.com`, `user@example.com`, and `blocked@example.com`, all with the shared password **`password`**. These are for local development only. If you ever seeded them (e.g. in a non-production environment that later goes live), **change their passwords or delete the accounts** before exposing the site.
- **Manual / `db:seed` installs** — running `php artisan migrate --seed` (instead of the web installer) creates a default admin `admin@example.com` via `DefaultAdminUserSeeder`. Its password is **randomly generated and printed once to the console** during seeding — copy it from the output, because it is only stored hashed and cannot be shown again (if you miss it, reset it via `php artisan tinker` or the password-reset flow).

> **⚠️ Never expose a site to the internet while any account still uses the `password` default.**

For the server side — HTTPS, reverse proxies, trusted host names, file permissions, backups, `APP_KEY` rotation and updates — follow the [Security Hardening Guide](SECURITY-HARDENING.md).

### 🔄 Refreshing the Demo Content

After an update you can bring the demo pages of an existing installation up to date:

```bash
php artisan cms:demo-refresh
```

It replaces the blocks of the demo pages (home, about, services, contact, FAQ, features, documentation …), creates missing demo subpages (such as the individual feature pages below **Features**), renews the demo illustrations, brings the texts of the demo blog posts up to date and lets installed language packs add their translations again. A revision of every page and post is created first, so your own changes can be restored under **Revisions**. Other pages and posts are not touched, and demo posts you deleted are not created again.

---

## 📊 Dashboard Overview

After logging in, you land on the admin dashboard at `/backend`. The dashboard gives you an at-a-glance overview of your site and quick access to all CMS features.

### Customizable Layout

The dashboard uses a **row-based layout builder**. Each user can customize their own dashboard layout:

1. Click **Customize** in the top-right corner to enter edit mode.
2. **Rows** — Add rows with predefined column layouts (100%, 50/50, 33/33/33, 25/25/25/25, 66/33, 33/66). Drag rows to reorder them. Switch a row's layout using the icons in the row bar.
3. **Widgets** — Drag widgets between columns in any row. Each widget has a width selector (25%, 33%, 50%, 66%, 100% of its column). Remove widgets or add them back from the widget picker.
4. **Spacers** — Insert invisible spacer widgets to control positioning. Spacers are always available and can be added multiple times.
5. Click **Done** to save. Click the reset button to return to the default layout.

Widgets that a later release adds to the default layout are not pushed into a layout you have already saved — they appear in the widget picker instead.

Layout changes are saved per user and persist across sessions.

### Default Widgets

| Widget | Description |
|:-----|:------------|
| **Dashboard** (welcome) | Greeting banner with user name. |
| **Statistics** | Stat cards for pages, media, users, and plugin metrics (e.g. blog posts, gallery albums). Each card links to its management page. |
| **Recent Pages** | Last 5 edited pages with status and timestamp. |
| **Quick Actions** | Shortcuts to create pages, upload media, and view the site. Plugin actions are included automatically. |
| **System Information** | PHP, Laravel, and CMS version with a link to the full system info page. Only for users with `show-system`. |
| **System & Security** | CMS version, number of installed components, enabled plugins and PHP version, with a link to [Components (SBOM)](#components-sbom) and an SBOM download. Warns when search engines are blocked, debug mode is on, or the site address uses `http://` in production; suggests setting up a `security.txt` when none is configured. Only for users with `show-system`. |
| **Notifications** | Recent notifications (HTTP errors, failed logins, updates) with unread badge. |

The **Media** widget (last 5 uploaded files with thumbnail, size, and timestamp) is not part of the default layout: add it from the widget picker under **Customize**. Dashboards that already show it keep it.

Plugins can register additional widgets (e.g. the Activity Log plugin adds a recent activity feed, the Blog plugin shows recent posts). See [Plugin Development](PLUGIN-DEVELOPMENT.md) for details.

### Notifications

The header bar includes a **bell icon** with a badge showing the number of unread notifications. Click it to see a dropdown preview of recent notifications. Notifications alert you to events such as HTTP errors, failed login attempts, changed log files, and available updates. See [Notifications](#-notifications) for details.

### Quick Search (Ctrl K)

The search field in the header — or <kbd>Ctrl</kbd> + <kbd>K</kbd> (<kbd>⌘</kbd> + <kbd>K</kbd> on a Mac) anywhere in the admin area — searches the whole system:

- **Navigation** — every menu item, instantly.
- **Settings** — every settings page by its topics: *smtp* opens Mail Providers, *password* or *2FA* the security settings, *sitemap* the SEO settings; single settings such as *time zone* lead straight to their entry. English and German terms both work.
- **Content** — from two characters: pages (including inactive ones), media, users, blog posts and categories, forms and gallery albums. Results are grouped and open the editor.

You only see what you may open; plugins add their own content. Use the arrow keys to choose and <kbd>Enter</kbd> to open.

### Sidebar Navigation

The left sidebar is organized into sections. On desktop, the sidebar can be **collapsed** to icon-only mode by clicking the toggle button at the bottom of the sidebar. The collapsed/expanded state is saved automatically and persists across page loads. When collapsed, hovering over a nav icon shows a tooltip with the item label. Each item only appears for users with the matching permission, and plugin items only while the plugin is enabled.

**Dashboard** sits at the top, followed by these sections:

#### 📝 Content

| Item | Description |
|:-----|:------------|
| **Pages** | Create, edit, and organize your site's pages (with the tree view and page categories). |
| **Media Library** | Upload and manage images, documents, and other files. |
| **Gallery** | Image albums (Gallery plugin). |
| **Posts** | Blog posts and categories (Blog plugin). |
| **Forms** | Forms and their submissions (Form Builder plugin). |
| **Trash** | Restore or permanently delete soft-deleted content. |

#### 👥 User Management

| Item | Description |
|:-----|:------------|
| **Users** | Manage user accounts. |
| **Manage Roles** | Create roles and assign permissions to them. |
| **Permissions** | List and manage permissions. |

#### 🛠️ System

| Item | Description |
|:-----|:------------|
| **Settings** | A group with **General**, **Search**, **Security**, **SEO** and **API Tokens**. |
| **Languages** | Default language, language packs, translations of site texts (Multilingual plugin). |
| **Mail Providers** | Configure outgoing email (Mail Providers plugin). |
| **Logs** | View, download, and clear the application log files. |
| **Activity Log** | A history of actions performed in the admin panel (Activity Log plugin). |
| **Updates** | New versions of the CMS itself: update from the release, upload a package by hand, go back to the previous version (admins). |
| **System Info** | View PHP version, extensions, and clear application cache; the **Components (SBOM)** tab lists every installed package, plugin and theme. |

#### 🧩 Extensions

| Item | Description |
|:-----|:------------|
| **Themes** | Switch and customize the frontend theme. |
| **Plugins** | Enable, disable, and configure plugins. |
| **Cookie Consent** | Consent banner and external services (Cookie Consent plugin). |

Notifications have no sidebar entry — open them with the bell in the header (see [Notifications](#-notifications)).

---

## 📄 Managing Pages

Pages are the primary content type in Inverys CMS. They support a hierarchical parent/child structure, per-page SEO settings, category assignment and template selection. Every page is built from blocks in one editor — there is no separate HTML mode.

### Creating a Page

1. Navigate to **Content > Pages** in the sidebar.
2. Click **Add New Page**. A dialog opens.
3. Enter a **Title**, optionally choose a **Parent Page**, and pick how the page starts:

   | Start with | Description |
   |:-----------|:------------|
   | **Blank** | An empty page — add blocks as you like. |
   | **Text page** | One text block, e.g. for legal notices or articles. |
   | **Landing page** | A stage with headline and button, plus three feature cards. |

4. Click **Create and open editor**. The page is created as a **draft** and opens in the editor. It only appears on the website once you switch **Is Active** on (tab **Page**).

> **📌 Note:** Opening `backend/pages/create` directly (e.g. from the dashboard) shows the same form as a page.

### The Page Editor

The editor fills the whole window:

| Area | Description |
|:-----|:------------|
| **Top bar** | Back to the page list, title and status (*Online* / *Draft*), the save state of the blocks, preview width (desktop, tablet, mobile), language menu (with the Multilingual plugin, see [Translating a Page](#translating-a-page)), **View** (opens the live page; greyed out while the page is offline) and **Save**. |
| **Structure** *(left)* | All blocks of the page as a tree. Click a block to edit it, drag the handle to move it, collapse containers. **Add block** opens a dialog with search, categories and your recently used blocks; inside containers and columns the **+ Add block** buttons add to that spot. |
| **Preview** *(center)* | The page exactly as the website shows it — with your theme, header and footer, even as a draft. It reloads after every change without jumping. Links in the preview do not navigate away. |
| **Sidebar** *(right)* | **Block** — the settings of the selected block, with move, duplicate and delete. **Page** — title, address, parent, status, navigation, SEO, design and categories in collapsible sections. **Revisions** — the version history. |

The columns can be hidden with the two panel buttons in the top bar. On small screens one area is shown at a time — switch between **Structure**, **Preview** and **Settings** below the top bar.

**Saving:** Blocks save themselves on every change; the top bar shows *Saved* with the time. Page settings (tab **Page**) are saved with **Save** or <kbd>Ctrl</kbd>/<kbd>⌘</kbd> + <kbd>S</kbd>. If page settings have unsaved changes, the save button shows a dot and the browser asks before you leave the page.

### Undo, Redo and Keyboard Shortcuts

**Undo** and **Redo** sit in the top bar. Every action is one step: adding, moving, duplicating or deleting a block, or a change in its settings. Typing counts as one step after a short pause, not letter by letter. Undo also restores the block that was selected.

| Shortcut | Action |
|:---------|:-------|
| <kbd>Ctrl</kbd> + <kbd>Z</kbd> | Undo |
| <kbd>Ctrl</kbd> + <kbd>Shift</kbd> + <kbd>Z</kbd> / <kbd>Ctrl</kbd> + <kbd>Y</kbd> | Redo |
| <kbd>Ctrl</kbd> + <kbd>S</kbd> | Save page settings |
| <kbd>Ctrl</kbd> + <kbd>D</kbd> | Duplicate the selected block |
| <kbd>Del</kbd> | Delete the selected block. A confirmation follows: <kbd>Enter</kbd> deletes, <kbd>Esc</kbd> cancels. |
| <kbd>Alt</kbd> + <kbd>↑</kbd> / <kbd>↓</kbd> | Move the selected block up or down |
| <kbd>Esc</kbd> | Deselect the block |
| <kbd>?</kbd> | Show the overview of all shortcuts (also the keyboard button in the top bar) |

The shortcuts also work while the preview has focus. Inside a text field, <kbd>Ctrl</kbd> + <kbd>Z</kbd> undoes the typing in that field. On a Mac, use <kbd>⌘</kbd> instead of <kbd>Ctrl</kbd>.

### Working in the Preview

- **Select:** click a block in the preview. Its frame shows the block name, and the structure and sidebar follow.
- **Edit text in place:** click the text of the selected block once more, or double-click it. <kbd>Enter</kbd> or <kbd>Esc</kbd> ends editing.
- **Toolbar:** the selected block has a toolbar to drag, move up or down, add a block below, duplicate and delete.
- **Drag:** hold the grip in the toolbar and move the block. A teal line shows where it will land, also inside other containers. Below the last block or above the first one it moves to the end or the start of the page. <kbd>Esc</kbd> cancels while dragging.

### Editing a Page

1. Navigate to **Content > Pages**.
2. Click the page (or the **Edit** button) to open it in the editor.
3. Change blocks in the structure and sidebar, and page settings in the **Page** tab.
4. Click **Save** for page settings; blocks are already saved.

### Translating a Page

With the Multilingual plugin, every editor has a language menu in the top bar
(the same icon as the language menu on the website). It lists all installed
languages and shows their state for this item: *Original*, *translated*,
*copied from …* or *not translated yet*. A dot on the button means the version
you are editing is not translated yet.

1. Open the page and choose the language in the menu, e.g. **Français**.
2. The page builder starts with the blocks of the original. Overwrite the texts
   in the preview or the structure. Fields such as the title stay empty and show
   the original as placeholder. Greyed-out fields apply to all languages.
3. **Don't start from scratch:** if the page already has another translation
   (e.g. German), the editor offers **Copy from Deutsch**. It copies all texts
   except the address. The address is created from the translated title when
   you save.
4. Click **Save**. Until then, a copied version is marked *copied, not
   translated yet* — in the editor, in the language menu and with a dashed
   language tag in the lists.

Visitors never see an empty page: fields without a translation show the default
language, then the original.

**A whole new language at once:** under **System > Languages > Prefill a
language**, choose *Copy from* (e.g. German) and *Into* (e.g. French). All
pages, posts, menus, forms, media, theme texts and site texts are copied as a
starting point. Fields already translated stay untouched and addresses are not
copied. From the command line: `php artisan multilingual:copy de fr`.

### Deleting a Page

1. Navigate to **Content > Pages**.
2. Click the **Delete** button (trash icon) next to the page.
3. Confirm the deletion in the modal dialog.

> **💡 Tip:** Deleted pages are soft-deleted and moved to the [Trash](#️-trash-management), where they can be restored or permanently removed. Deleting a parent page does not automatically delete its children; child pages become top-level pages.

### Reordering Pages (Drag-and-Drop Tree)

Pages can be reordered using the drag-and-drop tree view.

1. Navigate to **Content > Pages** and click **Tree View**.
2. The tree displays all pages in their hierarchical structure.
3. Drag a page to reorder it within its current level, or drag it onto another page to nest it as a child.
4. The new order and hierarchy are saved automatically when you drop the page.

> **📌 Note:** The page order determines the display sequence in navigation menus and subpage listings on the frontend.

### Page Templates

Every page uses one of two templates:

| Template | Description |
|:---------|:------------|
| **Container** | Content is centered within a max-width wrapper with padding. This is the default and suits most pages. |
| **Full Width** | Content spans the entire viewport width with no max-width constraint. Use this for landing pages, image galleries, or layouts that rely on full-bleed sections. |

The template is selected when creating or editing a page and can be changed at any time.

### Page Categories

Page categories provide a way to group and organize pages by topic or purpose.

1. Navigate to **Content > Pages** and click **Page Categories**.
2. **Create** a new category with a name and an optional description.
3. **Edit** or **Delete** existing categories.
4. When editing a page, assign it to one or more categories in the **Page** tab of the editor.

> **📌 Note:** Categories are separate from the blog category system and apply only to pages.

### Parent/Child Hierarchy

Pages support unlimited nesting depth. A child page's URL includes its parent's slug:

```
/services              (parent page)
/services/consulting   (child page)
/services/support      (child page)
```

Set the parent page when creating or editing a page using the **Parent Page** dropdown. To make a child page a top-level page, set the parent to "None." The `level` field is computed automatically from the parent relationship.

---

## 🧱 The Page Builder

The page builder is the editor for all page content: you compose pages from blocks. It offers **23 block types** across three categories: Layout, Content, and Interactive. Design is chosen from lists (background, alignment, size, icon) — no CSS classes needed. See [The Page Editor](#the-page-editor) for the layout of the editor itself.

### Block Types

#### 🏗️ Layout Blocks

These blocks define the structure and spacing of your page.

| Block | Description |
|:------|:------------|
| **Hero** | The big intro of a page: eyebrow, title, text, two buttons and an optional image. Layout (side by side or centered), background, height and title level (H1 for the page intro, H2 for a call to action further down). A page that starts with a hero shows no separate page title. |
| **Container** | A wrapper with configurable padding, max-width, background and text alignment. Full width it becomes a coloured band; narrower it becomes a rounded panel. Contains child blocks. |
| **Section** | A section with background, padding and text alignment. Contains child blocks. |
| **Columns** | A multi-column grid (1–6 columns) with adjustable gap spacing and vertical alignment (top or centered). Each column is a separate drop zone for child blocks. |
| **Spacer** | Adds configurable vertical space between blocks. Set the height in any CSS unit (e.g. `2rem`, `48px`). |
| **Divider** | A horizontal line separator with optional CSS class. |

#### 📝 Content Blocks

These blocks hold your page content.

| Block | Description |
|:------|:------------|
| **Heading** | A heading element (H1 through H6) with alignment, an optional eyebrow (small label above) and optional gradient text. |
| **Text** | Rich text content with a WYSIWYG editor. Text size (normal, introduction, small), alignment and line width (full width or readable). |
| **Key Figures** | Numbers with labels (e.g. „98 % — satisfied customers"), as cards or without frame. |
| **Pricing Table** | Plans with name, price, period, description, features (one per line) and a button; one plan can be highlighted with a badge. |
| **Logo Strip** | Customer or partner logos in a row — grey with colour on hover, or in colour; optionally linked. |
| **Image** | An image with source URL, alt text, caption, and width settings. Integrates with the media library picker. |
| **Video** | An embedded video with URL, optional poster image, autoplay toggle, and player controls toggle. |
| **Quote** | A blockquote with quote text, author attribution, and citation source URL. |
| **Code** | A code snippet block with syntax language selection (HTML, CSS, JavaScript, PHP, etc.). |
| **List** | An ordered or unordered list with individually editable items. |

#### ⚡ Interactive Blocks

These blocks add dynamic, engaging elements to your pages.

| Block | Description |
|:------|:------------|
| **Accordion** | Expandable/collapsible content sections. Add multiple accordion items, each with a title and nested child blocks. |
| **Tabs** | Tabbed content panels. Add multiple tabs, each with a title and nested child blocks. |
| **Button** | A call-to-action button with text, URL, style, size, position (left, center, right, full width), link target and an optional icon. |
| **Buttons** | Several buttons side by side (text, URL, style, icon), with size and position — wraps nicely on small screens. |
| **Link Group** | A collection of links displayed as a vertical list or inline group. Each link has text, URL, and target. |
| **Alert** | A styled notification box with four variants: info, success, warning, and danger. Includes a title and content body. |
| **Card** | A card with title, content, optional image or icon, optional link (with custom link text). Styles: with frame, tinted, without frame; icon soft or gradient; left or centered. |

> **📌 Note:** Blocks marked as "internal" (Column, Accordion Section, Tab) are created automatically when you add a Columns, Accordion, or Tabs block. You do not add them from the block dialog directly.

### Adding, Moving and Nesting Blocks

- **Add:** *Add block* at the end of the structure, or *+ Add block* inside a container, column, accordion section or tab. Type in the dialog to filter; <kbd>Enter</kbd> adds the first match.
- **Move:** drag the handle (⋮⋮) in the structure — also into and out of containers and columns — or use the arrows in the block's sidebar header.
- **Duplicate / delete:** in the block's sidebar header.

### Block Settings

1. Click a block in the structure.
2. Its settings open in the **Block** tab of the sidebar.
3. Change content, design and behaviour — the preview updates after saving (automatically, within a second).

**Design** — Most blocks have a *Design* group with lists instead of CSS classes:

| Option | Values |
|:-------|:-------|
| **Background** (container, section, hero) | none, light grey, tinted, glow, gradient (light text), dark (light text) |
| **Text alignment** | left, center, right |
| **Icon** (card, button) | a picker with 50+ icons |
| **Anchor** (container, section, hero) | a name such as `contact`; link to it with `#contact`, e.g. from a button |
| **Background image** (container, section, hero) | an image from the media library, darkened, lightened or tinted with the brand gradient so text stays readable |
| **Space between blocks** (container) | none, small, normal, large |
| **Split** (columns) | equal widths or e.g. ⅔ + ⅓ for two and ½ + ¼ + ¼ for three columns |
| **Image** | aspect ratio (16:9, 4:3, 1:1, 3:4), corners (rounded, round, square), optional link |
| **Card image** | cover image, round portrait (team) or whole image (logos, graphics) |
| **Quote** | optional photo next to the author |
| **Visibility** (all blocks, under *Advanced*) | everywhere, desktop and tablet only, mobile only |
| **Size / style** | per block, e.g. button size, card style, key figure style |

**Common settings across all blocks:**

- **Advanced: own CSS classes** — collapsed by default. Custom CSS classes (and, for containers and sections, a free background class) for fine-grained styling.

**Content-specific settings vary by block type:**

| Block | Settings |
|:------|:---------|
| **Heading** | Text, heading level (H1–H6), alignment |
| **Text** | Rich text content via WYSIWYG editor, size, alignment, line width |
| **Image** | Source URL (with media library picker), alt text, caption, width |
| **Video** | URL, title, poster image, autoplay, controls, captions, transcript |
| **Columns** | Number of columns (2–6), gap size |
| **Container** | Padding, max-width, full-width toggle, background, text alignment |
| **Hero** | Eyebrow, title, text, two buttons, image, layout, background, height, title level, grid pattern |
| **Key Figures** | Value and label per row, style, alignment, animate numbers (count up) |
| **Spacer** | Height value |
| **Accordion** | Allow multiple sections open simultaneously, heading level of the section titles; per section: open when the page loads |

### Accessibility

Every block has an **Accessibility** group in its settings. With these options an editor can make each page meet WCAG 2.2 AA without touching code; the **accessibility check** in the structure header lists what is still missing.

**Language of a block (all blocks).** Choose *Same as the page*, one of the installed languages or *Other language …* with a code such as `fr`, `en-GB` or `pt-BR`. The block is then marked with `lang`, so screen readers pronounce a foreign-language quote correctly. Invalid codes are rejected (the field shows an error, the website ignores them); nested blocks inherit the language of their container.

**Images.** Image, hero, card, logo strip and slider images each have an **alt text** and a **Decorative image** switch:

- Describe what the image shows — or, when it is a link, where the link leads.
- Mark purely decorative images as decorative; they get an empty `alt=""` and screen readers skip them.
- Background images of containers, sections and heroes are decorative by default. Switch that off and enter an alt text only when the image carries information the text does not repeat.
- Images picked from the media library take over its alt text and its *decorative* flag.
- Cards and slides without their own alt text keep using their title, as before; the check suggests a proper description.

**Links and buttons.** Buttons, the buttons block, link groups, hero buttons, card links, slide links, pricing buttons and logo links have a **Target** (*Same tab* / *New tab*). Links that open a new tab automatically announce *(opens in a new tab)* to screen readers. An optional **Accessible name** replaces the visible text for screen readers — use it for links like *Read more*, and start it with the visible words (e.g. *Read more about our pricing*). Content created before these options keeps its previous behaviour: external links in the buttons block and logo links still open in a new tab until you choose otherwise.

**Headings.** Cards, pricing plans, slide titles, alert titles and accordion sections have a **Heading level** (H2–H6), so the page outline has no gaps. Alert titles and accordion sections are not headings unless you choose a level; with a level, accordion buttons sit inside the heading and screen reader users can jump between them.

**Areas (landmarks).** A container or section can be rendered as a *Region (section)*, *Complementary (aside)* or *Navigation (nav)* landmark with a **Name of the area** (e.g. *Related articles*). Screen reader users jump between landmarks.

**Media.** The video block has a **Title** (announced to screen readers), **Captions** as a WebVTT file (URL or media library) with language and track name, and an optional **Transcript** — as text shown below the video in an expandable box, and/or as a link. While autoplay is on, the player controls are always shown so the video can be paused. For YouTube and Vimeo, switch captions on at the platform.

**Interactive blocks.**

| Block | What the options do |
|:------|:--------------------|
| **Slider** | Autoplay is off for new sliders. When it plays, a pause/play button is shown, rotation pauses on hover and keyboard focus, and it never starts by itself for visitors who prefer reduced motion. Hidden slides cannot be focused or read out; the slider is announced as a carousel with its **Name of the slider**. |
| **Tabs** | Optional **Name of the tab list**; arrow keys, <kbd>Home</kbd> and <kbd>End</kbd> move between tabs, and the panels can be reached with <kbd>Tab</kbd>. |
| **Accordion** | Panels are labelled by their section title; sections can open on page load; several may be open at once if allowed. |
| **Alert** | **Announcement**: *Status* (default, read out politely), *Note*, *Alert* (interrupts the screen reader — only for urgent messages) or *None*. |
| **Code** | The scroll area can be reached and scrolled with the keyboard; **Name for screen readers** (default: *Code example*). |
| **Divider** | **Decorative only** hides the line from screen readers; leave it off when the line separates two topics. |

**Key figures.** **Animate numbers (count up)** (in *Design*) makes the numbers count up from 0 when they scroll into view. The value is taken as written — `1,200`, `1.200`, `98 %`, `25+`, `4.9` keep their separators, prefix and suffix. The page always contains the final value: screen readers, search engines and visitors without JavaScript get the real number, and visitors who prefer reduced motion see no animation. Key figures are announced as a list.

#### The accessibility check

The shield button in the structure header (next to *Collapse all*) shows the number of open problems; click it to see the list. Clicking an entry selects the block, so you can fix it in the sidebar. The list updates while you edit. It reports, among others:

- images without alt text, and linked images whose link therefore has no name (also images inside text blocks);
- buttons and links without text, *Read more* / *Learn more* links without an accessible name;
- skipped heading levels (e.g. H2 followed by H4), more than one H1, or no H1 at all — the page title counts as the H1 when the theme shows it (every page except the home page, unless the page starts with a hero);
- videos without captions or title, sliders that play automatically, accordion sections and tabs without a title, invalid language codes, unnamed navigation/aside areas;
- hints (not counted) such as links that open a new tab and embedded videos whose captions are managed on YouTube or Vimeo.

The check covers what the block options control. Also review colour contrast of custom colours, link texts in your own words and the reading order in the preview.

### Auto-Save

Blocks save automatically on every change, and the preview reloads afterwards. Page settings (title, address, SEO, design …) are saved with **Save** — the top bar reminds you when some are unsaved.

### Revisions

The CMS keeps earlier versions of every page automatically, in the **Revisions** tab of the editor sidebar:

- **One revision per editing session.** The page builder saves on every change; all saves by the same person within 10 minutes form one revision (`CMS_REVISION_SESSION_MINUTES`, `0` = every save).
- **Translations too.** Each language has its own history; revisions of a translation carry the language code (e.g. **DE**). Restoring one changes only that language.
- **Find the right version.** Every entry says what was changed *afterwards* — e.g. *Afterwards: Hero changed · 2 blocks added*. Click an entry to compare.
- **Compare:** *With the current state* shows what restoring would change; *What changed afterwards* shows the next version. Blocks are listed as added, removed or changed — with their type and the text that differs.
- **Preview:** look at the page as it was (original language), in a new tab, without changing anything.
- **Restore:** asks first in a dialog that names the date you go back to, and saves the current state as a revision (*Before restore*), so you can always go back.
- **Delete** a single revision from the **…** menu.

Blog posts keep revisions the same way — see **Revisions** below the post form. Plugins can offer them for their content too.

The maximum number of revisions per page or post is set under **System > Settings > General > Revisions per entry** (`revisions.max_per_model`, default: 50; `0` keeps all). When the limit is reached, the oldest revisions are pruned automatically.

---

## ✍️ Blog

The blog is provided by the **Blog plugin**. When enabled, it adds a full-featured blogging system with posts, categories, an RSS feed, and configurable display settings.

### Creating and Editing Posts

1. Navigate to **Content > Posts** in the sidebar (visible when the Blog plugin is enabled).
2. Click **Create Post** to create a new post, or click **Edit** on an existing post.
3. Fill in the post fields:

   | Field | Description |
   |:------|:------------|
   | **Title** | The post headline. |
   | **Slug** | URL-friendly identifier, auto-generated from the title. |
   | **Content** | The post body, written in a rich text (WYSIWYG) editor. |
   | **Excerpt** | A short summary displayed on the blog listing page. |
   | **Featured Image** | A header image for the post, selected via the media library picker. |
   | **Categories** | Assign one or more blog categories. |
   | **Status** | Choose the post status (see below). |
   | **Published Date** | Set or override the publication date (required for scheduled posts). |
   | **Meta Title** | SEO title for the post. |
   | **Meta Description** | SEO description for the post. |

4. Click **Save**.

#### Post Statuses

| Status | Behavior |
|:-------|:---------|
| **Published** | Immediately visible on the frontend. Sets `published_at` to now if not already set. |
| **Draft** | Saved but not visible on the frontend. |
| **Scheduled** | Set a future `published_at` date. The post becomes visible automatically when that date arrives. |

> **📌 Note:** Post status changes are permission-controlled. The `publish-post`, `schedule-post`, and `draft-post` permissions determine which statuses a user can set.

#### Revisions

Below the form of an existing post, **Revisions** lists its earlier versions — as for pages (see [Revisions](#revisions)): what was changed afterwards, compare, preview the post as it was, restore (the current state is kept as *Before restore*) and delete.

### Categories

Blog categories help organize posts by topic.

1. Navigate to **Content > Posts** and click **Categories**.
2. **Create** a category with a name, slug, and optional description.
3. **Edit** or **Delete** existing categories.
4. Assign categories to posts when creating or editing a post.

Category archive pages are available at `/{blog-prefix}/category/{slug}` on the frontend.

### Blog Settings

Admins can configure blog display options:

1. Navigate to **Extensions > Plugins** and click the settings button of the **Blog** plugin (or type *blog* into the [quick search](#quick-search-ctrl-k)). The page is called **Blog Settings** and needs the `blog-settings` permission (admin only).
2. Available settings:

| Setting | Description |
|:--------|:------------|
| **URL Prefix** | Change the blog URL path (e.g. `blog`, `news`, `articles`). |
| **Posts per page** | Number of posts shown per listing page. |
| **Columns** | Number of columns in the blog listing grid (1–6). |
| **Sorting** | Sort posts by date, title, or creation date, ascending or descending. |
| **Show pagination** | Toggle pagination links on the listing page. |
| **Show author** | Display the author name on posts. |
| **Show date** | Display the publication date. |
| **Show categories** | Display category tags on posts. |
| **Show excerpt** | Display the excerpt on the listing page. |
| **Show featured image** | Display the featured image on posts. |
| **Show read more** | Toggle the "read more" link. Optionally set custom link text. |
| **Show breadcrumbs** | Display breadcrumbs on single post pages. |
| **Show prev/next** | Display previous/next post navigation on single post pages. |
| **Show sidebar** | Display the sidebar with categories and archive. |
| **Show RSS link** | Display a link to the RSS feed. |

> **💡 Tip:** Each setting has a **Reset** button to restore its default value. Use "Reset all to defaults" to restore everything at once.

### RSS Feed

An RSS feed of published posts is automatically available at `/{blog-prefix}/feed` (e.g. `/blog/feed`). The feed includes the post title, excerpt, author, publication date, and categories. Visibility of the RSS link in the frontend is controlled by the "Show RSS link" blog setting.

---

## 🖼️ Media Library

The media library manages all uploaded files — images, documents, videos — in one central location.

### Uploading Files

1. Navigate to **Content > Media Library** in the sidebar.
2. Click **Upload New** and choose a file or drop it into the upload area.
3. Files are automatically processed:
   - Images receive automatic thumbnail generation.
   - File metadata (size, MIME type, dimensions for images) is recorded.
   - Images get scaled copies for visitors' screens (see [Responsive images](#responsive-images)).
4. Add **Alt Text** for each image to improve accessibility and SEO.

> **💡 Tip:** Uploads are also supported via inline AJAX from other parts of the admin (e.g. the page builder image block, blog featured image picker).

### Responsive images

Visitors on a phone should not download a 4000-pixel photo. When you upload a
JPEG, PNG or WebP image (in the backend or through the API), the CMS stores its
width and height and creates scaled copies that are **480, 768, 1280 and 1920
pixels wide** — only the ones smaller than the original, nothing is enlarged.
The copies are written as **WebP** when the server's GD library supports it
(otherwise in the format of the original) and sit next to the original
(`media/2026/10/<file>-768w.webp`). Deleting an image permanently removes its
copies too.

On the website, images from the media library then come with:

- `srcset`/`sizes` — the browser picks the smallest copy that is sharp enough,
- `width`/`height` — the page does not jump while images load,
- `loading="lazy"` for images further down the page, and `loading="eager"` with
  `fetchpriority="high"` for the **first image of the page**: the header image
  of a page or blog post, otherwise the first block that shows an image (image,
  hero, card, logos, slider, gallery or a background image of a section,
  container or hero).

GIFs (animations would be lost), SVGs and images from other websites are shown
unchanged.

**Images in running text** (text and accordion blocks, page and blog post
bodies) get the same treatment when the page is shown: an image from the media
library inserted in the text editor comes with `srcset`/`sizes`,
`width`/`height`, `loading="lazy"` and `decoding="async"`. What you save stays
exactly as you wrote it — the attributes are added on display only. If you
gave the image a width in the editor, that width is kept (the height follows
from the aspect ratio) and the browser picks a copy that fits it. Images from
other websites are left as they are.

**Images uploaded before this feature** get their size and copies with one
command — safe to run several times; it only fills in what is missing and
changes nothing else:

```bash
php artisan media:variants          # all images that still lack size or copies
php artisan media:variants --force  # regenerate every copy (e.g. after changing the format)
php artisan media:variants --id=12  # a single image
```

Set `MEDIA_WEBP_VARIANTS=false` in `.env` to keep the copies in the format of
the original.

### Focal point

Many places on the website crop images to a fixed shape: an image block with
an aspect ratio, hero and card images, sliders, background images of sections,
containers and heroes, the gallery grid, blog featured images and the header
image. By default the crop keeps the **center**. If the important part of a
photo is elsewhere — a face at the top, a product on the left — set the
**focal point** on the image's edit page in the media library:

- **Click** (or drag) on the image; the marker moves there.
- **Keyboard:** select the marker with <kbd>Tab</kbd> and move it with the
  arrow keys (1 % per step, with <kbd>Shift</kbd> 10 %); <kbd>Home</kbd> /
  <kbd>End</kbd> jump to the left / right edge, <kbd>Page Up</kbd> /
  <kbd>Page Down</kbd> to the top / bottom. The current position is announced.
- **Number fields:** *Horizontal* and *Vertical* in percent from the left /
  top (0–100). Empty fields mean the center.
- **Crop preview:** square, portrait and wide versions show the result live.
- **Reset to center** empties both fields.

Save to apply. The focal point belongs to the image, not to a page: every
block that shows the image uses it, nothing has to be set in the page builder.
It applies to all languages (locked while editing a translation). Images shown
in full (logos, cards and galleries set to *contain*, images without a fixed
shape) are not affected. The files themselves are not cut — the browser crops
the image when it shows it.

### Browsing and Searching

| Feature | Description |
|:--------|:------------|
| **List** | Browse all files with thumbnails. |
| **Search and filter** | Use the search bar to find files by name, alt text or description, and the **Type** filter for images, documents, videos or audio. |
| **File details** | Click a file to view full details, copy its direct URL, edit its name, alternative text, description and [focal point](#focal-point), and see where it is **Used in** (page builder, header image, OG image). Deleting a file that is still in use shows a warning. |

### Global Picker Modal

The media library picker is a modal dialog that appears throughout the CMS wherever an image or file needs to be selected:

| Context | Usage |
|:--------|:------|
| **Page builder** | Image blocks use the media picker to select a source image. The captions field of the video block lists only caption files (WebVTT, `.vtt`). |
| **Blog posts** | The featured image field opens the media picker. |
| **Theme settings** | Image-type theme settings (logos, background images) use the media picker. |
| **User avatars** | Avatar uploads integrate with the media system. |

In the picker modal, you can browse existing uploads, search by name, or upload a new file on the spot. Selecting a file inserts its URL into the appropriate field.

> **📌 Note:** All media files are stored in `storage/app/public/` and served via the storage symlink (`public/storage/`).

---

## 👥 User Management

Inverys CMS includes a full user management system with role-based access control (RBAC).

### Creating Users

1. Navigate to **User Management > Users** in the sidebar.
2. Click **Create New User**.
3. Fill in the required fields:

   | Field | Description |
   |:------|:------------|
   | **Username** | A unique display name. |
   | **First Name** / **Last Name** | User's real name. |
   | **Email** | Must be unique. |
   | **Password** | With confirmation. |
   | **Roles** | Assign one or more roles. |

4. Fill in optional fields:

   | Field | Description |
   |:------|:------------|
   | **Phone**, **Job Title**, **Bio** | Additional profile information. |
   | **Avatar** | Upload a profile picture (max 2 MB). |
   | **Active** | Toggle whether the user account is active. |

5. Click **Save**.

### Editing and Deactivating Users

1. Navigate to **User Management > Users**.
2. Click **Edit** on any user to modify their name, email, roles, or other profile fields.
3. To deactivate a user, uncheck the **Active** checkbox and save. Deactivated users (`is_active = false`) cannot log in or access the backend.
4. To reactivate, check the **Active** checkbox again.

### Deleting Users

To delete a user, click the **Delete** button. The user is soft-deleted and moved to the trash.

> **⚠️ Warning:** The following deletion restrictions are enforced:
> - **You cannot delete your own account.**
> - **Admin users can only be deleted by other administrators.**
> - **The last remaining admin account is protected and cannot be deleted.**

---

### 🔐 Roles and Permissions

The CMS ships with four default roles:

| Role | Description | Key Permissions |
|:-----|:------------|:----------------|
| **Admin** | Full access to everything. | All permissions, including the ones plugins add. Code on the server (theme file editor, uploading or deleting themes and plugins, updates) is reserved for this role. |
| **Editor** | Content management. | Pages, page categories, media, trash and the content plugins (posts and blog categories, forms and submissions, albums) in full. No access to users, roles, permissions, settings, logs, system information, themes, plugins, updates, plugin settings or the API. |
| **User** | Frontend only. | Authenticated frontend visitor. No backend access. |
| **Blocked** | No access. | Holds no permissions. |

Additional roles can be created under **User Management > Manage Roles**. Permissions are granular and can be assigned per role; you can only grant permissions you hold yourself.

#### Core Permissions

| Category | Permissions |
|:---------|:------------|
| **Dashboard** | `access-dashboard` (needed for any backend access) |
| **Pages** | `show-page`, `create-page`, `edit-page`, `delete-page` |
| **Page categories** | `show-page-category`, `create-page-category`, `edit-page-category`, `delete-page-category` |
| **Media** | `show-media`, `create-media`, `edit-media`, `delete-media` |
| **Users** | `show-user`, `create-user`, `edit-user`, `delete-user` |
| **Roles** | `show-role`, `create-role`, `edit-role`, `delete-role` |
| **Permissions** | `show-permission`, `create-permission`, `edit-permission`, `delete-permission` |
| **Settings** | `show-settings`, `create-settings`, `edit-settings`, `delete-settings`, `manage-search-settings`, `manage-security-settings`, `manage-seo-settings` |
| **Notifications** | `show-notifications`, `delete-notifications`, `manage-notification-settings` |
| **System** | `show-system`, `show-logs`, `download-logs`, `clear-logs`, `manage-updates` |
| **Themes** | `manage-themes`, `edit-theme-files`, `install-themes` (marketplace) |
| **Plugins** | `manage-plugins`, `install-plugins` (marketplace) |
| **Marketplace** | `manage-marketplace` (sources, administrators only) |
| **Blocks** | `manage-blocks` |
| **Trash** | `manage-trash` |
| **API** | `api-access` |

Plugins add their own permissions, e.g. `mail-providers-settings`, `multilingual-settings` or the form and activity log permissions listed in their sections.

#### Blog Permissions *(when the Blog plugin is enabled)*

| Category | Permissions |
|:---------|:------------|
| **Posts** | `show-post`, `create-post`, `edit-post`, `delete-post` |
| **Workflow** | `publish-post`, `schedule-post`, `draft-post` |
| **Categories** | `show-blog-category`, `create-blog-category`, `edit-blog-category`, `delete-blog-category` |
| **Settings** | `blog-settings` (admin only) |

---

## ⚙️ Site Settings

Site-wide defaults are managed under **System > Settings > General**.
Every setting has exactly **one** place where it is edited. Settings that have
their own page still appear in the General list — locked, with a link to the
page that manages them — so you can see that they exist and where to find them.
All other settings pages are in the sidebar, and the
[quick search](#quick-search-ctrl-k) finds them by topic.

| Setting | Description |
|:--------|:------------|
| **Site name** | Shown in the browser tab, the navigation and emails. Translations: **System > Languages > Translate texts** (Multilingual plugin). |
| **Default language** | Language for visitors and for users without their own choice. With the Multilingual plugin it is set only under **System > Languages** (it also decides which language needs no prefix in the address) and is locked here. |
| **Time zone** | Time zone of the website; all times are stored in it. |
| **Date format** | PHP format such as `d.m.Y`. Empty: the format of the interface language. Visitors of a translated site always see the format of their language. |
| **Default appearance** | Light or dark for visitors and users without their own choice. Only takes effect on the website if the active theme supports dark mode. |
| **Active theme** | Locked here — activated under **Extensions > Themes**, which checks that the theme exists. |
| **Favicon** | Path relative to `public/`, e.g. `images/favicon.svg`. Applies to the backend and every theme. |
| **Revisions per entry** | Older revisions of a page or post beyond this number are deleted; `0` keeps all. Default: 50. |
| **Minify HTML** | On (default): pages are delivered minified — see [Lean page source](#lean-page-source). |
| **Protect email addresses** | On (default): email addresses on the website are hidden from spam bots — see [Email addresses and spam bots](#email-addresses-and-spam-bots). |

Values are validated per setting (e.g. only installed languages, real time
zones, `light`/`dark`). The REST endpoint `PUT /api/v1/settings/{key}` accepts
the same keys with the same rules; locked settings are refused there as well.

### Where is what configured

| What | Where | Notes |
|:-----|:------|:------|
| Site name, time zone, date format, default appearance, favicon, revisions, minify HTML, protect email addresses | **System > Settings > General** | Site defaults |
| Default language | **System > Settings > General**, or **System > Languages** when the Multilingual plugin is active | Base language of the content: set once at installation, shown under **Languages** |
| Active theme | **Extensions > Themes** | |
| Logo, colours, footer text, regions, custom CSS | **Extensions > Themes > Customize** (per theme) | Site name and favicon are *not* theme settings |
| Translations of site texts (site name, cookie banner …) | **System > Languages > Translate texts** | The original stays on its settings page |
| Search | **System > Settings > Search** | |
| Passwords, registration, sign-in, two-factor, sessions, security.txt | **System > Settings > Security** | security.txt: a value entered here wins over `SECURITY_TXT_*` in the `.env`; a fixed `SECURITY_TXT_EXPIRES` locks the validity period |
| Sitemap, robots.txt | **System > Settings > SEO** | |
| API tokens | **System > Settings > API Tokens** | Per user, needs `api-access` |
| Sending email, default sender | **System > Mail Providers** (Mail Providers plugin) | Empty fields fall back to `MAIL_*` in the `.env`; forms can set their own sender |
| Blog address (URL prefix) | **Blog Settings** (**Extensions > Plugins**, settings of the Blog plugin) | The “Blog” menu entry follows it; a prefix already used by a page is refused |
| Settings of other plugins | **Extensions > Plugins**, settings button of the plugin | |
| Which notifications you receive | **Notifications > Settings** (bell in the header, then **View all notifications**) | Per user; the page requires `manage-notification-settings` |
| Your language, time zone, date format, appearance, website theme | **Profile** | Per user, see precedence |

### Precedence: profile before site default

Language, time zone, date format, appearance (light/dark) and the website
theme can be chosen per user in the **Profile**. A personal choice wins over
the site default; **“Site default (…)”** or an empty field removes it, and the
user follows the website again — also when an administrator changes the
default later. The personal time zone changes only how dates are shown; times
are stored in the time zone of the website. The personal website theme is a
preview for that user while signed in; visitors always get the active theme.

> **📌 Note:** Settings are stored in the `settings` database table (personal ones in `user_settings`) and loaded at runtime. Changes take effect immediately.

### Lean page source

Every HTML page starts with a copyright notice right after the doctype (`<!-- Copyright © <year> Inverys · Inverys CMS -->`, the year updates itself). It is added to the finished response, so no theme or plugin can change or remove it — error pages included.

**System > Settings > General > Minify HTML** (on by default) delivers every page minified, for every theme and plugin: after the copyright notice the page source is a single line, without indentation, line breaks between tags or comments. Inline `<style>` and `<script>` are minified as well, with the bundled library [matthiasmullie/minify](https://github.com/matthiasmullie/minify) — it runs on your server, reads no files and contacts no service; results are cached per content. JSON in `<script type="application/json">` is compacted.

What stays exactly as it is: the contents of `pre` and `textarea`, attribute values (they can hold data, such as code with indentation), spaces inside text, and line breaks inside text, which `white-space: pre-line` shows. The page builder preview is never minified, so editing text directly in the preview saves exactly what is stored.

Icons appear once per page as an SVG sprite that all occurrences point to; in long backend lists this saves another quarter. Compression on the server (gzip or Brotli) shrinks the transfer much further and should be on as well.

### Email addresses and spam bots

**System > Settings > General > Protect email addresses** (on by default) hides every email address on the website from spam bots, for every theme and plugin:

- `mailto:` links carry the address only encoded (`data-cms-email`); a small script (`public/js/email-protection.js`) turns them back into normal links in the visitor's browser.
- Addresses in the text show the `@` through a style instead of the character itself; the script puts the real character back, so copying works.
- `title`, `aria-label` and `alt` read `(at)` until the script has run.

Visitors see and use the addresses as usual. Without JavaScript the addresses stay readable, but `mailto:` links do nothing. Only guests get the protected page: signed-in users get it unchanged, so the page builder never saves the protected form. The `<head>`, form fields (input values, `textarea`, `select`), `script`, `style`, SVG graphics and HTML comments are left alone.

---

## 🌐 Languages

With the **Multilingual** plugin (enabled on installation), content can be offered in several languages. Open **System > Languages** (permission `multilingual-settings`). The settings are on the left; *How it works* and *Add languages* are on the right.

| Area | Description |
|:-----|:------------|
| **Default language** | The original language of the content and the language visitors see without a language in the address. It needs no prefix; every other language gets its own (`/de/…`). It is also the default for the admin interface. |
| **Prefill a language** | Copies all translations of one language into another as a starting point (see [Translating a Page](#translating-a-page)). |
| **Add languages** | Lists the installed language packs with version and state. **Manage language packs** opens **Extensions > Plugins** filtered to language packs (filter *All plugins / Language packs*). A pack such as `language-de` adds its language to the editors and the language menu of the website once enabled. |
| **Translate texts** *(button at the top)* | Translations of texts from settings that visitors see — the site name, the cookie banner, the announcement bar and more. Empty fields show the original, which stays on its own settings page. |

Visitors never see an empty page: a missing translation falls back to the default language. How to translate pages, posts and other content is described in [Translating a Page](#translating-a-page).

---

## 🎨 Theme Management

Themes control the visual appearance of the frontend. Two themes are bundled with Inverys CMS:

| Theme | Description |
|:------|:------------|
| **Default** | A clean, modern design with primary color customization, breadcrumbs, and a configurable footer. |
| **Modern** | A bold, magazine-style theme with gradient accents, a hero header, centered navigation, and configurable content width. |

### Switching Themes

1. Navigate to **Extensions > Themes** in the sidebar.
2. Browse available themes with their screenshots and descriptions.
3. Click **Activate** on the theme you want to use.
4. The frontend immediately reflects the new theme.

### Customizing a Theme

1. Navigate to **Extensions > Themes**.
2. Click **Customize** on the active (or any installed) theme.
3. The customizer shows settings grouped by category. Available setting types vary by theme but typically include:

   | Setting Type | Examples |
   |:-------------|:---------|
   | **Color pickers** | Brand colors, gradient stops, background colors. |
   | **Image uploads** | Logos and background images via the media library picker. |
   | **Text fields** | Copyright text, taglines, and other textual content. |
   | **Toggles (booleans)** | Hero sections, breadcrumbs, scroll-to-top buttons, search visibility, profile/logout links. |
   | **Dropdowns (selects)** | Content width, font, corners, header style, footer style, dark mode background. |

   The **Default** theme offers: primary and accent colour, dark mode background (midnight blue, black, slate), font (Inter, system font, serif headings), corners (sharp, rounded, extra round), background glow, content width (narrow, normal, wide), header style (translucent, solid, scrolls away), footer style (dark, light) plus page title, breadcrumbs, subpages below the content (text pages only, always or never), search, profile and logout links, a logo for dark mode, an optional highlighted header button (text and link), and whether the login button and the light/dark switch are shown. The login pages use the same logo as the website.

4. Each setting has a **Reset** button to restore its theme-defined default value.

5. **Theme Regions** — Themes can define editable regions (such as footer content) that use the same block editor as the page builder. You can compose region content using all the same block types: headings, text, columns, link groups, and so on.

6. **Custom CSS** — Add custom CSS rules that are injected into the theme's frontend output. Use this for styling overrides that go beyond the theme's built-in settings.

7. Changes are saved per setting and take effect on the frontend immediately.

> **⚠️ Warning:** Deleting a theme's customizations removes all saved settings for that theme and moves them to the trash, where they can be restored if needed.

### Theme File Editor

Themes that support file editing show an **Edit Files** button on the theme card. This opens a built-in code editor where you can modify theme templates (Blade views, CSS, JSON, etc.) directly in the browser.

1. Navigate to **Extensions > Themes** and click **Edit Files** on the desired theme.
2. The left panel shows the theme's file tree. Click a file to load it.
3. Edit the file in the monospace text area. Use **Ctrl+S** (or **Cmd+S**) to save.
4. Changes take effect immediately — the view cache is cleared automatically.
5. If a file has been modified, a **Reset** button appears. Click it to restore the original version.

> **📌 Note:** The theme author controls which files are editable via the `editable` field in `theme.json`; otherwise the button is disabled. The editor writes code to the server, so it is reserved for the admin role (`edit-theme-files`), asks for your password again, and is switched off on a server with `CMS_CODE_EDITING=false` in the `.env`.

### Installing Themes from the Marketplace

**Extensions > Themes > Marketplace** lists the themes of the marketplace sources — catalogs that anyone can publish — with preview, description, version and where each comes from.

1. Click **Install** on a theme and confirm. The CMS downloads it, compares it with the checksum of the catalog and checks it like an uploaded ZIP.
2. Click **Activate** — on the marketplace card or in the list of installed themes.

A newer version shows an **Update** button on the card. **Refresh** fetches the catalogs again (they are otherwise kept for six hours).

> **📌 Note:** Installing needs the permission *Install themes*, asks for your password again and is switched off with `CMS_CODE_EDITING=false`. The permission can go to any role, e.g. moderators.

**Sources** (button at the top, administrators only) lists the catalog addresses: the ones set on the server (`CMS_MARKETPLACE_SOURCES`) and more you add, one per line. Add only sources you trust — their packages run as code on the server. How a catalog looks and how to run your own marketplace: [MARKETPLACE.md](MARKETPLACE.md).

### Uploading Themes

You can install third-party themes by uploading a ZIP file:

1. Navigate to **Extensions > Themes** in the sidebar.
2. Click **Upload Package** in the top-right corner.
3. Select a `.zip` file (max 20 MB) containing the theme.
4. Click **Install**. The system validates the package (manifest, slug format, security scan) and extracts it to `themes/`.
5. After upload, the theme appears in the list and can be activated.

> **📌 Note:** The ZIP file must contain a valid `theme.json` manifest at its root (or one directory level deep). See the [Theme Development Guide](THEME-DEVELOPMENT.md) for the required manifest format. Uploading and deleting themes needs the admin role and is switched off with `CMS_CODE_EDITING=false`. Uploading a newer version of an installed theme updates it (see [Updates](#updates)).

### Installing Themes via Composer

Themes distributed as Composer packages (type `inverys-theme`) can be installed directly:

```bash
composer require vendor/theme-name
```

The CMS automatically discovers the theme in the `vendor/` directory and lists it in **Extensions > Themes**.

---

## 🔌 Plugin Management

Plugins extend the CMS with additional features. They are managed under **Extensions > Plugins**.

### Enabling and Disabling Plugins

1. Navigate to **Extensions > Plugins** in the sidebar. Managing plugins (`manage-plugins`) is reserved for the admin role, and the page asks for your password again.
2. View all discovered plugins with their name, description, version, and status. The filter above the list switches between **All plugins** and **Language packs**.
3. Switch a plugin **on** to enable it. On first enable, the plugin runs its installation routine (creating database tables, registering permissions, inserting default settings, and adding sidebar navigation items).
4. Switch it **off** to disable it. Disabling removes its routes and sidebar items from the admin but **preserves all its data** in the database.

> **💡 Tip:** Disabling a plugin is non-destructive. All data is preserved and will be available again when you re-enable it.

### Installing Plugins from the Marketplace

**Extensions > Plugins > Marketplace** lists the plugins of the marketplace sources, for example the Announcement Bar. **Install** downloads the plugin, compares the checksum and checks it like an uploaded ZIP; it then appears switched off in the plugin list until an administrator switches it on. Installing needs the permission *Install plugins* (any role can get it), password confirmation and `CMS_CODE_EDITING`.

### Uploading Plugins

You can install third-party plugins by uploading a ZIP file:

1. Navigate to **Extensions > Plugins** in the sidebar.
2. Click **Upload Package** in the top-right corner.
3. Select a `.zip` file (max 20 MB) containing the plugin.
4. Click **Install**. The system validates the package (manifest, slug format, security scan) and extracts it to `plugins/`.
5. After upload, the plugin appears in the list and can be enabled.

> **📌 Note:** The ZIP file must contain a valid `plugin.json` manifest at its root (or one directory level deep). See the [Plugin Development Guide](PLUGIN-DEVELOPMENT.md) for the required manifest format. Uploading and deleting plugins is switched off with `CMS_CODE_EDITING=false`. A plugin must be disabled before it can be deleted.

### Installing Plugins via Composer

Plugins distributed as Composer packages (type `inverys-plugin`) can be installed directly:

```bash
composer require vendor/plugin-name
```

The CMS automatically discovers the plugin in the `vendor/` directory and lists it in **Extensions > Plugins**.

### Plugin Settings

Plugins that provide a settings page show a **Settings** button in their row of the plugin list (enabled plugins only). Click it to access the plugin's configuration page. The [quick search](#quick-search-ctrl-k) finds plugin settings pages by topic as well.

> **💡 Tip:** For example, the Blog plugin's settings page lets you control posts per page, grid columns, sort order, element visibility (author, date, categories, excerpt, featured image, read more, breadcrumbs, sidebar, pagination, RSS), and the blog URL prefix. See [Blog Settings](#blog-settings) for the full list.

### Updates

New versions are checked daily (`cms:check-updates`, needs the scheduler) or with **Check for updates** in the plugin and theme lists; new versions also arrive as a notification.

- **Plugins and themes** show an **Update to …** button in their own list (**Extensions > Plugins / Themes**) as soon as a newer version exists — for packages that name a GitHub repository in their manifest (`"repository": "owner/repo"`). The current folder is backed up first; **Restore version …** puts the previous one back. The last three versions are kept.
- If an active plugin fails after the update (it does not load, or its upgrade step throws), the previous version is restored automatically when the plugin list is opened next.
- Folders that are a Git checkout or were installed with Composer are updated with `git pull` / `composer update` — the button then says why it is locked.
- **Upload Package** in the same lists installs a ZIP by hand; for an installed plugin or theme this is an update (an older version is refused, the current one is backed up).
- **System > Updates** covers the CMS itself — see [Updating the CMS](#updating-the-cms).

Updating needs the admin role, `CMS_CODE_EDITING=true` and a recently confirmed password. For a private GitHub repository set `CMS_UPDATE_TOKEN` in `.env`; `CMS_UPDATE_REPOSITORY` changes where CMS releases are looked up.

### Updating the CMS

**System > Updates** looks like the plugin and theme lists: **Check for updates** and **Upload Package** in the header, and one card for the CMS with the installed and the latest version, status, last check, release notes and actions.

- **Update to …** downloads the complete update package of the release (`inverys-cms-<version>.zip` — with `vendor/` and the built assets, attached to every release by a GitHub workflow) and checks its SHA-256 checksum.
- **Upload Package** folds out an upload area that installs that ZIP by hand, e.g. when the server cannot reach GitHub. The plain source archive is refused — it cannot run without Composer and a build.

What happens:

1. The package is checked: version (no downgrade), PHP version, and that no Composer package of this installation is missing from it.
2. The database is backed up (SQLite: a copy of the file; MySQL/MariaDB: an SQL file) and the site goes into maintenance mode.
3. The core files are swapped; the old ones are backed up under `storage/app/cms-updates/backups/`. **Never touched:** `.env`, `storage/`, themes and plugins that do not ship with the CMS, SQLite files and `public/storage`.
4. The next request starts with the new code, runs the migrations and ends the maintenance mode. If a migration fails, its changes are undone and the old files are put back.
5. **Restore version …** takes the last update back later: its migrations are rolled back and the previous files return. The last two versions are kept.

If the new version does not start at all, the intermediate page offers a link to `update-recovery.php` — a small script without Laravel that puts the previous files back and ends the maintenance mode. It works only while that update is open and only with the key from the link.

> **📌 Note:** Installations that are a Git checkout are updated with `git pull`, `composer install --no-dev`, `php artisan migrate --force` and `npm run build`; the page shows these steps instead of the buttons. Custom changes inside core folders (`app/`, `config/`, `lang/` …) are replaced by an update — keep them in themes and plugins.

---

## 📋 Form Builder

The Form Builder plugin lets you create dynamic forms with a visual drag-and-drop editor. Navigate to **Content > Forms** in the sidebar.

### Creating a Form

1. Click **Create Form**
2. Enter a name (slug is auto-generated)
3. Add fields from the left palette by clicking on a field type
4. Drag fields to reorder them on the canvas
5. Click a field to expand its settings (label, name, placeholder, required, validation, width, etc.)
6. Configure email and behavior settings below the builder
7. Click **Create**

### Field Types

| Type | Description |
|:-----|:------------|
| **Text, Email, Number, Phone, URL** | Standard input fields with type-specific validation |
| **Textarea** | Multi-line text input with configurable default value |
| **Select, Multi-Select** | Dropdown with custom options. Multi-select allows multiple choices |
| **Checkbox, Checkbox Group** | Single toggle or grouped checkboxes with custom options |
| **Radio** | Radio button group with custom options |
| **File** | File upload with configurable allowed types and max size. Secure defaults block executable files |
| **Hidden** | Hidden input for pre-set values |
| **Date, Time** | Native date and time pickers |
| **Static Text** | Informational text displayed between fields (not submitted) |
| **Columns** | Row layout for side-by-side fields (2, 3, or 4 columns) |

### File Upload Security

File fields enforce a safe default whitelist: `pdf, jpg, jpeg, png, gif, webp, svg, doc, docx, xls, xlsx, ppt, pptx, csv, txt, rtf, zip, rar, 7z, mp3, mp4, mov, avi, webm`. Dangerous extensions (exe, php, sh, bat, etc.) are always blocked, even if manually entered. Custom allowed types and max file size can be set per field.

### Form Settings

| Setting | Description |
|:--------|:------------|
| **Send Email Notifications** | Send submission data to configured recipients |
| **Send confirmation to sender** | Sends a short confirmation of receipt — without the submitted data, at most 3 per address and hour — to the address in the form's first email field. Its text can be set per form |
| **Recipients** | Comma-separated email addresses for notifications |
| **Reply-To, From Name, From Email, Subject** | Per-form email overrides (falls back to plugin settings, then CMS mail config) |
| **Submit Button Text** | Custom label for the submit button |
| **Success Message** | Custom message shown after submission (replaces the form) |
| **Honeypot** | Hidden spam protection field |
| **Confirmation Page** | Optional review page before final submission |

### Submissions

Navigate to a form and click **Submissions** to view the inbox. Features:
- **Read/Unread** status with toggle
- **Detail view** with all submitted data and metadata (IP, user agent, timestamp)
- **CSV Export** of all submissions
- **Delete** individual submissions

### Page Builder Integration

Add a **Form** block in the page builder and select a form from the dropdown. The form renders on the frontend with all configured fields, validation, and styling.

### Permissions

| Permission | Required For |
|:-----------|:-------------|
| `show-form` | Viewing forms list |
| `create-form` | Creating forms |
| `edit-form` | Editing forms |
| `delete-form` | Deleting forms |
| `show-form-submission` | Viewing submissions |
| `delete-form-submission` | Deleting submissions |
| `form-settings` | Accessing plugin settings |

---

## 📜 Activity Log

The Activity Log plugin automatically tracks all user actions in the system. Navigate to **System > Activity Log** in the sidebar.

### What Gets Tracked

- **CRUD Operations:** Create, update, delete, and restore events on pages, users, media, roles, permissions, settings, plugins, blog posts, and blog categories
- **Auth Events:** Login, logout, and failed login attempts
- **Field-Level Changes:** Update events store old and new values for each changed field

### Viewing the Log

The index page shows a paginated, filterable list of all log entries with:
- **Filters:** User, action type, subject type, date range, and free-text search
- **Per-Entry Actions:** View details or delete individual entries
- **Clear All:** Bulk delete all entries with confirmation

### Detail View

Click **Details** on any entry to see the full diff table showing:
- Which fields changed
- Old values vs. new values
- User, IP address, timestamp

### Dashboard Widget

The admin dashboard shows a **Recent Activity** feed with the 10 most recent log entries (visible only to users with `show-activity-log` permission).

### Auto-Cleanup

The `activity-log:prune` command runs daily and removes entries based on:
- **Retention Days** (default: 90) — entries older than this are deleted
- **Max Entries** (default: 10,000) — oldest entries are pruned when the limit is exceeded

Configure these values in the plugin's settings (**Extensions > Plugins**, settings button of **Activity Log**).

### Permissions

| Permission | Required For |
|:-----------|:-------------|
| `show-activity-log` | Viewing the activity log (admin only) |
| `delete-activity-log` | Deleting entries and clearing the log |
| `activity-log-settings` | Accessing retention settings |

---

## 🗑️ Trash Management

Inverys CMS provides a unified trash system for safely managing deleted content. When you delete an item, it is soft-deleted and moved to the trash rather than permanently removed.

Navigate to **Content > Trash** in the sidebar.

### Supported Content Types

The trash displays tabs for each content type that supports soft-delete:

| Tab | Displayed Columns |
|:----|:-------------------|
| **Pages** | Title, slug, parent page, deleted date |
| **Users** | Name, email, deleted date |
| **Media** | Filename, MIME type, deleted date |
| **Roles** | Role name, deleted date |
| **Permissions** | Permission name, slug, deleted date |
| **Plugins** | Plugin name, slug, version, deleted date |
| **Themes** | Theme name, number of customized settings, deleted date |

> **📌 Note:** Plugins can register additional trash tabs for their own models — the Blog, Form Builder and Gallery plugins add tabs for their posts, forms and albums.

### Restoring Items

Click the **Restore** button next to any trashed item to move it back to its original location. The item becomes active again immediately.

### Permanently Deleting Items

Click the **Delete Permanently** button to remove an item from the database entirely. A confirmation dialog appears before the action executes.

> **⚠️ Warning:** Permanent deletion cannot be undone. Make sure you no longer need the item before confirming.

### Permissions

Access to the trash requires the `manage-trash` permission, which the Admin and Editor roles have by default. Each tab additionally needs the matching delete permission (e.g. `delete-page` for pages, `delete-user` for users). Deleted admin accounts, and accounts with more permissions than your own, can only be restored by someone who may grant those permissions.

---

## 🔔 Notifications

Inverys CMS includes a built-in notification system that alerts administrators about important events. Access notifications via the **bell icon** in the top-right header bar (there is no sidebar entry).

### Notification Types

| Type | Description | Required permission |
|:-----|:------------|:--------------------|
| **Log File Changes** | New or modified log files were detected. | `show-logs` |
| **Laravel Updates** | A newer Laravel version is available. | `show-system` |
| **CMS, plugin and theme updates** | A new version of the CMS, a plugin or a theme is available (daily check, `cms:check-updates`). | `manage-updates` |
| **HTTP Errors** | Error responses with one of the tracked status codes — includes URL, status code, and user agent. | `show-system` |
| **Failed Logins** | Failed login attempts — includes email address and IP. | `show-system` |

System notifications contain IP and email addresses, so each type only reaches users with the permission listed. The checks run through the scheduler (`php artisan schedule:run` via cron).

### Viewing Notifications

- **Header Dropdown:** Click the bell icon to see a preview of recent unread notifications. A red badge shows the unread count.
- **Notification Index:** Click "View all notifications" in the dropdown or navigate to `/backend/notifications` for the full list with timestamps and details.

### Managing Notifications

| Action | Description |
|:-------|:------------|
| **Mark as Read** | Click the check icon on any unread notification to mark it as read. |
| **Mark All Read** | Click "Mark all as read" to dismiss all unread notifications at once. |
| **Delete All** | Click "Delete all" to permanently remove all notifications. A confirmation dialog appears with a custom message. |

### Notification Settings

Click **Settings** on the notification list (or open `/backend/notifications/settings`; requires `manage-notification-settings`, admin only by default):

- **Your Notifications** — switch each notification type on or off for yourself (all on by default). Types you lack the permission for are shown disabled.
- **Global Settings** — **Tracked HTTP Status Codes**: which status codes trigger an HTTP error notification (default: 401, 403, 404, 500, 503).

Notifications older than the retention period are deleted automatically (**System > Settings > Security > Data Retention**, default 30 days).

### Permissions

| Permission | Required For |
|:-----------|:-------------|
| `show-notifications` | Viewing the notification list. |
| `delete-notifications` | Deleting notifications. |
| `manage-notification-settings` | The notification settings page. |

---

## 📧 Mail Providers

Mail transport configuration is handled by the **[Mail Providers Plugin](../plugins/mail-providers/)** — a system plugin that ships with the CMS and is auto-enabled on install.

Navigate to **System > Mail Providers** in the sidebar.

### Supported Providers

| Provider | Auth Method |
|:---------|:-----------|
| **SMTP** | Username / Password |
| **Microsoft 365** (Graph API) | OAuth2 (Authorization Code) |
| **Google / Gmail** (Gmail API) | OAuth2 (Authorization Code) |

API services such as Amazon SES, Mailgun, Postmark or Resend are used through their SMTP access.

### Quick Setup

The page has two columns: credentials and sender on the left, the choice of provider and the test mail on the right (below on narrow screens).

1. Choose the provider under **Mail Provider** (right column)
2. Enter the provider-specific credentials (SMTP login or OAuth client IDs) — only the card of the selected provider is shown
3. Set the global **From Address** and **From Name** (empty: `MAIL_FROM_ADDRESS` / `MAIL_FROM_NAME` from the `.env`)
4. Click **Save**
5. Use **Send Test Email** in the right column to verify delivery (it uses the saved settings)

For OAuth providers (Microsoft Graph, Gmail), click **Connect** to complete the OAuth authorization flow. The plugin handles token storage and auto-refresh.

### Permissions

| Permission | Required For |
|:-----------|:-------------|
| `mail-providers-settings` | Viewing and editing mail provider settings. |

For detailed setup instructions including OAuth configuration, see the [Mail Providers README](../plugins/mail-providers/README.md).

---

## 🔍 SEO Settings

Every page has a dedicated set of SEO fields for search engine optimization. These are edited in the **SEO** section of the page editor.

### Available Fields

| Field | Description | Example |
|:------|:------------|:--------|
| **Meta Title** | The title shown in search engine results and the browser tab. Overrides the page title when set. | `About Our Company - Inverys` |
| **Meta Description** | A summary shown in search results below the title. Keep it under 160 characters. | `Learn about our mission and team.` |
| **Meta Robots** | Directives for search engine crawlers. | `index, follow` or `noindex, nofollow` |
| **Canonical URL** | Leave empty: the page names itself as its canonical address. Only enter an address (full `https://…` or a path such as `/about`) if another page has the same content and should be found instead — the page then leaves the sitemap. Translatable. | `https://example.com/about` |
| **Social title** | The title shown when the page is shared (Facebook, LinkedIn, X …). Empty: the meta title, then the page title. Translatable. | `About Us` |
| **Social description** | The description in link previews. Empty: the meta description. Translatable. | `Discover our story.` |
| **Social image** | The image in link previews — JPG, PNG or WebP, ideally 1200 × 630 pixels, picked from the media library. Empty: the header image, then the default image from *Settings → SEO*. SVG files are skipped (social networks do not show them). Applies to all languages, like the header image. | `storage/media/og-about.jpg` |

### What every page gets automatically

The CMS writes the search engine and social media tags into the head of every page
itself — the theme does not need to do anything:

- **Description:** the meta description; without one the subtitle, then the beginning of the page text (shortened to about 160 characters), then the **default description** from *Settings → SEO*. A text containing an email address is skipped while email protection is on.
- **Canonical address:** the page's own address — absolute, without parameters such as `?utm_source=…`; page 2 and later of a list keep `?page=N`. Pages with `noindex` name none.
- **Open Graph and X (Twitter) cards:** type, title, description, address, language (`og:locale`, plus the other languages the page is translated into), image with size and alternative text; for blog posts also the publication and modification date, author and category.
- **Structured data (JSON-LD):** on the home page `WebSite` (with the site search, if guests may search) and `Organization` (name, logo, social profiles); on pages `WebPage` with a breadcrumb trail; blog posts as `BlogPosting`, the blog overview and categories as `CollectionPage`.
- **Not indexed:** search result pages and blog archives filtered by year or month get `noindex, follow`; a blog page number beyond the last page returns 404. Error pages get no SEO tags at all.
- **Title:** `Page title – Site name` by default; order and separator are set in *Settings → SEO*. Page 2 and later of a list get `– Page 2` …
- **Feed and icons:** a link to the blog's RSS feed (with the blog plugin), the Apple touch icon and the web app manifest.

Each page and post has exactly one address per language. Requested under another
spelling (`/About-Us`) or, in a translation, under the original slug (`/fr/about-us`
instead of `/fr/a-propos`), it answers with a permanent redirect (301) to the
correct one.

### Additional Page Settings Related to SEO

| Setting | Description |
|:--------|:------------|
| **ARIA Label** | An accessible label for screen readers, useful when the visual page title differs from the intended accessible name. |
| **Include in Search** | Toggle whether this page appears in the site's internal search results. |
| **Meta Author** | Set an author name in the page metadata. |

### Sitemap and robots.txt

The site publishes an XML sitemap at `/sitemap.xml` and a `/robots.txt` that points
to it. Both are configured under **System > Settings > SEO**.

**Sitemap** lists the home page and all active pages (pages with an external link or
with `noindex`/`none` in **Meta Robots** are left out) plus the content of plugins —
the blog adds its overview, published posts and the categories that contain published
posts. Drafts and scheduled posts never appear.

With the multilingual plugin and more than one language, `/sitemap.xml` is a sitemap
index that points to one sitemap per language (`/sitemap-en.xml`, `/sitemap-de.xml` …).
Each lists the addresses of its language (translated slugs) and links every
language version via `hreflang`. Content only appears in the sitemap of languages it
is really translated into: a page without a German translation can still be opened at
`/de/…` (it shows the original), but that address points to the original as its
canonical address and is neither listed nor linked via `hreflang`. A translation saved
later than the page itself moves the page's `lastmod` forward in that language.
With a single language, `/sitemap.xml` is a plain list and
`/sitemap-<default language>.xml` redirects to it.

Pages whose **Canonical URL** names another address are left out. Header and social
images of pages and the featured images of posts are listed as `<image:image>`.
The sitemap is rebuilt as soon as a page, post, category or translation is saved or
deleted — the cache duration only matters for changes made outside the CMS.

| Setting | Description | Default |
|:--------|:------------|:--------|
| **Provide sitemap** | Off: `/sitemap.xml` returns 404 and robots.txt no longer mentions it. | On |
| **Content** | One switch per content type a plugin adds (e.g. blog posts, blog categories). Pages are always included. | All on |
| **Cache duration** | How long the generated sitemap is reused (none, 15 minutes, 1, 6 or 24 hours). Saving the page rebuilds it. | 1 hour |

**Search engines**

| Setting | Description | Default |
|:--------|:------------|:--------|
| **Allow search engines** | Off blocks search engines, e.g. on staging or test sites: robots.txt contains only `User-agent: *` / `Disallow: /` (whatever mode is set below), and every frontend response sends `X-Robots-Tag: noindex, nofollow` plus `<meta name="robots" content="noindex, nofollow">` in the page head. Header and meta work with every theme, including Composer themes. The backend and the API are not marked. While search engines are blocked, the SEO page shows a warning and the backend header shows a red "Not indexed" badge to admins. | On |

The sitemap keeps its own switch and stays reachable while search engines are
blocked (it carries the `noindex` header too), but robots.txt no longer points to it.

**Getting the sitemap found**

Search engines read the `Sitemap:` line in robots.txt. To get the sitemap crawled
sooner and to see reports, submit its address once in
[Google Search Console](https://search.google.com/search-console) and
[Bing Webmaster Tools](https://www.bing.com/webmasters); the SEO page shows the
address with a copy button and links to both. There is no automatic "ping" — Google
discontinued its sitemap ping endpoint.

**robots.txt**

| Setting | Description | Default |
|:--------|:------------|:--------|
| **Mode** | **Automatic**: allows all crawlers (`User-agent: *` / `Disallow:`) plus the additional rules. **Custom content**: you write the complete file. **Off**: there is no robots.txt — `/robots.txt` returns 404, and crawlers then assume they may crawl everything. | Automatic |
| **Additional rules** (Automatic) | Extra lines, one per line: `User-agent`, `Allow`, `Disallow`, `Crawl-delay`, `Host` or comments (`#`). HTML and other directives are rejected. | Empty |
| **Content of robots.txt** (Custom content) | The complete file (up to 10,000 characters). Same lines as above, plus `Sitemap:` with a full `http(s)://` address. | Empty |
| **Add sitemap to robots.txt** | Adds `Sitemap: <address of /sitemap.xml>` in the Automatic and Custom modes — only while the sitemap is enabled and search engines are allowed, and not if your custom content already has a `Sitemap:` line. | On |

The page shows a preview of the resulting robots.txt. A `robots.txt` file placed in
`public/` takes precedence — the web server delivers it before the CMS is asked, and
the settings page warns about it.

**Search results and social networks**

| Setting | Description | Default |
|:--------|:------------|:--------|
| **Default description** | For pages without their own description or text (e.g. the blog overview). Translatable under *Languages → Texts*. | Empty |
| **Page title** / **Separator** | Order of page title and site name in the browser tab and in search results — *Page title, then site name*, *Site name, then page title* or *Page title only* — and the character between them. | *Page title – Site name* |
| **Default social image** | Shown when a shared page has no image of its own. | Empty |
| **Logo for search engines** | The organisation logo in the structured data. Empty: the logo of the active theme. | Empty |
| **Social profiles** | One address per line (LinkedIn, Mastodon, GitHub …) — search engines link them to the website (`sameAs`). | Empty |
| **Google Search Console / Bing Webmaster Tools verification** | The code of the *HTML tag* verification method; the whole pasted meta tag works too. It appears on the home page. | Empty |

Managing these settings requires the `manage-seo-settings` permission (admin only).

### Redirects

**System > Settings > SEO > Redirects** leads old addresses to new ones instead of
showing "page not found":

- **Automatic:** when the address (slug) of a page, blog post or blog category changes — also a translated one — the old address redirects to the new one with 301. Chains are shortened (A → B and B → C becomes A → C), and when a page gets an earlier address back, the redirect away from it is removed.
- **By hand:** *old address* (a path such as `/old-page`, without language prefix), *new address* (a path of this site or a full `https://…` address), *type* (301 permanent or 302 temporary) and, on multilingual sites, optionally the language it applies to. The query of the request (`?ref=…`) travels along.
- A redirect only applies when the address would otherwise end in 404 — a live page always wins. Redirects that would lead back to themselves (directly or through other redirects) are rejected; a circle created otherwise ends in the normal 404.
- The list shows how often each redirect was used and when last. Managing redirects requires the `manage-seo-settings` permission.

### Best Practices

> **💡 Tip:** Follow these SEO best practices for optimal search engine visibility:
>
> - Always set a **Meta Title** and **Meta Description** for important pages.
> - Keep meta descriptions concise (120–160 characters) and descriptive.
> - Set a **default social image** and use the **Social image** field for pages you expect to be shared.
> - Leave the **Canonical URL** empty unless another page carries the same content — every page names itself automatically.
> - Use `noindex` for utility pages (thank-you pages, campaign landing pages) that should not appear in search engine results; search results are marked automatically.
> - Change addresses sparingly — the CMS adds a redirect, but links from other websites still point to the old address.
> - Blog posts have their own **Meta Title** and **Meta Description** fields in the post editor.

---

## 🔎 Search

Inverys CMS includes a plugin-based search system. Search providers are registered by the core system and by plugins.

### Built-in Search Providers

| Provider | Source | Searchable Fields |
|:---------|:-------|:------------------|
| **Pages** | Core | Title, content (including the text inside blocks), meta description |
| **Blog Posts** | Blog plugin | Title, content, excerpt, meta description |
| **Forms** | Form Builder plugin | Title, description |
| **Gallery** | Gallery plugin | Title, description |

> **📌 Note:** Additional providers can be added by plugins. This is the website search for visitors; the admin area has its own [quick search](#quick-search-ctrl-k).

### Frontend Search

- Click the search icon in the navigation bar to open the search page.
- Type your query. Results appear grouped by type (pages, blog posts …) with colored badges indicating the content type.
- Results are paginated according to the configured "results per page" setting.

### Search Settings

1. Navigate to **System > Settings > Search**. The page has two columns: the content types on the left, the general options on the right.
2. **What the search finds** (left) — one entry per content type: the switch on the right of each entry includes or excludes the type, and the checkboxes below choose its searchable fields (e.g. search by title only, or include content and meta fields).
3. **Search Options** (right):

   | Setting | Description | Default |
   |:--------|:------------|:--------|
   | **Min. Search Length** | Minimum number of characters required to trigger a search (1–10). | `2` |
   | **Results per Page** | Number of results displayed per page (5–100). | `10` |
   | **Guest Access** | Whether visitors who are not signed in can use the search. | On |

4. Click **Save** to apply changes.

### Permissions

Managing search settings requires the `manage-search-settings` permission.

---

## 🛡️ Security Settings

Navigate to **System > Settings > Security** to configure all security-related settings from one page. The page has two independent columns: password policy, two-factor authentication, login protection and data retention on the left; API & sessions and security.txt on the right.

### Password Policy

| Setting | Description | Default |
|:--------|:------------|:--------|
| **Minimum Length** | Minimum number of characters required for passwords (8–128). | `10` |
| **Require Uppercase** | Passwords must contain upper and lowercase letters. | On |
| **Require Numbers** | Passwords must contain at least one number. | On |
| **Require Symbols** | Passwords must contain at least one special character. | On |

Password rules are enforced globally via `Password::defaults()` — they apply to registration, password reset, profile updates, and admin user management. The registration page validates password requirements **live as you type**, showing each rule in green once satisfied.

### Two-Factor Authentication

| Setting | Description | Default |
|:--------|:------------|:--------|
| **Require for all users** | Every user verifies a second factor on login — a code by email or an authenticator app (TOTP), set up in the profile. A user can switch it off for their own account in the profile after entering a valid code. The administrator created by the web installer starts with it switched off, so you can sign in before mail is configured. | On |
| **Email Code Lifetime** | Minutes an email verification code stays valid (5–30). | `10` |

### Login Protection

| Setting | Description | Default |
|:--------|:------------|:--------|
| **Allow registration** | Visitors can create an account themselves; new accounts stay inactive until an administrator activates them. Off: the registration page and its links disappear from the login page and all themes. | On |
| **Max. Login Attempts** | Failed login attempts before lockout (3–20). | `5` |
| **Lockout Duration** | Minutes until the lockout expires (1–60). | `1` |

### API & Sessions

| Setting | Description | Default |
|:--------|:------------|:--------|
| **API Rate Limit** | Max API requests per minute per IP address (10–1000). | `60` |
| **Session Lifetime** | Idle timeout in minutes before re-login is required (5–1440). | `120` |

### Data Retention

| Setting | Description | Default |
|:--------|:------------|:--------|
| **Notification Retention** | Notifications older than this are automatically deleted (7–365 days). | `30` |

The retention of the activity log is set in the [Activity Log](#-activity-log) plugin's settings.

### security.txt

Publishes `/.well-known/security.txt` (RFC 9116) so that security researchers know how to reach you: **Contact** (one per line: an email address, an `https` address or `tel:`), **Disclosure policy**, **Encryption key**, **Preferred languages** and **Valid for** (30, 90, 180 or 365 days; the expiry date moves forward automatically). Without a contact, no file is published. Empty fields fall back to `SECURITY_TXT_*` in the `.env`; a fixed `SECURITY_TXT_EXPIRES` there locks the validity period. A preview shows the resulting file.

### Safety Floors

All values enforce minimum thresholds at runtime — even if the database is tampered with directly, values cannot go below safe minimums (e.g. password length never below 8, login attempts never below 3).

### Permissions

Managing security settings requires the `manage-security-settings` permission (admin only).

---

## 🖥️ System Information

**System → System Info** shows the versions, environment and PHP limits of this installation and lets you clear the application cache. It requires the `show-system` permission (admin only). The page has two tabs: **Overview** and **Components (SBOM)**.

### Components (SBOM)

A software bill of materials (SBOM) of **this server** — read live, not from the release:

| Source | What is listed |
|:-------|:---------------|
| `vendor/composer/installed.json` | Every installed Composer package with version, licence, source link and commit. Development packages (`require-dev`) are hidden by default; switch on **Development packages** to include them. After `composer install --no-dev` the switch is greyed out. |
| `plugins/`, `themes/` and Composer packages of type `inverys-plugin` / `inverys-theme` | Every plugin (enabled or not) and every theme (the active one is marked), including uploaded ones. A plugin installed via Composer appears once, as a plugin. |
| PHP runtime | PHP version, SAPI and number of loaded extensions (**Platform** card). |

Filter the list by name, version or licence, or pick a type (plugins, themes, libraries, development). Click a row for details (package URL, commit, folder). A licence badge **Not declared** means the package does not state a licence — for an uploaded plugin, check its origin.

**Download SBOM** creates a [CycloneDX](https://cyclonedx.org/) 1.5 JSON file of this installation on every click (`inverys-cms-{version}-{host}-sbom.cdx.json`). It contains the CMS as root component, all components with purl (`pkg:composer/…`), licences and VCS references, the plugin/theme state, PHP as platform component (with CPE), and the dependency graph. Feed it into your own tooling (e.g. Dependency-Track) to monitor this site for known vulnerabilities.

The **Release SBOMs** card offers the files shipped with the release (`sbom/composer.cdx.json`, `sbom/npm.cdx.json`) and shows whether their version matches this installation. npm packages only build the front-end assets and are not installed on the server, so they appear only there, labelled **Build tooling (npm)**.
