<?php
session_start();
require '../../../vendor/autoload.php';

// Set timezone to ensure consistent time handling
date_default_timezone_set('Asia/Manila');

// Load SMS configuration
$config = require '../config/config.php';
use WebSocket\Client;

class UserPhoneModel {
    private $db;
    private $apiKey;
    private $device;
    private $smsUrl;

    public function __construct(PDO $db, $apiKey, $device, $smsUrl) {
        $this->db = $db;
        $this->apiKey = $apiKey;
        $this->device = $device;
        $this->smsUrl = $smsUrl;
    }

    public function getPhoneNumbers($userId) {
        $stmt = $this->db->prepare("SELECT * FROM user_phone_numbers WHERE user_id = :user_id ORDER BY is_primary DESC, created_at DESC");
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPrimaryPhone($userId) {
        $stmt = $this->db->prepare("SELECT * FROM user_phone_numbers WHERE user_id = :user_id AND is_primary = 1");
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function phoneNumberExists($phoneNumber) {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM user_phone_numbers WHERE phone_number = :phone_number");
        $stmt->bindParam(':phone_number', $phoneNumber, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetchColumn() > 0;
    }

    public function verifyPhoneOwnership($userId, $phoneId) {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM user_phone_numbers WHERE phone_id = :phone_id AND user_id = :user_id");
        $stmt->bindParam(':phone_id', $phoneId, PDO::PARAM_INT);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchColumn() > 0;
    }

    public function addPhoneNumber($userId, $phoneNumber, $isPrimary = false, $label = null) {
        $this->db->beginTransaction();
        
        try {
            // If setting as primary, remove primary status from other numbers
            if ($isPrimary) {
                $this->clearPrimaryStatus($userId);
            }
            
            // Generate verification code
            $verificationCode = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
            $expiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));
            
            $stmt = $this->db->prepare("INSERT INTO user_phone_numbers (user_id, phone_number, label, is_primary, verification_code, verification_expiry) 
                                       VALUES (:user_id, :phone_number, :label, :is_primary, :verification_code, :verification_expiry)");
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':phone_number', $phoneNumber, PDO::PARAM_STR);
            $stmt->bindParam(':label', $label, PDO::PARAM_STR);
            $stmt->bindParam(':is_primary', $isPrimary, PDO::PARAM_BOOL);
            $stmt->bindParam(':verification_code', $verificationCode, PDO::PARAM_STR);
            $stmt->bindParam(':verification_expiry', $expiry, PDO::PARAM_STR);
            $result = $stmt->execute();
            
            $this->db->commit();
            
            // Send verification code via SMS
            list($smsSent, $smsError) = $this->sendVerificationSMS($phoneNumber, $verificationCode);
            
            return [
                'success' => $result,
                'phone_id' => $this->db->lastInsertId(),
                'verification_code' => $verificationCode, // Only for internal use
                'sms_sent' => $smsSent,
                'sms_error' => $smsError
            ];
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error adding phone number: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function setPrimaryPhone($userId, $phoneId) {
        $this->db->beginTransaction();
        
        try {
            // First clear all primary statuses
            $this->clearPrimaryStatus($userId);
            
            // Then set the new primary
            $stmt = $this->db->prepare("UPDATE user_phone_numbers SET is_primary = 1 WHERE phone_id = :phone_id AND user_id = :user_id");
            $stmt->bindParam(':phone_id', $phoneId, PDO::PARAM_INT);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $result = $stmt->execute();
            
            $this->db->commit();
            return $result;
        } catch (Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }

    public function deletePhoneNumber($userId, $phoneId) {
        $this->db->beginTransaction();
        
        try {
            // First verify ownership
            if (!$this->verifyPhoneOwnership($userId, $phoneId)) {
                $this->db->rollBack();
                return ['success' => false, 'error' => 'Phone number not found or does not belong to you.'];
            }
            
            // Check if this is the last phone number
            $count = $this->countUserPhones($userId);
            if ($count <= 1) {
                $this->db->rollBack();
                return ['success' => false, 'error' => 'Cannot delete the last phone number. You must have at least one phone number.'];
            }
            
            // Check if this is the primary phone number
            $stmt = $this->db->prepare("SELECT is_primary FROM user_phone_numbers WHERE phone_id = :phone_id AND user_id = :user_id");
            $stmt->bindParam(':phone_id', $phoneId, PDO::PARAM_INT);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            $phone = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($phone && $phone['is_primary']) {
                // If deleting primary, set another number as primary
                $stmt = $this->db->prepare("UPDATE user_phone_numbers SET is_primary = 1 
                                          WHERE user_id = :user_id AND phone_id != :phone_id 
                                          ORDER BY verified DESC, created_at ASC LIMIT 1");
                $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
                $stmt->bindParam(':phone_id', $phoneId, PDO::PARAM_INT);
                $stmt->execute();
            }
            
            // Delete the phone number
            $stmt = $this->db->prepare("DELETE FROM user_phone_numbers WHERE phone_id = :phone_id AND user_id = :user_id");
            $stmt->bindParam(':phone_id', $phoneId, PDO::PARAM_INT);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $result = $stmt->execute();
            
            if ($result) {
                $this->db->commit();
                return ['success' => true, 'message' => 'Phone number deleted successfully!'];
            } else {
                $this->db->rollBack();
                return ['success' => false, 'error' => 'Failed to delete phone number.'];
            }
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error deleting phone number: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error occurred while deleting phone number.'];
        }
    }

    public function getPhoneVerificationStatus($userId, $phoneId) {
        $stmt = $this->db->prepare("SELECT phone_id, user_id, phone_number, verified, verification_code, verification_expiry, NOW() as current_time FROM user_phone_numbers WHERE phone_id = ? AND user_id = ?");
        $stmt->execute([$phoneId, $userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function verifyPhoneNumber($userId, $phoneId, $code) {
        // Validate input
        if (empty($code) || !preg_match('/^\d{6}$/', $code)) {
            error_log("Verification failed: Invalid code format - User: $userId, Phone: $phoneId, Code: '$code'");
            return ['success' => false, 'message' => 'Please enter a valid 6-digit verification code.'];
        }
        
        // First check if the phone exists and belongs to user
        $stmt = $this->db->prepare("SELECT * FROM user_phone_numbers WHERE phone_id = ? AND user_id = ?");
        $stmt->execute([$phoneId, $userId]);
        $phone = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$phone) {
            error_log("Verification failed: Phone not found - User: $userId, Phone: $phoneId");
            return ['success' => false, 'message' => 'Phone number not found or does not belong to you.'];
        }
        
        // Log current status for debugging
        error_log("Verification attempt - User: $userId, Phone: $phoneId, Current verified: " . ($phone['verified'] ? 'YES' : 'NO') . ", Code: '$code', Stored code: '{$phone['verification_code']}', Expiry: {$phone['verification_expiry']}");
        
        if ($phone['verified']) {
            error_log("Verification failed: Already verified - User: $userId, Phone: $phoneId");
            return ['success' => false, 'message' => 'This phone number is already verified.'];
        }
        
        if (!$phone['verification_code']) {
            error_log("Verification failed: No verification code - User: $userId, Phone: $phoneId");
            return ['success' => false, 'message' => 'No verification code found. Please request a new code.'];
        }
        
        // Check if code matches
        if ($phone['verification_code'] !== $code) {
            error_log("Verification failed: Code mismatch - User: $userId, Phone: $phoneId, Expected: '{$phone['verification_code']}', Received: '$code'");
            return ['success' => false, 'message' => 'Invalid verification code. Please check and try again.'];
        }
        
        // Check if code has expired
        $currentTime = date('Y-m-d H:i:s');
        if ($phone['verification_expiry'] <= $currentTime) {
            error_log("Verification failed: Code expired - User: $userId, Phone: $phoneId, Expiry: {$phone['verification_expiry']}, Current: $currentTime");
            return ['success' => false, 'message' => 'Verification code has expired. Please request a new code.'];
        }
        
        // All checks passed, verify the phone
        $update = $this->db->prepare("UPDATE user_phone_numbers 
                                     SET verified = 1, verification_code = NULL, verification_expiry = NULL 
                                     WHERE phone_id = ? AND user_id = ?");
        $result = $update->execute([$phoneId, $userId]);
        
        if ($result) {
            // Double-check the update was successful and only affected the correct record
            $checkStmt = $this->db->prepare("SELECT verified FROM user_phone_numbers WHERE phone_id = ? AND user_id = ?");
            $checkStmt->execute([$phoneId, $userId]);
            $checkResult = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            // Also check that no other phone numbers were affected
            $otherPhonesStmt = $this->db->prepare("SELECT COUNT(*) FROM user_phone_numbers WHERE user_id = ? AND phone_id != ? AND verified = 1");
            $otherPhonesStmt->execute([$userId, $phoneId]);
            $otherVerifiedCount = $otherPhonesStmt->fetchColumn();
            
            error_log("Verification check - Target phone verified: " . ($checkResult && $checkResult['verified'] ? 'YES' : 'NO') . ", Other verified phones: $otherVerifiedCount");
            
            if ($checkResult && $checkResult['verified']) {
                error_log("Verification successful - User: $userId, Phone: $phoneId");
                return ['success' => true, 'message' => 'Phone number verified successfully!'];
            } else {
                error_log("Verification failed: Update didn't persist - User: $userId, Phone: $phoneId");
                return ['success' => false, 'message' => 'Failed to verify phone number. Please try again.'];
            }
        } else {
            error_log("Verification failed: Database update failed - User: $userId, Phone: $phoneId");
            return ['success' => false, 'message' => 'Failed to verify phone number. Please try again.'];
        }
    }

    public function resendVerificationCode($userId, $phoneId) {
        $stmt = $this->db->prepare("SELECT * FROM user_phone_numbers WHERE phone_id = :phone_id AND user_id = :user_id");
        $stmt->bindParam(':phone_id', $phoneId, PDO::PARAM_INT);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $phone = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($phone) {
            // Generate new verification code
            $verificationCode = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
            $expiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));
            
            $update = $this->db->prepare("UPDATE user_phone_numbers 
                                         SET verification_code = :code, verification_expiry = :expiry 
                                         WHERE phone_id = :phone_id");
            $update->bindParam(':code', $verificationCode, PDO::PARAM_STR);
            $update->bindParam(':expiry', $expiry, PDO::PARAM_STR);
            $update->bindParam(':phone_id', $phoneId, PDO::PARAM_INT);
            $result = $update->execute();
            
            // Send new verification code via SMS
            list($smsSent, $smsError) = $this->sendVerificationSMS($phone['phone_number'], $verificationCode);
            
            return [
                'success' => $result,
                'verification_code' => $verificationCode,
                'sms_sent' => $smsSent,
                'sms_error' => $smsError
            ];
        }
        
        return ['success' => false, 'error' => 'Phone number not found'];
    }

    public function updatePhoneLabel($userId, $phoneId, $label) {
        $stmt = $this->db->prepare("UPDATE user_phone_numbers SET label = :label WHERE phone_id = :phone_id AND user_id = :user_id");
        $stmt->bindParam(':label', $label, PDO::PARAM_STR);
        $stmt->bindParam(':phone_id', $phoneId, PDO::PARAM_INT);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function getPhoneUsageStats($userId) {
        $stmt = $this->db->prepare("SELECT 
                                    COUNT(*) as total_numbers,
                                    SUM(is_primary) as primary_numbers,
                                    SUM(verified) as verified_numbers
                                   FROM user_phone_numbers 
                                   WHERE user_id = :user_id");
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function clearPrimaryStatus($userId) {
        $stmt = $this->db->prepare("UPDATE user_phone_numbers SET is_primary = 0 WHERE user_id = :user_id");
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    private function countUserPhones($userId) {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM user_phone_numbers WHERE user_id = :user_id");
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchColumn();
    }

    private function sendVerificationSMS($phoneNumber, $code) {
        $params = [
            'message' => "Your verification code is: $code. Valid for 15 minutes.",
            'mobile_number' => $phoneNumber,
            'device' => $this->device
        ];

        $headers = [
            "Content-Type: application/x-www-form-urlencoded",
            "apikey: {$this->apiKey}"
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->smsUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        // Log the SMS attempt
        $logEntry = date('Y-m-d H:i:s') . " | Phone: $phoneNumber | Code: $code | HTTP: $httpCode | Response: $response | Error: $error\n";
        file_put_contents(__DIR__ . '/../sms_log.txt', $logEntry, FILE_APPEND);

        if ($error) {
            error_log('SMS Error: ' . $error);
            return [false, 'Network error: ' . $error];
        }

        // Try to parse response for more details
        $json = json_decode($response, true);
        
        if ($httpCode == 200 && isset($json['success']) && $json['success']) {
            error_log("SMS sent successfully to $phoneNumber. Response: $response");
            return [true, null];
        } else {
            $msg = isset($json['message']) ? $json['message'] : 'Unknown SMS API error';
            error_log("SMS API error for $phoneNumber: $msg (HTTP: $httpCode, Response: $response)");
            return [false, $msg];
        }
    }

    public function fixVerificationStatus($userId, $phoneId) {
        // Check current status
        $stmt = $this->db->prepare("SELECT verified, verification_code, verification_expiry FROM user_phone_numbers WHERE phone_id = ? AND user_id = ?");
        $stmt->execute([$phoneId, $userId]);
        $phone = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$phone) {
            return ['success' => false, 'message' => 'Phone number not found'];
        }
        
        // If there's no verification code and it's not verified, something is wrong
        if (!$phone['verification_code'] && !$phone['verified']) {
            // This shouldn't happen, but let's fix it
            $update = $this->db->prepare("UPDATE user_phone_numbers SET verified = 0 WHERE phone_id = ? AND user_id = ?");
            $update->execute([$phoneId, $userId]);
            return ['success' => true, 'message' => 'Verification status fixed'];
        }
        
        // If there's a verification code but it's expired, clear it
        if ($phone['verification_code'] && $phone['verification_expiry'] && $phone['verification_expiry'] <= date('Y-m-d H:i:s')) {
            $update = $this->db->prepare("UPDATE user_phone_numbers SET verification_code = NULL, verification_expiry = NULL WHERE phone_id = ? AND user_id = ?");
            $update->execute([$phoneId, $userId]);
            return ['success' => true, 'message' => 'Expired verification code cleared'];
        }
        
        return ['success' => true, 'message' => 'Status is correct'];
    }
    
    public function resetAllVerificationStatus($userId) {
        // Reset all verification statuses for the user
        $update = $this->db->prepare("UPDATE user_phone_numbers SET verified = 0, verification_code = NULL, verification_expiry = NULL WHERE user_id = ?");
        $result = $update->execute([$userId]);
        
        if ($result) {
            error_log("Reset all verification statuses for user: $userId");
            return ['success' => true, 'message' => 'All verification statuses have been reset'];
        } else {
            error_log("Failed to reset verification statuses for user: $userId");
            return ['success' => false, 'message' => 'Failed to reset verification statuses'];
        }
    }
}

require_once '../db_connection.php';

try {
    $db = getDatabaseConnection();
    
    // Check if user_phone_numbers table exists, create if not
    $tableCheck = $db->query("SHOW TABLES LIKE 'user_phone_numbers'");
    if ($tableCheck->rowCount() == 0) {
        // Create the table
        $createTable = "CREATE TABLE IF NOT EXISTS `user_phone_numbers` (
            `phone_id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) NOT NULL,
            `phone_number` varchar(20) NOT NULL,
            `label` varchar(100) DEFAULT NULL,
            `is_primary` tinyint(1) DEFAULT 0,
            `verified` tinyint(1) DEFAULT 0,
            `verification_code` varchar(10) DEFAULT NULL,
            `verification_expiry` datetime DEFAULT NULL,
            `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`phone_id`),
            KEY `user_id` (`user_id`),
            KEY `phone_number` (`phone_number`),
            KEY `verified` (`verified`),
            UNIQUE KEY `unique_user_phone` (`user_id`, `phone_number`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $db->exec($createTable);
        error_log("Created user_phone_numbers table");
    } else {
        // Check if the verified column exists and has correct type
        $columnCheck = $db->query("SHOW COLUMNS FROM user_phone_numbers LIKE 'verified'");
        if ($columnCheck->rowCount() == 0) {
            $db->exec("ALTER TABLE user_phone_numbers ADD COLUMN `verified` tinyint(1) DEFAULT 0");
            error_log("Added verified column to user_phone_numbers table");
        }
        
        // Check if verification_code column exists
        $codeColumnCheck = $db->query("SHOW COLUMNS FROM user_phone_numbers LIKE 'verification_code'");
        if ($codeColumnCheck->rowCount() == 0) {
            $db->exec("ALTER TABLE user_phone_numbers ADD COLUMN `verification_code` varchar(10) DEFAULT NULL");
            error_log("Added verification_code column to user_phone_numbers table");
        }
        
        // Check if verification_expiry column exists
        $expiryColumnCheck = $db->query("SHOW COLUMNS FROM user_phone_numbers LIKE 'verification_expiry'");
        if ($expiryColumnCheck->rowCount() == 0) {
            $db->exec("ALTER TABLE user_phone_numbers ADD COLUMN `verification_expiry` datetime DEFAULT NULL");
            error_log("Added verification_expiry column to user_phone_numbers table");
        }
    }
    
    // Initialize phone model with SMS credentials
    $phoneModel = new UserPhoneModel($db, $config['api_key'], $config['device'], $config['url']);
    
    // Get current user ID (from session)
    $userId = $_SESSION['user_id'] ?? null;
    
    if (!$userId) {
        header("Location: ../../../index.php");
        exit();
    }
    
    // Get user stats
    $stats = $phoneModel->getPhoneUsageStats($userId);
    
    // Handle AJAX requests
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        $response = [];
        
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'check_phone':
                    $phoneNumber = $_POST['phone_number'];
                    // Remove all non-digit characters
                    $phoneNumber = preg_replace('/\D/', '', $phoneNumber);
                    // Ensure it's exactly 11 digits starting with 09
                    if (!preg_match('/^09\d{9}$/', $phoneNumber)) {
                        $response = ['valid' => false, 'message' => 'Invalid Philippine phone number. Must be exactly 11 digits starting with 09.'];
                    } elseif ($phoneModel->phoneNumberExists($phoneNumber)) {
                        $response = ['valid' => false, 'message' => 'This phone number is already registered.'];
                    } else {
                        $response = ['valid' => true];
                    }
                    break;
                    
                case 'verify_code':
                    $phoneId = $_POST['phone_id'];
                    $code = $_POST['code'];
                    
                    $result = $phoneModel->verifyPhoneNumber($userId, $phoneId, $code);
                    $response = $result;
                    break;
                    
                case 'resend_code':
                    $phoneId = $_POST['phone_id'];
                    $result = $phoneModel->resendVerificationCode($userId, $phoneId);
                    if ($result['success']) {
                        if ($result['sms_sent']) {
                            $response = ['success' => true, 'message' => 'New verification code sent via SMS!'];
                        } else {
                            $response = ['success' => false, 'message' => 'Verification code updated, but SMS delivery failed: ' . ($result['sms_error'] ?? 'Unknown error') . '. Please try again or contact support.'];
                        }
                    } else {
                        $response = ['success' => false, 'message' => 'Failed to resend verification code. ' . ($result['error'] ?? '')];
                    }
                    break;
                    
                case 'update_label':
                    $phoneId = $_POST['phone_id'];
                    $label = $_POST['label'];
                    if ($phoneModel->updatePhoneLabel($userId, $phoneId, $label)) {
                        $response = ['success' => true, 'message' => 'Label updated successfully!'];
                    } else {
                        $response = ['success' => false, 'message' => 'Failed to update label.'];
                    }
                    break;
                    
                case 'delete_phone':
                    $phoneId = $_POST['phone_id'];
                    $result = $phoneModel->deletePhoneNumber($userId, $phoneId);
                    $response = $result;
                    break;
                    
                case 'debug_status':
                    $phoneId = $_POST['phone_id'];
                    $status = $phoneModel->getPhoneVerificationStatus($userId, $phoneId);
                    $response = ['success' => true, 'status' => $status];
                    break;
                    
                case 'fix_status':
                    $phoneId = $_POST['phone_id'];
                    $result = $phoneModel->fixVerificationStatus($userId, $phoneId);
                    $response = $result;
                    break;
                    
                case 'reset_all_verification':
                    $result = $phoneModel->resetAllVerificationStatus($userId);
                    $response = $result;
                    break;
            }
        }
        
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }
    
    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['add_phone'])) {
            $phoneNumber = $_POST['phone_number'];
            // Remove all non-digit characters
            $phoneNumber = preg_replace('/\D/', '', $phoneNumber);
            $isPrimary = isset($_POST['is_primary']);
            $label = $_POST['label'] ?? null;
            
            // Validate Philippine phone number (11 digits starting with 09)
            if (!preg_match('/^09\d{9}$/', $phoneNumber)) {
                $_SESSION['error'] = "Invalid Philippine phone number format. Must be exactly 11 digits starting with 09.";
            } elseif ($phoneModel->phoneNumberExists($phoneNumber)) {
                $_SESSION['error'] = "This phone number is already registered.";
            } else {
                $result = $phoneModel->addPhoneNumber($userId, $phoneNumber, $isPrimary, $label);
                if ($result['success']) {
                    if ($result['sms_sent']) {
                        $_SESSION['success'] = "Phone number added successfully! A verification code has been sent via SMS.";
                    } else {
                        $_SESSION['error'] = "Phone number added, but SMS delivery failed: " . ($result['sms_error'] ?? 'Unknown error') . ". Please try resending the verification code or contact support.";
                    }
                    $_SESSION['verifying_phone'] = true;
                    $_SESSION['new_phone_id'] = $result['phone_id'];
                } else {
                    $_SESSION['error'] = "Failed to add phone number. " . ($result['error'] ?? '');
                }
            }
        } elseif (isset($_POST['set_primary'])) {
            $phoneId = $_POST['phone_id'];
            
            if ($phoneModel->verifyPhoneOwnership($userId, $phoneId)) {
                if ($phoneModel->setPrimaryPhone($userId, $phoneId)) {
                    $_SESSION['success'] = "Primary phone number updated!";
                } else {
                    $_SESSION['error'] = "Failed to update primary phone number.";
                }
            } else {
                $_SESSION['error'] = "Invalid phone number selected.";
            }
        } elseif (isset($_POST['verify_phone'])) {
            $phoneId = $_POST['phone_id'];
            $code = $_POST['verification_code'];
            
            error_log("Form verification attempt - User: $userId, Phone: $phoneId, Code: '$code'");
            
            $result = $phoneModel->verifyPhoneNumber($userId, $phoneId, $code);
            if ($result['success']) {
                error_log("Form verification successful - User: $userId, Phone: $phoneId");
                $_SESSION['success'] = $result['message'];
                unset($_SESSION['verifying_phone']);
                unset($_SESSION['new_phone_id']);
            } else {
                error_log("Form verification failed - User: $userId, Phone: $phoneId, Error: {$result['message']}");
                $_SESSION['error'] = $result['message'];
            }
        } elseif (isset($_POST['resend_code'])) {
            $phoneId = $_POST['phone_id'];
            
            $result = $phoneModel->resendVerificationCode($userId, $phoneId);
            if ($result['success']) {
                if ($result['sms_sent']) {
                    $_SESSION['success'] = "New verification code sent via SMS!";
                } else {
                    $_SESSION['error'] = "Verification code updated, but SMS delivery failed: " . ($result['sms_error'] ?? 'Unknown error') . '. Please try again or contact support.';
                }
            } else {
                $_SESSION['error'] = "Failed to resend verification code. " . ($result['error'] ?? '');
            }
        } elseif (isset($_POST['update_label'])) {
            $phoneId = $_POST['phone_id'];
            $label = $_POST['label'];
            
            if ($phoneModel->verifyPhoneOwnership($userId, $phoneId)) {
                if ($phoneModel->updatePhoneLabel($userId, $phoneId, $label)) {
                    $_SESSION['success'] = "Phone label updated successfully!";
                } else {
                    $_SESSION['error'] = "Failed to update phone label.";
                }
            } else {
                $_SESSION['error'] = "Invalid phone number selected.";
            }
        }
        
        header("Location: ".$_SERVER['PHP_SELF']);
        exit();
    }
    
    // Get user's phone numbers
    $phoneNumbers = $phoneModel->getPhoneNumbers($userId);
    $primaryPhone = $phoneModel->getPrimaryPhone($userId);
    
    // Check for unverified phone numbers
    $unverifiedCount = 0;
    foreach ($phoneNumbers as $phone) {
        if (!$phone['verified']) {
            $unverifiedCount++;
        }
    }
    
    // Check for session messages
    $error = $_SESSION['error'] ?? null;
    $success = $_SESSION['success'] ?? null;
    $verifyingPhone = $_SESSION['verifying_phone'] ?? false;
    $newPhoneId = $_SESSION['new_phone_id'] ?? null;
    unset($_SESSION['error']);
    unset($_SESSION['success']);
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

<?php include('../../components/header.php'); ?>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="nav-md">
    <div class="container body">
      <div class="main_container">
            <?php include('../../components/sidebar.php'); ?>
            </div>
          </div>
        </div>
        <?php include('../../components/navigation.php')?>
        <div class="right_col" role="main"> 
    <!-- Floating Action Button -->
    <a href="#" class="floating-btn pulse" data-bs-toggle="modal" data-bs-target="#addPhoneModal">
        <i class="bi bi-plus-lg"></i>
    </a>

    <!-- Toast Notifications -->
    <div class="toast-container">
        <?php if (isset($error)): ?>
            <div class="toast show align-items-center text-white bg-danger border-0" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo htmlspecialchars($error); ?>
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (isset($success)): ?>
            <div class="toast show align-items-center text-white bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="bi bi-check-circle-fill me-2"></i><?php echo htmlspecialchars($success); ?>
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="container">
       
        <div class="row">
            <div class="col-12">
                <div class="card border-0 shadow-sm" style="background: white; border-radius: 16px;">
                    <div class="card-header bg-transparent border-0 py-4" style="border-radius: 16px 16px 0 0;">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-telephone text-primary fs-4 me-3"></i>
                            <div>
                                <h5 class="mb-1 text-dark fw-semibold">My Phone Numbers</h5>
                                <p class="text-muted mb-0 small">Manage your registered phone numbers</p>
                            </div>
                            <div class="ms-auto d-flex align-items-center">
                                <button class="btn btn-outline-primary me-2" data-bs-toggle="modal" data-bs-target="#helpModal">
                                    <i class="bi bi-question-circle me-1"></i>Help
                                </button>
                                <button class="btn btn-warning me-2" id="resetAllVerificationBtn">
                                    <i class="bi bi-arrow-clockwise me-1"></i>Reset All Verification
                                </button>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPhoneModal">
                                    <i class="bi bi-plus-lg me-1"></i>Add Number
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card-body px-4 py-0">
                        <div class="tab-content">
                            <!-- Numbers Tab -->
                            <div class="tab-pane fade show active" id="numbers-tab">
                                <?php if (empty($phoneNumbers)): ?>
                                    <div class="text-center py-5">
                                        <div class="bg-light bg-opacity-50 p-4 rounded-circle d-inline-block mb-3">
                                            <i class="bi bi-phone text-muted" style="font-size: 3rem;"></i>
                                        </div>
                                        <h4 class="text-dark fw-semibold mb-2">No Phone Numbers Found</h4>
                                        <p class="text-muted mb-4">Add your first phone number to get started with phone management</p>
                                        <button class="btn btn-primary px-4 py-2 rounded-pill" data-bs-toggle="modal" data-bs-target="#addPhoneModal">
                                            <i class="bi bi-plus-lg me-2"></i>Add Phone Number
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <!-- Search Filter -->
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <div class="search-container">
                                                <div class="input-group">
                                                    <span class="input-group-text bg-light border-end-0">
                                                        <i class="bi bi-search text-muted"></i>
                                                    </span>
                                                    <input type="text" id="phoneSearchInput" class="form-control border-start-0" 
                                                           placeholder="Search phone numbers, labels, or status..." 
                                                           style="border-radius: 0.375rem 0 0 0.375rem;">
                                                    <button class="btn btn-outline-secondary border-start-0" type="button" id="clearSearchBtn" 
                                                            style="border-radius: 0 0.375rem 0.375rem 0; display: none;">
                                                        <i class="bi bi-x-lg"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="d-flex justify-content-end align-items-center">
                                                <small class="text-muted me-3">
                                                    <span id="searchResultsCount">Showing <?php echo count($phoneNumbers); ?> numbers</span>
                                                </small>
                                                <div class="btn-group" role="group">
                                                    <input type="radio" class="btn-check" name="statusFilter" id="filterAll" value="all" checked>
                                                    <label class="btn btn-outline-secondary btn-sm" for="filterAll">All</label>
                                                    
                                                    <input type="radio" class="btn-check" name="statusFilter" id="filterVerified" value="verified">
                                                    <label class="btn btn-outline-success btn-sm" for="filterVerified">Verified</label>
                                                    
                                                    <input type="radio" class="btn-check" name="statusFilter" id="filterUnverified" value="unverified">
                                                    <label class="btn btn-outline-warning btn-sm" for="filterUnverified">Unverified</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="phone-list">
                                        <div class="table-responsive">
                                            <table class="table table-hover align-middle mb-0">
                                                <thead>
                                                    <tr class="border-0">
                                                        <th class="border-0 text-muted fw-semibold text-uppercase small" style="font-size: 0.75rem; letter-spacing: 0.5px;">Phone Number</th>
                                                        <th class="border-0 text-muted fw-semibold text-uppercase small" style="font-size: 0.75rem; letter-spacing: 0.5px;">Label</th>
                                                        <th class="border-0 text-muted fw-semibold text-uppercase small" style="font-size: 0.75rem; letter-spacing: 0.5px;">Status</th>
                                                        <th class="border-0 text-muted fw-semibold text-uppercase small" style="font-size: 0.75rem; letter-spacing: 0.5px;">Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($phoneNumbers as $phone): ?>
                                                        <tr class="phone-item <?php echo $phone['is_primary'] ? 'primary' : ''; ?> border-0" 
                                                            style="border-bottom: 1px solid #f8f9fa;"
                                                            data-phone-number="<?php echo htmlspecialchars($phone['phone_number'] ?? ''); ?>"
                                                            data-label="<?php echo htmlspecialchars($phone['label'] ?? ''); ?>"
                                                            data-status="<?php echo $phone['verified'] ? 'verified' : 'unverified'; ?>"
                                                            data-is-primary="<?php echo $phone['is_primary'] ? 'true' : 'false'; ?>">
                                                            <td class="py-3">
                                                                <div class="d-flex align-items-center">
                                                                    <i class="bi bi-phone text-primary me-3"></i>
                                                                    <div>
                                                                        <span class="phone-number-display fw-semibold text-dark"><?php echo htmlspecialchars($phone['phone_number']); ?></span>
                                                                        <?php if ($phone['is_primary']): ?>
                                                                            <div class="mt-1">
                                                                                <span class="badge bg-success bg-opacity-10 text-white border border-success border-opacity-25 px-2 py-1" style="font-size: 0.7rem;">
                                                                                    <i class="bi bi-star-fill me-1"></i>Primary
                                                                                </span>
                                                                            </div>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                            <td class="py-3">
                                                                <div class="d-flex align-items-center">
                                                                    <span class="phone-label-text text-dark"><?php echo htmlspecialchars($phone['label'] ?? 'No label'); ?></span>
                                                                    <button class="btn btn-link btn-sm p-0 ms-2 edit-label-btn" 
                                                                           data-phone-id="<?php echo $phone['phone_id']; ?>"
                                                                           data-current-label="<?php echo htmlspecialchars($phone['label'] ?? ''); ?>"
                                                                           style="color: #6c757d; text-decoration: none;">
                                                                        <i class="bi bi-pencil-square"></i>
                                                                    </button>
                                                                    <input type="text" class="form-control form-control-sm label-input border-0 bg-light" 
                                                                           data-phone-id="<?php echo $phone['phone_id']; ?>"
                                                                           value="<?php echo htmlspecialchars($phone['label'] ?? ''); ?>"
                                                                           style="display: none; max-width: 150px;">
                                                                </div>
                                                            </td>
                                                            <td class="py-3">
                                                                <?php if ($phone['verified']): ?>
                                                                    <span class="badge bg-success bg-opacity-10 text-white border border-success border-opacity-25 px-3 py-2">
                                                                        <i class="bi bi-check-circle-fill me-1"></i>Verified
                                                                    </span>
                                                                <?php else: ?>
                                                                    <span class="badge bg-warning bg-opacity-10 text-white border border-warning border-opacity-25 px-3 py-2">
                                                                        <i class="bi bi-exclamation-triangle-fill me-1"></i>Unverified
                                                                    </span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td class="py-3">
                                                                <div class="d-flex gap-2 flex-wrap">
                                                                    <?php if (!$phone['is_primary'] && $phone['verified']): ?>
                                                                        <form method="POST" class="d-inline">
                                                                            <input type="hidden" name="phone_id" value="<?php echo $phone['phone_id']; ?>">
                                                                            <button type="submit" name="set_primary" class="btn btn-sm btn-success rounded-pill px-3 py-1">
                                                                                <i class="bi bi-star-fill me-1"></i>Make Primary
                                                                            </button>
                                                                        </form>
                                                                    <?php endif; ?>
                                                                    
                                                                    <?php if (!$phone['verified']): ?>
                                                                        <button type="button" class="btn btn-sm btn-warning rounded-pill px-3 py-1 verify-btn" 
                                                                                data-phone-id="<?php echo $phone['phone_id']; ?>">
                                                                            <i class="bi bi-shield-check me-1"></i>Verify
                                                                        </button>
                                                                        <button type="button" class="btn btn-sm btn-info rounded-pill px-3 py-1 resend-btn" 
                                                                                data-phone-id="<?php echo $phone['phone_id']; ?>">
                                                                            <i class="bi bi-arrow-repeat me-1"></i>Resend
                                                                        </button>
                                                                    <?php endif; ?>
                                                                    
                                                                    <button type="button" class="btn btn-sm btn-danger rounded-pill px-3 py-1 delete-btn" 
                                                                            data-phone-id="<?php echo $phone['phone_id']; ?>"
                                                                            data-phone-number="<?php echo htmlspecialchars($phone['phone_number']); ?>">
                                                                        <i class="bi bi-trash-fill me-1"></i>Delete
                                                                    </button>
                                                                    
                                                                    <!-- Debug button (remove in production)
                                                                    <button type="button" class="btn btn-sm btn-secondary rounded-pill px-3 py-1 debug-btn" 
                                                                            data-phone-id="<?php echo $phone['phone_id']; ?>">
                                                                        <i class="bi bi-bug me-1"></i>Debug
                                                                    </button> -->
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        
                                        <!-- No Results Message -->
                                        <div id="noResultsMessage" class="text-center py-5" style="display: none;">
                                            <div class="bg-light bg-opacity-50 p-4 rounded-circle d-inline-block mb-3">
                                                <i class="bi bi-search text-muted" style="font-size: 3rem;"></i>
                                            </div>
                                            <h4 class="text-dark fw-semibold mb-2">No Results Found</h4>
                                            <p class="text-muted mb-4">Try adjusting your search terms or filters</p>
                                            <button class="btn btn-outline-primary px-4 py-2 rounded-pill" id="clearAllFiltersBtn">
                                                <i class="bi bi-arrow-clockwise me-2"></i>Clear All Filters
                                            </button>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card-footer bg-transparent border-0 py-3" style="border-radius: 0 0 16px 16px;">
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted">
                                <i class="bi bi-clock me-1"></i>Last updated: <?php echo date('F j, Y, g:i a'); ?>
                            </small>
                            <small class="text-muted">
                                <i class="bi bi-hash me-1"></i>Total numbers: <?php echo count($phoneNumbers); ?>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Phone Modal -->
    <div class="modal fade" id="addPhoneModal" tabindex="-1" aria-labelledby="addPhoneModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-warning text-dark border-0 py-3">
                    <h6 class="modal-title fw-bold mb-0" id="addPhoneModalLabel">
                        <i class="bi bi-plus-circle me-2"></i>Add New Phone Number
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="addPhoneForm" method="POST">
                    <div class="modal-body p-4">
                        <div class="mb-4">
                            <label for="phone_number" class="form-label fw-semibold text-warning mb-2">Philippine Mobile Number</label>
                            <div class="input-group">
                                <span class="input-group-text bg-warning text-dark border-end-0 fw-semibold">+63</span>
                                <input type="text" name="phone_number" id="phone_number" class="form-control border-start-0" 
                                       placeholder="09171234567" required
                                       pattern="09[0-9]{9}" title="Philippine number starting with 09 (11 digits)"
                                       maxlength="11">
                            </div>
                            <div class="valid-feedback validation-feedback mt-1">
                                <i class="bi bi-check-circle-fill me-1"></i>Phone number is valid and available!
                            </div>
                            <div class="invalid-feedback validation-feedback mt-1">
                                <i class="bi bi-exclamation-triangle-fill me-1"></i>Please enter a valid Philippine number (09XXXXXXXXX).
                            </div>
                            <small class="text-muted mt-1 d-block">Must be exactly 11 digits starting with 09</small>
                        </div>
                        
                        <div class="mb-4">
                            <label for="label" class="form-label fw-semibold text-warning mb-2">Name<span class="text-muted">(Optional)</span></label>
                            <input type="text" name="label" id="label" class="form-control" 
                                   placeholder="Work, Personal, Home...">
                            <small class="text-muted mt-1 d-block">Helps you identify this number's purpose</small>
                        </div>
                        
                        <div class="form-check form-switch mb-4">
                            <input class="form-check-input" type="checkbox" name="is_primary" id="is_primary">
                            <label class="form-check-label fw-semibold text-warning" for="is_primary">
                                <i class="bi bi-star-fill me-1"></i>Set as Primary Number
                            </label>
                        </div>
                        
                        <div class="alert alert-warning border border-warning border-opacity-25 mb-0" style="background-color: #fff3cd;">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-info-circle text-warning me-2"></i>
                                <small class="text-warning mb-0">A verification code will be sent via SMS to confirm ownership of this number.</small>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light border-0 py-3">
                        <button type="button" class="btn btn-outline-warning px-4" data-bs-dismiss="modal">
                            <i class="bi bi-x-lg me-1"></i>Cancel
                        </button>
                        <button type="submit" name="add_phone" id="addPhoneBtn" class="btn btn-warning px-4" disabled>
                            <i class="bi bi-plus-lg me-1"></i>Add Number
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Verification Modal -->
    <div id="verificationModal" class="verification-modal">
        <div class="verification-content">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="m-0"><i class="bi bi-shield-check text-primary me-2"></i>Verify Phone Number</h4>
                <button type="button" class="btn-close" onclick="closeModal()" aria-label="Close"></button>
            </div>
            <div class="alert alert-info">
                <i class="bi bi-info-circle-fill me-2"></i>
                <strong>Enter the 6-digit verification code</strong> sent to your phone number.
            </div>
            <form id="verifyForm" method="POST">
                <input type="hidden" name="phone_id" id="modalPhoneId" value="">
                <div class="mb-3">
                    <label for="verification_code" class="form-label">Verification Code</label>
                    <input type="text" name="verification_code" id="verification_code" 
                           class="form-control verification-code-input text-center" 
                           pattern="[0-9]{6}" title="6-digit code" required maxlength="6" 
                           autocomplete="off" placeholder="000000"
                           style="font-size: 1.5rem; letter-spacing: 0.5rem;">
                    <div class="text-end mt-2">
                        <small class="text-muted" id="countdown">Code expires in 15:00</small>
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <button type="button" class="btn btn-outline-secondary" onclick="closeModal()">
                        <i class="bi bi-x-lg me-1"></i>Cancel
                    </button>
                    <div>
                        <button type="button" class="btn btn-outline-info me-2" id="resendFromModal">
                            <i class="bi bi-arrow-repeat me-1"></i>Resend Code
                        </button>
                        <button type="submit" name="verify_phone" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i>Verify
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Help Modal -->
    <div class="modal fade" id="helpModal" tabindex="-1" aria-labelledby="helpModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-md">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 12px;">
                <div class="modal-header bg-white border-0 py-3" style="border-radius: 12px 12px 0 0;">
                    <h6 class="modal-title text-dark fw-semibold mb-0" id="helpModalLabel">
                        <i class="bi bi-question-circle text-primary me-2"></i>Phone Number Help
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0" style="max-height: 400px; overflow-y: auto;">
                    <div class="p-3">
                        <!-- Adding Phone Number -->
                        <div class="mb-3">
                            <div class="d-flex align-items-center mb-2">
                                <i class="bi bi-plus-circle text-primary me-2"></i>
                                <h6 class="mb-0 text-dark fw-semibold">Adding Phone Numbers</h6>
                            </div>
                            <ul class="list-unstyled mb-0 small">
                                <li class="mb-1">
                                    <i class="bi bi-check-circle-fill text-success me-2" style="font-size: 0.8rem;"></i>
                                    Enter 11-digit number starting with 09
                                </li>
                                <li class="mb-1">
                                    <i class="bi bi-check-circle-fill text-success me-2" style="font-size: 0.8rem;"></i>
                                    Add optional label and set as primary
                                </li>
                                <li class="mb-1">
                                    <i class="bi bi-check-circle-fill text-success me-2" style="font-size: 0.8rem;"></i>
                                    Verification code sent via SMS
                                </li>
                            </ul>
                        </div>

                        <!-- Verification Process -->
                        <div class="mb-3">
                            <div class="d-flex align-items-center mb-2">
                                <i class="bi bi-shield-check text-primary me-2"></i>
                                <h6 class="mb-0 text-dark fw-semibold">Verification Process</h6>
                            </div>
                            <ul class="list-unstyled mb-0 small">
                                <li class="mb-1">
                                    <i class="bi bi-123 text-info me-2" style="font-size: 0.8rem;"></i>
                                    Enter 6-digit code from SMS
                                </li>
                                <li class="mb-1">
                                    <i class="bi bi-clock-history text-warning me-2" style="font-size: 0.8rem;"></i>
                                    Codes expire after 15 minutes
                                </li>
                                <li class="mb-1">
                                    <i class="bi bi-arrow-repeat text-primary me-2" style="font-size: 0.8rem;"></i>
                                    Request new code if needed
                                </li>
                            </ul>
                        </div>

                        <!-- Primary Number -->
                        <div class="mb-3">
                            <div class="d-flex align-items-center mb-2">
                                <i class="bi bi-star-fill text-primary me-2"></i>
                                <h6 class="mb-0 text-dark fw-semibold">Primary Number</h6>
                            </div>
                            <ul class="list-unstyled mb-0 small">
                                <li class="mb-1">
                                    <i class="bi bi-envelope-fill text-info me-2" style="font-size: 0.8rem;"></i>
                                    Used for important communications
                                </li>
                                <li class="mb-1">
                                    <i class="bi bi-arrow-left-right text-primary me-2" style="font-size: 0.8rem;"></i>
                                    Can be changed anytime
                                </li>
                                <li class="mb-1">
                                    <i class="bi bi-shield-lock text-success me-2" style="font-size: 0.8rem;"></i>
                                    Must be verified
                                </li>
                            </ul>
                        </div>

                        <!-- Labels -->
                        <div class="mb-3">
                            <div class="d-flex align-items-center mb-2">
                                <i class="bi bi-tags-fill text-primary me-2"></i>
                                <h6 class="mb-0 text-dark fw-semibold">Labels</h6>
                            </div>
                            <ul class="list-unstyled mb-0 small">
                                <li class="mb-1">
                                    <i class="bi bi-pencil-square text-primary me-2" style="font-size: 0.8rem;"></i>
                                    Click edit icon to add/change labels
                                </li>
                                <li class="mb-1">
                                    <i class="bi bi-funnel-fill text-info me-2" style="font-size: 0.8rem;"></i>
                                    Helps identify number purposes
                                </li>
                                <li class="mb-1">
                                    <i class="bi bi-card-heading text-secondary me-2" style="font-size: 0.8rem;"></i>
                                    Examples: "Work", "Personal", "Backup"
                                </li>
                            </ul>
                        </div>

                        <!-- Quick Tips -->
                        <div class="bg-light rounded p-2 mt-3">
                            <div class="d-flex align-items-center mb-1">
                                <i class="bi bi-lightbulb text-warning me-2"></i>
                                <small class="fw-semibold text-dark">Quick Tips</small>
                            </div>
                            <ul class="list-unstyled mb-0 small text-muted">
                                <li class="mb-1">• You can have multiple phone numbers</li>
                                <li class="mb-1">• Only one can be primary at a time</li>
                                <li class="mb-1">• All numbers must be verified</li>
                                <li class="mb-1">• Use labels to organize your numbers</li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-white border-0 py-2" style="border-radius: 0 0 12px 12px;">
                    <button type="button" class="btn btn-primary btn-sm px-3" data-bs-dismiss="modal">
                        <i class="bi bi-check-lg me-1"></i>Got it!
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- New Phone Added Modal -->
    <div class="modal fade" id="newPhoneModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bi bi-phone me-2"></i>Verification Required</h5>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-4">
                        <i class="bi bi-chat-square-text text-primary" style="font-size: 3rem;"></i>
                    </div>
                    <p>A verification code has been sent to your phone number. Please verify it to complete the registration.</p>
                    <p>You can verify now or later from your phone numbers list.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Verify Later</button>
                    <button type="button" class="btn btn-primary" id="verifyNowBtn">Verify Now</button>
                </div>
            </div>
        </div>
    </div>
                                                        

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Force cache refresh
        console.log('UserPhone.js loaded at:', new Date().toISOString());
    </script>
    <script>
        $(document).ready(function() {
            // Auto-close toasts after 5 seconds
            $('.toast').each(function() {
                setTimeout(() => {
                    $(this).toast('hide');
                }, 5000);
            });
            
            // Show new phone modal if we just added a phone
            <?php if ($verifyingPhone && $newPhoneId): ?>
                const newPhoneModal = new bootstrap.Modal(document.getElementById('newPhoneModal'));
                newPhoneModal.show();
                
                // Handle verify now button
                $('#verifyNowBtn').click(function() {
                    newPhoneModal.hide();
                    $('#modalPhoneId').val(<?php echo $newPhoneId; ?>);
                    $('#verificationModal').show();
                    startCountdown();
                });
            <?php endif; ?>
            
            // Phone number input formatting and validation
            $('#phone_number').on('input', function() {
                let phoneInput = $(this);
                let value = phoneInput.val().trim();
                
                // Remove all non-digit characters
                let digitsOnly = value.replace(/\D/g, '');
                
                // Ensure it starts with 09 and has exactly 11 digits
                if (digitsOnly.length > 0) {
                    // If it starts with 9, prepend 0
                    if (digitsOnly.charAt(0) === '9' && digitsOnly.length <= 10) {
                        phoneInput.val('0' + digitsOnly.substring(0, 10));
                    } 
                    // If it starts with 09, limit to 11 digits
                    else if (digitsOnly.startsWith('09') && digitsOnly.length > 11) {
                        phoneInput.val(digitsOnly.substring(0, 11));
                    }
                    // Otherwise, just take first 11 digits
                    else {
                        phoneInput.val(digitsOnly.substring(0, 11));
                    }
                }
                
                validatePhoneNumber();
            });
            
            function validatePhoneNumber() {
                const phoneInput = $('#phone_number');
                const feedback = $('.validation-feedback');
                const addButton = $('#addPhoneBtn');
                const phoneNumber = phoneInput.val().trim();
                
                // Reset state
                phoneInput.removeClass('is-valid is-invalid');
                feedback.hide();
                addButton.prop('disabled', true);
                
                // Check if empty
                if (!phoneNumber) {
                    return;
                }
                
                // Validate format - must be exactly 11 digits starting with 09
                if (!/^09\d{9}$/.test(phoneNumber)) {
                    phoneInput.addClass('is-invalid');
                    $('.invalid-feedback').text('Invalid Philippine phone number. Must be exactly 11 digits starting with 09.').show();
                    return;
                }
                
                // Check if number exists via AJAX
                $.ajax({
                    url: window.location.href,
                    method: 'POST',
                    data: {
                        action: 'check_phone',
                        phone_number: phoneNumber
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.valid) {
                            phoneInput.addClass('is-valid');
                            $('.valid-feedback').show();
                            addButton.prop('disabled', false);
                        } else {
                            phoneInput.addClass('is-invalid');
                            $('.invalid-feedback').text(response.message).show();
                        }
                    },
                    error: function() {
                        phoneInput.addClass('is-invalid');
                        $('.invalid-feedback').text('Error validating phone number. Please try again.').show();
                    }
                });
            }
            
            // Verify button click handler
            $('.verify-btn').click(function() {
                const phoneId = $(this).data('phone-id');
                $('#modalPhoneId').val(phoneId);
                $('#verificationModal').show();
                $('#verification_code').focus();
                startCountdown();
            });
            
            // Resend button click handler
            $('.resend-btn').click(function() {
                const phoneId = $(this).data('phone-id');
                resendVerificationCode(phoneId);
            });
            
            // Resend button in modal
            $('#resendFromModal').click(function() {
                const phoneId = $('#modalPhoneId').val();
                resendVerificationCode(phoneId);
            });
            
            // Verification code input formatting
            $('.verification-code-input').on('input', function() {
                let value = $(this).val();
                // Remove all non-digit characters
                value = value.replace(/\D/g, '');
                // Limit to 6 digits
                value = value.substring(0, 6);
                $(this).val(value);
            });
            
            // Auto-submit when 6 digits are entered
            $('.verification-code-input').on('keyup', function() {
                if ($(this).val().length === 6) {
                    // Small delay to ensure the last digit is processed
                    setTimeout(() => {
                        if ($(this).val().length === 6) {
                            verifyPhoneNumber();
                        }
                    }, 100);
                }
            });
            
            // Delete button click handler
            $('.delete-btn').click(function() {
                const phoneId = $(this).data('phone-id');
                const phoneNumber = $(this).data('phone-number');
                
                Swal.fire({
                    title: 'Delete Phone Number?',
                    text: `Are you sure you want to delete ${phoneNumber}?`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Yes, delete it!',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        deletePhoneNumber(phoneId);
                    }
                });
            });
            
            // Debug button click handler
            $('.debug-btn').click(function() {
                const phoneId = $(this).data('phone-id');
                debugPhoneStatus(phoneId);
            });
            
            // Form submission handler
            $('#verifyForm').submit(function(e) {
                e.preventDefault();
                verifyPhoneNumber();
            });
            
            // Close modal when clicking outside
            $(window).click(function(event) {
                if (event.target === document.getElementById('verificationModal')) {
                    closeModal();
                }
            });

            // Add phone form submission
            $('#addPhoneForm').submit(function(e) {
                const phoneInput = $('#phone_number');
                if (phoneInput.hasClass('is-invalid')) {
                    e.preventDefault();
                    Swal.fire({
                        title: 'Invalid Phone Number',
                        text: 'Please enter a valid Philippine phone number starting with 09 (11 digits total)',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                }
            });
            
            // Label editing functionality
            $('.edit-label-btn').click(function() {
                const phoneId = $(this).data('phone-id');
                const currentLabel = $(this).data('current-label') || '';
                
                // Hide the text and edit button
                $(this).siblings('.phone-label-text').hide();
                $(this).hide();
                
                // Show the input field
                const input = $(`.label-input[data-phone-id="${phoneId}"]`);
                input.show().focus().val(currentLabel);
            });
            
            // Handle label input blur (when user clicks away)
            $('.label-input').on('blur', function() {
                const phoneId = $(this).data('phone-id');
                const newLabel = $(this).val().trim();
                
                // Show the text and edit button
                $(this).hide();
                $(this).siblings('.phone-label-text').show();
                $(this).siblings('.edit-label-btn').show();
                
                // Only update if the label changed
                if (newLabel !== $(this).siblings('.phone-label-text').text().replace('No label', '').trim()) {
                    updatePhoneLabel(phoneId, newLabel);
                }
            });
            
            // Handle label input enter key
            $('.label-input').on('keypress', function(e) {
                if (e.which === 13) { // Enter key
                    $(this).blur();
                }
            });
            
            // Download QR code button
            $('#downloadQrBtn').click(function() {
                Swal.fire({
                    title: 'Download QR Code',
                    text: 'This feature would download your contact QR code in a real application',
                    icon: 'info',
                    confirmButtonText: 'OK'
                });
            });
        });
        
        function verifyPhoneNumber() {
            const phoneId = $('#modalPhoneId').val();
            const code = $('input[name="verification_code"]').val().trim();
            
            if (!/^\d{6}$/.test(code)) {
                Swal.fire({
                    title: 'Invalid Code',
                    text: 'Please enter a 6-digit verification code',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
                return;
            }
            
            Swal.fire({
                title: 'Verifying...',
                text: 'Please wait while we verify your code',
                icon: 'info',
                showConfirmButton: false,
                allowOutsideClick: false
            });
            
            $.ajax({
                url: window.location.href,
                method: 'POST',
                data: {
                    action: 'verify_code',
                    phone_id: phoneId,
                    code: code
                },
                dataType: 'json',
                success: function(response) {
                    Swal.close();
                    if (response.success) {
                        Swal.fire({
                            title: 'Success!',
                            text: response.message,
                            icon: 'success',
                            confirmButtonText: 'OK'
                        }).then(() => {
                            // Force a hard refresh to ensure the page shows updated status
                            window.location.href = window.location.href;
                        });
                    } else {
                        Swal.fire({
                            title: 'Error!',
                            text: response.message,
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });
                    }
                },
                error: function() {
                    Swal.fire({
                        title: 'Error!',
                        text: 'An error occurred while verifying. Please try again.',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                }
            });
        }
        
        function resendVerificationCode(phoneId) {
            Swal.fire({
                title: 'Resending Code',
                text: 'Please wait while we send a new verification code...',
                icon: 'info',
                showConfirmButton: false,
                allowOutsideClick: false
            });
            
            $.ajax({
                url: window.location.href,
                method: 'POST',
                data: {
                    action: 'resend_code',
                    phone_id: phoneId
                },
                dataType: 'json',
                success: function(response) {
                    Swal.close();
                    if (response.success) {
                        Swal.fire({
                            title: 'Code Sent!',
                            text: response.message,
                            icon: 'success',
                            confirmButtonText: 'OK'
                        });
                    } else {
                        Swal.fire({
                            title: 'Error!',
                            text: response.message,
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });
                    }
                },
                error: function() {
                    Swal.fire({
                        title: 'Error!',
                        text: 'An error occurred while resending the code. Please try again.',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                }
            });
        }
        
        function updatePhoneLabel(phoneId, label) {
            Swal.fire({
                title: 'Updating Label...',
                text: 'Please wait while we update your label',
                icon: 'info',
                showConfirmButton: false,
                allowOutsideClick: false
            });
            
            $.ajax({
                url: window.location.href,
                method: 'POST',
                data: {
                    action: 'update_label',
                    phone_id: phoneId,
                    label: label
                },
                dataType: 'json',
                success: function(response) {
                    Swal.close();
                    if (response.success) {
                        // Update the displayed label
                        $(`.phone-label-text[data-phone-id="${phoneId}"]`).text(label || 'No label');
                        $(`.edit-label-btn[data-phone-id="${phoneId}"]`).data('current-label', label);
                        
                        Swal.fire({
                            title: 'Success!',
                            text: response.message,
                            icon: 'success',
                            confirmButtonText: 'OK',
                            timer: 1500,
                            showConfirmButton: false
                        });
                    } else {
                        Swal.fire({
                            title: 'Error!',
                            text: response.message,
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });
                    }
                },
                error: function() {
                    Swal.fire({
                        title: 'Error!',
                        text: 'An error occurred while updating the label. Please try again.',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                }
            });
        }
        
        function deletePhoneNumber(phoneId) {
            Swal.fire({
                title: 'Deleting Phone Number...',
                text: 'Please wait while we delete your phone number',
                icon: 'info',
                showConfirmButton: false,
                allowOutsideClick: false
            });
            
            $.ajax({
                url: window.location.href,
                method: 'POST',
                data: {
                    action: 'delete_phone',
                    phone_id: phoneId
                },
                dataType: 'json',
                success: function(response) {
                    Swal.close();
                    if (response.success) {
                        Swal.fire({
                            title: 'Success!',
                            text: response.message,
                            icon: 'success',
                            confirmButtonText: 'OK'
                        }).then(() => {
                            window.location.reload();
                        });
                    } else {
                        Swal.fire({
                            title: 'Error!',
                            text: response.error || 'Failed to delete phone number.',
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });
                    }
                },
                error: function() {
                    Swal.fire({
                        title: 'Error!',
                        text: 'An error occurred while deleting the phone number. Please try again.',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                }
            });
        }
        
        function startCountdown() {
            let minutes = 14;
            let seconds = 59;
            
            const countdownElement = $('#countdown');
            
            const interval = setInterval(function() {
                countdownElement.text(`Code expires in ${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`);
                
                if (seconds === 0) {
                    if (minutes === 0) {
                        clearInterval(interval);
                        countdownElement.text('Code expired').addClass('text-danger');
                        return;
                    }
                    minutes--;
                    seconds = 59;
                } else {
                    seconds--;
                }
            }, 1000);
        }
        
        function closeModal() {
            $('#verificationModal').hide();
            $('input[name="verification_code"]').val('').focus();
            $('#countdown').removeClass('text-danger').text('Code expires in 15:00');
        }
        
        function debugPhoneStatus(phoneId) {
            $.ajax({
                url: window.location.href,
                method: 'POST',
                data: {
                    action: 'debug_status',
                    phone_id: phoneId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.status) {
                        const status = response.status;
                        Swal.fire({
                            title: 'Phone Status Debug',
                            html: `
                                <div class="text-left">
                                    <p><strong>Phone ID:</strong> ${status.phone_id}</p>
                                    <p><strong>User ID:</strong> ${status.user_id}</p>
                                    <p><strong>Phone Number:</strong> ${status.phone_number}</p>
                                    <p><strong>Verified:</strong> ${status.verified ? 'YES' : 'NO'}</p>
                                    <p><strong>Verification Code:</strong> ${status.verification_code || 'NULL'}</p>
                                    <p><strong>Expiry:</strong> ${status.verification_expiry || 'NULL'}</p>
                                    <p><strong>Current Time:</strong> ${status.current_time}</p>
                                </div>
                            `,
                            icon: 'info',
                            showCancelButton: true,
                            confirmButtonText: 'Fix Status',
                            cancelButtonText: 'Close',
                            reverseButtons: true
                        }).then((result) => {
                            if (result.isConfirmed) {
                                fixPhoneStatus(phoneId);
                            }
                        });
                    } else {
                        Swal.fire({
                            title: 'Error',
                            text: 'Failed to get phone status',
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });
                    }
                },
                error: function() {
                    Swal.fire({
                        title: 'Error',
                        text: 'Failed to get phone status',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                }
            });
        }
        
        function fixPhoneStatus(phoneId) {
            $.ajax({
                url: window.location.href,
                method: 'POST',
                data: {
                    action: 'fix_status',
                    phone_id: phoneId
                },
                dataType: 'json',
                success: function(response) {
                    Swal.fire({
                        title: response.success ? 'Success' : 'Error',
                        text: response.message,
                        icon: response.success ? 'success' : 'error',
                        confirmButtonText: 'OK'
                    }).then(() => {
                        if (response.success) {
                            window.location.reload();
                        }
                    });
                },
                error: function() {
                    Swal.fire({
                        title: 'Error',
                        text: 'Failed to fix phone status',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                }
            });
        }
        
        function resetAllVerification() {
            Swal.fire({
                title: 'Resetting Verification Status...',
                text: 'Please wait while we reset all verification statuses',
                icon: 'info',
                showConfirmButton: false,
                allowOutsideClick: false
            });
            
            $.ajax({
                url: window.location.href,
                method: 'POST',
                data: {
                    action: 'reset_all_verification'
                },
                dataType: 'json',
                success: function(response) {
                    Swal.close();
                    Swal.fire({
                        title: response.success ? 'Success' : 'Error',
                        text: response.message,
                        icon: response.success ? 'success' : 'error',
                        confirmButtonText: 'OK'
                    }).then(() => {
                        if (response.success) {
                            window.location.reload();
                        }
                    });
                },
                error: function() {
                    Swal.close();
                    Swal.fire({
                        title: 'Error',
                        text: 'Failed to reset verification statuses',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                }
            });
        }

        // Reset all verification button click handler
        $('#resetAllVerificationBtn').click(function() {
            Swal.fire({
                title: 'Reset All Verification Status?',
                text: 'This will reset all phone numbers to unverified status. Are you sure?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#f39c12',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, reset all!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    resetAllVerification();
                }
            });
        });
        
        // Search and Filter Functionality
        let allPhoneRows = $('.phone-item');
        let totalCount = allPhoneRows.length;
        
        // Search input handler
        $('#phoneSearchInput').on('input', function() {
            const searchTerm = String($(this).val() || '').toLowerCase().trim();
            const clearBtn = $('#clearSearchBtn');
            
            if (searchTerm.length > 0) {
                clearBtn.show();
            } else {
                clearBtn.hide();
            }
            
            filterPhoneNumbers();
        });
        
        // Clear search button handler
        $('#clearSearchBtn').click(function() {
            $('#phoneSearchInput').val('').focus();
            $(this).hide();
            filterPhoneNumbers();
        });
        
        // Status filter handlers
        $('input[name="statusFilter"]').change(function() {
            filterPhoneNumbers();
        });
        
        // Clear all filters button handler
        $('#clearAllFiltersBtn').click(function() {
            $('#phoneSearchInput').val('');
            $('#clearSearchBtn').hide();
            $('input[name="statusFilter"][value="all"]').prop('checked', true);
            filterPhoneNumbers();
        });
        
        function filterPhoneNumbers() {
            const searchTerm = String($('#phoneSearchInput').val() || '').toLowerCase().trim();
            const statusFilter = $('input[name="statusFilter"]:checked').val();
            let visibleCount = 0;
            
            console.log('filterPhoneNumbers called with searchTerm:', searchTerm, 'statusFilter:', statusFilter);
            
            allPhoneRows.each(function() {
                const $row = $(this);
                
                try {
                    // Safely get data attributes with proper type conversion
                    const phoneNumberRaw = $row.data('phone-number');
                    const labelRaw = $row.data('label');
                    const statusRaw = $row.data('status');
                    
                    console.log('Row data - phoneNumberRaw:', phoneNumberRaw, 'labelRaw:', labelRaw, 'statusRaw:', statusRaw);
                    
                    const phoneNumber = phoneNumberRaw ? String(phoneNumberRaw) : '';
                    const label = labelRaw ? String(labelRaw).toLowerCase() : '';
                    const status = statusRaw ? String(statusRaw) : '';
                    const isPrimary = $row.data('is-primary') === 'true';
                    
                    let matchesSearch = true;
                    let matchesStatus = true;
                
                // Check search term match
                if (searchTerm.length > 0) {
                    matchesSearch = phoneNumber.includes(searchTerm) || 
                                  label.includes(searchTerm) ||
                                  status.includes(searchTerm) ||
                                  (isPrimary && 'primary'.includes(searchTerm));
                }
                
                // Check status filter match
                if (statusFilter !== 'all') {
                    if (statusFilter === 'verified') {
                        matchesStatus = status === 'verified';
                    } else if (statusFilter === 'unverified') {
                        matchesStatus = status === 'unverified';
                    } else if (statusFilter === 'primary') {
                        matchesStatus = isPrimary;
                    }
                }
                
                    // Show/hide row based on filters
                    if (matchesSearch && matchesStatus) {
                        $row.show();
                        visibleCount++;
                    } else {
                        $row.hide();
                    }
                } catch (error) {
                    console.error('Error processing row:', error, 'Row data:', $row.data());
                    // Hide the problematic row
                    $row.hide();
                }
            });
            
            // Update results count
            updateResultsCount(visibleCount);
            
            // Show/hide no results message
            if (visibleCount === 0) {
                $('#noResultsMessage').show();
                $('.table-responsive').hide();
            } else {
                $('#noResultsMessage').hide();
                $('.table-responsive').show();
            }
        }
        
        function updateResultsCount(count) {
            const $countElement = $('#searchResultsCount');
            if (count === totalCount) {
                $countElement.text(`Showing ${count} numbers`);
            } else {
                $countElement.text(`Showing ${count} of ${totalCount} numbers`);
            }
        }
    </script>
<?php include('../../../../components/scripts.php')?>
</body>
</html>