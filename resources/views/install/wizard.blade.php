@php
    $locale = app()->getLocale();

    // Languages from the core and the language packs (plugins/*, type "language")
    $installerLanguages = \App\Support\InstallerLanguages::all();
    $availableInstallerLangs = array_keys($installerLanguages);

    // Build JS translation object from the lang pack
    $jsKeys = [
        'title', 'subtitle', 'requirements', 'requirements_desc',
        'database', 'database_desc', 'db_driver', 'sqlite_label',
        'host', 'port', 'username', 'password',
        'connect_btn', 'test_btn', 'testing', 'connecting', 'connected', 'conn_failed',
        'select_db', 'select_db_placeholder', 'select_db_connect_first',
        'no_empty_db', 'no_empty_db_msg', 'db_hint', 'select_db_error',
        'settings', 'settings_desc', 'site_name', 'site_url', 'site_url_hint', 'language',
        'demo_content', 'demo_desc',
        'admin', 'admin_desc', 'full_name', 'email', 'password_label', 'password_confirm',
        'password_placeholder', 'password_confirm_placeholder',
        'err_required', 'err_email', 'err_password_short', 'err_password_match',
        'installing', 'installing_desc', 'all_done', 'paused', 'paused_resume', 'retry',
        'complete_title', 'complete_desc', 'go_login',
        'back', 'next', 'install_now', 'err_save', 'err_generic', 'err_network',
    ];
    $jsTranslations = [];
    foreach ($jsKeys as $key) {
        $jsTranslations[$key] = __("install.{$key}");
    }
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('install.title') }} — Inverys CMS</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root { --blue: #3b82f6; --indigo: #6366f1; --green: #22c55e; --red: #ef4444; }
        body { font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; background: #0f172a; color: #e2e8f0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 2rem 1rem; }
        body::before { content: ''; position: fixed; inset: 0; background: radial-gradient(ellipse at 50% 0%, rgba(99,102,241,.15) 0%, transparent 60%), radial-gradient(ellipse at 80% 100%, rgba(59,130,246,.1) 0%, transparent 50%); pointer-events: none; }

        .installer { position: relative; background: rgba(30,41,59,.8); backdrop-filter: blur(20px); border: 1px solid rgba(148,163,184,.1); border-radius: 20px; width: 100%; max-width: 640px; overflow: hidden; }
        .installer-header { padding: 2.5rem 2.5rem 1.5rem; text-align: center; }
        .logo { display: inline-block; margin-bottom: 1rem; }
        .logo { display: inline-flex; align-items: baseline; gap: 8px; }
        .logo svg { display: block; height: 28px; width: auto; }
        .logo-product { font-size: 15px; font-weight: 400; line-height: 1; color: #98a1b5; }
        .logo svg [fill="#1E1B4B"] { fill: #F8FAFC; }
        .installer-header h1 { font-size: 1.375rem; font-weight: 700; color: #f8fafc; margin-bottom: .25rem; }
        .installer-header p { font-size: .8125rem; color: #94a3b8; }

        .lang-switch { position: absolute; top: 1.25rem; right: 1.5rem; display: flex; gap: .375rem; z-index: 2; }
        .lang-switch a { font-size: .75rem; padding: .25rem .5rem; border-radius: 6px; text-decoration: none; color: #64748b; transition: all .2s; }
        .lang-switch a:hover, .lang-switch a.active { color: #e2e8f0; background: rgba(148,163,184,.12); }

        .steps { display: flex; padding: 1rem 2.5rem; gap: .5rem; border-bottom: 1px solid rgba(148,163,184,.08); }
        .step-dot { flex: 1; height: 4px; border-radius: 2px; background: rgba(148,163,184,.15); transition: background .3s; }
        .step-dot.active { background: var(--blue); }
        .step-dot.done { background: var(--green); }

        .step-content { padding: 2rem 2.5rem; min-height: 340px; display: none; }
        .step-content.active { display: block; }
        .step-title { font-size: 1.125rem; font-weight: 700; color: #f8fafc; margin-bottom: .25rem; }
        .step-desc { font-size: .8125rem; color: #94a3b8; margin-bottom: 1.5rem; }

        .field { margin-bottom: 1.25rem; }
        .field label { display: block; font-size: .8125rem; font-weight: 600; color: #94a3b8; margin-bottom: .375rem; }
        .field input, .field select { width: 100%; padding: .625rem .75rem; border: 1px solid rgba(148,163,184,.2); border-radius: 10px; font-size: .875rem; font-family: inherit; color: #e2e8f0; background: rgba(15,23,42,.5); transition: border-color .2s; }
        .field select { appearance: none; -webkit-appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2394a3b8' d='M2 4l4 4 4-4'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right .75rem center; padding-right: 2.25rem; cursor: pointer; }
        .field select:disabled { opacity: .5; cursor: not-allowed; }
        .field input:focus, .field select:focus { outline: none; border-color: var(--blue); box-shadow: 0 0 0 3px rgba(59,130,246,.15); }
        .field input::placeholder { color: #64748b; }
        .field .hint { font-size: .75rem; color: #64748b; margin-top: .25rem; }
        .field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }

        .checkbox-field { display: flex; align-items: center; gap: .75rem; padding: .75rem 1rem; margin-bottom: .5rem; cursor: pointer; border-radius: 10px; border: 1px solid rgba(148,163,184,.12); background: rgba(15,23,42,.3); transition: all .2s; }
        .checkbox-field:hover { border-color: rgba(148,163,184,.25); background: rgba(15,23,42,.5); }
        .checkbox-field:has(input:checked) { border-color: rgba(99,102,241,.4); background: rgba(99,102,241,.08); }
        .checkbox-field input[type="checkbox"] { appearance: none; -webkit-appearance: none; width: 36px; height: 20px; background: rgba(148,163,184,.25); border-radius: 999px; cursor: pointer; flex-shrink: 0; position: relative; transition: background .2s; }
        .checkbox-field input[type="checkbox"]::after { content: ''; position: absolute; top: 2px; left: 2px; width: 16px; height: 16px; background: #fff; border-radius: 50%; transition: transform .2s; }
        .checkbox-field input[type="checkbox"]:checked { background: var(--blue); }
        .checkbox-field input[type="checkbox"]:checked::after { transform: translateX(16px); }
        .checkbox-field .cb-label { font-size: .8125rem; font-weight: 600; color: #cbd5e1; line-height: 1.3; }
        .checkbox-field .cb-desc { font-size: .7rem; color: #64748b; line-height: 1.3; }

        .check-list { list-style: none; }
        .check-list li { display: flex; align-items: center; gap: .625rem; padding: .625rem 0; border-bottom: 1px solid rgba(148,163,184,.06); font-size: .875rem; color: #cbd5e1; }
        .check-list li:last-child { border-bottom: none; }
        .check-icon { width: 22px; height: 22px; border-radius: 6px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: .75rem; font-weight: 700; }
        .check-icon.pass { background: rgba(34,197,94,.15); color: var(--green); }
        .check-icon.fail { background: rgba(239,68,68,.15); color: var(--red); }
        .check-current { margin-left: auto; font-size: .75rem; color: #64748b; }

        .btn-row { display: flex; justify-content: space-between; padding: 1.25rem 2.5rem; border-top: 1px solid rgba(148,163,184,.08); }
        .btn { display: inline-flex; align-items: center; gap: .5rem; padding: .625rem 1.5rem; border-radius: 10px; font-size: .875rem; font-weight: 600; border: none; cursor: pointer; font-family: inherit; transition: all .2s; text-decoration: none; }
        .btn:disabled { opacity: .4; cursor: not-allowed; }
        .btn-primary { background: linear-gradient(135deg, var(--blue), var(--indigo)); color: #fff; box-shadow: 0 4px 12px rgba(99,102,241,.25); }
        .btn-primary:hover:not(:disabled) { box-shadow: 0 6px 20px rgba(99,102,241,.35); transform: translateY(-1px); }
        .btn-secondary { background: rgba(148,163,184,.1); color: #94a3b8; }
        .btn-secondary:hover:not(:disabled) { background: rgba(148,163,184,.15); color: #cbd5e1; }

        .db-status { padding: .75rem 1rem; border-radius: 10px; font-size: .8125rem; margin-bottom: 1rem; display: none; }
        .db-status.success { display: block; background: rgba(34,197,94,.1); border: 1px solid rgba(34,197,94,.2); color: #86efac; }
        .db-status.error { display: block; background: rgba(239,68,68,.1); border: 1px solid rgba(239,68,68,.2); color: #fca5a5; }

        .install-steps { list-style: none; }
        .install-steps li { display: flex; align-items: center; gap: .75rem; padding: .75rem 0; border-bottom: 1px solid rgba(148,163,184,.06); font-size: .875rem; }
        .install-steps li:last-child { border-bottom: none; }
        .s-icon { width: 28px; height: 28px; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: .75rem; font-weight: 700; }
        .s-icon.waiting { background: rgba(148,163,184,.1); color: #64748b; }
        .s-icon.running { background: rgba(59,130,246,.15); color: var(--blue); }
        .s-icon.done { background: rgba(34,197,94,.15); color: var(--green); }
        .s-icon.error { background: rgba(239,68,68,.15); color: var(--red); }
        .s-label { flex: 1; color: #cbd5e1; }
        .s-label.running { font-weight: 600; color: #93c5fd; }
        .s-label.done { color: #86efac; }
        .s-label.error { color: #fca5a5; }
        .s-error { font-size: .75rem; color: #fca5a5; margin-top: .25rem; }
        .spinner-ring { width: 16px; height: 16px; border: 2px solid rgba(59,130,246,.3); border-top-color: var(--blue); border-radius: 50%; animation: spin .6s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }

        .done-screen { text-align: center; padding: 2rem; display: none; }
        .done-icon { width: 72px; height: 72px; background: rgba(34,197,94,.12); border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 1.5rem; }
        .done-icon svg { width: 36px; height: 36px; color: var(--green); }
        .done-screen h2 { font-size: 1.25rem; font-weight: 700; color: #f8fafc; margin-bottom: .5rem; }
        .done-screen p { font-size: .875rem; color: #94a3b8; margin-bottom: 1.5rem; }
        .btn-success { background: linear-gradient(135deg, #16a34a, #22c55e); color: #fff; font-size: 1rem; padding: .75rem 2rem; box-shadow: 0 4px 12px rgba(34,197,94,.25); }
        .btn-success:hover { box-shadow: 0 6px 20px rgba(34,197,94,.35); transform: translateY(-1px); }

        .error-box { background: rgba(239,68,68,.1); border: 1px solid rgba(239,68,68,.2); color: #fca5a5; padding: 1rem; border-radius: 10px; font-size: .8125rem; margin-top: 1rem; display: none; }
        .retry-row { text-align: center; margin-top: 1rem; }

        .db-fields { display: none; }
        .db-fields.visible { display: block; }

        .section-label { font-size: .75rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: .05em; margin-bottom: .75rem; }
        .section-label.mt { margin-top: 1.25rem; }

        .field select option { background: #1e293b; color: #e2e8f0; }

        @media (max-width: 480px) {
            .field-row { grid-template-columns: 1fr; }
            .btn-row { flex-direction: column-reverse; gap: .75rem; }
            .btn { width: 100%; justify-content: center; }
            .step-content, .installer-header { padding-left: 1.5rem; padding-right: 1.5rem; }
            .btn-row, .steps { padding-left: 1.5rem; padding-right: 1.5rem; }
        }
    </style>
</head>
<body>
    <div class="installer">
        <div class="lang-switch">
            @foreach($availableInstallerLangs as $code)
            <a href="?lang={{ $code }}" class="{{ $locale === $code ? 'active' : '' }}">{{ strtoupper($code) }}</a>
            @endforeach
        </div>

        <div class="installer-header">
            <div class="logo">{!! file_get_contents(public_path('images/logo.svg')) !!}<span class="logo-product">CMS</span></div>
            <h1>{{ __('install.title') }}</h1>
            <p>{{ __('install.subtitle') }}</p>
        </div>

        <div class="steps" id="stepDots"></div>

        <!-- Step 1: Requirements -->
        <div class="step-content active" id="step-0">
            <div class="step-title">{{ __('install.requirements') }}</div>
            <div class="step-desc">{{ __('install.requirements_desc') }}</div>

            <h3 class="section-label">{{ __('install.php_extensions') }}</h3>
            <ul class="check-list">
                @foreach($requirements as $req)
                <li>
                    <span class="check-icon {{ $req['ok'] ? 'pass' : 'fail' }}">{{ $req['ok'] ? '✓' : '✗' }}</span>
                    {{ $req['name'] }}
                    <span class="check-current">{{ $req['current'] }}</span>
                </li>
                @endforeach
            </ul>

            <h3 class="section-label mt">{{ __('install.dir_permissions') }}</h3>
            <ul class="check-list">
                @foreach($permissions as $perm)
                <li>
                    <span class="check-icon {{ $perm['ok'] ? 'pass' : 'fail' }}">{{ $perm['ok'] ? '✓' : '✗' }}</span>
                    {{ $perm['path'] }}
                    <span class="check-current">{{ $perm['ok'] ? __('install.writable') : __('install.not_writable') }}</span>
                </li>
                @endforeach
            </ul>
        </div>

        <!-- Step 2: Database -->
        <div class="step-content" id="step-1">
            <div class="step-title">{{ __('install.database') }}</div>
            <div class="step-desc">{{ __('install.database_desc') }}</div>

            <div class="field">
                <label for="db_driver">{{ __('install.db_driver') }}</label>
                <select id="db_driver">
                    <option value="sqlite">{{ __('install.sqlite_label') }}</option>
                    <option value="mysql">MySQL / MariaDB</option>
                    <option value="pgsql">PostgreSQL</option>
                </select>
            </div>

            <div class="db-fields" id="dbFields">
                <div class="field-row">
                    <div class="field">
                        <label for="db_host">{{ __('install.host') }}</label>
                        <input type="text" id="db_host" value="127.0.0.1">
                    </div>
                    <div class="field">
                        <label for="db_port">{{ __('install.port') }}</label>
                        <input type="text" id="db_port" value="3306">
                    </div>
                </div>
                <div class="field-row">
                    <div class="field">
                        <label for="db_username">{{ __('install.username') }}</label>
                        <input type="text" id="db_username" value="root">
                    </div>
                    <div class="field">
                        <label for="db_password">{{ __('install.password') }}</label>
                        <input type="password" id="db_password">
                    </div>
                </div>

                <button type="button" class="btn btn-secondary" id="btnTestDb" disabled style="margin-bottom:1.25rem">{{ __('install.connect_btn') }}</button>

                <div class="db-status" id="dbStatus"></div>

                <div class="field" id="dbSelectField" style="display:none">
                    <label for="db_database">{{ __('install.select_db') }}</label>
                    <select id="db_database" disabled>
                        <option value="">{{ __('install.select_db_connect_first') }}</option>
                    </select>
                    <div class="hint">{{ __('install.db_hint') }}</div>
                </div>
            </div>

            <div class="db-status" id="dbStatusSqlite" style="display:none"></div>
            <button type="button" class="btn btn-secondary" id="btnTestSqlite" style="display:none">{{ __('install.test_btn') }}</button>
        </div>

        <!-- Step 3: Site Settings + Demo Content -->
        <div class="step-content" id="step-2">
            <div class="step-title">{{ __('install.settings') }}</div>
            <div class="step-desc">{{ __('install.settings_desc') }}</div>

            <div class="field">
                <label for="site_name">{{ __('install.site_name') }}</label>
                <input type="text" id="site_name" value="Inverys CMS">
            </div>
            <div class="field">
                <label for="site_url">{{ __('install.site_url') }}</label>
                <input type="url" id="site_url" value="{{ url('/') }}">
                <div class="hint">{{ __('install.site_url_hint') }}</div>
            </div>
            <div class="field">
                <label for="locale">{{ __('install.language') }}</label>
                <select id="locale">
                    @foreach($availableInstallerLangs as $code)
                    <option value="{{ $code }}" {{ $locale === $code ? 'selected' : '' }}>{{ $installerLanguages[$code]['native'] }}</option>
                    @endforeach
                </select>
            </div>

            <h3 class="section-label mt">{{ __('install.demo_content') }}</h3>
            <div class="step-desc" style="margin-top:-.25rem">{{ __('install.demo_desc') }}</div>

            <label class="checkbox-field">
                <input type="checkbox" id="seed_pages">
                <div><div class="cb-label">{{ __('install.seed_pages') }}</div><div class="cb-desc">{{ __('install.seed_pages_desc') }}</div></div>
            </label>
            <label class="checkbox-field">
                <input type="checkbox" id="seed_categories">
                <div><div class="cb-label">{{ __('install.seed_categories') }}</div><div class="cb-desc">{{ __('install.seed_categories_desc') }}</div></div>
            </label>
            <label class="checkbox-field">
                <input type="checkbox" id="seed_blog">
                <div><div class="cb-label">{{ __('install.seed_blog') }}</div><div class="cb-desc">{{ __('install.seed_blog_desc') }}</div></div>
            </label>
        </div>

        <!-- Step 4: Admin Account -->
        <div class="step-content" id="step-3">
            <div class="step-title">{{ __('install.admin') }}</div>
            <div class="step-desc">{{ __('install.admin_desc') }}</div>

            <div class="field-row">
                <div class="field">
                    <label for="admin_first_name">{{ __('install.first_name') }}</label>
                    <input type="text" id="admin_first_name">
                </div>
                <div class="field">
                    <label for="admin_last_name">{{ __('install.last_name') }}</label>
                    <input type="text" id="admin_last_name">
                </div>
            </div>
            <div class="field-row">
                <div class="field">
                    <label for="admin_name">{{ __('install.username') }}</label>
                    <input type="text" id="admin_name">
                </div>
                <div class="field">
                    <label for="admin_email">{{ __('install.email') }}</label>
                    <input type="email" id="admin_email">
                </div>
            </div>
            <div class="field-row">
                <div class="field">
                    <label for="admin_password">{{ __('install.password_label') }}</label>
                    <input type="password" id="admin_password" placeholder="{{ __('install.password_placeholder') }}">
                </div>
                <div class="field">
                    <label for="admin_password_confirm">{{ __('install.password_confirm') }}</label>
                    <input type="password" id="admin_password_confirm" placeholder="{{ __('install.password_confirm_placeholder') }}">
                </div>
            </div>
            <div class="error-box" id="adminError"></div>
        </div>

        <!-- Step 5: Installing -->
        <div class="step-content" id="step-4">
            <div class="step-title" id="installTitle">{{ __('install.installing') }}</div>
            <div class="step-desc" id="installDesc">{{ __('install.installing_desc') }}</div>

            <ul class="install-steps" id="installSteps"></ul>

            <div class="error-box" id="installError"></div>
            <div class="retry-row" id="retryRow" style="display:none">
                <button type="button" class="btn btn-primary" id="btnResumeInstall">{{ __('install.retry') }}</button>
            </div>

            <div class="done-screen" id="doneScreen">
                <div class="done-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                </div>
                <h2>{{ __('install.complete_title') }}</h2>
                <p>{{ __('install.complete_desc') }}</p>
                <a href="" class="btn btn-success" id="loginLink">{{ __('install.go_login') }}</a>
            </div>
        </div>

        <!-- Navigation buttons -->
        <div class="btn-row" id="btnRow">
            <button type="button" class="btn btn-secondary" id="btnPrev" style="visibility:hidden">{{ __('install.back') }}</button>
            <button type="button" class="btn btn-primary" id="btnNext">{{ __('install.next') }}</button>
        </div>
    </div>

<script @cspNonce>
(function() {
    var T = @json($jsTranslations);
    var WIZARD_STEPS = 5;
    var current = 0;
    var dbTested = false;
    var installSteps = [];
    var completedSteps = [];
    var installing = false;
    var baseUrl = '{{ url("/") }}';
    var savedState = @json($state);

    // ─── Dots
    var dotsHtml = '';
    for (var i = 0; i < WIZARD_STEPS; i++) dotsHtml += '<div class="step-dot' + (i === 0 ? ' active' : '') + '"></div>';
    document.getElementById('stepDots').innerHTML = dotsHtml;

    // ─── Navigation
    function showWizardStep(n) {
        for (var i = 0; i < WIZARD_STEPS; i++) {
            document.getElementById('step-' + i).classList.toggle('active', i === n);
            var dot = document.getElementById('stepDots').children[i];
            dot.className = 'step-dot' + (i < n ? ' done' : '') + (i === n ? ' active' : '');
        }
        current = n;
        document.getElementById('btnPrev').style.visibility = (n === 0 || n === WIZARD_STEPS - 1) ? 'hidden' : 'visible';
        if (n === WIZARD_STEPS - 1) {
            document.getElementById('btnRow').style.display = 'none';
        } else {
            document.getElementById('btnRow').style.display = 'flex';
            document.getElementById('btnNext').textContent = n === WIZARD_STEPS - 2 ? T.install_now : T.next;
        }
        updateNextDisabled();
    }

    function updateNextDisabled() {
        var btn = document.getElementById('btnNext');
        if (current === 0) {
            var ok = true;
            @foreach($requirements as $req) @if(!$req['ok']) ok = false; @endif @endforeach
            @foreach($permissions as $perm) @if(!$perm['ok']) ok = false; @endif @endforeach
            btn.disabled = !ok;
        } else {
            btn.disabled = false;
        }
    }

    window.nextStep = function() {
        if (current === 1) {
            var driver = document.getElementById('db_driver').value;
            if (!dbTested) {
                testConnection(function() {
                    if (driver === 'sqlite') showWizardStep(current + 1);
                });
                return;
            }
            if (driver !== 'sqlite' && !document.getElementById('db_database').value) {
                var status = document.getElementById('dbStatus');
                status.className = 'db-status error'; status.style.display = 'block';
                status.textContent = T.select_db_error;
                return;
            }
        }
        if (current === 3) {
            if (!validateAdmin()) return;
            showWizardStep(4);
            startInstall();
            return;
        }
        if (current < WIZARD_STEPS - 1) showWizardStep(current + 1);
    };
    window.prevStep = function() { if (current > 0) showWizardStep(current - 1); };

    // ─── DB connect button highlight
    function updateConnectBtn() {
        var btn = document.getElementById('btnTestDb');
        var filled = val('db_host') && val('db_port') && val('db_username') && val('db_password');
        btn.className = 'btn ' + (filled ? 'btn-primary' : 'btn-secondary');
        btn.disabled = !filled;
    }
    ['db_host','db_port','db_username','db_password'].forEach(function(id) {
        document.getElementById(id).addEventListener('input', updateConnectBtn);
    });

    // ─── DB Fields
    window.toggleDbFields = function() {
        var driver = document.getElementById('db_driver').value;
        var isSqlite = driver === 'sqlite';
        document.getElementById('dbFields').classList.toggle('visible', !isSqlite);
        document.getElementById('btnTestSqlite').style.display = isSqlite ? 'inline-flex' : 'none';
        document.getElementById('dbStatusSqlite').style.display = 'none';
        if (driver === 'pgsql') document.getElementById('db_port').value = '5432';
        else if (driver === 'mysql') document.getElementById('db_port').value = '3306';
        dbTested = false;
        var sel = document.getElementById('db_database');
        sel.innerHTML = '<option value="">' + T.select_db_connect_first + '</option>';
        sel.disabled = true;
        document.getElementById('dbSelectField').style.display = 'none';
        var s = document.getElementById('dbStatus'); s.className = 'db-status'; s.style.display = 'none';
    };

    // ─── DB Test
    window.testConnection = function(callback) {
        var driver = document.getElementById('db_driver').value;
        var isSqlite = driver === 'sqlite';
        var btn = document.getElementById(isSqlite ? 'btnTestSqlite' : 'btnTestDb');
        var status = document.getElementById(isSqlite ? 'dbStatusSqlite' : 'dbStatus');
        btn.disabled = true; btn.textContent = isSqlite ? T.testing : T.connecting; status.style.display = 'none';

        var body = { driver: driver };
        if (!isSqlite) {
            body.host = val('db_host'); body.port = val('db_port');
            body.username = val('db_username'); body.password = val('db_password');
        }

        post(baseUrl + '/install/test-db', body, function(ok, data) {
            btn.disabled = false;
            if (isSqlite) {
                btn.textContent = T.test_btn;
                if (ok && data.success) {
                    status.className = 'db-status success'; status.style.display = 'block';
                    status.textContent = T.connected + ' ' + (data.version || '');
                    dbTested = true;
                    if (callback) callback();
                } else {
                    status.className = 'db-status error'; status.style.display = 'block';
                    status.textContent = T.conn_failed + ': ' + (data.error || '');
                }
                return;
            }

            btn.textContent = T.connect_btn;
            if (ok && data.success) {
                status.className = 'db-status success'; status.style.display = 'block';
                status.textContent = T.connected + ' ' + (data.version || '');

                var sel = document.getElementById('db_database');
                var dbs = data.databases || [];
                sel.innerHTML = '';
                if (dbs.length === 0) {
                    sel.innerHTML = '<option value="">' + T.no_empty_db + '</option>';
                    sel.disabled = true;
                    dbTested = false;
                    status.className = 'db-status error'; status.style.display = 'block';
                    status.textContent = T.no_empty_db_msg;
                } else {
                    sel.innerHTML = '<option value="">' + T.select_db_placeholder + '</option>';
                    for (var i = 0; i < dbs.length; i++) {
                        var opt = document.createElement('option');
                        opt.value = dbs[i]; opt.textContent = dbs[i];
                        sel.appendChild(opt);
                    }
                    sel.disabled = false;
                    sel.onchange = function() { dbTested = !!sel.value; };
                }
                document.getElementById('dbSelectField').style.display = 'block';
            } else {
                status.className = 'db-status error'; status.style.display = 'block';
                status.textContent = T.conn_failed + ': ' + (data.error || '');
                document.getElementById('dbSelectField').style.display = 'none';
            }
        });
    };

    // ─── Admin validation
    function validateAdmin() {
        var err = document.getElementById('adminError');
        var fn = val('admin_first_name'), ln = val('admin_last_name'), n = val('admin_name'), e = val('admin_email'), p = val('admin_password'), p2 = val('admin_password_confirm');
        if (!fn || !ln || !n || !e || !p) { err.textContent = T.err_required; err.style.display = 'block'; return false; }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(e)) { err.textContent = T.err_email; err.style.display = 'block'; return false; }
        // Like the installation's password rules: 10 characters, upper/lower case, digit, special character
        if (p.length < 10 || !/[a-z]/.test(p) || !/[A-Z]/.test(p) || !/\d/.test(p) || !/[^A-Za-z0-9]/.test(p)) { err.textContent = T.err_password_short; err.style.display = 'block'; return false; }
        if (p !== p2) { err.textContent = T.err_password_match; err.style.display = 'block'; return false; }
        err.style.display = 'none'; return true;
    }

    // ─── Install
    function startInstall() {
        var driver = document.getElementById('db_driver').value;
        var body = {
            db_driver: driver,
            site_name: val('site_name'),
            site_url: val('site_url').replace(/\/+$/, ''),
            locale: document.getElementById('locale').value,
            admin_first_name: val('admin_first_name'),
            admin_last_name: val('admin_last_name'),
            admin_name: val('admin_name'),
            admin_email: val('admin_email'),
            admin_password: val('admin_password'),
            seed_pages: checked('seed_pages') ? 1 : 0,
            seed_categories: checked('seed_categories') ? 1 : 0,
            seed_blog: checked('seed_blog') ? 1 : 0,
        };
        if (driver !== 'sqlite') {
            body.db_host = val('db_host'); body.db_port = val('db_port');
            body.db_database = val('db_database'); body.db_username = val('db_username'); body.db_password = val('db_password');
        }

        post(baseUrl + '/install/save', body, function(ok, data) {
            if (ok && data.success) {
                installSteps = data.steps;
                completedSteps = [];
                renderSteps();
                runNextStep();
            } else {
                showInstallError(data.error || T.err_save);
            }
        });
    }

    function runNextStep() {
        installing = true;
        var next = null;
        for (var i = 0; i < installSteps.length; i++) {
            if (completedSteps.indexOf(installSteps[i].id) === -1) { next = installSteps[i]; break; }
        }
        if (!next) { installing = false; return; }

        setStepStatus(next.id, 'running');
        document.getElementById('installDesc').textContent = next.label + '...';

        post(baseUrl + '/install/step', {}, function(ok, data) {
            if (ok && data.success) {
                completedSteps.push(data.step);
                setStepStatus(data.step, 'done');
                if (data.done) {
                    installing = false;
                    document.getElementById('installDesc').textContent = T.all_done;
                    document.getElementById('installSteps').style.display = 'none';
                    document.getElementById('doneScreen').style.display = 'block';
                    document.getElementById('loginLink').href = data.redirect;
                } else {
                    runNextStep();
                }
            } else {
                installing = false;
                setStepStatus(data.step || next.id, 'error', data.error);
                showInstallError(data.error || T.err_generic);
                document.getElementById('retryRow').style.display = 'block';
                document.getElementById('installDesc').textContent = T.paused;
            }
        });
    }

    window.resumeInstall = function() {
        document.getElementById('installError').style.display = 'none';
        document.getElementById('retryRow').style.display = 'none';
        runNextStep();
    };

    // ─── Step UI
    function renderSteps() {
        var ul = document.getElementById('installSteps');
        ul.innerHTML = ''; ul.style.display = 'block';
        for (var i = 0; i < installSteps.length; i++) {
            var s = installSteps[i];
            var isDone = completedSteps.indexOf(s.id) !== -1;
            var li = document.createElement('li');
            li.id = 'is-' + s.id;
            li.innerHTML = '<span class="s-icon ' + (isDone ? 'done' : 'waiting') + '">' + (isDone ? '&#10003;' : '&bull;') + '</span><span class="s-label ' + (isDone ? 'done' : '') + '">' + s.label + '</span>';
            ul.appendChild(li);
        }
    }

    function setStepStatus(id, status, error) {
        var li = document.getElementById('is-' + id);
        if (!li) return;
        var icon = li.querySelector('.s-icon');
        var label = li.querySelector('.s-label');
        icon.className = 's-icon ' + status;
        label.className = 's-label ' + status;
        if (status === 'running') icon.innerHTML = '<div class="spinner-ring"></div>';
        else if (status === 'done') icon.innerHTML = '&#10003;';
        else if (status === 'error') {
            icon.innerHTML = '&#10007;';
            if (error) { var d = document.createElement('div'); d.className = 's-error'; d.textContent = error; li.appendChild(d); }
        }
    }

    function showInstallError(msg) {
        var box = document.getElementById('installError');
        box.textContent = msg; box.style.display = 'block';
    }

    // ─── Resume from saved state
    if (savedState && savedState.started) {
        installSteps = savedState.steps || [];
        completedSteps = savedState.completed || [];
        showWizardStep(4);
        renderSteps();
        if (savedState.error) {
            showInstallError(savedState.error);
            document.getElementById('retryRow').style.display = 'block';
            document.getElementById('installDesc').textContent = T.paused_resume;
        } else {
            runNextStep();
        }
    }

    // ─── Utilities
    function val(id) { return (document.getElementById(id).value || '').trim(); }
    function checked(id) { return document.getElementById(id).checked; }
    function post(url, body, cb) {
        fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: JSON.stringify(body) })
        .then(function(r) { return r.json().then(function(d) { cb(r.ok, d); }); })
        .catch(function(e) { cb(false, { error: T.err_network + ': ' + e.message }); });
    }

    updateNextDisabled();

    // Wire controls (were inline onchange/onclick — removed for the nonce-based CSP)
    document.getElementById('db_driver').addEventListener('change', function() { toggleDbFields(); });
    ['btnTestDb', 'btnTestSqlite'].forEach(function(id) {
        var btn = document.getElementById(id);
        if (btn) btn.addEventListener('click', function() { testConnection(); });
    });
    var btnResume = document.getElementById('btnResumeInstall');
    if (btnResume) btnResume.addEventListener('click', function() { resumeInstall(); });
    document.getElementById('btnPrev').addEventListener('click', function() { prevStep(); });
    document.getElementById('btnNext').addEventListener('click', function() { nextStep(); });
})();
</script>
</body>
</html>
