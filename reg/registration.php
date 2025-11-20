<?php
// Enable detailed error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Clear registration session data if not submitting a form (i.e., on page reload or direct visit)
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !isset($_GET['step'])) {
    unset($_SESSION['reg_data']);
}

// Database connection
require_once 'db_config.php';

// Security functions
require_once 'security_functions.php';

// Check if required tables exist
try {
    $conn = getDatabaseConnection();
    $tables = ['users', 'admin_devices', 'devices', 'buildings'];
    foreach ($tables as $table) {
        $stmt = $conn->prepare("SHOW TABLES LIKE '" . $table . "'");
        $stmt->execute();
        if ($stmt->rowCount() == 0) {
            throw new Exception("Required table '$table' does not exist in the database.");
        }
    }
} catch (Exception $e) {
    error_log("Database table check failed: " . $e->getMessage());
    $errors['system'] = "System configuration error. Please contact support.";
}

// Ensure Composer's autoloader is loaded for PHPMailer
require_once dirname(__DIR__) . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Initialize variables
$errors = [];
$success = '';
$barangays_list = [];

// Handle session errors from redirects
if (isset($_SESSION['form_errors'])) {
    $errors = $_SESSION['form_errors'];
    unset($_SESSION['form_errors']);
}

// Load barangays for dropdown (optional; UI convenience only)
try {
    $connTmp = getDatabaseConnection();
    $stmtTmp = $connTmp->query("SELECT id, barangay_name FROM barangay ORDER BY barangay_name");
    $barangays_list = $stmtTmp->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log('Failed to load barangays: ' . $e->getMessage());
}

// --- [START: Helper Functions for Validation and Email] ---
function cleanBarangayName($name) {
    if (empty($name)) return '';
    // Remove common prefixes (case-insensitive)
    $name = preg_replace('/^(barangay|brgy\.?|br\.?)\s+/i', '', $name);
    // Remove anything after comma (city/municipality names)
    $name = explode(',', $name)[0];
    // Trim whitespace
    $name = trim($name);
    return $name;
}

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

function get_active_geo_fences() {
    try {
        $conn = getDatabaseConnection();
        $stmt = $conn->prepare("SELECT id, city_name, country_code, ST_AsText(polygon) as polygon_wkt FROM geo_fences WHERE is_active = 1");
        $stmt->execute();
        $fences = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $result = [];
        foreach ($fences as $fence) {
            // Parse the WKT polygon to extract coordinates
            $polygon_wkt = $fence['polygon_wkt'];
            if (preg_match('/POLYGON\(\(([^)]+)\)\)/', $polygon_wkt, $matches)) {
                $coords_string = $matches[1];
                $coords = [];
                $pairs = explode(',', $coords_string);
                foreach ($pairs as $pair) {
                    $pair = trim($pair);
                    if (preg_match('/([0-9.-]+)\s+([0-9.-]+)/', $pair, $coord_matches)) {
                        $coords[] = [floatval($coord_matches[2]), floatval($coord_matches[1])]; // [lat, lng]
                    }
                }
                $result[] = [
                    'id' => $fence['id'],
                    'city_name' => $fence['city_name'],
                    'country_code' => $fence['country_code'],
                    'polygon' => $coords
                ];
            }
        }
        return $result;
    } catch (Exception $e) {
        error_log("Error fetching geo fences: " . $e->getMessage());
        return [];
    }
}

function point_in_any_geo_fence($lat, $lng, $geo_fences) {
    foreach ($geo_fences as $fence) {
        if (point_in_polygon($lat, $lng, $fence['polygon'])) {
            return ['in_fence' => true, 'fence' => $fence];
        }
    }
    return ['in_fence' => false, 'fence' => null];
}

function point_in_polygon($lat, $lng, $polygon) {
    $inside = false;
    $n = count($polygon);
    for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
        $xi = $polygon[$i][0]; $yi = $polygon[$i][1];
        $xj = $polygon[$j][0]; $yj = $polygon[$j][1];
        $intersect = (($yi > $lng) != ($yj > $lng)) &&
            ($lat < ($xj - $xi) * ($lng - $yi) / ($yj - $yi) + $xi);
        if ($intersect) $inside = !$inside;
    }
    return $inside;
}

