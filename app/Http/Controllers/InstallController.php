<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserSetting;
use App\Services\PluginManager;
use App\Support\InstallerLanguages;
use Database\Seeders\StarterContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class InstallController extends Controller
{
    private const STATE_FILE = 'framework/install-state.json';

    /**
     * All possible install steps in order.
     * Steps marked 'optional' are only run when selected by the user.
     */
    private const STEPS = [
        'env' => ['optional' => null],
        'key' => ['optional' => null],
        'database' => ['optional' => null],
        'admin' => ['optional' => null],
        'pages' => ['optional' => 'seed_pages'],
        'categories' => ['optional' => 'seed_categories'],
        'blog' => ['optional' => 'seed_blog'],
        'storage' => ['optional' => null],
        'finalize' => ['optional' => null],
    ];

    /**
     * Translated step labels (resolved at runtime from lang packs).
     */
    private static function stepLabels(): array
    {
        return [
            'env' => __('install.step_env'),
            'key' => __('install.step_key'),
            'database' => __('install.step_database'),
            'pages' => __('install.step_pages'),
            'categories' => __('install.step_categories'),
            'blog' => __('install.step_blog'),
            'admin' => __('install.step_admin'),
            'storage' => __('install.step_storage'),
            'finalize' => __('install.step_finalize'),
        ];
    }

    // ─── Pages ──────────────────────────────────────────────

    public function index(Request $request): View
    {
        // Handle ?lang= parameter for the installer language switcher
        InstallerLanguages::use($request->query('lang'));

        $requirements = $this->checkRequirements();
        $permissions = $this->checkPermissions();
        // Progress only — the state file contains the database credentials
        // and the admin's password hash, and this page is public
        $state = $this->publicState($this->readState());

        return view('install.wizard', compact('requirements', 'permissions', 'state'));
    }

    // ─── AJAX endpoints ─────────────────────────────────────

    /**
     * Return current install state (for resume on reload).
     */
    public function state(): JsonResponse
    {
        $state = $this->readState();

        // Set locale from saved config so step labels are translated
        InstallerLanguages::use($state['config']['locale'] ?? null);

        return response()->json($this->publicState($state));
    }

    /**
     * What browsers may see of the install state: steps and progress.
     *
     * @return array{started: bool, steps?: array, completed?: array, error?: ?string}
     */
    private function publicState(?array $state): array
    {
        if (! $state) {
            return ['started' => false];
        }

        return [
            'started' => true,
            'steps' => $this->buildStepList($state['config'] ?? []),
            'completed' => $state['completed'] ?? [],
            'error' => $state['error'] ?? null,
        ];
    }

    /**
     * Save form config and return the step list.
     */
    public function save(Request $request): JsonResponse
    {
        $request->validate([
            'db_driver' => 'required|in:sqlite,mysql,mariadb,pgsql',
            'site_name' => 'required|string|max:255',
            'site_url' => 'required|url:http,https',
            'locale' => ['required', 'string', Rule::in(array_keys(InstallerLanguages::all()))],
            'admin_first_name' => 'required|string|max:255',
            'admin_last_name' => 'required|string|max:255',
            'admin_name' => 'required|string|max:255',
            'admin_email' => 'required|email|max:255',
            // The same rules the installation starts with (Security → Password rules):
            // the settings do not exist yet at this step
            'admin_password' => ['required', 'string', Password::min(10)->mixedCase()->numbers()->symbols()],
        ]);

        $config = $request->only([
            'db_driver', 'db_host', 'db_port', 'db_database', 'db_username', 'db_password',
            'site_name', 'site_url', 'locale', 'admin_first_name', 'admin_last_name', 'admin_name', 'admin_email',
            'seed_pages', 'seed_categories', 'seed_blog',
        ]);

        // Hash password so plaintext is never stored on disk
        $config['admin_password_hash'] = Hash::make($request->input('admin_password'));

        // Set app locale so step labels are translated
        InstallerLanguages::use($config['locale'] ?? null);

        $state = [
            'config' => $config,
            'completed' => [],
            'error' => null,
            'started_at' => now()->toIso8601String(),
        ];

        $this->writeState($state);

        return response()->json([
            'success' => true,
            'steps' => $this->buildStepList($config),
        ]);
    }

    /**
     * Execute the next pending step.
     */
    public function runStep(): JsonResponse
    {
        $state = $this->readState();

        if (! $state) {
            return response()->json(['success' => false, 'error' => 'No install state found. Please start over.'], 400);
        }

        $config = $state['config'];
        $completed = $state['completed'] ?? [];

        // Set the installation language before the first text: the steps create
        // content (home page, footer), and a language pack only runs after the
        // database step — without this, some of it would end up in English.
        InstallerLanguages::use($config['locale'] ?? null);

        $steps = $this->buildStepList($config);

        // Find the next step that hasn't been completed
        $nextStep = null;
        foreach ($steps as $step) {
            if (! in_array($step['id'], $completed)) {
                $nextStep = $step;
                break;
            }
        }

        if (! $nextStep) {
            return response()->json(['success' => true, 'done' => true, 'redirect' => url('/login')]);
        }

        // Clear any previous error
        $state['error'] = null;
        $this->writeState($state);

        try {
            $result = $this->executeStep($nextStep['id'], $config);

            // Mark step as completed
            $state['completed'][] = $nextStep['id'];

            // The admin exists now: stop keeping the password hash on disk
            if ($nextStep['id'] === 'admin') {
                unset($state['config']['admin_password_hash']);
            }

            // Check if this was the last step
            $remaining = array_diff(array_column($steps, 'id'), $state['completed']);

            if (empty($remaining)) {
                // Installation complete: the finalize step already removed the state file
                // (which contains the admin password hash) — do NOT recreate it.
                $this->deleteState();
            } else {
                $this->writeState($state);
            }

            $payload = [
                'success' => true,
                'step' => $nextStep['id'],
                'done' => empty($remaining),
                'redirect' => empty($remaining) ? url('/login') : null,
            ];

            return response()->json($payload);
        } catch (\Exception $e) {
            $state['error'] = $e->getMessage();
            $this->writeState($state);

            return response()->json([
                'success' => false,
                'step' => $nextStep['id'],
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Test database connection (connects to server without selecting a database).
     */
    public function testDatabase(Request $request): JsonResponse
    {
        $driver = $request->input('driver', 'sqlite');

        try {
            if ($driver === 'sqlite') {
                $dbPath = database_path('database.sqlite');
                if (! file_exists($dbPath)) {
                    file_put_contents($dbPath, '');
                }
                $pdo = new \PDO("sqlite:{$dbPath}");
                $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                $version = $pdo->query('SELECT sqlite_version()')->fetchColumn();

                return response()->json(['success' => true, 'version' => "SQLite {$version}", 'databases' => []]);
            }

            $pdo = $this->connectToServer($driver, $request);

            $version = match ($driver) {
                'mysql', 'mariadb' => $pdo->query('SELECT VERSION()')->fetchColumn(),
                'pgsql' => $pdo->query('SELECT version()')->fetchColumn(),
                default => 'unknown',
            };

            $databases = $this->listEmptyDatabases($driver, $pdo, $request);

            return response()->json(['success' => true, 'version' => $version, 'databases' => $databases]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /**
     * Connect to database server without selecting a specific database.
     */
    private function connectToServer(string $driver, Request $request): \PDO
    {
        $dsn = match ($driver) {
            'mysql', 'mariadb' => sprintf(
                'mysql:host=%s;port=%s',
                $request->input('host', '127.0.0.1'),
                $request->input('port', '3306')
            ),
            'pgsql' => sprintf(
                'pgsql:host=%s;port=%s;dbname=postgres',
                $request->input('host', '127.0.0.1'),
                $request->input('port', '5432')
            ),
            default => throw new \RuntimeException("Unsupported driver: {$driver}"),
        };

        $pdo = new \PDO($dsn, $request->input('username', ''), $request->input('password', ''));
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        return $pdo;
    }

    /**
     * List databases that contain zero tables (empty databases).
     */
    private function listEmptyDatabases(string $driver, \PDO $pdo, Request $request): array
    {
        $systemDbs = ['information_schema', 'mysql', 'performance_schema', 'sys', 'postgres', 'template0', 'template1'];
        $empty = [];

        if ($driver === 'mysql' || $driver === 'mariadb') {
            $stmt = $pdo->query('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA ORDER BY SCHEMA_NAME');
            $allDbs = $stmt->fetchAll(\PDO::FETCH_COLUMN);

            foreach ($allDbs as $db) {
                if (in_array(strtolower($db), $systemDbs, true)) {
                    continue;
                }
                $escaped = $pdo->quote($db);
                $count = $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = {$escaped}")->fetchColumn();
                if ((int) $count === 0) {
                    $empty[] = $db;
                }
            }
        } elseif ($driver === 'pgsql') {
            $stmt = $pdo->query('SELECT datname FROM pg_database WHERE datistemplate = false ORDER BY datname');
            $allDbs = $stmt->fetchAll(\PDO::FETCH_COLUMN);

            $host = $request->input('host', '127.0.0.1');
            $port = $request->input('port', '5432');
            $user = $request->input('username', '');
            $pass = $request->input('password', '');

            foreach ($allDbs as $db) {
                if (in_array(strtolower($db), $systemDbs, true)) {
                    continue;
                }
                try {
                    $dbPdo = new \PDO("pgsql:host={$host};port={$port};dbname={$db}", $user, $pass);
                    $dbPdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                    $count = $dbPdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'public'")->fetchColumn();
                    if ((int) $count === 0) {
                        $empty[] = $db;
                    }
                } catch (\Exception $e) {
                    // Skip databases we can't connect to
                }
            }
        }

        return $empty;
    }

    // ─── Step execution ─────────────────────────────────────

    private function executeStep(string $step, array $config): mixed
    {
        return match ($step) {
            'env' => $this->stepWriteEnv($config),
            'key' => $this->stepGenerateKey($config),
            'database' => $this->stepDatabase($config),
            'pages' => $this->stepSeedPages(),
            'categories' => $this->stepSeedCategories(),
            'blog' => $this->stepSeedBlog(),
            'admin' => $this->stepCreateAdmin($config),
            'storage' => $this->stepStorageLink(),
            'finalize' => $this->stepFinalize($config),
            default => throw new \RuntimeException("Unknown step: {$step}"),
        };
    }

    /**
     * Value for the .env: always in double quotes, with \\ " and $ escaped
     * (otherwise a space in the password breaks startup or ${…} is read as a
     * variable). Control characters are stripped — a line break could
     * otherwise write additional lines into the .env.
     */
    public static function envValue(mixed $value): string
    {
        $value = preg_replace('/[\x00-\x1F\x7F]/', '', (string) $value);

        return '"'.str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value).'"';
    }

    private function stepWriteEnv(array $config): void
    {
        $driver = $config['db_driver'];

        $lines = [
            'APP_NAME='.self::envValue($config['site_name']),
            'APP_ENV=production',
            'APP_KEY=',
            'APP_DEBUG=false',
            'APP_URL='.self::envValue($config['site_url']),
            '',
            'APP_LOCALE='.$config['locale'],
            'APP_FALLBACK_LOCALE=en',
            '',
            'BCRYPT_ROUNDS=12',
            '',
            'LOG_CHANNEL=stack',
            'LOG_STACK=single',
            'LOG_LEVEL=error',
            '',
            'DB_CONNECTION='.$driver,
        ];

        if ($driver !== 'sqlite') {
            $lines[] = 'DB_HOST='.self::envValue($config['db_host'] ?? '127.0.0.1');
            $lines[] = 'DB_PORT='.self::envValue($config['db_port'] ?? ($driver === 'pgsql' ? '5432' : '3306'));
            $lines[] = 'DB_DATABASE='.self::envValue($config['db_database'] ?? '');
            $lines[] = 'DB_USERNAME='.self::envValue($config['db_username'] ?? '');
            $lines[] = 'DB_PASSWORD='.self::envValue($config['db_password'] ?? '');
        }

        $isHttps = str_starts_with($config['site_url'], 'https://');

        $lines = array_merge($lines, [
            '',
            'SESSION_DRIVER=database',
            'SESSION_LIFETIME=120',
            'SESSION_ENCRYPT=true',
            'SESSION_SECURE_COOKIE='.($isHttps ? 'true' : 'false'),
            '',
            'QUEUE_CONNECTION=database',
            'CACHE_STORE=database',
            '',
            'MAIL_MAILER=log',
            'MAIL_FROM_ADDRESS="noreply@'.parse_url($config['site_url'], PHP_URL_HOST).'"',
            'MAIL_FROM_NAME="${APP_NAME}"',
        ]);

        file_put_contents(base_path('.env'), implode("\n", $lines)."\n");

        // Remove sensitive data from state file now that .env is written
        $statePath = storage_path(self::STATE_FILE);
        if (file_exists($statePath)) {
            $state = json_decode(file_get_contents($statePath), true) ?: [];
            unset($state['config']['db_password']);
            file_put_contents($statePath, json_encode($state));
        }
    }

    private function stepGenerateKey(array $config): void
    {
        Artisan::call('config:clear');
        $this->setDatabaseConfig($config);
        Artisan::call('key:generate', ['--force' => true]);
    }

    private function stepDatabase(array $config): void
    {
        // Ensure DB config is active for this request
        $this->setDatabaseConfig($config);

        // Start from a clean slate so this step is fully resumable. A plain `migrate` is
        // not safe to retry after an interrupted attempt: if the process died mid-migration
        // a table may have been created without being recorded in the `migrations` table,
        // and the retry would then fail with "table already exists". `migrate:fresh` does
        // not help here — it only wipes when the migrations repository already exists, which
        // is exactly what an interrupted install may be missing. `db:wipe` drops every table
        // unconditionally. The installer targets an empty database (the DB picker only offers
        // databases with zero tables, and SQLite starts from a fresh file), so this is safe.
        Artisan::call('db:wipe', ['--force' => true]);
        Artisan::call('migrate', ['--force' => true]);

        // Seed core (roles, permissions, settings) — not shown individually
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RolePermissionSeeder', '--force' => true]);
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\SettingsTableSeeder', '--force' => true]);

        // Apply the timezone from the settings just created right away (like
        // AppServiceProvider on every later request) — otherwise the home page,
        // plugins and admin get UTC timestamps and later appear hours old
        $timezone = Setting::where('key', 'timezone')->value('value');
        if ($timezone && in_array($timezone, timezone_identifiers_list(), true)) {
            config(['app.timezone' => $timezone]);
            date_default_timezone_set($timezone);
        }

        // Save site name and language
        Setting::updateOrCreate(
            ['key' => 'site_name'],
            ['value' => $config['site_name'], 'type' => 'string']
        );
        Setting::updateOrCreate(
            ['key' => 'language'],
            ['value' => $config['locale'], 'type' => 'string']
        );

        // Discover and enable plugins based on plugin.json manifests
        $pluginManager = app(PluginManager::class);
        $pluginManager->discover();

        foreach ($pluginManager->all() as $slug => $manifest) {
            if ($manifest['is_system'] ?? false) {
                // System plugins are mandatory: if one fails, the step aborts instead
                // of finishing an installation without blog, forms or language pack
                if (! $pluginManager->enable($slug)) {
                    throw new \RuntimeException(__('install.err_plugin', ['slug' => $slug]));
                }
            } else {
                $pluginManager->registerOnly($slug);
            }
        }

        // Remove the blog nav page auto-created by BlogServiceProvider::install()
        // so that Page::count() stays at 0/1 and PageSeeder's guard doesn't trigger
        $blogUrlPrefix = Setting::where('key', 'blog.url_prefix')->value('value') ?? 'blog';
        Page::withTrashed()->where('slug', $blogUrlPrefix)->forceDelete();

        // Always create a home page (slug "/"). Styled only via block options
        // (hero + intro section) and only with links every installation has —
        // the sample pages may not be installed.
        if (! Page::where('slug', '/')->exists()) {
            Page::create([
                'title' => StarterContent::homeTitle(),
                'slug' => '/',
                'template' => 'full_width',
                'content_type' => 'blocks',
                'is_active' => true,
                'is_root' => true,
                'level' => 0,
                'order' => 0,
                'include_in_search' => true,
                'blocks' => StarterContent::homeBlocks($config['site_name'], '/'.$blogUrlPrefix),
            ]);
        }
    }

    private function stepSeedPages(): void
    {
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PageSeeder', '--force' => true]);
    }

    private function stepSeedCategories(): void
    {
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PageCategorySeeder', '--force' => true]);
    }

    private function stepSeedBlog(): void
    {
        $pluginManager = app(PluginManager::class);
        $providers = $pluginManager->getLoadedProviders();
        if (isset($providers['blog'])) {
            $providers['blog']->seedDemoContent();
        }
    }

    private function stepCreateAdmin(array $config): void
    {
        $this->setDatabaseConfig($config);

        $admin = User::updateOrCreate(
            ['email' => $config['admin_email']],
            [
                'name' => $config['admin_name'],
                'first_name' => $config['admin_first_name'],
                'last_name' => $config['admin_last_name'],
                'password' => $config['admin_password_hash'],
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        $adminRole = Role::named('admin');
        if ($adminRole) {
            $admin->roles()->attach($adminRole->id);
        }

        // Disable 2FA for the installer-created admin so they can log in
        // even when SMTP/mail is not configured yet. They can re-enable it
        // later in their profile once a mail provider is set up.
        UserSetting::updateOrCreate(
            ['user_id' => $admin->id, 'key' => 'two_factor_disabled'],
            ['value' => '1']
        );
    }

    private function stepStorageLink(): void
    {
        // Link relatively so the link still works after a move (different folder or
        // server, upload via FTP); otherwise fall back to storage:link
        $link = public_path('storage');
        if (! file_exists($link) && ! is_link($link) && PHP_OS_FAMILY !== 'Windows'
            && @symlink('../storage/app/public', $link)) {
            return;
        }

        Artisan::call('storage:link', ['--force' => true]);
    }

    private function stepFinalize(array $config): void
    {
        // Ensure the blog navigation page exists (it was removed during stepDatabase
        // to avoid inflating Page::count before PageSeeder runs)
        $blogUrlPrefix = Setting::where('key', 'blog.url_prefix')->value('value') ?? 'blog';
        $maxOrder = Page::whereNull('parent_id')->max('order') ?? 0;
        Page::firstOrCreate(
            ['slug' => $blogUrlPrefix],
            [
                'title' => 'Blog',
                'nav_title' => 'Blog',
                'content_type' => 'html',
                'content' => '',
                'external_url' => '/'.$blogUrlPrefix,
                'is_active' => true,
                'level' => 0,
                'order' => $maxOrder + 1,
                'hide_in_menu' => false,
                'include_in_search' => false,
            ]
        );

        // Theme texts matching the installation: copyright with the site name and —
        // without sample pages — a footer without links to /about or /contact.
        // Then the starter content for the other languages; before the demo
        // translations, so those don't override their own versions.
        $siteName = (string) ($config['site_name'] ?? config('app.name'));
        $withoutSamplePages = empty($config['seed_pages']);
        StarterContent::applyThemeTexts($siteName, '/'.$blogUrlPrefix, $withoutSamplePages);
        StarterContent::translate($siteName, '/'.$blogUrlPrefix, $withoutSamplePages);

        // Language packs layer their version of the demo content on top — as a
        // translation, or into the originals when installing in their language
        foreach (app(PluginManager::class)->getLoadedProviders() as $provider) {
            if (method_exists($provider, 'seedDemoTranslations')) {
                $provider->seedDemoTranslations();
            }
        }

        file_put_contents(storage_path('installed'), json_encode([
            'version' => config('cms.version', '0.0.0'),
            'installed_at' => now()->toIso8601String(),
        ]));

        // Clean up state file
        $path = storage_path(self::STATE_FILE);
        if (file_exists($path)) {
            unlink($path);
        }
    }

    // ─── Helpers ────────────────────────────────────────────

    /**
     * Build the list of steps to run based on config.
     */
    private function buildStepList(array $config): array
    {
        $labels = self::stepLabels();
        $steps = [];

        foreach (self::STEPS as $id => $meta) {
            // Skip optional steps that weren't selected
            if (! empty($meta['optional']) && empty($config[$meta['optional']])) {
                continue;
            }

            $steps[] = ['id' => $id, 'label' => $labels[$id] ?? $id];
        }

        return $steps;
    }

    private function setDatabaseConfig(array $config): void
    {
        $driver = $config['db_driver'];
        config(['database.default' => $driver]);

        if ($driver === 'sqlite') {
            config(['database.connections.sqlite.database' => database_path('database.sqlite')]);
        } else {
            config([
                "database.connections.{$driver}.host" => $config['db_host'] ?? '127.0.0.1',
                "database.connections.{$driver}.port" => $config['db_port'] ?? null,
                "database.connections.{$driver}.database" => $config['db_database'] ?? null,
                "database.connections.{$driver}.username" => $config['db_username'] ?? null,
                "database.connections.{$driver}.password" => $config['db_password'] ?? null,
            ]);
        }

        DB::purge();
        DB::reconnect();
    }

    private function readState(): ?array
    {
        $path = storage_path(self::STATE_FILE);
        if (! file_exists($path)) {
            return null;
        }

        $data = json_decode(file_get_contents($path), true);

        return is_array($data) ? $data : null;
    }

    private function writeState(array $state): void
    {
        $dir = dirname(storage_path(self::STATE_FILE));
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(storage_path(self::STATE_FILE), json_encode($state, JSON_PRETTY_PRINT));
    }

    private function deleteState(): void
    {
        $path = storage_path(self::STATE_FILE);
        if (file_exists($path)) {
            unlink($path);
        }
    }

    private function checkRequirements(): array
    {
        // Labels from the language files — the installer also runs in German
        $extension = fn (string $ext, string $label) => [
            'name' => __('install.extension', ['name' => $label]),
            'ok' => extension_loaded($ext),
            'current' => extension_loaded($ext) ? __('install.loaded') : __('install.missing'),
        ];

        return [
            ['name' => 'PHP >= 8.4', 'ok' => version_compare(PHP_VERSION, '8.4.0', '>='), 'current' => PHP_VERSION],
            $extension('pdo', 'PDO'),
            $extension('mbstring', 'Mbstring'),
            $extension('openssl', 'OpenSSL'),
            $extension('tokenizer', 'Tokenizer'),
            $extension('ctype', 'Ctype'),
            $extension('json', 'JSON'),
            $extension('fileinfo', 'Fileinfo'),
            ['name' => __('install.extension_gd'), 'ok' => extension_loaded('gd'), 'current' => extension_loaded('gd') ? __('install.loaded') : __('install.missing')],
            $extension('dom', 'DOM'),
        ];
    }

    private function checkPermissions(): array
    {
        return [
            ['path' => 'storage/app', 'ok' => is_writable(storage_path('app'))],
            ['path' => 'storage/framework', 'ok' => is_writable(storage_path('framework'))],
            ['path' => 'storage/logs', 'ok' => is_writable(storage_path('logs'))],
            ['path' => 'bootstrap/cache', 'ok' => is_writable(base_path('bootstrap/cache'))],
            ['path' => __('install.env_file'), 'ok' => is_writable(base_path('.env')) || is_writable(base_path())],
        ];
    }
}
