<?php

declare(strict_types=1);

final class View
{
    public static function icon(string $name, int $size = 18): string
    {
        static $p = [
            'grid'     => '<rect x="3.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="13.5" width="7" height="7" rx="1.5"/>',
            'alert'    => '<path d="M10.3 4.2 2.8 17.5A2 2 0 0 0 4.5 20.5h15a2 2 0 0 0 1.7-3L13.7 4.2a2 2 0 0 0-3.4 0Z"/><path d="M12 9.5v4M12 17h.01"/>',
            'gear'     => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1Z"/>',
            'plus'     => '<path d="M12 5v14M5 12h14"/>',
            'logout'   => '<path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3M10 17l5-5-5-5M15 12H3"/>',
            'sun'      => '<circle cx="12" cy="12" r="4"/><path d="M12 2.5v2M12 19.5v2M2.5 12h2M19.5 12h2M5.3 5.3l1.4 1.4M17.3 17.3l1.4 1.4M5.3 18.7l1.4-1.4M17.3 6.7l1.4-1.4"/>',
            'moon'     => '<path d="M20 14.5A8 8 0 0 1 9.5 4a8 8 0 1 0 10.5 10.5Z"/>',
            'check'    => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
            'x'        => '<path d="M6 6l12 12M18 6 6 18"/>',
            'edit'     => '<path d="M4 20h4L19 9a2.8 2.8 0 0 0-4-4L4 16Z"/><path d="m13.5 6.5 4 4"/>',
            'trash'    => '<path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/>',
            'refresh'  => '<path d="M20 11a8 8 0 0 0-14.3-4.9L4 8M4 4v4h4M4 13a8 8 0 0 0 14.3 4.9L20 16M20 20v-4h-4"/>',
            'copy'     => '<rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V5.5A1.5 1.5 0 0 0 14.5 4h-9A1.5 1.5 0 0 0 4 5.5v9A1.5 1.5 0 0 0 5.5 16H8"/>',
            'lock'     => '<rect x="4.5" y="10.5" width="15" height="10" rx="2"/><path d="M8 10.5V7.5a4 4 0 0 1 8 0v3"/>',
            'menu'     => '<path d="M4 7h16M4 12h16M4 17h16"/>',
            'ext'      => '<path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/>',
            'clock'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
            'forum'    => '<path d="M4 5h12a1 1 0 0 1 1 1v7a1 1 0 0 1-1 1H9l-4 3v-3H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z"/><path d="M17 9h3a1 1 0 0 1 1 1v7a1 1 0 0 1-1 1h-1v3l-4-3h-4a1 1 0 0 1-1-1v-1"/>',
            'tv'       => '<rect x="2.5" y="4" width="19" height="13" rx="2"/><path d="M8 21h8M12 17v4"/>',
            'user'     => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
            'list'     => '<path d="M9 6h11M9 12h11M9 18h11M4.5 6h.01M4.5 12h.01M4.5 18h.01"/>',
            'tag'      => '<path d="M3 12V4h8l9.5 9.5a1.5 1.5 0 0 1 0 2.1l-5.9 5.9a1.5 1.5 0 0 1-2.1 0Z"/><circle cx="7.5" cy="8.5" r="1.3"/>',
            'percent'  => '<path d="M19 5 5 19"/><circle cx="6.5" cy="6.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/>',
            'image'    => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="m21 16-5-5L5 20"/>',
            'calendar' => '<rect x="3.5" y="5" width="17" height="15.5" rx="2"/><path d="M3.5 10h17M8 3v4M16 3v4"/>',
            'pin'      => '<path d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21Z"/><circle cx="12" cy="9.5" r="2.5"/>',
            'phone'    => '<path d="M5 4h3.5l1.8 4.5-2.3 1.4a11 11 0 0 0 6.1 6.1l1.4-2.3L20 15.5V19a1.5 1.5 0 0 1-1.6 1.5A16.5 16.5 0 0 1 3.5 5.6 1.5 1.5 0 0 1 5 4Z"/>',
            'download' => '<path d="M12 4v11M7 10l5 5 5-5M5 20h14"/>',
            'upload'   => '<path d="M12 20V9M7 14l5-5 5 5M5 4h14"/>',
            'eye'      => '<path d="M2.5 12S6 5 12 5s9.5 7 9.5 7-3.5 7-9.5 7-9.5-7-9.5-7Z"/><circle cx="12" cy="12" r="3"/>',
            'eye-off'  => '<path d="M3 3l18 18M10.6 5.1A9.7 9.7 0 0 1 12 5c6 0 9.5 7 9.5 7a17 17 0 0 1-2.9 3.9M6.6 6.6A17 17 0 0 0 2.5 12S6 19 12 19a9 9 0 0 0 4.4-1.1M9.9 9.9a3 3 0 0 0 4.2 4.2"/>',
            'arrow-up' => '<path d="M12 19V5M6 11l6-6 6 6"/>',
            'arrow-dn' => '<path d="M12 5v14M6 13l6 6 6-6"/>',
            'drag'     => '<path d="M9 6h.01M15 6h.01M9 12h.01M15 12h.01M9 18h.01M15 18h.01"/>',
            'coin'     => '<ellipse cx="12" cy="12" rx="8.5" ry="8.5"/><ellipse cx="12" cy="12" rx="5" ry="5"/>',
            'swap'     => '<path d="M7 4 3 8l4 4M3 8h14M17 20l4-4-4-4M21 16H7"/>',
            'bowl'     => '<path d="M3 11h18a9 9 0 0 1-18 0Z"/><path d="M8 7c0-1.5 1-2 1-3.5M12 7c0-1.5 1-2 1-3.5M16 7c0-1.5 1-2 1-3.5"/>',
            'spark'    => '<path d="M12 3v4M12 17v4M3 12h4M17 12h4M5.6 5.6l2.8 2.8M15.6 15.6l2.8 2.8M5.6 18.4l2.8-2.8M15.6 8.4l2.8-2.8"/>',
            'cross'    => '<path d="M9 3h6v6h6v6h-6v6H9v-6H3V9h6Z"/>',
            'compass'  => '<circle cx="12" cy="12" r="9"/><path d="m15.5 8.5-2 5-5 2 2-5Z"/>',
            'database' => '<ellipse cx="12" cy="5.5" rx="8" ry="2.5"/><path d="M4 5.5v13c0 1.4 3.6 2.5 8 2.5s8-1.1 8-2.5v-13M4 12c0 1.4 3.6 2.5 8 2.5s8-1.1 8-2.5"/>',
        ];
        $body = $p[$name] ?? '';
        return '<svg class="ico" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $body . '</svg>';
    }

