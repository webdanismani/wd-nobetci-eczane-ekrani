<?php
/**
 * Ekranın dakikada bir çektiği veri.
 */

declare(strict_types=1);

define('APP_JSON', true);
require __DIR__ . '/app/bootstrap.php';

if (!Product::screenAllowed()) {
    json_out(['ok' => false, 'error' => 'forbidden'], 403);
}
json_out(Duty::payload());
