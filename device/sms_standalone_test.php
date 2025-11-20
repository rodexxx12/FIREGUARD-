<?php
// Standalone SMS test interface that works without web server
require_once '../db/db.php';

// Function to get database status
function getDatabaseStatus() {
    try {
        $pdo = getDatabaseConnection();
        
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
        
        return [
            'success' => true,
            'users_count' => $usersCount,
            'phone_numbers_count' => $phoneNumbersCount,
            'verified_phones_count' => $verifiedPhonesCount,
            'phone_numbers' => $phoneNumbers
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// Function to get SMS history
function getSMSHistory() {
    try {
        $pdo = getDatabaseConnection();
        
        // Check if phone_logs table exists
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'phone_logs'");
        if ($tableCheck->rowCount() == 0) {
            return ['success' => false, 'message' => 'phone_logs table does not exist'];
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
        
        return ['success' => true, 'logs' => $logs];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// Function to check API config
function getAPIConfig() {
    try {
        $smsConfig = require 'config.php';
        
        $apiKey = $smsConfig['api_key'] ?? '';
        $deviceId = $smsConfig['device'] ?? '';
        $apiUrl = $smsConfig['url'] ?? '';
        
        $valid = !empty($apiKey) && !empty($deviceId) && !empty($apiUrl);
        
        return [
            'success' => true,
            'api_key' => $apiKey,
            'device_id' => $deviceId,
            'api_url' => $apiUrl,
            'valid' => $valid
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// Function to send SMS
function sendSMS($message, $emergency = false, $autoEmergency = false, $userId = null) {
    try {
        $pdo = getDatabaseConnection();
        
        // Build recipients based on mode
        if ($autoEmergency) {
            // Auto-detect latest EMERGENCY status and send to that user
            $conditions = ["fd.status = 'EMERGENCY'", "(fd.notified = 0 OR fd.notified IS NULL)"];
            $params = [];
            
            if (!empty($userId)) { 
                $conditions[] = 'fd.user_id = :user_id'; 
                $params[':user_id'] = $userId; 
            }
            
            $where = 'WHERE ' . implode(' AND ', $conditions);
            
            // Get the latest EMERGENCY record
            $sql = "
                SELECT fd.id, fd.user_id, fd.device_id, fd.building_id, fd.timestamp, 
                       b.building_name, b.address, u.fullname
                FROM fire_data fd
                LEFT JOIN buildings b ON b.id = fd.building_id
                LEFT JOIN users u ON u.user_id = fd.user_id
                $where
                ORDER BY fd.id DESC
                LIMIT 1
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $emergencyRecord = $stmt->fetch();
            
            if (!$emergencyRecord) {
                return ['success' => false, 'message' => 'No unprocessed EMERGENCY records found'];
            }
            
            // Generate emergency message
            $buildingInfo = $emergencyRecord['building_name'] ? 
                " at {$emergencyRecord['building_name']}" : 
                ($emergencyRecord['address'] ? " at {$emergencyRecord['address']}" : "");
            
            $message = "🚨 FIRE EMERGENCY DETECTED{$buildingInfo}! " .
                      "Please evacuate immediately and call 911. " .
                      "Time: {$emergencyRecord['timestamp']}";
            
            // Get phone numbers for this user
            $phoneSql = "
                SELECT DISTINCT upn.user_id, upn.phone_number
                FROM user_phone_numbers upn
                INNER JOIN users u ON u.user_id = upn.user_id
                WHERE upn.user_id = :user_id
                  AND (u.status = 'Active' OR u.status IS NULL)
                  AND (upn.verified = 1 OR upn.verified IS NULL)
            ";
            $phoneStmt = $pdo->prepare($phoneSql);
            $phoneStmt->execute([':user_id' => $emergencyRecord['user_id']]);
            $recipients = $phoneStmt->fetchAll();
            
        } elseif ($emergency) {
            $conditions = ["fd.status = 'EMERGENCY'"];
            $params = [];
            $where = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';

            $sql = "
                SELECT DISTINCT upn.user_id, upn.phone_number
                FROM fire_data fd
                INNER JOIN users u ON u.user_id = fd.user_id
                INNER JOIN user_phone_numbers upn ON upn.user_id = u.user_id
                $where
                  AND (u.status = 'Active' OR u.status IS NULL)
                  AND (upn.verified = 1 OR upn.verified IS NULL)
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $recipients = $stmt->fetchAll();
        } elseif (!empty($userId)) {
            // Target only the specified user's verified phone numbers
            $sql = "
                SELECT DISTINCT upn.user_id, upn.phone_number
                FROM user_phone_numbers upn
                INNER JOIN users u ON u.user_id = upn.user_id
                WHERE upn.user_id = :user_id
                  AND (u.status = 'Active' OR u.status IS NULL)
                  AND (upn.verified = 1 OR upn.verified IS NULL)
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':user_id' => $userId]);
            $recipients = $stmt->fetchAll();
        } else {
            // Fetch unique, active, verified phone numbers per user (broadcast)
            $sql = "
                SELECT DISTINCT upn.user_id, upn.phone_number
                FROM user_phone_numbers upn
                INNER JOIN users u ON u.user_id = upn.user_id
                WHERE (u.status = 'Active' OR u.status IS NULL)
                  AND (upn.verified = 1 OR upn.verified IS NULL)
            ";
            $stmt = $pdo->query($sql);
            $recipients = $stmt->fetchAll();
        }

        if (!$recipients) {
            return ['success' => false, 'message' => 'No recipients found'];
        }

        // Load SMS config
        $smsConfig = require 'config.php';
        $apiKey = $smsConfig['api_key'] ?? '';
        $deviceId = $smsConfig['device'] ?? '';
        $apiUrl = $smsConfig['url'] ?? '';

        // Prepare HTTP headers for SMS API
        $headers = ['apikey:' . $apiKey];

        $results = [];
        $successCount = 0;
        $failureCount = 0;

        foreach ($recipients as $row) {
            $userId = (int)$row['user_id'];
            $phone = (string)$row['phone_number'];

            if ($phone === '') {
                continue;
            }

            $payload = [
                'message' => $message,
                'mobile_number' => $phone,
                'device' => $deviceId,
            ];

            // Send SMS via cURL
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_URL, $apiUrl);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $responseBody = curl_exec($ch);
            $curlError = curl_error($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $status = ($curlError === '' && $httpCode >= 200 && $httpCode < 300) ? 'sent' : 'failed';
            if ($status === 'sent') {
                $successCount++;
            } else {
                $failureCount++;
            }

            // Log to phone_logs
            try {
                $tableCheck = $pdo->query("SHOW TABLES LIKE 'phone_logs'");
                if ($tableCheck->rowCount() == 0) {
                    $createTable = "
                    CREATE TABLE `phone_logs` (
                        `id` int(11) NOT NULL AUTO_INCREMENT,
                        `user_id` int(11) DEFAULT NULL,
                        `phone_number` varchar(20) DEFAULT NULL,
                        `message` text DEFAULT NULL,
                        `status` varchar(20) DEFAULT NULL,
                        `provider` varchar(50) DEFAULT NULL,
                        `http_code` int(11) DEFAULT NULL,
                        `error` text DEFAULT NULL,
                        `api_response_raw` text DEFAULT NULL,
                        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (`id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
                    $pdo->exec($createTable);
                }
                
                $logSql = "INSERT INTO phone_logs (user_id, phone_number, message, status, provider, http_code, error, api_response_raw) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $logStmt = $pdo->prepare($logSql);
                $logStmt->execute([
                    $userId, $phone, $message, $status, 'pagenet', $httpCode, $curlError, $responseBody
                ]);
            } catch (Exception $e) {
                // Log error but don't fail
            }

            $results[] = [
                'user_id' => $userId,
                'phone_number' => $phone,
                'status' => $status,
                'http_code' => $httpCode,
                'error' => $curlError,
            ];
            
            sleep(1); // Small delay between sends
        }

        return [
            'success' => true,
            'message' => 'SMS processing completed',
            'sent' => $successCount,
            'failed' => $failureCount,
            'total' => $successCount + $failureCount,
            'results' => $results,
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

// Main interface
echo "🚨 Fire Detection System - Standalone SMS Test Interface\n";
echo str_repeat("=", 60) . "\n\n";

while (true) {
    echo "Choose an option:\n";
    echo "1. Check Database Status\n";
    echo "2. Send Test SMS to All Users\n";
    echo "3. Send Test SMS to Specific User\n";
    echo "4. Send Emergency Alert\n";
    echo "5. Send Auto Emergency Alert\n";
    echo "6. View SMS History\n";
    echo "7. Check API Configuration\n";
    echo "8. Exit\n";
    echo "\nEnter your choice (1-8): ";
    
    $choice = trim(fgets(STDIN));
    echo "\n" . str_repeat("-", 40) . "\n";
    
    switch ($choice) {
        case '1':
            echo "📊 Checking Database Status...\n\n";
            $dbStatus = getDatabaseStatus();
            
            if ($dbStatus['success']) {
                echo "✅ Database Connection: SUCCESS\n";
                echo "👥 Users: {$dbStatus['users_count']}\n";
                echo "📱 Phone Numbers: {$dbStatus['phone_numbers_count']}\n";
                echo "✅ Verified Phones: {$dbStatus['verified_phones_count']}\n\n";
                
                if (!empty($dbStatus['phone_numbers'])) {
                    echo "Available Phone Numbers:\n";
                    echo str_repeat("-", 30) . "\n";
                    foreach ($dbStatus['phone_numbers'] as $phone) {
                        $status = $phone['verified'] ? '✅' : '❌';
                        echo "• {$phone['fullname']} (ID: {$phone['user_id']}) - {$phone['phone_number']} $status\n";
                    }
                }
            } else {
                echo "❌ Database Error: {$dbStatus['message']}\n";
            }
            break;
            
        case '2':
            echo "📱 Sending Test SMS to All Users...\n\n";
            $testMessage = "🚨 FIRE DETECTION SYSTEM TEST 🚨\n\n" .
                          "This is a test message from your Fire Detection System.\n" .
                          "Time: " . date('Y-m-d H:i:s') . "\n\n" .
                          "If you received this message, the SMS system is working correctly!";
            
            $result = sendSMS($testMessage);
            
            if ($result['success']) {
                echo "✅ SMS Test Results:\n";
                echo "   Sent: {$result['sent']}\n";
                echo "   Failed: {$result['failed']}\n";
                echo "   Total: {$result['total']}\n\n";
                
                if (!empty($result['results'])) {
                    echo "Individual Results:\n";
                    foreach ($result['results'] as $i => $res) {
                        $status = $res['status'] === 'sent' ? '✅' : '❌';
                        echo "   " . ($i + 1) . ". {$res['phone_number']} - $status (HTTP {$res['http_code']})\n";
                        if ($res['error']) {
                            echo "      Error: {$res['error']}\n";
                        }
                    }
                }
            } else {
                echo "❌ SMS Failed: {$result['message']}\n";
            }
            break;
            
        case '3':
            echo "📱 Sending Test SMS to Specific User...\n";
            echo "Enter User ID (or press Enter for user 1): ";
            $userId = trim(fgets(STDIN));
            if (empty($userId)) $userId = 1;
            
            $testMessage = "🚨 FIRE DETECTION SYSTEM TEST 🚨\n\n" .
                          "This is a targeted test message for User ID: $userId\n" .
                          "Time: " . date('Y-m-d H:i:s') . "\n\n" .
                          "If you received this message, the SMS system is working correctly!";
            
            $result = sendSMS($testMessage, false, false, $userId);
            
            if ($result['success']) {
                echo "✅ SMS Test Results:\n";
                echo "   Sent: {$result['sent']}\n";
                echo "   Failed: {$result['failed']}\n";
                echo "   Total: {$result['total']}\n";
            } else {
                echo "❌ SMS Failed: {$result['message']}\n";
            }
            break;
            
        case '4':
            echo "🚨 Sending Emergency Alert...\n\n";
            $emergencyMessage = "🚨 FIRE EMERGENCY DETECTED!\n\n" .
                               "Please evacuate immediately and call 911.\n" .
                               "This is a test emergency alert.\n\n" .
                               "Time: " . date('Y-m-d H:i:s');
            
            $result = sendSMS($emergencyMessage, true);
            
            if ($result['success']) {
                echo "✅ Emergency Alert Results:\n";
                echo "   Sent: {$result['sent']}\n";
                echo "   Failed: {$result['failed']}\n";
                echo "   Total: {$result['total']}\n";
            } else {
                echo "❌ Emergency Alert Failed: {$result['message']}\n";
            }
            break;
            
        case '5':
            echo "🚨 Sending Auto Emergency Alert...\n\n";
            $result = sendSMS("", false, true);
            
            if ($result['success']) {
                echo "✅ Auto Emergency Alert Results:\n";
                echo "   Sent: {$result['sent']}\n";
                echo "   Failed: {$result['failed']}\n";
                echo "   Total: {$result['total']}\n";
            } else {
                echo "❌ Auto Emergency Alert Failed: {$result['message']}\n";
            }
            break;
            
        case '6':
            echo "📋 Viewing SMS History...\n\n";
            $history = getSMSHistory();
            
            if ($history['success'] && !empty($history['logs'])) {
                echo "Recent SMS Logs (Last 10):\n";
                echo str_repeat("-", 50) . "\n";
                foreach ($history['logs'] as $i => $log) {
                    $status = $log['status'] === 'sent' ? '✅' : '❌';
                    echo ($i + 1) . ". ID: {$log['id']} - {$log['phone_number']} - $status\n";
                    echo "   Time: {$log['created_at']}\n";
                    echo "   HTTP Code: {$log['http_code']}\n";
                    if ($log['error']) {
                        echo "   Error: {$log['error']}\n";
                    }
                    echo "\n";
                }
            } else {
                echo "No SMS logs found or error: " . ($history['message'] ?? 'Unknown error') . "\n";
            }
            break;
            
        case '7':
            echo "⚙️ Checking API Configuration...\n\n";
            $config = getAPIConfig();
            
            if ($config['success']) {
                echo "SMS API Configuration:\n";
                echo str_repeat("-", 25) . "\n";
                echo "API Key: " . substr($config['api_key'], 0, 10) . "...\n";
                echo "Device ID: {$config['device_id']}\n";
                echo "API URL: {$config['api_url']}\n";
                echo "Status: " . ($config['valid'] ? '✅ Valid' : '❌ Invalid') . "\n";
            } else {
                echo "❌ Config Error: {$config['message']}\n";
            }
            break;
            
        case '8':
            echo "👋 Goodbye!\n";
            exit(0);
            
        default:
            echo "❌ Invalid choice. Please enter 1-8.\n";
    }
    
    echo "\n" . str_repeat("=", 60) . "\n\n";
}
?>


