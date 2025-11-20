<?php
/**
 * SQL Injection Security Module for Building Registration
 * This module provides comprehensive security functions without modifying existing code
 */

class BuildingSecurity {
    private static $instance = null;
    private $conn;
    private $rateLimitCache = [];
    private $maxAttempts = 5;
    private $timeWindow = 300; // 5 minutes
    
    private function __construct() {
        // Private constructor for singleton pattern
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Enhanced input sanitization with SQL injection protection
     */
    public function sanitizeInput($input, $type = 'string', $maxLength = 255) {
        if (is_null($input) || $input === '') {
            return null;
        }
        
        // Remove null bytes and control characters
        $input = str_replace(["\0", "\x00"], '', $input);
        $input = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $input);
        
        switch ($type) {
            case 'string':
                $input = trim($input);
                $input = htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $input = substr($input, 0, $maxLength);
                break;
                
            case 'int':
                $input = filter_var($input, FILTER_VALIDATE_INT);
                if ($input === false) {
                    throw new InvalidArgumentException('Invalid integer input');
                }
                break;
                
            case 'float':
                $input = filter_var($input, FILTER_VALIDATE_FLOAT);
                if ($input === false) {
                    throw new InvalidArgumentException('Invalid float input');
                }
                break;
                
            case 'email':
                $input = filter_var(trim($input), FILTER_VALIDATE_EMAIL);
                if ($input === false) {
                    throw new InvalidArgumentException('Invalid email format');
                }
                break;
                
            case 'phone':
                $input = preg_replace('/[^0-9+\-\(\)\s]/', '', $input);
                $input = substr($input, 0, 20);
                break;
                
            case 'date':
                $input = filter_var($input, FILTER_SANITIZE_STRING);
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $input)) {
                    throw new InvalidArgumentException('Invalid date format');
                }
                break;
                
            case 'coordinates':
                $input = filter_var($input, FILTER_VALIDATE_FLOAT);
                if ($input === false || $input < -180 || $input > 180) {
                    throw new InvalidArgumentException('Invalid coordinate value');
                }
                break;
                
