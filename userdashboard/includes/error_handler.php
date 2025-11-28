<?php
/**
 * Error Handling Configuration
 * 
 * Configures error reporting and exception handling based on environment
 */

if (!function_exists('configureErrorHandling')) {
    function configureErrorHandling() {
        $isProduction = getenv('APP_ENV') === 'production';
        
        if ($isProduction) {
            // Production: log errors, don't display
            error_reporting(E_ALL);
            ini_set('display_errors', '0');
            ini_set('log_errors', '1');
            
            // Ensure log directory exists
            $logDir = __DIR__ . '/../../logs';
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0755, true);
            }
            
            ini_set('error_log', $logDir . '/php_errors.log');
        } else {
            // Development: show all errors
            error_reporting(E_ALL);
            ini_set('display_errors', '1');
            ini_set('log_errors', '1');
        }
    }
    
    // Set custom error handler
    set_error_handler(function($severity, $message, $file, $line) {
        // Don't handle errors that are below the error_reporting level
        if (!(error_reporting() & $severity)) {
            return false;
        }
        
        $isProduction = getenv('APP_ENV') === 'production';
        
        // Log the error
        error_log("PHP Error ($severity): $message in $file on line $line");
        
        // In production, show generic message
        if ($isProduction) {
            // Only show generic message if output hasn't started
            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: application/json');
                die(json_encode(['error' => 'An error occurred. Please try again later.']));
            }
        }
        
        return false; // Let PHP handle the error normally
    });
    
    // Set custom exception handler
    set_exception_handler(function($exception) {
        $isProduction = getenv('APP_ENV') === 'production';
        
        error_log("Uncaught Exception: " . $exception->getMessage());
        error_log("Stack trace: " . $exception->getTraceAsString());
        
        if ($isProduction) {
            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: application/json');
                die(json_encode(['error' => 'An error occurred. Please try again later.']));
            }
        } else {
            // In development, show the exception details
            echo "<pre>";
            echo "Uncaught Exception: " . $exception->getMessage() . "\n";
            echo "Stack trace:\n" . $exception->getTraceAsString();
            echo "</pre>";
        }
    });
}

// Configure error handling on include
configureErrorHandling();








