<?php
session_start();
require '../../../vendor/autoload.php';

// Set timezone to ensure consistent time handling
date_default_timezone_set('Asia/Manila');

// Load security functions
require_once __DIR__ . '/security_functions.php';

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
            // Check if user already has maximum allowed phone numbers (10)
            $currentCount = $this->countUserPhones($userId);
            if ($currentCount >= 10) {
                $this->db->rollBack();
                return ['success' => false, 'error' => 'Maximum limit of 10 phone numbers reached. Please delete a phone number before adding a new one.'];
            }
            
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

    public function countUserPhones($userId) {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM user_phone_numbers WHERE user_id = :user_id");
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchColumn();
    }

    private function sendVerificationSMS($phoneNumber, $code) {
        // Validate SMS API credentials before attempting to send
        if (empty($this->apiKey)) {
            $errorMsg = 'SMS API key not configured. Please contact administrator.';
            error_log("SMS Error: $errorMsg");
            return [false, $errorMsg];
        }
        
        if (empty($this->device)) {
            $errorMsg = 'SMS device ID not configured. Please contact administrator.';
            error_log("SMS Error: $errorMsg");
            return [false, $errorMsg];
        }
        
        if (empty($this->smsUrl)) {
            $errorMsg = 'SMS API URL not configured. Please contact administrator.';
            error_log("SMS Error: $errorMsg");
            return [false, $errorMsg];
        }
        
        // Trim API key and device to ensure no whitespace issues
        $apiKey = trim($this->apiKey);
        $device = trim($this->device);
        
        // Remove any remaining quotes or special characters
        $apiKey = trim($apiKey, " \t\n\r\0\x0B\"'");
        $device = trim($device, " \t\n\r\0\x0B\"'");
        
        // Validate API key is not empty after trimming
        if (empty($apiKey)) {
            $errorMsg = 'SMS API key is empty. Please configure SMS_API_KEY in .env file.';
            error_log("SMS Error: $errorMsg");
            return [false, $errorMsg];
        }
        
        // Debug logging (remove in production if needed)
        error_log("SMS Debug: API Key: " . substr($apiKey, 0, 10) . "... (length: " . strlen($apiKey) . "), Device: $device, URL: $this->smsUrl");
        
        $params = [
            'message' => "Your verification code is: $code. Valid for 15 minutes.",
            'mobile_number' => $phoneNumber,
            'device' => $device
        ];

        // Try multiple header formats - different parts of the codebase use different formats
        // Format 1: As used in device/sms.php (no space after colon, no Content-Type) - THIS IS THE WORKING FORMAT
        // Format 2: As used in device/smokestore.php (with space after colon and Content-Type)
        $headerFormats = [
            ['apikey:' . $apiKey],  // device/sms.php format - try this first as it's known to work
            ["Content-Type: application/x-www-form-urlencoded", "apikey: $apiKey"]  // smokestore.php format
        ];
        
        $response = false;
        $httpCode = 0;
        $error = '';
        $curlErrno = 0;
        $lastError = '';
        
        // Try each header format
        foreach ($headerFormats as $formatIndex => $headers) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->smsUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            $curlErrno = curl_errno($ch);
            curl_close($ch);
            
            // Log the attempt
            error_log("SMS Request Debug (Format " . ($formatIndex + 1) . "): URL=$this->smsUrl, HTTP=$httpCode, Headers=" . json_encode($headers) . ", Response=" . substr($response ?: '', 0, 200));
            
            // If we got a successful response, break
            if ($httpCode == 200 && $response !== false) {
                $json = json_decode($response, true);
                if (isset($json['success']) && $json['success']) {
                    error_log("SMS: Success with header format #" . ($formatIndex + 1));
                    break; // Success!
                }
                // If we got 422 (header format issue), try next format
                if (isset($json['code']) && $json['code'] == 422) {
                    error_log("SMS: Header format #" . ($formatIndex + 1) . " not recognized (422), trying next format...");
                    $lastError = $response;
                    continue;
                }
                // For 406 (API key mismatch), don't retry - the key is wrong
                if (isset($json['code']) && $json['code'] == 406) {
                    $lastError = $response;
                    break; // API key is wrong, no point trying other formats
                }
            }
            
            // If connection error, don't retry
            if ($curlErrno !== 0) {
                break;
            }
            
            $lastError = $response;
        }
        
        // Use the response from the loop, or lastError if no response
        if ($response === false || empty($response)) {
            $response = $lastError;
        }

        // Ensure log directory exists
        $logDir = __DIR__ . '/../';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        // Log the SMS attempt with more details
        $logResponse = $response ?: $lastError ?: '';
        $logEntry = date('Y-m-d H:i:s') . " | Phone: $phoneNumber | Code: $code | HTTP: $httpCode | Response: " . substr($logResponse, 0, 500) . " | Error: $error | Curl Errno: $curlErrno | API Key: " . substr($apiKey, 0, 10) . "... | Device: $device\n";
        @file_put_contents($logDir . 'sms_log.txt', $logEntry, FILE_APPEND);

        // Check for cURL errors first
        if ($curlErrno !== 0) {
            $errorMsg = 'Network connection error';
            if ($curlErrno === CURLE_COULDNT_CONNECT) {
                $errorMsg = 'Could not connect to SMS service. Please check your internet connection.';
            } elseif ($curlErrno === CURLE_OPERATION_TIMEOUTED) {
                $errorMsg = 'SMS service timeout. Please try again later.';
            } elseif ($error) {
                $errorMsg = 'Network error: ' . $error;
            }
            error_log("SMS cURL Error ($curlErrno): $errorMsg for $phoneNumber");
            return [false, $errorMsg];
        }
        
        if ($error) {
            error_log("SMS cURL Error: $error for $phoneNumber");
            return [false, 'Network error: ' . $error];
        }

        // Check if we got a response
        if ($response === false || empty($response)) {
            $errorMsg = 'No response from SMS service. Please try again later.';
            error_log("SMS Error: $errorMsg for $phoneNumber (HTTP: $httpCode)");
            return [false, $errorMsg];
        }

        // Try to parse response for more details
        $json = json_decode($response, true);
        
        // Handle different HTTP status codes
        if ($httpCode == 200) {
            if (isset($json['success']) && $json['success']) {
                error_log("SMS sent successfully to $phoneNumber. Response: " . substr($response, 0, 200));
                return [true, null];
            } else {
                // API returned 200 but success is false - parse the error message
                $errorMsg = 'SMS API error';
                
                // Check for errors array (common format: {"success":false,"code":422,"errors":["message"]})
                if (isset($json['errors']) && is_array($json['errors']) && !empty($json['errors'])) {
                    $errorMsg = implode(', ', $json['errors']);
                } elseif (isset($json['error'])) {
                    $errorMsg = is_array($json['error']) ? implode(', ', $json['error']) : $json['error'];
                } elseif (isset($json['message'])) {
                    $errorMsg = $json['message'];
                }
                
                // Map error codes to user-friendly messages
                if (isset($json['code'])) {
                    $errorCodes = [
                        406 => 'API key mismatch or not acceptable',
                        422 => 'API key must be provided in request header',
                        401 => 'Invalid API key',
                        403 => 'API access forbidden'
                    ];
                    if (isset($errorCodes[$json['code']])) {
                        $errorMsg = $errorCodes[$json['code']];
                    }
                }
                
                error_log("SMS API error for $phoneNumber: $errorMsg (HTTP: $httpCode, Code: " . (isset($json['code']) ? $json['code'] : 'N/A') . ", Response: " . substr($response, 0, 500) . ")");
                
                // Return user-friendly error message
                if (stripos($errorMsg, 'API key') !== false || stripos($errorMsg, 'authentication') !== false) {
                    // Check if we're using fallback config
                    $configSource = '';
                    $envPath = __DIR__ . '/../../../.env';
                    $deviceConfigPath = __DIR__ . '/../../../device/config.php';
                    $diagnosticUrl = '../diagnose_sms.php';
                    $errorCode = isset($json['code']) ? $json['code'] : 'Unknown';
                    
                    // Determine source of API key
                    $envApiKey = getEnvVar('SMS_API_KEY', '');
                    $usingEnv = !empty($envApiKey) && file_exists($envPath);
                    
                    if (!$usingEnv) {
                        if (file_exists($deviceConfigPath)) {
                            $configSource = '⚠️ The system is using a fallback API key from device/config.php, which is invalid or expired.';
                        } else {
                            $configSource = '❌ No API key is configured.';
                        }
                    } else {
                        $configSource = '⚠️ The API key in your .env file (lines 76-80) is being rejected by PageNet.';
                    }
                    
                    // Build comprehensive error message
                    $errorMessage = 'SMS API authentication failed. Error Code: ' . $errorCode . ' (API Key Rejected).' . "\n\n";
                    $errorMessage .= $configSource . "\n\n";
                    
                    if ($errorCode == 406) {
                        $errorMessage .= '🔴 Error 406 means: "API Key mismatch" or "API key not acceptable".' . "\n";
                        $errorMessage .= 'This indicates your API key is invalid, expired, or has been revoked by PageNet.' . "\n\n";
                    }
                    
                    $errorMessage .= '📋 Action Required:' . "\n";
                    $errorMessage .= '1. Contact PageNet support to get a NEW, valid API key and device ID' . "\n";
                    
                    if ($usingEnv) {
                        $errorMessage .= '2. Update your .env file (lines 76-80) with the new credentials:' . "\n";
                        $errorMessage .= '   SMS_API_KEY=new_api_key_from_pagenet' . "\n";
                        $errorMessage .= '   SMS_DEVICE_ID=new_device_id_from_pagenet' . "\n";
                    } else {
                        $errorMessage .= '2. Add SMS credentials to your .env file (create if needed, lines 76-80):' . "\n";
                        $errorMessage .= '   SMS_API_KEY=new_api_key_from_pagenet' . "\n";
                        $errorMessage .= '   SMS_DEVICE_ID=new_device_id_from_pagenet' . "\n";
                        $errorMessage .= '   SMS_API_URL=https://sms.pagenet.info/api/v1/sms/send' . "\n";
                    }
                    
                    $errorMessage .= '3. Test your credentials using the diagnostic tool: ' . $diagnosticUrl . "\n";
                    $errorMessage .= '4. Refresh this page and try again' . "\n\n";
                    $errorMessage .= '💡 Tip: Make sure there are NO quotes around values and NO spaces before/after the = sign.';
                    
                    return [false, $errorMessage];
                }
                
                return [false, $errorMsg ?: 'SMS API returned error'];
            }
        } elseif ($httpCode == 401) {
            $errorMsg = 'SMS API authentication failed. Invalid API key.';
            error_log("SMS API authentication error for $phoneNumber (HTTP: $httpCode, Response: " . substr($response, 0, 200) . ")");
            return [false, $errorMsg];
        } elseif ($httpCode == 403) {
            $errorMsg = 'SMS API access forbidden. Please check API credentials.';
            error_log("SMS API forbidden for $phoneNumber (HTTP: $httpCode, Response: " . substr($response, 0, 200) . ")");
            return [false, $errorMsg];
        } elseif ($httpCode == 404) {
            $errorMsg = 'SMS API endpoint not found. Please check API URL configuration.';
            error_log("SMS API endpoint not found (HTTP: $httpCode, URL: $this->smsUrl)");
            return [false, $errorMsg];
        } elseif ($httpCode >= 500) {
            $errorMsg = 'SMS service is temporarily unavailable. Please try again later.';
            error_log("SMS API server error for $phoneNumber (HTTP: $httpCode, Response: " . substr($response, 0, 200) . ")");
            return [false, $errorMsg];
        } else {
            // Other HTTP error codes
            $msg = isset($json['message']) ? $json['message'] : 
                   (isset($json['error']) ? $json['error'] : 
                   "SMS API error (HTTP $httpCode)");
            error_log("SMS API error for $phoneNumber: $msg (HTTP: $httpCode, Response: " . substr($response, 0, 200) . ")");
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
    // Initialize error handling for better debugging
    error_reporting(E_ALL);
    ini_set('display_errors', '0'); // Don't display errors in production
    ini_set('log_errors', '1');
    
    // Try to get database connection with better error handling
    try {
        $db = getDatabaseConnection();
        if (!$db) {
            throw new Exception('Database connection returned null');
        }
    } catch (Exception $e) {
        error_log("CRITICAL: Database connection failed in UserPhone.php: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        
        // Show user-friendly error message
        http_response_code(500);
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            // AJAX request - return JSON
            header('Content-Type: application/json');
            die(json_encode([
                'success' => false,
                'error' => 'Database connection failed. Please contact support.',
                'message' => 'System error. Please try again later.'
            ]));
        } else {
            // Regular request - show error page
            die('<!DOCTYPE html><html><head><title>System Error</title></head><body><h1>System Temporarily Unavailable</h1><p>We are experiencing technical difficulties. Please try again later or contact support.</p></body></html>');
        }
    }
    
    // Check if user_phone_numbers table exists, create if not
    try {
        $tableCheck = $db->query("SHOW TABLES LIKE 'user_phone_numbers'");
        if (!$tableCheck) {
            throw new Exception('Failed to check if user_phone_numbers table exists');
        }
    } catch (PDOException $e) {
        error_log("Error checking user_phone_numbers table: " . $e->getMessage());
        http_response_code(500);
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            die(json_encode(['success' => false, 'error' => 'Database error. Please contact support.']));
        } else {
            die('<!DOCTYPE html><html><head><title>System Error</title></head><body><h1>System Temporarily Unavailable</h1><p>Database error. Please try again later.</p></body></html>');
        }
    }
    
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
    
    // Check SMS configuration and set error/warning if not configured
    $smsConfigError = null;
    if (!$config['is_configured']) {
        $smsConfigError = [
            'title' => 'SMS Service Not Configured',
            'message' => 'The SMS service is not properly configured. Please check your .env file.',
            'errors' => $config['errors']
        ];
        error_log("SMS Configuration Error on UserPhone.php: " . implode(', ', $config['errors']));
    }
    
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
        
        // Validate CSRF token for all POST actions (except check_phone which is GET-like)
        $action = $_POST['action'] ?? null;
        if ($action && $action !== 'check_phone') {
            if (!validateCSRFToken()) {
                http_response_code(403);
                echo json_encode(['error' => 'Invalid security token. Please refresh the page and try again.']);
                exit();
            }
        }
        
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'check_phone':
                    // Rate limiting for phone checks
                    if (!checkRateLimit('phone_check', 10, 60)) {
                        $response = ['valid' => false, 'message' => 'Too many requests. Please wait a moment and try again.'];
                        break;
                    }
                    
                    $phoneNumber = sanitizeInput($_POST['phone_number'] ?? '', 'phone');
                    if (!$phoneNumber || !validatePhoneNumber($phoneNumber)) {
                        $response = ['valid' => false, 'message' => 'Invalid Philippine phone number. Must be exactly 11 digits starting with 09.'];
                    } elseif ($phoneModel->phoneNumberExists($phoneNumber)) {
                        $response = ['valid' => false, 'message' => 'This phone number is already registered.'];
                    } else {
                        $response = ['valid' => true];
                    }
                    break;
                    
                case 'verify_code':
                    // Rate limiting for verification attempts
                    if (!checkRateLimit('verify_code', 5, 300)) {
                        $response = ['success' => false, 'message' => 'Too many verification attempts. Please wait 5 minutes and try again.'];
                        break;
                    }
                    
                    $phoneId = validateInteger($_POST['phone_id'] ?? null, 1);
                    $code = sanitizeInput($_POST['code'] ?? '', 'verification_code');
                    
                    if (!$phoneId || !$code) {
                        $response = ['success' => false, 'message' => 'Invalid input. Please check your verification code.'];
                    } else {
                        $result = $phoneModel->verifyPhoneNumber($userId, $phoneId, $code);
                        $response = $result;
                    }
                    break;
                    
                case 'resend_code':
                    // Rate limiting for resend attempts
                    if (!checkRateLimit('resend_code', 3, 300)) {
                        $response = ['success' => false, 'message' => 'Too many resend requests. Please wait 5 minutes and try again.'];
                        break;
                    }
                    
                    $phoneId = validateInteger($_POST['phone_id'] ?? null, 1);
                    if (!$phoneId) {
                        $response = ['success' => false, 'message' => 'Invalid phone number ID.'];
                    } else {
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
                    }
                    break;
                    
                case 'update_label':
                    $phoneId = validateInteger($_POST['phone_id'] ?? null, 1);
                    $label = validateLabel($_POST['label'] ?? '');
                    
                    if (!$phoneId) {
                        $response = ['success' => false, 'message' => 'Invalid phone number ID.'];
                    } elseif ($label === false) {
                        $response = ['success' => false, 'message' => 'Invalid label format. Label must be 100 characters or less and contain only letters, numbers, spaces, and common punctuation.'];
                    } else {
                        if ($phoneModel->updatePhoneLabel($userId, $phoneId, $label)) {
                            $response = ['success' => true, 'message' => 'Label updated successfully!'];
                        } else {
                            $response = ['success' => false, 'message' => 'Failed to update label.'];
                        }
                    }
                    break;
                    
                case 'delete_phone':
                    $phoneId = validateInteger($_POST['phone_id'] ?? null, 1);
                    if (!$phoneId) {
                        $response = ['success' => false, 'error' => 'Invalid phone number ID.'];
                    } else {
                        $result = $phoneModel->deletePhoneNumber($userId, $phoneId);
                        $response = $result;
                    }
                    break;
                    
                case 'debug_status':
                    $phoneId = validateInteger($_POST['phone_id'] ?? null, 1);
                    if (!$phoneId) {
                        $response = ['success' => false, 'error' => 'Invalid phone number ID.'];
                    } else {
                        $status = $phoneModel->getPhoneVerificationStatus($userId, $phoneId);
                        $response = ['success' => true, 'status' => $status];
                    }
                    break;
                    
                case 'fix_status':
                    $phoneId = validateInteger($_POST['phone_id'] ?? null, 1);
                    if (!$phoneId) {
                        $response = ['success' => false, 'message' => 'Invalid phone number ID.'];
                    } else {
                        $result = $phoneModel->fixVerificationStatus($userId, $phoneId);
                        $response = $result;
                    }
                    break;
                    
                case 'reset_all_verification':
                    // Additional security check for this sensitive action
                    if (!checkRateLimit('reset_verification', 1, 3600)) {
                        $response = ['success' => false, 'message' => 'This action can only be performed once per hour.'];
                    } else {
                        $result = $phoneModel->resetAllVerificationStatus($userId);
                        $response = $result;
                    }
                    break;
                    
                default:
                    $response = ['error' => 'Invalid action.'];
            }
        }
        
        // Add CSRF token to response for next request
        $response['csrf_token'] = generateCSRFToken();
        
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }
    
    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Validate CSRF token for all form submissions
        if (!validateCSRFToken()) {
            logSecurityEvent('CSRF_TOKEN_INVALID', 'Form submission without valid CSRF token', 'high');
            $_SESSION['error'] = "Security validation failed. Please refresh the page and try again.";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
        
        if (isset($_POST['add_phone'])) {
            // Rate limiting
            if (!checkRateLimit('add_phone', 5, 300)) {
                $_SESSION['error'] = "Too many requests. Please wait a moment and try again.";
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
            }
            
            $phoneNumber = sanitizeInput($_POST['phone_number'] ?? '', 'phone');
            $isPrimary = isset($_POST['is_primary']) && $_POST['is_primary'] === 'on';
            $label = validateLabel($_POST['label'] ?? '');
            
            // Validate phone number
            if (!$phoneNumber || !validatePhoneNumber($phoneNumber)) {
                $_SESSION['error'] = "Invalid Philippine phone number format. Must be exactly 11 digits starting with 09.";
            } elseif ($label === false) {
                $_SESSION['error'] = "Invalid label format. Label must be 100 characters or less.";
            } elseif ($phoneModel->phoneNumberExists($phoneNumber)) {
                $_SESSION['error'] = "This phone number is already registered.";
            } elseif ($phoneModel->countUserPhones($userId) >= 10) {
                $_SESSION['error'] = "Maximum limit of 10 phone numbers reached. Please delete a phone number before adding a new one.";
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
            $phoneId = validateInteger($_POST['phone_id'] ?? null, 1);
            
            if (!$phoneId) {
                $_SESSION['error'] = "Invalid phone number selected.";
            } elseif ($phoneModel->verifyPhoneOwnership($userId, $phoneId)) {
                if ($phoneModel->setPrimaryPhone($userId, $phoneId)) {
                    $_SESSION['success'] = "Primary phone number updated!";
                } else {
                    $_SESSION['error'] = "Failed to update primary phone number.";
                }
            } else {
                $_SESSION['error'] = "Invalid phone number selected.";
            }
        } elseif (isset($_POST['verify_phone'])) {
            // Rate limiting for verification attempts
            if (!checkRateLimit('verify_phone', 5, 300)) {
                $_SESSION['error'] = "Too many verification attempts. Please wait 5 minutes and try again.";
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
            }
            
            $phoneId = validateInteger($_POST['phone_id'] ?? null, 1);
            $code = sanitizeInput($_POST['verification_code'] ?? '', 'verification_code');
            
            if (!$phoneId || !$code) {
                $_SESSION['error'] = "Invalid verification code. Please enter a 6-digit code.";
            } else {
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
            }
        } elseif (isset($_POST['resend_code'])) {
            // Rate limiting for resend attempts
            if (!checkRateLimit('resend_code', 3, 300)) {
                $_SESSION['error'] = "Too many resend requests. Please wait 5 minutes and try again.";
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
            }
            
            $phoneId = validateInteger($_POST['phone_id'] ?? null, 1);
            
            if (!$phoneId) {
                $_SESSION['error'] = "Invalid phone number ID.";
            } else {
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
            }
        } elseif (isset($_POST['update_label'])) {
            $phoneId = validateInteger($_POST['phone_id'] ?? null, 1);
            $label = validateLabel($_POST['label'] ?? '');
            
            if (!$phoneId) {
                $_SESSION['error'] = "Invalid phone number selected.";
            } elseif ($label === false) {
                $_SESSION['error'] = "Invalid label format. Label must be 100 characters or less.";
            } elseif ($phoneModel->verifyPhoneOwnership($userId, $phoneId)) {
                if ($phoneModel->updatePhoneLabel($userId, $phoneId, $label)) {
                    $_SESSION['success'] = "Phone label updated successfully!";
                } else {
                    $_SESSION['error'] = "Failed to update phone label.";
                }
            } else {
                $_SESSION['error'] = "Invalid phone number selected.";
            }
        }
        
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    
    // Generate CSRF token for forms
    $csrfToken = generateCSRFToken();
    
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
        <i class="fa fa-plus"></i>
    </a>

    <!-- Toast Notifications -->
    <div class="toast-container">
        <?php if (isset($error)): ?>
            <div class="toast show align-items-center text-white bg-danger border-0" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="fa fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (isset($success)): ?>
            <div class="toast show align-items-center text-white bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="fa fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Gentelella Style Panel -->
    <div class="x_panel">
        <div class="x_title">
            <h2><i class="fa fa-phone"></i> My Phone Numbers <small>Manage your registered phone numbers</small></h2>
            <ul class="nav navbar-right panel_toolbox">
                <li>
                    <button class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#smsLogModal" title="View SMS Log">
                        <i class="fa fa-file-text-o"></i> SMS Log
                    </button>
                </li>
                <li>
                    <button class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#helpModal">
                        <i class="fa fa-question-circle"></i> Help
                    </button>
                </li>
                <li>
                    <button class="btn btn-warning btn-sm" id="resetAllVerificationBtn">
                        <i class="fa fa-refresh"></i> Reset All Verification
                    </button>
                </li>
                <li>
                    <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addPhoneModal">
                        <i class="fa fa-plus"></i> Add Number
                    </button>
                </li>
                <li><a class="collapse-link"><i class="fa fa-chevron-up"></i></a></li>
            </ul>
            <div class="clearfix"></div>
        </div>
        <div class="x_content">
            <!-- SMS Configuration Error Alert -->
            <?php if (isset($smsConfigError) && $smsConfigError): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <h4 class="alert-heading"><i class="fa fa-exclamation-triangle"></i> <?php echo htmlspecialchars($smsConfigError['title']); ?></h4>
                    <p class="mb-2"><?php echo htmlspecialchars($smsConfigError['message']); ?></p>
                    <hr>
                    <p class="mb-2"><strong>Missing Configuration:</strong></p>
                    <ul class="mb-0">
                        <?php foreach ($smsConfigError['errors'] as $error): ?>
                            <li><code><?php echo htmlspecialchars($error); ?></code></li>
                        <?php endforeach; ?>
                    </ul>
                    <hr>
                    <p class="mb-0"><small><strong>To fix this:</strong> Add the required SMS API credentials to your <code>.env</code> file in the root directory:</small></p>
                    <pre class="bg-dark text-white p-2 mt-2 rounded"><code>SMS_API_KEY=your_api_key_here
SMS_DEVICE_ID=your_device_id_here
SMS_API_URL=https://sms.pagenet.info/api/v1/sms/send</code></pre>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if (empty($phoneNumbers)): ?>
                <div class="text-center py-5">
                    <div class="bg-light bg-opacity-50 p-4 rounded-circle d-inline-block mb-3">
                        <i class="fa fa-phone text-muted" style="font-size: 3rem;"></i>
                    </div>
                    <h4 class="text-dark fw-semibold mb-2">No Phone Numbers Found</h4>
                    <p class="text-muted mb-4">Add your first phone number to get started with phone management</p>
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addPhoneModal">
                        <i class="fa fa-plus"></i> Add Phone Number
                    </button>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table id="datatable" class="table table-striped table-bordered jambo_table bulk_action" style="width:100%">
                        <thead>
                            <tr class="headings">
                                <th class="column-title">Phone Number</th>
                                <th class="column-title">Label</th>
                                <th class="column-title">Status</th>
                                <th class="column-title no-link last"><span class="nobr">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($phoneNumbers as $phone): ?>
                                <tr class="even pointer phone-item <?php echo $phone['is_primary'] ? 'primary' : ''; ?>"
                                    data-phone-id="<?php echo $phone['phone_id']; ?>"
                                    data-phone-number="<?php echo htmlspecialchars($phone['phone_number'] ?? ''); ?>"
                                    data-label="<?php echo htmlspecialchars($phone['label'] ?? ''); ?>"
                                    data-status="<?php echo $phone['verified'] ? 'verified' : 'unverified'; ?>"
                                    data-is-primary="<?php echo $phone['is_primary'] ? 'true' : 'false'; ?>"
                                    data-verified="<?php echo $phone['verified'] ? '1' : '0'; ?>">
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <i class="fa fa-phone text-primary me-2"></i>
                                            <div>
                                                <span class="phone-number-display fw-semibold"><?php echo htmlspecialchars($phone['phone_number']); ?></span>
                                                <?php if ($phone['is_primary']): ?>
                                                    <div class="mt-1">
                                                        <span class="badge bg-success" style="font-size: 0.7rem;">
                                                            <i class="fa fa-star"></i> Primary
                                                        </span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <span class="phone-label-text"><?php echo htmlspecialchars($phone['label'] ?? 'No label'); ?></span>
                                            <button class="btn btn-link btn-sm p-0 ms-2 edit-label-btn" 
                                                   data-phone-id="<?php echo $phone['phone_id']; ?>"
                                                   data-current-label="<?php echo htmlspecialchars($phone['label'] ?? ''); ?>"
                                                   style="color: #6c757d; text-decoration: none;">
                                                <i class="fa fa-pencil"></i>
                                            </button>
                                            <input type="text" class="form-control form-control-sm label-input border-0 bg-light" 
                                                   data-phone-id="<?php echo $phone['phone_id']; ?>"
                                                   value="<?php echo htmlspecialchars($phone['label'] ?? ''); ?>"
                                                   style="display: none; max-width: 150px;">
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($phone['verified']): ?>
                                            <span class="badge bg-success">
                                                <i class="fa fa-check-circle"></i> Verified
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">
                                                <i class="fa fa-exclamation-triangle"></i> Unverified
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="last">
                                        <div class="d-flex gap-1 flex-wrap">
                                            <?php if (!$phone['is_primary'] && $phone['verified']): ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?php echo escapeOutput($csrfToken); ?>">
                                                    <input type="hidden" name="phone_id" value="<?php echo escapeOutput($phone['phone_id']); ?>">
                                                    <button type="submit" name="set_primary" class="btn btn-sm btn-success">
                                                        <i class="fa fa-star"></i> Primary
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <?php if (!$phone['verified']): ?>
                                                <button type="button" class="btn btn-sm btn-warning verify-btn" 
                                                        data-phone-id="<?php echo $phone['phone_id']; ?>">
                                                    <i class="fa fa-shield"></i> Verify
                                                </button>
                                                <button type="button" class="btn btn-sm btn-info resend-btn" 
                                                        data-phone-id="<?php echo $phone['phone_id']; ?>">
                                                    <i class="fa fa-refresh"></i> Resend
                                                </button>
                                            <?php endif; ?>
                                            
                                            <button type="button" class="btn btn-sm btn-danger delete-btn" 
                                                    data-phone-id="<?php echo $phone['phone_id']; ?>"
                                                    data-phone-number="<?php echo htmlspecialchars($phone['phone_number']); ?>">
                                                <i class="fa fa-trash"></i> Delete
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add Phone Modal - Gentelella Form Validation Style -->
    <div class="modal fade" id="addPhoneModal" tabindex="-1" aria-labelledby="addPhoneModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title" id="addPhoneModalLabel">
                        <i class="fa fa-phone"></i> Add New Phone Number
                    </h4>
                    <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form id="addPhoneForm" method="POST" class="form-horizontal form-label-left" data-parsley-validate>
                    <input type="hidden" name="csrf_token" value="<?php echo escapeOutput($csrfToken); ?>">
                    <div class="modal-body">
                        <div class="item form-group">
                            <label class="col-form-label col-md-3 col-sm-3 label-align" for="phone_number">
                                Philippine Mobile Number <span class="required">*</span>
                            </label>
                            <div class="col-md-9 col-sm-9">
                                <div class="input-group">
                                    <span class="input-group-addon"></span>
                                    <input type="text" 
                                           id="phone_number" 
                                           name="phone_number" 
                                           required="required" 
                                           class="form-control has-feedback-left text-start"
                                           placeholder="Enter Phone Number"
                                           data-parsley-pattern="^09[0-9]{9}$"
                                           data-parsley-pattern-message="Must be exactly 11 digits starting with 09"
                                           data-parsley-trigger="change"
                                           data-parsley-errors-container="#phoneValidationMessage"
                                           maxlength="11">
                                    <span class="fa fa-phone form-control-feedback left" aria-hidden="true"></span>
                                </div>
                                <div id="phoneValidationMessage" class="mt-1">
                                    <!-- <small class="form-text small text-muted validation-text">
                                        Must be exactly 11 digits starting with 09
                                    </small> -->
                                </div>
                            </div>
                        </div>
                        
                        <div class="item form-group">
                            <label class="col-form-label col-md-3 col-sm-3 label-align" for="label">
                                Label <span class="optional">(Optional)</span>
                            </label>
                            <div class="col-md-9 col-sm-9">
                                <input type="text" 
                                       id="label" 
                                       name="label" 
                                       class="form-control has-feedback-left"
                                       placeholder="Work, Personal, Home...">
                                <span class="fa fa-tag form-control-feedback left" aria-hidden="true"></span>
                                <!-- <small class="form-text text-muted">Helps you identify this number's purpose</small> -->
                            </div>
                        </div>
                        
                        <div class="item form-group">
                            <label class="col-form-label col-md-3 col-sm-3 label-align"></label>
                            <div class="col-md-9 col-sm-9">
                                <div class="checkbox">
                                    <label>
                                        <input type="checkbox" name="is_primary" id="is_primary" class="flat"> 
                                        <i class="fa fa-star"></i> Set as Primary Number
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="alert alert-info alert-dismissible fade in" role="alert">
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">×</span>
                            </button>
                            <strong><i class="fa fa-info-circle"></i> Note:</strong> A verification code will be sent via SMS to confirm ownership of this number.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fa fa-times"></i> Cancel
                        </button>
                        <button type="submit" name="add_phone" id="addPhoneBtn" class="btn btn-success">
                            <i class="fa fa-plus"></i> Add Number
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Verification Modal - Gentelella Form Validation Style -->
    <div class="modal fade" id="verificationModal" tabindex="-1" aria-labelledby="verificationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title" id="verificationModalLabel">
                        <i class="fa fa-shield"></i> Verify Phone Number
                    </h4>
                    <button type="button" class="btn-close" id="closeVerificationModal" aria-label="Close"></button>
                </div>
                <form id="verifyForm" method="POST" class="form-horizontal form-label-left" data-parsley-validate>
                    <input type="hidden" name="csrf_token" value="<?php echo escapeOutput($csrfToken); ?>">
                    <div class="modal-body" style="padding: 1rem;">
                        <div class="alert alert-info alert-dismissible fade in mb-2 py-2" role="alert" style="margin-bottom: 0.75rem !important; padding: 0.5rem 1rem !important;">
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">×</span>
                            </button>
                            <strong><i class="fa fa-info-circle"></i> Enter the 6-digit verification code</strong> sent to your phone number.
                        </div>
                        
                        <input type="hidden" name="phone_id" id="modalPhoneId" value="">
                        
                        <div class="item form-group mb-2" style="margin-bottom: 0.5rem !important;">
                            <label class="form-label fw-bold mb-1" for="verification_code" style="margin-bottom: 0.25rem !important; font-size: 1.25rem !important; font-weight: 700 !important;">
                                Verification Code <span class="required">*</span>
                            </label>
                            <div class="position-relative">
                                <input type="text" 
                                       id="verification_code" 
                                       name="verification_code" 
                                       required="required" 
                                       class="form-control has-feedback-left verification-code-input text-center"
                                       data-parsley-pattern="^[0-9]{6}$"
                                       data-parsley-pattern-message="Please enter a valid 6-digit code"
                                       data-parsley-trigger="change"
                                       maxlength="6" 
                                       autocomplete="off" 
                                       placeholder="000000"
                                       style="font-size: 1.5rem; letter-spacing: 0.5rem;">
                                <span class="fa fa-key form-control-feedback left" aria-hidden="true"></span>
                                <div class="text-end mt-1" style="margin-top: 0.25rem !important;">
                                    <small class="text-muted" id="countdown" style="font-size: 0.8rem;">Code expires in 15:00</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" id="cancelVerificationBtn">
                            <i class="fa fa-times"></i> Cancel
                        </button>
                        <button type="button" class="btn btn-info" id="resendFromModal">
                            <i class="fa fa-refresh"></i> Resend Code
                        </button>
                        <button type="submit" name="verify_phone" class="btn btn-success">
                            <i class="fa fa-check"></i> Verify
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- SMS Log Viewer Modal -->
    <div class="modal fade" id="smsLogModal" tabindex="-1" aria-labelledby="smsLogModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title" id="smsLogModalLabel">
                        <i class="fa fa-file-text-o"></i> SMS Log Viewer
                    </h4>
                    <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <?php
                    // Read and display SMS log file
                    $logFile = __DIR__ . '/../sms_log.txt';
                    if (file_exists($logFile)) {
                        $logLines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                        $logLines = array_reverse($logLines); // Show newest first
                        $recentLogs = array_slice($logLines, 0, 20); // Show last 20 entries
                        
                        if (!empty($recentLogs)) {
                            echo '<div class="alert alert-info">';
                            echo '<i class="fa fa-info-circle"></i> Showing last 20 SMS attempts';
                            echo '</div>';
                            echo '<div style="max-height: 400px; overflow-y: auto;">';
                            echo '<table class="table table-striped table-bordered table-sm">';
                            echo '<thead><tr>';
                            echo '<th>Time</th>';
                            echo '<th>Phone</th>';
                            echo '<th>Code</th>';
                            echo '<th>Status</th>';
                            echo '<th>Error</th>';
                            echo '</tr></thead>';
                            echo '<tbody>';
                            
                            foreach ($recentLogs as $line) {
                                $parts = explode(' | ', $line);
                                if (count($parts) >= 5) {
                                    $time = $parts[0] ?? '';
                                    $phone = str_replace('Phone: ', '', $parts[1] ?? '');
                                    $code = str_replace('Code: ', '', $parts[2] ?? '');
                                    $http = str_replace('HTTP: ', '', $parts[3] ?? '');
                                    $response = str_replace('Response: ', '', $parts[4] ?? '');
                                    
                                    // Parse response to check for errors
                                    $hasError = false;
                                    $errorMsg = '';
                                    $responseData = json_decode($response, true);
                                    if ($responseData && isset($responseData['success']) && !$responseData['success']) {
                                        $hasError = true;
                                        if (isset($responseData['errors']) && is_array($responseData['errors'])) {
                                            $errorMsg = implode(', ', $responseData['errors']);
                                        }
                                    }
                                    
                                    $statusClass = $hasError ? 'danger' : 'success';
                                    $statusIcon = $hasError ? 'fa-times-circle' : 'fa-check-circle';
                                    $statusText = $hasError ? 'Failed' : 'Success';
                                    
                                    echo '<tr class="table-' . $statusClass . '">';
                                    echo '<td><small>' . htmlspecialchars($time) . '</small></td>';
                                    echo '<td><small>' . htmlspecialchars($phone) . '</small></td>';
                                    echo '<td><small><code>' . htmlspecialchars($code) . '</code></small></td>';
                                    echo '<td><small><i class="fa ' . $statusIcon . '"></i> ' . $statusText . '</small></td>';
                                    echo '<td><small>' . htmlspecialchars($errorMsg) . '</small></td>';
                                    echo '</tr>';
                                }
                            }
                            
                            echo '</tbody></table>';
                            echo '</div>';
                        } else {
                            echo '<div class="alert alert-info">';
                            echo '<i class="fa fa-info-circle"></i> No SMS log entries found.';
                            echo '</div>';
                        }
                    } else {
                        echo '<div class="alert alert-warning">';
                        echo '<i class="fa fa-exclamation-triangle"></i> SMS log file not found. It will be created when the first SMS is sent.';
                        echo '</div>';
                    }
                    ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Help Modal - Gentelella Style -->
    <div class="modal fade" id="helpModal" tabindex="-1" aria-labelledby="helpModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-md">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title" id="helpModalLabel">
                        <i class="fa fa-question-circle"></i> Phone Number Help
                    </h4>
                    <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body" style="max-height: 400px; overflow-y: auto;">
                    <!-- Adding Phone Number -->
                    <div class="mb-3">
                        <div class="d-flex align-items-center mb-2">
                            <i class="fa fa-plus-circle text-primary me-2"></i>
                            <h6 class="mb-0 text-dark fw-semibold">Adding Phone Numbers</h6>
                        </div>
                        <ul class="list-unstyled mb-0 small">
                            <li class="mb-1">
                                <i class="fa fa-check-circle text-success me-2" style="font-size: 0.8rem;"></i>
                                Enter 11-digit number starting with 09
                            </li>
                            <li class="mb-1">
                                <i class="fa fa-check-circle text-success me-2" style="font-size: 0.8rem;"></i>
                                Add optional label and set as primary
                            </li>
                            <li class="mb-1">
                                <i class="fa fa-check-circle text-success me-2" style="font-size: 0.8rem;"></i>
                                Verification code sent via SMS
                            </li>
                        </ul>
                    </div>

                    <!-- Verification Process -->
                    <div class="mb-3">
                        <div class="d-flex align-items-center mb-2">
                            <i class="fa fa-shield text-primary me-2"></i>
                            <h6 class="mb-0 text-dark fw-semibold">Verification Process</h6>
                        </div>
                        <ul class="list-unstyled mb-0 small">
                            <li class="mb-1">
                                <i class="fa fa-key text-info me-2" style="font-size: 0.8rem;"></i>
                                Enter 6-digit code from SMS
                            </li>
                            <li class="mb-1">
                                <i class="fa fa-clock-o text-warning me-2" style="font-size: 0.8rem;"></i>
                                Codes expire after 15 minutes
                            </li>
                            <li class="mb-1">
                                <i class="fa fa-refresh text-primary me-2" style="font-size: 0.8rem;"></i>
                                Request new code if needed
                            </li>
                        </ul>
                    </div>

                    <!-- Primary Number -->
                    <div class="mb-3">
                        <div class="d-flex align-items-center mb-2">
                            <i class="fa fa-star text-primary me-2"></i>
                            <h6 class="mb-0 text-dark fw-semibold">Primary Number</h6>
                        </div>
                        <ul class="list-unstyled mb-0 small">
                            <li class="mb-1">
                                <i class="fa fa-envelope text-info me-2" style="font-size: 0.8rem;"></i>
                                Used for important communications
                            </li>
                            <li class="mb-1">
                                <i class="fa fa-exchange text-primary me-2" style="font-size: 0.8rem;"></i>
                                Can be changed anytime
                            </li>
                            <li class="mb-1">
                                <i class="fa fa-lock text-success me-2" style="font-size: 0.8rem;"></i>
                                Must be verified
                            </li>
                        </ul>
                    </div>

                    <!-- Labels -->
                    <div class="mb-3">
                        <div class="d-flex align-items-center mb-2">
                            <i class="fa fa-tags text-primary me-2"></i>
                            <h6 class="mb-0 text-dark fw-semibold">Labels</h6>
                        </div>
                        <ul class="list-unstyled mb-0 small">
                            <li class="mb-1">
                                <i class="fa fa-pencil text-primary me-2" style="font-size: 0.8rem;"></i>
                                Click edit icon to add/change labels
                            </li>
                            <li class="mb-1">
                                <i class="fa fa-filter text-info me-2" style="font-size: 0.8rem;"></i>
                                Helps identify number purposes
                            </li>
                            <li class="mb-1">
                                <i class="fa fa-tag text-secondary me-2" style="font-size: 0.8rem;"></i>
                                Examples: "Work", "Personal", "Backup"
                            </li>
                        </ul>
                    </div>

                    <!-- Quick Tips -->
                    <div class="bg-light rounded p-2 mt-3">
                        <div class="d-flex align-items-center mb-1">
                            <i class="fa fa-lightbulb-o text-warning me-2"></i>
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
                <div class="modal-footer">
                    <button type="button" class="btn btn-success" data-bs-dismiss="modal">
                        <i class="fa fa-check"></i> Got it!
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- New Phone Added Modal - Gentelella Style -->
    <div class="modal fade" id="newPhoneModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title"><i class="fa fa-phone"></i> Verification Required</h4>
                    <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-4">
                        <i class="fa fa-comment text-primary" style="font-size: 3rem;"></i>
                    </div>
                    <p>A verification code has been sent to your phone number. Please verify it to complete the registration.</p>
                    <p>You can verify now or later from your phone numbers list.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fa fa-clock-o"></i> Verify Later
                    </button>
                    <button type="button" class="btn btn-success" id="verifyNowBtn">
                        <i class="fa fa-check"></i> Verify Now
                    </button>
                </div>
            </div>
        </div>
    </div>
                                                        

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <!-- Parsley.js for form validation (Gentelella style) -->
    <script src="https://cdn.jsdelivr.net/npm/parsleyjs@2.9.2/dist/parsley.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/parsleyjs@2.9.2/dist/i18n/en.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/parsleyjs@2.9.2/src/parsley.css">
    <script>
        // Force cache refresh
        console.log('UserPhone.js loaded at:', new Date().toISOString());
        
        // CSRF Token for AJAX requests
        var csrfToken = '<?php echo escapeOutput($csrfToken); ?>';
        
        // Helper function to safely get Parsley instance
        function getParsleyInstance($element) {
            if (!$element || !$element.length) return null;
            if (typeof window.Parsley === 'undefined') {
                console.warn('Parsley.js is not loaded');
                return null;
            }
            try {
                const parsley = $element.parsley();
                // Check if parsley instance is valid and has required methods
                if (parsley && typeof parsley === 'object' && typeof parsley.isValid === 'function') {
                    return parsley;
                }
                return null;
            } catch (e) {
                console.warn('Parsley not available for element:', e);
                return null;
            }
        }
    </script>
    <script>
        $(document).ready(function() {
            // Auto-close toasts after 5 seconds
            $('.toast').each(function() {
                setTimeout(() => {
                    $(this).toast('hide');
                }, 5000);
            });
            
            // Initialize DataTables - Gentelella Style (matching tables_dynamic.html)
            if ($('#datatable').length && typeof $.fn.DataTable !== 'undefined') {
                var phoneTable = $('#datatable').DataTable({
                    "processing": true,
                    "serverSide": false,
                    "responsive": true,
                    "autoWidth": false,
                    "pageLength": 10,
                    "lengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
                    "order": [[0, "asc"]],
                    "columnDefs": [
                        { "orderable": false, "targets": [3] }, // Actions column
                        { "className": "text-center", "targets": [2] } // Status column
                    ],
                    "language": {
                        "lengthMenu": "Show _MENU_ entries",
                        "search": "Search:",
                        "info": "Showing _START_ to _END_ of _TOTAL_ phone numbers",
                        "infoEmpty": "No phone numbers to display",
                        "infoFiltered": "(filtered from _MAX_ total phone numbers)",
                        "zeroRecords": "No matching phone numbers found",
                        "emptyTable": "No phone numbers available",
                        "processing": "Processing...",
                        "paginate": {
                            "first": "First",
                            "last": "Last",
                            "next": "Next",
                            "previous": "Previous"
                        }
                    },
                    "dom": '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
                           '<"row"<"col-sm-12"tr>>' +
                           '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
                    "initComplete": function() {
                        // Re-bind event handlers after table initialization
                        bindPhoneActions();
                    },
                    "drawCallback": function() {
                        // Re-bind event handlers after each table redraw
                        bindPhoneActions();
                    }
                });
            }
            
            // Show new phone modal if we just added a phone
            <?php if ($verifyingPhone && $newPhoneId): ?>
                const newPhoneModal = new bootstrap.Modal(document.getElementById('newPhoneModal'));
                newPhoneModal.show();
                
                // Handle verify now button
                $('#verifyNowBtn').click(function() {
                    newPhoneModal.hide();
                    $('#modalPhoneId').val(<?php echo $newPhoneId; ?>);
                    const verificationModal = new bootstrap.Modal(document.getElementById('verificationModal'));
                    verificationModal.show();
                    startCountdown();
                });
            <?php endif; ?>
            
            // Initialize Parsley.js for form validation (Gentelella style)
            if ($('#addPhoneForm').length && typeof window.Parsley !== 'undefined') {
                $('#addPhoneForm').parsley();
            }
            if ($('#verifyForm').length && typeof window.Parsley !== 'undefined') {
                $('#verifyForm').parsley();
            }
            
            const phoneValidationMessageContainer = $('#phoneValidationMessage');
            const phoneValidationMessageText = $('#phoneValidationMessage .validation-text');
            const validationStates = ['text-muted', 'text-success', 'text-danger', 'text-info'];
            
            function setPhoneValidationMessage(text, state = 'text-muted') {
                if (!phoneValidationMessageText.length) return;
                phoneValidationMessageText
                    .removeClass(validationStates.join(' '))
                    .addClass(state)
                    .text(text);
            }
            
            // Add custom Parsley validator for phone number existence check
            if (typeof window.Parsley !== 'undefined' && window.Parsley.addValidator) {
                window.Parsley.addValidator('phoneAvailable', {
                    validateString: function(value) {
                        // This will be handled via AJAX in the input event
                        return true; // Let AJAX handle the actual validation
                    },
                    priority: 32
                });
            }
            
            // Phone number input formatting and validation
            let phoneCheckTimeout;
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
                
                const phoneNumber = phoneInput.val().trim();
                
                const parsleyInstance = getParsleyInstance(phoneInput);
                
                if (!phoneNumber) {
                    setPhoneValidationMessage('Must be exactly 11 digits starting with 09');
                    if (parsleyInstance) {
                        parsleyInstance.removeError('phoneFormat');
                        parsleyInstance.removeError('phoneExists');
                        parsleyInstance.removeError('phoneCheck');
                    }
                    return;
                }
                
                if (!/^09\d{9}$/.test(phoneNumber)) {
                    setPhoneValidationMessage('Must be exactly 11 digits starting with 09', 'text-danger');
                    if (parsleyInstance) {
                        parsleyInstance.addError('phoneFormat', {message: 'Must be exactly 11 digits starting with 09'});
                        parsleyInstance.removeError('phoneExists');
                        parsleyInstance.removeError('phoneCheck');
                    }
                    return;
                }
                
                if (parsleyInstance) {
                    parsleyInstance.removeError('phoneFormat');
                    parsleyInstance.removeError('phoneCheck');
                }
                setPhoneValidationMessage('Checking availability...', 'text-info');
                
                // Clear previous timeout
                clearTimeout(phoneCheckTimeout);
                
                // Debounce AJAX check
                phoneCheckTimeout = setTimeout(function() {
                    $.ajax({
                        url: window.location.href,
                        method: 'POST',
                        data: {
                            action: 'check_phone',
                            phone_number: phoneNumber,
                            csrf_token: csrfToken
                        },
                        dataType: 'json',
                        success: function(response) {
                            const parsleyInst = getParsleyInstance(phoneInput);
                            if (!response.valid) {
                                // Add custom Parsley error
                                if (parsleyInst) {
                                    parsleyInst.addError('phoneExists', {message: response.message});
                                }
                                setPhoneValidationMessage(response.message, 'text-danger');
                            } else {
                                if (parsleyInst) {
                                    parsleyInst.removeError('phoneExists');
                                }
                                setPhoneValidationMessage('Phone number looks good!', 'text-success');
                            }
                        },
                        error: function() {
                            const parsleyInst = getParsleyInstance(phoneInput);
                            if (parsleyInst) {
                                parsleyInst.addError('phoneCheck', {message: 'Error validating phone number. Please try again.'});
                            }
                            setPhoneValidationMessage('Error validating phone number. Please try again.', 'text-danger');
                        }
                    });
                }, 500);
            });
            
            // Function to bind phone action handlers
            function bindPhoneActions() {
                // Verify button click handler
                $('.verify-btn').off('click').on('click', function() {
                    const phoneId = $(this).data('phone-id');
                    $('#modalPhoneId').val(phoneId);
                    const verificationModal = new bootstrap.Modal(document.getElementById('verificationModal'));
                    verificationModal.show();
                    $('#verification_code').focus();
                    startCountdown();
                });
                
                // Resend button click handler
                $('.resend-btn').off('click').on('click', function() {
                    const phoneId = $(this).data('phone-id');
                    resendVerificationCode(phoneId);
                });
                
                // Delete button click handler
                $('.delete-btn').off('click').on('click', function() {
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
                
                // Label editing functionality
                $('.edit-label-btn').off('click').on('click', function() {
                    const phoneId = $(this).data('phone-id');
                    const currentLabel = $(this).data('current-label') || '';
                    
                    $(this).siblings('.phone-label-text').hide();
                    $(this).hide();
                    
                    const input = $(`.label-input[data-phone-id="${phoneId}"]`);
                    input.show().focus().val(currentLabel);
                });
                
                // Handle label input blur
                $('.label-input').off('blur').on('blur', function() {
                    const phoneId = $(this).data('phone-id');
                    const newLabel = $(this).val().trim();
                    
                    $(this).hide();
                    $(this).siblings('.phone-label-text').show();
                    $(this).siblings('.edit-label-btn').show();
                    
                    if (newLabel !== $(this).siblings('.phone-label-text').text().replace('No label', '').trim()) {
                        updatePhoneLabel(phoneId, newLabel);
                    }
                });
                
                // Handle label input enter key
                $('.label-input').off('keypress').on('keypress', function(e) {
                    if (e.which === 13) {
                        $(this).blur();
                    }
                });
            }
            
            // Initial bind
            bindPhoneActions();
            
            // Set up verification modal close handlers
            // Close button (X) in header
            $(document).off('click', '#closeVerificationModal').on('click', '#closeVerificationModal', function(e) {
                e.preventDefault();
                e.stopPropagation();
                closeVerificationModal();
                return false;
            });
            
            // Cancel button in footer
            $(document).off('click', '#cancelVerificationBtn').on('click', '#cancelVerificationBtn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                closeVerificationModal();
                return false;
            });
            
            // Resend button in modal
            $('#resendFromModal').off('click').on('click', function() {
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
            
            // Form submission handler
            $('#verifyForm').off('submit').on('submit', function(e) {
                e.preventDefault();
                verifyPhoneNumber();
            });

            // Add phone form submission - Parsley will handle validation
            $('#addPhoneForm').on('submit', function(e) {
                const phoneInput = $('#phone_number');
                const phoneNumber = phoneInput.val().trim();
                
                // Final check before submission
                if (/^09\d{9}$/.test(phoneNumber)) {
                    // Check if number exists synchronously before submit
                    $.ajax({
                        url: window.location.href,
                        method: 'POST',
                        async: false,
                        data: {
                            action: 'check_phone',
                            phone_number: phoneNumber,
                            csrf_token: csrfToken
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (!response.valid) {
                                e.preventDefault();
                                const parsleyInst = getParsleyInstance(phoneInput);
                                if (parsleyInst) {
                                    parsleyInst.addError('phoneExists', {message: response.message});
                                }
                                Swal.fire({
                                    title: 'Invalid Phone Number',
                                    text: response.message,
                                    icon: 'error',
                                    confirmButtonText: 'OK'
                                });
                                return false;
                            }
                        }
                    });
                }
                
                // Parsley validation is handled automatically
                // Only proceed if form is valid
                const formParsley = getParsleyInstance($(this));
                if (formParsley) {
                    // Check if form is valid using Parsley
                    if (typeof formParsley.isValid === 'function') {
                        if (!formParsley.isValid()) {
                            e.preventDefault();
                            return false;
                        }
                    }
                } else {
                    // Fallback: Basic validation if Parsley is not available
                    const phoneNumber = $('#phone_number').val().trim();
                    if (!phoneNumber || !/^09[0-9]{9}$/.test(phoneNumber)) {
                        e.preventDefault();
                        Swal.fire({
                            title: 'Invalid Phone Number',
                            text: 'Please enter a valid 11-digit phone number starting with 09',
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });
                        return false;
                    }
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
                    code: code,
                    csrf_token: csrfToken
                },
                dataType: 'json',
                success: function(response) {
                    // Update CSRF token from response if provided
                    if (response.csrf_token) {
                        csrfToken = response.csrf_token;
                    }
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
                    phone_id: phoneId,
                    csrf_token: csrfToken
                },
                dataType: 'json',
                success: function(response) {
                    // Update CSRF token from response if provided
                    if (response.csrf_token) {
                        csrfToken = response.csrf_token;
                    }
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
                    label: label,
                    csrf_token: csrfToken
                },
                dataType: 'json',
                success: function(response) {
                    // Update CSRF token from response if provided
                    if (response.csrf_token) {
                        csrfToken = response.csrf_token;
                    }
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
                    phone_id: phoneId,
                    csrf_token: csrfToken
                },
                dataType: 'json',
                success: function(response) {
                    // Update CSRF token from response if provided
                    if (response.csrf_token) {
                        csrfToken = response.csrf_token;
                    }
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
        
        function resetVerificationForm() {
            // Reset form when modal is closed
            $('input[name="verification_code"]').val('');
            $('#countdown').removeClass('text-danger').text('Code expires in 15:00');
            
            // Clear any Parsley errors
            const parsleyInst = getParsleyInstance($('#verifyForm'));
            if (parsleyInst) {
                parsleyInst.reset();
            }
        }
        
        // Function to force close verification modal
        function closeVerificationModal() {
            const modalElement = document.getElementById('verificationModal');
            if (!modalElement) return;
            
            // Force close - directly manipulate DOM to ensure it closes immediately
            // Remove modal classes and hide
            $(modalElement).removeClass('show').css({
                'display': 'none',
                'padding-right': ''
            });
            $(modalElement).attr('aria-hidden', 'true');
            $(modalElement).removeAttr('aria-modal');
            $(modalElement).removeAttr('style');
            
            // Remove all modal backdrops
            $('.modal-backdrop').remove();
            
            // Reset body styles
            $('body').removeClass('modal-open').css({
                'overflow': '',
                'padding-right': ''
            });
            
            // Also try Bootstrap method if available (non-blocking)
            if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                try {
                    if (typeof bootstrap.Modal.getInstance === 'function') {
                        const modalInstance = bootstrap.Modal.getInstance(modalElement);
                        if (modalInstance) {
                            modalInstance.hide();
                        }
                    } else {
                        const modal = new bootstrap.Modal(modalElement);
                        modal.hide();
                    }
                } catch (e) {
                    // Ignore Bootstrap errors, we've already closed it manually
                }
            }
            
            // Try jQuery method as well (non-blocking)
            try {
                $(modalElement).modal('hide');
            } catch (e) {
                // Ignore jQuery errors, we've already closed it manually
            }
            
            // Reset form
            resetVerificationForm();
        }
        
        // Handle modal close events
        $('#verificationModal').on('hidden.bs.modal', function () {
            resetVerificationForm();
        });
        
        // Keep old function for backwards compatibility
        function closeModal() {
            closeVerificationModal();
        }
        
        function debugPhoneStatus(phoneId) {
            $.ajax({
                url: window.location.href,
                method: 'POST',
                data: {
                    action: 'debug_status',
                    phone_id: phoneId,
                    csrf_token: csrfToken
                },
                dataType: 'json',
                success: function(response) {
                    // Update CSRF token from response if provided
                    if (response.csrf_token) {
                        csrfToken = response.csrf_token;
                    }
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
                    phone_id: phoneId,
                    csrf_token: csrfToken
                },
                dataType: 'json',
                success: function(response) {
                    // Update CSRF token from response if provided
                    if (response.csrf_token) {
                        csrfToken = response.csrf_token;
                    }
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
                    action: 'reset_all_verification',
                    csrf_token: csrfToken
                },
                dataType: 'json',
                success: function(response) {
                    // Update CSRF token from response if provided
                    if (response.csrf_token) {
                        csrfToken = response.csrf_token;
                    }
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
        
    </script>
<?php include('../../components/scripts.php')?>
</body>
</html>