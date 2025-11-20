<?php
require_once 'db_config.php';
header('Content-Type: application/json');

$response = ['valid' => false, 'message' => 'Invalid request'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['type'] ?? '';
    switch ($type) {
        case 'email':
            $email = trim($_POST['email'] ?? '');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $response = ['valid' => false, 'message' => 'Invalid email format'];
            } else {
                $conn = getDatabaseConnection();
                $stmt = $conn->prepare('SELECT user_id FROM users WHERE email_address = ?');
                $stmt->execute([$email]);
                if ($stmt->rowCount() > 0) {
                    $response = ['valid' => false, 'message' => 'Email already registered'];
                } else {
                    $response = ['valid' => true, 'message' => 'Email is available'];
                }
            }
            break;
        case 'username':
            $username = trim($_POST['username'] ?? '');
            if (strlen($username) < 5) {
                $response = ['valid' => false, 'message' => 'Username must be at least 5 characters'];
            } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
                $response = ['valid' => false, 'message' => 'Username can only contain letters, numbers, and underscores'];
            } else {
                $conn = getDatabaseConnection();
                $stmt = $conn->prepare('SELECT user_id FROM users WHERE username = ?');
                $stmt->execute([$username]);
                if ($stmt->rowCount() > 0) {
                    $response = ['valid' => false, 'message' => 'Username already taken'];
                } else {
                    $response = ['valid' => true, 'message' => 'Username is available'];
                }
            }
            break;
        case 'fullname':
            $fullname = trim($_POST['fullname'] ?? '');
            if (empty($fullname)) {
                $response = ['valid' => false, 'message' => 'Full name is required'];
            } elseif (strlen($fullname) < 2) {
                $response = ['valid' => false, 'message' => 'Full name must be at least 2 characters'];
            } elseif (!preg_match('/^[a-zA-Z\s\.\'-]+$/', $fullname)) {
                $response = ['valid' => false, 'message' => 'Full name can only contain letters, spaces, dots, apostrophes, and hyphens'];
            } elseif (strlen($fullname) > 100) {
                $response = ['valid' => false, 'message' => 'Full name is too long (max 100 characters)'];
            } else {
                $response = ['valid' => true, 'message' => 'Full name is valid'];
            }
            break;
        case 'contact':
            $contact = trim($_POST['contact'] ?? '');
            if (empty($contact)) {
                $response = ['valid' => false, 'message' => 'Contact number is required'];
            } elseif (!preg_match('/^09\d{9}$/', $contact)) {
                $response = ['valid' => false, 'message' => 'Contact number must start with 09 and be exactly 11 digits (e.g., 09123456789)'];
            } else {
                // Check if contact number already exists in users table
                $conn = getDatabaseConnection();
                $stmt = $conn->prepare('SELECT user_id FROM users WHERE contact_number = ?');
                $stmt->execute([$contact]);
                if ($stmt->rowCount() > 0) {
                    $response = ['valid' => false, 'message' => 'Contact number already registered'];
                } else {
                    $response = ['valid' => true, 'message' => 'Contact number is available'];
                }
            }
            break;
        case 'birthdate':
            $birthdate = trim($_POST['birthdate'] ?? '');
            if (empty($birthdate)) {
                $response = ['valid' => false, 'message' => 'Birthdate is required'];
            } else {
                $birth = new DateTime($birthdate);
                $today = new DateTime();
                $age = $today->diff($birth)->y;
                
                if ($age < 18) {
                    $response = ['valid' => false, 'message' => 'You must be at least 18 years old'];
                } elseif ($age > 120) {
                    $response = ['valid' => false, 'message' => 'Please enter a valid birthdate'];
                } else {
                    $response = ['valid' => true, 'message' => 'Age: ' . $age . ' years old'];
                }
            }
            break;
        case 'password_strength':
            $password = $_POST['password'] ?? '';
            $strength = 0;
            $feedback = [];
            
            if (strlen($password) >= 8) {
                $strength += 1;
                $feedback[] = '✓ At least 8 characters';
            } else {
                $feedback[] = '✗ At least 8 characters needed';
            }
            
            if (preg_match('/[a-z]/', $password)) {
                $strength += 1;
                $feedback[] = '✓ Contains lowercase letter';
            } else {
                $feedback[] = '✗ Needs lowercase letter';
            }
            
            if (preg_match('/[A-Z]/', $password)) {
                $strength += 1;
                $feedback[] = '✓ Contains uppercase letter';
            } else {
                $feedback[] = '✗ Needs uppercase letter';
            }
            
            if (preg_match('/[0-9]/', $password)) {
                $strength += 1;
                $feedback[] = '✓ Contains number';
            } else {
                $feedback[] = '✗ Needs number';
            }
            
            if (preg_match('/[^A-Za-z0-9]/', $password)) {
                $strength += 1;
                $feedback[] = '✓ Contains special character';
            } else {
                $feedback[] = '✗ Needs special character';
            }
            
            $strengthLabels = ['Very Weak', 'Weak', 'Fair', 'Good', 'Strong', 'Very Strong'];
            $strengthColors = ['#dc3545', '#fd7e14', '#ffc107', '#28a745', '#20c997', '#198754'];
            
            $response = [
                'valid' => $strength >= 4,
                'strength' => $strength,
                'label' => $strengthLabels[$strength],
                'color' => $strengthColors[$strength],
                'feedback' => $feedback,
                'message' => 'Password strength: ' . $strengthLabels[$strength]
            ];
            break;
        case 'password_match':
            $password = $_POST['password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            if (empty($confirm_password)) {
                $response = ['valid' => false, 'message' => 'Please confirm your password'];
            } elseif ($password !== $confirm_password) {
                $response = ['valid' => false, 'message' => 'Passwords do not match'];
            } else {
                $response = ['valid' => true, 'message' => 'Passwords match'];
            }
            break;
        case 'address':
            $address = trim($_POST['address'] ?? '');
            if (empty($address)) {
                $response = ['valid' => false, 'message' => 'Address is required'];
            } elseif (strlen($address) < 10) {
                $response = ['valid' => false, 'message' => 'Address is too short (minimum 10 characters)'];
            } elseif (strlen($address) > 255) {
                $response = ['valid' => false, 'message' => 'Address is too long (maximum 255 characters)'];
            } else {
                // Enhanced validation for Philippine addresses
                $address_lower = strtolower($address);
                
                // Check if address contains key Philippine location indicators
                $philippine_indicators = [
                    'philippines', 'ph', 'negros occidental', 'negros', 'bago', 'bacolod', 'iloilo', 'cebu',
                    'manila', 'quezon', 'caloocan', 'davao', 'zamboanga', 'antipolo', 'pasig', 'valenzuela',
                    'taguig', 'paranaque', 'makati', 'mandaluyong', 'marikina', 'pasay', 'malabon', 'navotas',
                    'san juan', 'muntinlupa', 'las pinas', 'pateros', 'taguig', 'quezon city', 'manila city'
                ];
                
                $has_philippine_indicator = false;
                foreach ($philippine_indicators as $indicator) {
                    if (strpos($address_lower, $indicator) !== false) {
                        $has_philippine_indicator = true;
                        break;
                    }
                }
                
                // Check for postal code pattern (4-digit Philippine postal codes)
                $has_postal_code = preg_match('/\b\d{4}\b/', $address);
                
                // Check for common address components
                $has_street_components = preg_match('/\b(street|st\.|avenue|ave\.|road|rd\.|highway|hwy\.|boulevard|blvd\.|drive|dr\.|lane|ln\.|place|pl\.|court|ct\.|village|subdivision|subd\.|barangay|brgy\.|purok|sitio)\b/i', $address);
                
                // For Bago City specifically, be more lenient
                if (strpos($address_lower, 'bago') !== false) {
                    $response = ['valid' => true, 'message' => 'Valid Bago City address'];
                } elseif ($has_philippine_indicator && ($has_postal_code || $has_street_components)) {
                    $response = ['valid' => true, 'message' => 'Valid Philippine address'];
                } elseif ($has_philippine_indicator) {
                    $response = ['valid' => true, 'message' => 'Address appears to be in the Philippines'];
                } else {
                    // If no clear Philippine indicators, still accept if it's a reasonable length
                    $response = ['valid' => true, 'message' => 'Address format appears valid'];
                }
            }
            break;
        case 'building_name':
            $building_name = trim($_POST['building_name'] ?? '');
            if (empty($building_name)) {
                $response = ['valid' => false, 'message' => 'Building name is required'];
            } elseif (strlen($building_name) < 2) {
                $response = ['valid' => false, 'message' => 'Building name must be at least 2 characters'];
            } elseif (strlen($building_name) > 100) {
                $response = ['valid' => false, 'message' => 'Building name is too long (maximum 100 characters)'];
            } elseif (!preg_match('/^[a-zA-Z0-9\s\.\'-]+$/', $building_name)) {
                $response = ['valid' => false, 'message' => 'Building name contains invalid characters'];
            } else {
                $response = ['valid' => true, 'message' => 'Building name is valid'];
            }
            break;
        case 'building_type':
            $building_type = trim($_POST['building_type'] ?? '');
            if (empty($building_type)) {
                $response = ['valid' => false, 'message' => 'Building type is required'];
            } elseif (!in_array($building_type, ['Residential', 'Commercial', 'Institutional', 'Industrial'])) {
                $response = ['valid' => false, 'message' => 'Please select a valid building type'];
            } else {
                $response = ['valid' => true, 'message' => 'Building type is valid'];
            }
            break;
        case 'device_number':
            $device_number = trim($_POST['device_number'] ?? '');
            if (empty($device_number)) {
                $response = ['valid' => false, 'message' => 'Device number is required'];
            } else {
                $conn = getDatabaseConnection();
                // Check if device exists in admin_devices table and is approved
                $stmt = $conn->prepare("SELECT admin_device_id FROM admin_devices WHERE device_number = ? AND status = 'approved'");
                $stmt->execute([$device_number]);
                if ($stmt->rowCount() > 0) {
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    $admin_device_id = $row['admin_device_id'];
                    
                    // Check if device is already registered in devices table
                    $stmt = $conn->prepare("SELECT device_id FROM devices WHERE device_number = ?");
                    $stmt->execute([$device_number]);
                    if ($stmt->rowCount() > 0) {
                        $response = ['valid' => false, 'message' => 'Device is already registered in the system'];
                    } else {
                        $response = ['valid' => true, 'message' => 'Device number is valid', 'admin_device_id' => $admin_device_id];
                    }
                } else {
                    $response = ['valid' => false, 'message' => 'Device number not found in approved inventory'];
                }
            }
            break;
        case 'serial_number':
            $serial_number = trim($_POST['serial_number'] ?? '');
            $device_number = trim($_POST['device_number'] ?? '');
            $admin_device_id = trim($_POST['admin_device_id'] ?? '');
            
            if (empty($serial_number)) {
                $response = ['valid' => false, 'message' => 'Serial number is required'];
            } elseif (empty($device_number)) {
                $response = ['valid' => false, 'message' => 'Please enter device number first'];
            } elseif (empty($admin_device_id)) {
                $response = ['valid' => false, 'message' => 'Please enter a valid device number first'];
            } else {
                $conn = getDatabaseConnection();
                // Check if serial number matches the device number in admin_devices table
                $stmt = $conn->prepare("SELECT admin_device_id FROM admin_devices WHERE device_number = ? AND serial_number = ? AND status = 'approved'");
                $stmt->execute([$device_number, $serial_number]);
                if ($stmt->rowCount() > 0) {
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    $found_admin_device_id = $row['admin_device_id'];
                    
                    // Check if the admin_device_id matches
                    if ($found_admin_device_id == $admin_device_id) {
                        // Check if serial number is already registered in devices table
                        $stmt = $conn->prepare("SELECT device_id FROM devices WHERE serial_number = ?");
                        $stmt->execute([$serial_number]);
                        if ($stmt->rowCount() > 0) {
                            $response = ['valid' => false, 'message' => 'Serial number is already registered in the system'];
                        } else {
                            $response = ['valid' => true, 'message' => 'Serial number matches device number'];
                        }
                    } else {
                        $response = ['valid' => false, 'message' => 'Serial number does not match the device number'];
                    }
                } else {
                    $response = ['valid' => false, 'message' => 'Serial number does not match the device number'];
                }
            }
            break;
        case 'device':
            $device_number = trim($_POST['device_number'] ?? '');
            $serial_number = trim($_POST['serial_number'] ?? '');
            $value = trim($_POST['value'] ?? '');
            
            // If value is provided, it could be either device_number or serial_number
            if (!empty($value)) {
                $conn = getDatabaseConnection();
                // Check if the value exists as a device_number in admin_devices table
                $stmt = $conn->prepare("SELECT admin_device_id FROM admin_devices WHERE device_number = ? AND status = 'approved'");
                $stmt->execute([$value]);
                if ($stmt->rowCount() > 0) {
                    // Check if device is already registered in devices table
                    $stmt = $conn->prepare("SELECT device_id FROM devices WHERE device_number = ?");
                    $stmt->execute([$value]);
                    if ($stmt->rowCount() > 0) {
                        $response = ['valid' => false, 'message' => 'Device is already registered in the system.'];
                    } else {
                        $response = ['valid' => true, 'message' => 'Device number is available and valid'];
                    }
                } else {
                    // Check if the value exists as a serial_number in admin_devices table
                    $stmt = $conn->prepare("SELECT admin_device_id FROM admin_devices WHERE serial_number = ? AND status = 'approved'");
                    $stmt->execute([$value]);
                    if ($stmt->rowCount() > 0) {
                        // Check if serial is already registered in devices table
                        $stmt = $conn->prepare("SELECT device_id FROM devices WHERE serial_number = ?");
                        $stmt->execute([$value]);
                        if ($stmt->rowCount() > 0) {
                            $response = ['valid' => false, 'message' => 'Device is already registered in the system.'];
                        } else {
                            $response = ['valid' => true, 'message' => 'Serial number is available and valid'];
                        }
                    } else {
                        $response = ['valid' => false, 'message' => 'Device is not approved or does not exist in admin inventory.'];
                    }
                }
            } elseif (!empty($device_number) && !empty($serial_number)) {
                $conn = getDatabaseConnection();
                // Check if device exists in admin_devices table and is approved
                $stmt = $conn->prepare("SELECT admin_device_id FROM admin_devices WHERE device_number = ? AND serial_number = ? AND status = 'approved'");
                $stmt->execute([$device_number, $serial_number]);
                if ($stmt->rowCount() == 0) {
                    $response = ['valid' => false, 'message' => 'Device is not approved or does not exist in admin inventory.'];
                    break;
                }
                
                // Check if device is already registered in devices table
                $stmt = $conn->prepare("SELECT device_id FROM devices WHERE device_number = ? OR serial_number = ?");
                $stmt->execute([$device_number, $serial_number]);
                if ($stmt->rowCount() > 0) {
                    $response = ['valid' => false, 'message' => 'Device is already registered in the system.'];
                    break;
                }
                
                $response = ['valid' => true, 'message' => 'Device number and serial number are available and valid'];
            } else {
                $response = ['valid' => false, 'message' => 'Device number or serial number is required'];
            }
            break;
        default:
            $response = ['valid' => false, 'message' => 'Unknown validation type'];
    }
}
echo json_encode($response); 