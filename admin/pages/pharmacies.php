<?php

declare(strict_types=1);

$self = base_url('admin/?p=pharmacies');

/** "41.0123, 28.9876" veya "41,0123 28,9876" → [41.0123, 28.9876] */
$parseCoord = function (string $s): array {
    if (preg_match('/(-?\d{1,2}[.,]\d+)\s*[,; ]\s*(-?\d{1,3}[.,]\d+)/', trim($s), $m)) {
        $lat = (float) str_replace(',', '.', $m[1]);
        $lng = (float) str_replace(',', '.', $m[2]);
        if (abs($lat) <= 90 && abs($lng) <= 180) {
            return [$lat, $lng];
        }
    }
    return [null, null];
};

if (is_post()) {
    csrf_check();
    $action = post_str('action');

    if ($action === 'save') {
        $id   = post_int('id');
        $name = mb_substr(post_str('name'), 0, 120);
        if ($name === '') {
            flash(t('ph.err_name'), 'error');
            redirect($self . ($id ? '&edit=' . $id : ''));
        }
        $coord = post_str('coord');
        [$lat, $lng] = $parseCoord($coord);
        if ($coord !== '' && $lat === null) {
            flash(t('ph.err_coord'), 'error');
        }
        $data = [
            'name'     => $name,
            'district' => mb_substr(post_str('district'), 0, 80),
            'address'  => mb_substr(post_str('address'), 0, 255),
            'phone'    => mb_substr(post_str('phone'), 0, 40),
            'lat'      => $lat,
            'lng'      => $lng,
            'note'     => mb_substr(post_str('note'), 0, 160),
        ];
        if ($id) {
            DB::update('pharmacies', $data, 'id = ?', [$id]);
        } else {
            $data['created_at'] = time();
            $id = DB::insert('pharmacies', $data);
        }
        Product::touch();
        flash(t('ui.saved'));
        redirect($self);
    }

    if ($action === 'delete') {
        $id = post_int('id');
        DB::q('DELETE FROM {duties} WHERE pharmacy_id = ?', [$id]);
        DB::q('DELETE FROM {pharmacies} WHERE id = ?', [$id]);
        if ((int) DB::setting('self_id', 0) === $id) {
            DB::setSetting('self_id', 0);
        }
        Product::touch();
        flash(t('ui.deleted'));
        redirect($self);
    }
}

$list = DB::all('SELECT p.*, (SELECT COUNT(*) FROM {duties} d WHERE d.pharmacy_id = p.id AND d.duty_date >= ?) AS upcoming FROM {pharmacies} p ORDER BY p.name', [Duty::dutyDate()]);
$list = Duty::decorate($list);
usort($list, fn ($a, $b) => strcmp(mb_strtolower($a['name']), mb_strtolower($b['name'])));
$edit = get_int('edit') ? DB::row('SELECT * FROM {pharmacies} WHERE id = ?', [get_int('edit')]) : null;
$e = $edit ?: ['id' => 0, 'name' => '', 'district' => '', 'address' => '', 'phone' => '', 'lat' => null, 'lng' => null, 'note' => ''];
$coordVal = $e['lat'] !== null && $e['lng'] !== null ? rtrim(rtrim(number_format((float) $e['lat'], 6, '.', ''), '0'), '.') . ', ' . rtrim(rtrim(number_format((float) $e['lng'], 6, '.', ''), '0'), '.') : '';
$missing = count(array_filter($list, fn ($p) => $p['lat'] === null || $p['phone'] === ''));

