<?php

declare(strict_types=1);

final class Auth
{
    private const MAX_ATTEMPTS = 5;
    private const WINDOW = 900;

    public static function user(): ?array
    {
        session_boot();
        $id = $_SESSION['uid'] ?? null;
        if (!$id) {
            return null;
        }
        return DB::row('SELECT id, username FROM {users} WHERE id = ?', [(int) $id]);
    }

    public static function require(): array
    {
        $u = self::user();
        if (!$u) {
            redirect(base_url('admin/login.php'));
        }
        $url = rtrim(absolute_url(''), '/');
        if (DB::setting('site_url') !== $url) {
            DB::setSetting('site_url', $url);
        }
        return $u;
    }

    public static function blocked(): bool
    {
        $n = (int) DB::val('SELECT COUNT(*) FROM {login_attempts} WHERE ip = ? AND attempted_at > ?', [client_ip(), time() - self::WINDOW]);
        return $n >= self::MAX_ATTEMPTS;
    }

    public static function attempt(string $username, string $password): bool
    {
        if (self::blocked()) {
            return false;
        }
        $u = DB::row('SELECT * FROM {users} WHERE username = ?', [$username]);
        if (!$u || !password_verify($password, $u['password_hash'])) {
            DB::q('INSERT INTO {login_attempts} (ip, attempted_at) VALUES (?, ?)', [client_ip(), time()]);
            DB::q('DELETE FROM {login_attempts} WHERE attempted_at < ?', [time() - 86400]);
            usleep(400000);
            return false;
        }
        if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
            DB::update('users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], 'id = ?', [$u['id']]);
        }
        DB::q('DELETE FROM {login_attempts} WHERE ip = ?', [client_ip()]);
        DB::update('users', ['last_login' => time()], 'id = ?', [$u['id']]);
        session_boot();
        session_regenerate_id(true);
        $_SESSION['uid'] = (int) $u['id'];
        return true;
    }

    public static function changePassword(int $uid, string $current, string $new): ?string
    {
        $u = DB::row('SELECT * FROM {users} WHERE id = ?', [$uid]);
        if (!$u || !password_verify($current, $u['password_hash'])) {
            return t('account.err_current');
        }
        if (strlen($new) < 8) {
            return t('install.err_password');
        }
        DB::update('users', ['password_hash' => password_hash($new, PASSWORD_DEFAULT)], 'id = ?', [$uid]);
        session_regenerate_id(true);
        return null;
    }

    public static function logout(): void
    {
        session_boot();
        $_SESSION = [];
        session_destroy();
    }
}
