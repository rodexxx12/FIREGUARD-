<?php
require_once __DIR__ . '/db_connect.php';

function handleDatabaseError($e) {
    return [
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ];
}

function validateBuildingId($buildingId) {
    if (!is_numeric($buildingId) || $buildingId <= 0) {
        return false;
    }
    return true;
}

function sanitizeInput($input) {
    return htmlspecialchars(strip_tags(trim($input)));
}

function formatTimestamp($timestamp) {
    return date('Y-m-d H:i:s', strtotime($timestamp));
}

function isEmergencyStatus($status) {
    $emergencyStatuses = ['EMERGENCY', 'ACKNOWLEDGED'];
    return in_array(strtoupper($status), $emergencyStatuses);
}

function isSafeStatus($status) {
    $safeStatuses = ['SAFE', 'MONITORING'];
    return in_array(strtoupper($status), $safeStatuses);
}
?> 