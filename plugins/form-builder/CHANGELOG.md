# Form Builder Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-10-13

First public release of the Form Builder plugin for Inverys CMS.

### Added

- **Drag-and-drop editor** with a field palette and a sortable canvas
- **16 field types:** text, email, number, phone, URL, textarea, select, multi-select, checkbox, checkbox group, radio, file, hidden, date, time and static text
- **Column layouts** with 2, 3 or 4 columns
- **Submissions inbox** with read/unread status, detail view and CSV export
- **Email notifications** with recipients, reply-to, sender and subject per form; empty sender fields use the mail settings of the site
- **Sender confirmation:** an optional short confirmation to the submitter with its own text, without the submitted data, at most 3 per address and hour
- **Success message** and an optional **confirmation page** to review the input before sending
- **Spam protection:** honeypot field, rate limit per IP and form, and captcha fields via the Captcha plugin
- **File uploads** with allowed types and maximum size; HTML and SVG blocked by default, stored privately, served as a download only to signed-in users with access and deleted together with their submission or form
- **Page builder block** to embed a form in any page
- **Multilingual:** with the Multilingual plugin, field labels, placeholders, help texts, option labels, the submit button and the success message per language
- **Search:** forms in the website search and the admin quick search; the settings page found by topic
- **Trash integration** for deleted forms
- **Permissions** for forms, submissions and settings; editors manage forms and submissions, the settings are admin only
- **Extensible:** plugins add field types to the palette and validate submissions through the `form-builder.validating` event
- English and German translations
