<?php
header('Content-Type: application/json');
require_once 'db_config.php';

// Helper functions (copied from registration.php for modularity)
function is_valid_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}
function is_email_registered($email) {
    $conn = getDatabaseConnection();
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email_address = ?");
    $stmt->execute([$email]);
    return $stmt->rowCount() > 0;
}
function is_username_registered($username) {
    $conn = getDatabaseConnection();
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    return $stmt->rowCount() > 0;
}
// Remove is_device_approved and all references to admin_devices

$response = ['exists' => false, 'valid' => false];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    switch ($action) {
        case 'email':
            $email = trim($_POST['email'] ?? '');
            $response['valid'] = is_valid_email($email);
            $response['exists'] = is_email_registered($email);
            break;
        case 'username':
            $username = trim($_POST['username'] ?? '');
            $response['valid'] = strlen($username) >= 5;
            $response['exists'] = is_username_registered($username);
            break;
        case 'device':
            $device_number = trim($_POST['device_number'] ?? '');
            $serial_number = trim($_POST['serial_number'] ?? '');
            $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : null;
            $conn = getDatabaseConnection();
            // Uniqueness check in devices table for this user
            if ($user_id !== null) {
                $stmt = $conn->prepare("SELECT device_id FROM devices WHERE (device_number = ? OR serial_number = ?) AND user_id = ?");
                $stmt->execute([$device_number, $serial_number, $user_id]);
                if ($stmt->rowCount() > 0) {
                    $response['valid'] = false;
                    $response['exists'] = true;
                    break;
                }
            }
            // Global uniqueness check for serial_number
            $stmt = $conn->prepare("SELECT device_id FROM devices WHERE serial_number = ?");
            $stmt->execute([$serial_number]);
            if ($stmt->rowCount() > 0) {
                $response['valid'] = false;
                $response['exists'] = true;
                break;
            }
            // --- ADMIN DEVICES VALIDATION ---
            $stmt = $conn->prepare("SELECT admin_device_id FROM admin_devices WHERE device_number = ? AND serial_number = ? AND status = 'approved'");
            $stmt->execute([$device_number, $serial_number]);
            if ($stmt->rowCount() == 0) {
                $response['valid'] = false;
                $response['exists'] = false;
                $response['error'] = 'Device is not approved or does not exist in admin inventory.';
                break;
            }
            $response['valid'] = true;
            $response['exists'] = false;
            break;
        default:
            $response = ['exists' => false, 'valid' => false];
    }
}
echo json_encode($response); 