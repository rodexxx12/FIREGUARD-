<?php
/**
 * Application Health Check Endpoint
 *
 * Use this endpoint for uptime and basic health monitoring.
 * Returns 200 with JSON when the app and DB are reachable, 500 otherwise.
 */

declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/core/config/config.php';
require_once __DIR__ . '/core/database/database.php';

$status = [
    'status'    => 'ok',
    'app_env'   => env('APP_ENV', 'production'),
    'timestamp' => date('c'),
    'checks'    => [
        'db' => 'pending',
    ],
];

http_response_code(200);

try {
    $conn = getDatabaseConnection();
    $stmt = $conn->prepare('SELECT 1');
    $stmt->execute();
    $stmt->fetchColumn();
    $status['checks']['db'] = 'ok';
} catch (Throwable $e) {
    $status['status']        = 'degraded';
    $status['checks']['db']  = 'error';
    $status['error']         = 'Database check failed';
    http_response_code(500);
}

echo json_encode($status);
exit;


