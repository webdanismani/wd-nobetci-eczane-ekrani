<?php

declare(strict_types=1);

require __DIR__ . '/Duty.php';

/**
 * Nöbetçi eczane ekranına özel tanımlar.
 */
final class Product
{
    public const DB_VERSION = 1;

    /** @return string[] */
    public static function schema(string $id, string $eng, string $p, bool $my): array
    {
        $dbl = $my ? 'DOUBLE' : 'REAL';
        return [
            "CREATE TABLE {$p}pharmacies (
                id {$id},
                name VARCHAR(120) NOT NULL,
                district VARCHAR(80) NOT NULL DEFAULT '',
                address VARCHAR(255) NOT NULL DEFAULT '',
                phone VARCHAR(40) NOT NULL DEFAULT '',
                lat {$dbl} NULL,
                lng {$dbl} NULL,
                note VARCHAR(160) NOT NULL DEFAULT '',
                created_at INT NOT NULL DEFAULT 0
            ){$eng}",
            "CREATE TABLE {$p}duties (
                id {$id},
                duty_date VARCHAR(10) NOT NULL,
                pharmacy_id INT NOT NULL
            ){$eng}",
            "CREATE UNIQUE INDEX {$p}duties_day ON {$p}duties (duty_date, pharmacy_id)",
            "CREATE TABLE {$p}fetch_log (
                id {$id}, at INT NOT NULL, ok INT NOT NULL,
                count INT NOT NULL DEFAULT 0, message VARCHAR(255) NOT NULL DEFAULT ''
            ){$eng}",
        ];
    }

    public static function upgrade(int $from): void
    {
    }

    /** @return array<string,string> */
    public static function defaults(): array
    {
        $d = [
            'self_address'   => '',
            'self_phone'     => '',
            'self_lat'       => '',
            'self_lng'       => '',
            'self_id'        => '0',
            'city'           => '',
            'district'       => '',
            'switch_time'    => '08:30',
            'source'         => 'manual',
            'json_url'       => '',
            'json_header'    => '',
            'json_refresh'   => '30',
            'screen_key'     => random_token(8),
            'screen_public'  => '0',
            'screen_theme'   => 'auto',
            'screen_limit'   => '6',
            'screen_qr'      => '1',
            'screen_note'    => 'Acil durumlarda 112 Acil Çağrı Merkezi\'ni arayın.',
            'screen_ticker'  => '',
            'screen_version' => '1',
            'demo'           => '1',
        ];
        // Çalışma saatleri: 1 = Pazartesi … 7 = Pazar. Boş = kapalı.
        foreach (range(1, 7) as $day) {
            $d['hours_' . $day] = $day === 7 ? '' : ($day === 6 ? '09:00-17:00' : '08:30-19:00');
        }
        return $d;
    }

    /**
     * Örnek veri: kurgusal eczaneler ve önümüzdeki iki haftanın nöbet çizelgesi.
     * Adlar, adresler ve telefonlar gerçek değildir; panodaki düğmeyle silinir.
     */
    public static function seed(string $lang): void
    {
        $now = time();
        // Kendi eczanemiz (merkez)
        $selfLat = 40.98810;
        $selfLng = 29.02780;
        DB::setSetting('self_address', 'Örnek Mah. Çınar Sok. No: 4, Kadıköy');
        DB::setSetting('self_phone', '0216 000 00 00');
        DB::setSetting('self_lat', (string) $selfLat);
        DB::setSetting('self_lng', (string) $selfLng);
        DB::setSetting('city', 'İstanbul');
        DB::setSetting('district', 'Kadıköy');

        $rows = [
            ['Güven Eczanesi', 'Caferağa', 'Örnek Mah. Lale Sok. No: 12, Kadıköy', '0216 000 00 01', 40.98935, 29.02510],
            ['Deniz Eczanesi', 'Rasimpaşa', 'Örnek Cad. No: 41/A, Kadıköy', '0216 000 00 02', 40.99420, 29.02180],
            ['Papatya Eczanesi', 'Osmanağa', 'Papatya Sok. No: 7, Kadıköy', '0216 000 00 03', 40.99150, 29.03060],
            ['Umut Eczanesi', 'Koşuyolu', 'Umut Cad. No: 88, Kadıköy', '0216 000 00 04', 41.00110, 29.04120],
            ['Çınar Eczanesi', 'Fenerbahçe', 'Çınar Bulvarı No: 15, Kadıköy', '0216 000 00 05', 40.97650, 29.03650],
            ['Lale Eczanesi', 'Göztepe', 'Lale Cad. No: 203, Kadıköy', '0216 000 00 06', 40.97810, 29.05790],
            ['Şifa Eczanesi', 'Caferağa', 'Örnek Mah. Çınar Sok. No: 4, Kadıköy', '0216 000 00 00', $selfLat, $selfLng],
            ['Kardelen Eczanesi', 'Acıbadem', 'Kardelen Sok. No: 3, Kadıköy', '0216 000 00 07', 41.00480, 29.04990],
        ];
        $ids = [];
        foreach ($rows as [$name, $dist, $addr, $phone, $lat, $lng]) {
            $ids[$name] = DB::insert('pharmacies', ['name' => $name, 'district' => $dist, 'address' => $addr, 'phone' => $phone, 'lat' => $lat, 'lng' => $lng, 'created_at' => $now]);
        }
        $self = $ids['Şifa Eczanesi'];
        DB::setSetting('self_id', $self);
        $others = array_values(array_filter($ids, fn ($id) => $id !== $self));
        // Her gün 5 eczane nöbetçi; çizelge dönerek ilerler. Kendi eczanemiz 4 günde bir nöbetçi.
        $start = strtotime('today -3 days');
        for ($i = 0; $i < 21; $i++) {
            $day = date('Y-m-d', strtotime('+' . $i . ' days', $start));
            $pick = [];
            foreach ([0, 1, 3, 4, 5] as $o) {
                $pick[] = $others[($i + $o) % count($others)];
            }
            if ($i % 4 === 1) {
                $pick[4] = $self;
            }
            foreach (array_unique($pick) as $pid) {
                DB::insert('duties', ['duty_date' => $day, 'pharmacy_id' => $pid]);
            }
        }
    }

    /** @return array<string,array{0:string,1:string,2:string}> */
    public static function nav(): array
    {
        return [
            'dashboard'  => ['nav.dashboard', 'grid', base_url('admin/')],
            'schedule'   => ['nav.schedule', 'calendar', base_url('admin/?p=schedule')],
            'pharmacies' => ['nav.pharmacies', 'list', base_url('admin/?p=pharmacies')],
            'profile'    => ['nav.profile', 'cross', base_url('admin/?p=profile')],
            'screen'     => ['nav.screen', 'tv', base_url('admin/?p=screen')],
            'source'     => ['nav.source', 'database', base_url('admin/?p=source')],
        ];
    }

    /** @return string[] */
    public static function pages(): array
    {
        return ['dashboard', 'schedule', 'pharmacies', 'profile', 'screen', 'source', 'action'];
    }

    public static function logo(int $size = 34): string
    {
        return '<svg class="logo" width="' . $size . '" height="' . $size . '" viewBox="0 0 32 32" aria-hidden="true"><rect width="32" height="32" rx="9" fill="currentColor"/>'
            . '<path d="M11 8.5h10M11 8.5v15h10M11 16h8" fill="none" stroke="#fff" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    }

    public static function sideNote(): string
    {
        $day = Duty::dutyDate();
        $n = count(Duty::today());
        if ($n) {
            return '<a class="cron-ok" href="' . e(base_url('admin/?p=schedule')) . '">' . View::icon('check', 15) . '<span>' . e(t('side.ok', ['n' => $n])) . '</span></a>';
        }
        return '<a class="cron-warn" href="' . e(base_url('admin/?p=schedule&d=' . $day)) . '">' . View::icon('alert', 15) . '<span>' . e(t('side.empty')) . '</span></a>';
    }

    public static function touch(): void
    {
        DB::setSetting('screen_version', (int) DB::setting('screen_version', 1) + 1);
    }

    public static function screenUrl(): string
    {
        $q = DB::setting('screen_public', '0') === '1' ? '' : '?k=' . DB::setting('screen_key', '');
        return absolute_url('ekran.php' . $q);
    }

    public static function screenAllowed(): bool
    {
        if (DB::setting('screen_public', '0') === '1') {
            return true;
        }
        $k = get_str('k');
        return $k !== '' && hash_equals((string) DB::setting('screen_key', ''), $k);
    }

    /** @return array<string,array{0:string,1:string}> */
    public static function themes(): array
    {
        return [
            'auto'   => ['theme.auto', 'linear-gradient(90deg,#ffffff 50%,#14171b 50%)'],
            'gunduz' => ['theme.gunduz', '#ffffff'],
            'gece'   => ['theme.gece', '#14171b'],
        ];
    }
}
