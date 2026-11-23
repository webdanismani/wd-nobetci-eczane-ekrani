<?php

declare(strict_types=1);

/** @var array $user */

if (is_post()) {
    csrf_check();
    $action = post_str('action');

    if ($action === 'password') {
        $new = (string) ($_POST['new'] ?? '');
        if ($new !== (string) ($_POST['new2'] ?? '')) {
            flash(t('install.err_match'), 'error');
        } elseif ($err = Auth::changePassword((int) $user['id'], (string) ($_POST['current'] ?? ''), $new)) {
            flash($err, 'error');
        } else {
            flash(t('account.pw_saved'));
        }
    } elseif ($action === 'general') {
        $title = post_str('site_title');
        $tz    = post_str('timezone');
        $lang  = post_str('lang');
        if ($title !== '') {
            DB::setSetting('site_title', mb_substr($title, 0, 120));
        }
        if (in_array($tz, timezone_identifiers_list(), true)) {
            DB::setSetting('timezone', $tz);
        }
        if (isset(Lang::AVAILABLE[$lang])) {
            DB::setSetting('lang', $lang);
            setcookie(APP_SLUG . '_lang', $lang, ['expires' => time() + 31536000, 'path' => '/', 'samesite' => 'Lax']);
        }
        Product::touch();
        flash(t('ui.saved'));
    }
    redirect(base_url('admin/?p=account'));
}

$zones = array_filter(timezone_identifiers_list(), fn ($z) => preg_match('~^(Europe|Asia|Africa|America|Australia|Pacific|Atlantic|Indian|UTC)~', $z));

View::adminStart(t('nav.account'), 'account');
?>
<div class="split-2">
    <section class="panel">
        <div class="panel-head"><h2><?= View::icon('gear') ?><?= e(t('account.general')) ?></h2></div>
        <form method="post" class="form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="general">
            <label class="field"><span><?= e(t('settings.site_title')) ?></span><input name="site_title" value="<?= e(DB::setting('site_title', '')) ?>" required maxlength="120"><small><?= e(t('install.title_hint')) ?></small></label>
            <div class="grid-2">
                <label class="field"><span><?= e(t('account.lang')) ?></span>
                    <select name="lang"><?php foreach (Lang::AVAILABLE as $c => $n) : ?><option value="<?= e($c) ?>" <?= DB::setting('lang', 'tr') === $c ? 'selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?></select>
                </label>
                <label class="field"><span><?= e(t('account.timezone')) ?></span>
                    <select name="timezone"><?php foreach ($zones as $z) : ?><option <?= DB::setting('timezone') === $z ? 'selected' : '' ?>><?= e($z) ?></option><?php endforeach; ?></select>
                </label>
            </div>
            <div class="form-actions"><button class="btn btn-primary"><?= e(t('ui.save')) ?></button></div>
        </form>
    </section>
    <section class="panel">
        <div class="panel-head"><h2><?= View::icon('lock') ?><?= e(t('account.password')) ?></h2></div>
        <form method="post" class="form" autocomplete="off">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="password">
            <label class="field"><span><?= e(t('account.current')) ?></span><input type="password" name="current" required autocomplete="current-password"></label>
            <div class="grid-2">
                <label class="field"><span><?= e(t('account.new')) ?></span><input type="password" name="new" required minlength="8" autocomplete="new-password"></label>
                <label class="field"><span><?= e(t('install.password2')) ?></span><input type="password" name="new2" required minlength="8" autocomplete="new-password"></label>
            </div>
            <p class="muted"><?= e(t('account.signed_in', ['u' => $user['username']])) ?></p>
            <div class="form-actions"><button class="btn btn-primary"><?= e(t('account.change')) ?></button></div>
        </form>
    </section>
</div>
<?php View::adminEnd(); ?>
