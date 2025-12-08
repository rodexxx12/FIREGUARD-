<?php
// Error handling - environment-aware
$isProduction = (getenv('APP_ENV') === 'production' || 
                (isset($_SERVER['HTTP_HOST']) && 
                 strpos($_SERVER['HTTP_HOST'], 'localhost') === false && 
                 strpos($_SERVER['HTTP_HOST'], '127.0.0.1') === false));

if ($isProduction) {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    $logDir = __DIR__ . '/../../logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    ini_set('error_log', $logDir . '/php_errors.log');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

// Start session first (before including session_config)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Secure session configuration (now safe to include)
require_once __DIR__ . '/../../includes/session_config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../../index.php");
    exit();
}

include('../../db/db.php');

// Create an alias function for getDBConnection to maintain compatibility
function getDBConnection() {
    return getDatabaseConnection();
}

// ============================================================================
// SECURITY FUNCTIONS: CSRF Protection, Input Validation, and Sanitization
// ============================================================================

/**
 * Generate CSRF token and store in session
 * @return string CSRF token
 */
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    // Regenerate token every 30 minutes for security
    if (isset($_SESSION['csrf_token_time']) && (time() - $_SESSION['csrf_token_time']) > 1800) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token from request
 * @param string $token Token to validate
 * @return bool True if valid, false otherwise
 */
