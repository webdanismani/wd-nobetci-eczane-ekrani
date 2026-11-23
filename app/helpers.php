<?php

declare(strict_types=1);

final class App
{
    /** @var array<string,mixed> */
    public static array $config = [];
}

function e(mixed $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Uygulamanın kök URL yolu (/ekran/ gibi). Giriş dosyasının klasör derinliğine göre hesaplanır,
 * böylece alt klasöre kurulum ve alan adı değişikliği sorun olmaz.
 */
function base_path(): string
{
    if (PHP_SAPI === 'cli') {
        return '/';
    }
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    for ($i = 0; $i < APP_DEPTH; $i++) {
        $dir = dirname($dir);
    }
    $dir = rtrim($dir, '/') . '/';
    return $dir === '//' ? '/' : $dir;
}

function base_url(string $path = ''): string
{
    return base_path() . ltrim($path, '/');
}

function absolute_url(string $path = ''): string
{
    if (PHP_SAPI !== 'cli' && !empty($_SERVER['HTTP_HOST'])) {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        return ($https ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . base_url($path);
    }
    $stored = (string) DB::setting('site_url', '');
    return rtrim($stored, '/') . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    return base_url('assets/' . $path) . '?v=' . APP_VERSION;
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function json_out(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function post_str(string $key, string $default = ''): string
{
    $v = $_POST[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

function post_int(string $key, int $default = 0): int
{
    $v = $_POST[$key] ?? null;
    return is_numeric($v) ? (int) $v : $default;
}

function post_bool(string $key): int
{
    return !empty($_POST[$key]) ? 1 : 0;
}

/** "1.250,50", "1250.5", "1 250,50" gibi yazımları sayıya çevirir. */
function parse_num(string $s): ?float
{
    $s = str_replace([' ', "\u{00A0}", '₺', 'TL', '%'], '', trim($s));
    if ($s === '') {
        return null;
    }
    if (str_contains($s, ',')) {
        $s = str_replace('.', '', $s);
        $s = str_replace(',', '.', $s);
    } elseif (substr_count($s, '.') > 1 || preg_match('/^-?[1-9]\d{0,2}(\.\d{3})+$/', $s)) {
        // Türkçede "9.650" dokuz bin altı yüz elli demektir; kuruş virgülle yazılır.
        $s = str_replace('.', '', $s);
    }
    return is_numeric($s) ? (float) $s : null;
}

function post_num(string $key, ?float $default = null): ?float
{
    $v = $_POST[$key] ?? null;
    if (!is_string($v)) {
        return $default;
    }
    $n = parse_num($v);
    return $n ?? $default;
}

function get_str(string $key, string $default = ''): string
{
    $v = $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

function get_int(string $key, int $default = 0): int
{
    $v = $_GET[$key] ?? null;
    return is_numeric($v) ? (int) $v : $default;
}

function client_ip(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

function random_token(int $bytes = 16): string
{
    return bin2hex(random_bytes($bytes));
}

/* ---------- Oturum, CSRF, bildirim ---------- */

function session_boot(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_name(APP_SLUG . '_sess');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => base_path(),
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function csrf_token(): string
{
    session_boot();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = random_token(32);
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    $sent = $_POST['_csrf'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(419);
        exit(e(t('err.csrf')));
    }
}

function flash(string $msg, string $type = 'ok'): void
{
    session_boot();
    $_SESSION['flash'][] = ['msg' => $msg, 'type' => $type];
}

/** @return array<int,array{msg:string,type:string}> */
function flashes(): array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        if (headers_sent() || empty($_COOKIE[APP_SLUG . '_sess'])) {
            return [];
        }
        session_boot();
    }
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/* ---------- Biçimlendirme ---------- */

function fmt_num(float $n, int $dec = 2): string
{
    return Lang::$code === 'tr' ? number_format($n, $dec, ',', '.') : number_format($n, $dec, '.', ',');
}

function fmt_dt(?int $ts, bool $withTime = true): string
{
    if (!$ts) {
        return '—';
    }
    if (Lang::$code === 'tr') {
        return date($withTime ? 'd.m.Y H:i' : 'd.m.Y', $ts);
    }
    return date($withTime ? 'M j, Y H:i' : 'M j, Y', $ts);
}

function fmt_ago(?int $ts): string
{
    if (!$ts) {
        return t('time.never');
    }
    $diff = time() - $ts;
    if ($diff < 60) {
        return t('time.just_now');
    }
    if ($diff < 3600) {
        return t('time.min_ago', ['n' => intdiv($diff, 60)]);
    }
    if ($diff < 86400) {
        return t('time.hour_ago', ['n' => intdiv($diff, 3600)]);
    }
    return fmt_dt($ts);
}

/** Türkçe tarih adları (TV ekranı için sunucu tarafında da gerekir). */
function tr_date(int $ts, bool $weekday = true): string
{
    $months = ['Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];
    $days   = ['Pazar', 'Pazartesi', 'Salı', 'Çarşamba', 'Perşembe', 'Cuma', 'Cumartesi'];
    $s = (int) date('j', $ts) . ' ' . $months[(int) date('n', $ts) - 1] . ' ' . date('Y', $ts);
    return $weekday ? $s . ' ' . $days[(int) date('w', $ts)] : $s;
}

/* ---------- Dış istek ---------- */

/**
 * Basit GET isteği. Kaynak sitelerin yavaşlığı ekranı kilitlemesin diye kısa zaman aşımı kullanır.
 *
 * @return array{ok:bool,status:int,body:string,error:string}
 */
function http_get(string $url, int $timeout = 8, array $headers = []): array
{
    if (!preg_match('~^https?://~i', $url)) {
        return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'invalid url'];
    }
    $ua = APP_NAME . '/' . APP_VERSION . ' (+' . APP_AUTHOR_URL . ')';
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_CONNECTTIMEOUT => min(5, $timeout),
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_USERAGENT      => $ua,
            CURLOPT_HTTPHEADER     => array_merge(['Accept: application/json, text/xml, */*'], $headers),
            CURLOPT_ENCODING       => '',
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        ]);
        $body   = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err    = curl_error($ch);
        curl_close($ch);
        $body = is_string($body) ? $body : '';
        return ['ok' => $body !== '' && $status >= 200 && $status < 300, 'status' => $status, 'body' => $body, 'error' => $err ?: ($status >= 300 ? 'HTTP ' . $status : '')];
    }
    $ctx  = stream_context_create(['http' => ['timeout' => $timeout, 'header' => 'User-Agent: ' . $ua . "\r\n" . implode("\r\n", $headers), 'ignore_errors' => true]]);
    $body = @file_get_contents($url, false, $ctx);
    $status = 0;
    foreach ($http_response_header ?? [] as $h) {
        if (preg_match('~^HTTP/\S+\s+(\d{3})~', $h, $m)) {
            $status = (int) $m[1];
        }
    }
    $body = is_string($body) ? $body : '';
    return ['ok' => $body !== '' && $status >= 200 && $status < 300, 'status' => $status, 'body' => $body, 'error' => $body === '' ? 'no response' : ($status >= 300 ? 'HTTP ' . $status : '')];
}

/** Aynı anda iki ekranın aynı kaynağı yenilemesini engelleyen kilit. */
function with_lock(string $name, callable $fn): mixed
{
    $fh = @fopen(APP_DATA . '/' . $name . '.lock', 'c');
    if (!$fh) {
        return $fn();
    }
    if (!flock($fh, LOCK_EX | LOCK_NB)) {
        fclose($fh);
        return null;
    }
    try {
        return $fn();
    } finally {
        flock($fh, LOCK_UN);
        fclose($fh);
    }
}
