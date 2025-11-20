<?php
// Include the centralized database connection
require_once('../../db/db.php');

// Profile-specific constants
define('BASE_URL', 'https://fireguard.bccbsis.com/'); // Adjust to your base URL
define('PROFILE_IMG_DIR', '../uploads/profile_images/');
define('MAX_PROFILE_IMG_SIZE', 2 * 1024 * 1024); // 2MB
define('ALLOWED_IMG_TYPES', ['jpg', 'jpeg', 'png', 'gif']);

// Start session
session_start();

// Create upload directory if it doesn't exist
if (!file_exists(PROFILE_IMG_DIR)) {
    mkdir(PROFILE_IMG_DIR, 0755, true);
}

// Use the centralized database connection function
function getDBConnection() {
    return getDatabaseConnection();
}

// Authentication check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../../index.php");
    exit;
}

// Get user data
$user = [];
$errors = [];
$success = false;

try {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        session_destroy();
        header("Location: ../../../index.php");
        exit;
    }
} catch(PDOException $e) {
    error_log("Failed to load user data: " . $e->getMessage());
    $errors['general'] = "Failed to load user data";
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Determine which form was submitted
    $formType = $_POST['form_type'] ?? '';
    
    switch ($formType) {
        case 'profile_update':
            handleProfileUpdate($conn, $user);
            break;
        case 'password_change':
            handlePasswordChange($conn, $user);
            break;
        case 'profile_picture':
            handleProfilePictureUpload($conn, $user);
            break;
        default:
            $errors['general'] = "Invalid form submission";
    }
    
    // Refresh user data after updates
    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

function handleProfileUpdate($conn, $currentUser) {
    global $errors;
    
    $updates = [];
    $validationRules = [
        'fullname' => [
            'filter' => FILTER_SANITIZE_STRING,
            'required' => true,
            'min_length' => 3,
            'max_length' => 255
        ],
        'birthdate' => [
            'filter' => FILTER_SANITIZE_STRING,
            'required' => true,
            'valid_date' => true
        ],
        'address' => [
            'filter' => FILTER_SANITIZE_STRING,
            'required' => true,
            'min_length' => 5,
            'max_length' => 500
        ],
        'contact_number' => [
            'filter' => FILTER_SANITIZE_STRING,
            'required' => true,
            'pattern' => '/^[0-9]{10,15}$/',
            'error_msg' => 'Contact number must be 10-15 digits'
        ],
        'email_address' => [
            'filter' => FILTER_SANITIZE_EMAIL,
            'required' => true,
            'validate_email' => true,
            'unique' => true,
            'current_value' => $currentUser['email_address']
        ]
    ];
    
    foreach ($validationRules as $field => $rules) {
        $value = filter_input(INPUT_POST, $field, $rules['filter']);
        
        if ($rules['required'] && empty($value)) {
            $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . " is required";
            continue;
        }
        
        if (isset($rules['min_length']) && strlen($value) < $rules['min_length']) {
            $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . " must be at least {$rules['min_length']} characters";
            continue;
        }
        
        if (isset($rules['max_length']) && strlen($value) > $rules['max_length']) {
            $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . " must not exceed {$rules['max_length']} characters";
            continue;
        }
        
        if ($field === 'birthdate' && $rules['valid_date']) {
            $date = DateTime::createFromFormat('Y-m-d', $value);
            if (!$date || $date->format('Y-m-d') !== $value) {
                $errors[$field] = "Invalid date format";
                continue;
            }
            
            // Calculate age
            $today = new DateTime();
            $age = $today->diff($date)->y;
            $updates['age'] = $age;
        }
        
        if ($field === 'contact_number' && isset($rules['pattern'])) {
            if (!preg_match($rules['pattern'], $value)) {
                $errors[$field] = $rules['error_msg'] ?? "Invalid format";
                continue;
            }
        }
        
        if ($field === 'email_address' && $rules['validate_email']) {
            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[$field] = "Invalid email address";
                continue;
            }
            
            // Check if email is unique (if changed)
            if ($rules['unique'] && $value !== $rules['current_value']) {
                try {
                    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email_address = ?");
                    $stmt->execute([$value]);
                    if ($stmt->fetch()) {
                        $errors[$field] = "Email address is already in use";
                        continue;
                    }
                } catch(PDOException $e) {
                    error_log("Email uniqueness check failed: " . $e->getMessage());
                    $errors['general'] = "Validation error occurred";
                    continue;
                }
            }
        }
        
        $updates[$field] = $value;
    }
    
    // Update if no errors
    if (empty($errors)) {
        try {
            $setParts = [];
            $params = [];
            foreach ($updates as $field => $value) {
                $setParts[] = "$field = ?";
                $params[] = $value;
            }
            $params[] = $_SESSION['user_id'];
            
            $sql = "UPDATE users SET " . implode(', ', $setParts) . " WHERE user_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            
            $_SESSION['swal'] = [
                'title' => 'Success!',
                'text' => 'Profile updated successfully!',
                'icon' => 'success',
                'confirmButtonText' => 'OK'
            ];
            
            header("Location: ".$_SERVER['PHP_SELF']);
            exit;
            
        } catch(PDOException $e) {
            error_log("Profile update failed: " . $e->getMessage());
            $_SESSION['swal'] = [
                'title' => 'Error!',
                'text' => 'Failed to update profile: '.$e->getMessage(),
                'icon' => 'error',
                'confirmButtonText' => 'OK'
            ];
        }
    } else {
        $_SESSION['swal'] = [
            'title' => 'Validation Error',
            'text' => 'Please correct the errors in the form',
            'icon' => 'warning',
            'confirmButtonText' => 'OK'
        ];
    }
}

