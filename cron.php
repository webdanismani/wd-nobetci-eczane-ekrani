<?php
/**
 * İsteğe bağlı zamanlayıcı (yalnızca JSON kaynağı kullanılıyorsa gerekir).
 * Ekran açıkken liste zaten kendiliğinden yenilenir. Her 30 dakikada bir çalıştırın:
 *
 *   php /yol/cron.php
 *   veya: https://alanadi.com/klasor/cron.php?key=CRON_ANAHTARI
 */

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    $key = get_str('key');
    if ($key === '' || !hash_equals((string) (App::$config['cron_key'] ?? ''), $key)) {
        http_response_code(403);
        exit('forbidden');
    }
    header('Content-Type: text/plain; charset=utf-8');
}

if (DB::setting('source', 'manual') !== 'json') {
    echo "Kaynak: elle giriş. Yapılacak bir şey yok.\n";
    exit;
}
$r = Duty::fetch(Duty::dutyDate());
echo ($r['ok'] ? 'OK ' : 'HATA ') . $r['message'] . "\n";
