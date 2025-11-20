<?php
header('Content-Type: application/json');
session_start();

require_once '../../db/db.php';

// Check if user is logged in (add your authentication logic here)
// if (!isset($_SESSION['admin_id'])) {
//     echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
//     exit();
// }

try {
    $conn = getDatabaseConnection();
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'get_admins':
            getAdmins($conn);
            break;
        case 'get_admin':
            getAdmin($conn);
            break;
        case 'add_admin':
            addAdmin($conn);
            break;
        case 'update_admin':
            updateAdmin($conn);
            break;
        case 'toggle_status':
            toggleAdminStatus($conn);
            break;
        case 'get_counts':
            getCounts($conn);
            break;
        case 'check_duplicate':
            checkDuplicate($conn);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    error_log("Admin API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'System error occurred']);
}

function getAdmins($conn) {
    $draw = intval($_POST['draw']);
    $start = intval($_POST['start']);
    $length = intval($_POST['length']);
    $searchValue = $_POST['search']['value'] ?? '';
    $orderColumn = $_POST['order'][0]['column'] ?? 0;
    $orderDir = $_POST['order'][0]['dir'] ?? 'desc';
    
    // Custom filters
    $statusFilter = $_POST['status_filter'] ?? '';
    $nameSearch = $_POST['name_search'] ?? '';
    $emailSearch = $_POST['email_search'] ?? '';
    
    // Column mapping
    $columns = ['admin_id', 'profile_image', 'username', 'full_name', 'email', 'contact_number', 'status', 'created_at', 'updated_at'];
    $orderBy = $columns[$orderColumn] ?? 'admin_id';
    
    // Build WHERE clause
    $whereConditions = [];
    $params = [];
    
    if (!empty($searchValue)) {
        $whereConditions[] = "(username LIKE ? OR full_name LIKE ? OR email LIKE ? OR contact_number LIKE ?)";
        $searchParam = "%$searchValue%";
        $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
    }
    
    if (!empty($statusFilter)) {
        $whereConditions[] = "status = ?";
        $params[] = $statusFilter;
    }
    
    if (!empty($nameSearch)) {
        $whereConditions[] = "full_name LIKE ?";
        $params[] = "%$nameSearch%";
    }
    
    if (!empty($emailSearch)) {
        $whereConditions[] = "email LIKE ?";
        $params[] = "%$emailSearch%";
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    // Get total records
    $totalQuery = "SELECT COUNT(*) as total FROM admin";
    $totalStmt = $conn->prepare($totalQuery);
    $totalStmt->execute();
    $totalRecords = $totalStmt->fetch()['total'];
    
    // Get filtered records
    $filteredQuery = "SELECT COUNT(*) as total FROM admin $whereClause";
    $filteredStmt = $conn->prepare($filteredQuery);
    $filteredStmt->execute($params);
    $filteredRecords = $filteredStmt->fetch()['total'];
    
    // Get data
    $dataQuery = "SELECT * FROM admin $whereClause ORDER BY $orderBy $orderDir LIMIT $start, $length";
    $dataStmt = $conn->prepare($dataQuery);
    $dataStmt->execute($params);
    $data = $dataStmt->fetchAll();
    
    echo json_encode([
        'draw' => $draw,
        'recordsTotal' => $totalRecords,
        'recordsFiltered' => $filteredRecords,
        'data' => $data
    ]);
}

function getAdmin($conn) {
    $adminId = $_POST['admin_id'] ?? '';
    
    if (empty($adminId)) {
        echo json_encode(['success' => false, 'message' => 'Admin ID is required']);
        return;
    }
    
    $stmt = $conn->prepare("SELECT * FROM admin WHERE admin_id = ?");
    $stmt->execute([$adminId]);
    $admin = $stmt->fetch();
    
    if ($admin) {
        // Remove password from response
        unset($admin['password']);
        echo json_encode(['success' => true, 'data' => $admin]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Admin not found']);
    }
}

function addAdmin($conn) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $contactNumber = trim($_POST['contact_number'] ?? '');
    $status = $_POST['status'] ?? 'Active';
    
    // Validation
    $errors = [];
    
    if (empty($username)) $errors[] = 'Username is required';
    if (empty($password)) $errors[] = 'Password is required';
    if (empty($fullName)) $errors[] = 'Full name is required';
    if (empty($email)) $errors[] = 'Email is required';
    if (empty($contactNumber)) $errors[] = 'Contact number is required';
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    }
    
    // Philippine mobile number validation - must start with 09 and be exactly 11 digits
    $contactDigits = preg_replace('/\D/', '', $contactNumber);
    if (strlen($contactDigits) !== 11 || !str_starts_with($contactDigits, '09')) {
        $errors[] = 'Contact number must be a Philippine mobile number starting with 09 and exactly 11 digits';
    }
    
    if (!empty($errors)) {
        echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
        return;
    }
    
    // Check for duplicates
    $checkStmt = $conn->prepare("SELECT COUNT(*) FROM admin WHERE username = ? OR email = ? OR contact_number = ?");
    $checkStmt->execute([$username, $email, $contactNumber]);
    $exists = $checkStmt->fetchColumn();
    
    if ($exists > 0) {
        echo json_encode(['success' => false, 'message' => 'Username, email, or contact number already exists']);
        return;
    }
    
    // Hash the password securely
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Handle file upload
    $profileImage = null;
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../../uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $fileExtension = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
        $fileName = 'admin_' . time() . '_' . uniqid() . '.' . $fileExtension;
        $filePath = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $filePath)) {
            $profileImage = $fileName;
        }
    }
    
    // Insert admin
    $stmt = $conn->prepare("
        INSERT INTO admin (username, password, full_name, email, contact_number, status, profile_image) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    if ($stmt->execute([$username, $hashedPassword, $fullName, $email, $contactNumber, $status, $profileImage])) {
        echo json_encode(['success' => true, 'message' => 'Admin added successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add admin']);
    }
}

