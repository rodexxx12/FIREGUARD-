<?php
/**
 * DateTime Helper Functions for Philippines Timezone
 * 
 * This file contains helper functions to ensure consistent datetime handling
 * across the Fire Detection System using Philippines timezone (Asia/Manila)
 */

// Set Philippines timezone as default
date_default_timezone_set('Asia/Manila');

/**
 * Get current datetime in Philippines timezone
 * 
 * @param string $format The datetime format (default: 'Y-m-d H:i:s')
 * @return string Current datetime in Philippines timezone
 */
function getCurrentPhilippinesDateTime($format = 'Y-m-d H:i:s') {
    return date($format);
}

/**
 * Get current date in Philippines timezone
 * 
 * @param string $format The date format (default: 'Y-m-d')
 * @return string Current date in Philippines timezone
 */
function getCurrentPhilippinesDate($format = 'Y-m-d') {
    return date($format);
}

/**
 * Get current time in Philippines timezone
 * 
 * @param string $format The time format (default: 'H:i:s')
 * @return string Current time in Philippines timezone
 */
function getCurrentPhilippinesTime($format = 'H:i:s') {
    return date($format);
}

/**
 * Convert any datetime to Philippines timezone
 * 
 * @param string $datetime The datetime to convert
 * @param string $format The output format (default: 'Y-m-d H:i:s')
 * @return string Datetime in Philippines timezone
 */
function convertToPhilippinesDateTime($datetime, $format = 'Y-m-d H:i:s') {
    if (empty($datetime)) {
        return null;
    }
    
    $dt = new DateTime($datetime);
    $dt->setTimezone(new DateTimeZone('Asia/Manila'));
    return $dt->format($format);
}

/**
 * Format datetime for database storage (Philippines timezone)
 * 
 * @param string $datetime Optional datetime string, uses current time if not provided
 * @return string Formatted datetime for database storage
 */
function formatDateTimeForDatabase($datetime = null) {
    if ($datetime === null) {
        return getCurrentPhilippinesDateTime();
    }
    
    return convertToPhilippinesDateTime($datetime);
}

/**
 * Format datetime for display (Philippines timezone)
 * 
 * @param string $datetime The datetime to format
 * @param string $format The display format (default: 'M d, Y g:i A')
 * @return string Formatted datetime for display
 */
function formatDateTimeForDisplay($datetime, $format = 'M d, Y g:i A') {
    if (empty($datetime)) {
        return 'N/A';
    }
    
    return convertToPhilippinesDateTime($datetime, $format);
}

/**
 * Get Philippines timezone offset
 * 
 * @return string Timezone offset (e.g., '+08:00')
 */
function getPhilippinesTimezoneOffset() {
    $dt = new DateTime('now', new DateTimeZone('Asia/Manila'));
    return $dt->format('P');
}

/**
 * Validate if datetime is in correct format for database
 * 
 * @param string $datetime The datetime to validate
 * @return bool True if valid, false otherwise
 */
function isValidDatabaseDateTime($datetime) {
    if (empty($datetime)) {
        return false;
    }
    
    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $datetime);
    return $dt && $dt->format('Y-m-d H:i:s') === $datetime;
}

/**
 * Get datetime for form input (datetime-local format)
 * 
 * @param string $datetime Optional datetime string, uses current time if not provided
 * @return string Formatted datetime for HTML datetime-local input
 */
function getDateTimeForFormInput($datetime = null) {
    if ($datetime === null) {
        return getCurrentPhilippinesDateTime('Y-m-d\TH:i');
    }
    
    return convertToPhilippinesDateTime($datetime, 'Y-m-d\TH:i');
}

/**
 * Get date for form input (date format)
 * 
 * @param string $date Optional date string, uses current date if not provided
 * @return string Formatted date for HTML date input
 */
function getDateForFormInput($date = null) {
    if ($date === null) {
        return getCurrentPhilippinesDate();
    }
    
    return convertToPhilippinesDateTime($date, 'Y-m-d');
}

/**
 * Get time for form input (time format)
 * 
 * @param string $time Optional time string, uses current time if not provided
 * @return string Formatted time for HTML time input
 */
function getTimeForFormInput($time = null) {
    if ($time === null) {
        return getCurrentPhilippinesTime('H:i');
    }
    
    return convertToPhilippinesDateTime($time, 'H:i');
}
?>
