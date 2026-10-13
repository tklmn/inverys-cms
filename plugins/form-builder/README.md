<p align="center">
  <img src="https://img.shields.io/badge/Inverys_CMS-Plugin-6366f1?style=for-the-badge" alt="Inverys CMS Plugin">
  <img src="https://img.shields.io/badge/Version-1.0.0-22c55e?style=for-the-badge" alt="Version 1.0.0">
  <img src="https://img.shields.io/badge/License-MIT-blue?style=for-the-badge" alt="MIT License">
  <img src="https://img.shields.io/badge/PHP-8.4+-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP 8.4+">
</p>

# :clipboard: Form Builder

A dynamic form builder plugin for [Inverys CMS](https://github.com/tklmn/inverys-cms). Create forms with a drag-and-drop editor, collect submissions, export to CSV, and send email notifications.

---

## :sparkles: Features

| | Feature | Description |
|---|---|---|
| :jigsaw: | **Drag-and-Drop Editor** | Visual field editor with palette and sortable canvas |
| :1234: | **16 Field Types** | Text, email, number, phone, URL, textarea, select, multi-select, checkbox, checkbox-group, radio, file, hidden, date, time, static text |
| :left_right_arrow: | **Column Layouts** | 2, 3, or 4 column layouts for side-by-side fields |
| :inbox_tray: | **Submissions Inbox** | Paginated list with read/unread status and detail view |
| :page_facing_up: | **CSV Export** | Download all submissions for a form as CSV |
| :email: | **Email Notifications** | Configurable recipients, reply-to, from name/email, and custom subject |
| :envelope: | **Sender Confirmation** | Optional short confirmation to the submitter, with its own text but without the submitted data — at most 3 per address and hour |
| :white_check_mark: | **Confirmation Page** | Optional client-side review before final submit |
| :label: | **Custom Submit Button** | Configurable button text per form |
| :honey_pot: | **Honeypot Protection** | Hidden field that silently catches bots |
| :stopwatch: | **Rate Limiting** | Per-IP, per-form rate limiting |
| :shield: | **Captcha Support** | Optional captcha field via the captcha plugin |
| :building_construction: | **Page Builder Block** | Embed forms in any page via the page builder |
| :file_folder: | **File Uploads** | Configurable allowed types, max size, and secure defaults; stored privately and served only to signed-in users with access |
| :mag: | **Search Integration** | Forms in the website search and in the admin quick search (<kbd>Ctrl</kbd>+<kbd>K</kbd>); the settings page is found by topic, e.g. *recipient* or *notification email* |
| :earth_africa: | **Multilingual** | With the [Multilingual](../multilingual/) plugin: field labels, placeholders, help texts, the button and the success message per language |
| :wastebasket: | **Trash Integration** | Soft-deleted forms appear in the CMS trash manager |
| :lock: | **Permissions** | Granular permissions for forms, submissions, and settings |
| :earth_americas: | **i18n** | Full English and German translations |

---

## :zap: Quick Start

### Bundled with Inverys CMS

The form builder plugin is included with the CMS and enabled by default during installation.

### Standalone via Composer

```bash
composer require inverys/form-builder
```

Enable the plugin under **Extensions > Plugins**. The first enable creates its tables, permissions, default settings and the *Form* block type.

---

## :wrench: Configuration

The forms are under **Content > Forms**. For the global settings, open **Extensions > Plugins** and click **Settings** next to the plugin — or use the quick search (<kbd>Ctrl</kbd>+<kbd>K</kbd>):

| Setting | Description | Default |
|---------|-------------|---------|
| **Default Recipients** | Comma-separated email addresses for notifications | — |
| **From Name** | Sender name for notification emails | Default sender name of the CMS, otherwise the site name |
| **From Email** | Sender email for notifications | Default sender of the CMS (Mail Providers or `MAIL_FROM_ADDRESS`) |
| **Rate Limit** | Max submissions per minute per IP/form | `5` |

Each setting except the rate limit can be overridden per form in the form editor, which also sets the reply-to address, subject, success message, submit button text, honeypot, confirmation step and the sender confirmation.

---

## :link: Page Builder Integration

1. Add a **Form** block in the page builder
2. Select a form from the dropdown
3. Save — the form renders on the frontend with all configured fields

---

## :open_file_folder: File Structure

```
form-builder/
├── plugin.json
├── composer.json
├── lang/
│   ├── en/forms.php
│   └── de/forms.php
├── src/
│   ├── FormBuilderServiceProvider.php
│   ├── Models/
│   │   ├── Form.php
│   │   └── FormSubmission.php
│   ├── Services/
│   │   ├── FormRenderer.php
│   │   ├── FormValidator.php
│   │   └── SubmissionExporter.php
│   ├── Mail/
│   │   └── FormSubmissionNotification.php
│   ├── Search/
│   │   ├── FormSearchProvider.php      (website search)
│   │   └── FormAdminSearch.php         (quick search)
│   └── Http/Controllers/
│       ├── FormSubmitController.php
│       ├── FormSubmissionFileController.php   (private uploads)
│       └── Admin/
│           ├── FormController.php
│           ├── SubmissionController.php
│           └── SettingsController.php
└── views/
    ├── frontend/
    │   ├── form.blade.php
    │   ├── success.blade.php
    │   ├── blocks/form.blade.php
    │   └── fields/              (one view per field type)
    └── admin/
        ├── index.blade.php
        ├── create.blade.php
        ├── edit.blade.php
        ├── settings.blade.php
        ├── block-type-script.blade.php
        ├── partials/
        └── submissions/
            ├── index.blade.php
            └── show.blade.php
```

---

## :page_facing_up: Requirements

- **Inverys CMS** 1.0 or newer
- **PHP** >= 8.4

## :handshake: Author

**Tom Kilimann** — [tkilimann@icloud.com](mailto:tkilimann@icloud.com)

## :scroll: License

MIT
