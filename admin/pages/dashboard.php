<?php

declare(strict_types=1);

$date   = Duty::dutyDate();
$list   = Duty::today();
$status = Duty::status();
$srcTxt = DB::setting('source', 'manual') === 'json' ? t('source.json') : t('source.manual');
$next   = DB::all('SELECT d.duty_date, p.name FROM {duties} d JOIN {pharmacies} p ON p.id = d.pharmacy_id WHERE d.duty_date > ? AND d.duty_date <= ? ORDER BY d.duty_date, p.name', [$date, date('Y-m-d', strtotime($date . ' +6 days'))]);
$byDay  = [];
foreach ($next as $r) {
    $byDay[$r['duty_date']][] = preg_replace('/\s+Eczanesi$/u', '', $r['name']);
}
$selfOn = (bool) array_filter($list, fn ($p) => $p['is_self']);

View::adminStart(t('nav.dashboard'), 'dashboard', '<a class="btn btn-sm btn-primary" href="' . e(base_url('admin/?p=schedule&d=' . $date)) . '">' . View::icon('calendar', 15) . '<span>' . e(t('dash.edit_today')) . '</span></a>');
?>
<?php if (DB::setting('demo', '0') === '1') : ?>
    <div class="alert demo-alert">
        <span><?= e(t('dash.demo')) ?></span>
        <form method="post" action="<?= e(base_url('admin/?p=action')) ?>"><?= csrf_field() ?><input type="hidden" name="do" value="demo_keep"><button class="btn btn-sm"><?= e(t('dash.demo_keep')) ?></button></form>
        <form method="post" action="<?= e(base_url('admin/?p=action')) ?>"><?= csrf_field() ?><input type="hidden" name="do" value="demo_clear"><button class="btn btn-sm btn-ghost-danger" data-confirm="<?= e(t('dash.demo_confirm')) ?>"><?= e(t('dash.demo_clear')) ?></button></form>
    </div>
<?php endif; ?>

<div class="stat-strip">
    <div><span><?= View::icon('calendar', 14) ?><?= e(t('dash.duty_day')) ?></span><strong><?= e(tr_date((int) strtotime($date), false)) ?></strong></div>
    <div class="<?= $list ? '' : 'is-warn' ?>"><span><?= View::icon('cross', 14) ?><?= e(t('dash.on_duty')) ?></span><strong><?= count($list) ?></strong></div>
    <div><span><?= View::icon('clock', 14) ?><?= e(t('dash.status')) ?></span><strong><?= e($status['open'] ? t('dash.open') : t('dash.closed')) ?></strong></div>
    <div><span><?= View::icon('database', 14) ?><?= e(t('dash.source')) ?></span><strong><?= e($srcTxt) ?></strong></div>
</div>

<div class="split-2">
    <section class="panel">
        <div class="panel-head"><h2><?= View::icon('tv') ?><?= e(t('dash.screen')) ?></h2></div>
        <?= View::screenLink(Product::screenUrl()) ?>
        <div class="preview mt"><iframe src="<?= e(Product::screenUrl()) ?>" title="<?= e(t('dash.preview')) ?>" tabindex="-1"></iframe></div>
        <div class="hint mt"><?= View::icon('tv', 16) ?><div><?= e(t('dash.howto')) ?></div></div>
    </section>

    <div class="stack">
        <section class="panel">
            <div class="panel-head"><h2><?= View::icon('cross') ?><?= e(t('dash.today', ['d' => tr_date((int) strtotime($date), false)])) ?></h2></div>
            <?php if ($selfOn) : ?><div class="alert self-alert"><?= e(t('dash.self_on')) ?></div><?php endif; ?>
            <?php if (!$list) : ?>
                <div class="empty-block"><?= View::icon('alert', 28) ?><p><?= e(t('dash.empty')) ?></p><a class="btn btn-primary" href="<?= e(base_url('admin/?p=schedule&d=' . $date)) ?>"><?= e(t('dash.edit_today')) ?></a></div>
            <?php else : ?>
                <div class="table-wrap"><table class="table">
                    <tbody>
                    <?php foreach ($list as $p) : ?>
                        <tr><td><span class="strong"><?= e($p['name']) ?></span><?php if ($p['is_self']) : ?> <span class="chip is-accent"><?= e(t('ph.self')) ?></span><?php endif; ?><span class="sub"><?= e(trim(($p['district'] ?? '') . ' · ' . ($p['address'] ?? ''), ' ·')) ?></span></td>
                            <td class="nowrap"><?= e($p['phone'] ?: '—') ?></td>
                            <td class="num nowrap"><?= $p['meters'] !== null ? e(Duty::km($p['meters'])) . '<span class="sub">' . e(Duty::direction($p['bearing'])) . '</span>' : '—' ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div>
            <?php endif; ?>
        </section>
        <section class="panel">
            <div class="panel-head"><h2><?= View::icon('calendar') ?><?= e(t('dash.week')) ?></h2><a class="btn btn-sm" href="<?= e(base_url('admin/?p=schedule')) ?>"><?= e(t('dash.calendar')) ?></a></div>
            <ul class="week">
                <?php for ($i = 1; $i <= 6; $i++) : $d = date('Y-m-d', strtotime($date . ' +' . $i . ' days')); ?>
                    <li class="<?= empty($byDay[$d]) ? 'is-empty' : '' ?>"><a href="<?= e(base_url('admin/?p=schedule&d=' . $d)) ?>"><b><?= e(tr_date((int) strtotime($d))) ?></b><span><?= e(!empty($byDay[$d]) ? implode(', ', $byDay[$d]) : t('dash.no_duty')) ?></span></a></li>
                <?php endfor; ?>
            </ul>
        </section>
    </div>
</div>
<?php View::adminEnd(); ?>
