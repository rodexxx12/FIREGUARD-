<?php
/**
 * Input Validation Module
 * 
 * Provides comprehensive input validation functions
 * 
 * Usage:
 * require_once __DIR__ . '/../../core/config/config.php';
 * require_once __DIR__ . '/../../core/validation/validator.php';
 * 
 * if (!validateEmail($_POST['email'])) {
 *     $errors[] = 'Invalid email address';
 * }
 */

// Load constants
if (!defined('PASSWORD_MIN_LENGTH')) {
    require_once __DIR__ . '/../config/constants.php';
}

/**
 * Validate email address
 * 
 * @param mixed $email Email to validate
 * @return bool True if valid
 */
if (!function_exists('validateEmail')) {
    function validateEmail($email) {
        if (empty($email) || !is_string($email)) {
            return false;
        }
        
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}

/**
 * Validate phone number (Philippine format)
 * 
 * @param mixed $phone Phone number to validate
 * @return bool True if valid
 */
if (!function_exists('validatePhone')) {
    function validatePhone($phone) {
        if (empty($phone) || !is_string($phone)) {
            return false;
        }
        
        // Remove spaces and common formatting
        $phone = preg_replace('/[\s\-\(\)]/', '', $phone);
        
        // Check for Philippine phone number format
        // +63XXXXXXXXXX or 09XXXXXXXXX or 9XXXXXXXXX
        return preg_match('/^(\+63|0)?9\d{9}$/', $phone) === 1;
    }
}

/**
 * Validate password strength
 * 
 * @param string $password Password to validate
 * @return array ['valid' => bool, 'error' => string|null, 'strength' => string]
 */
if (!function_exists('validatePassword')) {
    function validatePassword($password) {
        if (empty($password) || !is_string($password)) {
            return [
                'valid' => false,
                'error' => 'Password is required',
                'strength' => 'weak'
            ];
        }
        
        $length = strlen($password);
        
        // Check minimum length
        if ($length < PASSWORD_MIN_LENGTH) {
            return [
                'valid' => false,
                'error' => "Password must be at least " . PASSWORD_MIN_LENGTH . " characters",
                'strength' => 'weak'
            ];
        }
        
        // Check for uppercase letter
        if (PASSWORD_REQUIRE_UPPERCASE && !preg_match('/[A-Z]/', $password)) {
            return [
                'valid' => false,
                'error' => 'Password must contain at least one uppercase letter',
                'strength' => 'weak'
            ];
        }
        
        // Check for lowercase letter
        if (PASSWORD_REQUIRE_LOWERCASE && !preg_match('/[a-z]/', $password)) {
            return [
                'valid' => false,
                'error' => 'Password must contain at least one lowercase letter',
                'strength' => 'weak'
            ];
        }
        
        // Check for number
        if (PASSWORD_REQUIRE_NUMBER && !preg_match('/[0-9]/', $password)) {
            return [
                'valid' => false,
                'error' => 'Password must contain at least one number',
                'strength' => 'weak'
            ];
        }
        
        // Check for special character
        if (PASSWORD_REQUIRE_SPECIAL && !preg_match('/[^A-Za-z0-9]/', $password)) {
            return [
                'valid' => false,
                'error' => 'Password must contain at least one special character',
                'strength' => 'weak'
            ];
        }
        
        // Calculate strength
        $strength = 'medium';
        if ($length >= 12 && preg_match('/[A-Z]/', $password) && 
            preg_match('/[a-z]/', $password) && preg_match('/[0-9]/', $password) && 
            preg_match('/[^A-Za-z0-9]/', $password)) {
            $strength = 'strong';
        }
        
        return [
            'valid' => true,
            'error' => null,
            'strength' => $strength
        ];
    }
}

/**
 * Validate date
 * 
 * @param string $date Date string
 * @param string $format Date format (default: 'Y-m-d')
 * @return bool True if valid
 */
if (!function_exists('validateDate')) {
    function validateDate($date, $format = 'Y-m-d') {
        if (empty($date) || !is_string($date)) {
            return false;
        }
        
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }
}

/**
 * Validate URL
 * 
 * @param mixed $url URL to validate
 * @return bool True if valid
 */
if (!function_exists('validateUrl')) {
    function validateUrl($url) {
        if (empty($url) || !is_string($url)) {
            return false;
        }
        
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
}

/**
 * Validate integer within range
 * 
 * @param mixed $value Value to validate
 * @param int|null $min Minimum value (null = no minimum)
 * @param int|null $max Maximum value (null = no maximum)
 * @return bool True if valid
 */
if (!function_exists('validateInteger')) {
    function validateInteger($value, $min = null, $max = null) {
        if (!is_numeric($value)) {
            return false;
        }
        
        $int = (int)$value;
        
        if ($min !== null && $int < $min) {
            return false;
        }
        
        if ($max !== null && $int > $max) {
            return false;
        }
        
        return true;
    }
}

/**
 * Validate string length
 * 
 * @param mixed $value Value to validate
 * @param int|null $minLength Minimum length (null = no minimum)
 * @param int|null $maxLength Maximum length (null = no maximum)
 * @return bool True if valid
 */
if (!function_exists('validateString')) {
    function validateString($value, $minLength = null, $maxLength = null) {
        if (!is_string($value)) {
            return false;
        }
        
        $length = strlen($value);
        
        if ($minLength !== null && $length < $minLength) {
            return false;
        }
        
        if ($maxLength !== null && $length > $maxLength) {
            return false;
        }
        
        return true;
    }
}

/**
 * Validate file upload
 * 
 * @param array $file $_FILES array element
 * @param array $allowedTypes Allowed MIME types
 * @param int $maxSize Maximum file size in bytes
 * @return array ['valid' => bool, 'error' => string|null]
 */
if (!function_exists('validateFileUpload')) {
    function validateFileUpload($file, $allowedTypes = [], $maxSize = null) {
        if ($maxSize === null) {
            $maxSize = MAX_UPLOAD_SIZE;
        }
        
        // Check if file was uploaded
        if (!isset($file) || !isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['valid' => false, 'error' => 'No file uploaded or invalid upload'];
        }
        
        // Check for upload errors
        if (isset($file['error']) && $file['error'] !== UPLOAD_ERR_OK) {
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
                UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload'
            ];
            return ['valid' => false, 'error' => $errorMessages[$file['error']] ?? 'Unknown upload error'];
        }
        
        // Check file size
        if ($file['size'] > $maxSize) {
            return ['valid' => false, 'error' => 'File size exceeds maximum limit of ' . round($maxSize / 1024 / 1024, 2) . 'MB'];
        }
        
        // Validate MIME type
        if (!empty($allowedTypes)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            if (!in_array($mimeType, $allowedTypes)) {
                return ['valid' => false, 'error' => 'Invalid file type. Allowed types: ' . implode(', ', $allowedTypes)];
            }
        }
        
        // Additional security: Check file extension matches MIME type
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExtensions = array_map(function($mime) {
            $map = [
                'image/jpeg' => 'jpg',
                'image/jpg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'application/pdf' => 'pdf'
            ];
            return $map[$mime] ?? '';
        }, $allowedTypes);
        
        if (!empty($allowedExtensions) && !in_array($extension, $allowedExtensions)) {
            return ['valid' => false, 'error' => 'File extension does not match file type'];
        }
        
        return ['valid' => true, 'error' => null];
    }
}
?>



