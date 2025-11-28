<?php
/**
 * Input Validation and Sanitization Helper
 */
class InputValidator {
    /**
     * Validate and sanitize integer
     * 
     * @param mixed $value Input value
     * @param int $min Minimum value
     * @param int $max Maximum value
     * @return int|false Validated integer or false
     */
    public static function validateInt($value, $min = null, $max = null) {
        $options = ['options' => []];
        if ($min !== null) $options['options']['min_range'] = $min;
        if ($max !== null) $options['options']['max_range'] = $max;
        
        return filter_var($value, FILTER_VALIDATE_INT, $options);
    }
    
    /**
     * Validate and sanitize string
     * 
     * @param mixed $value Input value
     * @param int $maxLength Maximum length
     * @param bool $allowEmpty Allow empty strings
     * @return string|false Validated string or false
     */
    public static function validateString($value, $maxLength = null, $allowEmpty = true) {
        if (!is_string($value) && !is_numeric($value)) {
            return false;
        }
        
        $value = trim((string)$value);
        
        if (!$allowEmpty && empty($value)) {
            return false;
        }
        
        if ($maxLength !== null && strlen($value) > $maxLength) {
            return false;
        }
        
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Validate date
     * 
     * @param mixed $value Input value
     * @param string $format Date format (default: Y-m-d)
     * @return string|false Validated date or false
     */
    public static function validateDate($value, $format = 'Y-m-d') {
        if (empty($value)) {
            return false;
        }
        
        $date = DateTime::createFromFormat($format, $value);
        if ($date && $date->format($format) === $value) {
            return $value;
        }
        
        return false;
    }
    
    /**
     * Validate email
     * 
     * @param mixed $value Input value
     * @return string|false Validated email or false
     */
    public static function validateEmail($value) {
        return filter_var($value, FILTER_VALIDATE_EMAIL);
    }
    
    /**
     * Validate against whitelist
     * 
     * @param mixed $value Input value
     * @param array $allowedValues Allowed values
     * @return mixed Validated value or false
     */
    public static function validateWhitelist($value, array $allowedValues) {
        return in_array($value, $allowedValues, true) ? $value : false;
    }
}








