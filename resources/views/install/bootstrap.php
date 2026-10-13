<?php
/**
 * Pre-boot dependency installer.
 *
 * This file is included by public/index.php when vendor/autoload.php
 * doesn't exist yet. Laravel is NOT loaded — only plain PHP is available.
 *
 * It provides an AJAX endpoint (?bootstrap=run) that runs composer install
 * and npm build, plus a UI page with language support loaded from lang packs.
 */
$basePath = realpath(__DIR__.'/../../../');

// ── AJAX endpoint: run composer install ─────────────────────────
if (isset($_GET['bootstrap']) && $_GET['bootstrap'] === 'run') {
    header('Content-Type: application/json');

    // Find composer
    $composer = null;
    if (file_exists($basePath.'/composer.phar')) {
        $composer = PHP_BINARY.' '.$basePath.'/composer.phar';
    } else {
        exec('which composer 2>/dev/null', $out, $code);
        if ($code === 0 && ! empty($out[0])) {
            $composer = trim($out[0]);
        }
    }

    if (! $composer) {
        echo json_encode(['success' => false, 'error' => 'composer_not_found']);
        exit;
    }

    // Run composer install
    // Ensure HOME is set — web-server users (www-data) often lack it,
    // and Composer refuses to run without HOME or COMPOSER_HOME.
    $home = getenv('HOME') ?: getenv('COMPOSER_HOME') ?: sys_get_temp_dir();
    $envPrefix = 'HOME='.escapeshellarg($home).' COMPOSER_HOME='.escapeshellarg($home.'/.composer');
    $cmd = 'cd '.escapeshellarg($basePath)." && {$envPrefix} {$composer} install --no-dev --optimize-autoloader 2>&1";
    $output = [];
    exec($cmd, $output, $exitCode);

    if ($exitCode !== 0 || ! file_exists($basePath.'/vendor/autoload.php')) {
        echo json_encode(['success' => false, 'error' => 'composer_failed', 'output' => implode("\n", $output)]);
        exit;
    }

    $result = ['success' => true, 'steps' => ['composer' => true]];

    // Check if npm/node exists and built assets are missing
    $needsBuild = ! file_exists($basePath.'/public/build/manifest.json');
    if ($needsBuild) {
        $npm = null;
        exec('which npm 2>/dev/null', $npmOut, $npmCode);
        if ($npmCode === 0 && ! empty($npmOut[0])) {
            $npm = trim($npmOut[0]);
        }

        if ($npm) {
            $npmCmd = 'cd '.escapeshellarg($basePath)." && {$envPrefix} {$npm} install --no-audit --no-fund 2>&1 && {$envPrefix} {$npm} run build 2>&1";
            $npmOutput = [];
            exec($npmCmd, $npmOutput, $npmExit);
            $result['steps']['npm'] = ($npmExit === 0);
            if ($npmExit !== 0) {
                $result['npm_output'] = implode("\n", $npmOutput);
            }
        } else {
            $result['steps']['npm'] = false;
            $result['npm_skipped'] = true;
        }
    } else {
        $result['steps']['npm'] = true;
    }

    echo json_encode($result);
    exit;
}

// ── Detect language ─────────────────────────────────────────────
// English in the core, other languages in language packs (plugins/*/plugin.json
// with "type": "language") — like App\Support\InstallerLanguages, just without Laravel.
$installerLangFiles = [];
foreach (glob($basePath.'/lang/*/install.php') ?: [] as $file) {
    $installerLangFiles[basename(dirname($file))] = $file;
}
foreach (glob($basePath.'/plugins/*/plugin.json') ?: [] as $manifest) {
    $meta = json_decode((string) file_get_contents($manifest), true);
    $code = $meta['locale'] ?? null;
    if (($meta['type'] ?? null) === 'language' && is_string($code) && preg_match('/^[a-z]{2,3}(-[A-Za-z]{2,4})?$/', $code)
        && is_file(dirname($manifest).'/lang/'.$code.'/install.php')) {
        $installerLangFiles[$code] = dirname($manifest).'/lang/'.$code.'/install.php';
    }
}
ksort($installerLangFiles);

$lang = 'en';
if (isset($_GET['lang']) && isset($installerLangFiles[$_GET['lang']])) {
    $lang = $_GET['lang'];
}

// Texts of the language, missing ones from English
$allTranslations = array_replace(require $installerLangFiles['en'], require $installerLangFiles[$lang]);

// Build the subset used by the pre-boot UI (keys starting with 'setup_')
$s = [];
foreach ($allTranslations as $key => $value) {
    if (str_starts_with($key, 'setup_')) {
        // Strip 'setup_' prefix for simpler JS access
        $s[substr($key, 6)] = $value;
    }
}

$availableLangs = array_keys($installerLangFiles);

// Detect state for the UI
$vendorExists = file_exists($basePath.'/vendor/autoload.php');

