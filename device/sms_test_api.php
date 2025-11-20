<?php
// API backend for SMS test interface
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../db/db.php';

$action = $_GET['action'] ?? '';

try {
    $pdo = getDatabaseConnection();
    
    switch ($action) {
        case 'check_database':
            checkDatabase($pdo);
            break;
            
        case 'view_history':
            viewSMSHistory($pdo);
            break;
            
        case 'check_config':
            checkAPIConfig();
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

function checkDatabase($pdo) {
    try {
        // Check users count
        $usersStmt = $pdo->query("SELECT COUNT(*) as count FROM users");
        $usersCount = $usersStmt->fetch()['count'];
        
        // Check phone numbers count
        $phoneStmt = $pdo->query("SELECT COUNT(*) as count FROM user_phone_numbers");
        $phoneNumbersCount = $phoneStmt->fetch()['count'];
        
        // Check verified phones count
        $verifiedStmt = $pdo->query("SELECT COUNT(*) as count FROM user_phone_numbers WHERE verified = 1");
        $verifiedPhonesCount = $verifiedStmt->fetch()['count'];
        
        // Get phone numbers with user details
        $phoneDetailsStmt = $pdo->query("
            SELECT upn.user_id, upn.phone_number, upn.verified, u.fullname
            FROM user_phone_numbers upn
            LEFT JOIN users u ON u.user_id = upn.user_id
            ORDER BY upn.user_id
        ");
        $phoneNumbers = $phoneDetailsStmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'users_count' => $usersCount,
            'phone_numbers_count' => $phoneNumbersCount,
            'verified_phones_count' => $verifiedPhonesCount,
            'phone_numbers' => $phoneNumbers
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function viewSMSHistory($pdo) {
    try {
        // Check if phone_logs table exists
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'phone_logs'");
        if ($tableCheck->rowCount() == 0) {
            echo json_encode(['success' => false, 'message' => 'phone_logs table does not exist']);
            return;
        }
        
        // Get recent SMS logs
        $logsStmt = $pdo->query("
            SELECT id, user_id, phone_number, message, status, provider, 
                   http_code, error, created_at
            FROM phone_logs 
            ORDER BY created_at DESC 
            LIMIT 10
        ");
        $logs = $logsStmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'logs' => $logs
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function checkAPIConfig() {
    try {
        $smsConfig = require 'config.php';
        
        $apiKey = $smsConfig['api_key'] ?? '';
        $deviceId = $smsConfig['device'] ?? '';
        $apiUrl = $smsConfig['url'] ?? '';
        
        $valid = !empty($apiKey) && !empty($deviceId) && !empty($apiUrl);
        
        echo json_encode([
            'success' => true,
            'api_key' => $apiKey,
            'device_id' => $deviceId,
            'api_url' => $apiUrl,
            'valid' => $valid
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>