View::adminStart(t('nav.pharmacies'), 'pharmacies');
?>
<div class="split-2 split-form">
    <section class="panel">
        <div class="panel-head"><h2><?= View::icon('list') ?><?= e(t('ph.list', ['n' => count($list)])) ?></h2><input type="search" class="search" placeholder="<?= e(t('ui.search')) ?>" data-filter-input></div>
        <?php if ($missing) : ?><div class="alert"><?= e(t('ph.missing', ['n' => $missing])) ?></div><?php endif; ?>
        <?php if (!$list) : ?>
            <p class="muted"><?= e(t('ph.empty')) ?></p>
        <?php else : ?>
            <div class="table-wrap mt"><table class="table">
                <thead><tr><th><?= e(t('ph.name')) ?></th><th><?= e(t('ph.phone')) ?></th><th class="num"><?= e(t('ph.distance')) ?></th><th></th></tr></thead>
                <tbody>
                <?php foreach ($list as $p) : ?>
                    <tr data-filter-text="<?= e(mb_strtolower($p['name'] . ' ' . $p['district'] . ' ' . $p['address'])) ?>">
                        <td><a class="strong" href="<?= e($self . '&edit=' . $p['id']) ?>"><?= e($p['name']) ?></a>
                            <?php if ($p['is_self']) : ?><span class="chip is-accent"><?= e(t('ph.self')) ?></span><?php endif; ?>
                            <span class="sub"><?= e(trim($p['district'] . ($p['address'] !== '' ? ' · ' . $p['address'] : ''), ' ·')) ?: '—' ?></span></td>
                        <td class="nowrap"><?= $p['phone'] !== '' ? e($p['phone']) : '<span class="chip is-warn">' . e(t('ph.no_phone')) . '</span>' ?></td>
                        <td class="num nowrap"><?= $p['meters'] !== null ? e(Duty::km($p['meters'])) : '<span class="chip is-warn">' . e(t('ph.no_coord')) . '</span>' ?><span class="sub"><?= e(t('ph.upcoming', ['n' => $p['upcoming']])) ?></span></td>
                        <td class="actions">
                            <a class="iconbtn iconbtn-sm" href="<?= e($self . '&edit=' . $p['id']) ?>" title="<?= e(t('ui.edit')) ?>"><?= View::icon('edit', 14) ?></a>
                            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><button class="iconbtn iconbtn-sm btn-ghost-danger" data-confirm="<?= e(t('ph.confirm_delete', ['n' => $p['name']])) ?>" title="<?= e(t('ui.delete')) ?>"><?= View::icon('trash', 14) ?></button></form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
            <p class="empty-line mt" data-filter-empty hidden><?= e(t('ui.no_results')) ?></p>
        <?php endif; ?>
    </section>

    <section class="panel">
        <div class="panel-head"><h2><?= View::icon($e['id'] ? 'edit' : 'plus') ?><?= e($e['id'] ? t('ph.edit') : t('ph.new')) ?></h2><?php if ($e['id']) : ?><a class="btn btn-sm" href="<?= e($self) ?>"><?= e(t('ph.new')) ?></a><?php endif; ?></div>
        <form method="post" class="form">
            <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int) $e['id'] ?>">
            <div class="grid-2">
                <label class="field"><span><?= e(t('ph.name')) ?></span><input name="name" value="<?= e($e['name']) ?>" required maxlength="120" placeholder="Hayat Eczanesi"></label>
                <label class="field"><span><?= e(t('ph.district')) ?></span><input name="district" value="<?= e($e['district']) ?>" maxlength="80" placeholder="Caferağa"></label>
            </div>
            <label class="field"><span><?= e(t('ph.address')) ?></span><input name="address" value="<?= e($e['address']) ?>" maxlength="255"></label>
            <div class="grid-2">
                <label class="field"><span><?= e(t('ph.phone')) ?></span><input name="phone" value="<?= e($e['phone']) ?>" maxlength="40" inputmode="tel" placeholder="0216 123 45 67"></label>
                <label class="field"><span><?= e(t('ph.coord')) ?></span><input name="coord" value="<?= e($coordVal) ?>" placeholder="40.98810, 29.02780"></label>
            </div>
            <div class="hint"><?= View::icon('pin', 16) ?><div><?= e(t('ph.coord_hint')) ?></div></div>
            <label class="field"><span><?= e(t('ph.note')) ?> <em class="muted">(<?= e(t('ui.optional')) ?>)</em></span><input name="note" value="<?= e($e['note']) ?>" maxlength="160"></label>
            <div class="form-actions"><button class="btn btn-primary"><?= e(t('ui.save')) ?></button></div>
        </form>
    </section>
</div>
<?php View::adminEnd(); ?>
