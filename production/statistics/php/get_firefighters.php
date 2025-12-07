<?php
// Environment-aware error handling for JSON responses
$isProduction = (getenv('APP_ENV') === 'production');
$debugMode = filter_var(getenv('APP_DEBUG') ?? '0', FILTER_VALIDATE_BOOLEAN);
error_reporting(E_ALL);
ini_set('display_errors', ($isProduction && !$debugMode) ? '0' : '1');
ini_set('log_errors', '1');

// Start output buffering to catch any accidental output
if (!ob_get_level()) {
    ob_start();
}

// Register error handler to catch any errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (ob_get_level() > 0) {
            ob_clean();
        }
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        echo json_encode([
            'success' => false,
            'message' => 'Internal server error',
            'error' => $error['message']
        ]);
        exit;
    }
});

require_once 'common/database_utils.php';

try {
    // Get all firefighters with their basic information
    $sql = "SELECT 
                id,
                name,
                badge_number,
                rank,
                specialization
            FROM firefighters 
            WHERE availability = 1
            ORDER BY name ASC";
    
    $firefighters = DatabaseUtils::executeQuery($sql);
    
    DatabaseUtils::sendResponse(true, ['firefighters' => $firefighters], 'Firefighters loaded successfully');
    
} catch (Exception $e) {
    DatabaseUtils::sendError('Failed to load firefighters', $e->getMessage());
} catch (Throwable $e) {
    DatabaseUtils::sendError('Failed to load firefighters', $e->getMessage());
}
?>
