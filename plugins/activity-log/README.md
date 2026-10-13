<p align="center">
  <img src="https://img.shields.io/badge/Inverys_CMS-Plugin-6366f1?style=for-the-badge" alt="Inverys CMS Plugin">
  <img src="https://img.shields.io/badge/Version-1.0.0-22c55e?style=for-the-badge" alt="Version 1.0.0">
  <img src="https://img.shields.io/badge/License-MIT-blue?style=for-the-badge" alt="MIT License">
  <img src="https://img.shields.io/badge/PHP-8.4+-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP 8.4+">
</p>

# :scroll: Activity Log

An activity logging plugin for [Inverys CMS](https://github.com/tklmn/inverys-cms). Automatically tracks all user actions with field-level change diffs, auth event logging, and configurable auto-cleanup.

---

## :sparkles: Features

| | Feature | Description |
|---|---|---|
| :eyes: | **Auto Tracking** | Automatic logging of create, update, delete, and restore operations on tracked models |
| :mag: | **Field-Level Diffs** | Update events store old/new values, displayed as side-by-side diff tables |
| :key: | **Auth Events** | Login, logout, and failed login attempts are tracked |
| :bar_chart: | **Filterable Index** | Filter by user, action, subject type, date range, and free-text search |
| :memo: | **Detail View** | Full entry view with formatted diff table showing changed fields |
| :broom: | **Auto-Cleanup** | `activity-log:prune` runs daily via the scheduler, with configurable retention days and max entries |
| :wastebasket: | **Clear Log** | Delete single entries or clear the whole log — the clearing itself is logged |
| :chart_with_upwards_trend: | **Dashboard Widget** | Recent activity feed on the admin dashboard |
| :gear: | **Settings** | Configurable retention period and maximum entry count; found by the quick search (<kbd>Ctrl</kbd>+<kbd>K</kbd>), e.g. *audit* or *retention* |
| :lock: | **Permissions** | Show, delete entries, manage settings — admin only |
| :earth_americas: | **i18n** | Full English and German translations |

---

## :zap: Quick Start

### Bundled with Inverys CMS

The activity log plugin is included with the CMS and enabled by default during installation.

### Standalone via Composer

```bash
composer require inverys/activity-log
```

Enable the plugin under **Extensions > Plugins**. The first enable creates its table, permissions and default settings.

---

## :wrench: Configuration

The log itself is under **System > Activity Log**. For the settings, open **Extensions > Plugins** and click **Settings** next to the plugin — or use the quick search (<kbd>Ctrl</kbd>+<kbd>K</kbd>):

| Setting | Description | Default |
|---------|-------------|---------|
| **Retention Days** | How long to keep log entries (`0` = keep forever) | `90` days |
| **Max Entries** | Maximum number of entries to retain (`0` = unlimited) | `10,000` |

### Auto-Cleanup

The CMS schedules `activity-log:prune` once a day (`routes/console.php`). It only runs when the Laravel scheduler is called by cron:

```bash
* * * * * cd /path/to/inverys-cms && php artisan schedule:run >> /dev/null 2>&1
```

You can also run it by hand:

```bash
php artisan activity-log:prune
```

Removes entries older than the retention period and trims above the max entry count.

---

## :link: Tracking Models

Core CMS models are tracked automatically via the `ActivityLogObserver` — no trait needed. The core lists them in `config('cms.observable_models')`:

> Page, User, Media, Role, Permission, Setting, Plugin

Blog models (Post, BlogCategory) are tracked when the blog plugin is active.

### Custom Models

To track your own models, add the `LogsActivity` trait:

```php
use Plugins\ActivityLog\Traits\LogsActivity;

class Widget extends Model
{
    use LogsActivity;
}
```

Or register the observer manually in your service provider:

```php
\App\Models\YourModel::observe(new \Plugins\ActivityLog\Observers\ActivityLogObserver());
```

---

## :open_file_folder: File Structure

```
activity-log/
├── plugin.json
├── composer.json
├── lang/
│   ├── en/activity.php
│   └── de/activity.php
├── src/
│   ├── ActivityLogServiceProvider.php
│   ├── Models/
│   │   └── ActivityLog.php
│   ├── Traits/
│   │   └── LogsActivity.php
│   ├── Observers/
│   │   └── ActivityLogObserver.php
│   ├── Listeners/
│   │   └── LogAuthEvent.php
│   ├── Services/
│   │   └── ActivityLogger.php
│   ├── Console/
│   │   └── PruneActivityLogCommand.php
│   └── Http/Controllers/Admin/
│       ├── ActivityLogController.php
│       └── SettingsController.php
└── views/admin/
    ├── index.blade.php
    ├── show.blade.php
    ├── settings.blade.php
    └── partials/
        ├── dashboard-widget.blade.php
        └── diff-table.blade.php
```

---

## :page_facing_up: Requirements

- **Inverys CMS** 1.0 or newer
- **PHP** >= 8.4

## :handshake: Author

**Tom Kilimann** — [tkilimann@icloud.com](mailto:tkilimann@icloud.com)

## :scroll: License

MIT