function updateAdmin($conn) {
    $adminId = $_POST['admin_id'] ?? '';
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $contactNumber = trim($_POST['contact_number'] ?? '');
    $status = $_POST['status'] ?? 'Active';
    
    if (empty($adminId)) {
        echo json_encode(['success' => false, 'message' => 'Admin ID is required']);
        return;
    }
    
    // Validation
    $errors = [];
    
    if (empty($username)) $errors[] = 'Username is required';
    if (empty($fullName)) $errors[] = 'Full name is required';
    if (empty($email)) $errors[] = 'Email is required';
    if (empty($contactNumber)) $errors[] = 'Contact number is required';
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    }
    
    // Philippine mobile number validation - must start with 09 and be exactly 11 digits
    $contactDigits = preg_replace('/\D/', '', $contactNumber);
    if (strlen($contactDigits) !== 11 || !str_starts_with($contactDigits, '09')) {
        $errors[] = 'Contact number must be a Philippine mobile number starting with 09 and exactly 11 digits';
    }
    
    if (!empty($errors)) {
        echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
        return;
    }
    
    // Check for duplicates (excluding current admin)
    $checkStmt = $conn->prepare("SELECT COUNT(*) FROM admin WHERE (username = ? OR email = ? OR contact_number = ?) AND admin_id != ?");
    $checkStmt->execute([$username, $email, $contactNumber, $adminId]);
    $exists = $checkStmt->fetchColumn();
    
    if ($exists > 0) {
        echo json_encode(['success' => false, 'message' => 'Username, email, or contact number already exists']);
        return;
    }
    
    // Build update query
    $updateFields = ['username = ?', 'full_name = ?', 'email = ?', 'contact_number = ?', 'status = ?'];
    $params = [$username, $fullName, $email, $contactNumber, $status];
    
    // Handle password update (hash the password securely)
    if (!empty($password)) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $updateFields[] = 'password = ?';
        $params[] = $hashedPassword;
    }
    
    // Handle file upload
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../../uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $fileExtension = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
        $fileName = 'admin_' . time() . '_' . uniqid() . '.' . $fileExtension;
        $filePath = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $filePath)) {
            $updateFields[] = 'profile_image = ?';
            $params[] = $fileName;
        }
    }
    
    $params[] = $adminId;
    
    $stmt = $conn->prepare("UPDATE admin SET " . implode(', ', $updateFields) . " WHERE admin_id = ?");
    
    if ($stmt->execute($params)) {
        echo json_encode(['success' => true, 'message' => 'Admin updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update admin']);
    }
}

