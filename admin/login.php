<?php

declare(strict_types=1);

define('APP_DEPTH', 1);
require dirname(__DIR__) . '/app/bootstrap.php';

session_boot();
if (Auth::user()) {
    redirect(base_url('admin/'));
}

$error = '';
if (is_post()) {
    csrf_check();
    if (Auth::blocked()) {
        $error = t('login.blocked');
    } elseif (Auth::attempt(post_str('username'), (string) ($_POST['password'] ?? ''))) {
        redirect(base_url('admin/'));
    } else {
        $error = Auth::blocked() ? t('login.blocked') : t('login.failed');
    }
}

View::head(t('login.title') . ' · ' . DB::setting('site_title', APP_NAME), 'auth');
?>
<div class="auth-wrap">
    <div class="auth-card">
        <div class="auth-brand">
            <?= View::logo(40) ?>
            <div>
                <h1><?= e(APP_NAME) ?></h1>
                <p><?= e(DB::setting('site_title', '')) ?></p>
            </div>
            <div class="auth-lang">
                <?php foreach (Lang::AVAILABLE as $code => $name) : ?>
                    <a href="?lang=<?= e($code) ?>" class="<?= Lang::$code === $code ? 'is-active' : '' ?>"><?= e(strtoupper($code)) ?></a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php if ($error) : ?><div class="alert is-down"><?= e($error) ?></div><?php endif; ?>
        <form method="post" class="form">
            <?= csrf_field() ?>
            <label class="field"><span><?= e(t('login.username')) ?></span><input name="username" required autofocus autocomplete="username" value="<?= e(post_str('username')) ?>"></label>
            <label class="field"><span><?= e(t('login.password')) ?></span><input name="password" type="password" required autocomplete="current-password"></label>
            <button type="submit" class="btn btn-primary btn-lg btn-block"><?= e(t('login.submit')) ?></button>
        </form>
        <p class="auth-credit"><?= View::credit() ?></p>
    </div>
</div>
<?php View::foot(); ?>
