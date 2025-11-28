<?php
// Load environment variables from config.env or .env file if it exists
if (file_exists(__DIR__ . '/env_setup.php')) {
    require_once __DIR__ . '/env_setup.php';
}

require_once 'security_functions.php';
configure_secure_session();
session_start();
require_once 'db_config.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

if (!function_exists('env_or')) {
    function env_or(string $key, $default = null) {
        $value = getenv($key);
        return ($value === false || $value === null || $value === '') ? $default : $value;
    }
}

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Invalid request'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $response = ['success' => false, 'message' => 'Email is required'];
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response = ['success' => false, 'message' => 'Invalid email format'];
    } else {
        try {
            // Check if email is already registered
            $conn = getDatabaseConnection();
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE email_address = ?");
            $stmt->execute([$email]);
            if ($stmt->rowCount() > 0) {
                $response = ['success' => false, 'message' => 'Email is already registered'];
            } else {
                $rateKey = 'email_code_' . hash('sha256', strtolower($email) . ':' . get_client_ip());
                $rateLimit = check_rate_limit($rateKey, 3, 600);
                if (!$rateLimit['allowed']) {
                    $response = ['success' => false, 'message' => 'Please wait a few minutes before requesting another code.'];
                } else {
                    // Generate 6-digit verification code
                    $verification_code = sprintf('%06d', mt_rand(0, 999999));
                    $expiry_time = date('Y-m-d H:i:s', strtotime('+10 minutes'));
                    
                    // Store verification code in session
                    $_SESSION['email_verification'] = [
                        'email' => $email,
                        'code_hash' => password_hash($verification_code, PASSWORD_DEFAULT),
                        'expiry' => $expiry_time,
                        'attempts' => 0
                    ];
                    
                    // Send verification email with direct Hostinger configuration
                    $email_sent = false;
                    $last_error = '';
                    
                    // Try different configurations in order of preference
                    $configs = [
                        [
                            'port' => 465,
                            'encryption' => PHPMailer::ENCRYPTION_SMTPS,
                            'description' => 'SSL on port 465'
                        ],
                        [
                            'port' => 587,
                            'encryption' => PHPMailer::ENCRYPTION_STARTTLS,
                            'description' => 'STARTTLS on port 587'
                        ]
                    ];
                    
                    $debugMode = filter_var(getenv('APP_DEBUG') ?? '0', FILTER_VALIDATE_BOOLEAN);
                    foreach ($configs as $config) {
                        try {
                            $mail = new PHPMailer(true);
                            
                            // Enable debug output for troubleshooting (set to DEBUG_OFF in production)
                            $mail->SMTPDebug = $debugMode ? SMTP::DEBUG_SERVER : SMTP::DEBUG_OFF;
                            $mail->Debugoutput = 'error_log';
                            
                            // Validate SMTP credentials before attempting to send
                            $smtp_username = env_or('SMTP_USERNAME', '');
                            $smtp_password = env_or('SMTP_PASSWORD', '');
                            $smtp_from_email = env_or('SMTP_FROM_EMAIL', '');
                            
                            if (empty($smtp_username) || empty($smtp_password) || empty($smtp_from_email)) {
                                throw new Exception("SMTP configuration error: Missing required environment variables");
                            }
                            
                            // Server settings
                            $mail->isSMTP();
                            $mail->Host = env_or('SMTP_HOST', 'smtp.hostinger.com');
                            $mail->SMTPAuth = true;
                            $mail->Username = $smtp_username;
                            $mail->Password = $smtp_password;
                            $mail->SMTPSecure = $config['encryption'];
                            $mail->Port = $config['port'];
                            
                            // Additional settings for better reliability
                            $mail->Timeout = 30;
                            $mail->SMTPKeepAlive = true;
                            $mail->CharSet = 'UTF-8';
                            
                            // Set sender information (must match authenticated email)
                            $mail->setFrom(
                                $smtp_from_email,
                                env_or('SMTP_FROM_NAME', 'Fire Detection System')
                            );
                            $mail->addAddress($email);
                            
                            // Content
                            $mail->isHTML(true);
                            $mail->Subject = 'Email Verification Code - Fire Detection System';
                            $mail->Body = "
                            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
                                <div style='background: #f8f9fa; padding: 20px; border-radius: 10px; text-align: center;'>
                                    <h2 style='color: #333; margin-bottom: 20px;'>Email Verification</h2>
                                    <p style='color: #666; margin-bottom: 20px;'>Thank you for registering with our Fire Detection System. Please use the verification code below to verify your email address:</p>
                                    
                                    <div style='background: #fff; padding: 20px; border-radius: 8px; border: 2px dashed #007bff; margin: 20px 0;'>
                                        <h1 style='color: #007bff; font-size: 32px; letter-spacing: 8px; margin: 0; font-weight: bold;'>$verification_code</h1>
                                    </div>
                                    
                                    <p style='color: #666; font-size: 14px; margin-bottom: 20px;'>
                                        <strong>Important:</strong> This code will expire in 10 minutes for security reasons.
                                    </p>
                                    
                                    <div style='background: #e9ecef; padding: 15px; border-radius: 5px; margin-top: 20px;'>
                                        <p style='color: #495057; font-size: 12px; margin: 0;'>
                                            If you didn't request this verification code, please ignore this email.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        ";
                            $mail->AltBody = "Your email verification code is: $verification_code. This code will expire in 10 minutes.";
                            
                            // SSL/TLS options for better compatibility
                            $allowSelfSigned = filter_var(env_or('SMTP_ALLOW_SELF_SIGNED', 'false'), FILTER_VALIDATE_BOOLEAN);
                            $mail->SMTPOptions = [
                                'ssl' => [
                                    'verify_peer' => !$allowSelfSigned,
                                    'verify_peer_name' => !$allowSelfSigned,
                                    'allow_self_signed' => $allowSelfSigned
                                ]
                            ];
                            
                            // Send the email
                            if ($mail->send()) {
                                $email_sent = true;
                                $response = [
                                    'success' => true, 
                                    'message' => 'Verification code sent successfully! Check your email.',
                                    'email' => $email,
                                    'config_used' => $config['description']
                                ];
                                
                                // Log successful email sending
                                error_log("Verification email sent successfully to: $email using {$config['description']}");
                                break; // Exit the loop on success
                            }
                            
                        } catch (Exception $e) {
                            $last_error = $e->getMessage();
                            error_log("Email sending failed with {$config['description']}: " . $last_error);
                            continue; // Try next configuration
                        }
                    }
                    
                    // If no configuration worked, provide detailed error information
                    if (!$email_sent) {
                        $response = [
                            'success' => false, 
                            'message' => 'Failed to send verification code. Please try again later or contact support.'
                        ];
                        
                        // Log the complete failure
                        error_log("All SMTP configurations failed for email: $email. Last error: $last_error");
                    }
                }
            }
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            $response = ['success' => false, 'message' => 'Database error. Please try again.'];
        }
    }
}

echo json_encode($response);
?> 