<?php
/**
 * Production Error Handler
 */
class ErrorHandler {
    private static $isProduction = false;
    
    /**
     * Initialize error handler
     * 
     * @param bool $isProduction Production mode flag
     */
    public static function init($isProduction = false) {
        self::$isProduction = $isProduction;
        
        if ($isProduction) {
            error_reporting(E_ALL);
            ini_set('display_errors', '0');
            ini_set('log_errors', '1');
        }
        
        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }
    
    /**
     * Handle PHP errors
     */
    public static function handleError($errno, $errstr, $errfile, $errline) {
        if (!(error_reporting() & $errno)) {
            return false;
        }
        
        $errorMessage = sprintf(
            "Error [%s]: %s in %s on line %s",
            $errno,
            $errstr,
            $errfile,
            $errline
        );
        
        error_log($errorMessage);
        
        if (self::$isProduction) {
            // Don't expose error details in production
            return true;
        }
        
        // Development mode - show error
        echo "<div style='color: red; padding: 10px; border: 1px solid red; margin: 10px;'>";
        echo "<strong>Error:</strong> " . htmlspecialchars($errstr) . "<br>";
        echo "<strong>File:</strong> " . htmlspecialchars($errfile) . "<br>";
        echo "<strong>Line:</strong> " . htmlspecialchars($errline);
        echo "</div>";
        
        return true;
    }
    
    /**
     * Handle uncaught exceptions
     */
    public static function handleException($exception) {
        $errorMessage = sprintf(
            "Uncaught Exception: %s in %s on line %s\nStack trace:\n%s",
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString()
        );
        
        error_log($errorMessage);
        
        if (self::$isProduction) {
            http_response_code(500);
            if (php_sapi_name() === 'cli') {
                echo "An error occurred. Please check the logs.\n";
            } else {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'An error occurred']);
            }
        } else {
            echo "<div style='color: red; padding: 10px; border: 1px solid red; margin: 10px;'>";
            echo "<strong>Exception:</strong> " . htmlspecialchars($exception->getMessage()) . "<br>";
            echo "<strong>File:</strong> " . htmlspecialchars($exception->getFile()) . "<br>";
            echo "<strong>Line:</strong> " . htmlspecialchars($exception->getLine());
            echo "<pre>" . htmlspecialchars($exception->getTraceAsString()) . "</pre>";
            echo "</div>";
        }
        
        exit(1);
    }
    
    /**
     * Handle fatal errors
     */
    public static function handleShutdown() {
        $error = error_get_last();
        if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
            self::handleError(
                $error['type'],
                $error['message'],
                $error['file'],
                $error['line']
            );
        }
    }
}



























