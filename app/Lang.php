<?php

declare(strict_types=1);

final class Lang
{
    public const AVAILABLE = ['tr' => 'Türkçe', 'en' => 'English'];

    public static string $code = 'tr';

    /** @var array<string,string> */
    private static array $strings = [];

    /** @var array<string,string> */
    private static array $fallback = [];

    public static function load(string $code): void
    {
        $code = isset(self::AVAILABLE[$code]) ? $code : 'tr';
        self::$code = $code;
        self::$strings = require APP_ROOT . '/lang/' . $code . '.php';
        self::$fallback = $code === 'tr' ? [] : require APP_ROOT . '/lang/tr.php';
    }

    /** Panel dili: ?lang= > çerez > kurulum dili > tarayıcı dili. */
    public static function detect(): string
    {
        $q = $_GET['lang'] ?? null;
        if (is_string($q) && isset(self::AVAILABLE[$q])) {
            if (PHP_SAPI !== 'cli' && !headers_sent()) {
                setcookie(APP_SLUG . '_lang', $q, ['expires' => time() + 31536000, 'path' => '/', 'samesite' => 'Lax']);
            }
            return $q;
        }
        $c = $_COOKIE[APP_SLUG . '_lang'] ?? null;
        if (is_string($c) && isset(self::AVAILABLE[$c])) {
            return $c;
        }
        if (DB::ready()) {
            return (string) DB::setting('lang', 'tr');
        }
        $al = strtolower((string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''));
        return str_starts_with($al, 'tr') || $al === '' ? 'tr' : 'en';
    }

    public static function get(string $key, array $vars = []): string
    {
        $s = self::$strings[$key] ?? self::$fallback[$key] ?? $key;
        foreach ($vars as $k => $v) {
            $s = str_replace('{' . $k . '}', (string) $v, $s);
        }
        return $s;
    }
}

function t(string $key, array $vars = []): string
{
    return Lang::get($key, $vars);
}
