<?php
/**
 * WD Nöbetçi Eczane Ekranı
 * Geliştirici: Web Danışmanı (https://webdanismani.com)
 * Destek: oblifex.com webmaster destek forumu
 *
 * Ücretsiz paylaşılır; satılamaz. CC BY-NC-SA 4.0
 */

declare(strict_types=1);

define('APP_NAME', 'WD Nöbetçi Eczane Ekranı');
define('APP_SLUG', 'wdeczane');
define('APP_VERSION', '1.0.0');
define('APP_ROOT', dirname(__DIR__));
define('APP_DATA', APP_ROOT . '/data');
define('APP_AUTHOR_URL', 'https://webdanismani.com');
define('APP_SUPPORT_URL', 'https://oblifex.com');

if (!defined('APP_DEPTH')) {
    define('APP_DEPTH', 0);
}

if (PHP_VERSION_ID < 80000) {
    http_response_code(500);
    exit(APP_NAME . ' requires PHP 8.0 or newer.');
}

require __DIR__ . '/helpers.php';
require __DIR__ . '/Lang.php';
require __DIR__ . '/DB.php';
require __DIR__ . '/Auth.php';
require __DIR__ . '/View.php';
require __DIR__ . '/Product.php';

$configFile = APP_DATA . '/config.php';

if (!is_file($configFile)) {
    if (!defined('APP_INSTALLER')) {
        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, APP_NAME . " is not installed yet. Open install.php in your browser.\n");
            exit(1);
        }
        if (defined('APP_JSON')) {
            json_out(['ok' => false, 'error' => 'not_installed'], 503);
        }
        header('Location: ' . base_url('install.php'));
        exit;
    }
    Lang::load(Lang::detect());
    return;
}

App::$config = require $configFile;

try {
    DB::connect(App::$config['db']);
    DB::upgrade();
} catch (Throwable $e) {
    http_response_code(500);
    exit('Database connection failed: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES));
}

date_default_timezone_set((string) DB::setting('timezone', 'Europe/Istanbul'));
Lang::load(PHP_SAPI === 'cli' ? (string) DB::setting('lang', 'tr') : Lang::detect());
