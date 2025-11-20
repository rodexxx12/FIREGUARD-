<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../../db/db.php';


// Get database connection
$conn = getDatabaseConnection();

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'get_firefighters':
                $draw = intval($_POST['draw']);
                $start = intval($_POST['start']);
                $length = intval($_POST['length']);
                $searchValue = $_POST['search']['value'] ?? '';
                $orderColumn = intval($_POST['order'][0]['column'] ?? 0);
                $orderDir = $_POST['order'][0]['dir'] ?? 'asc';
                
                // Column mapping
                $columns = ['id', 'name', 'email', 'username', 'phone', 'badge_number', 'rank', 'specialization', 'availability', 'created_at'];
                $orderColumnName = $columns[$orderColumn] ?? 'id';
                
                // Build WHERE clause for filters
                $whereConditions = [];
                $params = [];
                
                // Search filter
                if (!empty($searchValue)) {
                    $whereConditions[] = "(name LIKE :search OR email LIKE :search OR username LIKE :search OR phone LIKE :search OR badge_number LIKE :search OR rank LIKE :search OR specialization LIKE :search)";
                    $params[':search'] = "%$searchValue%";
                }
                
                // Additional filters
                if (!empty($_POST['availability_filter'])) {
                    $whereConditions[] = "availability = :availability";
                    $params[':availability'] = $_POST['availability_filter'];
                }
                
                if (!empty($_POST['rank_filter'])) {
                    $whereConditions[] = "rank = :rank";
                    $params[':rank'] = $_POST['rank_filter'];
                }
                
                if (!empty($_POST['specialization_filter'])) {
                    $whereConditions[] = "specialization = :specialization";
                    $params[':specialization'] = $_POST['specialization_filter'];
                }
                
                $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
                
                // Get total records
                $totalQuery = "SELECT COUNT(*) as total FROM firefighters";
                $totalStmt = $conn->prepare($totalQuery);
                $totalStmt->execute();
                $totalRecords = $totalStmt->fetch()['total'];
                
                // Get filtered records count
                $filteredQuery = "SELECT COUNT(*) as total FROM firefighters $whereClause";
                $filteredStmt = $conn->prepare($filteredQuery);
                $filteredStmt->execute($params);
                $filteredRecords = $filteredStmt->fetch()['total'];
                
                // Get data
                $dataQuery = "SELECT * FROM firefighters $whereClause ORDER BY $orderColumnName $orderDir LIMIT :start, :length";
                $dataStmt = $conn->prepare($dataQuery);
                $dataStmt->bindValue(':start', $start, PDO::PARAM_INT);
                $dataStmt->bindValue(':length', $length, PDO::PARAM_INT);
                
                foreach ($params as $key => $value) {
                    $dataStmt->bindValue($key, $value);
                }
                
                $dataStmt->execute();
                $firefighters = $dataStmt->fetchAll();
                
                // Format data for DataTables
                $data = [];
                foreach ($firefighters as $firefighter) {
                    $availabilityBadge = $firefighter['availability'] ? 
                        '<span class="badge badge-success status-badge-enhanced"><i class="fas fa-check-circle"></i> Available</span>' :
                        '<span class="badge badge-danger status-badge-enhanced"><i class="fas fa-times-circle"></i> Unavailable</span>';
                    
                    // Enhanced badge number with icon and styling
                    $badgeNumber = $firefighter['badge_number'] ? 
                        '<span class="badge-number-enhanced"><i class="fas fa-id-badge text-primary"></i> <strong>' . htmlspecialchars($firefighter['badge_number']) . '</strong></span>' :
                        '<span class="text-muted no-data"><i class="fas fa-minus-circle"></i> N/A</span>';
                    
                    // Enhanced specialization with color coding
                    $specialization = $firefighter['specialization'] ? 
                        '<span class="specialization-enhanced"><i class="fas fa-tools text-info"></i> ' . htmlspecialchars($firefighter['specialization']) . '</span>' :
                        '<span class="text-muted no-data"><i class="fas fa-minus-circle"></i> N/A</span>';
                    
                    $data[] = [
                        $firefighter['id'],
                        htmlspecialchars($firefighter['name']),
                        htmlspecialchars($firefighter['email']),
                        htmlspecialchars($firefighter['username']),
                        htmlspecialchars($firefighter['phone']),
                        $badgeNumber,
                        htmlspecialchars($firefighter['rank'] ?? 'N/A'),
                        $specialization,
                        $availabilityBadge,
                        date('M d, Y', strtotime($firefighter['created_at'])),
                        '<button class="btn btn-outline-warning btn-sm edit-btn" data-id="' . $firefighter['id'] . '">
                            <i class="fas fa-edit"></i> Edit
                        </button>'
                    ];
                }
                
                echo json_encode([
                    'draw' => $draw,
                    'recordsTotal' => $totalRecords,
                    'recordsFiltered' => $filteredRecords,
                    'data' => $data
                ]);
                break;
                
            case 'get_firefighter':
                $id = intval($_POST['id']);
                $stmt = $conn->prepare("SELECT * FROM firefighters WHERE id = :id");
                $stmt->bindValue(':id', $id, PDO::PARAM_INT);
                $stmt->execute();
                $firefighter = $stmt->fetch();
                
                if ($firefighter) {
                    echo json_encode(['success' => true, 'data' => $firefighter]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Firefighter not found']);
                }
                break;
                
            case 'update_firefighter':
                $id = intval($_POST['id']);
                $name = trim($_POST['name']);
                $email = trim($_POST['email']);
                $username = trim($_POST['username']);
                $phone = trim($_POST['phone']);
                $badge_number = trim($_POST['badge_number']);
                $rank = trim($_POST['rank']);
                $specialization = trim($_POST['specialization']);
                $availability = intval($_POST['availability']);
                
                // Validation
                $errors = [];
                
                if (empty($name)) $errors[] = 'Name is required';
                if (empty($email)) $errors[] = 'Email is required';
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email format';
                if (empty($username)) $errors[] = 'Username is required';
                if (empty($phone)) $errors[] = 'Phone is required';
                
                // Check for duplicate email
                $stmt = $conn->prepare("SELECT id FROM firefighters WHERE email = :email AND id != :id");
                $stmt->bindValue(':email', $email);
                $stmt->bindValue(':id', $id, PDO::PARAM_INT);
                $stmt->execute();
                if ($stmt->fetch()) $errors[] = 'Email already exists';
                
                // Check for duplicate username
                $stmt = $conn->prepare("SELECT id FROM firefighters WHERE username = :username AND id != :id");
                $stmt->bindValue(':username', $username);
                $stmt->bindValue(':id', $id, PDO::PARAM_INT);
                $stmt->execute();
                if ($stmt->fetch()) $errors[] = 'Username already exists';
                
                // Check for duplicate badge number if provided
                if (!empty($badge_number)) {
                    $stmt = $conn->prepare("SELECT id FROM firefighters WHERE badge_number = :badge_number AND id != :id");
                    $stmt->bindValue(':badge_number', $badge_number);
                    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
                    $stmt->execute();
                    if ($stmt->fetch()) $errors[] = 'Badge number already exists';
                }
                
                if (!empty($errors)) {
                    echo json_encode(['success' => false, 'errors' => $errors]);
                    break;
                }
                
                // Update firefighter
                $stmt = $conn->prepare("UPDATE firefighters SET name = :name, email = :email, username = :username, phone = :phone, badge_number = :badge_number, rank = :rank, specialization = :specialization, availability = :availability WHERE id = :id");
                $stmt->bindValue(':name', $name);
                $stmt->bindValue(':email', $email);
                $stmt->bindValue(':username', $username);
                $stmt->bindValue(':phone', $phone);
                $stmt->bindValue(':badge_number', $badge_number ?: null);
                $stmt->bindValue(':rank', $rank ?: null);
                $stmt->bindValue(':specialization', $specialization ?: null);
                $stmt->bindValue(':availability', $availability, PDO::PARAM_INT);
                $stmt->bindValue(':id', $id, PDO::PARAM_INT);
                
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Firefighter updated successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to update firefighter']);
                }
                break;
                
            case 'insert_firefighter':
                $name = trim($_POST['name']);
                $email = trim($_POST['email']);
                $username = trim($_POST['username']);
                $password = trim($_POST['password']);
                $phone = trim($_POST['phone']);
                $badge_number = trim($_POST['badge_number']);
                $rank = trim($_POST['rank']);
                $specialization = trim($_POST['specialization']);
                $availability = intval($_POST['availability'] ?? 1);
                
                // Validation
                $errors = [];
                
                if (empty($name)) $errors[] = 'Name is required';
                if (empty($email)) $errors[] = 'Email is required';
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email format';
                if (!preg_match('/\.com$/', $email)) $errors[] = 'Email must end with .com';
                if (empty($username)) $errors[] = 'Username is required';
                if (empty($password)) $errors[] = 'Password is required';
                if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters';
                if (empty($phone)) $errors[] = 'Phone is required';
                if (!preg_match('/^09\d{9}$/', $phone)) $errors[] = 'Phone must start with 09 and be exactly 11 digits';
                
                // Check for duplicate email
                $stmt = $conn->prepare("SELECT id FROM firefighters WHERE email = :email");
                $stmt->bindValue(':email', $email);
                $stmt->execute();
                if ($stmt->fetch()) $errors[] = 'Email already exists';
                
                // Check for duplicate username
                $stmt = $conn->prepare("SELECT id FROM firefighters WHERE username = :username");
                $stmt->bindValue(':username', $username);
                $stmt->execute();
                if ($stmt->fetch()) $errors[] = 'Username already exists';
                
                // Check for duplicate badge number if provided
                if (!empty($badge_number)) {
                    $stmt = $conn->prepare("SELECT id FROM firefighters WHERE badge_number = :badge_number");
                    $stmt->bindValue(':badge_number', $badge_number);
                    $stmt->execute();
                    if ($stmt->fetch()) $errors[] = 'Badge number already exists';
                }
                
                if (!empty($errors)) {
                    echo json_encode(['success' => false, 'errors' => $errors]);
                    break;
                }
                
                // Hash password
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert firefighter
                $stmt = $conn->prepare("INSERT INTO firefighters (name, email, username, password, phone, badge_number, rank, specialization, availability) VALUES (:name, :email, :username, :password, :phone, :badge_number, :rank, :specialization, :availability)");
                $stmt->bindValue(':name', $name);
                $stmt->bindValue(':email', $email);
                $stmt->bindValue(':username', $username);
                $stmt->bindValue(':password', $hashedPassword);
                $stmt->bindValue(':phone', $phone);
                $stmt->bindValue(':badge_number', $badge_number ?: null);
                $stmt->bindValue(':rank', $rank ?: null);
                $stmt->bindValue(':specialization', $specialization ?: null);
                $stmt->bindValue(':availability', $availability, PDO::PARAM_INT);
                
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Firefighter added successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to add firefighter']);
                }
                break;
                
            case 'check_email_exists':
                $email = trim($_POST['email']);
                $id = intval($_POST['id'] ?? 0);
                
                $stmt = $conn->prepare("SELECT id FROM firefighters WHERE email = :email" . ($id > 0 ? " AND id != :id" : ""));
                $stmt->bindValue(':email', $email);
                if ($id > 0) $stmt->bindValue(':id', $id, PDO::PARAM_INT);
                $stmt->execute();
                
                echo json_encode(['exists' => $stmt->fetch() !== false]);
                break;
                
            case 'check_username_exists':
                $username = trim($_POST['username']);
                $id = intval($_POST['id'] ?? 0);
                
                $stmt = $conn->prepare("SELECT id FROM firefighters WHERE username = :username" . ($id > 0 ? " AND id != :id" : ""));
                $stmt->bindValue(':username', $username);
                if ($id > 0) $stmt->bindValue(':id', $id, PDO::PARAM_INT);
                $stmt->execute();
                
                echo json_encode(['exists' => $stmt->fetch() !== false]);
                break;
                
            case 'get_filter_options':
                // Get unique ranks
                $stmt = $conn->prepare("SELECT DISTINCT rank FROM firefighters WHERE rank IS NOT NULL AND rank != '' ORDER BY rank");
                $stmt->execute();
                $ranks = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                // Get unique specializations
                $stmt = $conn->prepare("SELECT DISTINCT specialization FROM firefighters WHERE specialization IS NOT NULL AND specialization != '' ORDER BY specialization");
                $stmt->execute();
                $specializations = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                echo json_encode([
                    'success' => true,
                    'ranks' => $ranks,
                    'specializations' => $specializations
                ]);
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } catch (Exception $e) {
        error_log("Firefighter table error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'An error occurred']);
    }
    exit;
}
?>

    
    <!-- Include header with all necessary libraries -->
    <?php include '../../components/header.php'; ?>
    
    <!-- DataTables Buttons CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">
    
    <!-- Custom CSS for firefighter table -->
    <link rel="stylesheet" href="../css/firefighter_table.css">
