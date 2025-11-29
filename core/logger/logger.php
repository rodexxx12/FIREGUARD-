<?php
/**
 * PSR-3 Compatible Logger Module
 * 
 * Provides structured logging with multiple log levels
 * 
 * Usage:
 * require_once __DIR__ . '/../../core/config/config.php';
 * require_once __DIR__ . '/../../core/logger/logger.php';
 * 
 * logInfo('User logged in', ['user_id' => 123]);
 * logError('Database connection failed', ['error' => $e->getMessage()]);
 */

// Load configuration
if (!defined('LOG_DIR')) {
    require_once __DIR__ . '/../config/constants.php';
}

/**
 * Log message at specified level
 * 
 * @param string $level Log level (DEBUG, INFO, WARNING, ERROR, CRITICAL)
 * @param string $message Log message
 * @param array $context Additional context data
 * @return void
 */
if (!function_exists('logMessage')) {
    function logMessage($level, $message, $context = []) {
        // Check if logging is enabled
        if (!config('app_debug', false) && $level === 'DEBUG') {
            return; // Skip debug logs in production
        }
        
        // Ensure log directory exists
        if (!is_dir(LOG_DIR)) {
            @mkdir(LOG_DIR, 0755, true);
        }
        
        // Prepare log entry
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'level' => strtoupper($level),
            'message' => $message,
            'context' => $context,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown'
        ];
        
        // Add user information if available
        if (isset($_SESSION['user_id'])) {
            $logEntry['user_id'] = $_SESSION['user_id'];
        }
        if (isset($_SESSION['admin_id'])) {
            $logEntry['admin_id'] = $_SESSION['admin_id'];
        }
        
        // Format log line
        $logLine = sprintf(
            "[%s] %s: %s | Context: %s | IP: %s\n",
            $logEntry['timestamp'],
            $logEntry['level'],
            $logEntry['message'],
            json_encode($logEntry['context']),
            $logEntry['ip']
        );
        
        // Determine log file
        $logFile = LOG_DIR . strtolower($level) . '.log';
        
        // Write to log file
        @file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
        
        // Also log to PHP error log for critical errors
        if (in_array($level, ['ERROR', 'CRITICAL'])) {
            error_log(sprintf("%s: %s | Context: %s", $level, $message, json_encode($context)));
        }
    }
}

/**
 * Log debug message
 * 
 * @param string $message Log message
 * @param array $context Additional context
 * @return void
 */
if (!function_exists('logDebug')) {
    function logDebug($message, $context = []) {
        logMessage('DEBUG', $message, $context);
    }
}

/**
 * Log info message
 * 
 * @param string $message Log message
 * @param array $context Additional context
 * @return void
 */
if (!function_exists('logInfo')) {
    function logInfo($message, $context = []) {
        logMessage('INFO', $message, $context);
    }
}

/**
 * Log warning message
 * 
 * @param string $message Log message
 * @param array $context Additional context
 * @return void
 */
if (!function_exists('logWarning')) {
    function logWarning($message, $context = []) {
        logMessage('WARNING', $message, $context);
    }
}

/**
 * Log error message
 * 
 * @param string $message Log message
 * @param array $context Additional context
 * @return void
 */
if (!function_exists('logError')) {
    function logError($message, $context = []) {
        logMessage('ERROR', $message, $context);
    }
}

/**
 * Log critical message
 * 
 * @param string $message Log message
 * @param array $context Additional context
 * @return void
 */
if (!function_exists('logCritical')) {
    function logCritical($message, $context = []) {
        logMessage('CRITICAL', $message, $context);
    }
}

/**
 * Log security event
 * 
 * @param string $event Event name
 * @param array $context Additional context
 * @return void
 */
if (!function_exists('logSecurityEvent')) {
    function logSecurityEvent($event, $context = []) {
        $context['event_type'] = 'security';
        logWarning("SECURITY: {$event}", $context);
        
        // Also write to security-specific log
        $securityLogFile = LOG_DIR . 'security.log';
        $logLine = sprintf(
            "[%s] SECURITY EVENT: %s | Context: %s | IP: %s\n",
            date('Y-m-d H:i:s'),
            $event,
            json_encode($context),
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        );
        @file_put_contents($securityLogFile, $logLine, FILE_APPEND | LOCK_EX);
    }
}

/**
 * Rotate log file if it exceeds size limit
 * 
 * @param string $logFile Log file path
 * @param int $maxSize Maximum size in bytes (default: 10MB)
 * @return void
 */
if (!function_exists('rotateLogFile')) {
    function rotateLogFile($logFile, $maxSize = 10 * 1024 * 1024) {
        if (!file_exists($logFile)) {
            return;
        }
        
        if (filesize($logFile) > $maxSize) {
            $backupFile = $logFile . '.' . date('Y-m-d_His') . '.bak';
            @rename($logFile, $backupFile);
            
            // Keep only last 10 backup files
            $backupFiles = glob($logFile . '.*.bak');
            if (count($backupFiles) > 10) {
                usort($backupFiles, function($a, $b) {
                    return filemtime($a) - filemtime($b);
                });
                
                // Delete oldest backups
                $filesToDelete = array_slice($backupFiles, 0, count($backupFiles) - 10);
                foreach ($filesToDelete as $file) {
                    @unlink($file);
                }
            }
        }
    }
}
?>






