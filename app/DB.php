<?php

declare(strict_types=1);

/**
 * Basit PDO katmanı. Sorgularda tablo adları {tablo} biçiminde yazılır,
 * MySQL'de önek otomatik eklenir. Zamanlar UNIX saniyesidir; SQLite ve MySQL aynı sorgularla çalışır.
 */
final class DB
{
    private static ?PDO $pdo = null;
    private static string $prefix = '';
    private static string $driver = 'sqlite';

    /** @var array<string,string|null>|null */
    private static ?array $settings = null;

    /** @param array<string,mixed> $cfg */
    public static function connect(array $cfg): void
    {
        $opts = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        self::$driver = $cfg['driver'] === 'mysql' ? 'mysql' : 'sqlite';
        self::$prefix = (string) ($cfg['prefix'] ?? '');
        self::$settings = null;

        if (self::$driver === 'mysql') {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $cfg['host'], (int) ($cfg['port'] ?? 3306), $cfg['name']);
            self::$pdo = new PDO($dsn, (string) $cfg['user'], (string) $cfg['pass'], $opts);
            self::$pdo->exec("SET time_zone = '+00:00'");
        } else {
            self::$pdo = new PDO('sqlite:' . $cfg['path'], null, null, $opts);
            self::$pdo->exec('PRAGMA journal_mode = WAL');
            self::$pdo->exec('PRAGMA busy_timeout = 8000');
            self::$pdo->exec('PRAGMA synchronous = NORMAL');
            self::$pdo->exec('PRAGMA foreign_keys = ON');
        }
    }

    public static function ready(): bool
    {
        return self::$pdo !== null;
    }

    public static function driver(): string
    {
        return self::$driver;
    }

    private static function sql(string $sql): string
    {
        return (string) preg_replace_callback('/\{([a-z_]+)\}/', fn ($m) => self::$prefix . $m[1], $sql);
    }

    public static function q(string $sql, array $params = []): PDOStatement
    {
        $st = self::$pdo->prepare(self::sql($sql));
        $st->execute(array_values($params));
        return $st;
    }

    /** @return array<int,array<string,mixed>> */
    public static function all(string $sql, array $params = []): array
    {
        return self::q($sql, $params)->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public static function row(string $sql, array $params = []): ?array
    {
        $r = self::q($sql, $params)->fetch();
        return $r === false ? null : $r;
    }

    public static function val(string $sql, array $params = []): mixed
    {
        $v = self::q($sql, $params)->fetchColumn();
        return $v === false ? null : $v;
    }

    /** @param array<string,mixed> $data */
    public static function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $sql  = 'INSERT INTO {' . $table . '} (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')';
        self::q($sql, array_values($data));
        return (int) self::$pdo->lastInsertId();
    }

    /** @param array<string,mixed> $data */
    public static function update(string $table, array $data, string $where, array $params = []): void
    {
        $set = implode(',', array_map(fn ($c) => $c . ' = ?', array_keys($data)));
        self::q('UPDATE {' . $table . '} SET ' . $set . ' WHERE ' . $where, array_merge(array_values($data), $params));
    }

    public static function transaction(callable $fn): mixed
    {
        self::$pdo->beginTransaction();
        try {
            $r = $fn();
            self::$pdo->commit();
            return $r;
        } catch (Throwable $e) {
            self::$pdo->rollBack();
            throw $e;
        }
    }

    /* ---------- Ayarlar ---------- */

    public static function setting(string $key, mixed $default = null): mixed
    {
        if (self::$settings === null) {
            self::$settings = [];
            foreach (self::all('SELECT k, v FROM {settings}') as $r) {
                self::$settings[$r['k']] = $r['v'];
            }
        }
        return self::$settings[$key] ?? $default;
    }

    public static function setSetting(string $key, string|int|float|null $value): void
    {
        $value = $value === null ? null : (string) $value;
        if (self::val('SELECT COUNT(*) FROM {settings} WHERE k = ?', [$key])) {
            self::q('UPDATE {settings} SET v = ? WHERE k = ?', [$value, $key]);
        } else {
            self::q('INSERT INTO {settings} (k, v) VALUES (?, ?)', [$key, $value]);
        }
        if (self::$settings !== null) {
            self::$settings[$key] = $value;
        }
    }

    /* ---------- Şema ---------- */

    /** @return string[] */
    public static function schema(): array
    {
        $my  = self::$driver === 'mysql';
        $id  = $my ? 'INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $eng = $my ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
        $p   = self::$prefix;

        return array_merge([
            "CREATE TABLE {$p}settings (k VARCHAR(64) NOT NULL PRIMARY KEY, v TEXT NULL){$eng}",
            "CREATE TABLE {$p}users (
                id {$id}, username VARCHAR(64) NOT NULL, password_hash VARCHAR(255) NOT NULL,
                created_at INT NOT NULL DEFAULT 0, last_login INT NOT NULL DEFAULT 0
            ){$eng}",
            "CREATE UNIQUE INDEX {$p}users_username ON {$p}users (username)",
            "CREATE TABLE {$p}login_attempts (ip VARCHAR(64) NOT NULL, attempted_at INT NOT NULL){$eng}",
            "CREATE INDEX {$p}login_attempts_ip ON {$p}login_attempts (ip, attempted_at)",
        ], Product::schema($id, $eng, $p, $my));
    }

    public static function migrate(): void
    {
        foreach (self::schema() as $sql) {
            self::$pdo->exec($sql);
        }
        self::setSetting('db_version', Product::DB_VERSION);
    }

    /** Sonraki sürümlerde tablo değişikliği olursa Product::upgrade() çalışır. */
    public static function upgrade(): void
    {
        $v = (int) self::setting('db_version', Product::DB_VERSION);
        if ($v < Product::DB_VERSION) {
            Product::upgrade($v);
            self::setSetting('db_version', Product::DB_VERSION);
        }
    }

    /** Kurulumdaki tablo önekini dışarıya verir (ör. veri dışa aktarımı için). */
    public static function prefix(): string
    {
        return self::$prefix;
    }
}
