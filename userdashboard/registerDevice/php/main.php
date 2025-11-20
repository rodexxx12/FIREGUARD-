<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../../index.php");
    exit();
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

include('../../db/db.php');

// Create an alias function for getDBConnection to maintain compatibility
function getDBConnection() {
    return getDatabaseConnection();
}

// Handle AJAX POST request for registering device
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    try {
        $conn = getDBConnection();
        $user_id = $_SESSION['user_id'];
    } catch (Exception $e) {
        error_log("Database connection error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Unable to connect to the database.']);
        exit;
    }

    $action = isset($_POST['action']) ? trim($_POST['action']) : null;

    if ($action === 'validate_field') {
        $field = isset($_POST['field']) ? trim($_POST['field']) : '';
        $value = isset($_POST['value']) ? trim($_POST['value']) : '';
        $deviceId = isset($_POST['device_id']) ? (int)$_POST['device_id'] : null;
        $response = ['valid' => false, 'message' => 'Unknown field validation request'];

        if ($field === 'device_number') {
            if ($value === '') {
                $response = ['valid' => false, 'message' => 'Device number is required'];
            } else {
                $stmt = $conn->prepare("SELECT status FROM admin_devices WHERE device_number = ?");
                $stmt->execute([$value]);
                $adminDevice = $stmt->fetch();

                if (!$adminDevice) {
                    $response = ['valid' => false, 'message' => 'Device number is not recognized. Please contact support.'];
                } elseif ($adminDevice['status'] !== 'approved') {
                    $response = ['valid' => false, 'message' => 'Device number is not approved for registration.'];
                } else {
                    $query = "SELECT COUNT(*) FROM devices WHERE device_number = ? AND user_id = ?";
                    $params = [$value, $user_id];

                    if (!empty($deviceId)) {
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
                $deviceNumber = isset($_POST['device_number']) ? trim($_POST['device_number']) : '';

                if ($deviceNumber !== '') {
                    $stmt = $conn->prepare("SELECT status FROM admin_devices WHERE serial_number = ? AND device_number = ?");
                    $stmt->execute([$value, $deviceNumber]);
                } else {
                    $stmt = $conn->prepare("SELECT status FROM admin_devices WHERE serial_number = ?");
                    $stmt->execute([$value]);
                }
                $adminDevice = $stmt->fetch();

                if (!$adminDevice) {
                    $response = ['valid' => false, 'message' => 'Serial number is not recognized. Please contact support.'];
                } elseif ($adminDevice['status'] !== 'approved') {
                    $response = ['valid' => false, 'message' => 'Serial number is not approved for registration.'];
                } else {
                    $query = "SELECT COUNT(*) FROM devices WHERE serial_number = ?";
                    $params = [$value];

                    if (!empty($deviceId)) {
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
        $device_id = isset($_POST['device_id']) ? (int)$_POST['device_id'] : 0;

        if ($device_id <= 0) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid device selected.']);
            exit;
        }

        $stmt = $conn->prepare("SELECT device_id FROM devices WHERE device_id = ? AND user_id = ?");
        $stmt->execute([$device_id, $user_id]);

        if (!$stmt->fetch()) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'You do not have permission to update this device.']);
            exit;
        }

        $device_name = isset($_POST['device_name']) ? htmlspecialchars(trim($_POST['device_name'])) : '';
        $device_number = isset($_POST['device_number']) ? htmlspecialchars(trim($_POST['device_number'])) : '';
        $serial_number = isset($_POST['serial_number']) ? htmlspecialchars(trim($_POST['serial_number'])) : '';
        $device_type = isset($_POST['device_type']) ? htmlspecialchars(trim($_POST['device_type'])) : 'FIREGUARD DEVICE';
        $status = isset($_POST['status']) ? htmlspecialchars(trim($_POST['status'])) : 'offline';
        $barangay_id = isset($_POST['barangay_id']) && $_POST['barangay_id'] !== '' ? (int)$_POST['barangay_id'] : null;
        $is_active = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 0;

        $errors = [];
        if ($device_name === '') $errors[] = 'Device name is required';
        if ($device_number === '') $errors[] = 'Device number is required';
        if ($serial_number === '') $errors[] = 'Serial number is required';

        if (!in_array($status, ['online', 'offline', 'faulty'])) {
            $errors[] = 'Invalid device status';
        }

        $stmt = $conn->prepare("SELECT COUNT(*) FROM devices WHERE device_number = ? AND user_id = ? AND device_id != ?");
        $stmt->execute([$device_number, $user_id, $device_id]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = 'Device number already exists for your account';
        }

        $stmt = $conn->prepare("SELECT COUNT(*) FROM devices WHERE serial_number = ? AND device_id != ?");
        $stmt->execute([$serial_number, $device_id]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = 'Serial number already exists';
        }

        if ($device_number !== '' && $serial_number !== '') {
            $stmt = $conn->prepare("SELECT status FROM admin_devices WHERE device_number = ? AND serial_number = ?");
            $stmt->execute([$device_number, $serial_number]);
            $adminDevice = $stmt->fetch();

            if (!$adminDevice) {
                $errors[] = 'Device number and serial number do not match any authorized devices. Please verify your details.';
            } elseif ($adminDevice['status'] !== 'approved') {
                $errors[] = 'This device is not approved for registration.';
            }
        }

        if (!empty($barangay_id)) {
            $stmt = $conn->prepare("SELECT COUNT(*) FROM barangay WHERE id = ?");
            $stmt->execute([$barangay_id]);
            if ($stmt->fetchColumn() == 0) {
                $errors[] = 'Invalid barangay selected';
            }
        }

        if (!empty($errors)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => implode('<br>', $errors)]);
            exit;
        }

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
        $device_id = isset($_POST['device_id']) ? (int)$_POST['device_id'] : 0;
        $is_active = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 0;

        if ($device_id <= 0) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid device selected.']);
            exit;
        }

        $stmt = $conn->prepare("SELECT device_id FROM devices WHERE device_id = ? AND user_id = ?");
        $stmt->execute([$device_id, $user_id]);

        if (!$stmt->fetch()) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'You do not have permission to modify this device.']);
            exit;
        }

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
        $device_name = isset($_POST['device_name']) ? htmlspecialchars(trim($_POST['device_name'])) : '';
        $device_number = isset($_POST['device_number']) ? htmlspecialchars(trim($_POST['device_number'])) : '';
        $serial_number = isset($_POST['serial_number']) ? htmlspecialchars(trim($_POST['serial_number'])) : '';
        $device_type = isset($_POST['device_type']) ? htmlspecialchars(trim($_POST['device_type'])) : 'FIREGUARD DEVICE';
        $is_active = isset($_POST['is_active']) ? 1 : 1;
        $status = isset($_POST['status']) ? htmlspecialchars(trim($_POST['status'])) : 'offline';
        $building_id = !empty($_POST['building_id']) ? (int)$_POST['building_id'] : null;
        $barangay_id = !empty($_POST['barangay_id']) ? (int)$_POST['barangay_id'] : null;
        $wifi_ssid = isset($_POST['wifi_ssid']) ? htmlspecialchars(trim($_POST['wifi_ssid'])) : null;
        $wifi_password = isset($_POST['wifi_password']) ? htmlspecialchars(trim($_POST['wifi_password'])) : null;

        $errors = [];
        if (empty($device_name)) $errors[] = 'Device name is required';
        if (empty($device_number)) $errors[] = 'Device number is required';
        if (empty($serial_number)) $errors[] = 'Serial number is required';

        $stmt = $conn->prepare("SELECT COUNT(*) FROM devices WHERE device_number = ? AND user_id = ?");
        $stmt->execute([$device_number, $user_id]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = 'Device number already exists for your account';
        }

        $stmt = $conn->prepare("SELECT COUNT(*) FROM devices WHERE serial_number = ?");
        $stmt->execute([$serial_number]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = 'Serial number already exists';
        }

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

        if (!in_array($status, ['online', 'offline', 'faulty'])) {
            $errors[] = 'Invalid device status';
        }

        if (!empty($building_id)) {
            $stmt = $conn->prepare("SELECT COUNT(*) FROM buildings WHERE id = ? AND user_id = ?");
            $stmt->execute([$building_id, $user_id]);
            if ($stmt->fetchColumn() == 0) {
                $errors[] = 'Invalid building selected';
            }
        }

        if (!empty($barangay_id)) {
            $stmt = $conn->prepare("SELECT COUNT(*) FROM barangay WHERE id = ?");
            $stmt->execute([$barangay_id]);
            if ($stmt->fetchColumn() == 0) {
                $errors[] = 'Invalid barangay selected';
            }
        }

        if (!empty($errors)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => implode('<br>', $errors)]);
            exit;
        }

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
    } catch (Exception $e) {
        error_log("Device registration error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
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
?>
<?php include('../../components/header.php'); ?>
<style>
    .x_panel {
        background: white;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        margin-bottom: 20px;
    }
    .x_title {
        border-bottom: 1px solid #e5e5e5;
        padding: 15px 20px;
        margin-bottom: 0;
    }
    .x_title h2 {
        margin: 0;
        font-size: 18px;
        font-weight: 600;
        color: #333;
    }
    .x_content {
        padding: 20px;
    }
    .item.form-group {
        margin-bottom: 20px;
    }
    .item.form-group label {
        font-weight: 600;
        color: #555;
    }
    .item.form-group label.required:after,
    .item.form-group label .required {
        color: #e74c3c;
    }
    .ln_solid {
        border-top: 1px solid #e5e5e5;
        margin: 20px 0;
    }
    .form-control {
        border-radius: 4px;
        border: 1px solid #ddd;
        padding: 8px 12px;
        font-size: 14px;
        transition: border-color 0.3s;
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
        padding: 10px 20px;
        font-size: 14px;
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
        padding: 10px 20px;
        font-size: 14px;
        border-radius: 4px;
        transition: all 0.3s;
    }
    .btn-default:hover {
        background-color: #e6e6e6;
        border-color: #adadad;
    }
    .help-block {
        color: #737373;
        font-size: 12px;
        margin-top: 5px;
    }
    .invalid-feedback {
        display: none;
        color: #e74c3c;
        font-size: 12px;
        margin-top: 5px;
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
</style>
<body class="nav-md">
    <div class="container body">
      <div class="main_container">
        <div class="col-md-3 left_col">
          <div class="left_col scroll-view">
            <?php include('../../components/sidebar.php'); ?>
            </div>
          </div>
        </div>
        <?php include('../../components/navigation.php')?>
        <div class="right_col" role="main">        
            <main class="main-content">
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
                                                            <td><?php echo htmlspecialchars($device['device_name']); ?></td>
                                                            <td><?php echo htmlspecialchars($device['device_number']); ?></td>
                                                            <td><?php echo htmlspecialchars($device['serial_number']); ?></td>
                                                            <td><?php echo htmlspecialchars($device['device_type']); ?></td>
                                                            <td>
                                                                <?php
                                                                    $statusClass = strtolower($device['status']);
                                                                    $statusLabel = ucfirst($device['status']);
                                                                ?>
                                                                <span class="label-status <?php echo $statusClass; ?>"><?php echo htmlspecialchars($statusLabel); ?></span>
                                                            </td>
                                                            <td>
                                                                <?php if ((int)$device['is_active'] === 1): ?>
                                                                    <span class="label-active active">Active</span>
                                                                <?php else: ?>
                                                                    <span class="label-active inactive">Inactive</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td><?php echo !empty($device['barangay_name']) ? htmlspecialchars($device['barangay_name']) : '<em>-</em>'; ?></td>
                                                            <td><?php echo htmlspecialchars(date('M d, Y g:i A', strtotime($device['created_at']))); ?></td>
                                                            <td class="device-actions">
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
                                                                >
                                                                    <i class="fa fa-pencil"></i> Update
                                                                </button>
                                                                <button 
                                                                    type="button" 
                                                                    class="btn btn-xs toggle-device-btn <?php echo $isActive ? 'btn-warning' : 'btn-success'; ?>"
                                                                    data-device-id="<?php echo (int)$device['device_id']; ?>"
                                                                    data-is-active="<?php echo $isActive; ?>"
                                                                >
                                                                    <i class="fa fa-power-off"></i> <?php echo $isActive ? 'Disable' : 'Enable'; ?>
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <tr>
                                                        <td colspan="9">
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
                                    <div class="item form-group">
                                        <label class="col-form-label col-md-3 col-sm-3 label-align required" for="device_name">
                                            Device Name <span class="required">*</span>
                                        </label>
                                        <div class="col-md-6 col-sm-6">
                                            <input type="text" class="form-control" id="device_name" name="device_name" required="required">
                                            <span class="help-block">Enter a descriptive name for your device (e.g., "Kitchen Sensor", "Main Hall Device")</span>
                                            <div class="invalid-feedback">Please provide a device name</div>
                                        </div>
                                    </div>

                                    <div class="item form-group">
                                        <label class="col-form-label col-md-3 col-sm-3 label-align required" for="device_number">
                                            Device Number <span class="required">*</span>
                                        </label>
                                        <div class="col-md-6 col-sm-6">
                                            <input type="text" class="form-control" id="device_number" name="device_number" required="required">
                                            <span class="help-block">Unique identifier for this device</span>
                                            <div class="invalid-feedback">Please provide a device number</div>
                                        </div>
                                    </div>

                                    <div class="item form-group">
                                        <label class="col-form-label col-md-3 col-sm-3 label-align required" for="serial_number">
                                            Serial Number <span class="required">*</span>
                                        </label>
                                        <div class="col-md-6 col-sm-6">
                                            <input type="text" class="form-control" id="serial_number" name="serial_number" required="required">
                                            <span class="help-block">Device serial number (must be unique)</span>
                                            <div class="invalid-feedback">Please provide a serial number</div>
                                        </div>
                                    </div>

                                    <div class="item form-group">
                                        <label class="col-form-label col-md-3 col-sm-3 label-align" for="device_type">Device Type</label>
                                        <div class="col-md-6 col-sm-6">
                                            <input type="text" class="form-control" id="device_type" name="device_type" value="FIREGUARD DEVICE">
                                            <span class="help-block">Type of device (default: FIREGUARD DEVICE)</span>
                                        </div>
                                    </div>

                                    <div class="item form-group">
                                        <label class="col-form-label col-md-3 col-sm-3 label-align" for="status">Status</label>
                                        <div class="col-md-6 col-sm-6">
                                            <select class="form-control" id="status" name="status">
                                                <option value="offline" selected>Offline</option>
                                                <option value="online">Online</option>
                                                <option value="faulty">Faulty</option>
                                            </select>
                                            <span class="help-block">Current device status</span>
                                        </div>
                                    </div>

                                    <div class="item form-group">
                                        <label class="col-form-label col-md-3 col-sm-3 label-align" for="barangay_id">Assign to Barangay</label>
                                        <div class="col-md-6 col-sm-6">
                                            <select class="form-control" id="barangay_id" name="barangay_id">
                                                <option value="">-- Select Barangay (Optional) --</option>
                                                <?php foreach ($barangays as $barangay): ?>
                                                    <option value="<?php echo $barangay['id']; ?>">
                                                        <?php echo htmlspecialchars($barangay['barangay_name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <span class="help-block">Link this device to a barangay jurisdiction (optional)</span>
                                        </div>
                                    </div>

                                    <div class="item form-group">
                                        <label class="col-form-label col-md-3 col-sm-3 label-align"></label>
                                        <div class="col-md-6 col-sm-6">
                                            <div class="checkbox">
                                                <label>
                                                    <input type="checkbox" id="is_active" name="is_active" value="1" checked>
                                                    Device is Active
                                                </label>
                                            </div>
                                            <span class="help-block">Uncheck to deactivate this device</span>
                                        </div>
                                    </div>

                                    <div class="ln_solid"></div>
                                    <div class="item form-group">
                                        <div class="col-md-6 col-sm-6 col-md-offset-3 col-sm-offset-3">
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
                                    <input type="hidden" id="update_device_id" name="device_id">

                                    <div class="item form-group">
                                        <label class="col-form-label col-md-3 col-sm-3 label-align required" for="update_device_name">
                                            Device Name <span class="required">*</span>
                                        </label>
                                        <div class="col-md-6 col-sm-6">
                                            <input type="text" class="form-control" id="update_device_name" name="device_name" required="required">
                                            <span class="help-block">Enter a descriptive name for your device.</span>
                                            <div class="invalid-feedback">Please provide a device name</div>
                                        </div>
                                    </div>

                                    <div class="item form-group">
                                        <label class="col-form-label col-md-3 col-sm-3 label-align required" for="update_device_number">
                                            Device Number <span class="required">*</span>
                                        </label>
                                        <div class="col-md-6 col-sm-6">
                                            <input type="text" class="form-control" id="update_device_number" name="device_number" required="required">
                                            <span class="help-block">Unique identifier for this device</span>
                                            <div class="invalid-feedback">Please provide a device number</div>
                                        </div>
                                    </div>

                                    <div class="item form-group">
                                        <label class="col-form-label col-md-3 col-sm-3 label-align required" for="update_serial_number">
                                            Serial Number <span class="required">*</span>
                                        </label>
                                        <div class="col-md-6 col-sm-6">
                                            <input type="text" class="form-control" id="update_serial_number" name="serial_number" required="required">
                                            <span class="help-block">Device serial number (must be unique)</span>
                                            <div class="invalid-feedback">Please provide a serial number</div>
                                        </div>
                                    </div>

                                    <div class="item form-group">
                                        <label class="col-form-label col-md-3 col-sm-3 label-align" for="update_device_type">Device Type</label>
                                        <div class="col-md-6 col-sm-6">
                                            <input type="text" class="form-control" id="update_device_type" name="device_type" value="FIREGUARD DEVICE">
                                            <span class="help-block">Type of device (default: FIREGUARD DEVICE)</span>
                                        </div>
                                    </div>

                                    <div class="item form-group">
                                        <label class="col-form-label col-md-3 col-sm-3 label-align" for="update_status">Status</label>
                                        <div class="col-md-6 col-sm-6">
                                            <select class="form-control" id="update_status" name="status">
                                                <option value="offline">Offline</option>
                                                <option value="online">Online</option>
                                                <option value="faulty">Faulty</option>
                                            </select>
                                            <span class="help-block">Current device status</span>
                                        </div>
                                    </div>

                                    <div class="item form-group">
                                        <label class="col-form-label col-md-3 col-sm-3 label-align" for="update_barangay_id">Assign to Barangay</label>
                                        <div class="col-md-6 col-sm-6">
                                            <select class="form-control" id="update_barangay_id" name="barangay_id">
                                                <option value="">-- Select Barangay (Optional) --</option>
                                                <?php foreach ($barangays as $barangay): ?>
                                                    <option value="<?php echo $barangay['id']; ?>">
                                                        <?php echo htmlspecialchars($barangay['barangay_name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <span class="help-block">Link this device to a barangay jurisdiction (optional)</span>
                                        </div>
                                    </div>

                                    <div class="item form-group">
                                        <label class="col-form-label col-md-3 col-sm-3 label-align"></label>
                                        <div class="col-md-6 col-sm-6">
                                            <div class="checkbox">
                                                <label>
                                                    <input type="checkbox" id="update_is_active" name="is_active" value="1">
                                                    Device is Active
                                                </label>
                                            </div>
                                            <span class="help-block">Uncheck to deactivate this device</span>
                                        </div>
                                    </div>

                                    <div class="ln_solid"></div>
                                    <div class="item form-group">
                                        <div class="col-md-6 col-sm-6 col-md-offset-3 col-sm-offset-3">
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

                const formData = {
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

                const formData = {
                    action: 'update_device',
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

                    $.ajax({
                        url: 'main.php',
                        method: 'POST',
                        dataType: 'json',
                        data: {
                            action: 'toggle_device_status',
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
            if (typeof Swal !== 'undefined' && Swal.fire) {
                const icon = type === 'success' ? 'success' : 'error';
                Swal.fire({
                    icon: icon,
                    title: title,
                    html: message || '',
                    confirmButtonText: 'OK'
                });
                return;
            }
            showLegacyAlert(type, title, message);
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

