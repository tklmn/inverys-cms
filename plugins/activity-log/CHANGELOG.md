# Activity Log Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-10-13

First public release of the Activity Log plugin for Inverys CMS.

### Added

- **Automatic tracking** of create, update, delete and restore on the models the core marks as observable (pages, users, media, roles, permissions, settings, plugins) and on plugin models with the `LogsActivity` trait (blog posts and categories)
- **Field-level diffs:** update events store old and new values, shown as a side-by-side table in the detail view
- **Auth events:** login, logout and failed login attempts
- **Filterable index** by user, action, subject type, date range and free text, with translated subject types and a detail panel
- **Clear log:** delete single entries or the whole log; clearing leaves one entry that states how many entries were deleted
- **Auto-cleanup:** `activity-log:prune` runs daily via the scheduler, with configurable retention days (default 90) and maximum entry count (default 10,000)
- **Dashboard widget** with the recent activity
- **Settings page** for retention and maximum entries, found by the admin quick search (e.g. *audit*, *retention*)
- **Permissions** `show-activity-log`, `delete-activity-log` and `activity-log-settings` — admin only by default
- **Secrets are masked:** changes of secret settings (type `encrypted`, or keys containing `secret`, `password`, `token` or ending in `.key`) show `********`; two-factor secrets and recovery codes are never written to the log
- Works under the strict nonce-based Content-Security-Policy of the CMS
- English and German translations