// Read the SVG logo
$logoPath = $basePath.'/public/images/logo.svg';
$logoSvg = file_exists($logoPath) ? file_get_contents($logoPath) : '';

// ── Render the bootstrap page ───────────────────────────────────
// Laravel (and its CSP middleware) is not loaded on this pre-boot page, so we
// emit a strict, nonce-based Content-Security-Policy here ourselves — matching
// the enforced app policy. style-src keeps 'unsafe-inline' for the inline <style>.
$cspNonce = base64_encode(random_bytes(16));
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$cspNonce}'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self' data:; connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'");
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inverys CMS — <?= htmlspecialchars($s['title']) ?></title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root { --blue: #3b82f6; --indigo: #6366f1; --purple: #7c3aed; --green: #22c55e; --red: #ef4444; --amber: #f59e0b; }
        body { font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; background: #0f172a; color: #e2e8f0; min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 2rem 1rem; }
        body::before { content: ''; position: fixed; inset: 0; background: radial-gradient(ellipse at 50% 0%, rgba(99,102,241,.15) 0%, transparent 60%), radial-gradient(ellipse at 80% 100%, rgba(59,130,246,.1) 0%, transparent 50%); pointer-events: none; }
        .card { position: relative; background: rgba(30,41,59,.8); backdrop-filter: blur(20px); border: 1px solid rgba(148,163,184,.1); border-radius: 20px; width: 100%; max-width: 520px; overflow: hidden; }
        .card-header { padding: 2.5rem 2.5rem 1.5rem; text-align: center; }
        .logo { display: inline-block; margin-bottom: 1.25rem; }
        .logo { display: inline-flex; align-items: baseline; gap: 8px; }
        .logo svg { display: block; height: 28px; width: auto; }
        .logo-product { font-size: 15px; font-weight: 400; line-height: 1; color: #98a1b5; }
        .logo svg [fill="#1E1B4B"] { fill: #F8FAFC; }
        .card-header h1 { font-size: 1.375rem; font-weight: 700; color: #f8fafc; margin-bottom: .375rem; }
        .card-header p { font-size: .8125rem; color: #94a3b8; }
        .card-body { padding: 0 2.5rem 2.5rem; }
        .info { font-size: .8125rem; color: #94a3b8; line-height: 1.7; margin-bottom: 1.75rem; }
        .info strong { color: #cbd5e1; font-weight: 600; }
        .btn { display: flex; align-items: center; justify-content: center; gap: .625rem; width: 100%; padding: .875rem 1.5rem; border-radius: 12px; font-size: .9375rem; font-weight: 600; border: none; cursor: pointer; font-family: inherit; transition: all .2s; color: #fff; background: linear-gradient(135deg, var(--blue), var(--indigo)); box-shadow: 0 4px 16px rgba(99,102,241,.3); }
        .btn:hover:not(:disabled) { box-shadow: 0 6px 24px rgba(99,102,241,.4); transform: translateY(-1px); }
        .btn:disabled { opacity: .5; cursor: not-allowed; transform: none; }
        .status { margin-top: 1.75rem; background: rgba(15,23,42,.5); border: 1px solid rgba(148,163,184,.08); border-radius: 12px; padding: .25rem 0; }
        .status-item { display: flex; align-items: center; gap: .75rem; padding: .875rem 1.25rem; }
        .status-item + .status-item { border-top: 1px solid rgba(148,163,184,.06); }
        .s-icon { width: 28px; height: 28px; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: .75rem; font-weight: 700; }
        .s-icon.waiting { background: rgba(148,163,184,.1); color: #64748b; }
        .s-icon.running { background: rgba(59,130,246,.15); color: var(--blue); }
        .s-icon.done { background: rgba(34,197,94,.15); color: var(--green); }
        .s-icon.warn { background: rgba(245,158,11,.15); color: var(--amber); }
        .s-icon.error { background: rgba(239,68,68,.15); color: var(--red); }
        .s-label { font-size: .8125rem; color: #cbd5e1; }
        .spinner { width: 16px; height: 16px; border: 2px solid rgba(59,130,246,.3); border-top-color: var(--blue); border-radius: 50%; animation: spin .6s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .error-box { background: rgba(239,68,68,.1); border: 1px solid rgba(239,68,68,.2); color: #fca5a5; padding: 1rem 1.25rem; border-radius: 12px; font-size: .8125rem; margin-top: 1.25rem; white-space: pre-wrap; word-break: break-all; max-height: 180px; overflow-y: auto; line-height: 1.6; }
        .warn-box { background: rgba(245,158,11,.1); border: 1px solid rgba(245,158,11,.2); color: #fcd34d; padding: 1rem 1.25rem; border-radius: 12px; font-size: .8125rem; margin-top: 1.25rem; line-height: 1.6; }
        .lang-switch { position: absolute; top: 1.25rem; right: 1.5rem; display: flex; gap: .375rem; }
        .lang-switch a { font-size: .75rem; padding: .25rem .5rem; border-radius: 6px; text-decoration: none; color: #64748b; transition: all .2s; }
        .lang-switch a:hover, .lang-switch a.active { color: #e2e8f0; background: rgba(148,163,184,.12); }
        @media (max-width: 480px) { .card-header, .card-body { padding-left: 1.5rem; padding-right: 1.5rem; } }
    </style>
</head>
<body>
    <div class="card">
        <div class="lang-switch">
            <?php foreach ($availableLangs as $code) { ?>
            <a href="?lang=<?= $code ?>" class="<?= $lang === $code ? 'active' : '' ?>"><?= strtoupper($code) ?></a>
            <?php } ?>
        </div>
        <div class="card-header">
            <div class="logo"><?= $logoSvg ?><span class="logo-product">CMS</span></div>
            <h1><?= htmlspecialchars($s['title']) ?></h1>
            <p><?= htmlspecialchars($vendorExists ? ($s['subtitle_assets'] ?? $s['subtitle']) : $s['subtitle']) ?></p>
        </div>
        <div class="card-body">
            <p class="info"><?= $vendorExists ? ($s['info_assets'] ?? $s['info']) : $s['info'] ?></p>

            <button class="btn" id="btnInstall">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                <?= htmlspecialchars($vendorExists ? ($s['btn_assets'] ?? $s['btn']) : $s['btn']) ?>
            </button>

            <div class="status" id="statusArea" style="display:none">
                <div class="status-item" id="step-composer">
                    <span class="s-icon waiting" id="icon-composer">&bull;</span>
                    <span class="s-label"><?= htmlspecialchars($s['step_composer']) ?></span>
                </div>
                <div class="status-item" id="step-npm">
                    <span class="s-icon waiting" id="icon-npm">&bull;</span>
                    <span class="s-label"><?= htmlspecialchars($s['step_npm']) ?></span>
                </div>
            </div>

            <div id="errorBox" class="error-box" style="display:none"></div>
            <div id="warnBox" class="warn-box" style="display:none"></div>
        </div>
    </div>

<script nonce="<?= htmlspecialchars($cspNonce, ENT_QUOTES) ?>">
var T = <?= json_encode($s) ?>;
var vendorExists = <?= $vendorExists ? 'true' : 'false' ?>;

function runSetup() {
    var btn = document.getElementById('btnInstall');
    btn.disabled = true;
    btn.innerHTML = '<div class="spinner"></div> ' + T.btn_loading;
    document.getElementById('statusArea').style.display = 'block';
    document.getElementById('errorBox').style.display = 'none';
    document.getElementById('warnBox').style.display = 'none';

    // If vendor already exists, show composer as pre-done
    if (vendorExists) {
        setIcon('composer', 'done');
    } else {
        setIcon('composer', 'running');
    }

    fetch('?bootstrap=run')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success) {
                setIcon('composer', 'error');
                var msg = data.error === 'composer_not_found' ? T.err_composer : T.err_composer_failed;
                showError(msg + (data.output ? '\n\n' + data.output : ''));
                btn.disabled = false;
                btn.textContent = T.btn_retry;
                return;
            }

            setIcon('composer', 'done');

            if (data.steps && data.steps.npm === true) {
                setIcon('npm', 'done');
                setTimeout(function() { window.location.reload(); }, 1500);
            } else if (data.npm_skipped) {
                setIcon('npm', 'warn');
                document.getElementById('warnBox').innerHTML = T.warn_npm_manual;
                document.getElementById('warnBox').style.display = 'block';
                btn.disabled = false;
                btn.textContent = T.btn_retry;
            } else {
                setIcon('npm', 'error');
                showError(T.err_npm + (data.npm_output ? '\n\n' + data.npm_output : ''));
                btn.disabled = false;
                btn.textContent = T.btn_retry;
            }
        })
        .catch(function(e) {
            setIcon('composer', 'error');
            showError(T.err_network + ': ' + e.message);
            btn.disabled = false;
            btn.textContent = T.btn_retry;
        });
}

function setIcon(step, status) {
    var el = document.getElementById('icon-' + step);
    el.className = 's-icon ' + status;
    if (status === 'running') el.innerHTML = '<div class="spinner"></div>';
    else if (status === 'done') el.innerHTML = '&#10003;';
    else if (status === 'error') el.innerHTML = '&#10007;';
    else if (status === 'warn') el.innerHTML = '!';
}

function showError(msg) {
    var box = document.getElementById('errorBox');
    box.textContent = msg;
    box.style.display = 'block';
}

// CSP-safe: no inline on* handler on the button.
document.getElementById('btnInstall').addEventListener('click', runSetup);
</script>
</body>
</html>
<?php exit; ?>