            case 'building_type':
                $allowedTypes = ['residential', 'commercial', 'industrial', 'mixed_use', 'educational', 'healthcare', 'government'];
                if (!in_array($input, $allowedTypes)) {
                    throw new InvalidArgumentException('Invalid building type');
                }
                break;
        }
        
        return $input;
    }
    
    /**
     * Validate and sanitize building registration data
     */
    public function validateBuildingData($data) {
        $sanitized = [];
        $errors = [];
        
        try {
            // Required fields validation
            $requiredFields = ['building_name', 'building_type', 'address'];
            foreach ($requiredFields as $field) {
                if (empty($data[$field])) {
                    $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
                }
            }
            
            // Sanitize string inputs
            $stringFields = [
                'building_name' => 255,
                'building_type' => 50,
                'address' => 500,
                'contact_person' => 255
            ];
            
            foreach ($stringFields as $field => $maxLength) {
                if (isset($data[$field]) && !empty($data[$field])) {
                    if ($field === 'building_type') {
                        $sanitized[$field] = $this->sanitizeInput($data[$field], 'building_type');
                    } else {
                        $sanitized[$field] = $this->sanitizeInput($data[$field], 'string', $maxLength);
                    }
                }
            }
            
            // Sanitize numeric inputs
            if (isset($data['total_floors'])) {
                $sanitized['total_floors'] = $this->sanitizeInput($data['total_floors'], 'int');
                if ($sanitized['total_floors'] < 1 || $sanitized['total_floors'] > 200) {
                    $errors[] = 'Invalid number of floors (1-200)';
                }
            }
            
            if (isset($data['construction_year'])) {
                $sanitized['construction_year'] = $this->sanitizeInput($data['construction_year'], 'int');
                if ($sanitized['construction_year'] < 1800 || $sanitized['construction_year'] > date('Y')) {
                    $errors[] = 'Invalid construction year (1800-' . date('Y') . ')';
                }
            }
            
            if (isset($data['building_area'])) {
                $sanitized['building_area'] = $this->sanitizeInput($data['building_area'], 'float');
                if ($sanitized['building_area'] < 0 || $sanitized['building_area'] > 999999.99) {
                    $errors[] = 'Invalid building area';
                }
            }
            
            // Sanitize coordinates
            if (isset($data['latitude'])) {
                $sanitized['latitude'] = $this->sanitizeInput($data['latitude'], 'coordinates');
            }
            
            if (isset($data['longitude'])) {
                $sanitized['longitude'] = $this->sanitizeInput($data['longitude'], 'coordinates');
            }
            
            // Sanitize contact number
            if (isset($data['contact_number']) && !empty($data['contact_number'])) {
                $sanitized['contact_number'] = $this->sanitizeInput($data['contact_number'], 'phone');
            }
            
            // Sanitize date
            if (isset($data['last_inspected']) && !empty($data['last_inspected'])) {
                $sanitized['last_inspected'] = $this->sanitizeInput($data['last_inspected'], 'date');
            }
            
            // Sanitize boolean fields
            $booleanFields = [
                'has_sprinkler_system', 'has_fire_alarm', 'has_fire_extinguishers',
                'has_emergency_exits', 'has_emergency_lighting', 'has_fire_escape'
            ];
            
            foreach ($booleanFields as $field) {
                $sanitized[$field] = isset($data[$field]) && $data[$field] ? 1 : 0;
            }
            
            // Sanitize ID fields
            if (isset($data['barangay_id'])) {
                $sanitized['barangay_id'] = $this->sanitizeInput($data['barangay_id'], 'int');
            }
            
            if (isset($data['geo_fence_id'])) {
                $sanitized['geo_fence_id'] = $this->sanitizeInput($data['geo_fence_id'], 'int');
            }
            
            if (isset($data['building_id'])) {
                $sanitized['building_id'] = $this->sanitizeInput($data['building_id'], 'int');
            }
            
        } catch (InvalidArgumentException $e) {
            $errors[] = $e->getMessage();
        }
        
        return [
            'data' => $sanitized,
            'errors' => $errors
        ];
    }
    
    /**
     * Enhanced prepared statement execution with additional security checks
     */
    public function executeSecureQuery($conn, $sql, $params = []) {
        // Validate SQL query for dangerous patterns
        $dangerousPatterns = [
            '/\b(union|select|insert|update|delete|drop|create|alter|exec|execute)\b/i',
            '/\b(script|javascript|vbscript|onload|onerror)\b/i',
            '/[;\'"]/',
            '/\b(or|and)\s+\d+\s*=\s*\d+/i'
        ];
        
        foreach ($dangerousPatterns as $pattern) {
            if (preg_match($pattern, $sql)) {
                $this->logSecurityEvent('DANGEROUS_SQL_PATTERN', $sql);
                throw new SecurityException('Potentially dangerous SQL pattern detected');
            }
        }
        
        // Prepare statement
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new DatabaseException('Failed to prepare statement');
        }
        
        // Bind parameters with type checking
        foreach ($params as $index => $param) {
            $paramType = PDO::PARAM_STR;
            if (is_int($param)) {
                $paramType = PDO::PARAM_INT;
            } elseif (is_bool($param)) {
                $paramType = PDO::PARAM_BOOL;
            } elseif (is_null($param)) {
                $paramType = PDO::PARAM_NULL;
            }
            
            $stmt->bindValue($index + 1, $param, $paramType);
        }
        
        // Execute with error handling
        if (!$stmt->execute()) {
            $errorInfo = $stmt->errorInfo();
            $this->logSecurityEvent('SQL_EXECUTION_ERROR', $errorInfo[2]);
            throw new DatabaseException('SQL execution failed: ' . $errorInfo[2]);
        }
        
        return $stmt;
    }
    
    /**
     * Rate limiting to prevent automated attacks
     */
    public function checkRateLimit($identifier, $action = 'building_registration') {
        $key = $identifier . '_' . $action;
        $currentTime = time();
        
        // Clean old entries
        foreach ($this->rateLimitCache as $cacheKey => $data) {
            if ($currentTime - $data['first_attempt'] > $this->timeWindow) {
                unset($this->rateLimitCache[$cacheKey]);
            }
        }
        
        // Check current attempts
        if (isset($this->rateLimitCache[$key])) {
            $data = $this->rateLimitCache[$key];
            if ($data['attempts'] >= $this->maxAttempts) {
                $timeLeft = $this->timeWindow - ($currentTime - $data['first_attempt']);
                $this->logSecurityEvent('RATE_LIMIT_EXCEEDED', $identifier);
                throw new SecurityException("Rate limit exceeded. Try again in {$timeLeft} seconds.");
            }
            $this->rateLimitCache[$key]['attempts']++;
        } else {
            $this->rateLimitCache[$key] = [
                'attempts' => 1,
                'first_attempt' => $currentTime
            ];
        }
        
        return true;
    }
    
    /**
     * CSRF Token generation and validation
     */
    public function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    public function validateCSRFToken($token) {
        if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
            $this->logSecurityEvent('CSRF_TOKEN_INVALID', $_SERVER['REMOTE_ADDR']);
            throw new SecurityException('Invalid CSRF token');
        }
        return true;
    }
    
    /**
     * Security event logging
     */
    public function logSecurityEvent($event, $details = '') {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event' => $event,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'user_id' => $_SESSION['user_id'] ?? 'anonymous',
            'details' => $details
        ];
        
        $logFile = __DIR__ . '/security.log';
        file_put_contents($logFile, json_encode($logEntry) . "\n", FILE_APPEND | LOCK_EX);
        
        // Also log to PHP error log for immediate attention
        error_log("SECURITY EVENT: {$event} - {$details}");
    }
    
    /**
     * Validate file uploads for building documents
     */
    public function validateFileUpload($file, $allowedTypes = ['jpg', 'jpeg', 'png', 'pdf'], $maxSize = 5242880) {
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new SecurityException('Invalid file upload');
        }
        
        // Check file size
        if ($file['size'] > $maxSize) {
            throw new SecurityException('File too large');
        }
        
        // Check file extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedTypes)) {
            throw new SecurityException('Invalid file type');
        }
        
        // Check MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        $allowedMimes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'pdf' => 'application/pdf'
        ];
        
        if (!isset($allowedMimes[$extension]) || $mimeType !== $allowedMimes[$extension]) {
            throw new SecurityException('File type mismatch');
        }
        
        return true;
    }
    
    /**
     * Sanitize file name for safe storage
     */
    public function sanitizeFileName($filename) {
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        $filename = preg_replace('/_{2,}/', '_', $filename);
        return substr($filename, 0, 100);
    }
}

