<?php
/**
 * Email Configuration File for Fire Detection System
 * This file contains all email-related configurations and can be easily modified
 */

// Email Configuration Constants
define('SMTP_HOST', 'smtp.hostinger.com');
define('SMTP_USERNAME', 'fireguard@bccbsis.com');
define('SMTP_PASSWORD', '1j/EIh?7Q');
define('FROM_EMAIL', 'fireguard@bccbsis.com');
define('FROM_NAME', 'Fire Detection System');

// SMTP Configuration Options
$smtp_configs = [
    'ssl_465' => [
        'host' => SMTP_HOST,
        'port' => 465,
        'encryption' => 'ssl',
        'description' => 'SSL on port 465 (Recommended)'
    ],
    'tls_587' => [
        'host' => SMTP_HOST,
        'port' => 587,
        'encryption' => 'tls',
        'description' => 'STARTTLS on port 587 (Alternative)'
    ],
    'ssl_587' => [
        'host' => SMTP_HOST,
        'port' => 587,
        'encryption' => 'ssl',
        'description' => 'SSL on port 587 (Alternative)'
    ]
];

// Function to get SMTP configuration
function getSmtpConfig($config_key = 'ssl_465') {
    global $smtp_configs;
    return $smtp_configs[$config_key] ?? $smtp_configs['ssl_465'];
}

// Function to configure PHPMailer with specific settings
function configurePHPMailer($mail, $config_key = 'ssl_465') {
    global $smtp_configs;
    
    $config = getSmtpConfig($config_key);
    
    // Basic SMTP settings
    $mail->isSMTP();
    $mail->Host = $config['host'];
    $mail->SMTPAuth = true;
    $mail->Username = SMTP_USERNAME;
    $mail->Password = SMTP_PASSWORD;
    
    // Set encryption and port based on configuration
    if ($config['encryption'] === 'ssl') {
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
    } else {
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    }
    
    $mail->Port = $config['port'];
    
    // Additional settings for better reliability
    $mail->Timeout = 30;
    $mail->SMTPKeepAlive = true;
    $mail->CharSet = 'UTF-8';
    
    // SSL/TLS options for better compatibility
    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        ]
    ];
    
    // Set sender information
    $mail->setFrom(FROM_EMAIL, FROM_NAME);
    
    return $mail;
}

// Function to test email configuration
function testEmailConfiguration($config_key = 'ssl_465') {
    require_once dirname(__DIR__) . '/vendor/autoload.php';
    
    // Use fully qualified class names since we can't use 'use' inside functions
    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        
        // Enable debug output for testing
        $mail->SMTPDebug = \PHPMailer\PHPMailer\SMTP::DEBUG_SERVER;
        $mail->Debugoutput = 'error_log';
        
        // Configure the mailer
        $mail = configurePHPMailer($mail, $config_key);
        
        // Test email content
        $mail->addAddress('test@example.com');
        $mail->Subject = 'Test Email Configuration';
        $mail->Body = 'This is a test email to verify SMTP configuration.';
        
        // Try to connect and send
        if ($mail->smtpConnect()) {
            $mail->smtpClose();
            return [
                'success' => true,
                'message' => 'SMTP connection successful',
                'config' => getSmtpConfig($config_key)
            ];
        } else {
            return [
                'success' => false,
                'message' => 'SMTP connection failed',
                'config' => getSmtpConfig($config_key)
            ];
        }
        
    } catch (\Exception $e) {
        return [
            'success' => false,
            'message' => 'Error: ' . $e->getMessage(),
            'config' => getSmtpConfig($config_key)
        ];
    }
}

// Function to get all available configurations for testing
function getAllSmtpConfigs() {
    global $smtp_configs;
    return $smtp_configs;
}

// Function to log email errors with detailed information
function logEmailError($error_message, $config_key = 'ssl_465', $additional_info = []) {
    $config = getSmtpConfig($config_key);
    $log_data = [
        'timestamp' => date('Y-m-d H:i:s'),
        'error' => $error_message,
        'config' => $config,
        'additional_info' => $additional_info
    ];
    
    error_log('Email Error: ' . json_encode($log_data));
    
    // Also log to a specific email error file
    $log_file = __DIR__ . '/email_errors.log';
    file_put_contents($log_file, json_encode($log_data) . "\n", FILE_APPEND | LOCK_EX);
}

// Function to get email error log
function getEmailErrorLog($lines = 50) {
    $log_file = __DIR__ . '/email_errors.log';
    if (file_exists($log_file)) {
        $log_content = file($log_file);
        return array_slice($log_content, -$lines);
    }
    return [];
}

// Function to clear email error log
function clearEmailErrorLog() {
    $log_file = __DIR__ . '/email_errors.log';
    if (file_exists($log_file)) {
        unlink($log_file);
        return true;
    }
    return false;
}
?>
