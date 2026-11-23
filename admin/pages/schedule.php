<?php

declare(strict_types=1);

$month = preg_match('/^\d{4}-\d{2}$/', get_str('m')) ? get_str('m') : date('Y-m', strtotime(Duty::dutyDate()));
$day   = preg_match('/^\d{4}-\d{2}-\d{2}$/', get_str('d')) ? get_str('d') : '';
if ($day !== '') {
    $month = substr($day, 0, 7);
}
$self = base_url('admin/?p=schedule&m=' . $month);

/** "23.11.2026", "2026-11-23", "23/11/26" → 2026-11-23 */
$parseDate = function (string $s): ?string {
    $s = trim($s);
    if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $s, $m)) {
        [$y, $mo, $d] = [(int) $m[1], (int) $m[2], (int) $m[3]];
    } elseif (preg_match('/^(\d{1,2})[.\/-](\d{1,2})[.\/-](\d{2,4})$/', $s, $m)) {
        [$d, $mo, $y] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        $y = $y < 100 ? 2000 + $y : $y;
    } else {
        return null;
    }
    return checkdate($mo, $d, $y) ? sprintf('%04d-%02d-%02d', $y, $mo, $d) : null;
};

/** Ada göre eczane bulur, yoksa oluşturur. */
$findOrCreate = function (string $name, int &$created): ?int {
    $name = Duty::titleCase(trim(preg_replace('/\s+/u', ' ', $name)));
    if ($name === '') {
        return null;
    }
    $norm = fn (string $x) => mb_strtolower(strtr($x, ['İ' => 'i', 'I' => 'ı']));
    if (!preg_match('/eczane/iu', $name)) {
        $name .= ' Eczanesi';
    }
    foreach (DB::all('SELECT id, name FROM {pharmacies}') as $p) {
        if ($norm($p['name']) === $norm($name)) {
            return (int) $p['id'];
        }
    }
    $created++;
    return DB::insert('pharmacies', ['name' => mb_substr($name, 0, 120), 'created_at' => time()]);
};

$assign = function (string $date, array $ids, bool $replace): int {
    if ($replace) {
        DB::q('DELETE FROM {duties} WHERE duty_date = ?', [$date]);
    }
    $n = 0;
    foreach (array_unique($ids) as $pid) {
        if (!DB::val('SELECT COUNT(*) FROM {duties} WHERE duty_date = ? AND pharmacy_id = ?', [$date, $pid])) {
            DB::insert('duties', ['duty_date' => $date, 'pharmacy_id' => $pid]);
            $n++;
        }
    }
    return $n;
};

if (is_post()) {
    csrf_check();
    $action = post_str('action');

    if ($action === 'day') {
        $date = $parseDate(post_str('date'));
        if ($date) {
            $ids = array_values(array_filter(array_map('intval', (array) ($_POST['ids'] ?? []))));
            DB::transaction(fn () => $assign($date, $ids, true));
            Product::touch();
            flash(t('sched.day_saved', ['d' => tr_date((int) strtotime($date), false)]));
        }
        redirect(base_url('admin/?p=schedule&d=' . $date));
    }

    if ($action === 'paste') {
        $created = 0;
        $days = 0;
        $bad = [];
        $replace = post_bool('replace') === 1;
        DB::transaction(function () use (&$created, &$days, &$bad, $replace, $parseDate, $findOrCreate, $assign) {
            $seen = [];
            foreach (preg_split('/\R/u', (string) ($_POST['text'] ?? '')) as $n => $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                $parts = preg_split('/\t|;|\s{2,}/u', $line, 2);
                if (count($parts) < 2 && preg_match('/^(\S+)\s+(.+)$/u', $line, $m)) {
                    $parts = [$m[1], $m[2]];
                }
                $date = $parseDate($parts[0] ?? '');
                if (!$date || empty($parts[1])) {
                    $bad[] = $n + 1;
                    continue;
                }
                $ids = [];
                foreach (preg_split('/\s*[,|]\s*/u', $parts[1]) as $name) {
                    $id = $findOrCreate($name, $created);
                    if ($id) {
                        $ids[] = $id;
                    }
                }
                // Aynı tarih birden çok satırda geçebilir: ilk satırda temizlenir, sonrakiler eklenir.
                $assign($date, $ids, $replace && !isset($seen[$date]));
                $seen[$date] = true;
                $days = count($seen);
            }
        });
        Product::touch();
        flash(t('sched.paste_done', ['d' => $days, 'c' => $created]) . ($bad ? ' ' . t('sched.paste_bad', ['l' => implode(', ', array_slice($bad, 0, 10))]) : ''), $bad ? 'error' : 'ok');
        redirect($self);
    }

    if ($action === 'rotate') {
        $from = $parseDate(post_str('from'));
        $to   = $parseDate(post_str('to'));
        $per  = max(1, min(10, post_int('per', 1)));
        $created = 0;
        $order = [];
        foreach (preg_split('/\R/u', (string) ($_POST['order'] ?? '')) as $name) {
            if (($id = $findOrCreate($name, $created)) !== null) {
                $order[] = $id;
            }
        }
        if (!$from || !$to || $to < $from || !$order || (strtotime($to) - strtotime($from)) / 86400 > 400) {
            flash(t('sched.rotate_err'), 'error');
            redirect($self);
        }
        $i = 0;
        $days = 0;
        DB::transaction(function () use ($from, $to, $per, $order, $assign, &$i, &$days) {
            for ($ts = strtotime($from); $ts <= strtotime($to); $ts = strtotime('+1 day', $ts)) {
                $ids = [];
                for ($k = 0; $k < $per; $k++) {
                    $ids[] = $order[$i % count($order)];
                    $i++;
                }
                $assign(date('Y-m-d', $ts), $ids, true);
                $days++;
            }
        });
        Product::touch();
        flash(t('sched.rotate_done', ['d' => $days, 'c' => $created]));
        redirect($self);
    }

    if ($action === 'clear_month') {
        DB::q('DELETE FROM {duties} WHERE duty_date LIKE ?', [$month . '-%']);
        Product::touch();
        flash(t('sched.cleared'));
        redirect($self);
    }
}

