<?php
/**
 * Kurulum sihirbazı. Kurulum tamamlandıktan sonra kendini kilitler.
 */

declare(strict_types=1);

define('APP_INSTALLER', true);
require __DIR__ . '/app/bootstrap.php';

if (is_file(APP_DATA . '/config.php')) {
    redirect(base_url('admin/'));
}

session_boot();

$checks = [
    ['PHP 8.0+', PHP_VERSION_ID >= 80000, PHP_VERSION],
    ['PDO', extension_loaded('pdo'), ''],
    ['PDO SQLite / MySQL', extension_loaded('pdo_sqlite') || extension_loaded('pdo_mysql'), implode(', ', array_filter([extension_loaded('pdo_sqlite') ? 'SQLite' : '', extension_loaded('pdo_mysql') ? 'MySQL' : '']))],
    ['cURL', extension_loaded('curl') || ini_get('allow_url_fopen'), extension_loaded('curl') ? '' : 'allow_url_fopen'],
    ['mbstring', extension_loaded('mbstring'), ''],
    [t('install.writable', ['d' => 'data/']), is_writable(APP_DATA), ''],
];
$ready = !in_array(false, array_column($checks, 1), true);

$errors = [];
$v = [
    'driver'   => post_str('driver', extension_loaded('pdo_sqlite') ? 'sqlite' : 'mysql'),
    'host'     => post_str('host', 'localhost'),
    'port'     => post_str('port', '3306'),
    'name'     => post_str('name'),
    'user'     => post_str('user'),
    'prefix'   => post_str('prefix', APP_SLUG . '_'),
    'title'    => post_str('title', ''),
    'username' => post_str('username', 'admin'),
    'lang'     => post_str('lang', Lang::$code),
];

if (is_post() && $ready) {
    csrf_check();
    $pass  = (string) ($_POST['password'] ?? '');
    $pass2 = (string) ($_POST['password2'] ?? '');

    if (!preg_match('/^[a-zA-Z0-9_.-]{3,32}$/', $v['username'])) {
        $errors[] = t('install.err_username');
    }
    if (strlen($pass) < 8) {
        $errors[] = t('install.err_password');
    } elseif ($pass !== $pass2) {
        $errors[] = t('install.err_match');
    }
    if ($v['title'] === '') {
        $errors[] = t('install.err_title');
    }

    if ($v['driver'] === 'mysql') {
        if (!preg_match('/^[a-zA-Z0-9_]{0,20}$/', $v['prefix'])) {
            $errors[] = t('install.err_prefix');
        }
        $db = [
            'driver' => 'mysql', 'host' => $v['host'], 'port' => (int) $v['port'], 'name' => $v['name'],
            'user'   => $v['user'], 'pass' => (string) ($_POST['pass'] ?? ''), 'prefix' => $v['prefix'],
        ];
    } else {
        $db = ['driver' => 'sqlite', 'path' => APP_DATA . '/' . APP_SLUG . '-' . random_token(6) . '.sqlite', 'prefix' => ''];
    }

    if (!$errors) {
        try {
            DB::connect($db);
            DB::migrate();
            DB::insert('users', ['username' => $v['username'], 'password_hash' => password_hash($pass, PASSWORD_DEFAULT), 'created_at' => time()]);
            $lang = isset(Lang::AVAILABLE[$v['lang']]) ? $v['lang'] : 'tr';
            $settings = array_merge(Product::defaults(), [
                'site_title'   => $v['title'],
                'lang'         => $lang,
                'timezone'     => 'Europe/Istanbul',
                'site_url'     => rtrim(absolute_url(''), '/'),
                'installed_at' => (string) time(),
            ]);
            foreach ($settings as $k => $val) {
                DB::setSetting($k, $val);
            }
            Product::seed($lang);

            $config = [
                'db'       => $db,
                'cron_key' => random_token(16),
            ];
            $php = "<?php\n// ' . APP_NAME . ' yapılandırması – bu dosyayı paylaşmayın.\nreturn " . var_export($config, true) . ";\n";
            if (file_put_contents(APP_DATA . '/config.php', $php, LOCK_EX) === false) {
                throw new RuntimeException(t('install.err_write'));
            }
            @chmod(APP_DATA . '/config.php', 0600);

            Auth::attempt($v['username'], $pass);
            flash(t('install.done'));
            redirect(base_url('admin/'));
        } catch (Throwable $e) {
            if (($db['driver'] ?? '') === 'sqlite' && isset($db['path']) && is_file($db['path'])) {
                @unlink($db['path']);
            }
            $errors[] = t('install.err_db') . ' ' . $e->getMessage();
        }
    }
}