    public static function logo(int $size = 34): string
    {
        return Product::logo($size);
    }

    public static function pill(string $label, string $state = 'idle'): string
    {
        return '<span class="pill is-' . e($state) . '"><span class="dot"></span>' . e($label) . '</span>';
    }

    /* ---------- Sayfa iskeleti ---------- */

    public static function head(string $title, string $bodyClass = ''): void
    {
        ?><!doctype html>
<html lang="<?= e(Lang::$code) ?>" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= e($title) ?></title>
<link rel="icon" href="<?= e(base_url('assets/favicon.svg')) ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= e(asset('admin.css')) ?>">
<script>try{var t=localStorage.getItem('<?= APP_SLUG ?>-theme');if(t)document.documentElement.setAttribute('data-theme',t);}catch(e){}</script>
</head>
<body class="<?= e($bodyClass) ?>">
<?php
    }

    public static function foot(): void
    {
        ?>
<div class="toasts" aria-live="polite"><?php foreach (flashes() as $f) : ?><div class="toast is-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div><?php endforeach; ?></div>
<script src="<?= e(asset('admin.js')) ?>"></script>
</body>
</html>
<?php
    }

    public static function themeButton(): string
    {
        return '<button type="button" class="iconbtn" data-theme-toggle aria-label="' . e(t('ui.theme')) . '">'
            . '<span class="show-light">' . self::icon('moon') . '</span><span class="show-dark">' . self::icon('sun') . '</span></button>';
    }

