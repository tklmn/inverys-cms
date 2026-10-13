<?php

namespace Plugins\FormBuilder;

use App\Services\Plugins\PluginServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Plugins\FormBuilder\Http\Controllers\Admin\FormController;
use Plugins\FormBuilder\Http\Controllers\Admin\SettingsController;
use Plugins\FormBuilder\Http\Controllers\Admin\SubmissionController;
use Plugins\FormBuilder\Http\Controllers\FormSubmissionFileController;
use Plugins\FormBuilder\Http\Controllers\FormSubmitController;
use Plugins\FormBuilder\Models\Form;
use Plugins\FormBuilder\Search\FormAdminSearch;
use Plugins\FormBuilder\Search\FormSearchProvider;

class FormBuilderServiceProvider extends PluginServiceProvider
{
    public function register(): void
    {
        $this->registerAutoloader('Plugins\\FormBuilder');
    }

    public function boot(): void
    {
        $pluginPath = $this->getPluginPath();

        // View namespaces — theme override for frontend
        $this->registerPluginViews('form-builder', $pluginPath.'/views/frontend');
        $this->registerPluginViews('form-builder-admin', $pluginPath.'/views/admin');

        // Translations
        $this->app->make('translator')->addNamespace('form-builder', $pluginPath.'/lang');

        // Frontend routes
        $this->routes()->web('forms', function () {
            Route::post('/{slug}/submit', [FormSubmitController::class, 'submit'])->name('forms.submit');
        });

        // Backend routes
        $this->routes()->backend('form-builder', function () {
            Route::get('/', [FormController::class, 'index'])->name('backend.forms.index');
            Route::get('/create', [FormController::class, 'create'])->name('backend.forms.create');
            Route::post('/', [FormController::class, 'store'])->name('backend.forms.store');
            Route::post('/store-draft', [FormController::class, 'storeDraft'])->name('backend.forms.store-draft');

            // Static routes must be registered before the /{form} wildcard, otherwise
            // "settings" is captured as a {form} route-model binding (404 on save).
            Route::get('/settings', [SettingsController::class, 'edit'])->name('backend.forms.settings');
            Route::put('/settings', [SettingsController::class, 'update'])->name('backend.forms.settings.update');

            Route::get('/{form}/edit', [FormController::class, 'edit'])->name('backend.forms.edit');
            Route::put('/{form}', [FormController::class, 'update'])->name('backend.forms.update');
            Route::post('/{form}/save-fields', [FormController::class, 'saveFields'])->name('backend.forms.save-fields');
            Route::delete('/{form}', [FormController::class, 'destroy'])->name('backend.forms.destroy');

            Route::get('/{form}/submissions', [SubmissionController::class, 'index'])->name('backend.forms.submissions.index');
            Route::get('/{form}/submissions/export', [SubmissionController::class, 'export'])->name('backend.forms.submissions.export');
            Route::get('/{form}/submissions/{submission}', [SubmissionController::class, 'show'])->name('backend.forms.submissions.show');
            Route::post('/{form}/submissions/{submission}/toggle-read', [SubmissionController::class, 'toggleRead'])->name('backend.forms.submissions.toggle-read');
            Route::delete('/{form}/submissions/{submission}', [SubmissionController::class, 'destroy'])->name('backend.forms.submissions.destroy');

            Route::get('/submissions/file/{formSlug}/{filename}', [FormSubmissionFileController::class, 'show'])->name('backend.forms.submission.file')->where('filename', '.*');
        });

        // Sidebar
        $this->extensions()->registerSidebarItem([
            'route' => 'backend.forms.index',
            'icon' => 'clipboard-list',
            'label' => 'form-builder::forms.forms',
            'active' => 'backend.forms.*',
            'section' => 'content',
            'order' => 40,
        ]);

        $this->extensions()->registerSettingsPrefix('form_builder.');

        // Search provider
        $this->extensions()->registerSearchProvider(new FormSearchProvider);
        $this->extensions()->registerAdminSearchProvider(new FormAdminSearch);

        // Trash tab
        $this->extensions()->registerTrashTab([
            'type' => 'form',
            'label' => 'form-builder::forms.forms',
            'icon' => 'bi-ui-checks-grid',
            'model' => Form::class,
            'title_column' => 'name',
            // Restore/permanently delete in the trash
            'permission' => 'delete-form',
        ]);

        // Head hook — page builder block type registration
        $this->extensions()->registerHeadHook('form-builder-admin::block-type-script');

        // View composer to share forms list with the block type script
        View::composer('form-builder-admin::block-type-script', function ($view) {
            $forms = [];
            if (Schema::hasTable('forms')) {
                $forms = Form::where('is_active', true)
                    ->pluck('name', 'id')
                    ->toArray();
            }
            $view->with('forms', $forms);
        });
    }

    public function syncPermissions(): void
    {
        $permNames = [
            'show-form', 'create-form', 'edit-form', 'delete-form',
            'show-form-submission', 'edit-form-submission', 'delete-form-submission',
            'form-settings',
        ];

        foreach ($permNames as $perm) {
            $this->permissions()->createPermission($perm);
        }

        $this->permissions()->assignToRole('admin', $permNames);
        // Editor = content management: forms and submissions in full
        // (deleting also for access/erasure requests), settings admin only
        $this->permissions()->assignToRole('editor', array_values(array_diff($permNames, ['form-settings'])));
    }

    public function install(): void
    {
        // Create forms table
        if (! Schema::hasTable('forms')) {
            Schema::create('forms', function (Blueprint $table) {
                $table->id();
                $table->string('name', 255);
                $table->string('slug', 255)->unique();
                $table->text('description')->nullable();
                $table->json('fields');
                $table->json('settings')->nullable();
                $table->boolean('is_active')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // Create form_submissions table
        if (! Schema::hasTable('form_submissions')) {
            Schema::create('form_submissions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('form_id')->constrained('forms')->cascadeOnDelete();
                $table->json('data');
                $table->json('files')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->boolean('is_read')->default(false);
                $table->boolean('is_spam')->default(false);
                $table->timestamp('read_at')->nullable();
                $table->timestamps();

                $table->index('form_id');
                $table->index('is_read');
                $table->index('created_at');
            });
        }

        // Register form block type for page builder
        $this->registerBlockType([
            'slug' => 'form',
            'name' => 'Form',
            'category' => 'interactive',
            'icon' => 'bi-ui-checks-grid',
        ]);

        // Seed default settings
        $defaults = [
            'form_builder.default_recipients' => ['value' => '', 'type' => 'string', 'description' => 'Default email recipients for form submissions'],
            'form_builder.from_name' => ['value' => '', 'type' => 'string', 'description' => 'From name for notification emails'],
            'form_builder.from_email' => ['value' => '', 'type' => 'string', 'description' => 'From email for notification emails'],
            'form_builder.rate_limit' => ['value' => '5', 'type' => 'integer', 'description' => 'Max submissions per minute per IP/form'],
        ];

        $this->seedSettings($defaults);

        // Create permissions and assign to roles
        $this->syncPermissions();
    }
}