function validateCsrfToken($token) {
    if (!isset($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    // Token expires after 2 hours
    if (isset($_SESSION['csrf_token_time']) && (time() - $_SESSION['csrf_token_time']) > 7200) {
        unset($_SESSION['csrf_token'], $_SESSION['csrf_token_time']);
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Require valid CSRF token for POST requests
 * Exits with error response if token is invalid
 */
function requireValidCsrfToken() {
    $token = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($token)) {
        http_response_code(419);
        echo json_encode(['status' => 'error', 'message' => 'Invalid or expired CSRF token. Please refresh the page and try again.']);
        exit;
    }
}

/**
 * Sanitize and validate string input
 * @param string $value Input value
 * @param int $maxLength Maximum allowed length
 * @param string $pattern Regex pattern for validation
 * @param string $fieldName Field name for error messages
 * @return string Sanitized value
 * @throws InvalidArgumentException
 */
function sanitizeString($value, $maxLength, $pattern, $fieldName = 'Field') {
    $clean = trim($value);
    
    if ($clean === '') {
        throw new InvalidArgumentException("{$fieldName} is required.");
    }
    
    if (mb_strlen($clean) > $maxLength) {
        throw new InvalidArgumentException("{$fieldName} must not exceed {$maxLength} characters.");
    }
    
    if (!preg_match($pattern, $clean)) {
        throw new InvalidArgumentException("{$fieldName} contains invalid characters.");
    }
    
    return $clean;
}

/**
 * Validate and normalize integer IDs
 * @param mixed $value Input value
 * @param string $fieldName Field name for error messages
 * @param bool $allowNull Whether null is allowed
 * @return int|null Validated integer or null
 * @throws InvalidArgumentException
 */
function validateId($value, $fieldName = 'ID', $allowNull = true) {
    if ($value === null || $value === '') {
        if ($allowNull) {
            return null;
        }
        throw new InvalidArgumentException("{$fieldName} is required.");
    }
    
    $validated = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($validated === false) {
        throw new InvalidArgumentException("Invalid {$fieldName} provided.");
    }
    
    return $validated;
}

/**
 * Validate device status value
 * @param string $status Status value
 * @return string Validated status
 */
function validateDeviceStatus($status) {
    $validStatuses = ['online', 'offline', 'faulty'];
    $status = strtolower(trim($status));
    return in_array($status, $validStatuses, true) ? $status : 'offline';
}

/**
 * Escape output for HTML context (prevents XSS)
 * @param string $value Value to escape
 * @return string Escaped value
 */
function escapeHtml($value) {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

// Handle AJAX POST request for registering device
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    // CSRF Protection: Validate token for all POST requests
    // Exception: validate_field action doesn't require CSRF (it's a read-only validation)
    $action = isset($_POST['action']) ? trim($_POST['action']) : null;
    if ($action !== 'validate_field') {
        requireValidCsrfToken();
    }

    try {
        $conn = getDBConnection();
        $user_id = $_SESSION['user_id'];
    } catch (Exception $e) {
        error_log("Database connection error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Unable to connect to the database.']);
        exit;
    }

    if ($action === 'validate_field') {
        $field = isset($_POST['field']) ? trim($_POST['field']) : '';
        $value = isset($_POST['value']) ? trim($_POST['value']) : '';
        $deviceId = null;
        if (isset($_POST['device_id']) && $_POST['device_id'] !== '') {
            try {
                $deviceId = validateId($_POST['device_id'], 'Device ID', false);
            } catch (InvalidArgumentException $e) {
                $deviceId = null;
            }
        }
        $response = ['valid' => false, 'message' => 'Unknown field validation request'];

        if ($field === 'device_number') {
            if ($value === '') {
                $response = ['valid' => false, 'message' => 'Device number is required'];
            } else {
                // Validate format: alphanumeric and hyphens, max 30 chars
                try {
                    $sanitizedValue = sanitizeString($value, 30, '/^[A-Z0-9\-]+$/i', 'Device number');
                } catch (InvalidArgumentException $e) {
                    $response = ['valid' => false, 'message' => $e->getMessage()];
                    echo json_encode($response);
                    exit;
                }

                $stmt = $conn->prepare("SELECT status FROM admin_devices WHERE device_number = ?");
                $stmt->execute([$sanitizedValue]);
                $adminDevice = $stmt->fetch();

                if (!$adminDevice) {
                    $response = ['valid' => false, 'message' => 'Device number is not recognized. Please contact support.'];
                } elseif ($adminDevice['status'] !== 'approved') {
                    $response = ['valid' => false, 'message' => 'Device number is not approved for registration.'];
                } else {
                    $query = "SELECT COUNT(*) FROM devices WHERE device_number = ? AND user_id = ?";
                    $params = [$sanitizedValue, $user_id];

                    if ($deviceId !== null) {
                        $query .= " AND device_id != ?";
                        $params[] = $deviceId;
                    }

                    $stmt = $conn->prepare($query);
                    $stmt->execute($params);

                    if ($stmt->fetchColumn() > 0) {
                        $response = ['valid' => false, 'message' => 'This device number is already registered to your account.'];
                    } else {
                        $response = ['valid' => true, 'message' => 'Device number is available.'];
                    }
                }
            }
        } elseif ($field === 'serial_number') {
            if ($value === '') {
                $response = ['valid' => false, 'message' => 'Serial number is required'];
            } else {
                // Validate format: alphanumeric and hyphens, max 40 chars
                try {
                    $sanitizedValue = sanitizeString($value, 40, '/^[A-Z0-9\-]+$/i', 'Serial number');
                } catch (InvalidArgumentException $e) {
                    $response = ['valid' => false, 'message' => $e->getMessage()];
                    echo json_encode($response);
                    exit;
                }

                $deviceNumber = isset($_POST['device_number']) ? trim($_POST['device_number']) : '';

                if ($deviceNumber !== '') {
                    try {
                        $sanitizedDeviceNumber = sanitizeString($deviceNumber, 30, '/^[A-Z0-9\-]+$/i', 'Device number');
                        $stmt = $conn->prepare("SELECT status FROM admin_devices WHERE serial_number = ? AND device_number = ?");
                        $stmt->execute([$sanitizedValue, $sanitizedDeviceNumber]);
                    } catch (InvalidArgumentException $e) {
                        $stmt = $conn->prepare("SELECT status FROM admin_devices WHERE serial_number = ?");
                        $stmt->execute([$sanitizedValue]);
                    }
                } else {
                    $stmt = $conn->prepare("SELECT status FROM admin_devices WHERE serial_number = ?");
                    $stmt->execute([$sanitizedValue]);
                }
                $adminDevice = $stmt->fetch();

                if (!$adminDevice) {
                    $response = ['valid' => false, 'message' => 'Serial number is not recognized. Please contact support.'];
                } elseif ($adminDevice['status'] !== 'approved') {
                    $response = ['valid' => false, 'message' => 'Serial number is not approved for registration.'];
                } else {
                    $query = "SELECT COUNT(*) FROM devices WHERE serial_number = ?";
                    $params = [$sanitizedValue];

                    if ($deviceId !== null) {
                        $query .= " AND device_id != ?";
                        $params[] = $deviceId;
                    }

                    $stmt = $conn->prepare($query);
                    $stmt->execute($params);

                    if ($stmt->fetchColumn() > 0) {
                        $response = ['valid' => false, 'message' => 'This serial number is already registered.'];
                    } else {
                        $response = ['valid' => true, 'message' => 'Serial number is available.'];
                    }
                }
            }
        }

        echo json_encode($response);
        exit;
    }

    if ($action === 'update_device') {
        try {
            $device_id = validateId($_POST['device_id'] ?? null, 'Device ID', false);
        } catch (InvalidArgumentException $e) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid device selected.']);
            exit;
        }

        // Verify device ownership
        $stmt = $conn->prepare("SELECT device_id FROM devices WHERE device_id = ? AND user_id = ?");
        $stmt->execute([$device_id, $user_id]);

        if (!$stmt->fetch()) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'You do not have permission to update this device.']);
            exit;
        }

        // Validate and sanitize input
        $errors = [];
        try {
            $device_name = sanitizeString($_POST['device_name'] ?? '', 100, '/^[\w\s\-\.,#]+$/u', 'Device name');
        } catch (InvalidArgumentException $e) {
            $errors[] = $e->getMessage();
        }

        try {
            $device_number = sanitizeString($_POST['device_number'] ?? '', 30, '/^[A-Z0-9\-]+$/i', 'Device number');
        } catch (InvalidArgumentException $e) {
            $errors[] = $e->getMessage();
        }

        try {
            $serial_number = sanitizeString($_POST['serial_number'] ?? '', 40, '/^[A-Z0-9\-]+$/i', 'Serial number');
        } catch (InvalidArgumentException $e) {
            $errors[] = $e->getMessage();
        }

        try {
            $device_type = sanitizeString($_POST['device_type'] ?? 'FIREGUARD DEVICE', 50, '/^[\w\s\-]+$/u', 'Device type');
        } catch (InvalidArgumentException $e) {
            $device_type = 'FIREGUARD DEVICE';
        }

        $status = validateDeviceStatus($_POST['status'] ?? 'offline');
        
        $barangay_id = null;
        if (isset($_POST['barangay_id']) && $_POST['barangay_id'] !== '') {
            try {
                $barangay_id = validateId($_POST['barangay_id'], 'Barangay ID', true);
            } catch (InvalidArgumentException $e) {
                $errors[] = $e->getMessage();
            }
        }

        $is_active = (isset($_POST['is_active']) && (int)$_POST['is_active'] === 1) ? 1 : 0;

        // Check for duplicate device numbers
        if (!empty($device_number)) {
            $stmt = $conn->prepare("SELECT COUNT(*) FROM devices WHERE device_number = ? AND user_id = ? AND device_id != ?");
            $stmt->execute([$device_number, $user_id, $device_id]);
            if ($stmt->fetchColumn() > 0) {
                $errors[] = 'Device number already exists for your account';
            }
        }

        // Check for duplicate serial numbers
        if (!empty($serial_number)) {
            $stmt = $conn->prepare("SELECT COUNT(*) FROM devices WHERE serial_number = ? AND device_id != ?");
            $stmt->execute([$serial_number, $device_id]);
            if ($stmt->fetchColumn() > 0) {
                $errors[] = 'Serial number already exists';
            }
        }

        // Validate device authorization
        if (!empty($device_number) && !empty($serial_number)) {
            $stmt = $conn->prepare("SELECT status FROM admin_devices WHERE device_number = ? AND serial_number = ?");
            $stmt->execute([$device_number, $serial_number]);
            $adminDevice = $stmt->fetch();

            if (!$adminDevice) {
                $errors[] = 'Device number and serial number do not match any authorized devices. Please verify your details.';
            } elseif ($adminDevice['status'] !== 'approved') {
                $errors[] = 'This device is not approved for registration.';
            }
        }

        // Validate barangay if provided
        if ($barangay_id !== null) {
            $stmt = $conn->prepare("SELECT COUNT(*) FROM barangay WHERE id = ?");
            $stmt->execute([$barangay_id]);
            if ($stmt->fetchColumn() == 0) {
                $errors[] = 'Invalid barangay selected';
            }
        }

        if (!empty($errors)) {
            http_response_code(400);
            // Use text separator instead of HTML to prevent XSS
            echo json_encode(['status' => 'error', 'message' => implode(' | ', $errors)]);
            exit;
        }

        // Update device using prepared statement
        $stmt = $conn->prepare("
            UPDATE devices
            SET device_name = ?, device_number = ?, serial_number = ?, device_type = ?, status = ?, barangay_id = ?, is_active = ?
            WHERE device_id = ? AND user_id = ?
        ");

        if (!$stmt->execute([
            $device_name,
            $device_number,
            $serial_number,
            $device_type,
            $status,
            $barangay_id,
            $is_active,
            $device_id,
            $user_id
        ])) {
            throw new Exception("Failed to update device");
        }

        echo json_encode(['status' => 'success', 'message' => 'Device updated successfully.']);
        exit;
    }

    if ($action === 'toggle_device_status') {
        try {
            $device_id = validateId($_POST['device_id'] ?? null, 'Device ID', false);
        } catch (InvalidArgumentException $e) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid device selected.']);
            exit;
        }

        $is_active = (isset($_POST['is_active']) && (int)$_POST['is_active'] === 1) ? 1 : 0;

        // Verify device ownership
        $stmt = $conn->prepare("SELECT device_id FROM devices WHERE device_id = ? AND user_id = ?");
        $stmt->execute([$device_id, $user_id]);

        if (!$stmt->fetch()) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'You do not have permission to modify this device.']);
            exit;
        }

        // Update device status
        $stmt = $conn->prepare("UPDATE devices SET is_active = ? WHERE device_id = ? AND user_id = ?");
        if (!$stmt->execute([$is_active, $device_id, $user_id])) {
            throw new Exception("Failed to update device status");
        }

        $statusLabel = $is_active ? 'activated' : 'disabled';

        echo json_encode([
            'status' => 'success',
            'message' => "Device {$statusLabel} successfully.",
            'is_active' => $is_active
        ]);
        exit;
    }

    try {
        // Validate and sanitize input
        $errors = [];
        
        try {
            $device_name = sanitizeString($_POST['device_name'] ?? '', 100, '/^[\w\s\-\.,#]+$/u', 'Device name');
        } catch (InvalidArgumentException $e) {
            $errors[] = $e->getMessage();
        }

        try {
            $device_number = sanitizeString($_POST['device_number'] ?? '', 30, '/^[A-Z0-9\-]+$/i', 'Device number');
        } catch (InvalidArgumentException $e) {
            $errors[] = $e->getMessage();
        }

        try {
            $serial_number = sanitizeString($_POST['serial_number'] ?? '', 40, '/^[A-Z0-9\-]+$/i', 'Serial number');
        } catch (InvalidArgumentException $e) {
            $errors[] = $e->getMessage();
        }

        try {
            $device_type = sanitizeString($_POST['device_type'] ?? 'FIREGUARD DEVICE', 50, '/^[\w\s\-]+$/u', 'Device type');
        } catch (InvalidArgumentException $e) {
            $device_type = 'FIREGUARD DEVICE';
        }

        $status = validateDeviceStatus($_POST['status'] ?? 'offline');
        $is_active = (isset($_POST['is_active']) && (int)$_POST['is_active'] === 1) ? 1 : 1;

        // Validate IDs
        $building_id = null;
        if (isset($_POST['building_id']) && $_POST['building_id'] !== '') {
            try {
                $building_id = validateId($_POST['building_id'], 'Building ID', true);
            } catch (InvalidArgumentException $e) {
                $errors[] = $e->getMessage();
            }
        }

        $barangay_id = null;
        if (isset($_POST['barangay_id']) && $_POST['barangay_id'] !== '') {
            try {
                $barangay_id = validateId($_POST['barangay_id'], 'Barangay ID', true);
            } catch (InvalidArgumentException $e) {
                $errors[] = $e->getMessage();
            }
        }

        // Check for duplicate device numbers
        if (!empty($device_number)) {
            $stmt = $conn->prepare("SELECT COUNT(*) FROM devices WHERE device_number = ? AND user_id = ?");
            $stmt->execute([$device_number, $user_id]);
            if ($stmt->fetchColumn() > 0) {
                $errors[] = 'Device number already exists for your account';
            }
        }

        // Check for duplicate serial numbers
        if (!empty($serial_number)) {
            $stmt = $conn->prepare("SELECT COUNT(*) FROM devices WHERE serial_number = ?");
            $stmt->execute([$serial_number]);
            if ($stmt->fetchColumn() > 0) {
                $errors[] = 'Serial number already exists';
            }
        }

        // Validate device authorization
        if (!empty($device_number) && !empty($serial_number)) {
            $stmt = $conn->prepare("SELECT status FROM admin_devices WHERE device_number = ? AND serial_number = ?");
            $stmt->execute([$device_number, $serial_number]);
            $adminDevice = $stmt->fetch();

            if (!$adminDevice) {
                $errors[] = 'Device number and serial number do not match any authorized devices. Please verify your details.';
            } elseif ($adminDevice['status'] !== 'approved') {
                $errors[] = 'This device is not approved for registration.';
            }
        }

        // Validate building if provided
        if ($building_id !== null) {
            $stmt = $conn->prepare("SELECT COUNT(*) FROM buildings WHERE id = ? AND user_id = ?");
            $stmt->execute([$building_id, $user_id]);
            if ($stmt->fetchColumn() == 0) {
                $errors[] = 'Invalid building selected';
            }
        }

        // Validate barangay if provided
        if ($barangay_id !== null) {
            $stmt = $conn->prepare("SELECT COUNT(*) FROM barangay WHERE id = ?");
            $stmt->execute([$barangay_id]);
            if ($stmt->fetchColumn() == 0) {
                $errors[] = 'Invalid barangay selected';
            }
        }

        if (!empty($errors)) {
            http_response_code(400);
            // Use text separator instead of HTML to prevent XSS
            echo json_encode(['status' => 'error', 'message' => implode(' | ', $errors)]);
            exit;
        }

        // Insert device using prepared statement
        $stmt = $conn->prepare("INSERT INTO devices 
            (user_id, device_name, device_number, serial_number, device_type, is_active, status, building_id, barangay_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

        if (!$stmt->execute([
            $user_id, $device_name, $device_number, $serial_number, $device_type,
            $is_active, $status, $building_id, $barangay_id
        ])) {
            throw new Exception("Execute failed");
        }

        $device_id = $conn->lastInsertId();

        echo json_encode([
            'status' => 'success',
            'message' => 'Device registered successfully!',
            'device_id' => $device_id
        ]);
    } catch (InvalidArgumentException $e) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    } catch (Exception $e) {
        error_log("Device registration error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database error occurred. Please try again.']);
    }
    exit;
}

// Fetch existing buildings for the user (for dropdown)
$buildings = [];
if (isset($_SESSION['user_id'])) {
    try {
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT id, building_name, building_type, address FROM buildings WHERE user_id = ? ORDER BY building_name");
        $stmt->execute([$_SESSION['user_id']]);
        $buildings = $stmt->fetchAll();
    } catch (Exception $e) {
        $buildings = [];
    }
}

$barangays = [];
try {
    if (!isset($conn)) {
        $conn = getDBConnection();
    }
    $stmt = $conn->prepare("SELECT id, barangay_name FROM barangay ORDER BY barangay_name");
    $stmt->execute();
    $barangays = $stmt->fetchAll();
} catch (Exception $e) {
    $barangays = [];
}

$devices = [];
try {
    if (!isset($conn)) {
        $conn = getDBConnection();
    }
    $stmt = $conn->prepare("
        SELECT 
            d.device_id,
            d.device_name,
            d.device_number,
            d.serial_number,
            d.device_type,
            d.is_active,
            d.status,
            d.created_at,
            d.updated_at,
            d.last_activity,
            d.building_id,
            d.barangay_id,
            b.building_name,
            br.barangay_name
        FROM devices d
        LEFT JOIN buildings b ON d.building_id = b.id
        LEFT JOIN barangay br ON d.barangay_id = br.id
        WHERE d.user_id = ?
        ORDER BY d.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $devices = [];
}

// Generate CSRF token for forms
$csrf_token = generateCsrfToken();
?>
<?php include('../../components/header.php'); ?>
<style>
    .x_panel {
        background: white;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        margin-bottom: 20px;
    }
    .main-content {
        padding: 20px 15px 40px;
    }
    .x_title {
        border-bottom: 1px solid #e5e5e5;
        padding: 15px 20px;
        margin-bottom: 0;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 12px;
    }
    .x_title h2 {
        margin: 0;
        font-size: 18px;
        font-weight: 600;
        color: #333;
        flex: 1 1 auto;
    }
    .x_content {
        padding: 20px;
    }
    .item.form-group {
        margin-bottom: 10px;
        height: 100%;
        display: flex;
        flex-direction: column;
    }
    .item.form-group label {
        font-weight: 600;
        color: #555;
        display: block;
        margin-bottom: 3px;
        min-height: 18px;
        line-height: 1.3;
        text-align: left;
        font-size: 13px;
    }
    .item.form-group > div {
        flex: 1;
        display: flex;
        flex-direction: column;
    }
    #registerDeviceModal .modal-body,
    #updateDeviceModal .modal-body {
        padding: 15px 20px;
    }
    #registerDeviceModal .modal-body .mb-3,
    #updateDeviceModal .modal-body .mb-3 {
        margin-bottom: 10px !important;
    }
    .item.form-group:last-of-type {
        margin-bottom: 8px;
    }
    .modal-body .item.form-group:last-child {
        margin-bottom: 0;
    }
    .modal-body .item.form-group .col-md-12.text-right {
        margin-top: 5px;
        padding-top: 5px;
    }
    .modal-body .row {
        margin-bottom: 0;
        margin-left: -8px;
        margin-right: -8px;
        display: flex;
        align-items: stretch;
    }
    .modal-body .row .col-md-6 {
        padding-left: 8px;
        padding-right: 8px;
        display: flex;
        flex-direction: column;
        position: relative;
    }
    .modal-body .row .col-md-6:first-child::after {
        content: '';
        position: absolute;
        right: 0;
        top: 0;
        bottom: 0;
        width: 1px;
        background-color: #e5e5e5;
        border-right: 1px solid #e5e5e5;
    }
    .modal-body .row .col-md-6:last-child {
        padding-left: 12px;
    }
    .modal-body .row .col-md-6 .item.form-group {
        flex: 1;
        display: flex;
        flex-direction: column;
    }
    .item.form-group label.required:after,
    .item.form-group label .required {
        color: #e74c3c;
    }
    .item.form-group label:empty {
        min-height: 20px;
        display: block;
    }
    .ln_solid {
        border-top: 1px solid #e5e5e5;
        margin: 10px 0;
    }
    .form-control {
        border-radius: 4px;
        border: 1px solid #ddd;
        padding: 6px 10px;
        font-size: 13px;
        transition: border-color 0.3s;
        height: 34px;
        width: 100%;
        box-sizing: border-box;
    }
    select.form-control {
        height: 34px;
        line-height: 20px;
    }
    .modal-body .item.form-group input[type="text"],
    .modal-body .item.form-group input[type="text"].form-control,
    .modal-body .item.form-group select.form-control {
        height: 34px;
    }
    .form-control:focus {
        border-color: #26B99A;
        box-shadow: 0 0 0 0.2rem rgba(38, 185, 154, 0.25);
        outline: none;
    }
    .btn-success {
        background-color: #26B99A;
        border-color: #26B99A;
        color: white;
        padding: 8px 16px;
        font-size: 13px;
        border-radius: 4px;
        transition: all 0.3s;
    }
    .btn-success:hover {
        background-color: #20a085;
        border-color: #20a085;
    }
    .btn-default {
        background-color: #f4f4f4;
        border-color: #ddd;
        color: #333;
        padding: 8px 16px;
        font-size: 13px;
        border-radius: 4px;
        transition: all 0.3s;
    }
    .btn-default:hover {
        background-color: #e6e6e6;
        border-color: #adadad;
    }
    .help-block {
        color: #737373;
        font-size: 11px;
        margin-top: 2px;
        min-height: 14px;
        line-height: 1.3;
        display: block;
        margin-bottom: 0;
    }
    .invalid-feedback {
        display: none;
        color: #e74c3c;
        font-size: 11px;
        margin-top: 2px;
        min-height: 14px;
        line-height: 1.3;
    }
    .modal-body .item.form-group .checkbox {
        margin-top: 4px;
        margin-bottom: 2px;
    }
    .modal-body .item.form-group .checkbox label {
        font-weight: normal;
        margin-bottom: 0;
        min-height: auto;
    }
    .modal-body .item.form-group > div {
        display: flex;
        flex-direction: column;
        justify-content: flex-start;
    }
    .modal-body .item.form-group label {
        text-align: left;
        margin-bottom: 5px;
    }
    @media (max-width: 768px) {
        .modal-body .row {
            flex-direction: column;
        }
        .modal-body .row .col-md-6 {
            width: 100%;
        }
    }
    .form-control.is-invalid {
        border-color: #e74c3c;
    }
    .form-control.is-invalid ~ .invalid-feedback {
        display: block;
    }
    .alert {
        padding: 15px;
        margin-bottom: 20px;
        border: 1px solid transparent;
        border-radius: 4px;
    }
    .alert-success {
        color: #3c763d;
        background-color: #dff0d8;
        border-color: #d6e9c6;
    }
    .alert-danger {
        color: #a94442;
        background-color: #f2dede;
        border-color: #ebccd1;
    }
    .panel_toolbox {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        margin-left: auto;
    }
    .add-device-btn {
        background: #1abb9c;
        color: #fff;
        border-radius: 20px;
        padding: 6px 18px;
        font-weight: 600;
        border: none;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        box-shadow: 0 6px 15px rgba(26, 187, 156, 0.25);
    }
    .add-device-btn:hover,
    .add-device-btn:focus {
        background: #148f77;
        color: #fff;
        text-decoration: none;
        transform: translateY(-1px);
        box-shadow: 0 8px 18px rgba(20, 143, 119, 0.3);
    }
    .device-status-nav {
        margin-bottom: 15px;
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }
    .device-status-filter {
        border-radius: 18px;
        padding: 6px 16px;
        border: 1px solid #d5d8dc;
        background: #fff;
        color: #4a5568;
        font-size: 13px;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        transition: all 0.2s ease;
    }
    .device-status-filter:hover {
        border-color: #1abb9c;
        color: #148f77;
    }
    .device-status-filter.active {
        background: #1abb9c;
        color: #fff;
        border-color: #1abb9c;
        box-shadow: 0 4px 12px rgba(26, 187, 156, 0.2);
    }
    .label-status {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.4px;
    }
    .label-status.online {
        background: rgba(26, 188, 156, 0.15);
        color: #148f77;
    }
    .label-status.offline {
        background: rgba(149, 165, 166, 0.2);
        color: #7f8c8d;
    }
    .label-status.faulty {
        background: rgba(231, 76, 60, 0.18);
        color: #c0392b;
    }
    .label-active {
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.4px;
    }
    .label-active.active {
        background: rgba(46, 204, 113, 0.18);
        color: #27ae60;
    }
    .label-active.inactive {
        background: rgba(241, 196, 15, 0.18);
        color: #d68910;
    }
    .table-responsive {
        overflow-x: auto;
    }
    .device-actions .btn {
        margin-right: 5px;
        margin-bottom: 5px;
        min-width: 28px;
        padding: 5px 8px;
        text-align: center;
    }
    .device-actions .btn i {
        margin: 0;
    }
    .device-empty-state {
        text-align: center;
        padding: 40px 20px;
        color: #95a5a6;
    }
    .device-empty-state i {
        font-size: 36px;
        margin-bottom: 12px;
        color: #bdc3c7;
    }
    @media (max-width: 992px) {
        .x_content {
            padding: 16px;
        }
        .panel_toolbox {
            justify-content: flex-start;
        }
        .device-status-nav {
            justify-content: flex-start;
        }
    }
    @media (max-width: 768px) {
        .x_title {
            flex-direction: column;
            align-items: flex-start;
            gap: 6px;
        }
        .panel_toolbox {
            width: 100%;
            padding-top: 6px;
        }
        .add-device-btn {
            width: 100%;
            justify-content: center;
        }
        .device-status-nav {
            flex-direction: column;
        }
        .device-status-filter {
            width: 100%;
            text-align: center;
        }
        .modal-dialog {
            margin: 15px;
        }
        .modal-dialog.modal-lg {
            max-width: 100%;
        }
    }
    @media (max-width: 576px) {
        .item.form-group {
            display: block;
        }
        .item.form-group .col-md-3,
        .item.form-group .col-sm-3,
        .item.form-group .col-md-6,
        .item.form-group .col-sm-6 {
            width: 100%;
            float: none;
        }
        .item.form-group .col-md-3,
        .item.form-group .col-sm-3 {
            margin-bottom: 6px;
            text-align: left;
        }
        .item.form-group .col-md-6,
        .item.form-group .col-sm-6 {
            padding-left: 0;
            padding-right: 0;
        }
        #devicesTable thead {
            display: none;
        }
        #devicesTable tbody tr {
            display: block;
            margin-bottom: 12px;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 12px 14px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.05);
        }
        #devicesTable tbody td {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: none !important;
            padding: 6px 0;
            font-size: 13px;
        }
        #devicesTable tbody td:before {
            content: attr(data-label);
            font-weight: 600;
            color: #4a5568;
            margin-right: 10px;
            text-align: left;
        }
        .device-actions .btn {
            width: auto;
            min-width: 28px;
            margin-right: 5px;
        }
        .device-actions .btn + .btn {
            margin-top: 0;
        }
    }
    /* DataTables Length Menu - Show X entries */
    .dataTables_length {
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .dataTables_length label {
        margin: 0;
        font-weight: 500;
        color: #555;
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }
    .dataTables_length select {
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 6px 30px 6px 10px;
        font-size: 13px;
        color: #555;
        background-color: #fff;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23555' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 8px center;
        background-size: 12px;
        appearance: none;
        cursor: pointer;
        transition: all 0.3s ease;
        min-width: 70px;
        height: 34px;
    }
    .dataTables_length select:hover {
        border-color: #26B99A;
    }
    .dataTables_length select:focus {
        border-color: #26B99A;
        outline: none;
        box-shadow: 0 0 0 0.2rem rgba(38, 185, 154, 0.25);
    }
    @media (max-width: 768px) {
        .dataTables_length {
            flex-direction: column;
            align-items: flex-start;
        }
        .dataTables_length label {
            width: 100%;
        }
        .dataTables_length select {
            width: 100%;
            max-width: 150px;
        }
    }
</style>
<body class="nav-md">
    <div class="container body">
      <div class="main_container">
            <?php include('../../components/sidebar.php'); ?>
            </div>
          </div>
        </div>
        <?php include('../../components/navigation.php')?>
        <div class="right_col" role="main"> 
                <div class="">
                    <div class="clearfix"></div>
                    <div class="row">
                        <div class="col-md-12 col-sm-12">
                            <div class="x_panel">
                                <div class="x_title">
                                    <h2><i class="fa fa-list"></i> My Devices <small>Manage and monitor your registered devices</small></h2>
                                    <div class="panel_toolbox">
                                        <button type="button" class="add-device-btn" data-toggle="modal" data-target="#registerDeviceModal">
                                            <i class="fa fa-plus"></i>
                                            Add Device
                                        </button>
                                    </div>
                                    <div class="clearfix"></div>
                                </div>
                                <div class="x_content">
                                    <div class="device-status-nav">
                                        <button type="button" class="device-status-filter active" data-status="">All</button>
                                        <button type="button" class="device-status-filter" data-status="Online">Online</button>
                                        <button type="button" class="device-status-filter" data-status="Offline">Offline</button>
                                        <button type="button" class="device-status-filter" data-status="Faulty">Faulty</button>
                                    </div>
                                    <div class="table-responsive">
                                        <table id="devicesTable" class="table table-striped table-bordered">
                                            <thead>
                                                <tr>
                                                    <th>Device Name</th>
                                                    <th>Device Number</th>
                                                    <th>Serial Number</th>
                                                    <th>Device Type</th>
                                                    <th>Status</th>
                                                    <th>Active</th>
                                                    <th>Barangay</th>
                                                    <th>Created At</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (!empty($devices)): ?>
                                                    <?php foreach ($devices as $device): ?>
                                                        <tr>
                                                            <td data-label="Device Name"><?php echo htmlspecialchars($device['device_name']); ?></td>
                                                            <td data-label="Device Number"><?php echo htmlspecialchars($device['device_number']); ?></td>
                                                            <td data-label="Serial Number"><?php echo htmlspecialchars($device['serial_number']); ?></td>
                                                            <td data-label="Device Type"><?php echo htmlspecialchars($device['device_type']); ?></td>
                                                            <td data-label="Status">
                                                                <?php
                                                                    $statusClass = strtolower($device['status']);
                                                                    $statusLabel = ucfirst($device['status']);
                                                                ?>
                                                                <span class="label-status <?php echo $statusClass; ?>"><?php echo htmlspecialchars($statusLabel); ?></span>
                                                            </td>
                                                            <td data-label="Active">
                                                                <?php if ((int)$device['is_active'] === 1): ?>
                                                                    <span class="label-active active">Active</span>
                                                                <?php else: ?>
                                                                    <span class="label-active inactive">Inactive</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td data-label="Barangay"><?php echo !empty($device['barangay_name']) ? htmlspecialchars($device['barangay_name']) : '<em>-</em>'; ?></td>
                                                            <td data-label="Created"><?php echo htmlspecialchars(date('M d, Y g:i A', strtotime($device['created_at']))); ?></td>
                                                            <td class="device-actions" data-label="Actions">
                                                                <?php $isActive = (int)$device['is_active']; ?>
                                                                <button 
                                                                    type="button" 
                                                                    class="btn btn-xs btn-primary update-device-btn"
                                                                    data-device-id="<?php echo (int)$device['device_id']; ?>"
                                                                    data-device-name="<?php echo htmlspecialchars($device['device_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                                                    data-device-number="<?php echo htmlspecialchars($device['device_number'], ENT_QUOTES, 'UTF-8'); ?>"
                                                                    data-serial-number="<?php echo htmlspecialchars($device['serial_number'], ENT_QUOTES, 'UTF-8'); ?>"
                                                                    data-device-type="<?php echo htmlspecialchars($device['device_type'], ENT_QUOTES, 'UTF-8'); ?>"
                                                                    data-status="<?php echo htmlspecialchars($device['status'], ENT_QUOTES, 'UTF-8'); ?>"
                                                                    data-is-active="<?php echo $isActive; ?>"
                                                                    data-barangay-id="<?php echo $device['barangay_id'] !== null ? (int)$device['barangay_id'] : ''; ?>"
                                                                    title="Update Device"
                                                                >
                                                                    <i class="fa fa-pencil"></i>
                                                                </button>
                                                                <button 
                                                                    type="button" 
                                                                    class="btn btn-xs toggle-device-btn <?php echo $isActive ? 'btn-warning' : 'btn-success'; ?>"
                                                                    data-device-id="<?php echo (int)$device['device_id']; ?>"
                                                                    data-is-active="<?php echo $isActive; ?>"
                                                                    title="<?php echo $isActive ? 'Disable Device' : 'Enable Device'; ?>"
                                                                >
                                                                    <i class="fa fa-power-off"></i>
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <tr>
                                                        <td colspan="9" data-label="Devices">
                                                            <div class="device-empty-state">
                                                                <i class="fa fa-tablet"></i>
                                                                <p>You haven&apos;t registered any devices yet. Click the <strong>Add Device</strong> button to get started.</p>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal fade" id="registerDeviceModal" tabindex="-1" role="dialog" aria-labelledby="registerDeviceModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-lg" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h4 class="modal-title" id="registerDeviceModalLabel">
                                    <i class="fa fa-tablet"></i> Register New Device
                                </h4>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body">
                                <div id="alert-container" class="mb-3"></div>
                                <form id="deviceForm" class="form-horizontal form-label-left" novalidate>
                                    <input type="hidden" name="csrf_token" value="<?php echo escapeHtml($csrf_token); ?>">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="item form-group">
                                                <label class="col-form-label label-align required" for="device_name">
                                                    Device Name <span class="required">*</span>
                                                </label>
                                                <div>
                                                    <input type="text" class="form-control" id="device_name" name="device_name" required="required">
                                                    <span class="help-block">Enter a descriptive name for your device</span>
                                                    <div class="invalid-feedback">Please provide a device name</div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="item form-group">
                                                <label class="col-form-label label-align required" for="device_number">
                                                    Device Number <span class="required">*</span>
                                                </label>
                                                <div>
                                                    <input type="text" class="form-control" id="device_number" name="device_number" required="required">
                                                    <span class="help-block">Unique identifier for this device</span>
                                                    <div class="invalid-feedback">Please provide a device number</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="item form-group">
                                                <label class="col-form-label label-align required" for="serial_number">
                                                    Serial Number <span class="required">*</span>
                                                </label>
                                                <div>
                                                    <input type="text" class="form-control" id="serial_number" name="serial_number" required="required">
                                                    <span class="help-block">Device serial number (must be unique)</span>
                                                    <div class="invalid-feedback">Please provide a serial number</div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="item form-group">
                                                <label class="col-form-label label-align" for="device_type">Device Type</label>
                                                <div>
                                                    <input type="text" class="form-control" id="device_type" name="device_type" value="FIREGUARD DEVICE">
                                                    <span class="help-block">Type of device (default: FIREGUARD DEVICE)</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="item form-group">
                                                <label class="col-form-label label-align" for="status">Status</label>
                                                <div>
                                                    <select class="form-control" id="status" name="status">
                                                        <option value="offline" selected>Offline</option>
                                                        <option value="online">Online</option>
                                                        <option value="faulty">Faulty</option>
                                                    </select>
                                                    <span class="help-block">Current device status</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="item form-group">
                                                <label class="col-form-label label-align" for="barangay_id">Assign to Barangay</label>
                                                <div>
                                                    <select class="form-control" id="barangay_id" name="barangay_id">
                                                        <option value="">-- Select Barangay (Optional) --</option>
                                                        <?php foreach ($barangays as $barangay): ?>
                                                            <option value="<?php echo $barangay['id']; ?>">
                                                                <?php echo htmlspecialchars($barangay['barangay_name']); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <span class="help-block">Link this device to a barangay (optional)</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="item form-group">
                                                <label class="col-form-label label-align" for="is_active">Device Status</label>
                                                <div>
                                                    <div class="checkbox">
                                                        <label>
                                                            <input type="checkbox" id="is_active" name="is_active" value="1" checked>
                                                            Device is Active
                                                        </label>
                                                    </div>
                                                    <span class="help-block">Uncheck to deactivate this device</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <!-- Empty column for alignment -->
                                            <div class="item form-group" style="visibility: hidden;">
                                                <label class="col-form-label label-align"></label>
                                                <div>
                                                    <div style="height: 34px;"></div>
                                                    <span class="help-block"></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="ln_solid"></div>
                                    <div class="item form-group">
                                        <div class="col-md-12 text-right">
                                            <button type="button" class="btn btn-default" onclick="resetForm()">Reset</button>
                                            <button type="submit" class="btn btn-success">Register Device</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal fade" id="updateDeviceModal" tabindex="-1" role="dialog" aria-labelledby="updateDeviceModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-lg" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h4 class="modal-title" id="updateDeviceModalLabel">
                                    <i class="fa fa-pencil"></i> Update Device
                                </h4>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body">
                                <div id="update-alert-container" class="mb-3"></div>
                                <form id="updateDeviceForm" class="form-horizontal form-label-left" novalidate>
                                    <input type="hidden" name="csrf_token" value="<?php echo escapeHtml($csrf_token); ?>">
                                    <input type="hidden" id="update_device_id" name="device_id">

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="item form-group">
                                                <label class="col-form-label label-align required" for="update_device_name">
                                                    Device Name <span class="required">*</span>
                                                </label>
                                                <div>
                                                    <input type="text" class="form-control" id="update_device_name" name="device_name" required="required">
                                                    <span class="help-block">Enter a descriptive name for your device.</span>
                                                    <div class="invalid-feedback">Please provide a device name</div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="item form-group">
                                                <label class="col-form-label label-align required" for="update_device_number">
                                                    Device Number <span class="required">*</span>
                                                </label>
                                                <div>
                                                    <input type="text" class="form-control" id="update_device_number" name="device_number" required="required">
                                                    <span class="help-block">Unique identifier for this device</span>
                                                    <div class="invalid-feedback">Please provide a device number</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="item form-group">
                                                <label class="col-form-label label-align required" for="update_serial_number">
                                                    Serial Number <span class="required">*</span>
                                                </label>
                                                <div>
                                                    <input type="text" class="form-control" id="update_serial_number" name="serial_number" required="required">
                                                    <span class="help-block">Device serial number (must be unique)</span>
                                                    <div class="invalid-feedback">Please provide a serial number</div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="item form-group">
                                                <label class="col-form-label label-align" for="update_device_type">Device Type</label>
                                                <div>
                                                    <input type="text" class="form-control" id="update_device_type" name="device_type" value="FIREGUARD DEVICE">
                                                    <span class="help-block">Type of device (default: FIREGUARD DEVICE)</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="item form-group">
                                                <label class="col-form-label label-align" for="update_status">Status</label>
                                                <div>
                                                    <select class="form-control" id="update_status" name="status">
                                                        <option value="offline">Offline</option>
                                                        <option value="online">Online</option>
                                                        <option value="faulty">Faulty</option>
                                                    </select>
                                                    <span class="help-block">Current device status</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="item form-group">
                                                <label class="col-form-label label-align" for="update_barangay_id">Assign to Barangay</label>
                                                <div>
                                                    <select class="form-control" id="update_barangay_id" name="barangay_id">
                                                        <option value="">-- Select Barangay (Optional) --</option>
                                                        <?php foreach ($barangays as $barangay): ?>
                                                            <option value="<?php echo $barangay['id']; ?>">
                                                                <?php echo htmlspecialchars($barangay['barangay_name']); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <span class="help-block">Link this device to a barangay (optional)</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="item form-group">
                                                <label class="col-form-label label-align" for="update_is_active">Device Status</label>
                                                <div>
                                                    <div class="checkbox">
                                                        <label>
                                                            <input type="checkbox" id="update_is_active" name="is_active" value="1">
                                                            Device is Active
                                                        </label>
                                                    </div>
                                                    <span class="help-block">Uncheck to deactivate this device</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <!-- Empty column for alignment -->
                                            <div class="item form-group" style="visibility: hidden;">
                                                <label class="col-form-label label-align"></label>
                                                <div>
                                                    <div style="height: 34px;"></div>
                                                    <span class="help-block"></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="ln_solid"></div>
                                    <div class="item form-group">
                                        <div class="col-md-12 text-right">
                                            <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-success">Update Device</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
                <?php include('../../components/footer.php'); ?>
            </div>
        </div>
    </div>
    <?php include('../../components/scripts.php'); ?>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>
    <script>
        $(document).ready(function() {
            let devicesTable = null;
            if ($('#devicesTable').length && $.fn.DataTable) {
                devicesTable = $('#devicesTable').DataTable({
                    pageLength: 10,
                    order: [[0, 'asc']],
                    autoWidth: false,
                    language: {
                        search: "_INPUT_",
                        searchPlaceholder: "Search devices...",
                        lengthMenu: "Show _MENU_ entries",
                        zeroRecords: "No matching devices found",
                        info: "Showing _START_ to _END_ of _TOTAL_ devices",
                        infoEmpty: "Showing 0 to 0 of 0 devices",
                        infoFiltered: "(filtered from _MAX_ total devices)",
                        paginate: {
                            first: "First",
                            last: "Last",
                            next: "Next",
                            previous: "Prev"
                        }
                    },
                    columnDefs: [
                        { orderable: false, targets: [8] }
                    ]
                });

                $('.device-status-filter').on('click', function() {
                    const status = $(this).data('status');
                    $('.device-status-filter').removeClass('active');
                    $(this).addClass('active');
                    if (status) {
                        devicesTable.column(4).search(status).draw();
                    } else {
                        devicesTable.column(4).search('').draw();
                    }
                });
            }

            const validationStates = {
                create: { device_number: null, serial_number: null },
                update: { device_number: null, serial_number: null }
            };
            window.validationStates = validationStates;

            $('#registerDeviceModal').on('shown.bs.modal', function () {
                $('#device_name').trigger('focus');
            });

            $('#registerDeviceModal').on('hidden.bs.modal', function () {
                resetForm();
                $('#alert-container').empty();
            });

            $('#updateDeviceModal').on('shown.bs.modal', function () {
                $('#update_device_name').trigger('focus');
            });

            $('#updateDeviceModal').on('hidden.bs.modal', function () {
                resetUpdateForm();
                $('#update-alert-container').empty();
            });

            const uniqueFieldSelector = '#device_number, #serial_number, #update_device_number, #update_serial_number';

            $(document).on('blur', uniqueFieldSelector, function() {
                const field = $(this);
                const value = field.val().trim();
                const form = field.closest('form');
                const context = form.attr('id') === 'updateDeviceForm' ? 'update' : 'create';
                const fieldName = field.attr('name');

                if (value === '') {
                    setFieldError(field, 'This field is required.');
                    validationStates[context][fieldName] = false;
                    return;
                }

                validateField(field);
            });

            $(document).on('input', uniqueFieldSelector, function() {
                const field = $(this);
                const form = field.closest('form');
                const context = form.attr('id') === 'updateDeviceForm' ? 'update' : 'create';
                const fieldName = field.attr('name');

                if (field.hasClass('is-invalid')) {
                    clearFieldError(field);
                }
                validationStates[context][fieldName] = null;
            });

            $('#deviceForm').on('submit', function(e) {
                e.preventDefault();

                const createState = validationStates.create;
                if (createState.device_number === false || createState.serial_number === false) {
                    showFeedback('error', 'Validation Error', 'Please resolve the highlighted field errors before submitting.');
                    if (createState.device_number === false) {
                        validateField($('#device_number'));
                    }
                    if (createState.serial_number === false) {
                        validateField($('#serial_number'));
                    }
                    return false;
                }

                if (!this.checkValidity()) {
                    e.stopPropagation();
                    $(this).addClass('was-validated');
                    return false;
                }

                const submitBtn = $(this).find('button[type="submit"]');
                const originalText = submitBtn.html();
                submitBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Registering...');

                // Get CSRF token from form
                const csrfToken = $('#deviceForm input[name="csrf_token"]').val();
                if (!csrfToken) {
                    showFeedback('error', 'Security Error', 'CSRF token is missing. Please refresh the page and try again.');
                    submitBtn.prop('disabled', false).html(originalText);
                    return false;
                }

                const formData = {
                    csrf_token: csrfToken,
                    device_name: $('#device_name').val(),
                    device_number: $('#device_number').val(),
                    serial_number: $('#serial_number').val(),
                    device_type: $('#device_type').val(),
                    status: $('#status').val(),
                    barangay_id: $('#barangay_id').val() || null,
                    is_active: $('#is_active').is(':checked') ? 1 : 0
                };

                $.ajax({
                    url: 'main.php',
                    method: 'POST',
                    data: formData,
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            showFeedback('success', 'Device Registered', response.message || 'Device registered successfully!');
                            $('#registerDeviceModal').modal('hide');
                            submitBtn.prop('disabled', false).html(originalText);

                            setTimeout(function() {
                                window.location.reload();
                            }, 1500);
                        } else {
                            showFeedback('error', 'Registration Failed', response.message || 'An error occurred while registering the device.');
                            submitBtn.prop('disabled', false).html(originalText);
                        }
                    },
                    error: function(xhr) {
                        let errorMessage = 'An error occurred while registering the device.';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMessage = xhr.responseJSON.message;
                        }
                        showFeedback('error', 'Registration Failed', errorMessage);
                        submitBtn.prop('disabled', false).html(originalText);
                    }
                });
            });

            $('#updateDeviceForm').on('submit', function(e) {
                e.preventDefault();

                const updateState = validationStates.update;
                if (updateState.device_number === false || updateState.serial_number === false) {
                    showFeedback('error', 'Validation Error', 'Please resolve the highlighted field errors before submitting.');
                    if (updateState.device_number === false) {
                        validateField($('#update_device_number'));
                    }
                    if (updateState.serial_number === false) {
                        validateField($('#update_serial_number'));
                    }
                    return false;
                }

                if (!this.checkValidity()) {
                    e.stopPropagation();
                    $(this).addClass('was-validated');
                    return false;
                }

                const submitBtn = $(this).find('button[type="submit"]');
                const originalText = submitBtn.html();
                submitBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Updating...');

                // Get CSRF token from form
                const csrfToken = $('#updateDeviceForm input[name="csrf_token"]').val();
                if (!csrfToken) {
                    showFeedback('error', 'Security Error', 'CSRF token is missing. Please refresh the page and try again.');
                    submitBtn.prop('disabled', false).html(originalText);
                    return false;
                }

                const formData = {
                    action: 'update_device',
                    csrf_token: csrfToken,
                    device_id: $('#update_device_id').val(),
                    device_name: $('#update_device_name').val(),
                    device_number: $('#update_device_number').val(),
                    serial_number: $('#update_serial_number').val(),
                    device_type: $('#update_device_type').val(),
                    status: $('#update_status').val(),
                    barangay_id: $('#update_barangay_id').val() || null,
                    is_active: $('#update_is_active').is(':checked') ? 1 : 0
                };

                $.ajax({
                    url: 'main.php',
                    method: 'POST',
                    data: formData,
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            showFeedback('success', 'Device Updated', response.message || 'Device updated successfully.');
                            $('#updateDeviceModal').modal('hide');
                            submitBtn.prop('disabled', false).html(originalText);

                            setTimeout(function() {
                                window.location.reload();
                            }, 1200);
                        } else {
                            showFeedback('error', 'Update Failed', response.message || 'An error occurred while updating the device.');
                            submitBtn.prop('disabled', false).html(originalText);
                        }
                    },
                    error: function(xhr) {
                        let errorMessage = 'An error occurred while updating the device.';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMessage = xhr.responseJSON.message;
                        }
                        showFeedback('error', 'Update Failed', errorMessage);
                        submitBtn.prop('disabled', false).html(originalText);
                    }
                });
            });

            $(document).on('click', '.update-device-btn', function() {
                resetUpdateForm();
                const button = $(this);

                $('#update_device_id').val(button.data('deviceId'));
                $('#update_device_name').val(button.data('deviceName'));
                $('#update_device_number').val(button.data('deviceNumber'));
                $('#update_serial_number').val(button.data('serialNumber'));
                $('#update_device_type').val(button.data('deviceType') || 'FIREGUARD DEVICE');
                $('#update_status').val((button.data('status') || 'offline').toLowerCase());
                const barangayId = button.data('barangayId');
                $('#update_barangay_id').val(barangayId !== '' ? barangayId : '');
                $('#update_is_active').prop('checked', Number(button.data('isActive')) === 1);

                $('#updateDeviceModal').modal('show');
            });

            $(document).on('click', '.toggle-device-btn', function() {
                const button = $(this);
                const deviceId = button.data('deviceId');
                const currentActive = Number(button.data('isActive'));
                const nextActive = currentActive === 1 ? 0 : 1;
                const actionVerb = currentActive === 1 ? 'Disable' : 'Enable';

                Swal.fire({
                    title: `${actionVerb} Device`,
                    html: `Are you sure you want to ${actionVerb.toLowerCase()} this device?`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: currentActive === 1 ? '#d33' : '#1abb9c',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: actionVerb,
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (!result.isConfirmed) {
                        return;
                    }

                    button.prop('disabled', true);

                    // Get CSRF token from any form on the page
                    const csrfToken = $('input[name="csrf_token"]').first().val();
                    if (!csrfToken) {
                        showFeedback('error', 'Security Error', 'CSRF token is missing. Please refresh the page and try again.');
                        button.prop('disabled', false);
                        return;
                    }

                    $.ajax({
                        url: 'main.php',
                        method: 'POST',
                        dataType: 'json',
                        data: {
                            action: 'toggle_device_status',
                            csrf_token: csrfToken,
                            device_id: deviceId,
                            is_active: nextActive
                        },
                        success: function(response) {
                            if (response.status === 'success') {
                                const message = nextActive === 1 ? 'Device enabled successfully.' : 'Device disabled successfully.';
                                showFeedback('success', 'Status Updated', message);
                                setTimeout(function() {
                                    window.location.reload();
                                }, 1000);
                            } else {
                                showFeedback('error', 'Update Failed', response.message || 'Unable to update device status.');
                                button.prop('disabled', false);
                            }
                        },
                        error: function(xhr) {
                            let errorMessage = 'Unable to update device status.';
                            if (xhr.responseJSON && xhr.responseJSON.message) {
                                errorMessage = xhr.responseJSON.message;
                            }
                            showFeedback('error', 'Update Failed', errorMessage);
                            button.prop('disabled', false);
                        }
                    });
                });
            });

            function validateField(fieldElement) {
                const form = fieldElement.closest('form');
                const context = form.attr('id') === 'updateDeviceForm' ? 'update' : 'create';
                const fieldName = fieldElement.attr('name');
                const value = fieldElement.val().trim();
                const payload = {
                    action: 'validate_field',
                    field: fieldName,
                    value: value
                };

                const serialField = form.find('[name="serial_number"]');
                const deviceField = form.find('[name="device_number"]');

                if (fieldName === 'device_number') {
                    payload.serial_number = serialField.val().trim();
                } else if (fieldName === 'serial_number') {
                    payload.device_number = deviceField.val().trim();
                }

                if (context === 'update') {
                    payload.device_id = $('#update_device_id').val();
                }

                fieldElement.addClass('is-validating');

                $.ajax({
                    url: 'main.php',
                    method: 'POST',
                    dataType: 'json',
                    data: payload,
                    success: function(response) {
                        if (response.valid) {
                            clearFieldError(fieldElement, true);
                            validationStates[context][fieldName] = true;
                        } else {
                            setFieldError(fieldElement, response.message);
                            validationStates[context][fieldName] = false;
                        }
                    },
                    error: function() {
                        setFieldError(fieldElement, 'Unable to validate right now. Please try again.');
                        validationStates[context][fieldName] = false;
                    },
                    complete: function() {
                        fieldElement.removeClass('is-validating');
                    }
                });
            }
        });
        
        function resetForm() {
            const form = $('#deviceForm');
            if (!form.length) {
                return;
            }
            form[0].reset();
            form.removeClass('was-validated');
            form.find('.form-control').removeClass('is-invalid is-valid');
            $('#device_type').val('FIREGUARD DEVICE');
            $('#status').val('offline');
            $('#is_active').prop('checked', true);
            $('#barangay_id').val('');
            $('#alert-container').empty();
            form.find('button[type="submit"]').prop('disabled', false).html('Register Device');
            if (window.validationStates && window.validationStates.create) {
                window.validationStates.create.device_number = null;
                window.validationStates.create.serial_number = null;
            }
        }

        function resetUpdateForm() {
            const form = $('#updateDeviceForm');
            if (!form.length) {
                return;
            }
            form[0].reset();
            form.removeClass('was-validated');
            form.find('.form-control').removeClass('is-invalid is-valid');
            form.find('button[type="submit"]').prop('disabled', false).html('Update Device');
            if (window.validationStates && window.validationStates.update) {
                window.validationStates.update.device_number = null;
                window.validationStates.update.serial_number = null;
            }
        }

        function setFieldError(fieldElement, message) {
            fieldElement.addClass('is-invalid');
            fieldElement.removeClass('is-valid');
            fieldElement.siblings('.invalid-feedback').text(message);
        }

        function clearFieldError(fieldElement, markValid) {
            fieldElement.removeClass('is-invalid');
            if (markValid) {
                fieldElement.addClass('is-valid');
            } else {
                fieldElement.removeClass('is-valid');
            }
            fieldElement.siblings('.invalid-feedback').text('');
        }
        
        function showFeedback(type, title, message) {
            // Escape HTML to prevent XSS
            const escapeHtml = function(text) {
                const map = {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                };
                return (text || '').replace(/[&<>"']/g, m => map[m]);
            };
            
            const safeTitle = escapeHtml(title || '');
            const safeMessage = escapeHtml(message || '').replace(/\|/g, '<br>'); // Convert | separator to <br>
            
            if (typeof Swal !== 'undefined' && Swal.fire) {
                const icon = type === 'success' ? 'success' : 'error';
                Swal.fire({
                    icon: icon,
                    title: safeTitle,
                    html: safeMessage,
                    confirmButtonText: 'OK'
                });
                return;
            }
            showLegacyAlert(type, safeTitle, safeMessage);
        }

        function showLegacyAlert(type, title, message) {
            const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
            const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
            const alertHtml = `
                <div class="alert ${alertClass} alert-dismissible fade in" role="alert">
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">×</span>
                    </button>
                    <strong><i class="fa ${icon}"></i> ${title ? title + ': ' : ''}</strong>${message}
                </div>
            `;
            $('#alert-container').html(alertHtml);

            setTimeout(function() {
                $('#alert-container .alert').fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        }
    </script>
</body>
</html>

