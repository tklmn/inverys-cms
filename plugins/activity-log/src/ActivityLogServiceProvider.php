<?php

namespace Plugins\ActivityLog;

use App\Services\Plugins\PluginServiceProvider;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Console\Application;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Plugins\ActivityLog\Console\PruneActivityLogCommand;
use Plugins\ActivityLog\Http\Controllers\Admin\ActivityLogController;
use Plugins\ActivityLog\Http\Controllers\Admin\SettingsController;
use Plugins\ActivityLog\Listeners\LogAuthEvent;
use Plugins\ActivityLog\Observers\ActivityLogObserver;
use Plugins\Blog\Models\BlogCategory;
use Plugins\Blog\Models\Post;

class ActivityLogServiceProvider extends PluginServiceProvider
{
    public function register(): void
    {
        $this->registerAutoloader('Plugins\\ActivityLog');
    }

    public function boot(): void
    {
        $pluginPath = $this->getPluginPath();

        // View namespace
        $this->registerPluginViews('activity-log-admin', $pluginPath.'/views/admin');

        // Translations
        $this->app->make('translator')->addNamespace('activity-log', $pluginPath.'/lang');

        // Backend routes
        $this->routes()->backend('activity-log', function () {
            Route::get('/', [ActivityLogController::class, 'index'])->name('backend.activity-log.index');
            Route::post('/clear', [ActivityLogController::class, 'clear'])->name('backend.activity-log.clear');
            Route::get('/settings/edit', [SettingsController::class, 'edit'])->name('backend.activity-log.settings.edit');
            Route::put('/settings', [SettingsController::class, 'update'])->name('backend.activity-log.settings.update');
            Route::get('/{activityLog}', [ActivityLogController::class, 'show'])->name('backend.activity-log.show')->whereNumber('activityLog');
            Route::delete('/{activityLog}', [ActivityLogController::class, 'destroy'])->name('backend.activity-log.destroy')->whereNumber('activityLog');
        });

        // Register sidebar item
        $this->extensions()->registerSidebarItem([
            'route' => 'backend.activity-log.index',
            'icon' => 'activity',
            'label' => 'activity-log::activity.title',
            'active' => 'backend.activity-log.*',
            'section' => 'system',
            // right after "Logs" (order 20) — same topic
            'order' => 25,
            'permission' => 'show-activity-log',
        ]);

        $this->extensions()->registerSettingsPrefix('activity_log.');

        $this->extensions()->registerDashboardWidget([
            'type' => 'widget',
            'label' => 'activity-log::activity.title',
            'icon' => 'bi-activity',
            'view' => 'activity-log-admin::partials.dashboard-widget',
            'permission' => 'show-activity-log',
            'data' => fn () => ['recentActivity' => Schema::hasTable('activity_logs') ? DB::table('activity_logs')->orderByDesc('created_at')->limit(10)->get() : collect()],
        ]);

        // Register observer on the core models the core advertises as observable
        // (config-driven, so the plugin does not import core model classes).
        $observer = new ActivityLogObserver;

        foreach (config('cms.observable_models', []) as $model) {
            $model::observe($observer);
        }

        // Conditionally register observer for blog models
        if (class_exists(Post::class)) {
            Post::observe($observer);
        }
        if (class_exists(BlogCategory::class)) {
            BlogCategory::observe($observer);
        }

        // Listen to auth events
        $listener = new LogAuthEvent;
        Event::listen(Login::class, [$listener, 'handleLogin']);
        Event::listen(Logout::class, [$listener, 'handleLogout']);
        Event::listen(Failed::class, [$listener, 'handleFailed']);

        // Register artisan command
        $this->app->singleton(PruneActivityLogCommand::class);
        $this->app->tag([PruneActivityLogCommand::class], 'commands');
        if ($this->app->runningInConsole()) {
            Application::starting(function ($artisan) {
                $artisan->resolve(PruneActivityLogCommand::class);
            });
        }
    }

    public function install(): void
    {
        // Create activity_logs table
        if (! Schema::hasTable('activity_logs')) {
            Schema::create('activity_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('user_name')->nullable();
                $table->string('action', 50);
                $table->string('subject_type')->nullable();
                $table->unsignedBigInteger('subject_id')->nullable();
                $table->string('subject_label')->nullable();
                $table->json('properties')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamps();

                $table->index('user_id');
                $table->index('action');
                $table->index('subject_type');
                $table->index('created_at');
                $table->index(['subject_type', 'subject_id']);
            });
        }

        // Sync permissions
        $this->syncPermissions();

        // Seed default settings
        if (! $this->settings()->has('activity_log.retention_days')) {
            $this->settings()->set('activity_log.retention_days', '90', 'integer', 'Delete activity log entries older than this many days. 0 = keep forever.');
        }
        if (! $this->settings()->has('activity_log.max_entries')) {
            $this->settings()->set('activity_log.max_entries', '10000', 'integer', 'Maximum number of activity log entries to keep. 0 = unlimited.');
        }
    }

    public function syncPermissions(): void
    {
        $permNames = [
            'show-activity-log',
            'delete-activity-log',
            'activity-log-settings',
        ];

        foreach ($permNames as $perm) {
            $this->permissions()->createPermission($perm);
        }

        $this->permissions()->assignToRole('admin', $permNames);
    }
}
