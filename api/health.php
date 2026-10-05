<?php
/**
 * Health check endpoint for hosting monitors (e.g. Render) and keep-alive crons.
 *
 * Public, no login required, no session started. Always answers HTTP 200:
 * - service alive  -> {"status":"ok","db":"up",...}
 * - service alive but DB unreachable -> HTTP 200 with the standard DB error page
 *   (the app is awake; the "db" check below reports it when reachable).
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../config/database.php';

$dbStatus = 'down';
try {
    $db = Database::getConnection();
    $db->query('SELECT 1');
    $dbStatus = 'up';
} catch (Throwable $e) {
    $dbStatus = 'down';
}

http_response_code(200);
echo json_encode([
    'status' => 'ok',
    'db'     => $dbStatus,
    'time'   => date('c'),
]);
