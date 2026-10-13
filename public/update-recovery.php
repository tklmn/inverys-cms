<?php

/*
 * Emergency rollback for a CMS update (System → Updates).
 *
 * If the new version does not start, this script — without Laravel — puts
 * the backed-up files back and ends maintenance mode. It only works while
 * an update is pending (storage/app/cms-updates/pending.json), and only
 * with the key from the link shown on the update page.
 * Texts come from pending.json (in the admin's language).
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$work = $root.'/storage/app/cms-updates';
$pendingFile = $work.'/pending.json';
$pending = is_file($pendingFile) ? json_decode((string) file_get_contents($pendingFile), true) : null;

header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');
header("Content-Security-Policy: default-src 'none'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");

$e = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$token = (string) ($_POST['token'] ?? $_GET['token'] ?? '');

if (! is_array($pending) || ! is_string($pending['token'] ?? null) || $token === '' || ! hash_equals($pending['token'], $token)) {
    http_response_code(404);
    exit('<!doctype html><meta name="robots" content="noindex"><title>404</title>');
}

$texts = (array) ($pending['texts'] ?? []);
$t = static fn (string $key): string => (string) ($texts[$key] ?? $key);

// The backup must be inside the working directory
$backup = realpath((string) ($pending['backup'] ?? ''));
$workReal = realpath($work);
if ($backup === false || $workReal === false || ! str_starts_with($backup, $workReal.DIRECTORY_SEPARATOR)) {
    http_response_code(404);
    exit('<!doctype html><meta name="robots" content="noindex"><title>404</title>');
}

$page = static function (string $title, string $body) use ($e): never {
    echo '<!doctype html><html><head><meta charset="utf-8"><meta name="robots" content="noindex">'
        .'<meta name="viewport" content="width=device-width, initial-scale=1"><title>'.$e($title).'</title></head>'
        .'<body><main><h1>'.$e($title).'</h1>'.$body.'</main></body></html>';
    exit;
};

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    $page($t('recovery_title'), '<p>'.$e($t('recovery_text')).'</p>'
        .'<form method="post"><input type="hidden" name="token" value="'.$e($token).'">'
        .'<button type="submit">'.$e($t('recovery_button')).'</button></form>');
}

$manifest = json_decode((string) @file_get_contents($backup.'/manifest.json'), true);
if (! is_array($manifest) || ! is_array($manifest['paths'] ?? null)) {
    $page($t('recovery_title'), '<p>'.$e($t('recovery_failed')).'</p>');
}

$move = static function (string $from, string $to): void {
    if (! is_dir(dirname($to))) {
        mkdir(dirname($to), 0775, true);
    }
    rename($from, $to);
};

// Like CoreUpdater::restoreFiles: restore backed-up files, move new ones aside
$replaced = $backup.'/replaced-'.bin2hex(random_bytes(3));
foreach (array_reverse($manifest['paths']) as $item) {
    $path = (string) ($item['path'] ?? '');
    if ($path === '' || str_starts_with($path, '/') || in_array('..', explode('/', $path), true)) {
        continue;
    }

    $current = $root.'/'.$path;
    $saved = $backup.'/files/'.$path;
    $hasSaved = file_exists($saved) || is_link($saved);

    if (! $hasSaved && ! empty($item['existed'])) {
        continue;
    }
    if (file_exists($current) || is_link($current)) {
        $move($current, $replaced.'/'.$path);
    }
    if ($hasSaved) {
        $move($saved, $current);
    }
}

foreach (glob($root.'/storage/framework/views/*.php') ?: [] as $file) {
    @unlink($file);
}
@unlink($root.'/storage/framework/down');
@unlink($root.'/storage/framework/maintenance.php');
@unlink($pendingFile);
if (function_exists('opcache_reset')) {
    @opcache_reset();
}

$page($t('recovery_title'), '<p>'.$e($t('recovery_done')).'</p>'
    .(! empty($pending['database']) ? '<p>'.$e($t('recovery_database')).' <code>'.$e($pending['database']).'</code></p>' : ''));
