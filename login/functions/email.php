<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

define('PASSWORD_RESET_EXPIRE_HOURS', 24); // Extended to 24 hours

$vendorAutoload = __DIR__ . '/../../vendor/autoload.php';
if (file_exists($vendorAutoload)) {
    require_once $vendorAutoload;
} else {
    throw new RuntimeException('Composer autoload not found. Please run composer install.');
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security.php';

function sendPasswordResetEmail($email) {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    
    $conn = getDatabaseConnection();
    
    // Check if user exists in any of the user tables
    $stmt = $conn->prepare("
        SELECT email_address as email FROM users WHERE email_address = ? 
        UNION 
        SELECT email as email FROM admin WHERE email = ?
        UNION
        SELECT email as email FROM firefighters WHERE email = ?
        LIMIT 1
    ");
    $stmt->execute([$email, $email, $email]);
    
    if ($stmt->rowCount() === 0) {
        return true; // Don't reveal if email doesn't exist
    }
    
    // Generate a secure token
    $token = bin2hex(random_bytes(32));
    
    // Clean up any existing expired tokens for this email
    $stmt = $conn->prepare("DELETE FROM password_resets WHERE email = ? AND expires_at < NOW()");
    $stmt->execute([$email]);
    
    // Insert new reset token
    $stmt = $conn->prepare("
        INSERT INTO password_resets (email, token, expires_at)
        VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? HOUR))
        ON DUPLICATE KEY UPDATE 
            token = VALUES(token), 
            expires_at = VALUES(expires_at),
            created_at = NOW()
    ");
    $stmt->execute([$email, $token, PASSWORD_RESET_EXPIRE_HOURS]);
    
    // Build reset link
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
    $resetLink = $protocol . $_SERVER['HTTP_HOST'] . "/login/php/reset_password.php?token=" . urlencode($token) . "&email=" . urlencode($email);
    
    // Initialize PHPMailer
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.hostinger.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'fireguard@bccbsis.com';
        $mail->Password   = '1j/EIh?7Q';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // Recipients
        $mail->setFrom('fireguard@bccbsis.com', 'Fire Detection System');
        $mail->addAddress($email);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Password Reset Request - Fire Detection System';
        
        // HTML Body
        $mail->Body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
            <div style='background: #f8f9fa; padding: 20px; border-radius: 10px; text-align: center;'>
                <h2 style='color: #dc3545; margin-bottom: 20px;'>Fire Detection System</h2>
                <h3 style='color: #333; margin-bottom: 20px;'>Password Reset Request</h3>
            </div>
            
            <div style='background: white; padding: 30px; border-radius: 10px; margin-top: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);'>
                <p style='color: #666; line-height: 1.6; margin-bottom: 25px;'>
                    We received a request to reset your password for the Fire Detection System. 
                    If you didn't make this request, you can safely ignore this email.
                </p>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='{$resetLink}' 
                       style='background: #dc3545; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;'>
                        Reset Your Password
                    </a>
                </div>
                
                <p style='color: #666; line-height: 1.6; margin-bottom: 15px;'>
                    <strong>Important:</strong> This link will expire in " . PASSWORD_RESET_EXPIRE_HOURS . " hour(s).
                </p>
                
                <p style='color: #999; font-size: 14px; margin-top: 25px; border-top: 1px solid #eee; padding-top: 20px;'>
                    If the button above doesn't work, copy and paste this link into your browser:<br>
                    <a href='{$resetLink}' style='color: #dc3545; word-break: break-all;'>{$resetLink}</a>
                </p>
            </div>
            
            <div style='text-align: center; margin-top: 20px; color: #999; font-size: 12px;'>
                <p>This is an automated message. Please do not reply to this email.</p>
            </div>
        </div>";
        
        // Plain text body
        $mail->AltBody = "Password Reset Request\n\n" .
                         "Click the following link to reset your password:\n" .
                         $resetLink . "\n\n" .
                         "This link will expire in " . PASSWORD_RESET_EXPIRE_HOURS . " hour(s).\n\n" .
                         "If you didn't request this, please ignore this email.";
        
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        error_log("Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}

function validatePasswordResetToken($token, $email) {
    if (empty($token) || empty($email)) {
        return false;
    }
    
    $conn = getDatabaseConnection();
    
    // Clean up expired tokens first
    $stmt = $conn->prepare("DELETE FROM password_resets WHERE expires_at < NOW()");
    $stmt->execute();
    
    // Validate token
    $stmt = $conn->prepare("
        SELECT email, expires_at FROM password_resets 
        WHERE token = ? AND email = ? AND expires_at > NOW()
        LIMIT 1
    ");
    $stmt->execute([$token, $email]);
    
    $result = $stmt->fetch();
    return $result ? $result : false;
}

function resetPassword($token, $email, $newPassword) {
    if (!validatePasswordResetToken($token, $email)) {
        return false;
    }
    
    if (strlen($newPassword) < 8) {
        return false;
    }
    
    $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
    $conn = getDatabaseConnection();
    
    // Try to update password in users table first
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE email_address = ?");
    $stmt->execute([$hashedPassword, $email]);
    $rowsAffected = $stmt->rowCount();
    
    // If no rows affected, try admin table
    if ($rowsAffected === 0) {
        $stmt = $conn->prepare("UPDATE admin SET password = ? WHERE email = ?");
        $stmt->execute([$hashedPassword, $email]);
        $rowsAffected = $stmt->rowCount();
    }
    
    // If still no rows affected, try firefighters table
    if ($rowsAffected === 0) {
        $stmt = $conn->prepare("UPDATE firefighters SET password = ? WHERE email = ?");
        $stmt->execute([$hashedPassword, $email]);
        $rowsAffected = $stmt->rowCount();
    }
    
    // If password was updated successfully, clean up the reset token
    if ($rowsAffected > 0) {
        $stmt = $conn->prepare("DELETE FROM password_resets WHERE token = ?");
        $stmt->execute([$token]);
        return true;
    }
    
    return false;
}

function cleanupExpiredTokens() {
    $conn = getDatabaseConnection();
    $stmt = $conn->prepare("DELETE FROM password_resets WHERE expires_at < NOW()");
    return $stmt->execute();
} 