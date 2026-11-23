<?php

declare(strict_types=1);

define('APP_DEPTH', 1);
require dirname(__DIR__) . '/app/bootstrap.php';

$user  = Auth::require();
$page  = get_str('p', 'dashboard');
$pages = array_merge(Product::pages(), ['account', 'logout']);

if (!in_array($page, $pages, true)) {
    $page = 'dashboard';
}

if ($page === 'logout') {
    if (is_post()) {
        csrf_check();
        Auth::logout();
    }
    redirect(base_url('admin/login.php'));
}

require __DIR__ . '/pages/' . $page . '.php';