function handlePasswordChange($conn, $currentUser) {
    global $errors;
    
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validate current password
    if (!password_verify($current_password, $currentUser['password'])) {
        $errors['current_password'] = "Current password is incorrect";
    }
    
    // Validate new password
    if (empty($new_password)) {
        $errors['new_password'] = "New password is required";
    } elseif (strlen($new_password) < 8) {
        $errors['new_password'] = "Password must be at least 8 characters";
    } elseif (!preg_match('/[A-Z]/', $new_password)) {
        $errors['new_password'] = "Password must contain at least one uppercase letter";
    } elseif (!preg_match('/[a-z]/', $new_password)) {
        $errors['new_password'] = "Password must contain at least one lowercase letter";
    } elseif (!preg_match('/[0-9]/', $new_password)) {
        $errors['new_password'] = "Password must contain at least one number";
    } elseif (!preg_match('/[\W]/', $new_password)) {
        $errors['new_password'] = "Password must contain at least one special character";
    }
    
    // Confirm password match
    if ($new_password !== $confirm_password) {
        $errors['confirm_password'] = "Passwords do not match";
    }
    
    if (empty($errors)) {
        try {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            $stmt->execute([$hashed_password, $_SESSION['user_id']]);
            
            $_SESSION['swal'] = [
                'title' => 'Success!',
                'text' => 'Password changed successfully!',
                'icon' => 'success',
                'confirmButtonText' => 'OK'
            ];
            
            header("Location: ".$_SERVER['PHP_SELF']);
            exit;
            
        } catch(PDOException $e) {
            error_log("Password change failed: " . $e->getMessage());
            $_SESSION['swal'] = [
                'title' => 'Error!',
                'text' => 'Failed to change password: '.$e->getMessage(),
                'icon' => 'error',
                'confirmButtonText' => 'OK'
            ];
        }
    } else {
        $_SESSION['swal'] = [
            'title' => 'Validation Error',
            'text' => 'Please correct the errors in the form',
            'icon' => 'warning',
            'confirmButtonText' => 'OK'
        ];
    }
}

function handleProfilePictureUpload($conn, $currentUser) {
    global $errors;
    
    if (!isset($_FILES['profile_image']) || $_FILES['profile_image']['error'] === UPLOAD_ERR_NO_FILE) {
        $errors['profile_image'] = "No file uploaded";
        return;
    }
    
    $file = $_FILES['profile_image'];
    
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors['profile_image'] = "File upload error: " . $file['error'];
        return;
    }
    
    // Check file size
    if ($file['size'] > MAX_PROFILE_IMG_SIZE) {
        $errors['profile_image'] = "File size exceeds maximum limit of " . (MAX_PROFILE_IMG_SIZE / 1024 / 1024) . "MB";
        return;
    }
    
    // Get file info
    $fileInfo = pathinfo($file['name']);
    $extension = strtolower($fileInfo['extension']);
    
    // Check file type
    if (!in_array($extension, ALLOWED_IMG_TYPES)) {
        $errors['profile_image'] = "Only JPG, JPEG, PNG, and GIF files are allowed";
        return;
    }
    
    // Generate unique filename
    $newFilename = uniqid('profile_', true) . '.' . $extension;
    $destination = PROFILE_IMG_DIR . $newFilename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        $errors['profile_image'] = "Failed to save uploaded file";
        return;
    }
    
    // Delete old profile image if it's not the default
    if ($currentUser['profile_image'] !== 'images/profile1.jpg' && file_exists(PROFILE_IMG_DIR . $currentUser['profile_image'])) {
        unlink(PROFILE_IMG_DIR . $currentUser['profile_image']);
    }
    
    // Update database
    try {
        $stmt = $conn->prepare("UPDATE users SET profile_image = ? WHERE user_id = ?");
        $stmt->execute([$newFilename, $_SESSION['user_id']]);
        
        $_SESSION['swal'] = [
            'title' => 'Success!',
            'text' => 'Profile picture updated successfully!',
            'icon' => 'success',
            'confirmButtonText' => 'OK'
        ];
        
        header("Location: ".$_SERVER['PHP_SELF']);
        exit;
        
    } catch(PDOException $e) {
        error_log("Profile picture update failed: " . $e->getMessage());
        // Delete the uploaded file since DB update failed
        unlink($destination);
        
        $_SESSION['swal'] = [
            'title' => 'Error!',
            'text' => 'Failed to update profile picture: '.$e->getMessage(),
            'icon' => 'error',
            'confirmButtonText' => 'OK'
        ];
    }
}

function getProfileImageUrl($profileImage) {
    // Default avatar
    $defaultAvatar = 'https://ui-avatars.com/api/?name=Unknown&background=random&size=150';

    // If empty or null, return default
    if (empty($profileImage)) {
        return $defaultAvatar;
    }

    // If it's a full URL already
    if (filter_var($profileImage, FILTER_VALIDATE_URL)) {
        return $profileImage;
    }

    // Construct local path (file system) and relative URL
    $localPath = __DIR__ . '/' . PROFILE_IMG_DIR . $profileImage;
    $relativeUrl = PROFILE_IMG_DIR . $profileImage;

    // Check if file exists on disk
    if (file_exists($localPath)) {
        return $relativeUrl;
    }

    // Fallback if file not found
    return $defaultAvatar;
}
?>