$pharm = DB::all('SELECT * FROM {pharmacies} ORDER BY name');
$names = array_column($pharm, 'name', 'id');
$rows  = DB::all('SELECT duty_date, pharmacy_id FROM {duties} WHERE duty_date LIKE ? ORDER BY duty_date', [$month . '-%']);
$byDay = [];
foreach ($rows as $r) {
    $byDay[$r['duty_date']][] = (int) $r['pharmacy_id'];
}
$first   = strtotime($month . '-01');
$days    = (int) date('t', $first);
$offset  = (int) date('N', $first) - 1;
$current = Duty::dutyDate();
$selfId  = (int) DB::setting('self_id', 0);
$months  = ['Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];
$label   = Lang::$code === 'tr' ? $months[(int) date('n', $first) - 1] . ' ' . date('Y', $first) : date('F Y', $first);
$prev    = date('Y-m', strtotime('-1 month', $first));
$next    = date('Y-m', strtotime('+1 month', $first));

View::adminStart(t('nav.schedule'), 'schedule');
?>
<?php if (DB::setting('source', 'manual') === 'json') : ?>
    <div class="alert"><?= e(t('sched.json_note')) ?></div>
<?php endif; ?>
<div class="sched">
    <section class="panel">
        <div class="panel-head">
            <h2><?= View::icon('calendar') ?><?= e($label) ?></h2>
            <div class="panel-tools">
                <a class="btn btn-sm" href="<?= e(base_url('admin/?p=schedule&m=' . $prev)) ?>"><span><?= e(t('sched.prev')) ?></span></a>
                <a class="btn btn-sm" href="<?= e(base_url('admin/?p=schedule')) ?>"><?= e(t('sched.today')) ?></a>
                <a class="btn btn-sm" href="<?= e(base_url('admin/?p=schedule&m=' . $next)) ?>"><span><?= e(t('sched.next')) ?></span></a>
            </div>
        </div>
        <div class="cal">
            <?php foreach (explode(',', t('sched.weekdays')) as $w) : ?><div class="cal-h"><?= e($w) ?></div><?php endforeach; ?>
            <?php for ($i = 0; $i < $offset; $i++) : ?><div class="cal-x"></div><?php endfor; ?>
            <?php for ($dnum = 1; $dnum <= $days; $dnum++) : $date = $month . '-' . sprintf('%02d', $dnum); $list = $byDay[$date] ?? []; ?>
                <a class="cal-d<?= $date === $current ? ' is-now' : '' ?><?= $date === $day ? ' is-sel' : '' ?><?= !$list ? ' is-empty' : '' ?>" href="<?= e(base_url('admin/?p=schedule&d=' . $date)) ?>">
                    <b><?= $dnum ?></b>
                    <?php foreach (array_slice($list, 0, 4) as $pid) : ?><span class="<?= $pid === $selfId ? 'is-self' : '' ?>"><?= e(preg_replace('/\s+Eczanesi$/u', '', $names[$pid] ?? '?')) ?></span><?php endforeach; ?>
                    <?php if (count($list) > 4) : ?><em>+<?= count($list) - 4 ?></em><?php endif; ?>
                </a>
            <?php endfor; ?>
        </div>
        <p class="muted mt"><?= e(t('sched.legend', ['t' => DB::setting('switch_time', '08:30')])) ?></p>
    </section>

    <div class="stack">
        <?php if ($day !== '') : $sel = $byDay[$day] ?? []; ?>
            <section class="panel">
                <div class="panel-head"><h2><?= View::icon('edit') ?><?= e(tr_date((int) strtotime($day))) ?></h2><a class="btn btn-sm" href="<?= e($self) ?>"><?= View::icon('x', 14) ?></a></div>
                <?php if (!$pharm) : ?>
                    <p class="muted"><?= e(t('sched.no_pharm')) ?></p>
                <?php else : ?>
                    <form method="post" class="form">
                        <?= csrf_field() ?><input type="hidden" name="action" value="day"><input type="hidden" name="date" value="<?= e($day) ?>">
                        <input type="search" class="search" placeholder="<?= e(t('ui.search')) ?>" data-filter-input>
                        <div class="check-list check-list-tall">
                            <?php foreach ($pharm as $p) : ?>
                                <label class="check" data-filter-text="<?= e(mb_strtolower($p['name'] . ' ' . $p['district'])) ?>"><input type="checkbox" name="ids[]" value="<?= (int) $p['id'] ?>" <?= in_array((int) $p['id'], $sel, true) ? 'checked' : '' ?>><span><?= e($p['name']) ?><?php if ($p['district']) : ?> <em class="muted"><?= e($p['district']) ?></em><?php endif; ?></span></label>
                            <?php endforeach; ?>
                        </div>
                        <div class="form-actions"><button class="btn btn-primary"><?= e(t('ui.save')) ?></button></div>
                    </form>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <section class="panel">
            <div class="panel-head"><h2><?= View::icon('copy') ?><?= e(t('sched.paste')) ?></h2></div>
            <p class="muted"><?= e(t('sched.paste_desc')) ?></p>
            <form method="post" class="form mt">
                <?= csrf_field() ?><input type="hidden" name="action" value="paste">
                <textarea class="code" name="text" rows="6" placeholder="<?= e("23.11.2026; Güven Eczanesi, Deniz Eczanesi\n24.11.2026; Papatya Eczanesi\n25.11.2026\tUmut Eczanesi") ?>"></textarea>
                <label class="check"><input type="checkbox" name="replace" value="1" checked><span><?= e(t('sched.replace')) ?></span></label>
                <div class="form-actions"><button class="btn btn-primary"><?= e(t('sched.import')) ?></button></div>
            </form>
        </section>

        <section class="panel">
            <div class="panel-head"><h2><?= View::icon('refresh') ?><?= e(t('sched.rotate')) ?></h2></div>
            <p class="muted"><?= e(t('sched.rotate_desc')) ?></p>
            <form method="post" class="form mt">
                <?= csrf_field() ?><input type="hidden" name="action" value="rotate">
                <label class="field"><span><?= e(t('sched.order')) ?></span><textarea name="order" rows="5" placeholder="<?= e("Güven Eczanesi\nDeniz Eczanesi\nPapatya Eczanesi") ?>"></textarea></label>
                <div class="grid-3">
                    <label class="field"><span><?= e(t('sched.from')) ?></span><input type="date" name="from" value="<?= e($month . '-01') ?>"></label>
                    <label class="field"><span><?= e(t('sched.to')) ?></span><input type="date" name="to" value="<?= e($month . '-' . $days) ?>"></label>
                    <label class="field"><span><?= e(t('sched.per')) ?></span><input name="per" value="1" inputmode="numeric"></label>
                </div>
                <div class="form-actions"><button class="btn btn-primary" data-confirm="<?= e(t('sched.rotate_confirm')) ?>"><?= e(t('sched.rotate_btn')) ?></button></div>
            </form>
        </section>

        <form method="post" class="right"><?= csrf_field() ?><input type="hidden" name="action" value="clear_month"><button class="btn btn-sm btn-ghost-danger" data-confirm="<?= e(t('sched.clear_confirm', ['m' => $label])) ?>"><?= View::icon('trash', 14) ?><span><?= e(t('sched.clear', ['m' => $label])) ?></span></button></form>
    </div>
</div>
<?php View::adminEnd(); ?>
