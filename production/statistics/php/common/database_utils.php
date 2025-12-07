<?php
/**
 * Common Database Utilities for Statistics Module
 * Reduces code duplication across all statistics files
 */

// Environment-aware error handling for JSON responses
$isProduction = (getenv('APP_ENV') === 'production');
$debugMode = filter_var(getenv('APP_DEBUG') ?? '0', FILTER_VALIDATE_BOOLEAN);
error_reporting(E_ALL);
ini_set('display_errors', ($isProduction && !$debugMode) ? '0' : '1');
ini_set('log_errors', '1');

require_once '../../../db/db.php';
require_once dirname(__DIR__, 3) . '/components/cache.php';

class DatabaseUtils {
    
    /**
     * Get database connection
     */
    public static function getConnection() {
        return getDatabaseConnection();
    }

    /**
     * Cache arbitrary payloads using the shared cache helper.
     */
    public static function remember(string $namespace, array $payload, callable $callback, int $ttl = 60) {
        if (!class_exists('CacheHelper')) {
            return $callback();
        }

        $key = CacheHelper::buildKey($namespace, $payload);
        return CacheHelper::remember($key, $ttl, $callback);
    }

    /**
     * Cache the result of a query plus bindings to avoid N+1 hits.
     */
    public static function cachedQuery(string $namespace, string $sql, array $params = [], int $ttl = 60) {
        return self::remember($namespace, ['sql' => $sql, 'params' => $params], function () use ($sql, $params) {
            return self::executeQuery($sql, $params);
        }, $ttl);
    }
    
    /**
     * Build barangay filter conditions for queries
     */
    public static function buildBarangayFilter($barangay, &$sql, &$params) {
        if (!empty($barangay)) {
            $sql .= " AND (fd.barangay_id = :barangay1 OR bld.barangay_id = :barangay2)";
            $params[':barangay1'] = $barangay;
            $params[':barangay2'] = $barangay;
        }
    }
    
    /**
     * Build date filter conditions for queries
     */
    public static function buildDateFilters($startDate, $endDate, &$sql, &$params) {
        if (!empty($startDate)) {
            $sql .= " AND DATE(fd.timestamp) >= :start_date";
            $params[':start_date'] = $startDate;
        }
        
        if (!empty($endDate)) {
            $sql .= " AND DATE(fd.timestamp) <= :end_date";
            $params[':end_date'] = $endDate;
        }
    }
    
    /**
     * Build month/year filter conditions for queries
     */
    public static function buildMonthYearFilters($month, $year, &$sql, &$params) {
        if (!empty($month)) {
            $sql .= " AND MONTH(fd.timestamp) = :month";
            $params[':month'] = $month;
        }
        
        if (!empty($year)) {
            $sql .= " AND YEAR(fd.timestamp) = :year";
            $params[':year'] = $year;
        }
    }
    
    /**
     * Get common JOIN clauses for fire data queries
     */
    public static function getFireDataJoins() {
        return "LEFT JOIN devices d ON fd.device_id = d.device_id
                LEFT JOIN buildings bld ON (fd.building_id = bld.id OR d.building_id = bld.id)
                LEFT JOIN barangay b ON (fd.barangay_id = b.id OR bld.barangay_id = b.id)";
    }
    
    /**
     * Get common JOIN clauses for barangay queries
     */
    public static function getBarangayJoins() {
        return "LEFT JOIN fire_data fd ON (
                    fd.barangay_id = b.id OR 
                    EXISTS (
                        SELECT 1 FROM devices d 
                        LEFT JOIN buildings bld ON d.building_id = bld.id 
                        WHERE d.device_id = fd.device_id AND bld.barangay_id = b.id
                    )
                )";
    }
    
    /**
     * Standardize JSON response format
     */
    public static function sendResponse($success, $data = null, $message = '', $debug = null) {
        // Clear any previous output buffers safely
        $obLevel = ob_get_level();
        for ($i = 0; $i < $obLevel; $i++) {
            @ob_end_clean();
        }
        
        $response = [
            'success' => $success,
            'message' => $message
        ];
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        if ($debug !== null) {
            $response['debug'] = $debug;
        }
        
        // Ensure headers haven't been sent
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    /**
     * Standardize error response
     */
    public static function sendError($message, $error = null) {
        if ($error) {
            error_log("API Error: {$message} - " . self::stringifyError($error));
        }
        $debug = null;
        if (self::isDebugEnabled() && $error) {
            $debug = [
                'error' => self::stringifyError($error),
                'trace' => $error instanceof Throwable ? $error->getTraceAsString() : null
            ];
        }
        self::sendResponse(false, null, $message, $debug);
    }
    
    /**
     * Execute query with error handling
     */
    public static function executeQuery($sql, $params = []) {
        try {
            $conn = self::getConnection();
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Database query error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Execute single row query with error handling
     */
    public static function executeSingleQuery($sql, $params = []) {
        try {
            $conn = self::getConnection();
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Database query error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get current month/year filters for monthly queries
     */
    public static function getCurrentMonthYearFilter() {
        return "AND MONTH(fd.timestamp) = MONTH(CURRENT_DATE())
                AND YEAR(fd.timestamp) = YEAR(CURRENT_DATE())";
    }
    
    /**
     * Format chart data with consistent structure
     */
    public static function formatChartData($labels, $data, $additionalData = []) {
        $chartData = [
            'labels' => $labels,
            'data' => $data
        ];
        
        return array_merge($chartData, $additionalData);
    }
    
    /**
     * Ensure no negative values in data arrays
     */
    public static function sanitizeData($data) {
        if (is_array($data)) {
            return array_map(function($value) {
                return max(0, $value ?? 0);
            }, $data);
        }
        return max(0, $data ?? 0);
    }

    /**
     * Normalize Y-m-d date input
     */
    public static function sanitizeDate($value) {
        if ($value === null || $value === '') {
            return null;
        }
        $date = DateTime::createFromFormat('Y-m-d', $value);
        return $date ? $date->format('Y-m-d') : null;
    }

    /**
     * Sanitize integer parameter with optional range
     */
    public static function sanitizeInt($value, $min = null, $max = null) {
        if ($value === null || $value === '') {
            return null;
        }
        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            return null;
        }
        $intVal = (int)$value;
        if ($min !== null && $intVal < $min) {
            return null;
        }
        if ($max !== null && $intVal > $max) {
            return null;
        }
        return $intVal;
    }

    /**
     * Ensure value belongs to allowed enum
     */
    public static function sanitizeEnum($value, array $allowed) {
        if ($value === null || $value === '') {
            return null;
        }
        $upperValue = strtoupper($value);
        $allowedUpper = array_map('strtoupper', $allowed);
        return in_array($upperValue, $allowedUpper, true) ? $upperValue : null;
    }

    /**
     * Determine if debug logging enabled
     */
    public static function isDebugEnabled() {
        return filter_var(getenv('APP_DEBUG') ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    private static function stringifyError($error) {
        if (is_string($error)) {
            return $error;
        }
        if (is_array($error)) {
            return json_encode($error);
        }
        if ($error instanceof Throwable) {
            return $error->getMessage();
        }
        return print_r($error, true);
    }
}
?>
