<?php
/**
 * Centralized Error Handler
 * Provides consistent error handling across the application
 */

/**
 * Initialize error handling based on environment
 * 
 * @param string|null $logFile Custom log file path (optional)
 * @return void
 */
function initializeErrorHandling($logFile = null) {
    // Determine if we're in production
    $isProduction = (
        getenv('APP_ENV') === 'production' || 
        (isset($_SERVER['HTTP_HOST']) && 
         strpos($_SERVER['HTTP_HOST'], 'localhost') === false &&
         strpos($_SERVER['HTTP_HOST'], '127.0.0.1') === false &&
         strpos($_SERVER['HTTP_HOST'], '.local') === false)
    );
    
    // Always log errors
    error_reporting(E_ALL);
    
    if ($isProduction) {
        // Production: Hide errors from users, log everything
        ini_set('display_errors', '0');
        ini_set('log_errors', '1');
        
        // Set up error log directory
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        
        // Set error log file
        $errorLogFile = $logFile ?? $logDir . '/php_errors.log';
        ini_set('error_log', $errorLogFile);
        
        // Hide error details from users
        ini_set('expose_php', '0');
        
    } else {
        // Development: Show errors for debugging
        ini_set('display_errors', '1');
        ini_set('log_errors', '1');
        
        // Still log to file in development for reference
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        
        $errorLogFile = $logFile ?? $logDir . '/php_errors_dev.log';
        ini_set('error_log', $errorLogFile);
    }
    
    // Set custom error handler for better logging
    set_error_handler(function($errno, $errstr, $errfile, $errline) use ($isProduction) {
        $errorTypes = [
            E_ERROR => 'ERROR',
            E_WARNING => 'WARNING',
            E_PARSE => 'PARSE',
            E_NOTICE => 'NOTICE',
            E_CORE_ERROR => 'CORE_ERROR',
            E_CORE_WARNING => 'CORE_WARNING',
            E_COMPILE_ERROR => 'COMPILE_ERROR',
            E_COMPILE_WARNING => 'COMPILE_WARNING',
            E_USER_ERROR => 'USER_ERROR',
            E_USER_WARNING => 'USER_WARNING',
            E_USER_NOTICE => 'USER_NOTICE',
            E_STRICT => 'STRICT',
            E_RECOVERABLE_ERROR => 'RECOVERABLE_ERROR',
            E_DEPRECATED => 'DEPRECATED',
            E_USER_DEPRECATED => 'USER_DEPRECATED'
        ];
        
        $errorType = $errorTypes[$errno] ?? 'UNKNOWN';
        $message = sprintf(
            "[%s] %s in %s on line %d",
            $errorType,
            $errstr,
            $errfile,
            $errline
        );
        
        error_log($message);
        
        // In production, don't display errors
        if (!$isProduction && ($errno & (E_ERROR | E_WARNING | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR))) {
            return false; // Let PHP handle it
        }
        
        return true;
    });
    
    // Handle fatal errors
    register_shutdown_function(function() use ($isProduction) {
        $error = error_get_last();
        if ($error !== null && ($error['type'] & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR))) {
            $message = sprintf(
                "[FATAL] %s in %s on line %d",
                $error['message'],
                $error['file'],
                $error['line']
            );
            error_log($message);
            
            if (!$isProduction) {
                // In development, show the error
                echo "<pre>Fatal Error: {$message}</pre>";
            } else {
                // In production, show generic error
                http_response_code(500);
                echo json_encode(['error' => 'An internal error occurred. Please try again later.']);
            }
        }
    });
}

/**
 * Log an error message
 * 
 * @param string $message Error message
 * @param array $context Additional context
 * @return void
 */
function logError($message, array $context = []) {
    $contextStr = !empty($context) ? ' | Context: ' . json_encode($context) : '';
    error_log("[CUSTOM ERROR] {$message}{$contextStr}");
}

/**
 * Log a warning message
 * 
 * @param string $message Warning message
 * @param array $context Additional context
 * @return void
 */
function logWarning($message, array $context = []) {
    $contextStr = !empty($context) ? ' | Context: ' . json_encode($context) : '';
    error_log("[WARNING] {$message}{$contextStr}");
}