function toggleAdminStatus($conn) {
    $adminId = $_POST['admin_id'] ?? '';
    $newStatus = $_POST['status'] ?? '';
    
    if (empty($adminId) || empty($newStatus)) {
        echo json_encode(['success' => false, 'message' => 'Admin ID and status are required']);
        return;
    }
    
    if (!in_array($newStatus, ['Active', 'Inactive'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid status value']);
        return;
    }
    
    // Check if admin exists
    $checkStmt = $conn->prepare("SELECT COUNT(*) FROM admin WHERE admin_id = ?");
    $checkStmt->execute([$adminId]);
    $exists = $checkStmt->fetchColumn();
    
    if (!$exists) {
        echo json_encode(['success' => false, 'message' => 'Admin not found']);
        return;
    }
    
    // Update admin status
    $stmt = $conn->prepare("UPDATE admin SET status = ?, updated_at = NOW() WHERE admin_id = ?");
    
    if ($stmt->execute([$newStatus, $adminId])) {
        $action = $newStatus === 'Active' ? 'activated' : 'deactivated';
        echo json_encode(['success' => true, 'message' => "Admin has been {$action} successfully"]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update admin status']);
    }
}

function getCounts($conn) {
    $statusFilter = $_POST['status_filter'] ?? '';
    $nameSearch = $_POST['name_search'] ?? '';
    $emailSearch = $_POST['email_search'] ?? '';
    
    // Build WHERE clause
    $whereConditions = [];
    $params = [];
    
    if (!empty($statusFilter)) {
        $whereConditions[] = "status = ?";
        $params[] = $statusFilter;
    }
    
    if (!empty($nameSearch)) {
        $whereConditions[] = "full_name LIKE ?";
        $params[] = "%$nameSearch%";
    }
    
    if (!empty($emailSearch)) {
        $whereConditions[] = "email LIKE ?";
        $params[] = "%$emailSearch%";
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    // Get counts
    $countQuery = "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN status = 'Inactive' THEN 1 ELSE 0 END) as inactive
        FROM admin $whereClause";
    
    $stmt = $conn->prepare($countQuery);
    $stmt->execute($params);
    $counts = $stmt->fetch();
    
    echo json_encode([
        'success' => true,
        'data' => [
            'total' => intval($counts['total']),
            'active' => intval($counts['active']),
            'inactive' => intval($counts['inactive'])
        ]
    ]);
}

function checkDuplicate($conn) {
    $field = $_POST['field'] ?? '';
    $value = trim($_POST['value'] ?? '');
    $adminId = $_POST['admin_id'] ?? '';
    
    if (empty($field) || empty($value)) {
        echo json_encode(['success' => false, 'message' => 'Field and value are required']);
        return;
    }
    
    // Validate field name to prevent SQL injection
    $allowedFields = ['username', 'email', 'contact_number'];
    if (!in_array($field, $allowedFields)) {
        echo json_encode(['success' => false, 'message' => 'Invalid field']);
        return;
    }
    
    // Build query
    $query = "SELECT COUNT(*) FROM admin WHERE $field = ?";
    $params = [$value];
    
    // Exclude current admin if editing
    if (!empty($adminId)) {
        $query .= " AND admin_id != ?";
        $params[] = $adminId;
    }
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $count = $stmt->fetchColumn();
    
    echo json_encode([
        'success' => true,
        'exists' => $count > 0,
        'message' => $count > 0 ? 'Value already exists' : 'Value is available'
    ]);
}
?>
