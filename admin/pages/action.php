<?php

declare(strict_types=1);

if (!is_post()) {
    redirect(base_url('admin/'));
}
csrf_check();

$back = 'dashboard';
switch (post_str('do')) {
    case 'regen_key':
        DB::setSetting('screen_key', random_token(8));
        flash(t('screen.key_new'));
        $back = 'screen';
        break;

    case 'demo_keep':
        DB::setSetting('demo', '0');
        break;

    case 'demo_clear':
        // Örnek eczaneler ve çizelge silinir; kendi eczane bilgileri boşaltılır.
        DB::transaction(function () {
            DB::q('DELETE FROM {duties}');
            DB::q('DELETE FROM {pharmacies}');
        });
        foreach (['self_address', 'self_phone', 'self_lat', 'self_lng'] as $k) {
            DB::setSetting($k, '');
        }
        DB::setSetting('self_id', 0);
        DB::setSetting('demo', '0');
        Product::touch();
        flash(t('dash.demo_cleared'));
        $back = 'profile';
        break;
}

redirect(base_url('admin/?p=' . $back));
