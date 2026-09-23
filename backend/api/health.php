<?php
require_once dirname(__DIR__) . '/bootstrap.php';

try {
    db()->query('SELECT 1');
    jsonResponse([
        'ok' => true,
        'app' => envValue('APP_NAME', 'Etki'),
        'database' => 'connected',
        'time' => date(DATE_ATOM),
    ]);
} catch (Throwable $e) {
    jsonResponse(['ok' => false, 'database' => 'disconnected', 'message' => $e->getMessage()], 500);
}