</head>
<body class="nav-md">
    <div class="container body">
      <div class="main_container">
            <?php include('../../components/sidebar.php'); ?>
            </div>
          </div>
        </div>
        <?php include('../../components/navigation.php')?>
        <div class="right_col" role="main"> 
    <div class="main-card">
   <!-- Main Content -->
 <div class="row">
            <div class="col-12">
                <div class="x_panel">
                    <div class="x_title">
                        <h2><i class="fas fa-list-alt"></i> Firefighters Records</h2>
                        <div class="clearfix"></div>
                    </div>
        <!-- Filter Panel -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5><i class="fas fa-filter"></i> Advanced Filters & Search</h5>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="clearFilters">
                        <i class="fas fa-times"></i> Clear Filters
                    </button>
                    <button type="button" class="btn btn-outline-success btn-sm" id="exportData">
                        <i class="fas fa-download"></i> Export
                    </button>
                    <button type="button" class="btn btn-primary btn-sm" id="addFirefighterBtn">
                        <i class="fas fa-plus"></i> Add New Firefighter
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <label for="customSearch" class="form-label">Search Firefighters</label>
                        <div class="input-group">
                            <input type="text" class="form-control form-control-sm" id="customSearch" placeholder="Search by name, email, username, phone, badge number...">
                            <span class="input-group-text">
                                <i class="fas fa-search"></i>
                            </span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label for="availabilityFilter" class="form-label">Availability Status</label>
                        <select id="availabilityFilter" class="form-select form-select-sm">
                            <option value="">All Status</option>
                            <option value="1">Available</option>
                            <option value="0">Unavailable</option>
                        </select>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-md-6">
                        <label for="rankFilter" class="form-label">Rank</label>
                        <select id="rankFilter" class="form-select form-select-sm">
                            <option value="">All Ranks</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="specializationFilter" class="form-label">Specialization</label>
                        <select id="specializationFilter" class="form-select form-select-sm">
                            <option value="">All Specializations</option>
                        </select>
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-12">
                        <div id="filterStatus" class="text-muted">
                            <small>Active filters: <span id="activeFiltersCount">0</span></small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Table Panel -->
        <div class="x_panel">
            <div class="x_content">
                <div class="table-responsive">
                    <table id="firefightersTable" class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Username</th>
                                <th>Phone</th>
                                <th>Badge #</th>
                                <th>Rank</th>
                                <th>Specialization</th>
                                <th>Status</th>
                                <th>Joined</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Data will be loaded via AJAX -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Firefighter Modal -->
    <div class="modal fade" id="editFirefighterModal" tabindex="-1" aria-labelledby="editFirefighterModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editFirefighterModalLabel">
                        <i class="fas fa-edit"></i> Edit Firefighter
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editFirefighterForm">
                        <input type="hidden" id="firefighterId" name="id">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="editName" class="form-label">Full Name <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="editName" name="name" required>
                                        <span class="validation-icon"></span>
                                    </div>
                                    <div class="invalid-feedback"></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="editEmail" class="form-label">Email Address <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="email" class="form-control" id="editEmail" name="email" required>
                                        <span class="validation-icon"></span>
                                    </div>
                                    <div class="invalid-feedback"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="editUsername" class="form-label">Username <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="editUsername" name="username" required>
                                        <span class="validation-icon"></span>
                                    </div>
                                    <div class="invalid-feedback"></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="editPhone" class="form-label">Phone Number <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="tel" class="form-control" id="editPhone" name="phone" required>
                                        <span class="validation-icon"></span>
                                    </div>
                                    <div class="invalid-feedback"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="editBadgeNumber" class="form-label">Badge Number</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="editBadgeNumber" name="badge_number">
                                        <span class="validation-icon"></span>
                                    </div>
                                    <div class="invalid-feedback"></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="editRank" class="form-label">Rank</label>
                                    <input type="text" class="form-control" id="editRank" name="rank">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="editSpecialization" class="form-label">Specialization</label>
                                    <input type="text" class="form-control" id="editSpecialization" name="specialization">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="editAvailability" class="form-label">Availability Status</label>
                                    <select class="form-select" id="editAvailability" name="availability">
                                        <option value="1">Available</option>
                                        <option value="0">Unavailable</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="button" class="btn btn-primary" id="saveFirefighterBtn">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Firefighter Modal -->
    <div class="modal fade" id="addFirefighterModal" tabindex="-1" aria-labelledby="addFirefighterModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addFirefighterModalLabel">
                        <i class="fas fa-plus"></i> Add New Firefighter
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addFirefighterForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="addName" class="form-label">Full Name <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="addName" name="name" required>
                                        <span class="validation-icon"></span>
                                    </div>
                                    <div class="invalid-feedback"></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="addEmail" class="form-label">Email Address <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="email" class="form-control" id="addEmail" name="email" required>
                                        <span class="validation-icon"></span>
                                    </div>
                                    <div class="invalid-feedback"></div>
                                    <small class="form-text text-muted">Email must end with .com</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="addUsername" class="form-label">Username <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="addUsername" name="username" required>
                                        <span class="validation-icon"></span>
                                    </div>
                                    <div class="invalid-feedback"></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="addPassword" class="form-label">Password <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="addPassword" name="password" required>
                                        <span class="validation-icon"></span>
                                    </div>
                                    <div class="invalid-feedback"></div>
                                    <small class="form-text text-muted">Minimum 6 characters</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="addPhone" class="form-label">Phone Number <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="tel" class="form-control" id="addPhone" name="phone" required placeholder="09XXXXXXXXX">
                                        <span class="validation-icon"></span>
                                    </div>
                                    <div class="invalid-feedback"></div>
                                    <small class="form-text text-muted">Must start with 09 and be 11 digits</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="addBadgeNumber" class="form-label">Badge Number</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="addBadgeNumber" name="badge_number">
                                        <span class="validation-icon"></span>
                                    </div>
                                    <div class="invalid-feedback"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="addRank" class="form-label">Rank</label>
                                    <input type="text" class="form-control" id="addRank" name="rank">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="addSpecialization" class="form-label">Specialization</label>
                                    <input type="text" class="form-control" id="addSpecialization" name="specialization">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="addAvailability" class="form-label">Availability Status</label>
                                    <select class="form-select" id="addAvailability" name="availability">
                                        <option value="1">Available</option>
                                        <option value="0">Unavailable</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="button" class="btn btn-primary" id="saveNewFirefighterBtn">
                        <i class="fas fa-save"></i> Add Firefighter
                    </button>
                </div>
            </div>
        </div>
    </div>
