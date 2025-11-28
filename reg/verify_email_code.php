<?php
// Load environment variables from config.env or .env file if it exists
if (file_exists(__DIR__ . '/env_setup.php')) {
    require_once __DIR__ . '/env_setup.php';
}

require_once 'security_functions.php';
configure_secure_session();
session_start();
require_once 'db_config.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Invalid request'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $verification_code = trim($_POST['verification_code'] ?? '');
    $email = trim($_POST['email'] ?? '');
    
    if (empty($verification_code)) {
        $response = ['success' => false, 'message' => 'Verification code is required'];
    } elseif (empty($email)) {
        $response = ['success' => false, 'message' => 'Email is required'];
    } elseif (!preg_match('/^[0-9]{6}$/', $verification_code)) {
        $response = ['success' => false, 'message' => 'Invalid verification code format'];
    } else {
        // Check if verification session exists
        if (!isset($_SESSION['email_verification'])) {
            $response = ['success' => false, 'message' => 'No verification session found. Please request a new code.'];
        } elseif ($_SESSION['email_verification']['email'] !== $email) {
            $response = ['success' => false, 'message' => 'Email mismatch. Please use the email you requested the code for.'];
        } else {
            $verification_data = $_SESSION['email_verification'];
            
            // Check if code has expired
            if (strtotime($verification_data['expiry']) < time()) {
                unset($_SESSION['email_verification']);
                $response = ['success' => false, 'message' => 'Verification code has expired. Please request a new one.'];
            } elseif ($verification_data['attempts'] >= 3) {
                unset($_SESSION['email_verification']);
                $response = ['success' => false, 'message' => 'Too many failed attempts. Please request a new verification code.'];
            } else {
                $storedHash = $verification_data['code_hash'] ?? null;
                if (!$storedHash && isset($verification_data['code'])) {
                    // Backwards compatibility with older sessions
                    $storedHash = password_hash($verification_data['code'], PASSWORD_DEFAULT);
                    $_SESSION['email_verification']['code_hash'] = $storedHash;
                    unset($_SESSION['email_verification']['code']);
                }
                
                if (!$storedHash || !password_verify($verification_code, $storedHash)) {
                    // Increment attempts
                    $_SESSION['email_verification']['attempts']++;
                    $remaining_attempts = 3 - $_SESSION['email_verification']['attempts'];
                    $response = [
                        'success' => false, 
                        'message' => "Invalid verification code. {$remaining_attempts} attempts remaining."
                    ];
                } else {
                    // Code is valid - mark email as verified
                    $_SESSION['email_verified'] = [
                        'email' => $email,
                        'verified_at' => date('Y-m-d H:i:s')
                    ];
                    
                    // Clear verification session
                    unset($_SESSION['email_verification']);
                    
                    $response = [
                        'success' => true, 
                        'message' => 'Email verified successfully! You can now proceed with registration.',
                        'email' => $email
                    ];
                }
            }
        }
    }
}

echo json_encode($response);
?> 