function send_verification_email($to, $token) {
    // PHPMailer is loaded via Composer's autoloader
    if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        error_log('PHPMailer class not found.');
        return false;
    }
    $mail = new PHPMailer(true);
    try {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
        $verifyLink = $protocol . $_SERVER['HTTP_HOST'] . "/reg/verify_email.php?token=" . urlencode($token) . "&email=" . urlencode($to);
        $mail->isSMTP();
        $mail->Host       = 'smtp.hostinger.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'fireguard@bccbsis.com';
        $mail->Password   = '1j/EIh?7Q';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->setFrom('noreply@firedetectionsystem.com', 'Fire Detection System');
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = 'Activate Your Account';
        $mail->Body    = "
            <h2>Welcome to Fire Detection System!</h2>
            <p>Thank you for registering with our Real-Time IoT-Based Fire Detection System.</p>
            <p>To activate your account and start monitoring your buildings, please verify your email address by clicking the button below:</p>
            <a href='$verifyLink' style='padding:10px 20px;background:#28a745;color:#fff;text-decoration:none;border-radius:4px;'>Verify My Account</a>
            <p>This verification link will expire in 24 hours for security reasons.</p>
            <p>If you did not register for this account, please ignore this message.</p>
        ";
        $mail->AltBody = "Welcome to Fire Detection System! To activate your account, please verify your email using this link: $verifyLink (valid for 24 hours)";

        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ],
        ];
        return $mail->send();
    } catch (Exception $e) {
        error_log("Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}
// --- [END: Helper Functions for Validation and Email] ---

// Process registration form
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Security checks: CSRF, Rate Limiting, Honeypot
        $csrf_token = $_POST['csrf_token'] ?? '';
        if (!validate_csrf_token($csrf_token)) {
            $errors['security'] = "Invalid security token. Please refresh the page and try again.";
            $_SESSION['form_errors'] = $errors;
            header("Location: registration.php?step=" . ($_GET['step'] ?? 'personal') . "&error=security");
            exit();
        }
        
        // Check honeypot (bot detection)
        if (check_honeypot($_POST)) {
            error_log("Bot detected from IP: " . get_client_ip());
            $errors['security'] = "Invalid request detected.";
            $_SESSION['form_errors'] = $errors;
            header("Location: registration.php?step=" . ($_GET['step'] ?? 'personal') . "&error=security");
            exit();
        }
        
        // Rate limiting check
        $rate_limit = check_rate_limit('registration', 5, 3600); // 5 attempts per hour
        if (!$rate_limit['allowed']) {
            $reset_time = date('H:i', $rate_limit['reset_time']);
            $errors['rate_limit'] = "Too many registration attempts. Please try again after {$reset_time}.";
            $_SESSION['form_errors'] = $errors;
            header("Location: registration.php?step=" . ($_GET['step'] ?? 'personal') . "&error=rate_limit");
            exit();
        }
        
        // Validate and process each step
        if (isset($_POST['personal_info_submit'])) {
            // Step 1: Personal Information
            $fullname = trim($_POST['fullname'] ?? '');
            $birthdate = trim($_POST['birthdate'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $contact = trim($_POST['contact'] ?? '');
            
            // Validate inputs
            if (empty($fullname)) {
                $errors['fullname'] = "Full name is required";
            }
            if (empty($birthdate)) {
                $errors['birthdate'] = "Birthdate is required";
            } else {
                $age = date_diff(date_create($birthdate), date_create('today'))->y;
                if ($age < 18) {
                    $errors['birthdate'] = "You must be at least 18 years old";
                }
            }
            if (empty($email)) {
                $errors['email'] = "Email is required";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = "Invalid email format";
            } else {
                // Check if email exists using PDO
                $conn = getDatabaseConnection();
                $stmt = $conn->prepare("SELECT user_id FROM users WHERE email_address = ?");
                $stmt->execute([$email]);
                if ($stmt->rowCount() > 0) {
                    $errors['email'] = "Email already registered";
                }
            }
            
            if (empty($contact)) {
                $errors['contact'] = "Contact number is required";
            } elseif (!preg_match('/^09\d{9}$/', $contact)) {
                $errors['contact'] = "Contact number must start with 09 and be exactly 11 digits (e.g., 09123456789)";
            } else {
                // Check if contact number already exists in users table
                $conn = getDatabaseConnection();
                $stmt = $conn->prepare("SELECT user_id FROM users WHERE contact_number = ?");
                $stmt->execute([$contact]);
                if ($stmt->rowCount() > 0) {
                    $errors['contact'] = "Contact number already registered";
                }
            }
            
            if (empty($errors)) {
                $_SESSION['reg_data'] = [
                    'fullname' => $fullname,
                    'birthdate' => $birthdate,
                    'age' => $age,
                    'email' => $email,
                    'contact' => $contact
                ];
                header("Location: registration.php?step=location");
                exit();
            } else {
                // Store errors in session for display
                $_SESSION['form_errors'] = $errors;
                header("Location: registration.php?step=personal&error=validation");
                exit();
            }
        }
        elseif (isset($_POST['location_submit'])) {
            // Step 2: Location Information
            $latitude = trim($_POST['latitude'] ?? '');
            $longitude = trim($_POST['longitude'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $building_type = trim($_POST['building_type'] ?? '');
            $building_name = trim($_POST['building_name'] ?? '');
            $barangay = trim($_POST['barangay'] ?? '');
            
            // Check if address is provided
            if (empty($address)) {
                $errors['address'] = "Address is required";
            }
            
            // Check if coordinates are provided (either from map click or automatic validation)
            if (empty($latitude) || empty($longitude)) {
                $errors['location'] = "Please select a location on the map or enter a valid address for automatic validation";
            }
            
            if (empty($building_type)) {
                $errors['building_type'] = "Building type is required";
            }
            // Optionally require building_name
            if (empty($building_name)) {
                $building_name = 'Primary Residence';
            }

            // Barangay required
            if (empty($barangay)) {
                $errors['barangay'] = "Barangay is required";
            }

            // Ensure barangay matches the full address (case-insensitive whole-word check)
            if (empty($errors) && !empty($barangay) && !empty($address)) {
                $addrNorm = strtolower(preg_replace('/[\s,]+/', ' ', $address));
                $brgyNorm = strtolower(trim($barangay));
                if (!preg_match('/\\b' . preg_quote($brgyNorm, '/') . '\\b/', $addrNorm)) {
                    $errors['barangay'] = "Barangay must match your full address";
                }
            }

            // GEO-FENCING: Check against active geo-fences from database
            if (empty($errors) && !empty($latitude) && !empty($longitude)) {
                $geo_fences = get_active_geo_fences();
                if (empty($geo_fences)) {
                    $errors['location'] = "No active geo-fences configured. Registration is currently disabled. Please contact support.";
                } else {
                    $fence_check = point_in_any_geo_fence(floatval($latitude), floatval($longitude), $geo_fences);
                    if (!$fence_check['in_fence']) {
                        $allowed_cities = array_map(function($fence) {
                            return $fence['city_name'];
                        }, $geo_fences);
                        $errors['location'] = "Registration is only allowed within the following areas: " . implode(', ', $allowed_cities) . ". Please select a location within these boundaries.";
                    }
                }
            }

            if (empty($errors)) {
                $_SESSION['reg_data']['latitude'] = $latitude;
                $_SESSION['reg_data']['longitude'] = $longitude;
                $_SESSION['reg_data']['address'] = $address;
                $_SESSION['reg_data']['building_type'] = $building_type;
                $_SESSION['reg_data']['building_name'] = $building_name;
                $_SESSION['reg_data']['barangay'] = $barangay;

                // Insert/update barangay coordinates and get barangay_id
                try {
                    $conn = getDatabaseConnection();
                    $stmt = $conn->prepare("INSERT INTO barangay (barangay_name, latitude, longitude) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE latitude = VALUES(latitude), longitude = VALUES(longitude)");
                    $stmt->execute([$barangay, $latitude, $longitude]);
                    
                    // Get the barangay_id (either from insert or existing record)
                    $stmt = $conn->prepare("SELECT id FROM barangay WHERE barangay_name = ?");
                    $stmt->execute([$barangay]);
                    $barangay_result = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($barangay_result) {
                        $_SESSION['reg_data']['barangay_id'] = $barangay_result['id'];
                        error_log('Barangay ID stored in session: ' . $barangay_result['id'] . ' for barangay: ' . $barangay);
                    } else {
                        error_log('Failed to retrieve barangay_id for: ' . $barangay);
                    }
                } catch (Exception $e) {
                    error_log('Barangay upsert failed: ' . $e->getMessage());
                }
                header("Location: registration.php?step=device");
                exit();
            } else {
                // Store errors in session for display
                $_SESSION['form_errors'] = $errors;
                header("Location: registration.php?step=location&error=validation");
                exit();
            }
        }
        elseif (isset($_POST['device_submit'])) {
            // Step 3: Device Registration
            $device_number = trim($_POST['device_number'] ?? '');
            $serial_number = trim($_POST['serial_number'] ?? '');
            $device_barangay_id = trim($_POST['device_barangay_id'] ?? '');
            
            // Auto-fill device_barangay_id from location step's barangay_id if empty
            if (empty($device_barangay_id) && isset($_SESSION['reg_data']['barangay_id'])) {
                $device_barangay_id = $_SESSION['reg_data']['barangay_id'];
            }
            
            if (empty($device_number)) {
                $errors['device_number'] = "Device number is required";
            }
            
            if (empty($serial_number)) {
                $errors['serial_number'] = "Serial number is required";
            }
            
            if (empty($device_barangay_id)) {
                $errors['device_barangay_id'] = "Barangay is required";
            } else {
                // Validate that the barangay_id exists in the database
                $conn = getDatabaseConnection();
                $stmt = $conn->prepare("SELECT id FROM barangay WHERE id = ?");
                $stmt->execute([$device_barangay_id]);
                if ($stmt->rowCount() == 0) {
                    $errors['device_barangay_id'] = "Invalid barangay selected.";
                }
            }
            
            // Check if device exists in admin_devices table and is approved
            if (empty($errors)) {
                $conn = getDatabaseConnection();
                $stmt = $conn->prepare("SELECT admin_device_id FROM admin_devices WHERE device_number = ? AND serial_number = ? AND status = 'approved'");
                $stmt->execute([$device_number, $serial_number]);
                if ($stmt->rowCount() == 0) {
                    $errors['device_number'] = "Device is not approved or does not exist.";
                }
            }
            
            // Check if device is already registered in devices table
            if (empty($errors)) {
                $conn = getDatabaseConnection();
                $stmt = $conn->prepare("SELECT device_id FROM devices WHERE device_number = ? OR serial_number = ?");
                $stmt->execute([$device_number, $serial_number]);
                if ($stmt->rowCount() > 0) {
                    $errors['device_number'] = "Device is already registered in the system.";
                }
            }
            
            // Check if device is already registered to a user
            if (empty($errors)) {
                $conn = getDatabaseConnection();
                $stmt = $conn->prepare("SELECT user_id FROM users WHERE device_number = ?");
                $stmt->execute([$device_number]);
                if ($stmt->rowCount() > 0) {
                    $errors['device_number'] = "Device number already registered to a user.";
                }
            }
            
            if (empty($errors)) {
                $_SESSION['reg_data']['device_number'] = $device_number;
                $_SESSION['reg_data']['serial_number'] = $serial_number;
                $_SESSION['reg_data']['device_barangay_id'] = $device_barangay_id;
                header("Location: registration.php?step=credentials");
                exit();
            } else {
                // Store errors in session for display
                $_SESSION['form_errors'] = $errors;
                header("Location: registration.php?step=device&error=validation");
                exit();
            }
        }
        elseif (isset($_POST['credentials_submit'])) {
            // Step 4: Credentials
            $username = trim($_POST['username'] ?? '');
            $password = trim($_POST['password'] ?? '');
            $confirm_password = trim($_POST['confirm_password'] ?? '');

            // Defensive check for required session fields
            $required_fields = ['fullname','birthdate','age','address','email','contact','device_number','serial_number','building_name','building_type','latitude','longitude','barangay_id'];
            $missing_fields = [];
            foreach ($required_fields as $field) {
                if (!isset($_SESSION['reg_data'][$field]) || $_SESSION['reg_data'][$field] === '' || $_SESSION['reg_data'][$field] === null) {
                    $missing_fields[] = $field;
                }
            }
            if (!empty($missing_fields)) {
                $_SESSION['reg_error'] = 'Session expired or missing information (' . implode(', ', $missing_fields) . '). Please start registration again.';
                header('Location: registration.php?step=personal&error=session');
                exit();
            }

            if (empty($username)) {
                $errors['username'] = "Username is required";
            } elseif (strlen($username) < 5) {
                $errors['username'] = "Username must be at least 5 characters";
            } else {
                $conn = getDatabaseConnection();
                $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
                $stmt->execute([$username]);
                if ($stmt->rowCount() > 0) {
                    $errors['username'] = "Username already taken";
                }
            }

            if (empty($password)) {
                $errors['password'] = "Password is required";
            } elseif (strlen($password) < 8) {
                $errors['password'] = "Password must be at least 8 characters";
            }

            if ($password !== $confirm_password) {
                $errors['confirm_password'] = "Passwords do not match";
            }

            // reCAPTCHA verification for final step
            $recaptcha_token = $_POST['g-recaptcha-response'] ?? '';
            $recaptcha_result = verify_recaptcha($recaptcha_token);
            if (!$recaptcha_result['success']) {
                $errors['recaptcha'] = "Please complete the security verification.";
            }

            if (empty($errors)) {
                $conn = getDatabaseConnection();
                $conn->beginTransaction();
                try {
                    // Use secure password hashing (argon2id or bcrypt)
                    $hashed_password = hash_password_secure($password);
                    if (!$hashed_password) {
                        throw new Exception("Password hashing failed");
                    }
                    
                    // Generate email verification token
                    $email_verification_token = bin2hex(random_bytes(32));
                    $verification_expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));
                    
                    // Insert user with email_verified = 0 (requires verification) - status is 'Inactive' until verified
                    $stmt = $conn->prepare("INSERT INTO users (fullname, birthdate, age, address, email_address, contact_number, device_number, username, password, status, email_verified, email_verification_token, verification_expiry) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Inactive', 0, ?, ?)");
                    $stmt->execute([
                        $_SESSION['reg_data']['fullname'],
                        $_SESSION['reg_data']['birthdate'],
                        $_SESSION['reg_data']['age'],
                        $_SESSION['reg_data']['address'],
                        $_SESSION['reg_data']['email'],
                        $_SESSION['reg_data']['contact'],
                        $_SESSION['reg_data']['device_number'],
                        $username,
                        $hashed_password,
                        $email_verification_token,
                        $verification_expiry
                    ]);
                    $user_id = $conn->lastInsertId();
                    // Insert device (do NOT use admin_device_id if not in table)
                    $device_barangay_id = $_SESSION['reg_data']['device_barangay_id'] ?? null;
                    $device_stmt = $conn->prepare("INSERT INTO devices (user_id, device_name, device_number, serial_number, barangay_id, is_active, status) VALUES (?, ?, ?, ?, ?, 1, 'offline')");
                    $device_name = "User Device";
                    $device_stmt->execute([
                        $user_id,
                        $device_name,
                        $_SESSION['reg_data']['device_number'],
                        $_SESSION['reg_data']['serial_number'],
                        $device_barangay_id
                    ]);
                    // Insert building
                    $barangay_id = $_SESSION['reg_data']['barangay_id'] ?? null;
                    error_log('Inserting building with barangay_id: ' . ($barangay_id ?? 'NULL'));
                    
                    $building_stmt = $conn->prepare("INSERT INTO buildings (user_id, barangay_id, building_name, building_type, address, latitude, longitude) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $building_stmt->execute([
                        $user_id,
                        $barangay_id,
                        $_SESSION['reg_data']['building_name'],
                        $_SESSION['reg_data']['building_type'],
                        $_SESSION['reg_data']['address'],
                        $_SESSION['reg_data']['latitude'],
                        $_SESSION['reg_data']['longitude']
                    ]);
                    
                    // Send verification email
                    $email_sent = send_verification_email($_SESSION['reg_data']['email'], $email_verification_token);
                    if (!$email_sent) {
                        error_log("Failed to send verification email to: " . $_SESSION['reg_data']['email']);
                    }
                    
                    $conn->commit();
                    // Registration successful - email verification required
                    $success = "Welcome to FireGuard! Your account has been created. Please check your email to verify your account before logging in.";
                    unset($_SESSION['reg_data']);
                } catch (PDOException $e) {
                    $conn->rollBack();
                    $errors['database'] = "Registration failed. Please try again. Error: " . $e->getMessage();
                    error_log("Registration error: " . $e->getMessage());
                }
            } else {
                // Store errors in session for display
                $_SESSION['form_errors'] = $errors;
                header("Location: registration.php?step=credentials&error=validation");
                exit();
            }
        }
    } catch (PDOException $e) {
        $errors['system'] = "Database error: " . $e->getMessage();
        error_log("Database error: " . $e->getMessage());
    } catch (Exception $e) {
        $errors['system'] = "An error occurred: " . $e->getMessage();
        error_log("System error: " . $e->getMessage());
    }
}

// Get current step
$step = isset($_GET['step']) ? $_GET['step'] : 'personal';
if (!isset($_SESSION['reg_data'])) {
    $step = 'personal';
} elseif ($step == 'location' && !isset($_SESSION['reg_data']['fullname'])) {
    $step = 'personal';
} elseif ($step == 'device' && !isset($_SESSION['reg_data']['address'])) {
    $step = 'location';
} elseif ($step == 'credentials' && !isset($_SESSION['reg_data']['device_number'])) {
    $step = 'device';
}

// Check if geo-fences are available before allowing location step
if ($step == 'location') {
    $geo_fences = get_active_geo_fences();
    if (empty($geo_fences)) {
        $errors['system'] = "No active geo-fences configured. Registration is currently disabled. Please contact support.";
        $step = 'personal'; // Redirect back to personal step
    }
}

$step_sequence = ['personal', 'location', 'device', 'credentials'];
$step_labels = [
    'personal' => 'Personal',
    'location' => 'Location',
    'device' => 'Device',
    'credentials' => 'Credentials'
];
$step_descriptions = [
    'personal' => 'Tell us about yourself to get started with FireGuard.',
    'location' => 'Pin your exact location so responders know where to go.',
    'device' => 'Link your approved FireGuard device to your account.',
    'credentials' => 'Secure your account with unique login credentials.'
];
$current_step_index = array_search($step, $step_sequence, true);
if ($current_step_index === false) {
    $current_step_index = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FIREGUARD</title>
    <link rel="icon" type="image/png" sizes="32x32" href="fireguard.png?v=1">
    <link rel="icon" type="image/png" sizes="16x16" href="fireguard.png?v=1">
    <link rel="shortcut icon" type="image/png" href="fireguard.png?v=1">
    <link rel="apple-touch-icon" sizes="180x180" href="fireguard.png?v=1">
    <link rel="apple-touch-icon" href="fireguard.png?v=1">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.7.1/dist/leaflet.css" />
    <?php 
    // Load reCAPTCHA script if credentials step
    if ($step == 'credentials'): 
        $recaptcha_config_file = dirname(__DIR__) . '/login/functions/recaptcha_config.php';
        if (file_exists($recaptcha_config_file)) {
            $config = require $recaptcha_config_file;
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $site_key = $config['domains'][$host]['site_key'] ?? $config['default']['site_key'] ?? '';
            if (!empty($site_key)):
    ?>
    <script>
        // Store site key for reCAPTCHA
        window.recaptchaSiteKey = '<?php echo htmlspecialchars($site_key); ?>';
    </script>
    <script src="https://www.google.com/recaptcha/api.js?render=<?php echo htmlspecialchars($site_key); ?>"></script>
    <?php 
            endif;
        }
    endif; 
    ?>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Segoe+UI:wght@300;400;500;600;700&display=swap');

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-color: #ff5a4d;
            --secondary-color: #ff814f;
            --accent-color: #ffbf40;
            --dark-bg: #121735;
            --darker-bg: #0b1030;
            --light-text: #f3f6fb;
            --gray-text: #c9cfdb;
            --card-bg: rgba(255, 255, 255, 0.12);
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, var(--darker-bg) 0%, var(--dark-bg) 100%);
            color: var(--light-text);
            overflow-x: hidden;
            line-height: 1.6;
            min-height: 100vh;
            position: relative;
        }

        /* Background video */
        .bg-video-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            overflow: hidden;
            z-index: -3;
        }

        .bg-video-container video {
            position: absolute;
            top: 50%;
            left: 50%;
            min-width: 100%;
            min-height: 100%;
            width: auto;
            height: auto;
            transform: translate(-50%, -50%);
            object-fit: cover;
            filter: brightness(0.5) contrast(1.05) saturate(1.05) blur(8px);
            opacity: 1;
        }

        /* Subtle overlay to keep text readable */
        .bg-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: linear-gradient(
                180deg,
                rgba(10, 14, 39, 0.45) 0%,
                rgba(10, 14, 39, 0.55) 50%,
                rgba(10, 14, 39, 0.65) 100%
            );
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            z-index: -2;
            pointer-events: none;
        }

        /* Animated background particles */
        .particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            overflow: hidden;
        }

        .particle {
            position: absolute;
            width: 4px;
            height: 4px;
            background: var(--primary-color);
            border-radius: 50%;
            animation: float 15s infinite ease-in-out;
            opacity: 0.3;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0) translateX(0); }
            25% { transform: translateY(-100px) translateX(50px); }
            50% { transform: translateY(-200px) translateX(-50px); }
            75% { transform: translateY(-100px) translateX(100px); }
        }

        .registration-container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 8rem 1.5rem 2rem;
            position: relative;
            z-index: 1;
        }

        .card {
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(30px);
            -webkit-backdrop-filter: blur(30px);
            border: 1px solid rgba(255, 255, 255, 0.14);
            border-radius: 22px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.45);
            padding: 2rem 2.75rem;
            margin-bottom: 2rem;
            position: relative;
            color: var(--light-text);
        }
        .card-header {
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding: 1.5rem 0;
            margin-bottom: 2rem;
            text-align: center;
        }
        .card-header h5 {
            font-weight: 700;
            color: var(--light-text);
            margin: 0;
            font-size: 1.55rem;
            background: linear-gradient(135deg, var(--secondary-color), var(--accent-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .card-body {
            padding: 0;
        }
        .form-control, .form-select, .form-check-input, input[type="text"], input[type="email"], input[type="password"], input[type="tel"], input[type="date"], input[type="number"], textarea, select {
            background: rgba(255, 255, 255, 0.18);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.25);
            color: var(--light-text);
            padding: 0.95rem 1.1rem;
            border-radius: 12px;
            outline: none;
            font-size: 1rem;
            transition: all 0.2s ease;
            width: 100%;
        }
        .form-control:focus, .form-select:focus, input:focus, textarea:focus, select:focus {
            border-color: var(--primary-color);
            background: rgba(255, 255, 255, 0.22);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            box-shadow: 0 0 0 3px rgba(255, 68, 68, 0.14);
        }
        .form-control::placeholder, input::placeholder, textarea::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }
        .form-label, label {
            font-weight: 500;
            color: var(--light-text);
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }
        .form-text {
            font-size: 0.8rem;
            color: var(--gray-text);
            margin-top: 0.5rem;
        }
        .invalid-feedback, .error-message {
            color: #ff6b6b;
            font-size: 0.8rem;
            margin-top: 0.5rem;
        }
        .is-invalid, input.is-invalid, textarea.is-invalid, select.is-invalid {
            border-color: #ff6b6b !important;
            box-shadow: 0 0 0 3px rgba(255, 107, 107, 0.2);
        }
        .is-invalid:focus {
            border-color: #ff6b6b !important;
            box-shadow: 0 0 0 3px rgba(255, 107, 107, 0.3);
        }
        .form-check-input {
            width: 1.1em;
            height: 1.1em;
            margin-top: 0.2em;
            background: #fff !important;
            border: 1px solid #d1d5db !important;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }
        .form-check-input:checked {
            background: #ff8c00 !important;
        }
        .form-check-label {
            color: #374151;
            font-size: 0.875rem;
            font-weight: 500;
            margin-left: 8px;
        }
        .form-switch .form-check-input {
            width: 3.5em;
            height: 2em;
            margin-top: 0.25em;
            background: #f3f6fa !important;
            border-radius: 20px;
        }
        .form-switch .form-check-input:checked {
            background: #ff8c00 !important;
        }
        .form-floating {
            position: relative;
            margin-bottom: 2rem;
        }
        .form-floating label {
            position: absolute;
            top: 20px;
            left: 28px;
            color: #b0b8c1;
            background: #fff;
            padding: 0 12px;
            border-radius: 8px;
            font-weight: 600;
            box-shadow: none;
            border: none;
        }
        .form-floating .form-control:focus ~ label,
        .form-floating .form-control:not(:placeholder-shown) ~ label {
            top: -12px;
            left: 20px;
            font-size: 0.9rem;
            color: #ff8c00;
            background: #fff;
            font-weight: 700;
        }
        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }
        .form-group::after {
            display: none;
        }
        .input-group {
            position: relative;
            display: flex;
            flex-wrap: wrap;
            align-items: stretch;
            width: 100%;
        }
        .input-group-text {
            display: flex;
            align-items: center;
            padding: 12px 16px;
            font-size: 0.875rem;
            font-weight: 500;
            line-height: 1.5;
            color: #6b7280;
            text-align: center;
            white-space: nowrap;
            background: #f9fafb !important;
            border: 1px solid #e5e7eb !important;
            border-radius: 12px 0 0 12px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }
        .input-group .form-control {
            border-radius: 0 12px 12px 0;
            border-left: none;
        }
        .input-group .form-control:focus {
            border-left: none;
        }
        #map {
            height: 500px;
            width: 100%;
            margin-bottom: 25px;
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            position: relative;
            z-index: 1;
            background: rgba(255, 255, 255, 0.05);
        }
        #get-location-btn {
            border-radius: 10px;
            padding: 0.5rem 0.9rem;
            font-weight: 600;
            font-size: 0.8rem;
            border: none;
            color: #fff;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.25);
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
        }
        #get-location-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 68, 68, 0.4);
        }
        #current-location-info {
            padding: 1rem;
            background: rgba(255, 255, 255, 0.08);
            border-radius: 12px;
            margin-top: 1rem;
            color: var(--light-text);
            font-size: 0.9rem;
            display: none;
            font-weight: 400;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .animate__animated {
            animation-duration: 0.5s;
        }
        /* Stepper and progress bar */
        .step-indicator {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 2rem;
            position: relative;
        }
        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
            position: relative;
        }
        .step-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            font-weight: 600;
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid rgba(255, 255, 255, 0.2);
            color: var(--gray-text);
            transition: all 0.3s ease;
        }
        .step.active .step-number,
        .step.completed .step-number {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-color: var(--primary-color);
            color: white;
            box-shadow: 0 0 15px rgba(255, 68, 68, 0.4);
        }
        .step-label {
            font-size: 0.75rem;
            font-weight: 500;
            color: var(--gray-text);
        }
        .step.active .step-label,
        .step.completed .step-label {
            color: var(--light-text);
            font-weight: 600;
        }
        .step:not(:last-child)::after {
            content: '';
            position: absolute;
            top: 20px;
            left: calc(100% + 0.75rem);
            width: 60px;
            height: 2px;
            background: rgba(255, 255, 255, 0.2);
            margin-top: -20px;
        }
        .step.completed:not(:last-child)::after {
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
        }
        .progress {
            height: 8px;
            margin-bottom: 2rem;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.1);
            overflow: visible;
            border: none;
        }
        .progress-bar {
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
            transition: width 0.6s ease;
            border-radius: 10px;
            position: relative;
            overflow: visible;
        }
        .progress-bar::after {
            content: '';
            position: absolute;
            right: -10px;
            top: -4px;
            width: 20px;
            height: 20px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: 50%;
            border: 4px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 3px 10px rgba(255, 68, 68, 0.3);
        }
        /* Responsive adjustments */
        @media (max-width: 992px) {
            .registration-wrapper {
                padding: 30px;
            }
            .card-body {
                padding: 25px;
            }
        }
        @media (max-width: 768px) {
            .registration-wrapper {
                padding: 25px 20px;
            }
            .step-indicator {
                flex-wrap: wrap;
            }
            .step {
                flex: 0 0 50%;
                margin-bottom: 25px;
            }
            .step:not(:last-child)::after {
                display: none;
            }
            .card-header h5 {
                font-size: 1.2rem;
            }
            .card-body {
                padding: 20px;
            }
            .btn {
                padding: 12px 20px;
                font-size: 0.95rem;
            }
            #map {
                height: 400px;
            }
        }
        @media (max-width: 576px) {
            .registration-wrapper {
                padding: 20px 15px;
            }
            .step {
                flex: 0 0 100%;
                margin-bottom: 15px;
            }
            .step-number {
                width: 40px;
                height: 40px;
                font-size: 1rem;
            }
            .step-label {
                font-size: 0.85rem;
            }
            .card-header h5 {
                font-size: 1.1rem;
            }
            .form-control, .form-select {
                padding: 12px 16px;
                font-size: 0.95rem;
            }
            .btn {
                padding: 10px 18px;
                font-size: 0.9rem;
            }
        }
        /* Flatpickr dark theme customization */
        .flatpickr-calendar {
            background: rgba(18, 23, 53, 0.95) !important;
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.14) !important;
            color: var(--light-text) !important;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.45);
        }
        .flatpickr-calendar.open {
            display: inline-block !important;
            z-index: 9999 !important;
        }
        .flatpickr-day {
            color: var(--light-text) !important;
            background: transparent !important;
            border: none !important;
        }
        .flatpickr-day:hover {
            background: rgba(255, 255, 255, 0.1) !important;
        }
        .flatpickr-day.selected {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)) !important;
            color: #fff !important;
        }
        .flatpickr-day.today {
            border-color: var(--primary-color) !important;
        }
        .flatpickr-months {
            background: transparent !important;
            color: var(--light-text) !important;
        }
        .flatpickr-month {
            color: var(--light-text) !important;
        }
        .flatpickr-current-month {
            color: var(--light-text) !important;
        }
        .flatpickr-monthDropdown-months {
            background: rgba(255, 255, 255, 0.1) !important;
            color: var(--light-text) !important;
        }
        .flatpickr-weekday {
            color: var(--gray-text) !important;
        }
        .flatpickr-prev-month, .flatpickr-next-month {
            color: var(--primary-color) !important;
        }
        .flatpickr-prev-month:hover, .flatpickr-next-month:hover {
            color: var(--secondary-color) !important;
        }
        /* Password toggle button styles */
        .position-relative .btn-link {
            color: #6b7280;
            text-decoration: none;
            transition: color 0.2s ease;
        }
        
        .position-relative .btn-link:hover {
            color: #ff8c00;
        }
        
        .position-relative .btn-link:focus {
            box-shadow: none;
            outline: none;
        }
        
        .position-relative .btn-link i {
            font-size: 1.1rem;
        }
        
        /* Ensure the button doesn't interfere with input focus */
        .position-relative .form-control:focus + .btn-link {
            color: #ff8c00;
        }
        
        /* Red asterisk for required fields */
        .form-label span[style*="color"] {
            color: var(--primary-color) !important;
        }
        
        /* Sliding line animation */
        @keyframes slideLine {
            0% {
                left: -100%;
            }
            100% {
                left: 100%;
            }
        }
        
        /* Button Styles */
        .btn, button[type="submit"], button[type="button"] {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border: none;
            padding: 0.95rem 1.6rem;
            border-radius: 12px;
            font-size: 1.05rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 5px 20px rgba(255, 68, 68, 0.28);
        }
        
        .btn:hover, button[type="submit"]:hover, button[type="button"]:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(255, 68, 68, 0.38);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        }
        
        .btn-secondary {
            background: transparent;
            border: 1px solid var(--primary-color);
            color: var(--primary-color);
        }
        
        .btn-secondary:hover {
            background: rgba(255, 68, 68, 0.1);
        }
        
        .btn-success {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        }
        
        .btn-outline-primary {
            background: transparent;
            border: 1px solid var(--primary-color);
            color: var(--primary-color);
        }
        
        .btn-outline-primary:hover {
            background: rgba(255, 68, 68, 0.1);
        }
        
        .btn-link {
            background: none;
            border: none;
            color: var(--primary-color);
            text-decoration: none;
            padding: 0;
            box-shadow: none;
        }
        
        .btn-link:hover {
            color: var(--secondary-color);
            text-decoration: underline;
            transform: none;
        }
        
        /* Text-to-Speech Button Styles */
        #welcome-speech-btn {
            backdrop-filter: blur(10px);
            transition: all 0.3s ease !important;
        }
        
        #welcome-speech-btn:hover {
            background: rgba(255,255,255,0.3) !important;
            border-color: rgba(255,255,255,0.5) !important;
            transform: scale(1.1) !important;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2) !important;
        }
        
        #welcome-speech-btn:active {
            transform: scale(0.95) !important;
        }
        
        #welcome-speech-btn i {
            transition: all 0.3s ease;
        }
        
        /* Show email verification button */
        #verify-email-btn {
            display: inline-block !important;
        }
        
        /* Alert Styles */
        .alert {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            padding: 1rem 1.25rem;
            color: var(--light-text);
            margin-bottom: 1.5rem;
        }
        .alert-danger {
            background: rgba(255, 107, 107, 0.15);
            border-color: rgba(255, 107, 107, 0.3);
            color: #ff6b6b;
        }
        .alert-warning {
            background: rgba(255, 193, 7, 0.15);
            border-color: rgba(255, 193, 7, 0.3);
            color: #ffc107;
        }
        .alert-info {
            background: rgba(100, 181, 246, 0.15);
            border-color: rgba(100, 181, 246, 0.3);
            color: #64b5f6;
        }
        .alert-success {
            background: rgba(76, 175, 80, 0.15);
            border-color: rgba(76, 175, 80, 0.3);
            color: #4caf50;
        }

        /* Remove old Bootstrap-specific styles that conflict */
        .mb-3, .mb-4 {
            margin-bottom: 1.5rem;
        }
        .mb-2 {
            margin-bottom: 0.75rem;
        }
        .mt-2, .mt-4 {
            margin-top: 1rem;
        }
        .text-center {
            text-align: center;
        }
        .d-flex {
            display: flex;
        }
        .d-grid {
            display: grid;
        }
        .justify-content-between {
            justify-content: space-between;
        }
        .align-items-center {
            align-items: center;
        }
        .gap-1rem {
            gap: 1rem;
        }
        .row {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -0.75rem;
        }
        .col-lg-4, .col-lg-5, .col-lg-7, .col-lg-8, .col-md-12 {
            padding: 0 0.75rem;
        }
        .col-lg-4 {
            flex: 0 0 33.333333%;
            max-width: 33.333333%;
        }
        .col-lg-5 {
            flex: 0 0 41.666667%;
            max-width: 41.666667%;
        }
        .col-lg-7 {
            flex: 0 0 58.333333%;
            max-width: 58.333333%;
        }
        .col-lg-8 {
            flex: 0 0 66.666667%;
            max-width: 66.666667%;
        }
        .col-md-12 {
            flex: 0 0 100%;
            max-width: 100%;
        }
        @media (max-width: 992px) {
            .col-lg-4, .col-lg-5, .col-lg-7, .col-lg-8 {
                flex: 0 0 100%;
                max-width: 100%;
            }
        }

        /* Landing page inspired register layout */
        .register-page {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 4rem 1.5rem 2.5rem;
            position: relative;
            z-index: 1;
        }

        .inline-register-panel {
            position: relative;
            inset: auto;
            transform: none;
            opacity: 1;
            pointer-events: auto;
            background: transparent;
            backdrop-filter: none;
            padding: 0;
        }

        .inline-register-content {
            max-width: 100%;
            width: 100%;
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(30px);
            -webkit-backdrop-filter: blur(30px);
            border: 1px solid rgba(255, 255, 255, 0.14);
            border-radius: 22px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.45);
            padding: 2rem 2.75rem 2.5rem;
        }

        .inline-register-header {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            align-items: center;
            margin-bottom: 2rem;
            gap: 1.25rem;
        }

        .register-header-left,
        .register-header-center,
        .register-header-right {
            display: flex;
            align-items: center;
            justify-content: flex-start;
        }

        .register-header-center {
            justify-content: center;
        }

        .register-header-right {
            justify-content: flex-end;
        }

        .inline-register-title {
            font-size: 1.6rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--secondary-color), var(--accent-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin: 0;
        }

        .inline-register-subtitle {
            font-size: 0.95rem;
            color: var(--gray-text);
            margin-top: 0.35rem;
        }

        .inline-register-close-link {
            color: var(--gray-text);
            text-decoration: none;
            font-weight: 600;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.18);
            border-radius: 999px;
            padding: 0.4rem 1rem;
            transition: all 0.2s ease;
        }

        .inline-register-close-link:hover {
            color: var(--light-text);
            border-color: var(--primary-color);
        }
        #modal-verification-code::placeholder {
            color: rgba(47, 47, 47, 0.35);
            letter-spacing: 0.3rem;
            font-weight: 600;
        }

        .register-progress {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.75rem;
            padding: 0;
            margin: 0;
        }

        .progress-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.35rem;
        }

        .progress-step-number {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.05rem;
            font-weight: 600;
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid rgba(255, 255, 255, 0.2);
            color: var(--gray-text);
            transition: all 0.3s ease;
        }

        .progress-step-label {
            font-size: 0.75rem;
            color: var(--gray-text);
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .progress-step.active .progress-step-number,
        .progress-step.completed .progress-step-number {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-color: var(--primary-color);
            color: #fff;
            box-shadow: 0 0 15px rgba(255, 68, 68, 0.35);
        }

        .progress-step.active .progress-step-label,
        .progress-step.completed .progress-step-label {
            color: var(--light-text);
            font-weight: 600;
        }

        .progress-connector {
            width: 60px;
            height: 2px;
            background: rgba(255, 255, 255, 0.2);
        }

        .progress-step.completed + .progress-connector,
        .progress-step.active + .progress-connector {
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
        }

        .inline-login-form {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .inline-login-form-group {
            position: relative;
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
        }

        .inline-login-form-group label {
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--gray-text);
        }

        .auth-input {
            width: 100%;
            padding: 1rem 1rem 0.25rem;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.18);
            border-radius: 14px;
            color: var(--light-text);
            font-size: 1rem;
            outline: none;
            transition: all 0.25s ease;
        }

        .auth-input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(255, 68, 68, 0.2);
            background: rgba(255, 255, 255, 0.18);
        }

        .auth-input.is-invalid,
        .auth-input.is-warning {
            border-color: #ff6b6b !important;
            box-shadow: 0 0 0 3px rgba(255, 107, 107, 0.3);
        }

        .inline-login-form-group label {
            position: absolute;
            left: 1.1rem;
            top: 1rem;
            pointer-events: none;
            transition: all 0.2s ease;
            color: rgba(255, 255, 255, 0.7);
        }

        /* Hide label when input has content */
        .auth-input:not(:placeholder-shown) + label,
        .auth-input[value]:not([value=""]) + label,
        textarea.auth-input:not(:placeholder-shown) + label,
        select.auth-input:not([value=""]) + label {
            opacity: 0;
            visibility: hidden;
        }
        
        /* Hide label when input is focused and has content */
        .auth-input:focus:not(:placeholder-shown) + label {
            opacity: 0;
            visibility: hidden;
        }
        
        /* Show label when input is empty or only has placeholder */
        .auth-input:placeholder-shown + label,
        .auth-input:focus:placeholder-shown + label {
            opacity: 1;
            visibility: visible;
        }

        .inline-login-submit {
            border: none;
            border-radius: 14px;
            padding: 0.95rem 1.5rem;
            font-size: 1rem;
            font-weight: 600;
            color: #fff;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            box-shadow: 0 12px 30px rgba(255, 68, 68, 0.3);
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            width: 100%;
        }

        .inline-login-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 18px 40px rgba(255, 68, 68, 0.35);
        }

        .inline-login-submit.secondary {
            background: transparent;
            border: 1px solid var(--primary-color);
            color: var(--primary-color);
            box-shadow: none;
        }

        .inline-login-submit.secondary:hover {
            background: rgba(255, 68, 68, 0.12);
        }

        .inline-login-submit.ghost {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: var(--light-text);
            box-shadow: none;
        }

        .inline-login-submit.ghost:hover {
            border-color: var(--primary-color);
            color: #fff;
        }

        .email-verification-group {
            display: flex;
            gap: 0.75rem;
            align-items: flex-start;
        }

        .email-verification-group .inline-login-form-group {
            flex: 1;
        }

        .send-verification-btn {
            background: linear-gradient(135deg, var(--secondary-color), var(--accent-color));
            color: white;
            border: none;
            padding: 0.95rem 1.5rem;
            border-radius: 12px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            white-space: nowrap;
        }

        .send-verification-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(255, 129, 79, 0.4);
        }

        .send-verification-btn.secondary {
            background: transparent;
            border: 1px solid var(--primary-color);
            color: var(--primary-color);
            box-shadow: none;
        }

        .send-verification-btn.secondary:hover {
            background: rgba(255, 68, 68, 0.12);
            box-shadow: none;
        }

        .send-verification-btn.verified {
            background: rgba(46, 204, 113, 0.18);
            border: 1px solid rgba(46, 204, 113, 0.6);
            color: #2ecc71;
            cursor: default;
            box-shadow: none;
        }

        .verification-feedback {
            font-size: 0.8rem;
            color: var(--gray-text);
        }

        .register-columns {
            display: grid;
            grid-template-columns: 0.9fr 1.1fr;
            gap: 2rem;
        }

        .register-info-card {
            background: linear-gradient(135deg, rgba(255, 90, 77, 0.18), rgba(255, 129, 79, 0.18), rgba(255, 191, 64, 0.18));
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 18px;
            padding: 2rem;
            display: flex;
            flex-direction: column;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .register-form-fields {
            display: flex;
            flex-direction: column;
            gap: 1.35rem;
        }

        .location-step {
            display: grid;
            grid-template-columns: 1.3fr 1fr;
            gap: 1.75rem;
        }

        .location-map-container {
            min-height: 420px;
            border-radius: 16px;
            overflow: hidden;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            position: relative;
        }

        .location-form-container {
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }

        #map {
            height: 100%;
            width: 100%;
        }

        .current-location-btn {
            position: absolute;
            bottom: 15px;
            left: 15px;
            z-index: 10;
            border-radius: 12px;
            padding: 0.75rem 1.25rem;
            font-weight: 600;
            border: none;
            color: #fff;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
        }

        .address-textarea {
            min-height: 120px;
            resize: none;
        }

        .validated-field .field-checkmark {
            position: absolute;
            right: 14px;
            top: 14px;
            width: 24px;
            height: 24px;
            border-radius: 12px;
            background: rgba(46, 204, 113, 0.15);
            color: #2ecc71;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.85rem;
        }

        .inline-alert {
            padding: 1rem 1.25rem;
            border-radius: 14px;
            border: 1px solid rgba(255, 255, 255, 0.15);
            background: rgba(255, 255, 255, 0.08);
            font-size: 0.9rem;
            line-height: 1.5;
            color: var(--light-text);
        }

        .inline-alert.warning {
            border-color: rgba(255, 193, 7, 0.35);
            background: rgba(255, 193, 7, 0.12);
            color: #ffdd75;
        }

        .inline-register-actions {
            margin-top: 2rem;
            text-align: center;
            color: var(--gray-text);
            font-size: 0.95rem;
        }

        .inline-register-actions a {
            color: var(--primary-color);
            font-weight: 600;
            text-decoration: none;
        }

        .inline-register-actions a:hover {
            color: var(--accent-color);
        }

        .device-registration-banner {
            padding: 1rem 1.2rem;
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.12);
            font-size: 0.9rem;
            color: var(--gray-text);
        }

        .device-registration-actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .password-field {
            position: relative;
        }

        .password-toggle-btn {
            position: absolute;
            right: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--gray-text);
            cursor: pointer;
            font-size: 1rem;
        }

        select.auth-input {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            padding-right: 2.5rem;
            background-image: linear-gradient(45deg, transparent 50%, rgba(255,255,255,0.6) 50%), linear-gradient(135deg, rgba(255,255,255,0.6) 50%, transparent 50%);
            background-position: calc(100% - 20px) calc(50% - 3px), calc(100% - 14px) calc(50% - 3px);
            background-size: 6px 6px, 6px 6px;
            background-repeat: no-repeat;
            color: #fdf0df;
        }

        select.auth-input option {
            color: inherit;
            background: transparent;
        }

        select.auth-input:focus option {
            color: #1a1a1a;
            background: #fdf0df;
            font-weight: 600;
        }

        textarea.auth-input {
            padding-top: 1.25rem;
        }

        @media (max-width: 992px) {
            .inline-register-content {
                padding: 1.75rem 1.5rem 2rem;
            }
            .inline-register-header {
                grid-template-columns: 1fr;
                text-align: center;
            }
            .register-columns,
            .location-step {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 576px) {
            .email-verification-group {
                flex-direction: column;
            }
            .send-verification-btn {
                width: 100%;
                justify-content: center;
            }
            .inline-register-content {
                padding: 1.5rem;
            }
            .inline-register-title {
                font-size: 1.35rem;
            }
        }

        /* Mobile-first refinements */
        @media (max-width: 1024px) {
            .register-page {
                padding: 3rem 1.25rem 2rem;
            }
            .inline-register-content {
                padding: 1.75rem 1.75rem 2rem;
            }
        }

        @media (max-width: 768px) {
            body {
                min-height: auto;
            }
            .register-page {
                padding: 2.5rem 1rem 1.5rem;
            }
            .inline-register-content {
                padding: 1.5rem;
            }
            .inline-register-header {
                grid-template-columns: 1fr;
                text-align: center;
            }
            .register-header-left,
            .register-header-center,
            .register-header-right {
                justify-content: center;
            }
            .register-header-right {
                margin-top: 0.5rem;
            }
            .register-progress {
                flex-wrap: wrap;
                gap: 0.5rem 1rem;
            }
            .progress-connector {
                display: none;
            }
            .register-columns,
            .location-step {
                grid-template-columns: minmax(0, 1fr);
                gap: 1.25rem;
            }
            .register-info-card {
                order: 2;
            }
            .register-form-fields {
                order: 1;
            }
            .location-map-container {
                min-height: 320px;
            }
            #map {
                min-height: 320px;
            }
            .device-registration-actions {
                flex-direction: column;
            }
            .inline-login-submit {
                width: 100%;
            }
            .email-verification-group {
                align-items: stretch;
            }
            .send-verification-btn {
                width: 100%;
            }
        }

        @media (max-width: 480px) {
            .register-page {
                padding: 2rem 0.75rem 1.25rem;
            }
            .inline-register-content {
                padding: 1.25rem 1rem 1.5rem;
            }
            .inline-register-title {
                font-size: 1.3rem;
            }
            .inline-register-subtitle {
                font-size: 0.9rem;
            }
            .progress-step-number {
                width: 36px;
                height: 36px;
                font-size: 0.95rem;
            }
            .progress-step-label {
                font-size: 0.7rem;
                letter-spacing: 0.02em;
            }
            .location-map-container {
                min-height: 260px;
            }
            #map {
                min-height: 260px;
            }
            .current-location-btn {
                position: static;
                width: 100%;
                margin-top: 1rem;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <!-- Background Video -->
    <div class="bg-video-container" aria-hidden="true">
        <video autoplay muted loop playsinline preload="auto">
            <source src="../fire/images/firebg2.mp4" type="video/mp4">
        </video>
    </div>
    <div class="bg-overlay" aria-hidden="true"></div>

    <!-- Animated Background Particles -->
    <div class="particles" id="particles"></div>

    <div class="register-page">
        <div class="inline-register-panel register-page-panel">
            <div class="inline-register-content">
                <?php if ($success): ?>
                    <!-- Success message will be shown via SweetAlert -->
                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            // Text-to-speech functionality
                            if ('speechSynthesis' in window) {
                                const speech = new SpeechSynthesisUtterance('<?php echo addslashes($success); ?>');
                                speech.rate = 0.9; // Slightly slower for better clarity
                                speech.pitch = 1.0;
                                speech.volume = 0.8;
                                
                                // Get available voices and set a preferred voice
                                speechSynthesis.onvoiceschanged = function() {
                                    const voices = speechSynthesis.getVoices();
                                    // Try to find an English voice
                                    const englishVoice = voices.find(voice => 
                                        voice.lang.startsWith('en') && voice.name.includes('Google')
                                    ) || voices.find(voice => 
                                        voice.lang.startsWith('en')
                                    ) || voices[0];
                                    
                                    if (englishVoice) {
                                        speech.voice = englishVoice;
                                    }
                                };
                                
                                // Speak the message
                                speechSynthesis.speak(speech);
                            }
                            
                            Swal.fire({
                                icon: 'success',
                                title: 'Registration Successful!',
                                text: '<?php echo addslashes($success); ?>',
                                confirmButtonText: 'OK',
                                confirmButtonColor: '#ff8c00',
                                timer: 5000,
                                timerProgressBar: true
                            });
                        });
                    </script>
                <?php endif; ?>
                
                <?php if (isset($errors['system'])): ?>
                    <div class="alert alert-danger"><?php echo $errors['system']; ?></div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['reg_error'])): ?>
                    <div class="alert alert-danger"><?php echo $_SESSION['reg_error']; unset($_SESSION['reg_error']); ?></div>
                <?php endif; ?>
                
                <?php if (isset($_GET['error']) && $_GET['error'] === 'session'): ?>
                    <div class="alert alert-warning">
                        <strong>Session Expired:</strong> Your registration session has expired. Please start the registration process again.
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_GET['error']) && $_GET['error'] === 'validation'): ?>
                    <div class="alert alert-danger">
                        <strong>Validation Error:</strong> Please check the form fields and fix any errors before proceeding.
                    </div>
                <?php endif; ?>
                
                <div class="inline-register-header">
                    <div class="register-header-left">
                        <div>
                            <h2 class="inline-register-title">Create Your Account</h2>
                            <p class="inline-register-subtitle">
                                <?php echo $step_descriptions[$step] ?? 'Complete the steps below to activate your FireGuard account.'; ?>
                            </p>
                        </div>
                    </div>
                    <div class="register-header-center">
                        <div class="register-progress">
                            <?php foreach ($step_sequence as $index => $step_key): ?>
                                <?php
                                    $status = '';
                                    if ($current_step_index > $index) {
                                        $status = 'completed';
                                    } elseif ($current_step_index === $index) {
                                        $status = 'active';
                                    }
                                ?>
                                <div class="progress-step <?php echo $status; ?>">
                                    <div class="progress-step-number"><?php echo $index + 1; ?></div>
                                    <span class="progress-step-label"><?php echo $step_labels[$step_key]; ?></span>
                                </div>
                                <?php if ($index < count($step_sequence) - 1): ?>
                                    <div class="progress-connector"></div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="register-header-right">
                        <a href="../index.php" class="inline-register-close-link">← Back</a>
                    </div>
                </div>
                
                <?php if ($step == 'personal'): ?>
                    <div class="register-columns">
                        <div class="register-info-card">
                            <div style="position: absolute; top: 15px; right: 15px; z-index: 10;">
                                <button type="button" class="btn" id="welcome-speech-btn"
                                    style="background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3); color: #fff; border-radius: 50%; width: 42px; height: 42px; display: flex; align-items: center; justify-content: center; transition: all 0.3s ease; padding: 0;"
                                    onclick="speakWelcomeText()" title="Listen to welcome message">
                                    <i class="fas fa-volume-up" id="speech-icon"></i>
                                </button>
                            </div>
                            <div style="margin-bottom: 1.5rem;">
                                <img src="fireguard.png" alt="Fire Guard Logo" style="max-width: 200px; height: auto; filter: drop-shadow(0 0 10px rgba(255, 68, 68, 0.5));">
                            </div>
                            <h4 style="color: var(--light-text); font-weight: 700; margin-bottom: 1rem; font-size: 1.5rem;">Welcome to Fire Guard</h4>
                            <p style="color: var(--gray-text); font-weight: 500; margin-bottom: 2rem; line-height: 1.6;">Your trusted partner in fire safety and detection. Join our community of safety-conscious individuals and help protect what matters most.</p>
                        </div>
                        <div class="register-form-fields">
                            <form method="POST" action="" id="personal-form" class="inline-login-form" onsubmit="return validateFormSubmission('personal')">
                                <?php 
                                $csrf_token = generate_csrf_token();
                                $honeypot_field = add_honeypot_field();
                                ?>
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                <input type="text" name="<?php echo htmlspecialchars($honeypot_field); ?>" style="display:none;visibility:hidden;" tabindex="-1" autocomplete="off">
                                <div class="inline-login-form-group">
                                    <input type="text" class="auth-input <?php echo isset($errors['fullname']) ? 'is-invalid' : ''; ?>" id="fullname" name="fullname" value="<?php echo isset($_SESSION['reg_data']['fullname']) ? htmlspecialchars($_SESSION['reg_data']['fullname']) : ''; ?>" required minlength="2" maxlength="100" pattern="[a-zA-Z\s\.'\-]+" placeholder=" ">
                                    <label for="fullname">Full Name *</label>
                                    <?php if (isset($errors['fullname'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['fullname']; ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="inline-login-form-group">
                                    <input type="date" class="auth-input <?php echo isset($errors['birthdate']) ? 'is-invalid' : ''; ?>" id="birthdate" name="birthdate" value="<?php echo isset($_SESSION['reg_data']['birthdate']) ? htmlspecialchars($_SESSION['reg_data']['birthdate']) : ''; ?>" required min="1900-01-01" max="<?php echo date('Y-m-d'); ?>" placeholder=" ">
                                    <label for="birthdate">Birthdate *</label>
                                    <?php if (isset($errors['birthdate'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['birthdate']; ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="form-text">
                                    <i class="fas fa-info-circle"></i> Click "Send Verification" to receive a verification code at your email address.
                                </div>
                                <div class="email-verification-group">
                                    <div class="inline-login-form-group email-input-group">
                                        <input type="email" class="auth-input <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" id="email" name="email" value="<?php echo isset($_SESSION['reg_data']['email']) ? htmlspecialchars($_SESSION['reg_data']['email']) : ''; ?>" required maxlength="255" placeholder=" ">
                                        <label for="email">Email Address *</label>
                                        <?php if (isset($errors['email'])): ?>
                                            <div class="invalid-feedback"><?php echo $errors['email']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <button type="button" class="send-verification-btn" id="verify-email-btn" onclick="sendEmailVerification()">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <line x1="22" y1="2" x2="11" y2="13"></line>
                                            <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                                        </svg>
                                        <span id="verify-btn-text">Send Verification</span>
                                    </button>
                                </div>
                                <div id="email-verification-status" class="verification-feedback" style="display: none;">
                                    <div id="verification-message"></div>
                                    <div id="verification-code-section" style="display: none;" class="mt-2">
                                        <div class="email-verification-group">
                                            <div class="inline-login-form-group">
                                                <input type="text" class="auth-input verification-code-input" id="verification-code" placeholder=" " maxlength="6" pattern="[0-9]{6}">
                                                <label for="verification-code">Verification Code</label>
                                            </div>
                                            <button type="button" class="send-verification-btn secondary" onclick="verifyEmailCode()">
                                                <span id="verify-code-btn-text">Verify Code</span>
                                            </button>
                                        </div>
                                        <div class="form-text">Check your email for the verification code</div>
                                    </div>
                                </div>
                                <div class="inline-login-form-group">
                                    <input type="tel" class="auth-input <?php echo isset($errors['contact']) ? 'is-invalid' : ''; ?>" id="contact" name="contact" value="<?php echo isset($_SESSION['reg_data']['contact']) ? htmlspecialchars($_SESSION['reg_data']['contact']) : ''; ?>" required pattern="09[0-9]{9}" minlength="11" maxlength="11" placeholder=" ">
                                    <label for="contact">Contact Number *</label>
                                    <?php if (isset($errors['contact'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['contact']; ?></div>
                                    <?php endif; ?>
                                </div>
                                <button type="submit" name="personal_info_submit" class="inline-login-submit">
                                    Next: Location
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="9 18 15 12 9 6"></polyline>
                                    </svg>
                                </button>
                            </form>
                        </div>
                    </div>
                <?php elseif ($step == 'location'): ?>
                    <form method="POST" action="" id="location-form" class="inline-login-form" onsubmit="return validateFormSubmission('location')">
                        <?php 
                        $csrf_token = generate_csrf_token();
                        $honeypot_field = add_honeypot_field();
                        ?>
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <input type="text" name="<?php echo htmlspecialchars($honeypot_field); ?>" style="display:none;visibility:hidden;" tabindex="-1" autocomplete="off">
                        <?php if (isset($errors['location'])): ?>
                            <div class="alert alert-danger"><?php echo $errors['location']; ?></div>
                        <?php endif; ?>
                        <div class="inline-alert warning" id="location-requirements">
                            <div style="display: flex; gap: 0.75rem; align-items: flex-start;">
                                <i class="fas fa-exclamation-triangle"></i>
                                <div>
                                    <strong>Location Requirements:</strong><br>
                                    <span id="allowed-areas">Loading allowed areas...</span><br>
                                    •You must be inside the building where the device will be deployed.
                                </div>
                            </div>
                        </div>
                        <div class="location-step">
                            <div class="location-map-container">
                                <div id="map"></div>
                                <button type="button" class="current-location-btn" id="get-location-btn">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="12" r="10"></circle>
                                        <path d="M12 2v4m0 12v4M2 12h4m12 0h4"></path>
                                    </svg>
                                    Get Current Location
                                </button>
                            </div>
                            <div class="location-form-container">
                                <input type="hidden" id="latitude" name="latitude" value="<?php echo isset($_SESSION['reg_data']['latitude']) ? htmlspecialchars($_SESSION['reg_data']['latitude']) : ''; ?>">
                                <input type="hidden" id="longitude" name="longitude" value="<?php echo isset($_SESSION['reg_data']['longitude']) ? htmlspecialchars($_SESSION['reg_data']['longitude']) : ''; ?>">
                                <input type="hidden" id="barangay_id" name="barangay_id" value="<?php echo isset($_SESSION['reg_data']['barangay_id']) ? htmlspecialchars($_SESSION['reg_data']['barangay_id']) : ''; ?>">
                                <div class="inline-login-form-group">
                                    <textarea class="auth-input address-textarea <?php echo isset($errors['address']) ? 'is-invalid' : ''; ?>" id="address" name="address" rows="3" required readonly placeholder=" "><?php echo isset($_SESSION['reg_data']['address']) ? htmlspecialchars($_SESSION['reg_data']['address']) : ''; ?></textarea>
                                    <label for="address">Full Address *</label>
                                    <?php if (isset($errors['address'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['address']; ?></div>
                                    <?php endif; ?>
                                    <div class="form-text">
                                        <i class="fas fa-info-circle"></i> This address is read-only and auto-fills when you select a location on the map.
                                    </div>
                                </div>
                                <div class="inline-login-form-group">
                                    <select class="auth-input <?php echo isset($errors['barangay']) ? 'is-invalid' : ''; ?>" id="barangay" name="barangay" required>
                                        <option value="">Select barangay</option>
                                        <?php foreach ($barangays_list as $b): 
                                            $cleanName = cleanBarangayName($b['barangay_name']);
                                            $originalName = $b['barangay_name'];
                                        ?>
                                            <option value="<?php echo htmlspecialchars($originalName); ?>" data-barangay-id="<?php echo htmlspecialchars($b['id']); ?>" <?php echo (isset($_SESSION['reg_data']['barangay']) && $_SESSION['reg_data']['barangay'] === $originalName) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($cleanName); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>                                    <?php if (isset($errors['barangay'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['barangay']; ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="inline-login-form-group">
                                    <input type="text" class="auth-input" id="building_name" name="building_name" value="<?php echo isset($_SESSION['reg_data']['building_name']) ? htmlspecialchars($_SESSION['reg_data']['building_name']) : 'Primary Residence'; ?>" required maxlength="100" placeholder=" ">
                                    <label for="building_name">Building Name *</label>
                                </div>
                                <div class="inline-login-form-group">
                                    <select class="auth-input" id="building_type" name="building_type" required>
                                        <option value="">Select type</option>
                                        <option value="Residential" <?php if(isset($_SESSION['reg_data']['building_type']) && $_SESSION['reg_data']['building_type']==='Residential') echo 'selected'; ?>>Residential</option>
                                        <option value="Commercial" <?php if(isset($_SESSION['reg_data']['building_type']) && $_SESSION['reg_data']['building_type']==='Commercial') echo 'selected'; ?>>Commercial</option>
                                        <option value="Institutional" <?php if(isset($_SESSION['reg_data']['building_type']) && $_SESSION['reg_data']['building_type']==='Institutional') echo 'selected'; ?>>Institutional</option>
                                        <option value="Industrial" <?php if(isset($_SESSION['reg_data']['building_type']) && $_SESSION['reg_data']['building_type']==='Industrial') echo 'selected'; ?>>Industrial</option>
                                    </select>
                                    <label for="building_type">Building Type *</label>
                                </div>
                                <div class="device-registration-actions">
                                    <button type="button" class="inline-login-submit ghost" onclick="window.location.href='registration.php?step=personal'">← Back</button>
                                    <button type="submit" name="location_submit" class="inline-login-submit">
                                        Next: Device Registration
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <polyline points="9 18 15 12 9 6"></polyline>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div id="current-location-info"></div>
                    </form>
                <?php elseif ($step == 'device'): ?>
                    <form method="POST" action="" id="device-form" class="inline-login-form" onsubmit="return validateFormSubmission('device')">
                        <?php 
                        $csrf_token = generate_csrf_token();
                        $honeypot_field = add_honeypot_field();
                        ?>
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <input type="text" name="<?php echo htmlspecialchars($honeypot_field); ?>" style="display:none;visibility:hidden;" tabindex="-1" autocomplete="off">
                        <?php if (isset($errors['device'])): ?>
                            <div class="alert alert-danger"><?php echo $errors['device']; ?></div>
                        <?php endif; ?>
                        <div class="device-registration-banner">
                            Please register your device. Enter a valid device number and serial number.
                        </div>
                        <div class="inline-login-form-group">
                            <input type="text" class="auth-input <?php echo isset($errors['device_number']) ? 'is-invalid' : ''; ?>" id="device_number" name="device_number" value="<?php echo isset($_SESSION['reg_data']['device_number']) ? htmlspecialchars($_SESSION['reg_data']['device_number']) : ''; ?>" placeholder=" " required>
                            <label for="device_number">Device Number *</label>
                            <?php if (isset($errors['device_number'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['device_number']; ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="inline-login-form-group">
                            <input type="text" class="auth-input <?php echo isset($errors['serial_number']) ? 'is-invalid' : ''; ?>" id="serial_number" name="serial_number" value="<?php echo isset($_SESSION['reg_data']['serial_number']) ? htmlspecialchars($_SESSION['reg_data']['serial_number']) : ''; ?>" placeholder=" " required>
                            <label for="serial_number">Serial Number *</label>
                            <?php if (isset($errors['serial_number'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['serial_number']; ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="inline-login-form-group">
                            <select class="auth-input <?php echo isset($errors['device_barangay_id']) ? 'is-invalid' : ''; ?>" id="device_barangay_id" name="device_barangay_id" required>
                                <option value="">Select a barangay</option>
                                <?php 
                                // Auto-select barangay from location step if available
                                $selected_barangay_id = isset($_SESSION['reg_data']['device_barangay_id']) ? $_SESSION['reg_data']['device_barangay_id'] : (isset($_SESSION['reg_data']['barangay_id']) ? $_SESSION['reg_data']['barangay_id'] : '');
                                foreach ($barangays_list as $barangay): 
                                    $cleanName = cleanBarangayName($barangay['barangay_name']);
                                ?>
                                    <option value="<?php echo htmlspecialchars($barangay['id']); ?>" <?php echo ($selected_barangay_id && $selected_barangay_id == $barangay['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cleanName); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <label for="device_barangay_id">Barangay *</label>
                            <?php if (isset($errors['device_barangay_id'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['device_barangay_id']; ?></div>
                            <?php endif; ?>
                            <div class="form-text">
                                <i class="fas fa-info-circle"></i> This field is automatically filled from your location selection.
                            </div>
                        </div>
                        <div class="device-registration-actions">
                            <button type="button" class="inline-login-submit ghost" onclick="window.location.href='registration.php?step=location'">← Back</button>
                            <button type="submit" name="device_submit" class="inline-login-submit">
                                Next: Create Credentials
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="9 18 15 12 9 6"></polyline>
                                </svg>
                            </button>
                        </div>
                    </form>
                <?php elseif ($step == 'credentials'): ?>
                    <form method="POST" action="" id="credentials-form" class="inline-login-form" onsubmit="return validateFormSubmission('credentials')">
                        <?php 
                        $csrf_token = generate_csrf_token();
                        $honeypot_field = add_honeypot_field();
                        ?>
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <input type="text" name="<?php echo htmlspecialchars($honeypot_field); ?>" style="display:none;visibility:hidden;" tabindex="-1" autocomplete="off">
                        <div class="inline-login-form-group">
                            <input type="text" class="auth-input <?php echo isset($errors['username']) ? 'is-invalid' : ''; ?>" id="username" name="username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required minlength="5" maxlength="50" pattern="[a-zA-Z0-9_]+" placeholder=" ">
                            <label for="username">Username *</label>
                            <?php if (isset($errors['username'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['username']; ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="inline-login-form-group">
                            <input type="password" class="auth-input <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>" id="password" name="password" required minlength="8" maxlength="255" placeholder=" ">
                            <label for="password">Password *</label>
                            <?php if (isset($errors['password'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['password']; ?></div>
                            <?php endif; ?>
                            <div class="form-text">Password must be at least 8 characters long</div>
                            <div id="password-strength"></div>
                        </div>
                        <div class="inline-login-form-group">
                            <input type="password" class="auth-input <?php echo isset($errors['confirm_password']) ? 'is-invalid' : ''; ?>" id="confirm_password" name="confirm_password" required minlength="8" maxlength="255" placeholder=" ">
                            <label for="confirm_password">Confirm Password *</label>
                            <?php if (isset($errors['confirm_password'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['confirm_password']; ?></div>
                            <?php endif; ?>
                            <div id="confirm-password-feedback"></div>
                        </div>
                        <?php if (isset($errors['recaptcha'])): ?>
                            <div class="alert alert-danger"><?php echo $errors['recaptcha']; ?></div>
                        <?php endif; ?>
                        <input type="hidden" name="g-recaptcha-response" id="g-recaptcha-response">
                        <div class="device-registration-actions">
                            <button type="button" class="inline-login-submit ghost" onclick="window.location.href='registration.php?step=device'">← Back</button>
                            <button type="submit" name="credentials_submit" class="inline-login-submit" id="credentials-submit-btn">
                                Complete Registration
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
                
                <div class="inline-register-actions">
                    Already have an account?
                    <a href="../index.php">Go to Login</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/leaflet@1.7.1/dist/leaflet.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="js/alerts.js"></script>
    <script src="js/validation.js?v=<?php echo time(); ?>"></script>
    <script src="https://cdn.jsdelivr.net/npm/leaflet-pip@0.1.0/leaflet-pip.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        // reCAPTCHA v3 handling for credentials form
        document.addEventListener('DOMContentLoaded', function() {
            const credentialsForm = document.getElementById('credentials-form');
            if (credentialsForm && window.recaptchaSiteKey && typeof grecaptcha !== 'undefined') {
                credentialsForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    // Execute reCAPTCHA v3
                    grecaptcha.ready(function() {
                        grecaptcha.execute(window.recaptchaSiteKey, {action: 'register'}).then(function(token) {
                            // Set the token in the hidden field
                            document.getElementById('g-recaptcha-response').value = token;
                            // Submit the form
                            credentialsForm.submit();
                        }).catch(function(error) {
                            console.error('reCAPTCHA error:', error);
                            alert('Security verification failed. Please try again.');
                        });
                    });
                });
            }
        });
        
        // Create animated particles
        const particlesContainer = document.getElementById('particles');
        if (particlesContainer) {
            const particleCount = 50;
            for (let i = 0; i < particleCount; i++) {
                const particle = document.createElement('div');
                particle.className = 'particle';
                particle.style.left = Math.random() * 100 + '%';
                particle.style.top = Math.random() * 100 + '%';
                particle.style.animationDelay = Math.random() * 15 + 's';
                particle.style.animationDuration = (Math.random() * 10 + 10) + 's';
                particlesContainer.appendChild(particle);
            }
        }
    </script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Hide/show labels based on input content
        function toggleLabelVisibility(input) {
            const label = input.nextElementSibling;
            if (label && label.tagName === 'LABEL') {
                let hasValue = false;
                
                // Handle different input types
                if (input.tagName === 'SELECT') {
                    hasValue = input.value && input.value !== '';
                } else if (input.tagName === 'TEXTAREA') {
                    hasValue = input.value && input.value.trim() !== '';
                } else {
                    hasValue = input.value && input.value.trim() !== '';
                }
                
                if (hasValue) {
                    label.style.opacity = '0';
                    label.style.visibility = 'hidden';
                } else {
                    label.style.opacity = '1';
                    label.style.visibility = 'visible';
                }
            }
        }
        
        // Apply to all auth-input fields
        const authInputs = document.querySelectorAll('.auth-input');
        authInputs.forEach(function(input) {
            // Check on page load
            toggleLabelVisibility(input);
            
            // Check on input/change
            input.addEventListener('input', function() {
                toggleLabelVisibility(input);
            });
            
            // For select elements, also listen to change event
            if (input.tagName === 'SELECT') {
                input.addEventListener('change', function() {
                    toggleLabelVisibility(input);
                });
            }
            
            // Check on blur
            input.addEventListener('blur', function() {
                toggleLabelVisibility(input);
            });
        });
        
        // Auto-fill device barangay from location barangay
        const currentStep = '<?php echo $step; ?>';
        if (currentStep === 'device') {
            const deviceBarangaySelect = document.getElementById('device_barangay_id');
            const locationBarangayId = <?php echo isset($_SESSION['reg_data']['barangay_id']) ? json_encode($_SESSION['reg_data']['barangay_id']) : 'null'; ?>;
            
            if (deviceBarangaySelect && locationBarangayId) {
                // Check if the select already has a value (from PHP)
                if (!deviceBarangaySelect.value || deviceBarangaySelect.value === '') {
                    // Auto-select the barangay from location step
                    deviceBarangaySelect.value = locationBarangayId;
                    
                    // Trigger change event to update label visibility
                    const changeEvent = new Event('change', { bubbles: true });
                    deviceBarangaySelect.dispatchEvent(changeEvent);
                    
                    // Also trigger input event for label visibility
                    const inputEvent = new Event('input', { bubbles: true });
                    deviceBarangaySelect.dispatchEvent(inputEvent);
                }
            }
        }
    });

    // Email Verification Functions
    function sendEmailVerification() {
        const emailInput = document.getElementById('email');
        const email = emailInput.value.trim();
        
        if (!email) {
            showErrorAlert('Please enter an email address first.');
            return;
        }
        
        if (!isValidEmail(email)) {
            showErrorAlert('Please enter a valid email address.');
            return;
        }
        
        // Show loading state
        const verifyBtn = document.getElementById('verify-email-btn');
        const btnText = document.getElementById('verify-btn-text');
        
        verifyBtn.disabled = true;
        btnText.textContent = 'Sending...';
        
        // Send verification request
        const formData = new FormData();
        formData.append('email', email);
        
        fetch('send_verification_code.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showSuccessAlert('Verification code sent to your email!');
                showVerificationCodeModal();
            } else {
                showErrorAlert(data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showErrorAlert('Network error. Please try again.');
        })
        .finally(() => {
            // Reset button state
            verifyBtn.disabled = false;
            btnText.textContent = 'Send Verification';
        });
    }
    
    function verifyEmailCode() {
        const emailInput = document.getElementById('email');
        const codeInput = document.getElementById('verification-code');
        const email = emailInput.value.trim();
        const code = codeInput.value.trim();
        
        if (!code) {
            showErrorAlert('Please enter the verification code.');
            return;
        }
        
        if (!/^[0-9]{6}$/.test(code)) {
            showErrorAlert('Please enter a valid 6-digit verification code.');
            return;
        }
        
        // Show loading state
        const verifyCodeBtn = document.getElementById('verify-code-btn-text');
        
        verifyCodeBtn.textContent = 'Verifying...';
        
        // Send verification request
        const formData = new FormData();
        formData.append('email', email);
        formData.append('verification_code', code);
        
        fetch('verify_email_code.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showSuccessAlert(data.message);
                updateVerificationStatus('Email verified successfully! ✓', 'success');
                markEmailAsVerified();
                hideVerificationCodeSection();
                
                // Trigger validation update for email field
                if (window.formValidator) {
                    setTimeout(() => {
                        window.formValidator.validateField('email', window.formValidator.validateEmail);
                    }, 500);
                }
            } else {
                showErrorAlert(data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showErrorAlert('Network error. Please try again.');
        })
        .finally(() => {
            // Reset button state
            verifyCodeBtn.textContent = 'Verify Code';
        });
    }
    
    function showVerificationCodeSection() {
        const statusDiv = document.getElementById('email-verification-status');
        const codeSection = document.getElementById('verification-code-section');
        
        statusDiv.style.display = 'block';
        codeSection.style.display = 'block';
        
        // Focus on verification code input
        setTimeout(() => {
            document.getElementById('verification-code').focus();
        }, 500);
    }
    
    function showVerificationCodeModal() {
        // Reset attempts counter when modal opens
        verificationAttempts = 0;
        
        Swal.fire({
            title: 'Email Verification',
            html: `
                <div class="text-center">
                    <i class="fas fa-envelope fa-3x mb-3" style="color: var(--primary-color);"></i>
                    <p class="mb-3">We've sent a 6-digit verification code to your email address.</p>
                    <p style="color: rgba(255, 255, 255, 0.7); margin-bottom: 1.5rem;">Please check your inbox and enter the code below:</p>
                    <input type="text" 
                           id="modal-verification-code" 
                           class="form-control form-control-lg text-center" 
                           placeholder="Enter 6-digit code" 
                           maxlength="6" 
                           pattern="[0-9]{6}"
                           style="font-size: 1.5rem; letter-spacing: 0.4rem; font-weight: 700; color: #ff5a4d; text-shadow: 0 0 3px rgba(255, 90, 77, 0.35); caret-color: #ff5a4d; background: rgba(255,255,255,0.92);">
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Verify Code',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#ff8c00',
            cancelButtonColor: '#6c757d',
            allowOutsideClick: false,
            allowEscapeKey: false,
            preConfirm: () => {
                const code = document.getElementById('modal-verification-code').value.trim();
                if (!code) {
                    Swal.showValidationMessage('Please enter the verification code');
                    return false;
                }
                if (!/^[0-9]{6}$/.test(code)) {
                    Swal.showValidationMessage('Please enter a valid 6-digit verification code');
                    return false;
                }
                return code;
            }
        }).then((result) => {
            if (result.isConfirmed) {
                const code = result.value;
                verifyEmailCodeFromModal(code);
            } else {
                // User cancelled, reset attempts counter
                verificationAttempts = 0;
            }
        });
        
        // Focus on the input field after modal opens
        setTimeout(() => {
            const input = document.getElementById('modal-verification-code');
            if (input) {
                input.focus();
            }
        }, 300);
    }
    
    function resendVerificationCode() {
        const emailInput = document.getElementById('email');
        const email = emailInput.value.trim();
        
        if (!email) {
            showErrorAlert('Email address not found.');
            return;
        }
        
        // Show loading state
        Swal.fire({
            title: 'Resending Code...',
            text: 'Please wait while we resend your verification code.',
            allowOutsideClick: false,
            showConfirmButton: false,
            willOpen: () => Swal.showLoading()
        });
        
        // Send verification request
        const formData = new FormData();
        formData.append('email', email);
        
        fetch('send_verification_code.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Code Resent!',
                    text: 'A new verification code has been sent to your email.',
                    confirmButtonColor: '#ff8c00',
                    timer: 2000
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.message,
                    confirmButtonColor: '#ff8c00'
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Network Error',
                text: 'Unable to resend verification code. Please try again.',
                confirmButtonColor: '#ff8c00'
            });
        });
    }
    
    // Track verification attempts
    let verificationAttempts = 0;
    const maxAttempts = 3;

    function verifyEmailCodeFromModal(code) {
        const emailInput = document.getElementById('email');
        const email = emailInput.value.trim();
        
        // Show loading state
        Swal.fire({
            title: 'Verifying Code...',
            text: 'Please wait while we verify your code.',
            allowOutsideClick: false,
            showConfirmButton: false,
            willOpen: () => Swal.showLoading()
        });
        
        // Send verification request
        const formData = new FormData();
        formData.append('email', email);
        formData.append('verification_code', code);
        
        fetch('verify_email_code.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                verificationAttempts = 0; // Reset attempts on success
                Swal.fire({
                    icon: 'success',
                    title: 'Email Verified!',
                    text: data.message,
                    confirmButtonColor: '#ff8c00',
                    timer: 2000
                }).then(() => {
                    markEmailAsVerified();
                    hideVerificationCodeSection();
                    
                    // Trigger validation update for email field
                    if (window.formValidator) {
                        setTimeout(() => {
                            window.formValidator.validateField('email', window.formValidator.validateEmail);
                        }, 500);
                    }
                });
            } else {
                // Check if it's a session-related error that should close the modal immediately
                const isSessionError = data.message && (
                    data.message.includes('No verification session found') ||
                    data.message.includes('verification session expired') ||
                    data.message.includes('session not found')
                );
                
                if (isSessionError) {
                    // Automatically close modal for session errors without showing additional message
                    Swal.close();
                    // Reset attempts counter
                    verificationAttempts = 0;
                } else {
                    verificationAttempts++;
                    const remainingAttempts = maxAttempts - verificationAttempts;
                    
                    if (verificationAttempts >= maxAttempts) {
                        // Show error with close button after 3 failed attempts
                        Swal.fire({
                            icon: 'error',
                            title: 'Verification Failed',
                            text: data.message + ' Maximum attempts reached. Please try again later.',
                            confirmButtonText: 'Close',
                            confirmButtonColor: '#ff8c00',
                            showCancelButton: false,
                            allowOutsideClick: false,
                            allowEscapeKey: false
                        }).then(() => {
                            // Reset attempts counter
                            verificationAttempts = 0;
                        });
                    } else {
                        // Show error but keep modal open
                        Swal.fire({
                            icon: 'error',
                            title: 'Verification Failed',
                            text: data.message + ` ${remainingAttempts} attempts remaining.`,
                            confirmButtonColor: '#ff8c00',
                            showCancelButton: true,
                            cancelButtonText: 'Cancel',
                            confirmButtonText: 'Try Again'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                // Show the verification modal again
                                showVerificationCodeModal();
                            } else {
                                // User cancelled, reset attempts
                                verificationAttempts = 0;
                            }
                        });
                    }
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            verificationAttempts++;
            const remainingAttempts = maxAttempts - verificationAttempts;
            
            if (verificationAttempts >= maxAttempts) {
                // Show error with close button after 3 failed attempts
                Swal.fire({
                    icon: 'error',
                    title: 'Network Error',
                    text: 'Unable to verify code. Maximum attempts reached. Please try again later.',
                    confirmButtonText: 'Close',
                    confirmButtonColor: '#ff8c00',
                    showCancelButton: false,
                    allowOutsideClick: false,
                    allowEscapeKey: false
                }).then(() => {
                    verificationAttempts = 0;
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Network Error',
                    text: `Unable to verify code. ${remainingAttempts} attempts remaining.`,
                    confirmButtonColor: '#ff8c00',
                    showCancelButton: true,
                    cancelButtonText: 'Cancel',
                    confirmButtonText: 'Try Again'
                }).then((result) => {
                    if (result.isConfirmed) {
                        showVerificationCodeModal();
                    } else {
                        verificationAttempts = 0;
                    }
                });
            }
        });
    }
    
    function hideVerificationCodeSection() {
        const codeSection = document.getElementById('verification-code-section');
        codeSection.style.display = 'none';
    }
    
    function updateVerificationStatus(message, type) {
        const messageDiv = document.getElementById('verification-message');
        messageDiv.textContent = message;
        messageDiv.className = `alert alert-${type === 'success' ? 'success' : 'info'} mt-2`;
        
        // Show verification status section when there's a message
        const statusDiv = document.getElementById('email-verification-status');
        if (statusDiv) {
            statusDiv.style.display = 'block';
        }
    }
    
    function markEmailAsVerified() {
        const emailInput = document.getElementById('email');
        const verifyBtn = document.getElementById('verify-email-btn');
        
        emailInput.setAttribute('data-verified', 'true');
        verifyBtn.disabled = true;
                    verifyBtn.innerHTML = '<span style="color: #fff;">✓ Verified</span>';
        verifyBtn.className = 'send-verification-btn verified';
        
        // Update validation state for the email field
        if (window.formValidator) {
            window.formValidator.updateFieldValidationState('email', {
                valid: true,
                message: 'Email verified successfully! ✓'
            });
        }
    }
    
    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
    
        // Check if email is already verified on page load
        document.addEventListener('DOMContentLoaded', function() {
            const emailInput = document.getElementById('email');
            if (emailInput && emailInput.getAttribute('data-verified') === 'true') {
                markEmailAsVerified();
            }
            
            // Auto-verify email when user enters a valid email (debounced)
            if (emailInput) {
                let emailVerificationTimeout;
                
                emailInput.addEventListener('input', function() {
                    const email = this.value.trim();
                    
                    // Clear previous timeout
                    clearTimeout(emailVerificationTimeout);
                    
                    if (this.getAttribute('data-verified') === 'true') {
                        // Reset verification status when email changes
                        this.removeAttribute('data-verified');
                        const verifyBtn = document.getElementById('verify-email-btn');
                        verifyBtn.disabled = false;
                        verifyBtn.innerHTML = '<i class="fas fa-paper-plane" style="margin-right: 0.5rem;"></i><span id="verify-btn-text">Send Verification</span>';
                        verifyBtn.className = 'send-verification-btn';
                        
                        // Hide verification status
                        const statusDiv = document.getElementById('email-verification-status');
                        if (statusDiv) {
                            statusDiv.style.display = 'none';
                        }
                        
                        // Update validation state to require verification again
                        if (window.formValidator) {
                            window.formValidator.updateFieldValidationState('email', {
                                valid: false,
                                message: 'Please verify your email address first'
                            });
                        }
                    }
                    
                    // Auto-verification disabled - user must click "Send Verification" button
                });
            }
        });
    
    // Hide validation report button on page load
    document.addEventListener('DOMContentLoaded', function() {
        // Hide any button that contains "Validation Report" or "📊" text
        const buttons = document.querySelectorAll('button');
        buttons.forEach(button => {
            const buttonText = button.textContent || button.innerText;
            if (buttonText.includes('Validation Report') || buttonText.includes('📊')) {
                button.style.display = 'none';
            }
        });
        
        // Hide password strength indicators
        const passwordStrengthDiv = document.getElementById('password-strength');
        if (passwordStrengthDiv) {
            passwordStrengthDiv.style.display = 'none';
        }
        
        // Hide password requirement text
        const passwordField = document.getElementById('password');
        if (passwordField) {
            const nextSibling = passwordField.nextElementSibling;
            if (nextSibling && nextSibling.classList.contains('form-text')) {
                nextSibling.style.display = 'none';
            }
        }
    });

    // Text-to-Speech functionality for welcome card
    function speakWelcomeText() {
        const speechBtn = document.getElementById('welcome-speech-btn');
        const speechIcon = document.getElementById('speech-icon');
        
        // Check if speech synthesis is supported
        if (!('speechSynthesis' in window)) {
            showErrorAlert('Text-to-speech is not supported by your browser.');
            return;
        }
        
        // Stop any current speech
        if (speechSynthesis.speaking) {
            speechSynthesis.cancel();
            speechIcon.className = 'fas fa-volume-up';
            speechBtn.style.background = 'rgba(255,255,255,0.2)';
            return;
        }
        
        // Welcome text to be spoken
        const welcomeText = `Welcome to Fire Guard. Your trusted partner in fire safety and detection. Join our community of safety-conscious individuals and help protect what matters most. Our features include Advanced Fire Detection, Real-time Alerts, and Community Safety.`;
        
        // Create speech utterance
        const speech = new SpeechSynthesisUtterance(welcomeText);
        speech.rate = 0.8; // Slightly slower for better clarity
        speech.pitch = 1.0;
        speech.volume = 0.9;
        
        // Get available voices and set a preferred voice
        speechSynthesis.onvoiceschanged = function() {
            const voices = speechSynthesis.getVoices();
            // Try to find an English voice
            const englishVoice = voices.find(voice => 
                voice.lang.startsWith('en') && voice.name.includes('Google')
            ) || voices.find(voice => 
                voice.lang.startsWith('en')
            ) || voices[0];
            
            if (englishVoice) {
                speech.voice = englishVoice;
            }
        };
        
        // Update button appearance when speaking starts
        speech.onstart = function() {
            speechIcon.className = 'fas fa-stop';
            speechBtn.style.background = 'rgba(255,255,255,0.4)';
            speechBtn.title = 'Stop speaking';
        };
        
        // Update button appearance when speaking ends
        speech.onend = function() {
            speechIcon.className = 'fas fa-volume-up';
            speechBtn.style.background = 'rgba(255,255,255,0.2)';
            speechBtn.title = 'Listen to welcome message';
        };
        
        // Handle speech errors
        speech.onerror = function(event) {
            console.error('Speech synthesis error:', event.error);
            speechIcon.className = 'fas fa-volume-up';
            speechBtn.style.background = 'rgba(255,255,255,0.2)';
            speechBtn.title = 'Listen to welcome message';
            showErrorAlert('Unable to play audio. Please check your browser settings.');
        };
        
        // Start speaking
        speechSynthesis.speak(speech);
    }
    </script>
        <script>
    // Initialize map when on location step
    document.addEventListener('DOMContentLoaded', function() {
        const currentStep = '<?php echo $step; ?>';
        const mapContainer = document.getElementById('map');
        
        console.log('Current step:', currentStep);
        console.log('Map container:', mapContainer);
        
        if (currentStep === 'location' && mapContainer) {
            console.log('Initializing map for location step...');
            
            // Check if Leaflet is loaded
            if (typeof L === 'undefined') {
                console.error('Leaflet library not loaded');
                mapContainer.innerHTML = '<div style="padding: 20px; text-align: center; color: #666;">Map library not loaded. Please refresh the page.</div>';
                return;
            }
            
            // Ensure map container is visible
            mapContainer.style.display = 'block';
            mapContainer.style.height = '500px';
            mapContainer.style.width = '100%';
            
            // Default coordinates (Bago City center)
            let defaultLat = <?php echo isset($_SESSION['reg_data']['latitude']) ? $_SESSION['reg_data']['latitude'] : '10.538287286291448'; ?>;
            let defaultLng = <?php echo isset($_SESSION['reg_data']['longitude']) ? $_SESSION['reg_data']['longitude'] : '122.83583164244666'; ?>;
            let userLocated = false;

            // Dynamic geo-fences will be loaded from database
            let geoFences = [];
            let cityLayers = [];

            // Lightweight point-in-polygon check (ray casting)
            function pointInPolygon(lat, lng, polygonCoords) {
                if (!Array.isArray(polygonCoords) || polygonCoords.length < 3) {
                    return false;
                }

                let inside = false;
                for (let i = 0, j = polygonCoords.length - 1; i < polygonCoords.length; j = i++) {
                    const latI = parseFloat(polygonCoords[i][0]); // y
                    const lngI = parseFloat(polygonCoords[i][1]); // x
                    const latJ = parseFloat(polygonCoords[j][0]);
                    const lngJ = parseFloat(polygonCoords[j][1]);

                    const intersect = ((lngI > lng) !== (lngJ > lng)) &&
                        (lat < (latJ - latI) * (lng - lngI) / ((lngJ - lngI) || 1e-12) + latI);
                    if (intersect) {
                        inside = !inside;
                    }
                }
                return inside;
            }

            // Function to load geo-fences from database
            async function loadGeoFences() {
                try {
                    const response = await fetch('get_geo_fences.php');
                    const data = await response.json();
                    
                    if (data.success && data.fences.length > 0) {
                        geoFences = data.fences;
                        console.log('Loaded geo-fences:', geoFences);
                        
                        // Create GeoJSON layers for each fence
                        geoFences.forEach((fence, index) => {
                            const geoJson = {
                                type: 'Feature',
                                geometry: {
                                    type: 'Polygon',
                                    coordinates: [fence.polygon.map(coord => [coord[1], coord[0]])] // Convert [lat, lng] to [lng, lat]
                                },
                                properties: {
                                    id: fence.id,
                                    city_name: fence.city_name,
                                    country_code: fence.country_code
                                }
                            };
                            
                            const layer = L.geoJSON(geoJson, {
                                style: {
                                    color: 'transparent',
                                    fillColor: 'transparent',
                                    fillOpacity: 0,
                                    weight: 0,
                                    opacity: 0
                                }
                            }).addTo(map);
                            
                            cityLayers.push(layer);
                        });
                        
                        // Update location requirements alert
                        const allowedAreasElement = document.getElementById('allowed-areas');
                        if (allowedAreasElement) {
                            const cityNames = geoFences.map(fence => fence.city_name).join(', ');
                            allowedAreasElement.textContent = `•You must be within: ${cityNames}`;
                        }
                        
                        return true;
                    } else {
                        console.error('Failed to load geo-fences:', data.message);
                        const allowedAreasElement = document.getElementById('allowed-areas');
                        if (allowedAreasElement) {
                            allowedAreasElement.textContent = 'No active geo-fences configured. Registration is currently disabled.';
                        }
                        
                        // Disable the location form
                        const locationFormWrapper = document.getElementById('location-form');
                        if (locationFormWrapper) {
                            locationFormWrapper.innerHTML = `
                                <div class="alert alert-danger" role="alert">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-exclamation-triangle" style="margin-right: 0.5rem;"></i>
                                        <div>
                                            <strong>Registration Temporarily Disabled</strong><br>
                                            No active geo-fences are configured. Please contact the administrator to set up geo-fencing before registration can proceed.
                                        </div>
                                    </div>
                                </div>
                                <div class="device-registration-actions">
                                    <button type="button" class="inline-login-submit ghost" onclick="window.location.href='registration.php?step=personal'">← Back</button>
                                    <button type="button" class="inline-login-submit secondary" onclick="window.location.reload()">Refresh</button>
                                </div>
                            `;
                        }
                        
                        showErrorAlert('No active geo-fences configured. Registration is currently disabled. Please contact support.');
                        return false;
                    }
                } catch (error) {
                    console.error('Error loading geo-fences:', error);
                    const allowedAreasElement = document.getElementById('allowed-areas');
                    if (allowedAreasElement) {
                        allowedAreasElement.textContent = 'Error loading geo-fence data. Registration is currently disabled.';
                    }
                    
                    // Disable the location form
                    const locationFormWrapper = document.getElementById('location-form');
                    if (locationFormWrapper) {
                        locationFormWrapper.innerHTML = `
                            <div class="alert alert-danger" role="alert">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-exclamation-triangle" style="margin-right: 0.5rem;"></i>
                                    <div>
                                        <strong>Registration Temporarily Disabled</strong><br>
                                        Error loading geo-fence data. Please contact the administrator to resolve this issue before registration can proceed.
                                    </div>
                                </div>
                            </div>
                            <div class="device-registration-actions">
                                <button type="button" class="inline-login-submit ghost" onclick="window.location.href='registration.php?step=personal'">← Back</button>
                                <button type="button" class="inline-login-submit secondary" onclick="window.location.reload()">Refresh</button>
                            </div>
                        `;
                    }
                    
                    showErrorAlert('Error loading geo-fence data. Registration is currently disabled. Please contact support.');
                    return false;
                }
            }

            // Create map with error handling
            let map;
            try {
                map = L.map('map', {
                    zoomControl: true,
                    scrollWheelZoom: true,
                    doubleClickZoom: true,
                    boxZoom: true,
                    keyboard: true,
                    dragging: true,
                    touchZoom: true
                }).setView([defaultLat, defaultLng], 15);
                console.log('Map created successfully');
            } catch (error) {
                console.error('Error creating map:', error);
                mapContainer.innerHTML = '<div style="padding: 20px; text-align: center; color: #666;">Map loading failed. Please refresh the page.</div>';
                return;
            }

            // Load geo-fences and initialize map
            loadGeoFences().then(loaded => {
                if (!loaded) {
                    console.error('Failed to load geo-fences');
                }
            });

            const osm = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            });
            const satellite = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                attribution: 'Tiles &copy; Esri &mdash; Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community'
            });
            osm.addTo(map);
            const baseMaps = { "OpenStreetMap": osm, "Satellite": satellite };
            L.control.layers(baseMaps).addTo(map);

            let marker;
            <?php if (isset($_SESSION['reg_data']['latitude']) && isset($_SESSION['reg_data']['longitude'])): ?>
            if (defaultLat && defaultLng) {
                marker = L.marker([defaultLat, defaultLng]).addTo(map)
                    .bindPopup("Your selected location").openPopup();
            }
            <?php endif; ?>

            // Point-in-polygon check using dynamic geo-fences
            function isPointInAnyFence(lat, lng) {
                console.log('Testing point:', lat, lng);
                console.log('Available geo-fences:', geoFences.length);
                
                if (geoFences.length === 0) {
                    console.warn('No geo-fences loaded - registration not allowed');
                    // No fallback: registration not allowed if no geo-fences are loaded
                    return { inFence: false, fence: null };
                }
                
                for (let i = 0; i < geoFences.length; i++) {
                    const fence = geoFences[i];
                    if (pointInPolygon(lat, lng, fence.polygon)) {
                        console.log('Point is in fence:', fence.city_name);
                        return { inFence: true, fence: fence };
                    }
                }
                
                console.log('Point is not in any fence');
                return { inFence: false, fence: null };
            }

            // Add this helper to enable/disable the Next button
            function setNextButtonEnabled(enabled) {
                const nextBtn = document.querySelector('form[action=""] button[name="location_submit"]');
                if (nextBtn) {
                    nextBtn.disabled = !enabled;
                }
            }

            // Initially disable Next button
            setNextButtonEnabled(false);

            // Add a loading spinner for reverse geocoding
            function showAddressLoading() {
                document.getElementById('current-location-info').innerHTML = '<span style="color: #4dabf7;">Getting address...</span>';
            }

            // Function to validate address automatically
            function validateAddressFromInput() {
                const addressField = document.getElementById('address');
                const address = addressField.value.trim();
                
                if (address.length === 0) {
                    document.getElementById('current-location-info').innerHTML = '';
                    setNextButtonEnabled(false);
                    
                    // Update form validator state for address field
                    if (window.formValidator) {
                        window.formValidator.updateFieldValidationState('address', {
                            valid: false,
                            message: 'Address is required'
                        });
                    }
                    return;
                }

                // Show loading message with spinner
                document.getElementById('current-location-info').innerHTML = '<span style="color: #4dabf7;"><i class="fas fa-spinner fa-spin"></i> Validating address...</span>';
                setNextButtonEnabled(false);

                // Create AbortController for timeout
                const searchController = new AbortController();
                const searchTimeoutId = setTimeout(() => searchController.abort(), 25000); // 25 second timeout

                // Geocode the address to get coordinates
                fetch(`address/nominatim_proxy.php?type=search&q=${encodeURIComponent(address)}&limit=1`, {
                    signal: searchController.signal
                })
                    .then(response => {
                        clearTimeout(searchTimeoutId);
                        if (!response.ok) {
                            return response.json().then(data => {
                                throw new Error(data.message || `HTTP error! status: ${response.status}`);
                            });
                        }
                        return response.json();
                    })
                    .then(payload => {
                        if (payload.success && Array.isArray(payload.data) && payload.data.length > 0) {
                            const result = payload.data[0];
                            const lat = parseFloat(result.lat);
                            const lng = parseFloat(result.lon);
                            
                            // Check if the location is within any allowed geo-fence
                            const fenceCheck = isPointInAnyFence(lat, lng);
                            if (fenceCheck.inFence) {
                                // Update hidden fields
                                document.getElementById('latitude').value = lat;
                                document.getElementById('longitude').value = lng;
                                
                                // Update map marker
                                if (marker) {
                                    marker.setLatLng([lat, lng]);
                                } else {
                                    marker = L.marker([lat, lng]).addTo(map)
                                        .bindPopup("Validated address location").openPopup();
                                }
                                
                                // Center map on the location
                                map.setView([lat, lng], 17);
                                
                                // Show success message
                                document.getElementById('current-location-info').innerHTML = 
                                    `<span style="color: #51cf66;"><i class="fas fa-check-circle"></i> Address validated successfully!</span><br>
                                     <b>Location:</b> ${lat.toFixed(6)}, ${lng.toFixed(6)}<br>
                                     <b>Address:</b> ${result.display_name}<br>
                                     <small style="color: rgba(255, 255, 255, 0.7);">You can now proceed to the next step.</small>`;
                                
                                setNextButtonEnabled(true);
                                
                                // Update form validator state for address field
                                if (window.formValidator) {
                                    window.formValidator.updateFieldValidationState('address', {
                                        valid: true,
                                        message: 'Address validated successfully'
                                    });
                                }
                                
                                // Update all field validation states
                                updateAllFieldValidationStates();
                                
                                // Show success toast
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Address Validated',
                                    text: 'Your address has been automatically validated and is within the allowed area.',
                                    showConfirmButton: false,
                                    timer: 2000
                                });
                            } else {
                                // Location is outside allowed area
                                document.getElementById('current-location-info').innerHTML = 
                                    '<span style="color: #ff6b6b;"><i class="fas fa-times-circle"></i> Address is outside the allowed area. Registration is not allowed.</span>';
                                setNextButtonEnabled(false);
                                
                                // Clear coordinates
                                document.getElementById('latitude').value = '';
                                document.getElementById('longitude').value = '';
                                
                                // Update form validator state for address field
                                if (window.formValidator) {
                                    window.formValidator.updateFieldValidationState('address', {
                                        valid: false,
                                        message: 'Address is outside the allowed area'
                                    });
                                }
                                
                                // Show error toast
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Invalid Address',
                                    text: 'This address is outside the allowed area. Please enter an address within Bago City.',
                                    showConfirmButton: true
                                });
                            }
                        } else {
                            const message = payload.message || 'Address not found. Please check your address or select a location on the map.';
                            document.getElementById('current-location-info').innerHTML = 
                                `<span style="color: #ffd43b;"><i class="fas fa-exclamation-triangle"></i> ${message}</span>`;
                            setNextButtonEnabled(false);
                            
                            // Clear coordinates
                            document.getElementById('latitude').value = '';
                            document.getElementById('longitude').value = '';
                            
                            // Update form validator state for address field
                            if (window.formValidator) {
                                window.formValidator.updateFieldValidationState('address', {
                                    valid: false,
                                    message: message
                                });
                            }
                        }
                    })
                    .catch(error => {
                        clearTimeout(searchTimeoutId);
                        console.error('Error validating address:', error);
                        let errorMessage = 'Error validating address. Please try again or select a location on the map.';
                        
                        // Check for timeout
                        if (error.name === 'AbortError' || error.message.includes('timeout') || error.message.includes('timed out')) {
                            errorMessage = 'Address validation timed out. Please try again or select a location on the map.';
                        } else if (error.message && (error.message.includes('503') || error.message.includes('rate limit') || error.message.includes('unavailable'))) {
                            errorMessage = 'Geocoding service is temporarily unavailable. Please wait a moment and try again, or select a location on the map.';
                        }
                        
                        document.getElementById('current-location-info').innerHTML = 
                            `<span style="color: #ff6b6b;"><i class="fas fa-times-circle"></i> ${errorMessage}</span>`;
                        setNextButtonEnabled(false);
                        
                        // Update form validator state for address field
                        if (window.formValidator) {
                            window.formValidator.updateFieldValidationState('address', {
                                valid: false,
                                message: errorMessage
                            });
                        }
                        
                        // Clear coordinates
                        document.getElementById('latitude').value = '';
                        document.getElementById('longitude').value = '';
                    });
            }

            // GEO-FENCING: Check location using dynamic geo-fences
            function checkCityAndSetFields(lat, lng, showToast = true) {
                const fenceCheck = isPointInAnyFence(lat, lng);
                if (!fenceCheck.inFence) {
                    if (showToast) {
                        const allowedCities = geoFences.map(fence => fence.city_name).join(', ');
                        Swal.fire({
                            icon: 'error',
                            title: 'Invalid Location',
                            text: `You are outside the allowed areas. Registration is only allowed within: ${allowedCities}`,
                            showConfirmButton: true
                        });
                    }
                    document.getElementById('latitude').value = '';
                    document.getElementById('longitude').value = '';
                    document.getElementById('address').value = '';
                    const allowedCities = geoFences.map(fence => fence.city_name).join(', ');
                    document.getElementById('current-location-info').innerHTML = `<span style="color: #ff6b6b;">Location is outside the allowed areas. Registration is only allowed within: ${allowedCities}</span>`;
                    setNextButtonEnabled(false);
                    
                    // Update form validator state for address field
                    if (window.formValidator) {
                        window.formValidator.updateFieldValidationState('address', {
                            valid: false,
                            message: 'Location is outside the allowed areas'
                        });
                    }
                    return;
                }
                // Show loading spinner
                showAddressLoading();
                
                // Create AbortController for timeout
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 25000); // 25 second timeout
                
                // Always reverse geocode and display full address
                fetch(`address/nominatim_proxy.php?type=reverse&lat=${lat}&lon=${lng}`, {
                    signal: controller.signal
                })
                    .then(response => {
                        clearTimeout(timeoutId);
                        if (!response.ok) {
                            return response.json().then(data => {
                                throw new Error(data.message || `HTTP error! status: ${response.status}`);
                            });
                        }
                        return response.json();
                    })
                    .then(payload => {
                        if (!payload.success || !payload.data) {
                            throw new Error(payload.message || 'Unable to get address');
                        }
                        const data = payload.data;
                        let address = '';
                        let extractedBarangay = '';
                        if (data.display_name) {
                            address = data.display_name;
                        } else if (data.address) {
                            // Fallback: build address from components
                            if (data.address.road) address += data.address.road + ', ';
                            if (data.address.suburb) address += data.address.suburb + ', ';
                            if (data.address.neighbourhood) address += data.address.neighbourhood + ', ';
                            if (data.address.hamlet) address += data.address.hamlet + ', ';
                            if (data.address.quarter) address += data.address.quarter + ', ';
                            if (data.address.city) address += data.address.city + ', ';
                            if (data.address.town) address += data.address.town + ', ';
                            if (data.address.village) address += data.address.village + ', ';
                            if (data.address.state) address += data.address.state + ', ';
                            if (data.address.country) address += data.address.country;
                        }
                        address = address.replace(/, $/, ''); // Remove trailing comma
                        document.getElementById('address').value = address || 'Address not found';
                        // Try to extract barangay-like value
                        if (data && data.address) {
                            extractedBarangay = data.address.barangay || data.address.suburb || data.address.neighbourhood || data.address.village || '';
                            
                            // Clean the barangay name: remove redundant prefixes and suffixes
                            function cleanBarangayName(name) {
                                if (!name) return '';
                                // Remove common prefixes (case-insensitive)
                                name = name.replace(/^(barangay|brgy\.?|br\.?)\s+/i, '');
                                // Remove anything after comma (city/municipality names)
                                name = name.split(',')[0];
                                // Trim whitespace
                                name = name.trim();
                                return name;
                            }
                            
                            const cleanedBarangay = cleanBarangayName(extractedBarangay);
                            const brgySelect = document.getElementById('barangay');
                            if (brgySelect && cleanedBarangay) {
                                // If option exists, select it; else add temporarily
                                let found = false;
                                for (let i = 0; i < brgySelect.options.length; i++) {
                                    const optionValue = cleanBarangayName(brgySelect.options[i].value);
                                    if (optionValue.toLowerCase() === cleanedBarangay.toLowerCase()) {
                                        brgySelect.selectedIndex = i;
                                        found = true;
                                        break;
                                    }
                                }
                                if (!found) {
                                    const opt = document.createElement('option');
                                    opt.value = cleanedBarangay;
                                    opt.textContent = cleanedBarangay; // Display only clean name
                                    brgySelect.appendChild(opt);
                                    brgySelect.value = cleanedBarangay;
                                    
                                    // Get barangay_id for the newly created barangay
                                    getBarangayId(cleanedBarangay);
                                } else {
                                    // Get barangay_id for existing barangay
                                    const selectedOption = brgySelect.options[brgySelect.selectedIndex];
                                    const barangayId = selectedOption.getAttribute('data-barangay-id');
                                    if (barangayId) {
                                        document.getElementById('barangay_id').value = barangayId;
                                        console.log('Barangay ID set to:', barangayId);
                                    }
                                }
                                
                                // Validate barangay after auto-select with a small delay to ensure DOM is updated
                                setTimeout(() => {
                                    if (typeof validateBarangaySelection === 'function') {
                                        validateBarangaySelection();
                                    }
                                }, 100);
                            }
                        }
                        document.getElementById('current-location-info').innerHTML =
                            `<b>Selected Location:</b> ${lat.toFixed(6)}, ${lng.toFixed(6)}<br><b>Full Address:</b> ${address}<br><small style="color: rgba(255, 255, 255, 0.7);">Address validation will be checked automatically...</small>`;
                        setNextButtonEnabled(!!address && address !== 'Address not found');
                        
                        // Update form validator state for address field
                        if (window.formValidator && address && address !== 'Address not found') {
                            window.formValidator.updateFieldValidationState('address', {
                                valid: true,
                                message: 'Address validated successfully'
                            });
                        }
                        
                        // Update all field validation states
                        updateAllFieldValidationStates();
                        if (showToast) {
                            const cityName = fenceCheck.fence ? fenceCheck.fence.city_name : 'allowed area';
                            Swal.fire({
                                icon: 'success',
                                title: 'Valid Location',
                                text: `You are inside ${cityName}. Registration is allowed.`,
                                showConfirmButton: false,
                                timer: 2000
                            });
                        }
                    })
                    .catch(error => {
                        clearTimeout(timeoutId);
                        console.error('Error reverse geocoding:', error);
                        
                        // Determine error message
                        let errorMessage = 'Unable to automatically get address from coordinates.';
                        let isTimeout = false;
                        
                        if (error.name === 'AbortError' || error.message.includes('timeout') || error.message.includes('timed out')) {
                            errorMessage = 'Address lookup timed out. You can manually enter your address below.';
                            isTimeout = true;
                        } else if (error.message && (error.message.includes('503') || error.message.includes('rate limit') || error.message.includes('unavailable'))) {
                            errorMessage = 'Geocoding service is temporarily unavailable. You can manually enter your address below.';
                        }
                        
                        // Show warning but allow user to proceed with manual address entry
                        const cityName = fenceCheck.fence ? fenceCheck.fence.city_name : 'allowed area';
                        document.getElementById('current-location-info').innerHTML = 
                            `<b>Selected Location:</b> ${lat.toFixed(6)}, ${lng.toFixed(6)}<br>` +
                            `<b>Status:</b> <span style="color: #ffa500;"><i class="fas fa-exclamation-triangle"></i> ${errorMessage}</span><br>` +
                            `<small style="color: rgba(255, 255, 255, 0.7);">You are inside ${cityName}. Please enter your address manually below.</small>`;
                        
                        // Don't disable the next button - allow user to proceed with manual address entry
                        // The address field will be validated separately
                        setNextButtonEnabled(true);
                        
                        // Update form validator state - mark as requiring manual entry
                        if (window.formValidator) {
                            window.formValidator.updateFieldValidationState('address', {
                                valid: null, // null means not yet validated, user needs to enter manually
                                message: 'Please enter your address manually'
                            });
                        }
                        
                        // Show a less intrusive notification
                        if (showToast && !isTimeout) {
                            Swal.fire({
                                icon: 'info',
                                title: 'Valid Location',
                                text: `You are inside ${cityName}. Please enter your address manually.`,
                                showConfirmButton: false,
                                timer: 3000
                            });
                        }
                    });
            }

            // On map click, place or move marker
            map.on('click', function(e) {
                if (marker) {
                    marker.setLatLng(e.latlng);
                } else {
                    marker = L.marker(e.latlng).addTo(map)
                        .bindPopup("Your selected location").openPopup();
                }
                // Update hidden fields
                document.getElementById('latitude').value = e.latlng.lat;
                document.getElementById('longitude').value = e.latlng.lng;
                checkCityAndSetFields(e.latlng.lat, e.latlng.lng, true);
            });

            // Geolocation logic as a function
            function locateUser(auto = false) {
                if (navigator.geolocation) {
                    navigator.geolocation.getCurrentPosition(function(position) {
                        userLocated = true;
                        const lat = position.coords.latitude;
                        const lng = position.coords.longitude;
                        map.setView([lat, lng], 17);
                        if (marker) {
                            marker.setLatLng([lat, lng]);
                        } else {
                            marker = L.marker([lat, lng]).addTo(map)
                                .bindPopup(auto ? "Your current location (auto-detected)" : "Your current location").openPopup();
                        }
                        document.getElementById('latitude').value = lat;
                        document.getElementById('longitude').value = lng;
                        checkCityAndSetFields(lat, lng, true);
                    }, function(error) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Geolocation Error',
                            text: 'Unable to get your current location. Please click on the map to select your location.'
                        });
                        document.getElementById('current-location-info').innerHTML =
                            '<span style="color: #ff6b6b;">Unable to get your current location. Please click on the map to select your location.</span>';
                        setNextButtonEnabled(false);
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Not Supported',
                        text: 'Geolocation is not supported by your browser. Please click on the map to select your location.'
                    });
                    document.getElementById('current-location-info').innerHTML =
                        '<span style="color: #ff6b6b;">Geolocation is not supported by your browser. Please click on the map to select your location.</span>';
                    setNextButtonEnabled(false);
                }
            }
            
            // Attach to button
            document.getElementById('get-location-btn').addEventListener('click', function() { locateUser(false); });
            // Automatically locate user on page load
            locateUser(true);
            
            // Add event listener for automatic address validation
            const addressField = document.getElementById('address');
            if (addressField) {
                let validationTimeout;
                addressField.addEventListener('input', function() {
                    // Clear previous timeout
                    clearTimeout(validationTimeout);
                    
                    // Clear validation state when user starts typing
                    if (window.formValidator) {
                        window.formValidator.updateFieldValidationState('address', {
                            valid: false,
                            message: 'Validating address...'
                        });
                    }
                    
                    // Set a new timeout to validate after user stops typing for 1.5 seconds
                    validationTimeout = setTimeout(function() {
                        validateAddressFromInput();
                    }, 1500);
                });
                
                // Also validate on blur (when user leaves the field)
                addressField.addEventListener('blur', function() {
                    clearTimeout(validationTimeout);
                    validateAddressFromInput();
                });
                
                // Add change event listener to trigger barangay validation when address changes
                addressField.addEventListener('change', function() {
                    // Trigger barangay validation when address changes
                    setTimeout(() => {
                        if (typeof window.validateBarangaySelection === 'function') {
                            window.validateBarangaySelection();
                        }
                    }, 100);
                });
            }
        }
    });

    // SweetAlert for PHP feedback
    document.addEventListener('DOMContentLoaded', function() {
        <?php if ($success): ?>
            Swal.fire({
                icon: 'success',
                title: 'Success',
                text: <?php echo json_encode($success); ?>,
                confirmButtonColor: '#ff8c00'
            });
        <?php endif; ?>
        <?php if (!empty($errors)): ?>
            <?php foreach ($errors as $err): ?>
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: <?php echo json_encode($err); ?>,
                    confirmButtonColor: '#ff8c00'
                });
            <?php endforeach; ?>
        <?php endif; ?>
    });

    // Alert functions
    function showLoadingAlert(title = 'Processing...') {
        return Swal.fire({
            title: title,
            html: 'Please wait...',
            allowOutsideClick: false,
            showConfirmButton: false,
            willOpen: () => Swal.showLoading()
        });
    }

    function showSuccessAlert(message, title = 'Success!') {
        // Text-to-speech functionality
        if ('speechSynthesis' in window) {
            const speech = new SpeechSynthesisUtterance(message);
            speech.rate = 0.9; // Slightly slower for better clarity
            speech.pitch = 1.0;
            speech.volume = 0.8;
            
            // Get available voices and set a preferred voice
            speechSynthesis.onvoiceschanged = function() {
                const voices = speechSynthesis.getVoices();
                // Try to find an English voice
                const englishVoice = voices.find(voice => 
                    voice.lang.startsWith('en') && voice.name.includes('Google')
                ) || voices.find(voice => 
                    voice.lang.startsWith('en')
                ) || voices[0];
                
                if (englishVoice) {
                    speech.voice = englishVoice;
                }
            };
            
            // Speak the message
            speechSynthesis.speak(speech);
        }
        
        return Swal.fire({
            title: title,
            text: message,
            icon: 'success',
            confirmButtonText: 'OK',
            timer: 3000,
            timerProgressBar: true
        });
    }

    function showErrorAlert(message, title = 'Error!') {
        return Swal.fire({
            title: title,
            text: message,
            icon: 'error',
            confirmButtonText: 'OK'
        });
    }

    function showFormValidationError(errorMessages) {
        let message = '';
        if (Array.isArray(errorMessages)) {
            message = errorMessages.join('<br>');
        } else {
            message = errorMessages;
        }
        return Swal.fire({
            title: 'Validation Error',
            html: message,
            icon: 'error',
            confirmButtonText: 'OK'
        });
    }

    function showTimeoutError(message) {
        return Swal.fire({
            title: 'Timeout Error',
            text: message,
            icon: 'warning',
            confirmButtonText: 'OK'
        });
    }

        // Function to update validation state for all fields
        function updateAllFieldValidationStates() {
            // Update address field validation state
            var addressField = $('#address');
            var latitudeField = $('#latitude');
            var longitudeField = $('#longitude');
            
            if (addressField.length && addressField.val().trim().length > 0 && 
                latitudeField.val() && longitudeField.val()) {
                addressField.addClass('is-valid').removeClass('is-invalid');
            } else if (addressField.length && addressField.val().trim().length > 0) {
                addressField.addClass('is-invalid').removeClass('is-valid');
            }
            
            // Update barangay field validation state
            var barangayField = $('#barangay');
            if (barangayField.length && barangayField.val().trim().length > 0) {
                barangayField.addClass('is-valid').removeClass('is-invalid');
            }
            
            // Update building type field validation state
            var buildingTypeField = $('#building_type');
            if (buildingTypeField.length && buildingTypeField.val().trim().length > 0) {
                buildingTypeField.addClass('is-valid').removeClass('is-invalid');
            }
            
            // Update building name field validation state
            var buildingNameField = $('#building_name');
            if (buildingNameField.length && buildingNameField.val().trim().length > 0) {
                buildingNameField.addClass('is-valid').removeClass('is-invalid');
            }
        }

        // Real-time AJAX validation for registration fields
        $(document).ready(function() {
            // Update validation states on page load
            updateAllFieldValidationStates();
            
            // Initialize date picker for birthdate field
            flatpickr("#birthdate", {
                dateFormat: "Y-m-d",
                maxDate: new Date(),
                minDate: new Date('1900-01-01'),
                allowInput: true,
                clickOpens: true,
                theme: "dark",
                disableMobile: false,
                onChange: function(selectedDates, dateStr, instance) {
                    // Trigger validation when date changes
                    validateBirthdate();
                }
            });
        
        // Debounce function to limit API calls
        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
        
        // Building name validation
        const validateBuildingName = debounce(function() {
            const building_name = $('#building_name').val().trim();
            if (building_name.length === 0) return;
            $.post('ajax_validate.php', {type: 'building_name', building_name: building_name}, function(data) {
                showFeedback($('#building_name'), data.valid, data.message);
            }, 'json');
        }, 500);
        
        $('#building_name').on('blur input', validateBuildingName);
        
        // Device validation functions
        const validateDeviceNumber = debounce(function() {
            const device_number = $('#device_number').val().trim();
            if (device_number.length === 0) return;
            $.post('ajax_validate.php', {type: 'device_number', device_number: device_number}, function(data) {
                showFeedback($('#device_number'), data.valid, data.message);
                // Store the admin_device_id if valid for serial number validation
                if (data.valid && data.admin_device_id) {
                    $('#device_number').attr('data-admin-device-id', data.admin_device_id);
                } else {
                    $('#device_number').removeAttr('data-admin-device-id');
                }
            }, 'json');
        }, 500);
        
        const validateSerialNumber = debounce(function() {
            const serial_number = $('#serial_number').val().trim();
            const device_number = $('#device_number').val().trim();
            const admin_device_id = $('#device_number').attr('data-admin-device-id');
            
            if (serial_number.length === 0) return;
            
            $.post('ajax_validate.php', {
                type: 'serial_number', 
                serial_number: serial_number,
                device_number: device_number,
                admin_device_id: admin_device_id
            }, function(data) {
                showFeedback($('#serial_number'), data.valid, data.message);
            }, 'json');
        }, 500);
        
        // Attach device validation to input events
        $('#device_number').on('blur input', validateDeviceNumber);
        $('#serial_number').on('blur input', validateSerialNumber);

        // Function to get barangay_id from barangay name
        function getBarangayId(barangayName) {
            if (!barangayName) return;
            
            fetch('get_barangay_id.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'barangay_name=' + encodeURIComponent(barangayName)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.barangay_id) {
                    document.getElementById('barangay_id').value = data.barangay_id;
                    console.log('Barangay ID set to:', data.barangay_id);
                } else {
                    console.warn('Failed to get barangay_id for:', barangayName);
                }
            })
            .catch(error => {
                console.error('Error getting barangay_id:', error);
            });
        }

        // Barangay validation: must not be empty and must match full address
        window.validateBarangaySelection = debounce(function() {
            const brgyEl = $('#barangay');
            const addrEl = $('#address');
            const brgy = (brgyEl.val() || '').trim().toLowerCase();
            const addr = (addrEl.val() || '').toLowerCase().replace(/[\s,]+/g, ' ');
            let valid = true;
            let message = 'Barangay is valid';
            if (!brgy) {
                valid = false;
                message = 'Barangay is required.';
            } else {
                // whole-word match
                const re = new RegExp('(^|\\s)' + brgy.replace(/[.*+?^${}()|[\\]\\\\]/g, '\\$&') + '(?=\\s|$)');
                if (!re.test(addr)) {
                    valid = false;
                    message = 'Barangay must match your full address.';
                }
            }
            showFeedback(brgyEl, valid, message);
            return valid;
        }, 300);

        // Clean existing barangay option text on page load (in case page was cached)
        function cleanExistingBarangayOptions() {
            const brgySelect = document.getElementById('barangay');
            if (brgySelect) {
                function cleanBarangayName(name) {
                    if (!name) return '';
                    name = name.replace(/^(barangay|brgy\.?|br\.?)\s+/i, '');
                    name = name.split(',')[0];
                    name = name.trim();
                    return name;
                }
                
                for (let i = 0; i < brgySelect.options.length; i++) {
                    const option = brgySelect.options[i];
                    if (option.value && option.value !== '') {
                        const cleanName = cleanBarangayName(option.value);
                        if (cleanName !== option.textContent) {
                            option.textContent = cleanName;
                        }
                    }
                }
            }
        }
        
        // Clean options on page load
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', cleanExistingBarangayOptions);
        } else {
            cleanExistingBarangayOptions();
        }
        
        $('#barangay').on('change blur', function(){
            // Get barangay_id when user manually selects a barangay
            const selectedOption = $(this).find('option:selected');
            const barangayId = selectedOption.data('barangay-id');
            if (barangayId) {
                document.getElementById('barangay_id').value = barangayId;
                console.log('Barangay ID set to:', barangayId);
            }
            
            // Validate barangay selection after a small delay to ensure DOM is updated
            setTimeout(() => {
                if (typeof window.validateBarangaySelection === 'function') {
                    window.validateBarangaySelection();
                }
                // Update all field validation states
                updateAllFieldValidationStates();
            }, 50);
        });
        
        // Add event listeners for building type and building name fields
        $('#building_type').on('change blur', function(){
            updateAllFieldValidationStates();
        });
        
        $('#building_name').on('input blur', function(){
            updateAllFieldValidationStates();
        });
        
        // Add event listener for address field changes
        $('#address').on('input blur', function(){
            updateAllFieldValidationStates();
        });
        
        // Feedback function for validation
        function showFeedback(element, isValid, message) {
            element.removeClass('is-valid is-invalid');
            element.addClass(isValid ? 'is-valid' : 'is-invalid');
            
            // Remove existing feedback
            element.siblings('.valid-feedback, .invalid-feedback').remove();
            
            // Add new feedback
            const feedbackClass = isValid ? 'valid-feedback' : 'invalid-feedback';
            element.after(`<div class="${feedbackClass}">${message}</div>`);
        }
        
        // Show/hide password toggle
        $('#showPasswordCheck').on('change', function() {
            var type = $(this).is(':checked') ? 'text' : 'password';
            $('#password, #confirm_password').attr('type', type);
        });
        
        // Auto-format contact number
        $('#contact').on('input', function() {
            var value = $(this).val().replace(/\D/g, '');
            
            // Remove any existing formatting
            if (value.startsWith('63')) {
                value = value.substring(2);
            }
            
            // Ensure it starts with 09
            if (value.length > 0) {
                if (value.startsWith('09')) {
                    // Keep the 09 prefix
                    if (value.length > 11) {
                        value = value.substring(0, 11);
                    }
                } else if (value.startsWith('9')) {
                    // Add 0 prefix if it starts with 9
                    value = '0' + value;
                    if (value.length > 11) {
                        value = value.substring(0, 11);
                    }
                } else {
                    // If it doesn't start with 9, add 09 prefix
                    value = '09' + value;
                    if (value.length > 11) {
                        value = value.substring(0, 11);
                    }
                }
            }
            
            $(this).val(value);
        });
        

        
        // Form validation function
        function validateFormSubmission(step) {
            let isValid = true;
            let errorMessages = [];
            
            // Get the form for the current step
            let form;
            switch(step) {
                case 'personal':
                    form = $('#personal-form');
                    break;
                case 'location':
                    form = $('#location-form');
                    break;
                case 'device':
                    form = $('#device-form');
                    break;
                case 'credentials':
                    form = $('#credentials-form');
                    break;
                default:
                    form = $('form');
            }
            
            // Check for validation errors
            form.find('.is-invalid').each(function() {
                isValid = false;
                var errorText = $(this).next('.invalid-feedback').text();
                if (errorText) {
                    errorMessages.push(errorText);
                }
            });
            
            // Special validation for location step
            if (step === 'location') {
                var addressField = $('#address');
                var barangayField = $('#barangay');
                var buildingTypeField = $('#building_type');
                var buildingNameField = $('#building_name');
                var latitudeField = $('#latitude');
                var longitudeField = $('#longitude');
                
                // Validate address field
                if (addressField.length && addressField.val().trim().length === 0) {
                    isValid = false;
                    errorMessages.push('Address is required. Please select a location on the map or enter your address.');
                    addressField.addClass('is-invalid').removeClass('is-valid');
                } else if (addressField.length && addressField.val().trim().length > 0) {
                    addressField.addClass('is-valid').removeClass('is-invalid');
                }
                
                // Validate barangay field
                if (barangayField.length && barangayField.val().trim().length === 0) {
                    isValid = false;
                    errorMessages.push('Barangay is required.');
                    barangayField.addClass('is-invalid').removeClass('is-valid');
                } else if (barangayField.length && barangayField.val().trim().length > 0) {
                    barangayField.addClass('is-valid').removeClass('is-invalid');
                }
                
                // Barangay must appear in full address (whole-word, case-insensitive)
                if (addressField.length && barangayField.length && 
                    addressField.val().trim().length > 0 && barangayField.val().trim().length > 0) {
                    var addr = (addressField.val() || '').toLowerCase().replace(/[\s,]+/g, ' ');
                    var brgy = (barangayField.val() || '').toLowerCase().trim();
                    var re = new RegExp('(^|\\s)' + brgy.replace(/[.*+?^${}()|[\\]\\\\]/g, '\\$&') + '(?=\\s|$)');
                    if (!re.test(addr)) {
                        isValid = false;
                        errorMessages.push('Barangay must match your full address.');
                        barangayField.addClass('is-invalid').removeClass('is-valid');
                    } else {
                        barangayField.addClass('is-valid').removeClass('is-invalid');
                    }
                }
                
                // Validate building type field
                if (buildingTypeField.length && buildingTypeField.val().trim().length === 0) {
                    isValid = false;
                    errorMessages.push('Building type is required. Please select a building type.');
                    buildingTypeField.addClass('is-invalid').removeClass('is-valid');
                } else if (buildingTypeField.length && buildingTypeField.val().trim().length > 0) {
                    buildingTypeField.addClass('is-valid').removeClass('is-invalid');
                }
                
                // Validate building name field
                if (buildingNameField.length && buildingNameField.val().trim().length === 0) {
                    isValid = false;
                    errorMessages.push('Building name is required.');
                    buildingNameField.addClass('is-invalid').removeClass('is-valid');
                } else if (buildingNameField.length && buildingNameField.val().trim().length > 0) {
                    buildingNameField.addClass('is-valid').removeClass('is-invalid');
                }
                
                // Check if address has been automatically validated (coordinates are set)
                if (addressField.length && addressField.val().trim().length > 0 && 
                    (!latitudeField.val() || !longitudeField.val())) {
                    // Address is entered but coordinates are not set (not validated)
                    isValid = false;
                    errorMessages.push('Please wait for address validation to complete or select a location on the map.');
                    addressField.addClass('is-invalid').removeClass('is-valid');
                } else if (addressField.length && addressField.val().trim().length > 0 && 
                          latitudeField.val() && longitudeField.val()) {
                    addressField.addClass('is-valid').removeClass('is-invalid');
                }
                
                // Ensure barangay field shows feedback now
                if (typeof window.validateBarangaySelection === 'function') {
                    window.validateBarangaySelection();
                }
            }
            
            // Special validation for device step
            if (step === 'device') {
                var deviceNumberField = $('#device_number');
                var serialNumberField = $('#serial_number');
                
                if (deviceNumberField.length && deviceNumberField.val().trim().length === 0) {
                    isValid = false;
                    errorMessages.push('Device number is required.');
                } else if (deviceNumberField.length && deviceNumberField.hasClass('is-invalid')) {
                    isValid = false;
                    errorMessages.push('Please enter a valid device number.');
                }
                
                if (serialNumberField.length && serialNumberField.val().trim().length === 0) {
                    isValid = false;
                    errorMessages.push('Serial number is required.');
                } else if (serialNumberField.length && serialNumberField.hasClass('is-invalid')) {
                    isValid = false;
                    errorMessages.push('Please enter a valid serial number.');
                }
                
                // Check if both fields are valid
                if (deviceNumberField.length && serialNumberField.length && 
                    deviceNumberField.hasClass('is-valid') && serialNumberField.hasClass('is-valid')) {
                    // Both fields are valid, allow submission
                } else if (deviceNumberField.length && serialNumberField.length && 
                          deviceNumberField.val().trim().length > 0 && serialNumberField.val().trim().length > 0) {
                    isValid = false;
                    errorMessages.push('Please ensure both device number and serial number are valid before proceeding.');
                }
            }
            
            if (!isValid && errorMessages.length > 0) {
                showFormValidationError(errorMessages);
                return false;
            }
            
            return true;
        }
        
        // Form submission with proper error handling
        $('form').on('submit', function(e) {
            var form = $(this);
            var submitButton = form.find('button[type="submit"]');
            var buttonName = submitButton.attr('name');
            
            // Check for validation errors before submitting
            var hasErrors = false;
            var errorMessages = [];
            
            form.find('.is-invalid').each(function() {
                hasErrors = true;
                var fieldName = $(this).attr('name');
                var errorText = $(this).next('.invalid-feedback').text();
                if (errorText) {
                    errorMessages.push(errorText);
                }
            });
            
            // Special check for address field - only on location step
            if (buttonName === 'location_submit') {
                var addressField = $('#address');
                var barangayField = $('#barangay');
                var buildingTypeField = $('#building_type');
                var buildingNameField = $('#building_name');
                var latitudeField = $('#latitude');
                var longitudeField = $('#longitude');
                
                // Check address field
                if (addressField.length && addressField.val().trim().length === 0) {
                    hasErrors = true;
                    errorMessages.push('Address is required. Please select a location on the map or enter your address.');
                    addressField.addClass('is-invalid').removeClass('is-valid');
                } else if (addressField.length && addressField.val().trim().length > 0) {
                    addressField.addClass('is-valid').removeClass('is-invalid');
                }
                
                // Check barangay field
                if (barangayField.length && barangayField.val().trim().length === 0) {
                    hasErrors = true;
                    errorMessages.push('Barangay is required.');
                    barangayField.addClass('is-invalid').removeClass('is-valid');
                } else if (barangayField.length && barangayField.val().trim().length > 0) {
                    barangayField.addClass('is-valid').removeClass('is-invalid');
                }
                
                // Check building type field
                if (buildingTypeField.length && buildingTypeField.val().trim().length === 0) {
                    hasErrors = true;
                    errorMessages.push('Building type is required. Please select a building type.');
                    buildingTypeField.addClass('is-invalid').removeClass('is-valid');
                } else if (buildingTypeField.length && buildingTypeField.val().trim().length > 0) {
                    buildingTypeField.addClass('is-valid').removeClass('is-invalid');
                }
                
                // Check building name field
                if (buildingNameField.length && buildingNameField.val().trim().length === 0) {
                    hasErrors = true;
                    errorMessages.push('Building name is required.');
                    buildingNameField.addClass('is-invalid').removeClass('is-valid');
                } else if (buildingNameField.length && buildingNameField.val().trim().length > 0) {
                    buildingNameField.addClass('is-valid').removeClass('is-valid');
                }
                
                // Check if address has been validated (coordinates are set)
                if (addressField.length && addressField.val().trim().length > 0 && 
                    (!latitudeField.val() || !longitudeField.val())) {
                    hasErrors = true;
                    errorMessages.push('Please wait for address validation to complete or select a location on the map.');
                    addressField.addClass('is-invalid').removeClass('is-valid');
                } else if (addressField.length && addressField.val().trim().length > 0 && 
                          latitudeField.val() && longitudeField.val()) {
                    addressField.addClass('is-valid').removeClass('is-invalid');
                }
                
                // Validate barangay matches address
                if (addressField.length && barangayField.length && 
                    addressField.val().trim().length > 0 && barangayField.val().trim().length > 0) {
                    var addr = (addressField.val() || '').toLowerCase().replace(/[\s,]+/g, ' ');
                    var brgy = (barangayField.val() || '').toLowerCase().trim();
                    var re = new RegExp('(^|\\s)' + brgy.replace(/[.*+?^${}()|[\\]\\\\]/g, '\\$&') + '(?=\\s|$)');
                    if (!re.test(addr)) {
                        hasErrors = true;
                        errorMessages.push('Barangay must match your full address.');
                        barangayField.addClass('is-invalid').removeClass('is-valid');
                    } else {
                        barangayField.addClass('is-valid').removeClass('is-invalid');
                    }
                }
            }
            
            if (hasErrors) {
                e.preventDefault();
                if (errorMessages.length > 0) {
                    showFormValidationError(errorMessages);
                } else {
                    showErrorAlert('Please fix the validation errors before proceeding.');
                }
                return false;
            }
            
            // Show appropriate loading message based on the step
            var loadingMessage = 'Processing...';
            if (buttonName === 'personal_info_submit') {
                loadingMessage = 'Processing Personal Information...';
            } else if (buttonName === 'location_submit') {
                loadingMessage = 'Processing Location Information...';
            } else if (buttonName === 'device_submit') {
                loadingMessage = 'Processing Device Registration...';
            } else if (buttonName === 'credentials_submit') {
                loadingMessage = 'Processing Registration...';
            }
            
            // Show loading alert
            var loadingAlert = showLoadingAlert(loadingMessage);
            
            // Set a timeout to handle cases where the form submission takes too long
            var timeoutId = setTimeout(function() {
                loadingAlert.close();
                showTimeoutError('Request timed out. Please try again.');
            }, 30000); // 30 seconds timeout
            
            // For regular form submissions (non-AJAX), the page will reload
            // The loading alert will be closed when the page reloads
            // If there's an error, the page will reload with error parameters
        });
    });
    </script>
</body>
</html>