    public static function credit(): string
    {
        return t('brand.footer', [
            'dev'   => '<a href="' . e(APP_AUTHOR_URL) . '" target="_blank" rel="noopener">Web Danışmanı</a>',
            'forum' => '<a href="' . e(APP_SUPPORT_URL) . '" target="_blank" rel="noopener">oblifex.com</a>',
        ]);
    }

    public static function adminStart(string $title, string $active, string $actions = ''): void
    {
        self::head($title . ' · ' . DB::setting('site_title', APP_NAME), 'admin');
        ?>
<div class="shell">
    <aside class="side" data-side>
        <a class="brand" href="<?= e(base_url('admin/')) ?>">
            <?= self::logo() ?>
            <span><strong><?= e(APP_NAME) ?></strong><small><?= e(DB::setting('site_title', '')) ?></small></span>
        </a>
        <nav class="nav">
            <?php foreach (Product::nav() as $key => [$label, $icon, $href]) : ?>
                <a href="<?= e($href) ?>" class="<?= $active === $key ? 'is-active' : '' ?>"><?= self::icon($icon) ?><span><?= e(t($label)) ?></span></a>
            <?php endforeach; ?>
            <a href="<?= e(base_url('admin/?p=account')) ?>" class="<?= $active === 'account' ? 'is-active' : '' ?>"><?= self::icon('user') ?><span><?= e(t('nav.account')) ?></span></a>
        </nav>
        <div class="side-foot">
            <?= Product::sideNote() ?>
            <a class="credit" href="<?= e(APP_SUPPORT_URL) ?>" target="_blank" rel="noopener"><?= self::icon('forum', 16) ?><span><?= e(t('brand.support')) ?></span></a>
            <a class="credit" href="<?= e(APP_AUTHOR_URL) ?>" target="_blank" rel="noopener"><span class="by"><?= e(t('brand.by')) ?> <strong>Web Danışmanı</strong></span></a>
        </div>
    </aside>
    <div class="main">
        <header class="topbar">
            <button type="button" class="iconbtn menu-btn" data-menu aria-label="<?= e(t('ui.menu')) ?>"><?= self::icon('menu') ?></button>
            <h1><?= e($title) ?></h1>
            <div class="topbar-actions">
                <?= $actions ?>
                <?= self::themeButton() ?>
                <form method="post" action="<?= e(base_url('admin/?p=logout')) ?>"><?= csrf_field() ?><button type="submit" class="iconbtn" aria-label="<?= e(t('nav.logout')) ?>" title="<?= e(t('nav.logout')) ?>"><?= self::icon('logout') ?></button></form>
            </div>
        </header>
        <main class="content">
<?php
    }

    public static function adminEnd(): void
    {
        ?>
        </main>
        <footer class="appfoot">
            <span><?= e(APP_NAME) ?> <?= e(APP_VERSION) ?> · <?= e(t('brand.free')) ?></span>
            <span><?= self::credit() ?></span>
        </footer>
    </div>
</div>
<div class="modal" data-modal hidden>
    <div class="modal-box" role="dialog" aria-modal="true">
        <h2><?= e(t('ui.confirm_title')) ?></h2>
        <p data-modal-text></p>
        <div class="modal-actions">
            <button type="button" class="btn" data-modal-no><?= e(t('ui.cancel')) ?></button>
            <button type="button" class="btn btn-danger" data-modal-yes><?= e(t('ui.confirm')) ?></button>
        </div>
    </div>
</div>
<?php
        self::foot();
    }

    /** Ekran bağlantısı kutusu: TV tarayıcısına yazılacak adres. */
    public static function screenLink(string $url, string $openLabel = ''): string
    {
        return '<div class="linkbox"><code>' . e($url) . '</code>'
            . '<button type="button" class="btn btn-sm" data-copy>' . self::icon('copy', 15) . '<span>' . e(t('ui.copy')) . '</span></button>'
            . '<a class="btn btn-sm btn-primary" href="' . e($url) . '" target="_blank" rel="noopener">' . self::icon('tv', 15) . '<span>' . e($openLabel ?: t('ui.open_screen')) . '</span></a></div>';
    }
}
