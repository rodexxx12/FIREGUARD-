<?php
/**
 * Email Configuration Test Script
 * Use this script to test different SMTP configurations
 */

require_once 'email_config.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Email Configuration Test</h1>";
echo "<p>Testing SMTP configurations for Hostinger...</p>";

// Get all available configurations
$configs = getAllSmtpConfigs();

echo "<h2>Available SMTP Configurations:</h2>";
echo "<ul>";
foreach ($configs as $key => $config) {
    echo "<li><strong>$key</strong>: {$config['description']} - {$config['host']}:{$config['port']}</li>";
}
echo "</ul>";

echo "<h2>Testing Each Configuration:</h2>";

foreach ($configs as $config_key => $config) {
    echo "<h3>Testing: $config_key</h3>";
    echo "<p><strong>Description:</strong> {$config['description']}</p>";
    echo "<p><strong>Host:</strong> {$config['host']}:{$config['port']}</p>";
    echo "<p><strong>Encryption:</strong> {$config['encryption']}</p>";
    
    try {
        $mail = new PHPMailer(true);
        
        // Enable debug output for testing
        $mail->SMTPDebug = SMTP::DEBUG_SERVER;
        $mail->Debugoutput = 'html';
        
        // Configure the mailer
        $mail = configurePHPMailer($mail, $config_key);
        
        echo "<p><strong>Configuration applied successfully.</strong></p>";
        
        // Test SMTP connection
        echo "<p><strong>Testing SMTP connection...</strong></p>";
        
        if ($mail->smtpConnect()) {
            echo "<p style='color: green;'><strong>✓ SMTP connection successful!</strong></p>";
            $mail->smtpClose();
            
            // Try to send a test email
            echo "<p><strong>Testing email sending...</strong></p>";
            
            // Add a test recipient (you can change this)
            $test_email = 'test@example.com'; // Change this to a real email for testing
            $mail->addAddress($test_email);
            $mail->Subject = 'Test Email - Fire Detection System';
            $mail->Body = "
                <h2>Test Email</h2>
                <p>This is a test email to verify SMTP configuration.</p>
                <p><strong>Configuration:</strong> $config_key</p>
                <p><strong>Host:</strong> {$config['host']}:{$config['port']}</p>
                <p><strong>Encryption:</strong> {$config['encryption']}</p>
                <p><strong>Timestamp:</strong> " . date('Y-m-d H:i:s') . "</p>
            ";
            $mail->AltBody = "Test email from Fire Detection System using configuration: $config_key";
            
            if ($mail->send()) {
                echo "<p style='color: green;'><strong>✓ Test email sent successfully!</strong></p>";
                echo "<p>Email sent to: $test_email</p>";
            } else {
                echo "<p style='color: red;'><strong>✗ Failed to send test email.</strong></p>";
                echo "<p>Error: " . $mail->ErrorInfo . "</p>";
            }
            
        } else {
            echo "<p style='color: red;'><strong>✗ SMTP connection failed!</strong></p>";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'><strong>✗ Configuration error:</strong></p>";
        echo "<p>" . $e->getMessage() . "</p>";
    }
    
    echo "<hr>";
}

// Test with a specific email address
echo "<h2>Test with Specific Email Address</h2>";
echo "<form method='post'>";
echo "<p><label>Email Address: <input type='email' name='test_email' placeholder='your@email.com' required></label></p>";
echo "<p><label>Configuration: <select name='config_key'>";
foreach ($configs as $key => $config) {
    $selected = ($key === 'ssl_465') ? 'selected' : '';
    echo "<option value='$key' $selected>{$config['description']}</option>";
}
echo "</select></label></p>";
echo "<p><input type='submit' value='Send Test Email'></p>";
echo "</form>";

if ($_POST && isset($_POST['test_email'])) {
    $test_email = trim($_POST['test_email']);
    $config_key = $_POST['config_key'];
    
    if (filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
        echo "<h3>Test Results for: $test_email</h3>";
        
        try {
            $mail = new PHPMailer(true);
            
            // Configure the mailer
            $mail = configurePHPMailer($mail, $config_key);
            
            // Add recipient and content
            $mail->addAddress($test_email);
            $mail->Subject = 'Test Email - Fire Detection System';
            $mail->Body = "
                <h2>Test Email</h2>
                <p>This is a test email to verify SMTP configuration.</p>
                <p><strong>Configuration:</strong> $config_key</p>
                <p><strong>Timestamp:</strong> " . date('Y-m-d H:i:s') . "</p>
                <p>If you receive this email, your SMTP configuration is working correctly!</p>
            ";
            $mail->AltBody = "Test email from Fire Detection System using configuration: $config_key";
            
            if ($mail->send()) {
                echo "<p style='color: green;'><strong>✓ Test email sent successfully to $test_email!</strong></p>";
                echo "<p>Please check your email inbox (and spam folder).</p>";
            } else {
                echo "<p style='color: red;'><strong>✗ Failed to send test email.</strong></p>";
                echo "<p>Error: " . $mail->ErrorInfo . "</p>";
            }
            
        } catch (Exception $e) {
            echo "<p style='color: red;'><strong>✗ Error sending test email:</strong></p>";
            echo "<p>" . $e->getMessage() . "</p>";
        }
    } else {
        echo "<p style='color: red;'>Invalid email address.</p>";
    }
}

// Show error log
echo "<h2>Recent Email Errors</h2>";
$error_log = getEmailErrorLog(10);
if (!empty($error_log)) {
    echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px;'>";
    echo "<h3>Last 10 Email Errors:</h3>";
    foreach ($error_log as $line) {
        $data = json_decode($line, true);
        if ($data) {
            echo "<div style='margin-bottom: 10px; padding: 10px; background: white; border-radius: 3px;'>";
            echo "<p><strong>Time:</strong> " . ($data['timestamp'] ?? 'N/A') . "</p>";
            echo "<p><strong>Error:</strong> " . ($data['error'] ?? 'N/A') . "</p>";
            echo "<p><strong>Config:</strong> " . ($data['config']['description'] ?? 'N/A') . "</p>";
            echo "</div>";
        }
    }
    echo "</div>";
    
    echo "<form method='post' style='margin-top: 15px;'>";
    echo "<input type='hidden' name='clear_log' value='1'>";
    echo "<input type='submit' value='Clear Error Log' style='background: #dc3545; color: white; border: none; padding: 8px 15px; border-radius: 3px; cursor: pointer;'>";
    echo "</form>";
} else {
    echo "<p style='color: green;'>No email errors found in the log.</p>";
}

// Handle clear log action
if (isset($_POST['clear_log'])) {
    if (clearEmailErrorLog()) {
        echo "<p style='color: green;'>Error log cleared successfully.</p>";
        echo "<script>location.reload();</script>";
    } else {
        echo "<p style='color: red;'>Failed to clear error log.</p>";
    }
}

echo "<hr>";
echo "<p><strong>Note:</strong> This test script helps diagnose email configuration issues. Check the output above for any error messages.</p>";
echo "<p><strong>Common Issues:</strong></p>";
echo "<ul>";
echo "<li>Firewall blocking outbound SMTP connections</li>";
echo "<li>Incorrect email credentials</li>";
echo "<li>Server blocking port 465 or 587</li>";
echo "<li>SSL/TLS certificate issues</li>";
echo "</ul>";
?>
