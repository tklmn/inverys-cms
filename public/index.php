<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

/*
|--------------------------------------------------------------------------
| Pre-Boot Dependency Installer
|--------------------------------------------------------------------------
| If vendor/autoload.php doesn't exist, Laravel cannot start.
| If built assets are missing and the CMS hasn't been installed yet,
| the bootstrap page must run npm build before the wizard can proceed.
|
*/
$hasVendor = file_exists(__DIR__.'/../vendor/autoload.php');
$hasAssets = file_exists(__DIR__.'/../public/build/manifest.json') || file_exists(__DIR__.'/../public/hot');
$isInstalled = file_exists(__DIR__.'/../storage/installed');

if (! $hasVendor || (! $hasAssets && ! $isInstalled)) {
    require __DIR__.'/../resources/views/install/bootstrap.php';
}
unset($hasVendor, $hasAssets, $isInstalled);

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
