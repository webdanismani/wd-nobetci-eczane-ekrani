<?php

declare(strict_types=1);

$test = null;
if (is_post()) {
    csrf_check();
    $src = post_str('source') === 'json' ? 'json' : 'manual';
    $url = post_str('json_url');
    if ($src === 'json' && !preg_match('~^https?://~i', $url)) {
        flash(t('source.err_url'), 'error');
        $src = 'manual';
    }
    DB::setSetting('source', $src);
    DB::setSetting('json_url', mb_substr($url, 0, 500));
    DB::setSetting('json_header', mb_substr(post_str('json_header'), 0, 300));
    DB::setSetting('json_refresh', max(5, min(720, post_int('json_refresh', 30))));
    DB::setSetting('json_cache', '');
    Product::touch();
    if (post_str('do') === 'test' && $src === 'json') {
        $test = Duty::fetch(Duty::dutyDate());
    } else {
        flash(t('ui.saved'));
        redirect(base_url('admin/?p=source'));
    }
}

$s   = fn (string $k, string $d = '') => (string) DB::setting($k, $d);
$log = DB::all('SELECT * FROM {fetch_log} ORDER BY id DESC LIMIT 20');
$cronUrl = absolute_url('cron.php?key=' . (App::$config['cron_key'] ?? ''));
$example = '{"data":[{"name":"Hayat Eczanesi","district":"Caferağa","address":"Lale Sok. No: 5","phone":"0216 123 45 67","lat":40.9881,"lng":29.0278}]}';

View::adminStart(t('nav.source'), 'source');
?>
<div class="split-2 split-form">
    <section class="panel">
        <div class="panel-head"><h2><?= View::icon('database') ?><?= e(t('source.title')) ?></h2></div>
        <form method="post" class="form">
            <?= csrf_field() ?>
            <div class="seg">
                <label><input type="radio" name="source" value="manual" <?= $s('source', 'manual') === 'manual' ? 'checked' : '' ?>><span><strong><?= e(t('source.manual')) ?></strong><small><?= e(t('source.manual_desc')) ?></small></span></label>
                <label><input type="radio" name="source" value="json" <?= $s('source') === 'json' ? 'checked' : '' ?>><span><strong><?= e(t('source.json')) ?></strong><small><?= e(t('source.json_desc')) ?></small></span></label>
            </div>
            <div class="form" data-when="source=json">
                <label class="field"><span><?= e(t('source.url')) ?></span><input name="json_url" value="<?= e($s('json_url')) ?>" placeholder="https://api.ornek.com/nobetci?il={il}&ilce={ilce}&tarih={tarih}"><small><?= e(t('source.url_hint')) ?></small></label>
                <div class="grid-2">
                    <label class="field"><span><?= e(t('source.header')) ?> <em class="muted">(<?= e(t('ui.optional')) ?>)</em></span><input name="json_header" value="<?= e($s('json_header')) ?>" placeholder="Authorization: apikey ..." autocomplete="off"><small><?= e(t('source.header_hint')) ?></small></label>
                    <label class="field"><span><?= e(t('source.refresh')) ?></span><span class="input-suffix"><input name="json_refresh" value="<?= e($s('json_refresh', '30')) ?>" inputmode="numeric"><em><?= e(t('source.minutes')) ?></em></span></label>
                </div>
                <div class="snippet"><h3><?= e(t('source.format')) ?></h3><p class="muted"><?= e(t('source.format_desc')) ?></p><pre class="code-block"><?= e($example) ?></pre></div>
            </div>
            <div class="form-actions">
                <button class="btn" name="do" value="test" data-when="source=json"><?= View::icon('refresh', 15) ?><span><?= e(t('source.test')) ?></span></button>
                <button class="btn btn-primary" name="do" value="save"><?= e(t('ui.save')) ?></button>
            </div>
        </form>
    </section>

    <section class="panel">
        <?php if ($test !== null) : ?>
            <div class="panel-head"><h2><?= View::icon($test['ok'] ? 'check' : 'alert') ?><?= e(t('source.test_result')) ?></h2></div>
            <?php if (!$test['ok']) : ?>
                <div class="alert is-down"><?= e($test['message']) ?></div>
                <p class="muted mt"><?= e(t('source.test_url', ['u' => Duty::jsonUrl(Duty::dutyDate())])) ?></p>
            <?php else : ?>
                <p class="muted"><?= e($test['message']) ?></p>
                <div class="table-wrap mt"><table class="table">
                    <tbody>
                    <?php foreach (Duty::decorate($test['list']) as $p) : ?>
                        <tr><td><span class="strong"><?= e($p['name']) ?></span><span class="sub"><?= e(trim($p['district'] . ' · ' . $p['address'], ' ·')) ?></span></td><td class="nowrap"><?= e($p['phone'] ?: '—') ?></td><td class="num"><?= $p['meters'] !== null ? e(Duty::km($p['meters'])) : '—' ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div>
            <?php endif; ?>
        <?php else : ?>
            <div class="panel-head"><h2><?= View::icon('clock') ?><?= e(t('source.log')) ?></h2></div>
            <?php if (!$log) : ?>
                <p class="muted"><?= e(t('source.log_empty')) ?></p>
            <?php else : ?>
                <div class="table-wrap"><table class="table">
                    <tbody>
                    <?php foreach ($log as $l) : ?>
                        <tr><td class="nowrap"><?= e(fmt_dt((int) $l['at'])) ?></td><td><span class="chip <?= $l['ok'] ? 'is-up' : 'is-down' ?>"><?= e($l['ok'] ? t('source.ok') : t('source.fail')) ?></span> <span class="muted"><?= e($l['message']) ?></span></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div>
            <?php endif; ?>
        <?php endif; ?>
        <div class="snippet mt">
            <h3><?= e(t('source.cron')) ?></h3>
            <p class="muted"><?= e(t('source.cron_desc')) ?></p>
            <div class="linkbox mt"><code>*/30 * * * * php <?= e(APP_ROOT) ?>/cron.php</code><button type="button" class="btn btn-sm" data-copy><?= View::icon('copy', 15) ?><span><?= e(t('ui.copy')) ?></span></button></div>
            <div class="linkbox mt"><code><?= e($cronUrl) ?></code><button type="button" class="btn btn-sm" data-copy><?= View::icon('copy', 15) ?><span><?= e(t('ui.copy')) ?></span></button></div>
        </div>
    </section>
</div>
<?php View::adminEnd(); ?>
