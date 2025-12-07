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

/**
 * Ensure password_resets table exists with correct structure
 */
function ensurePasswordResetsTable() {
    static $tableChecked = false;
    
    if ($tableChecked) {
        return true;
    }
    
    try {
        $conn = getDatabaseConnection();
        
        // Check if table exists
        $stmt = $conn->query("SHOW TABLES LIKE 'password_resets'");
        if ($stmt->rowCount() === 0) {
            // Create table if it doesn't exist
            $conn->exec("
                CREATE TABLE IF NOT EXISTS password_resets (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    email VARCHAR(255) NOT NULL,
                    token VARCHAR(64) NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    expires_at TIMESTAMP NOT NULL,
                    UNIQUE KEY unique_email (email),
                    INDEX idx_token (token),
                    INDEX idx_expires_at (expires_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            error_log("Password resets table created successfully");
        }
        
        $tableChecked = true;
        return true;
    } catch (PDOException $e) {
        error_log("Failed to ensure password_resets table exists: " . $e->getMessage());
        return false;
    }
}

function sendPasswordResetEmail($email) {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    
    // Ensure table exists
    if (!ensurePasswordResetsTable()) {
        error_log("Password reset email failed: Could not ensure password_resets table exists");
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
    
    // Normalize email to lowercase for consistent storage
    $email = strtolower(trim($email));
    
    // Clean up any existing expired tokens for this email
    $stmt = $conn->prepare("DELETE FROM password_resets WHERE email = ? AND expires_at < NOW()");
    $stmt->execute([$email]);
    
    // Insert new reset token - store email in lowercase
    try {
        // First, try to delete any existing token for this email
        $stmt = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
        $stmt->execute([$email]);
        
        // Then insert the new token
        $stmt = $conn->prepare("
            INSERT INTO password_resets (email, token, expires_at)
            VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? HOUR))
        ");
        $result = $stmt->execute([$email, $token, PASSWORD_RESET_EXPIRE_HOURS]);
        
        if (!$result) {
            error_log("Password reset token insertion failed for email: " . $email);
            return false;
        }
        
        error_log("Password reset token created successfully for email: " . $email . ", Token preview: " . substr($token, 0, 10) . "...");
    } catch (PDOException $e) {
        error_log("Password reset token insertion error: " . $e->getMessage());
        return false;
    }
    
    // Build reset link
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
    $baseUrl = rtrim((string)loginEnv('APP_URL', $protocol . ($_SERVER['HTTP_HOST'] ?? 'localhost')), '/');
    $resetLink = $baseUrl . "/login/php/reset_password.php?token=" . urlencode($token) . "&email=" . urlencode($email);
    
    // Initialize PHPMailer
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $smtpHost = (string)loginEnv('SMTP_HOST', '');
        $smtpUser = (string)loginEnv('SMTP_USER', '');
        $smtpPass = (string)loginEnv('SMTP_PASS', '');
        $smtpPort = (int)loginEnv('SMTP_PORT', 465);
        $smtpEncryption = strtolower((string)loginEnv('SMTP_ENCRYPTION', 'smtps'));
        $smtpAllowSelfSigned = loginEnvBool('SMTP_ALLOW_SELF_SIGNED', false);
        $fromAddress = (string)loginEnv('SMTP_FROM_ADDRESS', 'fireguard@bccbsis.com');
        $fromName = (string)loginEnv('SMTP_FROM_NAME', 'Fire Detection System');

        if ($smtpHost === '' || $smtpUser === '' || $smtpPass === '') {
            error_log('SMTP configuration is incomplete. Cannot send password reset email.');
            return false;
        }

        $mail->isSMTP();
        $mail->Host       = $smtpHost;
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtpUser;
        $mail->Password   = $smtpPass;
        $mail->SMTPSecure = ($smtpEncryption === 'tls')
            ? PHPMailer::ENCRYPTION_STARTTLS
            : PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = $smtpPort > 0 ? $smtpPort : 465;
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => !$smtpAllowSelfSigned,
                'verify_peer_name' => !$smtpAllowSelfSigned,
                'allow_self_signed' => $smtpAllowSelfSigned
            ]
        ];
        
        // Recipients
        $mail->setFrom($fromAddress, $fromName);
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
    // Trim and validate inputs
    $originalToken = $token;
    $token = trim($token);
    $email = trim(strtolower($email)); // Normalize email to lowercase
    
    if (empty($token) || empty($email)) {
        error_log("Password reset validation failed: Empty token or email. Token: " . (empty($token) ? 'empty' : 'present') . ", Email: " . (empty($email) ? 'empty' : 'present'));
        return false;
    }
    
    // Clean up token: remove any whitespace, newlines, or other non-hex characters
    $cleanedToken = preg_replace('/[^a-fA-F0-9]/', '', $token);
    
    // Try both the original token and cleaned token
    $tokensToTry = [];
    if ($cleanedToken !== $token && strlen($cleanedToken) === 64 && ctype_xdigit($cleanedToken)) {
        $tokensToTry[] = $cleanedToken;
    }
    if (strlen($token) === 64 && ctype_xdigit($token)) {
        $tokensToTry[] = $token;
    }
    
    // If neither token is valid format, log and return false
    if (empty($tokensToTry)) {
        error_log("Password reset validation failed: Invalid token format. Original length: " . strlen($originalToken) . ", Cleaned length: " . strlen($cleanedToken) . ", Original is hex: " . (ctype_xdigit($token) ? 'yes' : 'no') . ", Cleaned is hex: " . (ctype_xdigit($cleanedToken) ? 'yes' : 'no'));
        return false;
    }
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        error_log("Password reset validation failed: Invalid email format - " . $email);
        return false;
    }
    
    $conn = getDatabaseConnection();
    
    // Clean up expired tokens first
    try {
        $stmt = $conn->prepare("DELETE FROM password_resets WHERE expires_at < NOW()");
        $stmt->execute();
    } catch (PDOException $e) {
        error_log("Password reset cleanup failed: " . $e->getMessage());
        error_log("Password reset cleanup failed: " . $e->getMessage());
    }
    
    // Try each token variant
    foreach ($tokensToTry as $tokenToCheck) {
        // Validate token with case-insensitive email comparison
        // Use LOWER() in SQL for case-insensitive comparison
        try {
            $stmt = $conn->prepare("
                SELECT email, expires_at, created_at, token FROM password_resets 
                WHERE token = ? AND LOWER(TRIM(email)) = LOWER(TRIM(?)) AND expires_at > NOW()
                LIMIT 1
            ");
            $stmt->execute([$tokenToCheck, $email]);
            $tokenCheck = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($tokenCheck) {
                // Token is valid
                error_log("Password reset validation succeeded for email: " . $email);
                return $tokenCheck;
            }
        } catch (PDOException $e) {
            error_log("Password reset validation query failed: " . $e->getMessage());
        }
    }
    
    // If we get here, token wasn't found - do detailed debugging
    $debugToken = !empty($tokensToTry) ? $tokensToTry[0] : $token;
    try {
        // Check if token exists at all (without email/expiry check)
        $stmt = $conn->prepare("
            SELECT email, expires_at, created_at, token FROM password_resets 
            WHERE token = ?
            LIMIT 1
        ");
        $stmt->execute([$debugToken]);
        $tokenExists = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($tokenExists) {
            $storedEmail = strtolower(trim($tokenExists['email']));
            if ($storedEmail !== $email) {
                error_log("Password reset validation failed: Email mismatch. Stored: '" . $storedEmail . "', Provided: '" . $email . "', Token preview: " . substr($debugToken, 0, 10) . "...");
            } else {
                $expiresAt = $tokenExists['expires_at'];
                $now = new DateTime();
                $expiry = new DateTime($expiresAt);
                if ($expiry < $now) {
                    error_log("Password reset validation failed: Token expired. Expires: " . $expiresAt . ", Now: " . $now->format('Y-m-d H:i:s'));
                } else {
                    error_log("Password reset validation failed: Token found but query didn't match. Expires: " . $expiresAt . ", Now: " . $now->format('Y-m-d H:i:s'));
                }
            }
        } else {
            // Try to find any tokens for this email
            $stmt = $conn->prepare("
                SELECT email, expires_at, created_at, token FROM password_resets 
                WHERE LOWER(TRIM(email)) = LOWER(TRIM(?))
                ORDER BY created_at DESC
                LIMIT 5
            ");
            $stmt->execute([$email]);
            $emailTokens = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($emailTokens)) {
                error_log("Password reset validation failed: Token not found, but found " . count($emailTokens) . " token(s) for this email. Latest token preview: " . substr($emailTokens[0]['token'], 0, 10) . "...");
            } else {
                error_log("Password reset validation failed: Token not found in database - " . substr($debugToken, 0, 20) . "... and no tokens found for email: " . $email);
            }
        }
    } catch (PDOException $e) {
        error_log("Password reset validation debug query failed: " . $e->getMessage());
    }
    
    return false;
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