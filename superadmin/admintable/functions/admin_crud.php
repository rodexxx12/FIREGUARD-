<?php
require_once 'db_connect.php';
require_once 'ajax_helpers.php';

// Function to send JSON response
function send_json_response($success, $message, $data = null) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

// Function to validate admin data
function validate_admin_data($data, $is_update = false) {
    $errors = [];
    
    // Required fields
    $required_fields = ['full_name', 'email', 'username', 'contact_number', 'role', 'status'];
    foreach ($required_fields as $field) {
        if (empty($data[$field])) {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . " is required.";
        }
    }
    
    // Email validation
    if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }
    
    // Contact number validation (11 digits starting with 09 only)
    if (!empty($data['contact_number'])) {
        if (!preg_match('/^09\d{9}$/', $data['contact_number'])) {
            $errors[] = "Contact number must be 11 digits starting with 09.";
        }
    }
    
    // Password validation for new admin
    if (!$is_update) {
        if (empty($data['password'])) {
            $errors[] = "Password is required for new admin.";
        } elseif (strlen($data['password']) < 8) {
            $errors[] = "Password must be at least 8 characters long.";
        }
        
        // Password confirmation validation
        if (empty($data['confirm_password'])) {
            $errors[] = "Password confirmation is required.";
        } elseif ($data['password'] !== $data['confirm_password']) {
            $errors[] = "Password confirmation does not match.";
        }
    }
    
    // Role validation
    $valid_roles = ['superadmin', 'fire_officer', 'system_admin'];
    if (!empty($data['role']) && !in_array($data['role'], $valid_roles)) {
        $errors[] = "Invalid role selected.";
    }
    
    // Status validation
    $valid_statuses = ['Active', 'Inactive'];
    if (!empty($data['status']) && !in_array($data['status'], $valid_statuses)) {
        $errors[] = "Invalid status selected.";
    }
    
    return $errors;
}

// INSERT ADMIN FUNCTION
function insert_admin($data) {
    global $pdo;
    
    try {
        // Set default role if not provided
        if (empty($data['role'])) {
            $data['role'] = 'fire_officer';
        }
        
        // Validate data
        $errors = validate_admin_data($data, false);
        if (!empty($errors)) {
            return [
                'success' => false,
                'message' => 'Validation failed: ' . implode(' ', $errors)
            ];
        }
        
        // Check for duplicate username, email, or contact number
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM admin WHERE username = ? OR email = ? OR contact_number = ?");
        $stmt->execute([$data['username'], $data['email'], $data['contact_number']]);
        if ($stmt->fetchColumn() > 0) {
            return [
                'success' => false,
                'message' => 'Username, email, or contact number already exists.'
            ];
        }
        

        
        // Hash password
        $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);
        
        // Insert admin
        $stmt = $pdo->prepare("
            INSERT INTO admin (username, password, full_name, email, contact_number, role, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['username'],
            $hashed_password,
            $data['full_name'],
            $data['email'],
            $data['contact_number'],
            $data['role'],
            $data['status']
        ]);
        
        $admin_id = $pdo->lastInsertId();
        
        return [
            'success' => true,
            'message' => 'Admin created successfully!',
            'data' => ['admin_id' => $admin_id]
        ];
        
    } catch (PDOException $e) {
        return [
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ];
    }
}

// UPDATE ADMIN FUNCTION
function update_admin($data) {
    global $pdo;
    
    try {
        // Set default role if not provided
        if (empty($data['role'])) {
            $data['role'] = 'fire_officer';
        }
        
        // Validate data
        $errors = validate_admin_data($data, true);
        if (!empty($errors)) {
            return [
                'success' => false,
                'message' => 'Validation failed: ' . implode(' ', $errors)
            ];
        }
        
        // Check if admin exists
        $stmt = $pdo->prepare("SELECT admin_id FROM admin WHERE admin_id = ?");
        $stmt->execute([$data['admin_id']]);
        if (!$stmt->fetch()) {
            return [
                'success' => false,
                'message' => 'Admin not found.'
            ];
        }
        
        // Check for duplicate username, email, or contact number (excluding current admin)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM admin WHERE (username = ? OR email = ? OR contact_number = ?) AND admin_id != ?");
        $stmt->execute([$data['username'], $data['email'], $data['contact_number'], $data['admin_id']]);
        if ($stmt->fetchColumn() > 0) {
            return [
                'success' => false,
                'message' => 'Username, email, or contact number already exists.'
            ];
        }
        
        // Build update query
        $update_fields = [
            'username = ?',
            'full_name = ?',
            'email = ?',
            'contact_number = ?',
            'role = ?',
            'status = ?'
        ];
        
        $params = [
            $data['username'],
            $data['full_name'],
            $data['email'],
            $data['contact_number'],
            $data['role'],
            $data['status']
        ];
        
        $params[] = $data['admin_id']; // For WHERE clause
        
        $sql = "UPDATE admin SET " . implode(', ', $update_fields) . " WHERE admin_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        return [
            'success' => true,
            'message' => 'Admin updated successfully!'
        ];
        
    } catch (PDOException $e) {
        return [
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ];
    }
}



// GET ADMIN BY ID FUNCTION
function get_admin_by_id($admin_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT admin_id, username, full_name, email, contact_number, role, status, created_at, updated_at FROM admin WHERE admin_id = ?");
        $stmt->execute([$admin_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return false;
    }
}

// GET ALL ADMINS FUNCTION
function get_all_admins() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("SELECT admin_id, username, full_name, email, contact_number, role, status, created_at, updated_at FROM admin ORDER BY created_at DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

// Handle AJAX requests
if (is_ajax() && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'insert':
            $result = insert_admin($_POST);
            send_json_response($result['success'], $result['message'], $result['data'] ?? null);
            break;
            
        case 'update':
            $result = update_admin($_POST);
            send_json_response($result['success'], $result['message']);
            break;
            

            
        case 'get':
            $admin_id = $_POST['admin_id'] ?? 0;
            $admin = get_admin_by_id($admin_id);
            if ($admin) {
                send_json_response(true, 'Admin data retrieved successfully', $admin);
            } else {
                send_json_response(false, 'Admin not found');
            }
            break;
            
        default:
            send_json_response(false, 'Invalid action');
            break;
    }
}
?> 