// Custom exception classes
class SecurityException extends Exception {}
class DatabaseException extends Exception {}

// Global security functions for easy access
function sanitizeBuildingInput($input, $type = 'string', $maxLength = 255) {
    return BuildingSecurity::getInstance()->sanitizeInput($input, $type, $maxLength);
}

function validateBuildingData($data) {
    return BuildingSecurity::getInstance()->validateBuildingData($data);
}

function executeSecureQuery($conn, $sql, $params = []) {
    return BuildingSecurity::getInstance()->executeSecureQuery($conn, $sql, $params);
}

function checkRateLimit($identifier, $action = 'building_registration') {
    return BuildingSecurity::getInstance()->checkRateLimit($identifier, $action);
}

function generateCSRFToken() {
    return BuildingSecurity::getInstance()->generateCSRFToken();
}

function validateCSRFToken($token) {
    return BuildingSecurity::getInstance()->validateCSRFToken($token);
}

function logSecurityEvent($event, $details = '') {
    return BuildingSecurity::getInstance()->logSecurityEvent($event, $details);
}

function validateFileUpload($file, $allowedTypes = ['jpg', 'jpeg', 'png', 'pdf'], $maxSize = 5242880) {
    return BuildingSecurity::getInstance()->validateFileUpload($file, $allowedTypes, $maxSize);
}

function sanitizeFileName($filename) {
    return BuildingSecurity::getInstance()->sanitizeFileName($filename);
}
?>
