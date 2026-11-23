<?php

declare(strict_types=1);

/**
 * Nöbet günü, nöbetçi listesi, uzaklık/yön hesabı ve JSON kaynağı.
 *
 * Nöbet günü: nöbet değişim saatinden (varsayılan 08:30) önce bir önceki günün nöbeti sürer.
 * Ör. 24 Kasım 03:00'te ekranda 23 Kasım nöbetçileri görünür.
 */
final class Duty
{
    /** @var array<int,array<string,mixed>>|null */
    private static ?array $cache = null;

    public static function dutyDate(?int $ts = null): string
    {
        $ts ??= time();
        $switch = self::minutes((string) DB::setting('switch_time', '08:30')) ?? 510;
        $now = (int) date('G', $ts) * 60 + (int) date('i', $ts);
        return $now < $switch ? date('Y-m-d', strtotime('-1 day', $ts)) : date('Y-m-d', $ts);
    }

    /** "08:30" → 510 */
    public static function minutes(string $hm): ?int
    {
        return preg_match('/^([01]?\d|2[0-3])[:.]([0-5]\d)$/', trim($hm), $m) ? (int) $m[1] * 60 + (int) $m[2] : null;
    }

    /**
     * Bugünün nöbetçileri (yakından uzağa).
     *
     * @return array<int,array<string,mixed>>
     */
    public static function today(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $date = self::dutyDate();
        $list = [];
        if (DB::setting('source', 'manual') === 'json') {
            $list = self::remote($date);
        }
        if (!$list) {
            $list = DB::all('SELECT p.* FROM {duties} d JOIN {pharmacies} p ON p.id = d.pharmacy_id WHERE d.duty_date = ? ORDER BY p.name', [$date]);
            foreach ($list as &$p) {
                $p['source'] = 'manual';
            }
            unset($p);
        }
        return self::$cache = self::decorate($list);
    }

    /**
     * Uzaklık, yön, yürüme süresi ve harita bağlantısı ekler; en yakından sıralar.
     *
     * @param array<int,array<string,mixed>> $list
     * @return array<int,array<string,mixed>>
     */
    public static function decorate(array $list): array
    {
        $sLat = parse_num((string) DB::setting('self_lat', ''));
        $sLng = parse_num((string) DB::setting('self_lng', ''));
        $selfId = (int) DB::setting('self_id', 0);
        $selfName = mb_strtolower(trim((string) DB::setting('site_title', '')));
        foreach ($list as &$p) {
            $lat = isset($p['lat']) && $p['lat'] !== null && $p['lat'] !== '' ? (float) $p['lat'] : null;
            $lng = isset($p['lng']) && $p['lng'] !== null && $p['lng'] !== '' ? (float) $p['lng'] : null;
            $p['lat'] = $lat;
            $p['lng'] = $lng;
            $p['is_self'] = ($selfId && (int) ($p['id'] ?? 0) === $selfId) || ($selfName !== '' && mb_strtolower(trim((string) $p['name'])) === $selfName);
            $p['meters'] = null;
            if ($lat !== null && $lng !== null && $sLat !== null && $sLng !== null) {
                $p['meters'] = self::distance($sLat, $sLng, $lat, $lng);
                $p['bearing'] = self::bearing($sLat, $sLng, $lat, $lng);
            }
            $p['map'] = self::mapUrl($p);
        }
        unset($p);
        usort($list, function ($a, $b) {
            if ($a['is_self'] !== $b['is_self']) {
                return $a['is_self'] ? -1 : 1;
            }
            if ($a['meters'] === null || $b['meters'] === null) {
                return $a['meters'] === null ? ($b['meters'] === null ? strcmp((string) $a['name'], (string) $b['name']) : 1) : -1;
            }
            return $a['meters'] <=> $b['meters'];
        });
        return $list;
    }