</body>
<?php include '../../components/scripts.php'; ?>

    <!-- DataTables Core - Load after scripts.php to ensure proper jQuery instance -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    
    <!-- DataTables Buttons Extension -->
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    
    <script>
    // Wait for DataTables to be available
    function initFirefightersTable() {
        // Check if jQuery and DataTables are available
        if (typeof jQuery === 'undefined' || typeof jQuery.fn === 'undefined' || typeof jQuery.fn.DataTable === 'undefined') {
            // Wait a bit and try again
            setTimeout(initFirefightersTable, 50);
            return;
        }
        
        jQuery(document).ready(function($) {
            let firefightersTable;
            let filterOptions = { ranks: [], specializations: [] };
            
            // Initialize DataTable
            function initializeTable() {
                firefightersTable = $('#firefightersTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '',
                    type: 'POST',
                    data: function(d) {
                        d.action = 'get_firefighters';
                        d.availability_filter = $('#availabilityFilter').val();
                        d.rank_filter = $('#rankFilter').val();
                        d.specialization_filter = $('#specializationFilter').val();
                    }
                },
                columns: [
                    { data: 0, visible: true }, // ID
                    { data: 1 }, // Name
                    { data: 2 }, // Email
                    { data: 3 }, // Username
                    { data: 4 }, // Phone
                    { data: 5 }, // Badge Number
                    { data: 6 }, // Rank
                    { data: 7 }, // Specialization
                    { data: 8, orderable: false }, // Status
                    { data: 9 }, // Joined
                    { data: 10, orderable: false, searchable: false } // Actions
                ],
                order: [[0, 'desc']],
                pageLength: 25,
                lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
                dom: 'Brtip',
                buttons: [
                    {
                        extend: 'excel',
                        text: '<i class="fas fa-file-excel"></i> Excel',
                        className: 'btn btn-success btn-sm',
                        exportOptions: {
                            columns: [0, 1, 2, 3, 4, 5, 6, 7, 8, 9] // Exclude actions column
                        }
                    },
                    {
                        extend: 'pdf',
                        text: '<i class="fas fa-file-pdf"></i> PDF',
                        className: 'btn btn-danger btn-sm',
                        exportOptions: {
                            columns: [0, 1, 2, 3, 4, 5, 6, 7, 8, 9] // Exclude actions column
                        }
                    },
                    {
                        extend: 'print',
                        text: '<i class="fas fa-print"></i> Print',
                        className: 'btn btn-info btn-sm',
                        exportOptions: {
                            columns: [0, 1, 2, 3, 4, 5, 6, 7, 8, 9] // Exclude actions column
                        }
                    }
                ],
                language: {
                    processing: "Loading firefighters...",
                    emptyTable: "No firefighters found",
                    zeroRecords: "No matching firefighters found"
                }
            });
        }
        
        // Load filter options
        function loadFilterOptions() {
            $.post('', { action: 'get_filter_options' }, function(response) {
                if (response.success) {
                    filterOptions = response;
                    
                    // Populate rank filter
                    const rankSelect = $('#rankFilter');
                    rankSelect.empty().append('<option value="">All Ranks</option>');
                    response.ranks.forEach(rank => {
                        rankSelect.append(`<option value="${rank}">${rank}</option>`);
                    });
                    
                    // Populate specialization filter
                    const specializationSelect = $('#specializationFilter');
                    specializationSelect.empty().append('<option value="">All Specializations</option>');
                    response.specializations.forEach(specialization => {
                        specializationSelect.append(`<option value="${specialization}">${specialization}</option>`);
                    });
                }
            });
        }
        
        // Real-time validation
        function setupValidation() {
            const editFields = ['editName', 'editEmail', 'editUsername', 'editPhone', 'editBadgeNumber'];
            const addFields = ['addName', 'addEmail', 'addUsername', 'addPhone', 'addPassword', 'addBadgeNumber'];
            
            [...editFields, ...addFields].forEach(fieldId => {
                const field = $(`#${fieldId}`);
                const icon = field.siblings('.validation-icon');
                const feedback = field.siblings('.invalid-feedback');
                
                field.on('blur keyup', function() {
                    validateField(fieldId, field.val(), icon, feedback);
                });
                
                // Real-time duplicate checking for email and username
                if (fieldId.includes('Email')) {
                    field.on('blur', function() {
                        checkEmailExists(fieldId, field.val());
                    });
                }
                
                if (fieldId.includes('Username')) {
                    field.on('blur', function() {
                        checkUsernameExists(fieldId, field.val());
                    });
                }
            });
        }
        
        function validateField(fieldId, value, icon, feedback) {
            let isValid = true;
            let message = '';
            
            switch (fieldId) {
                case 'editName':
                case 'addName':
                    if (value.trim().length < 2) {
                        isValid = false;
                        message = 'Name must be at least 2 characters';
                    }
                    break;
                    
                case 'editEmail':
                case 'addEmail':
                    if (!value.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
                        isValid = false;
                        message = 'Please enter a valid email address';
                    } else if (!value.match(/\.com$/)) {
                        isValid = false;
                        message = 'Email must end with .com';
                    }
                    break;
                    
                case 'editUsername':
                case 'addUsername':
                    if (value.trim().length < 3) {
                        isValid = false;
                        message = 'Username must be at least 3 characters';
                    } else if (!value.match(/^[a-zA-Z0-9_]+$/)) {
                        isValid = false;
                        message = 'Username can only contain letters, numbers, and underscores';
                    }
                    break;
                    
                case 'editPhone':
                case 'addPhone':
                    if (!value.match(/^09\d{9}$/)) {
                        isValid = false;
                        message = 'Phone must start with 09 and be exactly 11 digits';
                    }
                    break;
                    
                case 'addPassword':
                    if (value.length < 6) {
                        isValid = false;
                        message = 'Password must be at least 6 characters';
                    }
                    break;
                    
                case 'editBadgeNumber':
                case 'addBadgeNumber':
                    if (value && value.trim().length < 3) {
                        isValid = false;
                        message = 'Badge number must be at least 3 characters';
                    }
                    break;
            }
            
            // Update field appearance
            if (isValid) {
                icon.removeClass('invalid').addClass('valid').html('<i class="fas fa-check"></i>');
                feedback.text('');
            } else {
                icon.removeClass('valid').addClass('invalid').html('<i class="fas fa-times"></i>');
                feedback.text(message);
            }
            
            return isValid;
        }
        
        // Check email existence
        function checkEmailExists(fieldId, email) {
            if (!email || !email.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) return;
            
            const id = fieldId.includes('edit') ? $('#firefighterId').val() : 0;
            const field = $(`#${fieldId}`);
            const icon = field.siblings('.validation-icon');
            const feedback = field.siblings('.invalid-feedback');
            
            $.post('', { 
                action: 'check_email_exists', 
                email: email,
                id: id
            }, function(response) {
                if (response.exists) {
                    icon.removeClass('valid').addClass('invalid').html('<i class="fas fa-times"></i>');
                    feedback.text('Email already exists');
                } else if (email.match(/\.com$/)) {
                    icon.removeClass('invalid').addClass('valid').html('<i class="fas fa-check"></i>');
                    feedback.text('');
                }
            });
        }
        
        // Check username existence
        function checkUsernameExists(fieldId, username) {
            if (!username || username.length < 3) return;
            
            const id = fieldId.includes('edit') ? $('#firefighterId').val() : 0;
            const field = $(`#${fieldId}`);
            const icon = field.siblings('.validation-icon');
            const feedback = field.siblings('.invalid-feedback');
            
            $.post('', { 
                action: 'check_username_exists', 
                username: username,
                id: id
            }, function(response) {
                if (response.exists) {
                    icon.removeClass('valid').addClass('invalid').html('<i class="fas fa-times"></i>');
                    feedback.text('Username already exists');
                } else if (username.match(/^[a-zA-Z0-9_]+$/)) {
                    icon.removeClass('invalid').addClass('valid').html('<i class="fas fa-check"></i>');
                    feedback.text('');
                }
            });
        }
        
        // Filter change handlers
        $('#availabilityFilter, #rankFilter, #specializationFilter').on('change', function() {
            updateFilterStatus();
            firefightersTable.ajax.reload();
            
            // Show simple success modal when filters are applied
            const filterName = $(this).find('option:selected').text();
            const filterType = $(this).attr('id').replace('Filter', '');
            
            if ($(this).val() !== '') {
                Swal.fire({
                    title: 'Filter Applied!',
                    text: `${filterType.charAt(0).toUpperCase() + filterType.slice(1)} filter has been applied successfully.`,
                    icon: 'success',
                    showConfirmButton: false,
                    timer: 1200,
                    timerProgressBar: true,
                    toast: false,
                    position: 'center'
                });
            }
        });
        
        // Custom search handler
        $('#customSearch').on('keyup', function() {
            const searchValue = $(this).val();
            firefightersTable.search(searchValue).draw();
            updateFilterStatus();
        });
        
        // Clear filters
        $('#clearFilters').on('click', function() {
            $('#availabilityFilter, #rankFilter, #specializationFilter').val('');
            $('#customSearch').val('');
            firefightersTable.search('').draw();
            updateFilterStatus();
            firefightersTable.ajax.reload();
            
            // Show simple success modal
            Swal.fire({
                title: 'Filters Cleared!',
                text: 'All filters have been successfully cleared.',
                icon: 'success',
                showConfirmButton: false,
                timer: 1500,
                timerProgressBar: true,
                toast: false,
                position: 'center'
            });
        });
        
        // Update filter status
        function updateFilterStatus() {
            let activeCount = 0;
            if ($('#customSearch').val()) activeCount++;
            if ($('#availabilityFilter').val()) activeCount++;
            if ($('#rankFilter').val()) activeCount++;
            if ($('#specializationFilter').val()) activeCount++;
            
            $('#activeFiltersCount').text(activeCount);
        }
        
        // Edit button handler
        $(document).on('click', '.edit-btn', function() {
            const id = $(this).data('id');
            
            $.post('', { action: 'get_firefighter', id: id }, function(response) {
                if (response.success) {
                    const data = response.data;
                    $('#firefighterId').val(data.id);
                    $('#editName').val(data.name);
                    $('#editEmail').val(data.email);
                    $('#editUsername').val(data.username);
                    $('#editPhone').val(data.phone);
                    $('#editBadgeNumber').val(data.badge_number || '');
                    $('#editRank').val(data.rank || '');
                    $('#editSpecialization').val(data.specialization || '');
                    $('#editAvailability').val(data.availability);
                    
                    // Clear validation states
                    $('.validation-icon').removeClass('valid invalid').html('');
                    $('.invalid-feedback').text('');
                    
                    $('#editFirefighterModal').modal('show');
                } else {
                    Swal.fire('Error', response.message, 'error');
                }
            });
        });
        
         // Save firefighter
         $('#saveFirefighterBtn').on('click', function() {
             const form = $('#editFirefighterForm');
             const formData = form.serializeArray();
             const data = {};
             
             // Convert form data to object
             formData.forEach(item => {
                 data[item.name] = item.value;
             });
             
             // Validate all fields
             let isValid = true;
             const fields = ['editName', 'editEmail', 'editUsername', 'editPhone'];
             
             fields.forEach(fieldId => {
                 const field = $(`#${fieldId}`);
                 const icon = field.siblings('.validation-icon');
                 const feedback = field.siblings('.invalid-feedback');
                 
                 if (!validateField(fieldId, field.val(), icon, feedback)) {
                     isValid = false;
                 }
             });
             
             if (!isValid) {
                 Swal.fire('Validation Error', 'Please fix the validation errors before saving.', 'error');
                 return;
             }
             
             $.post('', { action: 'update_firefighter', ...data }, function(response) {
                 if (response.success) {
                     Swal.fire('Success', response.message, 'success').then(() => {
                         $('#editFirefighterModal').modal('hide');
                         firefightersTable.ajax.reload();
                     });
                 } else {
                     if (response.errors) {
                         let errorMessage = 'Please fix the following errors:\n';
                         response.errors.forEach(error => {
                             errorMessage += `• ${error}\n`;
                         });
                         Swal.fire('Validation Error', errorMessage, 'error');
                     } else {
                         Swal.fire('Error', response.message, 'error');
                     }
                 }
             });
         });
        
        // Add firefighter button handler
        $('#addFirefighterBtn').on('click', function() {
            // Clear form
            $('#addFirefighterForm')[0].reset();
            
            // Clear validation states
            $('.validation-icon').removeClass('valid invalid').html('');
            $('.invalid-feedback').text('');
            
            $('#addFirefighterModal').modal('show');
        });
        
         // Save new firefighter
         $('#saveNewFirefighterBtn').on('click', function() {
             const form = $('#addFirefighterForm');
             const formData = form.serializeArray();
             const data = {};
             
             // Convert form data to object
             formData.forEach(item => {
                 data[item.name] = item.value;
             });
             
             // Validate all required fields
             let isValid = true;
             const requiredFields = ['addName', 'addEmail', 'addUsername', 'addPhone', 'addPassword'];
             
             requiredFields.forEach(fieldId => {
                 const field = $(`#${fieldId}`);
                 const icon = field.siblings('.validation-icon');
                 const feedback = field.siblings('.invalid-feedback');
                 
                 if (!validateField(fieldId, field.val(), icon, feedback)) {
                     isValid = false;
                 }
             });
             
             // Check for any existing validation errors
             if ($('.validation-icon.invalid').length > 0) {
                 isValid = false;
             }
             
             if (!isValid) {
                 Swal.fire('Validation Error', 'Please fix all validation errors before saving.', 'error');
                 return;
             }
             
             $.post('', { action: 'insert_firefighter', ...data }, function(response) {
                 if (response.success) {
                     Swal.fire('Success', response.message, 'success').then(() => {
                         $('#addFirefighterModal').modal('hide');
                         firefightersTable.ajax.reload();
                         loadFilterOptions(); // Refresh filter options
                     });
                 } else {
                     if (response.errors) {
                         let errorMessage = 'Please fix the following errors:\n';
                         response.errors.forEach(error => {
                             errorMessage += `• ${error}\n`;
                         });
                         Swal.fire('Validation Error', errorMessage, 'error');
                     } else {
                         Swal.fire('Error', response.message, 'error');
                     }
                 }
             });
         });
        
        // Export functionality
        $('#exportData').on('click', function() {
            firefightersTable.button('.buttons-excel').trigger();
            
            // Show simple success modal for export
            Swal.fire({
                title: 'Export Started!',
                text: 'Your firefighter data is being exported to Excel format.',
                icon: 'success',
                showConfirmButton: false,
                timer: 2000,
                timerProgressBar: true,
                toast: false,
                position: 'center'
            });
        });
        
            // Initialize everything
            loadFilterOptions();
            initializeTable();
            setupValidation();
            updateFilterStatus();
        });
    }
    
    // Start initialization
    initFirefightersTable();
    </script>
</html>
