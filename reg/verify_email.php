<?php
require_once 'db_config.php';
$email = $_GET['email'] ?? '';
$token = $_GET['token'] ?? '';
$verified = false;
$message = '';
if ($email && $token) {
    $conn = getDatabaseConnection();
    $stmt = $conn->prepare("SELECT user_id, email_verification_token, verification_expiry, email_verified FROM users WHERE email_address = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user && $user['email_verification_token'] === $token) {
        if ($user['email_verified']) {
            $message = 'Your account is already verified.';
        } elseif (strtotime($user['verification_expiry']) < time()) {
            $message = 'Verification link has expired.';
        } else {
            // Begin transaction to update user and insert pending data
            $conn->beginTransaction();
            try {
                // Update user verification status
                $update = $conn->prepare("UPDATE users SET email_verified=1, status='Active', email_verification_token=NULL, verification_expiry=NULL WHERE user_id=?");
                $update->execute([$user['user_id']]);
                
                // Check for pending device and building data
                $pendingStmt = $conn->prepare("SELECT pending_data FROM pending_registrations WHERE user_id = ?");
                $pendingStmt->execute([$user['user_id']]);
                $pendingRow = $pendingStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($pendingRow && !empty($pendingRow['pending_data'])) {
                    $pendingData = json_decode($pendingRow['pending_data'], true);
                    
                    // Insert device if data exists
                    if (!empty($pendingData['device'])) {
                        $deviceData = $pendingData['device'];
                        $deviceStmt = $conn->prepare("INSERT INTO devices (user_id, device_name, device_number, serial_number, barangay_id, is_active, status) VALUES (?, ?, ?, ?, ?, 1, 'offline')");
                        $deviceStmt->execute([
                            $user['user_id'],
                            $deviceData['device_name'] ?? 'User Device',
                            $deviceData['device_number'] ?? null,
                            $deviceData['serial_number'] ?? null,
                            $deviceData['barangay_id'] ?? null
                        ]);
                        error_log("Device inserted for user_id: " . $user['user_id']);
                    }
                    
                    // Insert building if data exists
                    if (!empty($pendingData['building'])) {
                        $buildingData = $pendingData['building'];
                        $buildingStmt = $conn->prepare("INSERT INTO buildings (user_id, barangay_id, building_name, building_type, address, latitude, longitude) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $buildingStmt->execute([
                            $user['user_id'],
                            $buildingData['barangay_id'] ?? null,
                            $buildingData['building_name'] ?? null,
                            $buildingData['building_type'] ?? null,
                            $buildingData['address'] ?? null,
                            $buildingData['latitude'] ?? null,
                            $buildingData['longitude'] ?? null
                        ]);
                        error_log("Building inserted for user_id: " . $user['user_id']);
                    }
                    
                    // Delete pending registration data
                    $deleteStmt = $conn->prepare("DELETE FROM pending_registrations WHERE user_id = ?");
                    $deleteStmt->execute([$user['user_id']]);
                }
                
                $conn->commit();
                $message = 'Your account has been verified! You can now log in.';
                $verified = true;
                
            } catch (Exception $e) {
                $conn->rollBack();
                error_log("Verification error: " . $e->getMessage());
                $message = 'Verification completed but there was an issue setting up your account. Please contact support.';
                $verified = false;
            }
        }
    } else {
        $message = 'Invalid verification link.';
    }
} else {
    $message = 'Invalid verification request.';
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Email Verification</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .welcome-message {
            background: white;
            color: #333;
            padding: 2rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            border: 1px solid #e0e0e0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .welcome-message h2 {
            color: #333;
            margin-bottom: 1rem;
            font-weight: 600;
            font-style: italic;
        }
        .welcome-message p {
            margin-bottom: 1rem;
            line-height: 1.6;
            font-style: italic;
        }
        .feature-list {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            margin: 1.5rem 0;
            border: 1px solid #e9ecef;
        }
        .feature-list ul {
            list-style: none;
            padding-left: 0;
            margin-bottom: 0;
        }
        .feature-list li {
            padding: 0.5rem 0;
            border-bottom: 1px solid #dee2e6;
            font-style: italic;
        }
        .feature-list li:last-child {
            border-bottom: none;
        }
        .feature-list li:before {
            content: "✓";
            color: #28a745;
            font-weight: bold;
            margin-right: 0.5rem;
        }
        .signature {
            font-style: italic;
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid #dee2e6;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="card mx-auto" style="max-width: 800px;">
            <div class="card-body">
                <!-- Welcome Message -->
                <div class="welcome-message">
                    <h2><i class="fas fa-fire-extinguisher me-2"></i>Welcome to the Real-Time IoT-Based Fire Detection System</h2>
                    
                    <p>Thank you for registering with our platform. We are committed to helping you safeguard your buildings and assets through intelligent, real-time fire monitoring and geo-tagged alert notifications.</p>
                    
                    <div class="feature-list">
                        <p><strong>With your account, you will gain access to:</strong></p>
                        <ul>
                            <li>Live sensor data and fire status monitoring across all your registered buildings</li>
                            <li>Instant alerts in the event of smoke, heat, flame, or temperature anomalies</li>
                            <li>A user-friendly dashboard designed for proactive risk management and incident response</li>
                            <li>Integration with emergency services and geo-location features to support swift action</li>
                        </ul>
                    </div>
                    
                    <p>To begin using the full features of your account, please verify your email address. This verification step is essential to activate your account and ensure the integrity and security of our services.</p>
                    
                    <p>We are dedicated to providing you with reliable and responsive fire detection.</p>
                    <p><strong>Stay informed. Stay protected. Stay ahead.</strong></p>
                    
                    <div class="signature">
                        <p>Warm regards,<br>Team</p>
                    </div>
                </div>

                <!-- Verification Status -->
                <div class="text-center">
                    <h3 class="mb-3">Email Verification Status</h3>
                    <div class="alert <?php echo $verified ? 'alert-success' : 'alert-danger'; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                    <div class="mt-3">
                        <a href="../index.php" class="btn btn-primary">
                            <i class="fas fa-sign-in-alt"></i> Go to Login
                        </a>
                        <button type="button" class="btn btn-outline-info ms-2" onclick="showInfoAlert('Your account has been verified successfully. You can now log in with your credentials.', 'Account Verified')">
                            <i class="fas fa-info-circle"></i> More Info
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="js/alerts.js"></script>
    <script>
        // Show appropriate alert based on verification status
        document.addEventListener('DOMContentLoaded', function() {
            const verificationMessage = <?php echo json_encode($message, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
            const isVerified = <?php echo $verified ? 'true' : 'false'; ?>;
            if (isVerified) {
                showSuccessAlert(verificationMessage, 'Account Verified');
            } else {
                showErrorAlert(verificationMessage, 'Verification Failed');
            }
        });
    </script>
</body>
</html> 