    public static function distance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return 2 * $r * asin(min(1, sqrt($a)));
    }

    public static function bearing(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $y = sin(deg2rad($lng2 - $lng1)) * cos(deg2rad($lat2));
        $x = cos(deg2rad($lat1)) * sin(deg2rad($lat2)) - sin(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($lng2 - $lng1));
        return fmod(rad2deg(atan2($y, $x)) + 360, 360);
    }

    public static function direction(float $deg): string
    {
        $names = ['Kuzey', 'Kuzeydoğu', 'Doğu', 'Güneydoğu', 'Güney', 'Güneybatı', 'Batı', 'Kuzeybatı'];
        return $names[(int) round($deg / 45) % 8];
    }

    public static function km(float $m): string
    {
        return $m < 1000 ? (string) (round($m / 10) * 10) . ' m' : number_format($m / 1000, 1, ',', '.') . ' km';
    }

    /** Kuş uçuşu mesafeye yol payı eklenerek yaklaşık yürüme süresi. */
    public static function walk(float $m): string
    {
        $min = (int) max(1, round($m * 1.3 / 80));
        return $min > 60 ? '' : $min . ' dk yürüme';
    }

    public static function mapUrl(array $p): string
    {
        if ($p['lat'] !== null && $p['lng'] !== null) {
            return 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode(round((float) $p['lat'], 6) . ',' . round((float) $p['lng'], 6));
        }
        $q = trim($p['name'] . ' ' . ($p['address'] ?? '') . ' ' . DB::setting('district', '') . ' ' . DB::setting('city', ''));
        return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($q);
    }

    public static function telHref(string $phone): string
    {
        $d = preg_replace('/\D/', '', $phone);
        if (str_starts_with($d, '90')) {
            $d = substr($d, 2);
        }
        if ($d !== '' && $d[0] !== '0') {
            $d = '0' . $d;
        }
        return $d;
    }

    /**
     * Saatten sonra gelen ek: "19:00'a", "17:00'ye", "13:00'te açılır". Türkçe ses uyumuna göre
     * saatin okunuşundaki son kelimeye bakılır.
     */
    public static function suffix(string $hm, string $case = 'dat'): string
    {
        [$h, $m] = array_map('intval', explode(':', $hm) + [0, 0]);
        $ones = ['sıfır', 'bir', 'iki', 'üç', 'dört', 'beş', 'altı', 'yedi', 'sekiz', 'dokuz'];
        $tens = [1 => 'on', 2 => 'yirmi', 3 => 'otuz', 4 => 'kırk', 5 => 'elli'];
        $n = $m === 0 ? $h : $m;
        $word = $n % 10 === 0 ? ($n === 0 ? 'sıfır' : $tens[intdiv($n, 10)]) : $ones[$n % 10];
        $map = [
            'sıfır' => ["'a", "'da"], 'bir' => ["'e", "'de"], 'iki' => ["'ye", "'de"], 'üç' => ["'e", "'te"],
            'dört' => ["'e", "'te"], 'beş' => ["'e", "'te"], 'altı' => ["'ya", "'da"], 'yedi' => ["'ye", "'de"],
            'sekiz' => ["'e", "'de"], 'dokuz' => ["'a", "'da"], 'on' => ["'a", "'da"], 'yirmi' => ["'ye", "'de"],
            'otuz' => ["'a", "'da"], 'kırk' => ["'a", "'ta"], 'elli' => ["'ye", "'de"],
        ];
        return $hm . $map[$word][$case === 'loc' ? 1 : 0];
    }

    /* ---------- Çalışma saatleri ---------- */

    /** @return array<int,array{0:int,1:int}> */
    public static function ranges(int $isoDay): array
    {
        $out = [];
        foreach (explode(',', (string) DB::setting('hours_' . $isoDay, '')) as $part) {
            $bits = array_map('trim', explode('-', $part));
            if (count($bits) === 2) {
                $a = self::minutes($bits[0]);
                $b = self::minutes($bits[1]);
                if ($a !== null && $b !== null && $b > $a) {
                    $out[] = [$a, $b];
                }
            }
        }
        return $out;
    }

    /** @return array{open:bool,text:string} */
    public static function status(?int $ts = null): array
    {
        $ts ??= time();
        $day = (int) date('N', $ts);
        $now = (int) date('G', $ts) * 60 + (int) date('i', $ts);
        $hm = fn (int $m) => sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
        foreach (self::ranges($day) as [$a, $b]) {
            if ($now >= $a && $now < $b) {
                return ['open' => true, 'text' => 'Açık · ' . self::suffix($hm($b)) . ' kadar'];
            }
        }
        // Sonraki açılış
        foreach (self::ranges($day) as [$a, $b]) {
            if ($a > $now) {
                return ['open' => false, 'text' => 'Kapalı · bugün ' . self::suffix($hm($a), 'loc') . ' açılır'];
            }
        }
        $days = ['', 'Pazartesi', 'Salı', 'Çarşamba', 'Perşembe', 'Cuma', 'Cumartesi', 'Pazar'];
        for ($i = 1; $i <= 7; $i++) {
            $d = ($day + $i - 1) % 7 + 1;
            $r = self::ranges($d);
            if ($r) {
                $when = $i === 1 ? 'yarın' : $days[$d];
                return ['open' => false, 'text' => 'Kapalı · ' . $when . ' ' . self::suffix($hm($r[0][0]), 'loc') . ' açılır'];
            }
        }
        return ['open' => false, 'text' => 'Kapalı'];
    }

    /* ---------- JSON kaynağı ---------- */

    public static function jsonUrl(string $date): string
    {
        $map = [
            '{il}'    => rawurlencode((string) DB::setting('city', '')),
            '{ilce}'  => rawurlencode((string) DB::setting('district', '')),
            '{tarih}' => $date,
            '{gun}'   => date('d.m.Y', (int) strtotime($date)),
        ];
        return strtr((string) DB::setting('json_url', ''), $map);
    }

    /**
     * Önbellekten veya kaynaktan nöbetçi listesi.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function remote(string $date, bool $force = false): array
    {
        $cache = json_decode((string) DB::setting('json_cache', ''), true);
        $fresh = is_array($cache) && ($cache['date'] ?? '') === $date
            && time() - (int) ($cache['at'] ?? 0) < max(5, (int) DB::setting('json_refresh', 30)) * 60;
        if ($fresh && !$force) {
            return $cache['list'] ?? [];
        }
        // Kaynak hata verdiyse 5 dakika yeniden denenmez; ekran istekleri kaynağı yormasın.
        if (!$force && time() - (int) DB::setting('json_fail_at', 0) < 300) {
            return is_array($cache) && ($cache['date'] ?? '') === $date ? ($cache['list'] ?? []) : [];
        }
        $res = with_lock('fetch', fn () => self::fetch($date));
        if ($res !== null && $res['ok']) {
            return $res['list'];
        }
        // Kaynak yanıt vermezse aynı güne ait son liste kullanılır.
        return is_array($cache) && ($cache['date'] ?? '') === $date ? ($cache['list'] ?? []) : [];
    }

    /** @return array{ok:bool,list:array,message:string} */
    public static function fetch(string $date): array
    {
        $url = self::jsonUrl($date);
        if ($url === '') {
            return ['ok' => false, 'list' => [], 'message' => t('source.no_url')];
        }
        $headers = [];
        $h = trim((string) DB::setting('json_header', ''));
        if ($h !== '' && str_contains($h, ':')) {
            $headers[] = $h;
        }
        $r = http_get($url, 10, $headers);
        if (!$r['ok']) {
            DB::setSetting('json_fail_at', time());
            self::log(false, 0, $r['error'] ?: 'HTTP ' . $r['status']);
            return ['ok' => false, 'list' => [], 'message' => $r['error'] ?: 'HTTP ' . $r['status']];
        }
        $list = self::parse($r['body']);
        if (!$list) {
            DB::setSetting('json_fail_at', time());
            self::log(false, 0, t('source.bad_data'));
            return ['ok' => false, 'list' => [], 'message' => t('source.bad_data')];
        }
        DB::setSetting('json_fail_at', 0);
        DB::setSetting('json_cache', json_encode(['date' => $date, 'at' => time(), 'list' => $list], JSON_UNESCAPED_UNICODE));
        self::log(true, count($list), t('source.got', ['n' => count($list)]));
        return ['ok' => true, 'list' => $list, 'message' => t('source.got', ['n' => count($list)])];
    }

    /**
     * Farklı servislerin biçimlerini tanır: kökte dizi veya data/result/eczaneler gibi bir anahtar altında;
     * alan adları Türkçe ya da İngilizce olabilir; konum "lat,lng" metni veya ayrı alanlar olabilir.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function parse(string $body): array
    {
        $j = json_decode($body, true);
        if (!is_array($j)) {
            return [];
        }
        $rows = $j;
        if (!array_is_list($j)) {
            $rows = [];
            foreach (['data', 'result', 'results', 'eczaneler', 'pharmacies', 'items', 'list', 'nobetciler'] as $k) {
                if (isset($j[$k]) && is_array($j[$k])) {
                    $rows = $j[$k];
                    break;
                }
            }
        }
        $pick = function (array $r, array $keys): string {
            foreach ($r as $k => $v) {
                if (in_array(mb_strtolower((string) $k), $keys, true) && (is_string($v) || is_numeric($v))) {
                    return trim((string) $v);
                }
            }
            return '';
        };
        $out = [];
        foreach ($rows as $r) {
            if (!is_array($r)) {
                continue;
            }
            $name = $pick($r, ['name', 'eczane', 'eczaneadi', 'eczane_adi', 'pharmacyname', 'pharmacy_name', 'adi', 'ad', 'title']);
            if ($name === '') {
                continue;
            }
            $name = self::titleCase($name);
            if (!preg_match('/eczane/iu', $name)) {
                $name .= ' Eczanesi';
            }
            $lat = parse_num($pick($r, ['lat', 'latitude', 'enlem']) ?: '');
            $lng = parse_num($pick($r, ['lng', 'lon', 'long', 'longitude', 'boylam']) ?: '');
            $loc = $pick($r, ['loc', 'location', 'konum', 'koordinat']);
            if (($lat === null || $lng === null) && preg_match('/(-?\d+[.,]\d+)\s*[,; ]\s*(-?\d+[.,]\d+)/', $loc, $m)) {
                $lat = (float) str_replace(',', '.', $m[1]);
                $lng = (float) str_replace(',', '.', $m[2]);
            }
            if (($lat === null || $lng === null) && isset($r['location']) && is_array($r['location'])) {
                $lat = isset($r['location']['lat']) ? (float) $r['location']['lat'] : null;
                $lng = isset($r['location']['lng']) ? (float) $r['location']['lng'] : (isset($r['location']['lon']) ? (float) $r['location']['lon'] : null);
            }
            $out[] = [
                'id'       => 0,
                'name'     => mb_substr($name, 0, 120),
                'district' => self::titleCase(mb_substr($pick($r, ['district', 'dist', 'ilce', 'semt', 'mahalle', 'county']), 0, 80)),
                'address'  => self::titleCase(mb_substr($pick($r, ['address', 'adres', 'acikadres']), 0, 255)),
                'phone'    => self::formatPhone(mb_substr($pick($r, ['phone', 'telefon', 'tel', 'phonenumber']), 0, 40)),
                'lat'      => $lat !== null && abs($lat) <= 90 ? $lat : null,
                'lng'      => $lng !== null && abs($lng) <= 180 ? $lng : null,
                'note'     => '',
                'source'   => 'json',
            ];
        }
        return $out;
    }

    /** Tamamı büyük harfle gelen adları Türkçe kurala göre düzeltir: "YENİ HAYAT" → "Yeni Hayat". */
    public static function titleCase(string $s): string
    {
        if ($s === '' || mb_strtoupper($s) !== $s || !preg_match('/\p{Lu}{3}/u', $s)) {
            return $s;
        }
        $lower = mb_strtolower(strtr($s, ['I' => 'ı', 'İ' => 'i']));
        return (string) preg_replace_callback('/(^|[\s\-\/(.])(\p{Ll})/u', function ($m) {
            $c = strtr($m[2], ['i' => 'İ', 'ı' => 'I']);
            return $m[1] . mb_strtoupper($c);
        }, $lower);
    }

    /** "02161234567" → "0216 123 45 67" */
    public static function formatPhone(string $p): string
    {
        $d = self::telHref($p);
        if (strlen($d) === 11) {
            return substr($d, 0, 4) . ' ' . substr($d, 4, 3) . ' ' . substr($d, 7, 2) . ' ' . substr($d, 9, 2);
        }
        return $p;
    }

    private static function log(bool $ok, int $count, string $msg): void
    {
        DB::insert('fetch_log', ['at' => time(), 'ok' => $ok ? 1 : 0, 'count' => $count, 'message' => mb_substr($msg, 0, 250)]);
        $keep = DB::val('SELECT id FROM {fetch_log} ORDER BY id DESC LIMIT 1 OFFSET 99');
        if ($keep) {
            DB::q('DELETE FROM {fetch_log} WHERE id <= ?', [(int) $keep]);
        }
    }

    /* ---------- Ekran verisi ---------- */

    /** @return array<string,mixed> */
    public static function payload(): array
    {
        $date   = self::dutyDate();
        $list   = self::today();
        $status = self::status();
        $s      = fn (string $k, string $d = '') => (string) DB::setting($k, $d);
        $limit  = max(1, min(12, (int) $s('screen_limit', '6')));
        $selfOn = (bool) array_filter($list, fn ($p) => $p['is_self']);
        $switch = $s('switch_time', '08:30');
        $next   = date('Y-m-d', strtotime($date . ' +1 day'));

        $items = [];
        foreach (array_slice(array_values(array_filter($list, fn ($p) => !$p['is_self'])), 0, $limit) as $p) {
            $items[] = [
                'name'     => $p['name'],
                'district' => (string) ($p['district'] ?? ''),
                'address'  => (string) ($p['address'] ?? ''),
                'phone'    => (string) ($p['phone'] ?? ''),
                'km'       => $p['meters'] !== null ? self::km($p['meters']) : '',
                'dir'      => $p['meters'] !== null && $p['meters'] > 40 ? self::direction($p['bearing']) : '',
                'deg'      => $p['meters'] !== null ? round($p['bearing'], 1) : null,
                'walk'     => $p['meters'] !== null ? self::walk($p['meters']) : '',
                'map'      => $p['map'],
            ];
        }
        if ($selfOn && !$status['open']) {
            $status = ['open' => true, 'text' => 'Nöbetçi · ' . date('j', strtotime($next)) . ' ' . ['Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'][(int) date('n', strtotime($next)) - 1] . ' ' . self::suffix($switch) . ' kadar açık'];
        }
        $theme = $s('screen_theme', 'auto');
        if ($theme === 'auto') {
            $theme = $status['open'] ? 'gunduz' : 'gece';
        }

        return [
            'ok'        => true,
            'version'   => (int) $s('screen_version', '1'),
            'now'       => time(),
            'tz_offset' => (int) date('Z'),
            'self'      => [
                'name'    => $s('site_title'),
                'phone'   => $s('self_phone'),
                'address' => $s('self_address'),
                'on_duty' => $selfOn,
            ],
            'status'    => $status,
            'duty'      => [
                'date'  => tr_date((int) strtotime($date)),
                'until' => tr_date((int) strtotime($next), false) . ' saat ' . self::suffix($switch) . ' kadar',
                'count' => count($list),
            ],
            'list'      => $items,
            'screen'    => [
                'theme'  => $theme,
                'note'   => $s('screen_note'),
                'ticker' => $s('screen_ticker'),
                'qr'     => $s('screen_qr', '1') === '1',
            ],
            'place'     => trim($s('district') . ($s('city') !== '' ? ' / ' . $s('city') : ''), ' /'),
        ];
    }
}
