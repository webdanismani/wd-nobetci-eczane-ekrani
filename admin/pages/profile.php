<?php

declare(strict_types=1);

$days = [1 => t('day.1'), 2 => t('day.2'), 3 => t('day.3'), 4 => t('day.4'), 5 => t('day.5'), 6 => t('day.6'), 7 => t('day.7')];

if (is_post()) {
    csrf_check();
    $name = mb_substr(post_str('site_title'), 0, 120);
    if ($name !== '') {
        DB::setSetting('site_title', $name);
    }
    DB::setSetting('self_phone', mb_substr(post_str('self_phone'), 0, 40));
    DB::setSetting('self_address', mb_substr(post_str('self_address'), 0, 255));
    DB::setSetting('city', mb_substr(post_str('city'), 0, 60));
    DB::setSetting('district', mb_substr(post_str('district'), 0, 60));
    if (preg_match('/(-?\d{1,2}[.,]\d+)\s*[,; ]\s*(-?\d{1,3}[.,]\d+)/', post_str('coord'), $m)) {
        DB::setSetting('self_lat', str_replace(',', '.', $m[1]));
        DB::setSetting('self_lng', str_replace(',', '.', $m[2]));
    } elseif (post_str('coord') === '') {
        DB::setSetting('self_lat', '');
        DB::setSetting('self_lng', '');
    } else {
        flash(t('ph.err_coord'), 'error');
    }
    DB::setSetting('self_id', post_int('self_id'));
    if (Duty::minutes(post_str('switch_time')) !== null) {
        DB::setSetting('switch_time', sprintf('%05s', str_replace('.', ':', post_str('switch_time'))));
    }
    $badHours = false;
    foreach ($days as $d => $label) {
        $v = preg_replace('/\s+/', '', post_str('hours_' . $d));
        DB::setSetting('hours_' . $d, $v);
        if ($v !== '' && !Duty::ranges($d)) {
            $badHours = true;
        }
    }
    if ($badHours) {
        flash(t('profile.err_hours'), 'error');
    }
    Product::touch();
    flash(t('ui.saved'));
    redirect(base_url('admin/?p=profile'));
}

$s = fn (string $k, string $d = '') => (string) DB::setting($k, $d);
$coord = $s('self_lat') !== '' ? $s('self_lat') . ', ' . $s('self_lng') : '';
$pharm = DB::all('SELECT id, name FROM {pharmacies} ORDER BY name');
$status = Duty::status();

View::adminStart(t('nav.profile'), 'profile');
?>
<form method="post" class="split-2 split-form">
    <?= csrf_field() ?>
    <section class="panel form">
        <div class="panel-head"><h2><?= View::icon('cross') ?><?= e(t('profile.title')) ?></h2></div>
        <label class="field"><span><?= e(t('profile.name')) ?></span><input name="site_title" value="<?= e($s('site_title')) ?>" required maxlength="120"></label>
        <div class="grid-2">
            <label class="field"><span><?= e(t('ph.phone')) ?></span><input name="self_phone" value="<?= e($s('self_phone')) ?>" maxlength="40" inputmode="tel"></label>
            <label class="field"><span><?= e(t('ph.coord')) ?></span><input name="coord" value="<?= e($coord) ?>" placeholder="40.98810, 29.02780"></label>
        </div>
        <label class="field"><span><?= e(t('ph.address')) ?></span><input name="self_address" value="<?= e($s('self_address')) ?>" maxlength="255"></label>
        <div class="grid-2">
            <label class="field"><span><?= e(t('profile.city')) ?></span><input name="city" value="<?= e($s('city')) ?>" maxlength="60" placeholder="İstanbul"></label>
            <label class="field"><span><?= e(t('profile.district')) ?></span><input name="district" value="<?= e($s('district')) ?>" maxlength="60" placeholder="Kadıköy"></label>
        </div>
        <div class="hint"><?= View::icon('compass', 16) ?><div><?= e(t('profile.coord_hint')) ?></div></div>
        <label class="field"><span><?= e(t('profile.self_id')) ?></span>
            <select name="self_id"><option value="0"><?= e(t('profile.self_none')) ?></option><?php foreach ($pharm as $p) : ?><option value="<?= (int) $p['id'] ?>" <?= (int) $s('self_id', '0') === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option><?php endforeach; ?></select>
            <small><?= e(t('profile.self_id_hint')) ?></small>
        </label>
    </section>
    <section class="panel form">
        <div class="panel-head"><h2><?= View::icon('clock') ?><?= e(t('profile.hours')) ?></h2><?= View::pill($status['text'], $status['open'] ? 'up' : 'idle') ?></div>
        <div class="hours">
            <?php foreach ($days as $d => $label) : ?>
                <label class="field"><span><?= e($label) ?></span><input name="hours_<?= $d ?>" value="<?= e($s('hours_' . $d)) ?>" placeholder="<?= e(t('profile.closed')) ?>"></label>
            <?php endforeach; ?>
        </div>
        <p class="muted"><?= e(t('profile.hours_hint')) ?></p>
        <label class="field"><span><?= e(t('profile.switch')) ?></span><input name="switch_time" value="<?= e($s('switch_time', '08:30')) ?>" placeholder="08:30" style="max-width:140px"><small><?= e(t('profile.switch_hint')) ?></small></label>
        <div class="form-actions"><button class="btn btn-primary"><?= e(t('ui.save')) ?></button></div>
    </section>
</form>
<?php View::adminEnd(); ?>
