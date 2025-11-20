<?php
/**
 * Common Database Utilities for Statistics Module
 * Reduces code duplication across all statistics files
 */

require_once '../../../db/db.php';

class DatabaseUtils {
    
    /**
     * Get database connection
     */
    public static function getConnection() {
        return getDatabaseConnection();
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
        
        header('Content-Type: application/json');
        echo json_encode($response);
    }
    
    /**
     * Standardize error response
     */
    public static function sendError($message, $error = null) {
        self::sendResponse(false, null, $message, $error ? ['error' => $error] : null);
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
}
?>
