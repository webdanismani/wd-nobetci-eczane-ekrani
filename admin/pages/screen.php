<?php

declare(strict_types=1);

if (is_post()) {
    csrf_check();
    $theme = post_str('screen_theme');
    DB::setSetting('screen_theme', isset(Product::themes()[$theme]) ? $theme : 'auto');
    DB::setSetting('screen_limit', max(1, min(12, post_int('screen_limit', 6))));
    DB::setSetting('screen_qr', post_bool('screen_qr'));
    DB::setSetting('screen_note', mb_substr(post_str('screen_note'), 0, 200));
    DB::setSetting('screen_ticker', mb_substr(post_str('screen_ticker'), 0, 500));
    DB::setSetting('screen_public', post_bool('screen_public'));
    Product::touch();
    flash(t('screen.saved'));
    redirect(base_url('admin/?p=screen'));
}

$s = fn (string $k, string $d = '') => (string) DB::setting($k, $d);

View::adminStart(t('nav.screen'), 'screen');
?>
<div class="split-2 split-form">
    <section class="panel">
        <div class="panel-head"><h2><?= View::icon('tv') ?><?= e(t('screen.settings')) ?></h2></div>
        <form method="post" class="form">
            <?= csrf_field() ?>
            <fieldset class="field"><span><?= e(t('screen.theme')) ?></span>
                <div class="swatches"><?php foreach (Product::themes() as $k => [$l, $c]) : ?><label><input type="radio" name="screen_theme" value="<?= e($k) ?>" <?= $s('screen_theme', 'auto') === $k ? 'checked' : '' ?>><span><i style="background:<?= e($c) ?>"></i><?= e(t($l)) ?></span></label><?php endforeach; ?></div>
                <small><?= e(t('screen.theme_hint')) ?></small>
            </fieldset>
            <div class="grid-2">
                <label class="field"><span><?= e(t('screen.limit')) ?></span><input name="screen_limit" value="<?= e($s('screen_limit', '6')) ?>" inputmode="numeric"><small><?= e(t('screen.limit_hint')) ?></small></label>
                <div class="field"><span><?= e(t('screen.options')) ?></span>
                    <label class="check"><input type="checkbox" name="screen_qr" value="1" <?= $s('screen_qr', '1') === '1' ? 'checked' : '' ?>><span><?= e(t('screen.qr')) ?></span></label>
                    <label class="check"><input type="checkbox" name="screen_public" value="1" <?= $s('screen_public') === '1' ? 'checked' : '' ?>><span><?= e(t('screen.public')) ?></span></label>
                </div>
            </div>
            <label class="field"><span><?= e(t('screen.note')) ?></span><input name="screen_note" value="<?= e($s('screen_note')) ?>" maxlength="200"></label>
            <label class="field"><span><?= e(t('screen.ticker')) ?> <em class="muted">(<?= e(t('ui.optional')) ?>)</em></span><textarea name="screen_ticker" rows="2" maxlength="500"><?= e($s('screen_ticker')) ?></textarea><small><?= e(t('screen.ticker_hint')) ?></small></label>
            <div class="form-actions"><button class="btn btn-primary"><?= e(t('ui.save')) ?></button></div>
        </form>
    </section>
    <section class="panel">
        <div class="panel-head">
            <h2><?= View::icon('eye') ?><?= e(t('dash.preview')) ?></h2>
            <?php if ($s('screen_public') !== '1') : ?>
                <form method="post" action="<?= e(base_url('admin/?p=action')) ?>"><?= csrf_field() ?><input type="hidden" name="do" value="regen_key"><button class="btn btn-sm" data-confirm="<?= e(t('screen.key_confirm')) ?>"><?= View::icon('refresh', 15) ?><span><?= e(t('screen.key_regen')) ?></span></button></form>
            <?php endif; ?>
        </div>
        <?= View::screenLink(Product::screenUrl()) ?>
        <div class="preview mt"><iframe src="<?= e(Product::screenUrl()) ?>" title="<?= e(t('dash.preview')) ?>" tabindex="-1"></iframe></div>
        <div class="preview is-portrait mt"><iframe src="<?= e(Product::screenUrl()) ?>" title="<?= e(t('screen.portrait')) ?>" tabindex="-1" loading="lazy"></iframe></div>
    </section>
</div>
<?php View::adminEnd(); ?>
