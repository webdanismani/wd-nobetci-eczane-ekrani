<?php
/**
 * Nöbetçi eczane TV ekranı. Vitrindeki TV'de tam ekran açın: ekran.php?k=EKRAN_ANAHTARI
 */

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

if (!Product::screenAllowed()) {
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    exit('<!doctype html><meta charset="utf-8"><title>403</title><body style="font:20px sans-serif;background:#cf1f2b;color:#fff;display:grid;place-items:center;height:100vh;margin:0"><p>' . e(t('screen.forbidden')) . '</p>');
}

$data = Duty::payload();
$api  = base_url('api.php') . (DB::setting('screen_public', '0') === '1' ? '' : '?k=' . rawurlencode(get_str('k')));
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');
?><!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= e($data['self']['name']) ?> · Nöbetçi Eczaneler</title>
<link rel="icon" href="<?= e(base_url('assets/favicon.svg')) ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= e(asset('fonts.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('screen.css')) ?>">
</head>
<body class="scr theme-<?= e($data['screen']['theme']) ?>" data-api="<?= e($api) ?>">
<div class="e" data-screen>
    <header class="e-head">
        <svg class="e-mark" viewBox="0 0 48 48" aria-hidden="true"><rect x="1.5" y="1.5" width="45" height="45" rx="9" fill="#fff"/><path d="M17 12h15M17 12v24h15M17 24h12" fill="none" stroke="#cf1f2b" stroke-width="4.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
        <h1><?= e($data['self']['name']) ?></h1>
        <span class="e-status"></span>
        <span class="e-clock"></span>
    </header>
    <div class="e-title"><h2>Nöbetçi Eczaneler</h2><p></p><span class="e-place"></span></div>
    <main class="e-body"></main>
    <footer class="e-foot">
        <span class="e-112"><b>112</b>Acil</span>
        <span class="e-note"></span>
        <div class="e-ticker" hidden><span></span></div>
        <span class="e-src"></span>
    </footer>
</div>
<noscript><p style="padding:40px;font-size:24px"><?= e(t('screen.nojs')) ?></p></noscript>
<script id="initial" type="application/json"><?= json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script src="<?= e(asset('qrcode.js')) ?>"></script>
<script src="<?= e(asset('screen.js')) ?>"></script>
</body>
</html>
