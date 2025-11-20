<?php require_once __DIR__ . '/../functions/functions.php'; ?>

<?php
// Handle device assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_device'])) {
    header('Content-Type: application/json');
    try {
        $device_id = $_POST['device_id'] ?? null;
        $building_id = $_POST['building_id'] ?? null;
        if (!$device_id || !$building_id) {
            echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
            exit;
        }
        // Use domain logic to validate ownership and perform assignment
        if (!isset($deviceManager)) { throw new Exception('Device manager unavailable'); }
        $result = $deviceManager->assignDevice($device_id, $building_id);
        echo json_encode($result);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// Handle device removal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_device'])) {
    header('Content-Type: application/json');
    try {
        $device_id = $_POST['device_id'] ?? null;
        if (!$device_id) {
            echo json_encode(['success' => false, 'message' => 'Missing device ID']);
            exit;
        }
        // Use domain logic to validate ownership and perform unassignment
        if (!isset($deviceManager)) { throw new Exception('Device manager unavailable'); }
        $result = $deviceManager->removeDevice($device_id);
        echo json_encode($result);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// Handle create device
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_device'])) {
    header('Content-Type: application/json');
    try {
        $device_name = trim($_POST['device_name'] ?? '');
        $device_number = trim($_POST['device_number'] ?? '');
        $serial_number = trim($_POST['serial_number'] ?? '');
        $device_type = trim($_POST['device_type'] ?? '');
        $status = trim($_POST['status'] ?? 'offline');
        $is_active = isset($_POST['is_active']) && $_POST['is_active'] === '1' ? 1 : 0;
        $building_id = $_POST['building_id'] !== '' ? $_POST['building_id'] : null;

        if ($device_name === '' || $device_number === '' || $serial_number === '') {
            echo json_encode(['success' => false, 'message' => 'Please fill in all required fields']);
            exit;
        }

        // 1) Validate the device exists in admin_devices with matching device_number and serial_number
        $admStmt = $pdo->prepare("SELECT admin_device_id, device_type FROM admin_devices WHERE device_number = ? AND serial_number = ? AND status = 'approved' LIMIT 1");
        $admStmt->execute([$device_number, $serial_number]);
        $adminDevice = $admStmt->fetch(PDO::FETCH_ASSOC);
        if (!$adminDevice) {
            echo json_encode([
                'success' => false,
                'message' => 'Device not found in admin devices or not approved. Please contact administrator.'
            ]);
            exit;
        }

        $admin_device_id = (int)$adminDevice['admin_device_id'];

        // Use admin device_type if none provided
        if ($device_type === '' && !empty($adminDevice['device_type'])) {
            $device_type = $adminDevice['device_type'];
        }

        // 2) Ensure the device is NOT already registered in devices
        $dupStmt = $pdo->prepare("SELECT COUNT(*) AS c FROM devices WHERE serial_number = ? OR device_number = ? OR admin_device_id = ?");
        $dupStmt->execute([$serial_number, $device_number, $admin_device_id]);
        $dup = (int)($dupStmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);
        if ($dup > 0) {
            echo json_encode([
                'success' => false,
                'message' => 'This device is already registered.'
            ]);
            exit;
        }

        // 3) Insert device linking to admin_device_id
        $stmt = $pdo->prepare("INSERT INTO devices (device_name, device_number, serial_number, device_type, status, is_active, building_id, admin_device_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $ok = $stmt->execute([$device_name, $device_number, $serial_number, $device_type ?: 'FIREGUARD DEVICE', $status, $is_active, $building_id, $admin_device_id]);
        echo json_encode(['success' => (bool)$ok]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// Handle update device
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_device'])) {
    header('Content-Type: application/json');
    try {
        $device_id = $_POST['device_id'] ?? null;
        if (!$device_id) {
            echo json_encode(['success' => false, 'message' => 'Missing device ID']);
            exit;
        }
        $device_name = trim($_POST['device_name'] ?? '');
        $device_number = trim($_POST['device_number'] ?? '');
        $serial_number = trim($_POST['serial_number'] ?? '');
        $device_type = trim($_POST['device_type'] ?? '');
        $status = trim($_POST['status'] ?? 'offline');
        $is_active = isset($_POST['is_active']) && $_POST['is_active'] === '1' ? 1 : 0;
        $building_id = $_POST['building_id'] !== '' ? $_POST['building_id'] : null;

        $stmt = $pdo->prepare("UPDATE devices SET device_name = ?, device_number = ?, serial_number = ?, device_type = ?, status = ?, is_active = ?, building_id = ? WHERE device_id = ?");
        $ok = $stmt->execute([$device_name, $device_number, $serial_number, $device_type, $status, $is_active, $building_id, $device_id]);
        echo json_encode(['success' => (bool)$ok]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// Handle delete device
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_device'])) {
    header('Content-Type: application/json');
    try {
        $device_id = $_POST['device_id'] ?? null;
        if (!$device_id) {
            echo json_encode(['success' => false, 'message' => 'Missing device ID']);
            exit;
        }
        $stmt = $pdo->prepare("DELETE FROM devices WHERE device_id = ?");
        $ok = $stmt->execute([$device_id]);
        echo json_encode(['success' => (bool)$ok]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// Realtime validation: check admin_devices existence and duplicates in devices
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['validate_device'])) {
    header('Content-Type: application/json');
    try {
        $device_number = trim($_POST['device_number'] ?? '');
        $serial_number = trim($_POST['serial_number'] ?? '');

        if ($device_number === '' && $serial_number === '') {
            echo json_encode(['ok' => true, 'ready' => false]);
            exit;
        }

        $resp = [
            'ok' => true,
            'in_admin' => false,
            'approved' => false,
            'admin_device_id' => null,
            'device_type' => null,
            'already_in_devices' => false,
            'message' => null,
            'ready' => false
        ];

        // Only validate pair if both provided
        if ($device_number !== '' && $serial_number !== '') {
            $admStmt = $pdo->prepare("SELECT admin_device_id, device_type, status FROM admin_devices WHERE device_number = ? AND serial_number = ? LIMIT 1");
            $admStmt->execute([$device_number, $serial_number]);
            $admin = $admStmt->fetch(PDO::FETCH_ASSOC);
            if ($admin) {
                $resp['in_admin'] = true;
                $resp['approved'] = ($admin['status'] ?? '') === 'approved';
                $resp['admin_device_id'] = (int)$admin['admin_device_id'];
                $resp['device_type'] = $admin['device_type'] ?? null;
                if (!$resp['approved']) {
                    $resp['message'] = 'Device exists but is not approved.';
                }
                // Check duplicates in devices
                $dup = $pdo->prepare("SELECT COUNT(*) AS c FROM devices WHERE serial_number = ? OR device_number = ? OR admin_device_id = ?");
                $dup->execute([$serial_number, $device_number, $resp['admin_device_id']]);
                $resp['already_in_devices'] = ((int)($dup->fetch(PDO::FETCH_ASSOC)['c'] ?? 0)) > 0;
                if ($resp['already_in_devices']) {
                    $resp['message'] = 'Device is already registered.';
                }
                $resp['ready'] = $resp['in_admin'] && $resp['approved'] && !$resp['already_in_devices'];
            } else {
                $resp['message'] = 'No matching device found in admin devices.';
            }
        } else {
            // Partial input: hint about requirement
            $resp['message'] = 'Enter both device number and serial to validate.';
        }

        echo json_encode($resp);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FIREGUARD - Device Management</title>
    <link rel="icon" type="image/png" sizes="32x32" href="fireguard.png?v=1">
    <link rel="icon" type="image/png" sizes="16x16" href="fireguard.png?v=1">
    <link rel="shortcut icon" type="image/png" href="fireguard.png?v=1">
    <link rel="apple-touch-icon" sizes="180x180" href="fireguard.png?v=1">
    <link rel="apple-touch-icon" href="fireguard.png?v=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="../../../vendors/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../../vendors/font-awesome/css/font-awesome.min.css" rel="stylesheet">
    <link href="../../../vendors/nprogress/nprogress.css" rel="stylesheet">
    <link href="../../../vendors/bootstrap-progressbar/css/bootstrap-progressbar-3.3.4.min.css" rel="stylesheet">  
    <link href="../../../vendors/bootstrap-daterangepicker/daterangepicker.css" rel="stylesheet">  
    <link href="../../../build/css/custom.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="../css/style.css">
    
    <!-- Compact Card Styles -->
    <style>
        .modern-card {
            border-radius: 0 !important;
        }
        
        .modern-header {
            position: relative;
            overflow: hidden;
        }
        
        .modern-table tbody tr {
            background: white;
        }
        
        .modern-btn {
            /* Simple button styling */
        }
        
        .modern-badge {
            /* Simple badge styling */
        }
        
        .building-icon {
            /* Simple icon styling */
        }
        
        .modern-alert {
            /* Simple alert styling */
        }
        
        /* Custom scrollbar for table */
        .table-responsive::-webkit-scrollbar {
            height: 4px;
        }
        
        .table-responsive::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 0;
        }
        
        .table-responsive::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
            border-radius: 0;
        }
        
        /* Simple Modal Styles */
        .status-badge {
            display: inline-block;
            padding: 0.2rem 0.4rem;
            font-size: 0.7rem;
            font-weight: 600;
            border-radius: 0;
        }
        
        .status-badge.bg-success {
            background-color: #28a745 !important;
            color: white;
        }
        
        .status-badge.bg-secondary {
            background-color: #6c757d !important;
            color: white;
        }
        
        .status-badge.bg-danger {
            background-color: #dc3545 !important;
            color: white;
        }
        
        .form-select:focus {
            border-color: #ff6b35;
            box-shadow: 0 0 0 0.15rem rgba(255, 107, 53, 0.25);
        }
        
        /* Table hover effects */
        .modern-table tbody tr:hover {
            background-color: #f8f9fa !important;
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: all 0.2s ease;
        }
        
        /* Device icon styling */
        .device-icon {
            transition: all 0.3s ease;
        }
        
        .modern-table tbody tr:hover .device-icon {
            transform: scale(1.1);
            background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%) !important;
        }
        
        .modern-table tbody tr:hover .device-icon i {
            color: white !important;
        }
        
        /* Button hover effects */
        .assign-btn:hover, .remove-device-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transition: all 0.2s ease;
        }

        /* Yellow theme */
        :root {
            --fg-yellow: #ffc107;
            --fg-yellow-600: #e0a800;
            --fg-yellow-700: #d39e00;
            --fg-yellow-100: #fff8e1;
            --fg-yellow-200: #ffecb3;
        }

        .btn-yellow {
            background: var(--fg-yellow);
            border: none;
            color: #000;
        }
        .btn-yellow:hover {
            background: var(--fg-yellow-600);
            color: #000;
        }
        .btn-outline-yellow {
            background: transparent;
            border: 1px solid var(--fg-yellow);
            color: var(--fg-yellow);
        }
        .btn-outline-yellow:hover {
            background: var(--fg-yellow);
            color: #000;
        }

        .card.yellow-accent {
            border: 1px solid #f1f1f1;
            transition: box-shadow .15s ease;
        }
        .card.yellow-accent:hover {
            box-shadow: 0 4px 12px rgba(255,193,7,.3);
        }
        .card-title-accent {
            color: var(--fg-yellow);
            display: flex;
            align-items: center;
            gap: .5rem;
            margin: 0;
        }
        .card-title-accent i { color: var(--fg-yellow); }

        .section-header {
            border-left: 3px solid var(--fg-yellow);
            padding-left: .5rem;
        }

        /* Status badge subtle style */
        .status-pill {
            padding: .2rem .4rem;
            border-radius: 0;
            font-weight: 600;
            font-size: .7rem;
        }

        /* Device card spacing */
        .device-card .card-body { padding: 0.75rem; }
        
        /* Assignment Stats Tooltip Styles */
        .assignment-tooltip {
            position: relative;
            cursor: pointer;
            display: inline-block;
        }
        
        .assignment-tooltip .tooltip-content {
            visibility: hidden;
            opacity: 0;
            position: absolute;
            z-index: 1000;
            bottom: 125%;
            left: 50%;
            transform: translateX(-50%);
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white;
            text-align: left;
            border-radius: 12px;
            padding: 0;
            width: 320px;
            max-width: 90vw;
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.1);
            transition: all 0.3s ease;
            font-size: 0.85rem;
        }
        
        .assignment-tooltip .tooltip-content::after {
            content: "";
            position: absolute;
            top: 100%;
            left: 50%;
            margin-left: -8px;
            border-width: 8px;
            border-style: solid;
            border-color: #2c3e50 transparent transparent transparent;
        }
        
        .assignment-tooltip:hover .tooltip-content {
            visibility: visible;
            opacity: 1;
            transform: translateX(-50%) translateY(-5px);
        }
        
        .tooltip-header {
            background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
            color: white;
            padding: 12px 16px;
            border-radius: 12px 12px 0 0;
            font-weight: 600;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .tooltip-body {
            padding: 16px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 16px;
        }
        
        .stat-item {
            background: rgba(255,255,255,0.1);
            padding: 8px 12px;
            border-radius: 8px;
            text-align: center;
        }
        
        .stat-number {
            font-size: 1.2rem;
            font-weight: 700;
            color: #ff6b35;
            display: block;
        }
        
        .stat-label {
            font-size: 0.75rem;
            color: #bdc3c7;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .history-section {
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        
        .history-title {
            font-size: 0.8rem;
            font-weight: 600;
            color: #ecf0f1;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .history-item {
            background: rgba(255,255,255,0.05);
            padding: 8px 10px;
            border-radius: 6px;
            margin-bottom: 6px;
            font-size: 0.75rem;
            border-left: 3px solid #ff6b35;
        }
        
        .history-item:last-child {
            margin-bottom: 0;
        }
        
        .history-device {
            font-weight: 600;
            color: #ecf0f1;
        }
        
        .history-building {
            color: #bdc3c7;
            font-size: 0.7rem;
        }
        
        .history-status {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.65rem;
            font-weight: 600;
            margin-left: 6px;
        }
        
        .history-status.online {
            background: #27ae60;
            color: white;
        }
        
        .history-status.offline {
            background: #95a5a6;
            color: white;
        }
        
        .history-status.error {
            background: #e74c3c;
            color: white;
        }
        
        .loading-spinner {
            display: inline-block;
            width: 12px;
            height: 12px;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: #ff6b35;
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Fire Icon Tooltip Styles */
        .fire-tooltip {
            position: relative;
            cursor: pointer;
            display: inline-block;
        }
        
        .fire-tooltip .fire-tooltip-content {
            visibility: hidden;
            opacity: 0;
            position: absolute;
            z-index: 1000;
            bottom: 125%;
            left: 50%;
            transform: translateX(-50%);
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
            color: white;
            text-align: left;
            border-radius: 16px;
            padding: 0;
            width: 400px;
            max-width: 95vw;
            box-shadow: 0 12px 35px rgba(0,0,0,0.4);
            backdrop-filter: blur(15px);
            border: 2px solid #ff4444;
            transition: all 0.4s ease;
            font-size: 0.85rem;
        }
        
        .fire-tooltip .fire-tooltip-content::after {
            content: "";
            position: absolute;
            top: 100%;
            left: 50%;
            margin-left: -10px;
            border-width: 10px;
            border-style: solid;
            border-color: #ff4444 transparent transparent transparent;
        }
        
        .fire-tooltip:hover .fire-tooltip-content {
            visibility: visible;
            opacity: 1;
            transform: translateX(-50%) translateY(-8px);
        }
        
        .fire-tooltip-header {
            background: linear-gradient(135deg, #ff4444 0%, #ff6666 100%);
            color: white;
            padding: 16px 20px;
            border-radius: 16px 16px 0 0;
            font-weight: 700;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 2px solid rgba(255,255,255,0.1);
        }
        
        .fire-tooltip-body {
            padding: 20px;
            max-height: 500px;
            overflow-y: auto;
        }
        
        .fire-stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .fire-stat-item {
            background: rgba(255,68,68,0.1);
            padding: 12px 15px;
            border-radius: 10px;
            text-align: center;
            border: 1px solid rgba(255,68,68,0.2);
            transition: all 0.3s ease;
        }
        
        .fire-stat-item:hover {
            background: rgba(255,68,68,0.15);
            transform: translateY(-2px);
        }
        
        .fire-stat-number {
            font-size: 1.4rem;
            font-weight: 800;
            color: #ff4444;
            display: block;
            text-shadow: 0 0 10px rgba(255,68,68,0.3);
        }
        
        .fire-stat-label {
            font-size: 0.75rem;
            color: #ffcccc;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-top: 4px;
        }
        
        .fire-section {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid rgba(255,68,68,0.2);
        }
        
        .fire-section-title {
            font-size: 0.9rem;
            font-weight: 700;
            color: #ff6666;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .fire-incident-item {
            background: rgba(255,68,68,0.05);
            padding: 10px 12px;
            border-radius: 8px;
            margin-bottom: 8px;
            font-size: 0.8rem;
            border-left: 4px solid #ff4444;
            transition: all 0.3s ease;
        }
        
        .fire-incident-item:hover {
            background: rgba(255,68,68,0.1);
            transform: translateX(5px);
        }
        
        .fire-incident-item:last-child {
            margin-bottom: 0;
        }
        
        .fire-incident-device {
            font-weight: 700;
            color: #ffcccc;
            font-size: 0.85rem;
        }
        
        .fire-incident-details {
            color: #cccccc;
            font-size: 0.75rem;
            margin-top: 4px;
        }
        
        .fire-incident-status {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 700;
            margin-left: 8px;
        }
        
        .fire-incident-status.high-risk {
            background: #ff4444;
            color: white;
            box-shadow: 0 0 10px rgba(255,68,68,0.5);
        }
        
        .fire-incident-status.medium-risk {
            background: #ff8800;
            color: white;
        }
        
        .fire-incident-status.low-risk {
            background: #44aa44;
            color: white;
        }
        
        .fire-calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 4px;
            margin-top: 10px;
        }
        
        .fire-calendar-day {
            background: rgba(255,255,255,0.05);
            padding: 8px 4px;
            border-radius: 6px;
            text-align: center;
            font-size: 0.7rem;
            transition: all 0.3s ease;
        }
        
        .fire-calendar-day.has-incidents {
            background: rgba(255,68,68,0.2);
            border: 1px solid #ff4444;
        }
        
        .fire-calendar-day.has-incidents:hover {
            background: rgba(255,68,68,0.3);
            transform: scale(1.1);
        }
        
        .fire-calendar-day-count {
            font-weight: 700;
            color: #ff4444;
            font-size: 0.8rem;
        }
        
        .fire-loading-spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 3px solid rgba(255,68,68,0.3);
            border-radius: 50%;
            border-top-color: #ff4444;
            animation: spin 1s ease-in-out infinite;
        }
        
        .fire-tooltip-body::-webkit-scrollbar {
            width: 8px;
        }
        
        .fire-tooltip-body::-webkit-scrollbar-track {
            background: rgba(255,255,255,0.1);
            border-radius: 4px;
        }
        
        .fire-tooltip-body::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #ff4444 0%, #ff6666 100%);
            border-radius: 4px;
        }
        
        .fire-tooltip-body::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, #ff6666 0%, #ff8888 100%);
        }
        
        /* Removed hover effects for compact design */
        
        /* Modal scrollbar styling */
        .modal-body::-webkit-scrollbar {
            width: 6px;
        }
        
        .modal-body::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }
        
        .modal-body::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #ffc107 0%, #ffb300 100%);
            border-radius: 3px;
        }
        
        .modal-body::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, #e0a800 0%, #d39e00 100%);
        }
        
        /* Modal compact styling */
        .modal-lg {
            max-width: 600px;
        }
        
        .modal-content {
            border-radius: 0 !important;
        }
        
        .modal-header {
            border-radius: 0 !important;
        }
        
        .modal-footer {
            border-radius: 0 !important;
        }
        
        /* Compact form elements */
        .form-label.small {
            font-size: 0.75rem;
            font-weight: 500;
            margin-bottom: 0.25rem;
        }
        
        .form-select {
            font-size: 0.9rem;
            padding: 0.5rem 0.75rem;
        }
        
        /* Status badge compact */
        .status-badge {
            font-size: 0.7rem;
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .card-body {
                padding: 1rem !important;
            }
            
            .btn-sm {
                padding: 0.4rem 0.6rem !important;
                font-size: 0.8rem;
            }
            
            .modal-lg {
                max-width: 95%;
                margin: 0.5rem;
            }
            
            .modal-body {
                max-height: 50vh !important;
            }
        }
        
    </style>
</head>
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
                <div class="row">
                   
        <!-- SweetAlert Notifications -->
        <?php if (isset($_SESSION['error'])): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        title: 'Error!',
                        text: '<?php echo addslashes($_SESSION['error']); ?>',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                });
            </script>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['success'])): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        title: 'Success!',
                        text: '<?php echo addslashes($_SESSION['success']); ?>',
                        icon: 'success',
                        confirmButtonText: 'OK'
                    });
                });
            </script>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <!-- Device Management Section -->
        <div class="row mx-0">
            <div class="col-12 px-0">
                <div class="card mb-1 modern-card" style="border: none; box-shadow: none; border-radius: 0; background: #ffffff; margin-top: 0; min-height: 500px;">
                    <div class="card-body" style="background: #ffffff; padding: 0.5rem; min-height: 500px">
                     
                        <!-- Device Statistics -->
                        <div class="row mb-2">
                            <div class="col-md-3 mb-2">
                                <div class="card h-100" style="border: 1px solid #e9ecef; border-radius: 0; box-shadow: none;">
                                    <div class="card-body text-center" style="padding: 0.5rem;">
                                        <div class="mb-1">
                                            <i class="bi bi-router-fill" style="font-size: 1.5rem; color: #ffc107;"></i>
                                        </div>
                                        <h4 class="card-title mb-1" style="font-size: 1.25rem; font-weight: 700; color: #333;"><?php echo count($devices ?? []); ?></h4>
                                        <p class="card-text mb-0" style="font-size: 0.7rem; color: #6c757d; font-weight: 500;">TOTAL DEVICES</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-2">
                                <div class="card h-100" style="border: 1px solid #e9ecef; border-radius: 0; box-shadow: none;">
                                    <div class="card-body text-center" style="padding: 0.5rem;">
                                        <div class="mb-1">
                                            <i class="bi bi-wifi" style="font-size: 1.5rem; color: #28a745;"></i>
                                        </div>
                                        <h4 class="card-title mb-1" style="font-size: 1.25rem; font-weight: 700; color: #333;"><?php echo count(array_filter($devices ?? [], function($d) { return $d['status'] === 'online'; })); ?></h4>
                                        <p class="card-text mb-0" style="font-size: 0.7rem; color: #6c757d; font-weight: 500;">ONLINE</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-2">
                                <div class="card h-100" style="border: 1px solid #e9ecef; border-radius: 0; box-shadow: none;">
                                    <div class="card-body text-center" style="padding: 0.5rem;">
                                        <div class="mb-1">
                                            <i class="bi bi-wifi-off" style="font-size: 1.5rem; color: #6c757d;"></i>
                                        </div>
                                        <h4 class="card-title mb-1" style="font-size: 1.25rem; font-weight: 700; color: #333;"><?php echo count(array_filter($devices ?? [], function($d) { return $d['status'] === 'offline'; })); ?></h4>
                                        <p class="card-text mb-0" style="font-size: 0.7rem; color: #6c757d; font-weight: 500;">OFFLINE</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-2">
                                <div class="card h-100" style="border: 1px solid #e9ecef; border-radius: 0; box-shadow: none;">
                                    <div class="card-body text-center" style="padding: 0.5rem;">
                                        <div class="mb-1">
                                            <i class="bi bi-exclamation-triangle-fill" style="font-size: 1.5rem; color: #ffc107;"></i>
                                        </div>
                                        <h4 class="card-title mb-1" style="font-size: 1.25rem; font-weight: 700; color: #333;"><?php echo count(array_filter($devices ?? [], function($d) { return empty($d['building_id']); })); ?></h4>
                                        <p class="card-text mb-0" style="font-size: 0.7rem; color: #6c757d; font-weight: 500;">UNASSIGNED</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Filters and Controls -->
                        <div class="card mb-2" style="border: 1px solid #e9ecef; border-radius: 0; box-shadow: none;">
                            <div class="card-body">
                                <div class="d-flex flex-wrap gap-2 align-items-center">
                                    <div class="input-group input-group-sm" style="width: 240px;">
                                        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                                        <input type="text" class="form-control" placeholder="Search devices..." id="deviceSearchInput">
                                    </div>
                                    <select class="form-select form-select-sm" id="statusFilter" style="width: 150px;">
                                        <option value="">All Status</option>
                                        <option value="online">Online</option>
                                        <option value="offline">Offline</option>
                                        <option value="error">Error</option>
                                    </select>
                                    <select class="form-select form-select-sm" id="buildingFilter" style="width: 200px;">
                                        <option value="">All Buildings</option>
                                        <?php foreach ($buildings as $building): ?>
                                            <option value="<?php echo $building['id']; ?>"><?php echo htmlspecialchars($building['building_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="clearFiltersBtn">Clear</button>
                                    <button type="button" class="btn btn-sm btn-yellow" id="addDeviceBtn">
                                        <i class="bi bi-plus-lg me-1"></i> Add Device
                                    </button>
                                </div>
                            </div>
                        </div>
                      
                        <!-- Devices Grid -->
                        <div class="row g-2 mt-2" id="devicesGrid">
                            <?php if (empty($devices)): ?>
                                <div class="col-12">
                                    <div class="card" style="border: 1px solid #e9ecef; border-radius: 0; box-shadow: none;">
                                        <div class="card-body text-center" style="padding: 1rem;">
                                            <i class="bi bi-info-circle" style="font-size: 2rem; color: #6c757d; margin-bottom: 0.5rem;"></i>
                                            <h6 class="card-title mb-1" style="color: #333; font-weight: 600; font-size: 0.9rem;">No devices found</h6>
                                            <p class="card-text text-muted mb-2" style="font-size: 0.8rem;">Start by adding your first device to begin monitoring.</p>
                                            <button type="button" class="btn btn-sm btn-primary" id="addDeviceBtnEmpty" style="background: #ffc107; border: none; color: #000; font-size: 0.8rem;">
                                                <i class="bi bi-plus-lg me-1"></i>Add Device
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <?php foreach ($devices as $device): ?>
                                    <div class="col-12 col-sm-6 col-lg-4">
                                        <div class="card h-100" style="border: 1px solid #e9ecef; border-radius: 0; box-shadow: none;">
                                            <div class="card-body d-flex flex-column" style="padding: 0.5rem;">
                                                <!-- Device Header -->
                                                <div class="d-flex align-items-center justify-content-between mb-1">
                                                    <div class="d-flex align-items-center">
                                                        <div class="me-2" style="width: 30px; height: 30px; background: linear-gradient(135deg, #ffc107 0%, #ffb300 100%); border-radius: 0; display: flex; align-items: center; justify-content: center;">
                                                            <i class="bi bi-router text-white" style="font-size: 0.9rem;"></i>
                                                        </div>
                                                        <div>
                                                            <h6 class="mb-0 fw-semibold" style="color: #333; font-size: 0.8rem;"><?php echo htmlspecialchars($device['device_name']); ?></h6>
                                                            <small class="text-muted" style="font-size: 0.7rem;"><?php echo htmlspecialchars($device['device_type'] ?: 'FIREGUARD DEVICE'); ?></small>
                                                        </div>
                                                    </div>
                                                    <span class="badge bg-<?php echo $device['status'] === 'online' ? 'success' : ($device['status'] === 'offline' ? 'secondary' : 'danger'); ?>" style="font-size: 0.6rem; padding: 0.2rem 0.4rem; border-radius: 0;">
                                                        <i class="bi bi-circle-fill me-1" style="font-size: 0.5rem;"></i><?php echo ucfirst($device['status']); ?>
                                                    </span>
                                                </div>
                                                
                                                <!-- Device Details -->
                                                <div class="mb-2">
                                                    <div class="row g-2">
                                                        <div class="col-6">
                                                            <small class="text-muted d-block">Serial Number</small>
                                                            <code class="text-dark" style="font-size: 0.75rem; background: #f8f9fa; padding: 0.2rem 0.4rem; border-radius: 4px;"><?php echo htmlspecialchars($device['serial_number']); ?></code>
                                                        </div>
                                                        <div class="col-6">
                                                            <small class="text-muted d-block">Status</small>
                                                            <?php if ((int)$device['is_active'] === 1): ?>
                                                                <span class="badge bg-success" style="font-size: 0.7rem; padding: 0.3rem 0.6rem;"><i class="bi bi-check2-circle me-1"></i>Active</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-secondary" style="font-size: 0.7rem; padding: 0.3rem 0.6rem;"><i class="bi bi-slash-circle me-1"></i>Inactive</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <!-- Location Info -->
                                                <div class="mb-2">
                                                    <small class="text-muted d-block mb-1">Location</small>
                                                    <?php if (!empty($device['building_id'])): ?>
                                                        <div class="d-flex align-items-center text-success">
                                                            <i class="bi bi-building me-2" style="font-size: 0.9rem;"></i>
                                                            <span class="fw-medium"><?php echo htmlspecialchars($device['building_name'] ?? 'Assigned'); ?></span>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="d-flex align-items-center text-warning">
                                                            <i class="bi bi-exclamation-circle me-2" style="font-size: 0.9rem;"></i>
                                                            <span class="fw-medium">Unassigned</span>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <!-- Action Buttons -->
                                                <div class="mt-auto">
                                                    <div class="d-flex gap-2">
                                                        <?php if (empty($device['building_id'])): ?>
                                                            <button type="button" class="btn btn-sm btn-primary assign-btn flex-fill" style="background: #ffc107; border: none; border-radius: 6px; padding: 0.5rem; color: #000;"
                                                                data-device-id="<?php echo $device['device_id']; ?>"
                                                                data-device-name="<?php echo htmlspecialchars($device['device_name']); ?>"
                                                                data-serial="<?php echo htmlspecialchars($device['serial_number']); ?>"
                                                                data-status="<?php echo htmlspecialchars($device['status']); ?>"
                                                                data-location="Unassigned"
                                                                title="Assign Device">
                                                                <i class="bi bi-plus-circle me-1"></i>Assign
                                                            </button>
                                                        <?php else: ?>
                                                            <button type="button" class="btn btn-sm btn-outline-danger remove-device-btn flex-fill" style="border-radius: 6px; padding: 0.5rem;"
                                                                data-device-id="<?php echo $device['device_id']; ?>"
                                                                data-device-name="<?php echo htmlspecialchars($device['device_name']); ?>"
                                                                title="Unassign Device">
                                                                <i class="bi bi-x-circle me-1"></i>Unassign
                                                            </button>
                                                        <?php endif; ?>
                                                        <button type="button" class="btn btn-sm btn-outline-secondary update-device-btn" style="border-radius: 6px; padding: 0.5rem;"
                                                            data-device-id="<?php echo $device['device_id']; ?>"
                                                            data-device-name="<?php echo htmlspecialchars($device['device_name']); ?>"
                                                            data-device-number="<?php echo htmlspecialchars($device['device_number'] ?? ''); ?>"
                                                            data-serial="<?php echo htmlspecialchars($device['serial_number']); ?>"
                                                            data-device-type="<?php echo htmlspecialchars($device['device_type'] ?: ''); ?>"
                                                            data-status="<?php echo htmlspecialchars($device['status']); ?>"
                                                            data-is-active="<?php echo (int)$device['is_active']; ?>"
                                                            data-building-id="<?php echo $device['building_id'] ?? ''; ?>"
                                                            title="Update Device">
                                                            <i class="bi bi-pencil"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-outline-danger delete-device-btn" style="border-radius: 6px; padding: 0.5rem;"
                                                            data-device-id="<?php echo $device['device_id']; ?>"
                                                            data-device-name="<?php echo htmlspecialchars($device['device_name']); ?>"
                                                            title="Delete Device">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <div class="text-muted small">
                                Showing <?php echo (($page - 1) * $per_page) + 1; ?> to <?php echo min($page * $per_page, $total_devices); ?> of <?php echo $total_devices; ?> devices
                            </div>
                            <nav aria-label="Device pagination">
                                <ul class="pagination pagination-sm mb-0">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page - 1; ?>" aria-label="Previous">
                                                <span aria-hidden="true">&laquo;</span>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php
                                    $start_page = max(1, $page - 2);
                                    $end_page = min($total_pages, $page + 2);
                                    
                                    if ($start_page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=1">1</a>
                                        </li>
                                        <?php if ($start_page > 2): ?>
                                            <li class="page-item disabled">
                                                <span class="page-link">...</span>
                                            </li>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($end_page < $total_pages): ?>
                                        <?php if ($end_page < $total_pages - 1): ?>
                                            <li class="page-item disabled">
                                                <span class="page-link">...</span>
                                            </li>
                                        <?php endif; ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $total_pages; ?>"><?php echo $total_pages; ?></a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page + 1; ?>" aria-label="Next">
                                                <span aria-hidden="true">&raquo;</span>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Assign Device Modal -->
        <div class="modal fade" id="assignDeviceModal" tabindex="-1" aria-labelledby="assignDeviceModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content" style="border-radius: 0; border: none; box-shadow: none; max-height: 90vh;">
                    <form id="assignDeviceForm">
                        <div class="modal-header" style="background: #ffc107; border: none; border-radius: 0; padding: 1rem;">
                            <h5 class="modal-title text-dark mb-0 d-flex align-items-center" id="assignDeviceModalLabel">
                                <i class="bi bi-cpu me-2"></i>Assign Device
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="filter: brightness(0);"></button>
                        </div>
                        
                        <div class="modal-body" style="padding: 0; max-height: 60vh; overflow-y: auto;">
                            <div style="padding: 1.5rem;">
                                <input type="hidden" name="device_id" id="assignDeviceId">
                                
                                <!-- Device Info - Compact -->
                                <div class="mb-3">
                                    <div class="row g-2">
                                        <div class="col-md-6">
                                            <label class="form-label small text-muted mb-1">Device Name</label>
                                            <div class="fw-semibold text-dark" id="assignDeviceName">-</div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small text-muted mb-1">Status</label>
                                            <div id="assignDeviceStatus" class="status-badge">-</div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small text-muted mb-1">Serial Number</label>
                                            <code class="text-dark small" style="background: #f8f9fa; padding: 0.2rem 0.4rem; border-radius: 4px;" id="assignDeviceSerial">-</code>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small text-muted mb-1">Current Location</label>
                                            <div id="assignDeviceLocation" class="fw-semibold small">-</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Building Selection -->
                                <div class="mb-3">
                                    <label class="form-label fw-semibold mb-2">Select Building</label>
                                    <select class="form-select" id="building_id" name="building_id" required style="border-radius: 0;">
                                        <option value="">Choose a building...</option>
                                        <?php foreach ($buildings as $building): ?>
                                            <option value="<?php echo $building['id']; ?>" 
                                                    data-type="<?php echo htmlspecialchars($building['building_type']); ?>"
                                                    data-address="<?php echo htmlspecialchars($building['address']); ?>"
                                                    data-devices="<?php echo count(array_filter($devices, function($d) use ($building) { return $d['building_id'] == $building['id']; })); ?>">
                                                <?php echo htmlspecialchars($building['building_name']); ?> 
                                                (<?php echo htmlspecialchars($building['building_type']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <!-- Building Preview - Compact -->
                                <div id="buildingDetails" class="mb-3" style="display: none;">
                                    <div class="card" style="border: 1px solid #e9ecef; border-radius: 0;">
                                        <div class="card-body" style="padding: 1rem;">
                                            <h6 class="text-success mb-2 d-flex align-items-center">
                                                <i class="bi bi-building me-2"></i>Building Details
                                            </h6>
                                            <div class="row g-2">
                                                <div class="col-4">
                                                    <small class="text-muted d-block">Type</small>
                                                    <div class="fw-semibold small" id="buildingType">-</div>
                                                </div>
                                                <div class="col-4">
                                                    <small class="text-muted d-block">Devices</small>
                                                    <div class="fw-semibold small" id="buildingDeviceCount">-</div>
                                                </div>
                                                <div class="col-4">
                                                    <small class="text-muted d-block">Address</small>
                                                    <div class="fw-semibold small text-truncate" id="buildingAddress">-</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Assignment Summary - Compact -->
                                <div id="assignmentSummary" style="display: none;">
                                    <div class="alert alert-success border-0 mb-0" style="border-radius: 0; background: #f8fff9;">
                                        <div class="d-flex align-items-center mb-2">
                                            <i class="bi bi-clipboard-check me-2"></i>
                                            <strong class="small">Assignment Summary</strong>
                                        </div>
                                        <div class="row g-2">
                                            <div class="col-6">
                                                <small class="text-muted d-block">Device</small>
                                                <div class="fw-semibold small" id="summaryDevice">-</div>
                                            </div>
                                            <div class="col-6">
                                                <small class="text-muted d-block">Building</small>
                                                <div class="fw-semibold small" id="summaryBuilding">-</div>
                                            </div>
                                        </div>
                                        <small class="text-muted mt-2 d-block">
                                            <i class="bi bi-info-circle me-1"></i>
                                            Device will be assigned for monitoring and alert management.
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="modal-footer" style="border: none; padding: 1rem 1.5rem; background: #f8f9fa; border-radius: 0;">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" style="border-radius: 0;">
                                Cancel
                            </button>
                            <button type="submit" class="btn btn-primary" id="assignSubmitBtn" disabled style="background: #ffc107; border: none; border-radius: 0; color: #000;">
                                <i class="bi bi-check me-1"></i>Assign Device
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Add Device Modal -->
        <div class="modal fade" id="addDeviceModal" tabindex="-1" aria-labelledby="addDeviceModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content" style="border-radius: 0; border: none; box-shadow: none; max-height: 90vh;">
                    <form id="addDeviceForm">
                        <input type="hidden" name="device_id" id="editDeviceId" value="">
                        <div class="modal-header" style="background: #ffc107; border: none; border-radius: 0; padding: 1rem;">
                            <h5 class="modal-title text-dark mb-0 d-flex align-items-center" id="addDeviceModalLabel">
                                <i class="bi bi-plus-circle me-2"></i>Add New Device
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="filter: brightness(0);"></button>
                        </div>
                        <div class="modal-body" style="padding: 0; max-height: 60vh; overflow-y: auto;">
                            <div style="padding: 1.5rem;">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label small">Device Name</label>
                                        <input type="text" name="device_name" class="form-control" required style="border-radius: 0;">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small">Device Number</label>
                                        <input type="text" name="device_number" class="form-control" required id="device_number_input" style="border-radius: 0;">
                                        <div class="form-text small" id="device_number_help"></div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small">Serial Number</label>
                                        <input type="text" name="serial_number" class="form-control" required id="serial_number_input" style="border-radius: 0;">
                                        <div class="form-text small" id="serial_number_help"></div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small">Device Type</label>
                                        <input type="text" name="device_type" class="form-control" placeholder="FIREGUARD DEVICE" id="device_type_input" style="border-radius: 0;">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small">Status</label>
                                        <select name="status" class="form-select" style="border-radius: 0;">
                                            <option value="offline" selected>Offline</option>
                                            <option value="online">Online</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small">Assign to Building</label>
                                        <select class="form-select" name="building_id" style="border-radius: 0;">
                                            <option value="">None</option>
                                            <?php foreach ($buildings as $building): ?>
                                                <option value="<?php echo $building['id']; ?>"><?php echo htmlspecialchars($building['building_name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="is_active" id="isActiveChk" checked>
                                            <label class="form-check-label small" for="isActiveChk">
                                                Active Device
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer" style="border: none; padding: 1rem 1.5rem; background: #f8f9fa; border-radius: 0;">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" style="border-radius: 0;">Cancel</button>
                            <button type="submit" class="btn btn-primary" id="addDeviceSubmitBtn" style="background: #ffc107; border: none; border-radius: 0; color: #000;">
                                <i class="bi bi-check me-1"></i> Save Device
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
    <?php include('../../components/footer.php'); ?>
    
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <script>
            // Initialize modals
            var assignDeviceModal = new bootstrap.Modal(document.getElementById('assignDeviceModal'));
            var addDeviceModal = new bootstrap.Modal(document.getElementById('addDeviceModal'));
            
            // Handle assign device modal
            document.querySelectorAll('.assign-btn').forEach(function(button) {
                button.addEventListener('click', function() {
                    const deviceId = this.getAttribute('data-device-id');
                    const deviceName = this.getAttribute('data-device-name');
                    const serial = this.getAttribute('data-serial') || '';
                    const status = this.getAttribute('data-status') || '';
                    const locationText = this.getAttribute('data-location') || '';
                    
                    // Populate modal with device information
                    document.getElementById('assignDeviceId').value = deviceId;
                    document.getElementById('assignDeviceName').textContent = deviceName;
                    document.getElementById('assignDeviceSerial').textContent = serial;
                    
                    // Handle status badge styling
                    const originalBadge = this.closest('.card').querySelector('.badge');
                    const statusBadge = document.getElementById('assignDeviceStatus');
                    statusBadge.className = 'status-badge';
                    
                    if (originalBadge.classList.contains('bg-success')) {
                        statusBadge.classList.add('bg-success');
                        statusBadge.textContent = 'Online';
                    } else if (originalBadge.classList.contains('bg-secondary')) {
                        statusBadge.classList.add('bg-secondary');
                        statusBadge.textContent = 'Offline';
                    } else if (originalBadge.classList.contains('bg-danger')) {
                        statusBadge.classList.add('bg-danger');
                        statusBadge.textContent = 'Error';
                    }
                    
                    // Handle location display
                    const locationElement = document.getElementById('assignDeviceLocation');
                    if (locationText && locationText.includes('Unassigned')) {
                        locationElement.innerHTML = '<i class="bi bi-exclamation-circle me-1"></i> Unassigned';
                        locationElement.style.color = '#ffc107';
                    } else {
                        locationElement.innerHTML = '<i class="bi bi-building me-1"></i> ' + (locationText || 'Unknown');
                        locationElement.style.color = '#28a745';
                    }
                    
                    // Reset building selection and hide details
                    document.getElementById('building_id').value = '';
                    document.getElementById('buildingDetails').style.display = 'none';
                    document.getElementById('assignmentSummary').style.display = 'none';
                    
                    // Disable submit button initially
                    document.getElementById('assignSubmitBtn').disabled = true;
                    
                    assignDeviceModal.show();
                });
            });
            
            // Handle building selection change
            const buildingSelect = document.getElementById('building_id');
            if (buildingSelect) {
                buildingSelect.addEventListener('change', function() {
                    const selectedOption = this.options[this.selectedIndex];
                    const buildingDetails = document.getElementById('buildingDetails');
                    const assignmentSummary = document.getElementById('assignmentSummary');
                    
                    if (this.value) {
                        // Show building details
                        const buildingType = document.getElementById('buildingType');
                        const buildingAddress = document.getElementById('buildingAddress');
                        const buildingDeviceCount = document.getElementById('buildingDeviceCount');
                        
                        if (buildingType) buildingType.textContent = selectedOption.getAttribute('data-type') || '-';
                        if (buildingAddress) buildingAddress.textContent = selectedOption.getAttribute('data-address') || '-';
                        if (buildingDeviceCount) buildingDeviceCount.textContent = (selectedOption.getAttribute('data-devices') || '0') + ' devices';
                        
                        if (buildingDetails) buildingDetails.style.display = 'block';
                        
                        // Show assignment summary
                        const summaryDevice = document.getElementById('summaryDevice');
                        const summaryBuilding = document.getElementById('summaryBuilding');
                        const assignDeviceName = document.getElementById('assignDeviceName');
                        
                        if (summaryDevice && assignDeviceName) summaryDevice.textContent = assignDeviceName.textContent || '-';
                        if (summaryBuilding) summaryBuilding.textContent = selectedOption.text || '-';
                        
                        if (assignmentSummary) assignmentSummary.style.display = 'block';
                        
                        // Enable submit button
                        const submitBtn = document.getElementById('assignSubmitBtn');
                        if (submitBtn) submitBtn.disabled = false;
                    } else {
                        // Hide details and summary
                        if (buildingDetails) buildingDetails.style.display = 'none';
                        if (assignmentSummary) assignmentSummary.style.display = 'none';
                        
                        // Disable submit button
                        const submitBtn = document.getElementById('assignSubmitBtn');
                        if (submitBtn) submitBtn.disabled = true;
                    }
                });
            }
            
            // Handle assign device form submission
            const assignDeviceForm = document.getElementById('assignDeviceForm');
            if (assignDeviceForm) {
                assignDeviceForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const deviceNameElement = document.getElementById('assignDeviceName');
                    const buildingSelect = document.getElementById('building_id');
                    
                    if (!deviceNameElement || !buildingSelect) {
                        console.error('Required form elements not found');
                        return;
                    }
                    
                    const deviceName = deviceNameElement.textContent;
                    const buildingName = buildingSelect.options[buildingSelect.selectedIndex]?.text || '';
                    
                    if (!buildingSelect.value) {
                        Swal.fire({
                            title: 'Validation Error',
                            text: 'Please select a building to assign the device to.',
                            icon: 'warning',
                            confirmButtonText: 'OK'
                        });
                        return;
                    }
                    
                    // Show enhanced confirmation dialog
                    Swal.fire({
                        title: '<i class="bi bi-link-45deg text-primary"></i> Confirm Device Assignment',
                        html: `
                            <div class="text-start">
                                <div class="alert alert-info">
                                    <p><strong>Device:</strong> ${deviceName}</p>
                                    <p><strong>Building:</strong> ${buildingName}</p>
                                    <p><strong>Action:</strong> Assign device to building for monitoring</p>
                                </div>
                                <p class="text-muted"><i class="bi bi-info-circle"></i> This will enable real-time monitoring and alert management for the selected device.</p>
                            </div>
                        `,
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: '<i class="bi bi-check-circle"></i> Yes, Assign Device',
                        cancelButtonText: '<i class="bi bi-x-circle"></i> Cancel',
                        width: '500px'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            const formData = new FormData(this);
                            formData.append('assign_device', '1');
                            
                            // Show loading state
                            const submitBtn = document.getElementById('assignSubmitBtn');
                            const originalText = submitBtn.innerHTML;
                            submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Assigning Device...';
                            submitBtn.disabled = true;
                            
                            // Show progress notification
                            Swal.fire({
                                title: 'Assigning Device...',
                                html: `
                                    <div class="text-center">
                                        <div class="spinner-border text-primary mb-3" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                        <p>Please wait while we assign the device to the building.</p>
                                    </div>
                                `,
                                allowOutsideClick: false,
                                allowEscapeKey: false,
                                showConfirmButton: false
                            });
                            
                            fetch(window.location.href, {
                                method: 'POST',
                                body: formData,
                                headers: {
                                    'X-Requested-With': 'XMLHttpRequest'
                                }
                            })
                            .then(response => response.json())
                            .then(data => {
                                Swal.close(); // Close progress dialog
                                
                                if (data.success) {
                                    Swal.fire({
                                        title: '<i class="bi bi-check-circle text-success"></i> Assignment Successful!',
                                        html: `
                                            <div class="text-start">
                                                <div class="alert alert-success">
                                                    <p><strong>Device:</strong> ${deviceName}</p>
                                                    <p><strong>Building:</strong> ${buildingName}</p>
                                                    <p><strong>Status:</strong> Successfully assigned</p>
                                                </div>
                                                <p class="text-muted">The device is now ready for monitoring and alert management.</p>
                                            </div>
                                        `,
                                        icon: 'success',
                                        confirmButtonText: '<i class="bi bi-check"></i> Continue',
                                        confirmButtonColor: '#28a745'
                                    }).then(() => {
                                        location.reload();
                                    });
                                } else {
                                    Swal.fire({
                                        title: '<i class="bi bi-exclamation-triangle text-danger"></i> Assignment Failed',
                                        html: `
                                            <div class="text-start">
                                                <div class="alert alert-danger">
                                                    <p><strong>Error:</strong> ${data.message}</p>
                                                </div>
                                                <p class="text-muted">Please try again or contact support if the problem persists.</p>
                                            </div>
                                        `,
                                        icon: 'error',
                                        confirmButtonText: '<i class="bi bi-x"></i> OK',
                                        confirmButtonColor: '#dc3545'
                                    });
                                }
                            })
                            .catch(error => {
                                Swal.close(); // Close progress dialog
                                
                                Swal.fire({
                                    title: '<i class="bi bi-exclamation-triangle text-danger"></i> Connection Error',
                                    html: `
                                        <div class="text-start">
                                            <div class="alert alert-danger">
                                                <p><strong>Error:</strong> An unexpected error occurred while processing your request.</p>
                                            </div>
                                            <p class="text-muted">Please check your internet connection and try again.</p>
                                        </div>
                                    `,
                                    icon: 'error',
                                    confirmButtonText: '<i class="bi bi-x"></i> OK',
                                    confirmButtonColor: '#dc3545'
                                });
                            })
                            .finally(() => {
                                // Reset button state
                                submitBtn.innerHTML = originalText;
                                submitBtn.disabled = false;
                            });
                        }
                    });
                });
            }
            
            // Handle remove device
            function handleUnassignClick(btn) {
                const deviceId = btn.getAttribute('data-device-id');
                const deviceName = btn.getAttribute('data-device-name');
                if (!deviceId || !deviceName) {
                    console.error('Missing device ID or name');
                    return;
                }
                Swal.fire({
                    title: 'Confirm Unassignment',
                    text: `Are you sure you want to unassign "${deviceName}" from its building?`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Yes, unassign it!',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        const originalText = btn.innerHTML;
                        btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Unassigning...';
                        btn.disabled = true;
                        const formData = new FormData();
                        formData.append('remove_device', '1');
                        formData.append('device_id', deviceId);
                        fetch(window.location.href, {
                            method: 'POST',
                            body: formData,
                            headers: { 'X-Requested-With': 'XMLHttpRequest' }
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire({ title: 'Success!', text: data.message, icon: 'success', confirmButtonText: 'OK' })
                                    .then(() => { location.reload(); });
                            } else {
                                Swal.fire({ title: 'Error!', text: data.message, icon: 'error', confirmButtonText: 'OK' });
                            }
                        })
                        .catch(() => {
                            Swal.fire({ title: 'Error!', text: 'An unexpected error occurred. Please try again.', icon: 'error', confirmButtonText: 'OK' });
                        })
                        .finally(() => {
                            btn.innerHTML = originalText;
                            btn.disabled = false;
                        });
                    }
                });
            }

            // Bind existing buttons present at load
            document.querySelectorAll('.remove-device-btn').forEach(function(button) {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    handleUnassignClick(this);
                });
            });

            // Add Device button
            const addDeviceBtn = document.getElementById('addDeviceBtn');
            if (addDeviceBtn) {
                addDeviceBtn.addEventListener('click', function(){
                    const form = document.getElementById('addDeviceForm');
                    if (form) form.reset();
                    // reset to create mode
                    (document.getElementById('editDeviceId')||{}).value = '';
                    const modalTitle = document.getElementById('addDeviceModalLabel');
                    if (modalTitle) modalTitle.innerHTML = '<i class="bi bi-plus-circle me-2" style="font-size: 1.2rem;"></i>Add New Device';
                    const submitBtn = document.getElementById('addDeviceSubmitBtn');
                    if (submitBtn) submitBtn.innerHTML = '<i class="bi bi-check me-1"></i> Save Device';
                    addDeviceModal.show();
                });
            }

            // Add Device button for empty state
            const addDeviceBtnEmpty = document.getElementById('addDeviceBtnEmpty');
            if (addDeviceBtnEmpty) {
                addDeviceBtnEmpty.addEventListener('click', function(){
                    const form = document.getElementById('addDeviceForm');
                    if (form) form.reset();
                    // reset to create mode
                    (document.getElementById('editDeviceId')||{}).value = '';
                    const modalTitle = document.getElementById('addDeviceModalLabel');
                    if (modalTitle) modalTitle.innerHTML = '<i class="bi bi-plus-circle me-2" style="font-size: 1.2rem;"></i>Add New Device';
                    const submitBtn = document.getElementById('addDeviceSubmitBtn');
                    if (submitBtn) submitBtn.innerHTML = '<i class="bi bi-check me-1"></i> Save Device';
                    addDeviceModal.show();
                });
            }

            // Handle Add Device form
            const addDeviceForm = document.getElementById('addDeviceForm');
            if (addDeviceForm) {
                // Realtime validation wiring
                const deviceNumberInput = document.getElementById('device_number_input');
                const serialNumberInput = document.getElementById('serial_number_input');
                const deviceTypeInput = document.getElementById('device_type_input');
                const addDeviceSubmitBtn = document.getElementById('addDeviceSubmitBtn');
                const numberHelp = document.getElementById('device_number_help');
                const serialHelp = document.getElementById('serial_number_help');

                let validateTimeout = null;

                function setHelp(el, text, type){
                    if(!el) return;
                    el.textContent = text || '';
                    el.className = 'form-text';
                    if(type === 'success') el.classList.add('text-success');
                    else if(type === 'error') el.classList.add('text-danger');
                    else if(type === 'warn') el.classList.add('text-warning');
                }

                function setValidityStyles(input, isValid){
                    if(!input) return;
                    input.classList.remove('is-valid','is-invalid');
                    if(isValid === true) input.classList.add('is-valid');
                    if(isValid === false) input.classList.add('is-invalid');
                }

                function runValidation(){
                    const dn = (deviceNumberInput?.value || '').trim();
                    const sn = (serialNumberInput?.value || '').trim();
                    if(!dn && !sn){
                        setHelp(numberHelp, '', null);
                        setHelp(serialHelp, '', null);
                        setValidityStyles(deviceNumberInput, null);
                        setValidityStyles(serialNumberInput, null);
                        if(addDeviceSubmitBtn) addDeviceSubmitBtn.disabled = false; // allow saving name-only etc but server will recheck
                        return;
                    }

                    const fd = new FormData();
                    fd.append('validate_device','1');
                    fd.append('device_number', dn);
                    fd.append('serial_number', sn);

                    if(addDeviceSubmitBtn) addDeviceSubmitBtn.disabled = true;
                    setHelp(numberHelp, 'Validating...', null);
                    setHelp(serialHelp, 'Validating...', null);

                    fetch(window.location.href, {
                        method: 'POST',
                        body: fd,
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    }).then(r=>r.json()).then(data=>{
                        const inAdmin = !!data.in_admin;
                        const approved = !!data.approved;
                        const already = !!data.already_in_devices;
                        const ready = !!data.ready;

                        setValidityStyles(deviceNumberInput, inAdmin && approved && !already);
                        setValidityStyles(serialNumberInput, inAdmin && approved && !already);

                        if(!inAdmin){
                            setHelp(numberHelp, 'No matching pair found in admin devices.', 'error');
                            setHelp(serialHelp, 'No matching pair found in admin devices.', 'error');
                        } else if(inAdmin && !approved){
                            setHelp(numberHelp, 'Device found but not approved.', 'warn');
                            setHelp(serialHelp, 'Device found but not approved.', 'warn');
                        } else if(already){
                            setHelp(numberHelp, 'Device already registered.', 'error');
                            setHelp(serialHelp, 'Device already registered.', 'error');
                        } else if(ready){
                            setHelp(numberHelp, 'Device is valid and available.', 'success');
                            setHelp(serialHelp, 'Device is valid and available.', 'success');
                            if(deviceTypeInput && !deviceTypeInput.value && data.device_type){
                                deviceTypeInput.value = data.device_type;
                            }
                        }

                        if(addDeviceSubmitBtn) addDeviceSubmitBtn.disabled = !ready;
                    }).catch(()=>{
                        setHelp(numberHelp, 'Validation failed. Please try again.', 'error');
                        setHelp(serialHelp, 'Validation failed. Please try again.', 'error');
                        if(addDeviceSubmitBtn) addDeviceSubmitBtn.disabled = true;
                    });
                }

                function scheduleValidation(){
                    if(validateTimeout) clearTimeout(validateTimeout);
                    validateTimeout = setTimeout(runValidation, 350);
                }

                if(deviceNumberInput){
                    deviceNumberInput.addEventListener('input', scheduleValidation);
                    deviceNumberInput.addEventListener('blur', runValidation);
                }
                if(serialNumberInput){
                    serialNumberInput.addEventListener('input', scheduleValidation);
                    serialNumberInput.addEventListener('blur', runValidation);
                }

                addDeviceForm.addEventListener('submit', function(e){
                    e.preventDefault();
                    const formData = new FormData(this);
                    if (formData.get('is_active')) {
                        formData.set('is_active', '1');
                    } else {
                        formData.set('is_active', '0');
                    }
                    if ((document.getElementById('editDeviceId')||{}).value) {
                        formData.append('update_device', '1');
                    } else {
                        formData.append('create_device', '1');
                    }
                    const submitBtn = document.getElementById('addDeviceSubmitBtn');
                    const originalText = submitBtn.innerHTML;
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Saving...';

                    fetch(window.location.href, {
                        method: 'POST',
                        body: formData,
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    }).then(r=>r.json())
                      .then(data=>{
                        if (data.success) {
                            Swal.fire('Success', 'Device saved successfully', 'success').then(()=>{ location.reload(); });
                        } else {
                            Swal.fire('Error', data.message || 'Failed to save device', 'error');
                        }
                      })
                      .catch(()=> Swal.fire('Error', 'Request failed', 'error'))
                      .finally(()=>{ submitBtn.disabled = false; submitBtn.innerHTML = originalText; });
                });
            }

            // Update button handler (prefill form and switch to update mode)
            document.querySelectorAll('.update-device-btn').forEach(function(btn){
                btn.addEventListener('click', function(){
                    const deviceId = this.getAttribute('data-device-id');
                    const name = this.getAttribute('data-device-name') || '';
                    const number = this.getAttribute('data-device-number') || '';
                    const serial = this.getAttribute('data-serial') || '';
                    const type = this.getAttribute('data-device-type') || '';
                    const status = this.getAttribute('data-status') || 'offline';
                    const isActive = this.getAttribute('data-is-active') === '1';
                    const buildingId = this.getAttribute('data-building-id') || '';

                    const form = document.getElementById('addDeviceForm');
                    if (!form) return;
                    form.reset();
                    (document.querySelector('[name="device_name"]')||{}).value = name;
                    (document.querySelector('[name="device_number"]')||{}).value = number;
                    (document.querySelector('[name="serial_number"]')||{}).value = serial;
                    (document.querySelector('[name="device_type"]')||{}).value = type;
                    const statusSel = document.querySelector('[name="status"]');
                    if (statusSel) statusSel.value = status;
                    const isActiveChk = document.getElementById('isActiveChk');
                    if (isActiveChk) isActiveChk.checked = isActive;
                    const buildingSel = document.querySelector('[name="building_id"]');
                    if (buildingSel) buildingSel.value = buildingId || '';
                    (document.getElementById('editDeviceId')||{}).value = deviceId;

                    const modalTitle = document.getElementById('addDeviceModalLabel');
                    if (modalTitle) modalTitle.innerHTML = '<i class="bi bi-pencil me-2" style="font-size: 1.2rem;"></i>Update Device';
                    const submitBtn = document.getElementById('addDeviceSubmitBtn');
                    if (submitBtn) submitBtn.innerHTML = '<i class="bi bi-check me-1"></i> Update Device';
                    addDeviceModal.show();
                });
            });

            // Delete button handler
            document.querySelectorAll('.delete-device-btn').forEach(function(btn){
                btn.addEventListener('click', function(){
                    const deviceId = this.getAttribute('data-device-id');
                    const deviceName = this.getAttribute('data-device-name') || 'this device';
                    Swal.fire({
                        title: 'Delete Device?',
                        text: `Are you sure you want to delete "${deviceName}"? This action cannot be undone.`,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#d33',
                        cancelButtonColor: '#3085d6',
                        confirmButtonText: 'Yes, delete it',
                        cancelButtonText: 'Cancel'
                    }).then((res)=>{
                        if(res.isConfirmed){
                            const formData = new FormData();
                            formData.append('delete_device', '1');
                            formData.append('device_id', deviceId);
                            fetch(window.location.href, { method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                                .then(r=>r.json())
                                .then(data=>{
                                    if(data.success){ Swal.fire('Deleted', 'Device deleted successfully', 'success').then(()=> location.reload()); }
                                    else { Swal.fire('Error', data.message || 'Failed to delete device', 'error'); }
                                })
                                .catch(()=> Swal.fire('Error', 'Request failed', 'error'));
                        }
                    });
                });
            });

            // Filters functionality
            const searchInput = document.getElementById('deviceSearchInput');
            const statusFilter = document.getElementById('statusFilter');
            const buildingFilter = document.getElementById('buildingFilter');
            const clearFiltersBtn = document.getElementById('clearFiltersBtn');
            const devicesGrid = document.getElementById('devicesGrid');

            function filterDevices() {
                const searchTerm = (searchInput?.value || '').toLowerCase();
                const statusValue = statusFilter?.value || '';
                const buildingValue = buildingFilter?.value || '';

                const deviceCards = devicesGrid.querySelectorAll('.col-12.col-sm-6.col-lg-4');
                
                deviceCards.forEach(card => {
                    const deviceName = card.querySelector('.fw-semibold')?.textContent?.toLowerCase() || '';
                    const serialNumber = card.querySelector('code')?.textContent?.toLowerCase() || '';
                    const statusBadge = card.querySelector('.badge');
                    const statusText = statusBadge?.textContent?.toLowerCase() || '';
                    const buildingText = card.querySelector('.text-success, .text-warning')?.textContent?.toLowerCase() || '';
                    
                    const matchesSearch = !searchTerm || 
                        deviceName.includes(searchTerm) || 
                        serialNumber.includes(searchTerm);
                    
                    const matchesStatus = !statusValue || 
                        (statusValue === 'online' && statusText.includes('online')) ||
                        (statusValue === 'offline' && statusText.includes('offline')) ||
                        (statusValue === 'error' && statusText.includes('error'));
                    
                    const matchesBuilding = !buildingValue || 
                        (buildingValue && buildingText.includes('assigned')) ||
                        (buildingValue === '' && buildingText.includes('unassigned'));
                    
                    if (matchesSearch && matchesStatus && matchesBuilding) {
                        card.style.display = 'block';
                    } else {
                        card.style.display = 'none';
                    }
                });
            }

            if (searchInput) searchInput.addEventListener('input', filterDevices);
            if (statusFilter) statusFilter.addEventListener('change', filterDevices);
            if (buildingFilter) buildingFilter.addEventListener('change', filterDevices);
            if (clearFiltersBtn) {
                clearFiltersBtn.addEventListener('click', function(){
                    if (searchInput) searchInput.value = '';
                    if (statusFilter) statusFilter.value = '';
                    if (buildingFilter) buildingFilter.value = '';
                    filterDevices();
                });
            }

            // AJAX Pagination functionality
            function loadPage(page) {
                const url = new URL(window.location);
                url.searchParams.set('page', page);
                
                // Show loading state
                const devicesGrid = document.getElementById('devicesGrid');
                if (devicesGrid) {
                    devicesGrid.innerHTML = '<div class="col-12 text-center py-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>';
                }
                
                // Load new page
                fetch(url.toString())
                    .then(response => response.text())
                    .then(html => {
                        // Parse the response and extract the devices grid and pagination
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(html, 'text/html');
                        
                        // Update devices grid
                        const newDevicesGrid = doc.getElementById('devicesGrid');
                        if (newDevicesGrid && devicesGrid) {
                            devicesGrid.innerHTML = newDevicesGrid.innerHTML;
                        }
                        
                        // Update pagination
                        const newPagination = doc.querySelector('.d-flex.justify-content-between.align-items-center.mt-3');
                        const currentPagination = document.querySelector('.d-flex.justify-content-between.align-items-center.mt-3');
                        if (newPagination && currentPagination) {
                            currentPagination.innerHTML = newPagination.innerHTML;
                        }
                        
                        // Re-initialize event listeners for new content
                        initializeEventListeners();
                        
                        // Scroll to top of devices section
                        devicesGrid.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    })
                    .catch(error => {
                        console.error('Error loading page:', error);
                        if (devicesGrid) {
                            devicesGrid.innerHTML = '<div class="col-12"><div class="alert alert-danger">Error loading devices. Please refresh the page.</div></div>';
                        }
                    });
            }

            // Initialize event listeners for pagination links
            function initializeEventListeners() {
                // Re-attach pagination click handlers
                document.querySelectorAll('.pagination .page-link').forEach(link => {
                    link.addEventListener('click', function(e) {
                        e.preventDefault();
                        const href = this.getAttribute('href');
                        const pageMatch = href.match(/page=(\d+)/);
                        if (pageMatch) {
                            loadPage(pageMatch[1]);
                        }
                    });
                });
                
                // Re-attach device action button handlers
                document.querySelectorAll('.assign-btn').forEach(button => {
                    button.addEventListener('click', function() {
                        const deviceId = this.getAttribute('data-device-id');
                        const deviceName = this.getAttribute('data-device-name');
                        const serial = this.getAttribute('data-serial') || '';
                        const status = this.getAttribute('data-status') || '';
                        const locationText = this.getAttribute('data-location') || '';
                        
                        // Populate modal with device information
                        document.getElementById('assignDeviceId').value = deviceId;
                        document.getElementById('assignDeviceName').textContent = deviceName;
                        document.getElementById('assignDeviceSerial').textContent = serial;
                        
                        // Handle status badge styling
                        const originalBadge = this.closest('.card').querySelector('.badge');
                        const statusBadge = document.getElementById('assignDeviceStatus');
                        statusBadge.className = 'status-badge';
                        
                        if (originalBadge.classList.contains('bg-success')) {
                            statusBadge.classList.add('bg-success');
                            statusBadge.textContent = 'Online';
                        } else if (originalBadge.classList.contains('bg-secondary')) {
                            statusBadge.classList.add('bg-secondary');
                            statusBadge.textContent = 'Offline';
                        } else if (originalBadge.classList.contains('bg-danger')) {
                            statusBadge.classList.add('bg-danger');
                            statusBadge.textContent = 'Error';
                        }
                        
                        // Handle location display
                        const locationElement = document.getElementById('assignDeviceLocation');
                        if (locationText && locationText.includes('Unassigned')) {
                            locationElement.innerHTML = '<i class="bi bi-exclamation-circle me-1"></i> Unassigned';
                            locationElement.style.color = '#ffc107';
                        } else {
                            locationElement.innerHTML = '<i class="bi bi-building me-1"></i> ' + (locationText || 'Unknown');
                            locationElement.style.color = '#28a745';
                        }
                        
                        assignDeviceModal.show();
                    });
                });
                
                // Re-attach other button handlers as needed
                document.querySelectorAll('.update-device-btn').forEach(button => {
                    button.addEventListener('click', function() {
                        // Update device modal logic here
                        // (Copy from existing update device handler)
                    });
                });
                
                document.querySelectorAll('.delete-device-btn').forEach(button => {
                    button.addEventListener('click', function() {
                        // Delete device logic here
                        // (Copy from existing delete device handler)
                    });
                });
                
                document.querySelectorAll('.remove-device-btn').forEach(button => {
                    button.addEventListener('click', function() {
                        // Remove device logic here
                        // (Copy from existing remove device handler)
                    });
                });
            }

            // Initialize pagination on page load
            initializeEventListeners();

            // Assignment Stats Tooltip Functionality
            let assignmentStatsCache = null;
            let statsCacheTime = 0;
            const CACHE_DURATION = 30000; // 30 seconds

            function createAssignmentTooltip(element) {
                const tooltip = document.createElement('div');
                tooltip.className = 'tooltip-content';
                tooltip.innerHTML = `
                    <div class="tooltip-header">
                        <i class="bi bi-graph-up"></i>
                        Device Assignment Statistics
                    </div>
                    <div class="tooltip-body">
                        <div class="loading-spinner"></div>
                        <div style="text-align: center; margin-top: 8px; color: #bdc3c7; font-size: 0.8rem;">
                            Loading statistics...
                        </div>
                    </div>
                `;
                
                element.appendChild(tooltip);
                
                // Add hover event listeners
                element.addEventListener('mouseenter', function() {
                    loadAssignmentStats(tooltip);
                });
                
                element.addEventListener('mouseleave', function() {
                    // Optional: Add any cleanup here
                });
            }

            function loadAssignmentStats(tooltipElement) {
                const now = Date.now();
                
                // Use cached data if available and not expired
                if (assignmentStatsCache && (now - statsCacheTime) < CACHE_DURATION) {
                    renderAssignmentStats(tooltipElement, assignmentStatsCache);
                    return;
                }
                
                // Fetch fresh data
                fetch('php/get_assignment_stats.php', {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        assignmentStatsCache = data;
                        statsCacheTime = now;
                        renderAssignmentStats(tooltipElement, data);
                    } else {
                        showErrorInTooltip(tooltipElement, data.message || 'Failed to load statistics');
                    }
                })
                .catch(error => {
                    console.error('Error loading assignment stats:', error);
                    showErrorInTooltip(tooltipElement, 'Failed to load statistics');
                });
            }

            function renderAssignmentStats(tooltipElement, data) {
                const stats = data.stats;
                const history = data.recent_history;
                const buildingStats = data.building_stats;
                
                const historyHtml = history.slice(0, 5).map(item => `
                    <div class="history-item">
                        <div class="history-device">${item.device_name}</div>
                        <div class="history-building">→ ${item.building_name} (${item.building_type})</div>
                        <span class="history-status ${item.status}">${item.status}</span>
                    </div>
                `).join('');
                
                const buildingStatsHtml = buildingStats.slice(0, 3).map(building => `
                    <div class="history-item">
                        <div class="history-device">${building.building_name}</div>
                        <div class="history-building">${building.building_type} • ${building.device_count} devices (${building.online_count} online)</div>
                    </div>
                `).join('');
                
                tooltipElement.innerHTML = `
                    <div class="tooltip-header">
                        <i class="bi bi-graph-up"></i>
                        Device Assignment Statistics
                    </div>
                    <div class="tooltip-body">
                        <div class="stats-grid">
                            <div class="stat-item">
                                <span class="stat-number">${stats.total_devices}</span>
                                <span class="stat-label">Total Devices</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number">${stats.assigned_devices}</span>
                                <span class="stat-label">Assigned</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number">${stats.unassigned_devices}</span>
                                <span class="stat-label">Unassigned</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number">${stats.assignment_percentage}%</span>
                                <span class="stat-label">Assignment Rate</span>
                            </div>
                        </div>
                        
                        <div class="history-section">
                            <div class="history-title">
                                <i class="bi bi-clock-history"></i>
                                Recent Assignments
                            </div>
                            ${historyHtml || '<div style="color: #bdc3c7; font-size: 0.75rem; text-align: center;">No recent assignments</div>'}
                        </div>
                        
                        ${buildingStatsHtml ? `
                        <div class="history-section">
                            <div class="history-title">
                                <i class="bi bi-building"></i>
                                Top Buildings
                            </div>
                            ${buildingStatsHtml}
                        </div>
                        ` : ''}
                    </div>
                `;
            }

            function showErrorInTooltip(tooltipElement, message) {
                tooltipElement.innerHTML = `
                    <div class="tooltip-header">
                        <i class="bi bi-exclamation-triangle"></i>
                        Error Loading Statistics
                    </div>
                    <div class="tooltip-body">
                        <div style="color: #e74c3c; text-align: center; font-size: 0.8rem;">
                            ${message}
                        </div>
                    </div>
                `;
            }

            // Initialize tooltips for all check icons
            function initializeAssignmentTooltips() {
                // Find all check icons and wrap them with tooltip functionality
                document.querySelectorAll('.bi-check, .bi-check-circle, .bi-check2-circle').forEach(function(icon) {
                    // Skip if already wrapped
                    if (icon.closest('.assignment-tooltip')) return;
                    
                    // Wrap the icon with tooltip container
                    const wrapper = document.createElement('span');
                    wrapper.className = 'assignment-tooltip';
                    icon.parentNode.insertBefore(wrapper, icon);
                    wrapper.appendChild(icon);
                    
                    // Create the tooltip
                    createAssignmentTooltip(wrapper);
                });
            }

            // Initialize tooltips when DOM is ready
            document.addEventListener('DOMContentLoaded', function() {
                initializeAssignmentTooltips();
            });

            // Re-initialize tooltips after dynamic content updates
            function reinitializeTooltips() {
                setTimeout(initializeAssignmentTooltips, 100);
            }

            // Fire Icon Tooltip Functionality
            let fireStatsCache = null;
            let fireStatsCacheTime = 0;
            const FIRE_CACHE_DURATION = 30000; // 30 seconds

            function createFireTooltip(element) {
                const tooltip = document.createElement('div');
                tooltip.className = 'fire-tooltip-content';
                tooltip.innerHTML = `
                    <div class="fire-tooltip-header">
                        <i class="bi bi-fire"></i>
                        Fire Incident Statistics & Calendar
                    </div>
                    <div class="fire-tooltip-body">
                        <div class="fire-loading-spinner"></div>
                        <div style="text-align: center; margin-top: 12px; color: #ffcccc; font-size: 0.9rem;">
                            Loading fire incident data...
                        </div>
                    </div>
                `;
                
                element.appendChild(tooltip);
                
                // Add hover event listeners
                element.addEventListener('mouseenter', function() {
                    loadFireStats(tooltip);
                });
                
                element.addEventListener('mouseleave', function() {
                    // Optional: Add any cleanup here
                });
            }

            function loadFireStats(tooltipElement) {
                const now = Date.now();
                
                // Use cached data if available and not expired
                if (fireStatsCache && (now - fireStatsCacheTime) < FIRE_CACHE_DURATION) {
                    renderFireStats(tooltipElement, fireStatsCache);
                    return;
                }
                
                // Fetch fresh data
                fetch('get_fire_stats.php', {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        fireStatsCache = data;
                        fireStatsCacheTime = now;
                        renderFireStats(tooltipElement, data);
                    } else {
                        showFireErrorInTooltip(tooltipElement, data.message || 'Failed to load fire statistics');
                    }
                })
                .catch(error => {
                    console.error('Error loading fire stats:', error);
                    showFireErrorInTooltip(tooltipElement, 'Failed to load fire statistics');
                });
            }

            function renderFireStats(tooltipElement, data) {
                const fireStats = data.fire_stats;
                const deviceStats = data.device_stats;
                const buildingStats = data.building_stats;
                const recentIncidents = data.recent_incidents;
                const calendarData = data.calendar_data;
                const buildingIncidents = data.building_incidents;
                const deviceIncidents = data.device_incidents;
                
                // Format recent incidents
                const incidentsHtml = recentIncidents.slice(0, 5).map(incident => {
                    const riskLevel = getRiskLevel(incident.ml_confidence, incident.flame_detected, incident.smoke, incident.temp);
                    return `
                        <div class="fire-incident-item">
                            <div class="fire-incident-device">${incident.device_name || 'Unknown Device'}</div>
                            <div class="fire-incident-details">
                                ${incident.building_name ? `→ ${incident.building_name} (${incident.building_type})` : 'Unassigned Location'}
                                <br>Smoke: ${incident.smoke} | Temp: ${incident.temp}°C | Heat: ${incident.heat}
                                <br>ML Confidence: ${incident.ml_confidence}% | ${incident.timestamp}
                            </div>
                            <span class="fire-incident-status ${riskLevel}">${riskLevel.toUpperCase()}</span>
                        </div>
                    `;
                }).join('');
                
                // Format calendar data
                const calendarHtml = generateCalendarGrid(calendarData);
                
                // Format building incidents
                const buildingIncidentsHtml = buildingIncidents.slice(0, 3).map(building => `
                    <div class="fire-incident-item">
                        <div class="fire-incident-device">${building.building_name}</div>
                        <div class="fire-incident-details">
                            ${building.building_type} • ${building.incident_count} incidents
                            <br>Fire Predictions: ${building.fire_predictions} | Flame Detections: ${building.flame_detections}
                            <br>Max Confidence: ${building.max_confidence}% | Avg Smoke: ${building.avg_smoke}
                        </div>
                    </div>
                `).join('');
                
                // Format device incidents
                const deviceIncidentsHtml = deviceIncidents.slice(0, 3).map(device => `
                    <div class="fire-incident-item">
                        <div class="fire-incident-device">${device.device_name}</div>
                        <div class="fire-incident-details">
                            ${device.device_number} • ${device.status} • ${device.incident_count} incidents
                            <br>Fire Predictions: ${device.fire_predictions} | Flame Detections: ${device.flame_detections}
                            <br>Max Confidence: ${device.max_confidence}% | Avg Temp: ${device.avg_temp}°C
                        </div>
                    </div>
                `).join('');
                
                tooltipElement.innerHTML = `
                    <div class="fire-tooltip-header">
                        <i class="bi bi-fire"></i>
                        Fire Incident Statistics & Calendar
                    </div>
                    <div class="fire-tooltip-body">
                        <div class="fire-stats-grid">
                            <div class="fire-stat-item">
                                <span class="fire-stat-number">${fireStats.total_incidents}</span>
                                <span class="fire-stat-label">Total Incidents</span>
                            </div>
                            <div class="fire-stat-item">
                                <span class="fire-stat-number">${fireStats.ml_predictions}</span>
                                <span class="fire-stat-label">Fire Predictions</span>
                            </div>
                            <div class="fire-stat-item">
                                <span class="fire-stat-number">${fireStats.flame_detections}</span>
                                <span class="fire-stat-label">Flame Detections</span>
                            </div>
                            <div class="fire-stat-item">
                                <span class="fire-stat-number">${fireStats.avg_ml_confidence}%</span>
                                <span class="fire-stat-label">Avg ML Confidence</span>
                            </div>
                            <div class="fire-stat-item">
                                <span class="fire-stat-number">${deviceStats.total_devices}</span>
                                <span class="fire-stat-label">Total Devices</span>
                            </div>
                            <div class="fire-stat-item">
                                <span class="fire-stat-number">${deviceStats.online_devices}</span>
                                <span class="fire-stat-label">Online Devices</span>
                            </div>
                            <div class="fire-stat-item">
                                <span class="fire-stat-number">${buildingStats.total_buildings}</span>
                                <span class="fire-stat-label">Total Buildings</span>
                            </div>
                            <div class="fire-stat-item">
                                <span class="fire-stat-number">${buildingStats.buildings_with_alarms}</span>
                                <span class="fire-stat-label">With Fire Alarms</span>
                            </div>
                        </div>
                        
                        <div class="fire-section">
                            <div class="fire-section-title">
                                <i class="bi bi-clock-history"></i>
                                Recent Fire Incidents
                            </div>
                            ${incidentsHtml || '<div style="color: #ffcccc; font-size: 0.8rem; text-align: center;">No recent incidents</div>'}
                        </div>
                        
                        <div class="fire-section">
                            <div class="fire-section-title">
                                <i class="bi bi-calendar3"></i>
                                Fire Calendar (Last 30 Days)
                            </div>
                            ${calendarHtml}
                        </div>
                        
                        ${buildingIncidentsHtml ? `
                        <div class="fire-section">
                            <div class="fire-section-title">
                                <i class="bi bi-building"></i>
                                Top Buildings by Incidents
                            </div>
                            ${buildingIncidentsHtml}
                        </div>
                        ` : ''}
                        
                        ${deviceIncidentsHtml ? `
                        <div class="fire-section">
                            <div class="fire-section-title">
                                <i class="bi bi-router"></i>
                                Top Devices by Incidents
                            </div>
                            ${deviceIncidentsHtml}
                        </div>
                        ` : ''}
                    </div>
                `;
            }

            function getRiskLevel(mlConfidence, flameDetected, smoke, temp) {
                if (flameDetected || mlConfidence > 80 || smoke > 800 || temp > 100) {
                    return 'high-risk';
                } else if (mlConfidence > 60 || smoke > 500 || temp > 80) {
                    return 'medium-risk';
                } else {
                    return 'low-risk';
                }
            }

            function generateCalendarGrid(calendarData) {
                const today = new Date();
                const daysInMonth = new Date(today.getFullYear(), today.getMonth() + 1, 0).getDate();
                const firstDay = new Date(today.getFullYear(), today.getMonth(), 1).getDay();
                
                let calendarHtml = '<div class="fire-calendar-grid">';
                
                // Add day headers
                const dayHeaders = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
                dayHeaders.forEach(day => {
                    calendarHtml += `<div style="font-weight: 700; color: #ff6666; font-size: 0.7rem;">${day}</div>`;
                });
                
                // Add empty cells for days before the first day of the month
                for (let i = 0; i < firstDay; i++) {
                    calendarHtml += '<div class="fire-calendar-day"></div>';
                }
                
                // Add days of the month
                for (let day = 1; day <= daysInMonth; day++) {
                    const dayStr = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                    const dayData = calendarData.find(d => d.incident_date === dayStr);
                    
                    if (dayData && dayData.incident_count > 0) {
                        calendarHtml += `
                            <div class="fire-calendar-day has-incidents" title="Incidents: ${dayData.incident_count}, Fire Predictions: ${dayData.fire_predictions}">
                                <div style="font-size: 0.6rem;">${day}</div>
                                <div class="fire-calendar-day-count">${dayData.incident_count}</div>
                            </div>
                        `;
                    } else {
                        calendarHtml += `<div class="fire-calendar-day">${day}</div>`;
                    }
                }
                
                calendarHtml += '</div>';
                return calendarHtml;
            }

            function showFireErrorInTooltip(tooltipElement, message) {
                tooltipElement.innerHTML = `
                    <div class="fire-tooltip-header">
                        <i class="bi bi-exclamation-triangle"></i>
                        Error Loading Fire Statistics
                    </div>
                    <div class="fire-tooltip-body">
                        <div style="color: #ff4444; text-align: center; font-size: 0.9rem;">
                            ${message}
                        </div>
                    </div>
                `;
            }

            // Initialize fire tooltips for all fire icons
            function initializeFireTooltips() {
                // Find all fire icons and wrap them with tooltip functionality
                document.querySelectorAll('img[src*="fire.png"], img[alt*="fire"], img[alt*="Fire"]').forEach(function(img) {
                    // Skip if already wrapped
                    if (img.closest('.fire-tooltip')) return;
                    
                    // Wrap the image with tooltip container
                    const wrapper = document.createElement('span');
                    wrapper.className = 'fire-tooltip';
                    img.parentNode.insertBefore(wrapper, img);
                    wrapper.appendChild(img);
                    
                    // Create the tooltip
                    createFireTooltip(wrapper);
                });
            }

            // Initialize fire tooltips when DOM is ready
            document.addEventListener('DOMContentLoaded', function() {
                initializeFireTooltips();
            });

            // Re-initialize fire tooltips after dynamic content updates
            function reinitializeFireTooltips() {
                setTimeout(initializeFireTooltips, 100);
            }
        </script>
        
        <!-- jQuery -->
        <script src="../../../vendors/jquery/dist/jquery.min.js"></script>
        <!-- Bootstrap -->
        <script src="../../../vendors/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
        <!-- FastClick -->
        <script src="../../../vendors/fastclick/lib/fastclick.js"></script>
        <!-- NProgress -->
        <script src="../../../vendors/nprogress/nprogress.js"></script>
        <!-- Chart.js -->
        <script src="../../../vendors/Chart.js/dist/Chart.min.js"></script>
        <!-- gauge.js -->
        <script src="../../../vendors/gauge.js/dist/gauge.min.js"></script>
        <!-- bootstrap-progressbar -->
        <script src="../../../vendors/bootstrap-progressbar/bootstrap-progressbar.min.js"></script>
        <!-- iCheck -->
        <script src="../../../vendors/iCheck/icheck.min.js"></script>
        <!-- Skycons -->
        <script src="../../../vendors/skycons/skycons.js"></script>
        <!-- Flot -->
        <script src="../../../vendors/Flot/jquery.flot.js"></script>
        <script src="../../../vendors/Flot/jquery.flot.pie.js"></script>
        <script src="../../../vendors/Flot/jquery.flot.time.js"></script>
        <script src="../../../vendors/Flot/jquery.flot.stack.js"></script>
        <script src="../../../vendors/Flot/jquery.flot.resize.js"></script>
        <!-- Flot plugins -->
        <script src="../../../vendors/flot.orderbars/js/jquery.flot.orderBars.js"></script>
        <script src="../../../vendors/flot-spline/js/jquery.flot.spline.min.js"></script>
        <script src="../../../vendors/flot.curvedlines/curvedLines.js"></script>
        <!-- DateJS -->
        <script src="../../../vendors/DateJS/build/date.js"></script>
        <!-- JQVMap -->
        <script src="../../../vendors/jqvmap/dist/jquery.vmap.js"></script>
        <script src="../../../vendors/jqvmap/dist/maps/jquery.vmap.world.js"></script>
        <script src="../../../vendors/jqvmap/examples/js/jquery.vmap.sampledata.js"></script>
        <!-- bootstrap-daterangepicker -->
        <script src="../../../vendors/moment/min/moment.min.js"></script>
        <script src="../../../vendors/bootstrap-daterangepicker/daterangepicker.js"></script>

        <!-- Custom Theme Scripts -->
        <script src="../../../build/js/custom.min.js"></script>
    </div>
</body>
</html>