View::head(t('install.title') . ' · ' . APP_NAME, 'auth');
?>
<div class="auth-wrap">
    <div class="auth-card auth-card--wide">
        <div class="auth-brand">
            <?= View::logo(40) ?>
            <div>
                <h1><?= e(APP_NAME) ?></h1>
                <p><?= e(t('install.subtitle')) ?></p>
            </div>
            <div class="auth-lang">
                <?php foreach (Lang::AVAILABLE as $code => $name) : ?>
                    <a href="?lang=<?= e($code) ?>" class="<?= Lang::$code === $code ? 'is-active' : '' ?>"><?= e(strtoupper($code)) ?></a>
                <?php endforeach; ?>
            </div>
        </div>

        <ul class="reqs">
            <?php foreach ($checks as [$label, $ok, $info]) : ?>
                <li class="<?= $ok ? 'ok' : 'bad' ?>"><?= View::icon($ok ? 'check' : 'x', 16) ?><span><?= e($label) ?></span><small><?= e($info) ?></small></li>
            <?php endforeach; ?>
        </ul>

        <?php if (!$ready) : ?>
            <div class="alert is-down"><?= e(t('install.not_ready')) ?></div>
        <?php else : ?>
            <?php foreach ($errors as $err) : ?><div class="alert is-down"><?= e($err) ?></div><?php endforeach; ?>

            <form method="post" class="form" autocomplete="off">
                <?= csrf_field() ?>
                <input type="hidden" name="lang" value="<?= e(Lang::$code) ?>">

                <fieldset>
                    <legend><?= e(t('install.db')) ?></legend>
                    <div class="seg" role="radiogroup">
                        <?php if (extension_loaded('pdo_sqlite')) : ?>
                            <label><input type="radio" name="driver" value="sqlite" <?= $v['driver'] === 'sqlite' ? 'checked' : '' ?> data-db-switch><span><strong>SQLite</strong><small><?= e(t('install.sqlite_desc')) ?></small></span></label>
                        <?php endif; ?>
                        <?php if (extension_loaded('pdo_mysql')) : ?>
                            <label><input type="radio" name="driver" value="mysql" <?= $v['driver'] === 'mysql' ? 'checked' : '' ?> data-db-switch><span><strong>MySQL / MariaDB</strong><small><?= e(t('install.mysql_desc')) ?></small></span></label>
                        <?php endif; ?>
                    </div>
                    <div class="grid-2" data-mysql <?= $v['driver'] === 'mysql' ? '' : 'hidden' ?>>
                        <label class="field"><span><?= e(t('install.db_host')) ?></span><input name="host" value="<?= e($v['host']) ?>"></label>
                        <label class="field"><span><?= e(t('install.db_port')) ?></span><input name="port" value="<?= e($v['port']) ?>" inputmode="numeric"></label>
                        <label class="field"><span><?= e(t('install.db_name')) ?></span><input name="name" value="<?= e($v['name']) ?>"></label>
                        <label class="field"><span><?= e(t('install.db_prefix')) ?></span><input name="prefix" value="<?= e($v['prefix']) ?>"></label>
                        <label class="field"><span><?= e(t('install.db_user')) ?></span><input name="user" value="<?= e($v['user']) ?>"></label>
                        <label class="field"><span><?= e(t('install.db_pass')) ?></span><input name="pass" type="password"></label>
                    </div>
                </fieldset>

                <fieldset>
                    <legend><?= e(t('install.site')) ?></legend>
                    <label class="field"><span><?= e(t('settings.site_title')) ?></span><input name="title" value="<?= e($v['title']) ?>" required placeholder="<?= e(t('install.title_ph')) ?>"><small><?= e(t('install.title_hint')) ?></small></label>
                </fieldset>

                <fieldset>
                    <legend><?= e(t('install.admin')) ?></legend>
                    <label class="field"><span><?= e(t('login.username')) ?></span><input name="username" value="<?= e($v['username']) ?>" required autocomplete="username"></label>
                    <div class="grid-2">
                        <label class="field"><span><?= e(t('login.password')) ?></span><input name="password" type="password" required minlength="8" autocomplete="new-password"></label>
                        <label class="field"><span><?= e(t('install.password2')) ?></span><input name="password2" type="password" required minlength="8" autocomplete="new-password"></label>
                    </div>
                </fieldset>

                <button type="submit" class="btn btn-primary btn-lg btn-block"><?= e(t('install.submit')) ?></button>
            </form>
        <?php endif; ?>

        <p class="auth-credit"><?= View::credit() ?></p>
    </div>
</div>
<?php View::foot(); ?>
