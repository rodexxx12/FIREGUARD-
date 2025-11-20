<?php
/**
 * Secure Database Connection Wrapper
 * Enhances existing database connections with SQL injection protection
 */

require_once 'security.php';

class SecureDatabaseConnection {
    private $conn;
    private $security;
    
    public function __construct() {
        $this->security = BuildingSecurity::getInstance();
    }
    
    /**
     * Get secure database connection
     */
    public function getSecureConnection() {
        if ($this->conn === null) {
            try {
                // Include your existing database connection
                include('../../db/db.php');
                $this->conn = getDatabaseConnection();
                
                // Set secure PDO attributes
                $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $this->conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
                $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                
                // Enable SQL mode for additional security
                $this->conn->exec("SET sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'");
                
            } catch (Exception $e) {
                logSecurityEvent('DATABASE_CONNECTION_ERROR', $e->getMessage());
                throw new DatabaseException('Database connection failed: ' . $e->getMessage());
            }
        }
        
        return $this->conn;
    }
    
    /**
     * Secure query execution with enhanced parameter binding
     */
    public function executeSecureQuery($sql, $params = []) {
        $conn = $this->getSecureConnection();
        
        // Additional SQL injection pattern detection
        $this->validateSQLQuery($sql);
        
        // Validate parameters
        $this->validateParameters($params);
        
        try {
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new DatabaseException('Failed to prepare statement');
            }
            
            // Bind parameters with enhanced type checking
            foreach ($params as $index => $param) {
                $this->bindSecureParameter($stmt, $index + 1, $param);
            }
            
            if (!$stmt->execute()) {
                $errorInfo = $stmt->errorInfo();
                logSecurityEvent('SQL_EXECUTION_ERROR', $errorInfo[2]);
                throw new DatabaseException('SQL execution failed: ' . $errorInfo[2]);
            }
            
            return $stmt;
            
        } catch (Exception $e) {
            logSecurityEvent('SECURE_QUERY_ERROR', $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Enhanced parameter binding with type validation
     */
    private function bindSecureParameter($stmt, $index, $param) {
        if (is_null($param)) {
            $stmt->bindValue($index, null, PDO::PARAM_NULL);
        } elseif (is_bool($param)) {
            $stmt->bindValue($index, $param, PDO::PARAM_BOOL);
        } elseif (is_int($param)) {
            $stmt->bindValue($index, $param, PDO::PARAM_INT);
        } elseif (is_float($param)) {
            $stmt->bindValue($index, $param, PDO::PARAM_STR);
        } elseif (is_string($param)) {
            // Additional string validation
            if (strlen($param) > 65535) {
                throw new SecurityException('String parameter too long');
            }
            $stmt->bindValue($index, $param, PDO::PARAM_STR);
        } else {
            throw new SecurityException('Invalid parameter type');
        }
    }
    
    /**
     * Validate SQL query for dangerous patterns
     */
    private function validateSQLQuery($sql) {
        $dangerousPatterns = [
            '/\b(union|select|insert|update|delete|drop|create|alter|exec|execute|sp_|xp_)\b/i',
            '/\b(script|javascript|vbscript|onload|onerror|onclick)\b/i',
            '/[;\'"]/',
            '/\b(or|and)\s+\d+\s*=\s*\d+/i',
            '/\b(load_file|into\s+outfile|into\s+dumpfile)\b/i',
            '/\b(concat|char|ascii|substring|mid|left|right)\s*\(/i',
            '/\b(hex|unhex|md5|sha1|sha2)\s*\(/i',
            '/\b(sleep|benchmark|waitfor)\s*\(/i'
        ];
        
        foreach ($dangerousPatterns as $pattern) {
            if (preg_match($pattern, $sql)) {
                logSecurityEvent('DANGEROUS_SQL_PATTERN', $sql);
                throw new SecurityException('Potentially dangerous SQL pattern detected');
            }
        }
        
        // Check for comment patterns that might bypass security
        if (preg_match('/--|\/\*|\*\//', $sql)) {
            logSecurityEvent('SQL_COMMENT_PATTERN', $sql);
            throw new SecurityException('SQL comments not allowed');
        }
    }
    
    /**
     * Validate parameters for security
     */
    private function validateParameters($params) {
        foreach ($params as $param) {
            if (is_string($param)) {
                // Check for SQL injection patterns in parameters
                $dangerousPatterns = [
                    '/\b(union|select|insert|update|delete|drop|create|alter)\b/i',
                    '/[;\'"]/',
                    '/\b(or|and)\s+\d+\s*=\s*\d+/i'
                ];
                
                foreach ($dangerousPatterns as $pattern) {
                    if (preg_match($pattern, $param)) {
                        logSecurityEvent('DANGEROUS_PARAMETER', $param);
                        throw new SecurityException('Potentially dangerous parameter detected');
                    }
                }
            }
        }
    }
    
    /**
     * Secure building registration with enhanced validation
     */
    public function registerBuildingSecure($buildingData) {
        $conn = $this->getSecureConnection();
        
        // Validate and sanitize all input data
        $validationResult = validateBuildingData($buildingData);
        
        if (!empty($validationResult['errors'])) {
            throw new SecurityException('Validation errors: ' . implode(', ', $validationResult['errors']));
        }
        
        $data = $validationResult['data'];
        
        // Check if this is an update or insert
        if (!empty($data['building_id'])) {
            // Verify ownership before update
            $checkSql = "SELECT id FROM buildings WHERE id = ? AND user_id = ?";
            $checkStmt = $this->executeSecureQuery($checkSql, [$data['building_id'], $data['user_id']]);
            
            if ($checkStmt->rowCount() === 0) {
                throw new SecurityException('Unauthorized building update attempt');
            }
            
            // Update building
            $updateSql = "UPDATE buildings SET 
                barangay_id = ?, building_name = ?, building_type = ?, address = ?, 
                contact_person = ?, contact_number = ?, total_floors = ?, 
                has_sprinkler_system = ?, has_fire_alarm = ?, has_fire_extinguishers = ?, 
                has_emergency_exits = ?, has_emergency_lighting = ?, has_fire_escape = ?,
                last_inspected = ?, latitude = ?, longitude = ?, construction_year = ?, 
                building_area = ?, geo_fence_id = ?
                WHERE id = ?";
            
            $params = [
                $data['barangay_id'], $data['building_name'], $data['building_type'], 
                $data['address'], $data['contact_person'], $data['contact_number'], 
                $data['total_floors'], $data['has_sprinkler_system'], $data['has_fire_alarm'], 
                $data['has_fire_extinguishers'], $data['has_emergency_exits'], 
                $data['has_emergency_lighting'], $data['has_fire_escape'],
                $data['last_inspected'], $data['latitude'], $data['longitude'], 
                $data['construction_year'], $data['building_area'], $data['geo_fence_id'],
                $data['building_id']
            ];
            
            $this->executeSecureQuery($updateSql, $params);
            
        } else {
            // Insert new building
            $insertSql = "INSERT INTO buildings 
                (user_id, barangay_id, building_name, building_type, address, 
                contact_person, contact_number, total_floors, 
                has_sprinkler_system, has_fire_alarm, has_fire_extinguishers, 
                has_emergency_exits, has_emergency_lighting, has_fire_escape,
                last_inspected, latitude, longitude, construction_year, 
                building_area, geo_fence_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $params = [
                $data['user_id'], $data['barangay_id'], $data['building_name'], 
                $data['building_type'], $data['address'], $data['contact_person'], 
                $data['contact_number'], $data['total_floors'], $data['has_sprinkler_system'], 
                $data['has_fire_alarm'], $data['has_fire_extinguishers'],
                $data['has_emergency_exits'], $data['has_emergency_lighting'], 
                $data['has_fire_escape'], $data['last_inspected'], $data['latitude'], 
                $data['longitude'], $data['construction_year'], $data['building_area'], 
                $data['geo_fence_id']
            ];
            
            $this->executeSecureQuery($insertSql, $params);
        }
        
        return $conn->lastInsertId();
    }
    
    /**
     * Secure building deletion with ownership verification
     */
    public function deleteBuildingSecure($buildingId, $userId) {
        // Verify ownership
        $checkSql = "SELECT id FROM buildings WHERE id = ? AND user_id = ?";
        $checkStmt = $this->executeSecureQuery($checkSql, [$buildingId, $userId]);
        
        if ($checkStmt->rowCount() === 0) {
            throw new SecurityException('Unauthorized building deletion attempt');
        }
        
        // Delete building
        $deleteSql = "DELETE FROM buildings WHERE id = ?";
        $this->executeSecureQuery($deleteSql, [$buildingId]);
        
        return true;
    }
    
    /**
     * Get buildings with secure query
     */
    public function getBuildingsSecure($userId, $filters = []) {
        $sql = "SELECT * FROM buildings WHERE user_id = ?";
        $params = [$userId];
        
        // Add filters if provided
        if (!empty($filters['building_type'])) {
            $sql .= " AND building_type = ?";
            $params[] = $filters['building_type'];
        }
        
        if (!empty($filters['barangay_id'])) {
            $sql .= " AND barangay_id = ?";
            $params[] = $filters['barangay_id'];
        }
        
        $sql .= " ORDER BY created_at DESC";
        
        $stmt = $this->executeSecureQuery($sql, $params);
        return $stmt->fetchAll();
    }
}

// Global functions for backward compatibility
function getSecureDatabaseConnection() {
    static $instance = null;
    if ($instance === null) {
        $instance = new SecureDatabaseConnection();
    }
    return $instance->getSecureConnection();
}

function executeSecureBuildingQuery($sql, $params = []) {
    static $db = null;
    if ($db === null) {
        $db = new SecureDatabaseConnection();
    }
    return $db->executeSecureQuery($sql, $params);
}
?>
