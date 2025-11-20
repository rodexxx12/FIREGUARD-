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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.3/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.Default.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.css" />
    <link href="../../../vendors/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../../vendors/font-awesome/css/font-awesome.min.css" rel="stylesheet">
    <link href="../../../vendors/nprogress/nprogress.css" rel="stylesheet">
    <link href="../../../vendors/bootstrap-progressbar/css/bootstrap-progressbar-3.3.4.min.css" rel="stylesheet">  
    <link href="../../../vendors/bootstrap-daterangepicker/daterangepicker.css" rel="stylesheet">  
    <link href="../../../build/css/custom.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.Default.css" />
    <script src="https://unpkg.com/leaflet-heat@0.2.0/dist/leaflet-heat.js"></script>
    <link href="https://unpkg.com/mapbox-gl@2.15.0/dist/mapbox-gl.css" rel="stylesheet" />
    <script src="https://unpkg.com/mapbox-gl@2.15.0/dist/mapbox-gl.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="../css/style.css">
    
    
    
    <!-- Modern Card Styles -->
    <style>
        .modern-card {
            border-radius: 12px !important;
        }
        
        .modern-header {
            position: relative;
            overflow: hidden;
        }
        
        .modern-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }
        
        /* Removed hover effect */
        
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
            height: 6px;
        }
        
        .table-responsive::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }
        
        .table-responsive::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
            border-radius: 3px;
        }
        
        /* Removed scrollbar hover effect */
        
        /* Simple Modal Styles */
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            font-weight: 600;
            border-radius: 0.25rem;
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
            box-shadow: 0 0 0 0.2rem rgba(255, 107, 53, 0.25);
        }
        
        /* Table hover effects */
        .modern-table tbody tr:hover {
            background-color: #f8f9fa !important;
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: all 0.2s ease;
        }
        
        /* Map icon styling */
        .building-icon {
            z-index: 100;
        }
        
        .building-icon img {
            z-index: 101;
        }
        
        .device-icon {
            z-index: 200;
            transition: all 0.3s ease;
        }
        
        .device-icon img {
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3));
            position: relative;
            z-index: 201;
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

        /* Orange theme */
        :root {
            --fg-orange: #ff6b35;
            --fg-orange-600: #ff5a1c;
            --fg-orange-700: #e24f18;
            --fg-orange-100: #fff3ec;
            --fg-orange-200: #ffe4d6;
        }

        .btn-orange {
            background: var(--fg-orange);
            border: none;
            color: #fff;
        }
        .btn-orange:hover {
            background: var(--fg-orange-600);
            color: #fff;
        }
        .btn-outline-orange {
            background: transparent;
            border: 1px solid var(--fg-orange);
            color: var(--fg-orange);
        }
        .btn-outline-orange:hover {
            background: var(--fg-orange);
            color: #fff;
        }

        .card.orange-accent {
            border: 1px solid #f1f1f1;
            transition: box-shadow .2s ease, transform .2s ease;
        }
        .card.orange-accent:hover {
            box-shadow: 0 10px 25px -10px rgba(255,107,53,.5);
            transform: translateY(-2px);
        }
        .card-title-accent {
            color: var(--fg-orange);
            display: flex;
            align-items: center;
            gap: .5rem;
            margin: 0;
        }
        .card-title-accent i { color: var(--fg-orange); }

        .section-header {
            border-left: 4px solid var(--fg-orange);
            padding-left: .75rem;
        }

        /* Map accent */
        #map {
            border: 1px solid var(--fg-orange-200) !important;
        }

        /* Status badge subtle style */
        .status-pill {
            padding: .25rem .5rem;
            border-radius: 999px;
            font-weight: 600;
            font-size: .72rem;
        }

        /* Device card spacing */
        .device-card .card-body { padding: 1rem; }
        
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
        
        /* Fire Icon Tooltip Styles - Compact White Card */
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
            background: #ffffff;
            color: #333333;
            text-align: left;
            border-radius: 8px;
            padding: 0;
            width: 580px;
            max-width: 90vw;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            border: 1px solid #e0e0e0;
            transition: all 0.3s ease;
            font-size: 0.7rem;
        }
        
        .fire-tooltip .fire-tooltip-content::after {
            content: "";
            position: absolute;
            top: 100%;
            left: 50%;
            margin-left: -8px;
            border-width: 8px;
            border-style: solid;
            border-color: #ffffff transparent transparent transparent;
        }
        
        .fire-tooltip:hover .fire-tooltip-content {
            visibility: visible;
            opacity: 1;
            transform: translateX(-50%) translateY(-6px);
        }
        
        .fire-tooltip-header {
            background: #ffffff;
            color: #333333;
            padding: 12px 16px;
            border-radius: 8px 8px 0 0;
            font-weight: 600;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 8px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .fire-tooltip-body {
            padding: 6px;
            max-height: 450px;
            overflow-y: auto;
            overflow-x: hidden;
            background: #ffffff;
            scrollbar-width: thin;
            scrollbar-color: #dee2e6 #f8f9fa;
        }
        
        .fire-stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1px;
            margin-bottom: 2px;
        }
        
        .fire-content-columns {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3px;
        }
        
        .fire-column {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        
        .fire-stat-item {
            background: #f8f9fa;
            padding: 1px 2px;
            border-radius: 1px;
            text-align: center;
            border: 1px solid #e9ecef;
            transition: all 0.2s ease;
        }
        
        .fire-stat-item:hover {
            background: #e9ecef;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .fire-stat-number {
            font-size: 0.9rem;
            font-weight: 700;
            color: #000000;
            display: block;
        }
        
        .fire-stat-number.high-value {
            color: #dc3545;
        }
        
        .fire-stat-number.medium-value {
            color: #fd7e14;
        }
        
        .fire-stat-number.low-value {
            color: #28a745;
        }
        
        .fire-stat-label {
            font-size: 0.6rem;
            color: #000000;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 2px;
            font-weight: 500;
        }
        
        .fire-section {
            margin-top: 6px;
            padding-top: 6px;
            border-top: 1px solid #e9ecef;
        }
        
        .fire-section-title {
            font-size: 0.5rem;
            font-weight: 600;
            color: #000000;
            margin-bottom: 0px;
            display: flex;
            align-items: center;
            gap: 1px;
            text-transform: uppercase;
            letter-spacing: 0.05px;
            padding: 1px 2px;
            background: #ffffff;
            border-bottom: 1px solid #e9ecef;
        }
        
        .fire-incident-item {
            background: #f8f9fa;
            padding: 1px 2px;
            border-radius: 1px;
            margin-bottom: 1px;
            font-size: 0.55rem;
            border-left: 1px solid #dc3545;
            transition: all 0.2s ease;
            color: #000000;
        }
        
        .fire-incident-item:hover {
            background: #e9ecef;
            transform: translateX(2px);
        }
        
        .fire-incident-item:last-child {
            margin-bottom: 0;
        }
        
        .fire-incident-device {
            font-weight: 600;
            color: #000000;
            font-size: 0.7rem;
            margin-bottom: 2px;
        }
        
        .fire-incident-details {
            color: #495057;
            font-size: 0.6rem;
            margin-top: 2px;
            line-height: 1.1;
        }
        
        .fire-incident-status {
            display: inline-block;
            padding: 1px 4px;
            border-radius: 2px;
            font-size: 0.55rem;
            font-weight: 600;
            margin-left: 4px;
        }
        
        .fire-incident-status.high-risk {
            background: #dc3545;
            color: #ffffff;
        }
        
        .fire-incident-status.medium-risk {
            background: #fd7e14;
            color: #ffffff;
        }
        
        .fire-incident-status.low-risk {
            background: #28a745;
            color: #ffffff;
        }
        
        .fire-calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 2px;
            margin-top: 4px;
        }
        
        .fire-calendar-day {
            background: #f8f9fa;
            padding: 4px 2px;
            border-radius: 2px;
            text-align: center;
            font-size: 0.6rem;
            transition: all 0.3s ease;
            color: #6c757d;
            border: 1px solid #e9ecef;
        }
        
        .fire-calendar-day.has-incidents {
            background: #fff5f5;
            border: 1px solid #dc3545;
            color: #dc3545;
            font-weight: 600;
        }
        
        .fire-calendar-day.has-incidents:hover {
            background: #dc3545;
            color: #ffffff;
            transform: scale(1.1);
        }
        
        .fire-calendar-day-count {
            font-weight: 700;
            color: #dc3545;
            font-size: 0.65rem;
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
            background: #f8f9fa;
            border-radius: 4px;
        }
        
        .fire-tooltip-body::-webkit-scrollbar-thumb {
            background: #dee2e6;
            border-radius: 4px;
            border: 1px solid #f8f9fa;
        }
        
        .fire-tooltip-body::-webkit-scrollbar-thumb:hover {
            background: #adb5bd;
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


<!-- Combined Map and Device Management Section -->
<div class="row mx-0">
    <div class="col-12 px-0">
        <!-- Single Card with Map and Device Management -->
        <div class="card mb-2 modern-card" id="devices-section" style="border: none; box-shadow: 0 8px 25px -5px rgba(0, 0, 0, 0.1), 0 4px 10px -2px rgba(0, 0, 0, 0.05); border-radius: 16px; background: #ffffff; min-height: 50vh; margin-top: 1rem; border-radius: 16px; width: 100%; max-width: none;">
    
            <!-- Card Body with Map and Device Management -->
            <div class="card-body" style="background: #ffffff; padding: 1rem; min-height: 100vh; border-radius: 12px;">
                                      <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h2 class="mb-1" style="color: #000000; font-weight: 600; margin-top: 5px; margin-bottom: 5px;">
                                    Map and Device Assignment - <?php echo date('F Y'); ?>
                                </h2>
                            </div>
                            <div class="d-flex gap-2">
                                <a href="devices.php" class="btn" style="background: none; border: none; color: #ff6b35; padding: 8px 16px; font-weight: 600; text-decoration: none; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 6px; font-size: 14px;">
                                    <i class="bi bi-list-ul"></i> Device List
                                </a>
                            </div>
                        </div>
                
                <!-- Map Section (moved above device management) -->
                <div class="mb-2" style="position: relative;">
                    <div id="map" style="height: 100vh; box-shadow: 0 8px 25px -5px rgba(0, 0, 0, 0.1), 0 4px 10px -2px rgba(0, 0, 0, 0.05); border: 1px solid #e9ecef;"></div>
                    
                    <!-- Map Controls Overlay - Left Bottom Corner -->
                    <div style="position: absolute; bottom: 10px; left: 10px; z-index: 1000; display: flex; flex-direction: column; gap: 3px; background: rgba(255, 255, 255, 0.95); padding: 8px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
                        <button type="button" class="btn btn-outline-primary btn-sm" id="resetMapViewBtn" style="font-size: 0.75rem; padding: 4px 8px; min-width: 100px;">
                            <i class="bi bi-arrow-clockwise me-1"></i> Reset
                        </button>
                        <button type="button" class="btn btn-outline-danger btn-sm" id="zoomToFireStationBtn" style="font-size: 0.75rem; padding: 4px 8px; min-width: 100px;">
                            <i class="bi bi-building-exclamation me-1"></i> Station
                        </button>
                        <button type="button" class="btn btn-outline-success btn-sm" id="toggleBuildingsBtn" style="font-size: 0.75rem; padding: 4px 8px; min-width: 100px;">
                            <i class="bi bi-building me-1"></i> Buildings
                        </button>
                    </div>
                </div>
                </div>
            </div>
        </div>
    </div>
</div>
    
    
    <!-- Simple Assign Device Modal -->
    <div class="modal fade" id="assignDeviceModal" tabindex="-1" aria-labelledby="assignDeviceModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 10px; border: none; box-shadow: 0 4px 20px rgba(0,0,0,0.15);">
                <form id="assignDeviceForm">
                    <!-- Simple Header -->
                    <div class="modal-header" style="background: #ff6b35; border: none; border-radius: 10px 10px 0 0; padding: 1rem 1.5rem;">
                        <h5 class="modal-title text-white mb-0 d-flex align-items-center" id="assignDeviceModalLabel">
                            <i class="bi bi-cpu me-2" style="font-size: 1.2rem;"></i>Assign Device
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    
                    <div class="modal-body" style="padding: 1.5rem;">
                        <input type="hidden" name="device_id" id="assignDeviceId">
                        
                        <!-- Device Info -->
                        <div class="mb-4">
                            <h6 class="text-muted mb-3">Device Information</h6>
                            <div class="row g-3">
                                <div class="col-6">
                                    <label class="form-label small text-muted">Device Name</label>
                                    <div class="fw-semibold" id="assignDeviceName">-</div>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small text-muted">Status</label>
                                    <div id="assignDeviceStatus" class="status-badge">-</div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label small text-muted">Serial Number</label>
                                    <div class="text-muted small" id="assignDeviceSerial">-</div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label small text-muted">Current Location</label>
                                    <div id="assignDeviceLocation" class="fw-semibold small">-</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Building Selection -->
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Select Building</label>
                            <select class="form-select" id="building_id" name="building_id" required>
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
                        
                        <!-- Building Preview -->
                        <div id="buildingDetails" class="mb-3" style="display: none;">
                            <div class="alert alert-light border" style="border-radius: 8px;">
                                <h6 class="text-success mb-2">
                                    <i class="bi bi-building me-1"></i>Building Details
                                </h6>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <small class="text-muted">Type</small>
                                        <div class="fw-semibold small" id="buildingType">-</div>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">Current Devices</small>
                                        <div class="fw-semibold small" id="buildingDeviceCount">-</div>
                                    </div>
                                    <div class="col-12">
                                        <small class="text-muted">Address</small>
                                        <div class="fw-semibold small" id="buildingAddress">-</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Assignment Summary -->
                        <div id="assignmentSummary" style="display: none;">
                            <div class="alert alert-success border-0" style="border-radius: 8px; background: #f8fff9;">
                                <div class="d-flex align-items-center mb-2">
                                    <i class="bi bi-clipboard-check me-2"></i>
                                    <strong>Assignment Summary</strong>
                                </div>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <small class="text-muted">Device:</small>
                                        <div class="fw-semibold small" id="summaryDevice">-</div>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">Building:</small>
                                        <div class="fw-semibold small" id="summaryBuilding">-</div>
                                    </div>
                                </div>
                                <small class="text-muted mt-2 d-block">
                                    <i class="bi bi-info-circle me-1"></i>
                                    This device will be assigned for monitoring and alert management.
                                </small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Simple Footer -->
                    <div class="modal-footer" style="border: none; padding: 1rem 1.5rem; background: #f8f9fa; border-radius: 0 0 10px 10px;">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-primary" id="assignSubmitBtn" disabled style="background: #ff6b35; border: none;">
                            <i class="bi bi-check me-1"></i>Assign Device
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Device Modal -->
    <div class="modal fade" id="addDeviceModal" tabindex="-1" aria-labelledby="addDeviceModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 10px; border: none; box-shadow: 0 4px 20px rgba(0,0,0,0.15);">
                <form id="addDeviceForm">
                    <input type="hidden" name="device_id" id="editDeviceId" value="">
                    <div class="modal-header" style="background: #ff6b35; border: none; border-radius: 10px 10px 0 0; padding: 1rem 1.5rem;">
                        <h5 class="modal-title text-white mb-0 d-flex align-items-center" id="addDeviceModalLabel">
                            <i class="bi bi-plus-circle me-2" style="font-size: 1.2rem;"></i>Add New Device
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" style="padding: 1.5rem;">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Device Name</label>
                                <input type="text" name="device_name" class="form-control" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Device Number</label>
                                <input type="text" name="device_number" class="form-control" required id="device_number_input">
                                <div class="form-text" id="device_number_help"></div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Serial Number</label>
                                <input type="text" name="serial_number" class="form-control" required id="serial_number_input">
                                <div class="form-text" id="serial_number_help"></div>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Device Type</label>
                                <input type="text" name="device_type" class="form-control" placeholder="FIREGUARD DEVICE" id="device_type_input">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option value="offline" selected>Offline</option>
                                    <option value="online">Online</option>
                                </select>
                            </div>
                            
                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_active" id="isActiveChk" checked>
                                    <label class="form-check-label" for="isActiveChk">
                                        Active
                                    </label>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Assign to Building (optional)</label>
                                <select class="form-select" name="building_id">
                                    <option value="">None</option>
                                    <?php foreach ($buildings as $building): ?>
                                        <option value="<?php echo $building['id']; ?>"><?php echo htmlspecialchars($building['building_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer" style="border: none; padding: 1rem 1.5rem; background: #f8f9fa; border-radius: 0 0 10px 10px;">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="addDeviceSubmitBtn" style="background: #ff6b35; border: none;">
                            <i class="bi bi-check me-1"></i> Save Device
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php include('../../components/footer.php'); ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.3/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet.markercluster@1.4.1/dist/leaflet.markercluster.js"></script>
    <script src="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.js"></script>
    <script src="https://unpkg.com/mapbox-gl-leaflet@0.0.15/leaflet-mapbox-gl.js"></script>
<script>
        // Get appropriate icon for building type
        function getBuildingIcon(buildingType) {
        const icons = {
            'Residential': 'https://cdn-icons-png.flaticon.com/512/619/619153.png',
            'Commercial': '../../images/commercial.png',
            'Industrial': '../../images/industrial.png',
            'Institutional': '../../images/institutional.png',
        };

        return icons[buildingType] || 'https://cdn-icons-png.flaticon.com/512/619/619153.png';  // Default icon if type is not found
    }

    // Initialize map
    var map = L.map('map').setView([14.5995, 120.9842], 13); // Default to Manila
    
    // Base layers
    const osmLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);
    
    const satelliteLayer = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        attribution: 'Tiles &copy; Esri &mdash; Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community'
    });
    
    // Add OpenTopoMap for terrain view
    const topoLayer = L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://opentopomap.org/">OpenTopoMap</a> contributors'
    });
    
    // Layer control
    const baseMaps = {
        "Street Map": osmLayer,
        "Satellite": satelliteLayer,
        "Terrain": topoLayer
    };
    
    // Add layer control to map
    L.control.layers(baseMaps).addTo(map);
    
    // Map control button handlers
    document.getElementById('resetMapViewBtn').addEventListener('click', function() {
        if (Object.keys(markers).length > 0) {
            var group = new L.featureGroup(Object.values(markers));
            map.fitBounds(group.getBounds().pad(0.2));
            
            Swal.fire({
                title: 'View Reset',
                text: 'Map view has been reset to show all locations.',
                icon: 'info',
                timer: 2000,
                showConfirmButton: false
            });
        } else {
            map.setView([14.5995, 120.9842], 13);
            
            Swal.fire({
                title: 'View Reset',
                text: 'Map view has been reset to default location.',
                icon: 'info',
                timer: 2000,
                showConfirmButton: false
            });
        }
    });
    
    document.getElementById('zoomToFireStationBtn').addEventListener('click', function() {
        zoomToFireStation();
    });
    
    // Hide/Show Buildings functionality
    let buildingsVisible = true;
    
    document.getElementById('toggleBuildingsBtn').addEventListener('click', function() {
        buildingsVisible = !buildingsVisible;
        const btn = this;
        
        // Toggle building markers visibility
        Object.values(markers).forEach(marker => {
            if (marker.options.buildingMarker) {
                if (buildingsVisible) {
                    map.addLayer(marker);
                } else {
                    map.removeLayer(marker);
                }
            }
        });
        
        // Update button text and icon
        if (buildingsVisible) {
            btn.innerHTML = '<i class="bi bi-building me-1"></i> Hide Buildings';
            btn.classList.remove('btn-outline-warning');
            btn.classList.add('btn-outline-success');
        } else {
            btn.innerHTML = '<i class="bi bi-building me-1"></i> Show Buildings';
            btn.classList.remove('btn-outline-success');
            btn.classList.add('btn-outline-warning');
        }
    });
    
    // Add map legend toggle burger button
    const legendToggleControl = L.Control.extend({
        onAdd: function(map) {
            const container = L.DomUtil.create('div', 'leaflet-bar leaflet-control leaflet-control-custom map-legend-toggle');
            container.style.backgroundColor = 'rgba(255, 255, 255, 0.95)';
            container.style.padding = '8px';
            container.style.borderRadius = '8px';
            container.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
            container.style.border = '1px solid rgba(255,255,255,0.2)';
            container.style.cursor = 'pointer';
            container.style.zIndex = '1000';
            
            // Burger button
            container.innerHTML = `
                <div class="burger-menu" style="display: flex; flex-direction: column; gap: 3px; width: 20px;">
                    <div class="burger-line" style="width: 100%; height: 2px; background-color: #ff6b35; border-radius: 1px; transition: all 0.3s ease;"></div>
                    <div class="burger-line" style="width: 100%; height: 2px; background-color: #ff6b35; border-radius: 1px; transition: all 0.3s ease;"></div>
                    <div class="burger-line" style="width: 100%; height: 2px; background-color: #ff6b35; border-radius: 1px; transition: all 0.3s ease;"></div>
                </div>
            `;
            
            // Legend panel (initially hidden)
            const legendPanel = L.DomUtil.create('div', 'legend-panel');
            legendPanel.style.position = 'absolute';
            legendPanel.style.bottom = '50px';
            legendPanel.style.right = '0px';
            legendPanel.style.backgroundColor = 'rgba(255, 255, 255, 0.95)';
            legendPanel.style.padding = '12px 16px';
            legendPanel.style.borderRadius = '12px';
            legendPanel.style.boxShadow = '0 4px 20px rgba(0,0,0,0.15)';
            legendPanel.style.fontSize = '11px';
            legendPanel.style.minWidth = '320px';
            legendPanel.style.maxWidth = '400px';
            legendPanel.style.border = '1px solid rgba(255,255,255,0.2)';
            legendPanel.style.display = 'none';
            legendPanel.style.zIndex = '1001';
            legendPanel.style.backdropFilter = 'blur(10px)';
            
            legendPanel.innerHTML = `
                <div style="display: flex; align-items: center; margin-bottom: 10px; padding-bottom: 8px; border-bottom: 2px solid #ff6b35;">
                    <i class="bi bi-map" style="margin-right: 6px; color: #ff6b35; font-size: 14px;"></i>
                    <span style="font-weight: 600; color: #333; font-size: 12px;">Map Legend</span>
                </div>
                <div style="display: flex; gap: 20px;">
                    <div style="flex: 1;">
                        <div style="font-weight: 600; color: #666; margin-bottom: 6px; font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px;">Buildings</div>
                        <div style="display: flex; align-items: center; margin-bottom: 4px;">
                            <img src="https://cdn-icons-png.flaticon.com/512/2972/2972035.png" style="width: 16px; height: 16px; margin-right: 6px;">
                            <span style="font-size: 10px;">Fire Station</span>
                        </div>
                        <div style="display: flex; align-items: center; margin-bottom: 4px;">
                            <img src="https://cdn-icons-png.flaticon.com/512/619/619153.png" style="width: 16px; height: 16px; margin-right: 6px;">
                            <span style="font-size: 10px;">Residential</span>
                        </div>
                        <div style="display: flex; align-items: center; margin-bottom: 4px;">
                            <img src="../../images/commercial.png" style="width: 16px; height: 16px; margin-right: 6px;">
                            <span style="font-size: 10px;">Commercial</span>
                        </div>
                        <div style="display: flex; align-items: center; margin-bottom: 4px;">
                            <img src="../../images/industrial.png" style="width: 16px; height: 16px; margin-right: 6px;">
                            <span style="font-size: 10px;">Industrial</span>
                        </div>
                        <div style="display: flex; align-items: center;">
                            <img src="../../images/institutional.png" style="width: 16px; height: 16px; margin-right: 6px;">
                            <span style="font-size: 10px;">Institutional</span>
                        </div>
                    </div>
                    <div style="flex: 1;">
                        <div style="display: flex; align-items: center;">
                            <img src="../images/fire.png" style="width: 16px; height: 16px; margin-right: 6px;">
                            <span style="font-size: 10px;">Assigned</span>
                        </div>
                    </div>
                </div>
            `;
            
            // Add legend panel to map container
            map.getContainer().appendChild(legendPanel);
            
            let isOpen = false;
            
            // Toggle functionality
            container.onclick = function() {
                isOpen = !isOpen;
                
                if (isOpen) {
                    legendPanel.style.display = 'block';
                    // Animate burger to X
                    const lines = container.querySelectorAll('.burger-line');
                    lines[0].style.transform = 'rotate(45deg) translate(5px, 5px)';
                    lines[1].style.opacity = '0';
                    lines[2].style.transform = 'rotate(-45deg) translate(7px, -6px)';
                } else {
                    legendPanel.style.display = 'none';
                    // Reset burger lines
                    const lines = container.querySelectorAll('.burger-line');
                    lines[0].style.transform = 'none';
                    lines[1].style.opacity = '1';
                    lines[2].style.transform = 'none';
                }
            };
            
            return container;
        }
    });
    
    map.addControl(new legendToggleControl({ position: 'bottomright' }));
    
    // Store all markers
    var markers = {};

   // Add permanent fire station marker
        var fireStationMarker = L.marker([10.525467693871333, 122.84123838118607], {
            icon: L.divIcon({
                className: 'fire-station-icon',
                html: '<img src="https://cdn-icons-png.flaticon.com/512/2972/2972035.png" alt="Fire Station" style="width: 30px; height: 30px;">',
                iconSize: [35, 35],
                iconAnchor: [17, 17]
            })
        }).addTo(map);

    
    markers['fire_station'] = fireStationMarker;
    
    var fireStationPopupContent = `
        <div class="fire-station-popup">
            <h6><i class="bi bi-building"></i> Bago City Fire Station</h6>
            <p><i class="bi bi-telephone"></i> (034) 461-1234</p>
            <p><i class="bi bi-geo-alt"></i> Fire Station Location</p>
            <hr>
            <div class="emergency-badge">
                <i class="bi bi-exclamation-triangle-fill"></i> Emergency Contact
            </div>
            <button class="contact-btn">
                <i class="bi bi-telephone-plus"></i> Call Now
            </button>
        </div>`;
    
    fireStationMarker.bindPopup(fireStationPopupContent);
    
    // Add a function to zoom to fire station
    function zoomToFireStation() {
        map.setView([10.525467693871333, 122.84123838118607], 16);
        fireStationMarker.openPopup();
        
        Swal.fire({
            title: 'Fire Station Located',
            text: 'Bago City Fire Station has been highlighted on the map.',
            icon: 'info',
            timer: 2000,
            showConfirmButton: false
        });
    }
    
  
    
    // Add markers for buildings and devices
    <?php if (!empty($map_data)): ?>
        <?php foreach ($map_data as $item): ?>
            <?php if ($item['type'] === 'building'): ?>
                // Building marker
                var buildingMarker = L.marker([<?php echo $item['lat']; ?>, <?php echo $item['lng']; ?>], {
                    icon: L.divIcon({
                        className: 'building-icon',
                        html: '<img src="' + getBuildingIcon('<?php echo addslashes($buildings[array_search($item['id'], array_column($buildings, 'id'))]['building_type']); ?>') + '" style="width: 30px; height: 30px;">',
                        iconSize: [30, 30]
                    }),
                    buildingMarker: true
                }).addTo(map);
                
                markers['building_<?php echo $item['id']; ?>'] = buildingMarker;
                
                // Building popup content
                var buildingPopupContent = `<h6><?php echo addslashes($item['name']); ?></h6>`;
                buildingPopupContent += `<p class="mb-1"><small>Type: <?php echo addslashes($buildings[array_search($item['id'], array_column($buildings, 'id'))]['building_type']); ?></small></p>`;
                buildingPopupContent += `<p class="mb-1"><small>Devices: <?php echo count($item['devices']); ?></small></p>`;
                
                
                <?php if (!empty($item['devices'])): ?>
                    buildingPopupContent += `<hr><h6>Devices</h6>`;
                    <?php foreach ($item['devices'] as $device): ?>
                        buildingPopupContent += `<p class="mb-1"><small><?php echo addslashes($device['device_name']); ?> (<?php echo $device['status']; ?>)</small></p>`;
                    <?php endforeach; ?>
                <?php endif; ?>
                
                buildingMarker.bindPopup(buildingPopupContent);
                        
            <?php foreach ($item['devices'] as $device): ?>
                var deviceMarker = L.marker([
                    <?php echo $item['lat'] + 0.0001; ?>, 
                    <?php echo $item['lng'] + 0.0001; ?>
                ], {
                    icon: L.divIcon({
                        className: 'device-icon',
                        html: '<img src="../images/fire.png" style="width: 40px; height: 40px; position: relative; z-index: 200;" data-device-id="<?php echo $device['device_id']; ?>">',
                        iconSize: [25, 25]
                    }),
                    deviceMarker: true
                }).addTo(map);
           
                    
                    markers['device_<?php echo $device['device_id']; ?>'] = deviceMarker;
                    
                    var devicePopupContent = `<h6><?php echo addslashes($device['device_name']); ?></h6>`;
                    devicePopupContent += `<p class=\"mb-1\"><small>Status: <?php echo ucfirst($device['status']); ?></small></p>`;
                    devicePopupContent += `<p class=\"mb-1\"><small>Serial: <?php echo $device['serial_number']; ?></small></p>`;
                    devicePopupContent += `<p class=\"mb-1\"><small>Location: <?php echo addslashes($item['name']); ?></small></p>`;
                    devicePopupContent += `<div class=\"mt-2\">`
                        + `<button type=\"button\" class=\"btn btn-sm btn-outline-danger remove-device-btn\" `
                        + `data-device-id=\"<?php echo $device['device_id']; ?>\" `
                        + `data-device-name=\"<?php echo addslashes($device['device_name']); ?>\">`
                        + `<i class=\"bi bi-x-circle\"></i> Unassign`
                        + `</button>`
                    + `</div>`;

                    deviceMarker.bindPopup(devicePopupContent);
                <?php endforeach; ?>
            <?php elseif ($item['type'] === 'device'): ?>
                // Unassigned device marker
                var deviceMarker = L.marker([<?php echo $item['lat']; ?>, <?php echo $item['lng']; ?>], {
                    icon: L.divIcon({
                        className: 'device-icon',
                        html: '<i class="bi bi-router" style="font-size: 20px; color: <?php echo $item['device']['status'] === 'online' ? '#28a745' : ($item['device']['status'] === 'offline' ? '#6c757d' : '#dc3545'); ?>;"></i>',
                        iconSize: [25, 25]
                    }),
                    deviceMarker: true
                }).addTo(map);
                
                markers['device_<?php echo $item['id']; ?>'] = deviceMarker;
                
                var devicePopupContent = `<h6><?php echo addslashes($item['name']); ?></h6>`;
                devicePopupContent += `<p class="mb-1"><small>Status: <?php echo ucfirst($item['device']['status']); ?></small></p>`;
                devicePopupContent += `<p class="mb-1"><small>Serial: <?php echo $item['device']['serial_number']; ?></small></p>`;
                devicePopupContent += `<p class="mb-1 text-warning"><small>Unassigned to building</small></p>`;
                
                
                deviceMarker.bindPopup(devicePopupContent);
            <?php endif; ?>
        <?php endforeach; ?>
        
        // Fit map to show all markers
        if (Object.keys(markers).length > 0) {
            var group = new L.featureGroup(Object.values(markers));
            map.fitBounds(group.getBounds().pad(0.2));
        }
    <?php endif; ?>
    
    // Show welcome notification if everything is set up properly
    <?php if (!empty($devices) && !empty($buildings)): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const assignedCount = <?php echo count(array_filter($devices, function($d) { return $d['building_id'] !== null; })); ?>;
            const totalCount = <?php echo count($devices); ?>;
            
            if (assignedCount === 0) {
                Swal.fire({
                    title: 'Welcome to Device Assignment',
                    html: `
                        <div class="text-start">
                            <p>You have <strong>${totalCount}</strong> devices and <strong><?php echo count($buildings); ?></strong> buildings.</p>
                            <p>Start by assigning your devices to buildings for better monitoring and management.</p>
                        </div>
                    `,
                    icon: 'info',
                    confirmButtonText: 'Get Started'
                });
            }
        });
    <?php endif; ?>
    
    // Function to zoom to a specific location
    function zoomToLocation(lat, lng) {
        map.setView([lat, lng], 18);
        
        // Show success notification
        Swal.fire({
            title: 'Building Located',
            text: 'Building has been highlighted on the map.',
            icon: 'success',
            timer: 2000,
            showConfirmButton: false
        });
    }
    
    // Function to zoom to a specific device
    function zoomToDevice(deviceId) {
        var marker = markers['device_' + deviceId];
        if (marker) {
            map.setView(marker.getLatLng(), 18);
            marker.openPopup();
            
            // Show success notification
            Swal.fire({
                title: 'Device Located',
                text: 'Device has been highlighted on the map.',
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
            });
        } else {
            Swal.fire({
                title: 'Device Not Found',
                text: 'This device does not have location data or is not visible on the map.',
                icon: 'warning',
                confirmButtonText: 'OK'
            });
        }
    }
    
    // Initialize assign device modal
    var assignDeviceModal = new bootstrap.Modal(document.getElementById('assignDeviceModal'));
    var addDeviceModal = new bootstrap.Modal(document.getElementById('addDeviceModal'));
    var assignButtons = document.querySelectorAll('.assign-btn');
    
    assignButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            const deviceId = this.getAttribute('data-device-id');
            const deviceName = this.getAttribute('data-device-name');
            const serial = this.getAttribute('data-serial') || '';
            const status = this.getAttribute('data-status') || '';
            const locationText = this.getAttribute('data-location') || '';
            const deviceData = { id: deviceId, name: deviceName, serial: serial, status: status, location: locationText };
            
            // Populate modal with device information
            document.getElementById('assignDeviceId').value = deviceData.id;
            document.getElementById('assignDeviceName').textContent = deviceData.name;
            document.getElementById('assignDeviceSerial').textContent = deviceData.serial;
            
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
            if (deviceData.location && deviceData.location.includes('Unassigned')) {
                locationElement.innerHTML = '<i class="bi bi-exclamation-circle me-1"></i> Unassigned';
                locationElement.style.color = '#ffc107';
            } else {
                locationElement.innerHTML = '<i class="bi bi-building me-1"></i> ' + (deviceData.location || 'Unknown');
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
                const summaryType = document.getElementById('summaryType');
                const summaryLocation = document.getElementById('summaryLocation');
                const assignDeviceName = document.getElementById('assignDeviceName');
                
                if (summaryDevice && assignDeviceName) summaryDevice.textContent = assignDeviceName.textContent || '-';
                if (summaryBuilding) summaryBuilding.textContent = selectedOption.text || '-';
                if (summaryType) summaryType.textContent = selectedOption.getAttribute('data-type') || '-';
                if (summaryLocation) summaryLocation.textContent = selectedOption.getAttribute('data-address') || '-';
                
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
    
    // Handle remove device (supports both static and dynamic buttons)
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

    // Delegate for dynamically created buttons (e.g., inside Leaflet popups)
    document.addEventListener('click', function(e){
        const btn = e.target.closest('.remove-device-btn');
        if (btn) {
            e.preventDefault();
            handleUnassignClick(btn);
        }
    });
    
    // Toggle active
    document.querySelectorAll('.toggle-active-btn').forEach(function(btn){
        btn.addEventListener('click', function(){
            const deviceId = this.getAttribute('data-device-id');
            const isActive = this.getAttribute('data-active') === '1' ? 0 : 1;
            const formData = new FormData();
            formData.append('toggle_active', '1');
            formData.append('device_id', deviceId);
            formData.append('is_active', isActive);
            fetch(window.location.href, { method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(r=>r.json())
                .then(data=>{
                    if(data.success){ location.reload(); } else { Swal.fire('Error', data.message, 'error'); }
                })
                .catch(()=> Swal.fire('Error', 'Request failed', 'error'));
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

    // Secondary Add Device button (in second card)
    const addDeviceBtnSecondary = document.getElementById('addDeviceBtnSecondary');
    if (addDeviceBtnSecondary) {
        addDeviceBtnSecondary.addEventListener('click', function(){
            const form = document.getElementById('addDeviceForm');
            if (form) form.reset();
            (document.getElementById('editDeviceId')||{}).value = '';
            const modalTitle = document.getElementById('addDeviceModalLabel');
            if (modalTitle) modalTitle.innerHTML = '<i class="bi bi-plus-circle me-2" style="font-size: 1.2rem;"></i>Add New Device';
            const submitBtn = document.getElementById('addDeviceSubmitBtn');
            if (submitBtn) submitBtn.innerHTML = '<i class="bi bi-check me-1"></i> Save Device';
            addDeviceModal.show();
        });
    }

    // Filters and realtime refresh for device cards
    const searchInput = document.getElementById('deviceSearchInput');
    const buildingTypeSelect = document.getElementById('buildingTypeFilter');
    const startDateInput = document.getElementById('startDateFilter');
    const endDateInput = document.getElementById('endDateFilter');
    const clearFiltersBtn = document.getElementById('clearFiltersBtn');
    const devicesGrid = document.getElementById('devicesGrid');

    let fetchAbort = null;
    let fetchTimer = null;

    function getFilters(){
        return {
            q: (searchInput?.value || '').trim(),
            building_type: (buildingTypeSelect?.value || '').trim(),
            start_date: (startDateInput?.value || '').trim(),
            end_date: (endDateInput?.value || '').trim()
        };
    }

    function buildQuery(params){
        const usp = new URLSearchParams();
        Object.entries(params).forEach(([k,v])=>{ if(v) usp.append(k,v); });
        return usp.toString();
    }

    function scheduleFetch(){
        if (fetchTimer) clearTimeout(fetchTimer);
        fetchTimer = setTimeout(fetchDevices, 250);
    }

    function fetchDevices(){
        if (!devicesGrid) return;
        const qs = buildQuery(getFilters());
        const url = 'get_devices.php' + (qs ? ('?' + qs) : '');

        if (fetchAbort) { fetchAbort.abort(); }
        fetchAbort = new AbortController();
        const signal = fetchAbort.signal;

        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, signal })
            .then(r=>r.ok ? r.json() : Promise.reject(new Error('Network')))
            .then(payload=>{
                if (payload && typeof payload.html === 'string') {
                    devicesGrid.innerHTML = payload.html;
                }
                // Re-bind assign/update/delete button handlers for the new cards
                rebindCardActionButtons();
            })
            .catch(()=>{})
            .finally(()=>{ fetchAbort = null; });
    }

    function rebindCardActionButtons(){
        document.querySelectorAll('.assign-btn').forEach(function(button){
            button.onclick = null;
        });
        // Re-run the original binding for assign buttons
        document.querySelectorAll('.assign-btn').forEach(function(button) {
            button.addEventListener('click', function() {
                const deviceId = this.getAttribute('data-device-id');
                const deviceName = this.getAttribute('data-device-name');
                const serial = this.getAttribute('data-serial') || '';
                const status = this.getAttribute('data-status') || '';
                const locationText = this.getAttribute('data-location') || '';
                const deviceData = { id: deviceId, name: deviceName, serial: serial, status: status, location: locationText };
                document.getElementById('assignDeviceId').value = deviceData.id;
                document.getElementById('assignDeviceName').textContent = deviceData.name;
                document.getElementById('assignDeviceSerial').textContent = deviceData.serial;
                const originalBadge = this.closest('.card').querySelector('.badge');
                const statusBadge = document.getElementById('assignDeviceStatus');
                statusBadge.className = 'status-badge';
                if (originalBadge.classList.contains('bg-success')) { statusBadge.classList.add('bg-success'); statusBadge.textContent = 'Online'; }
                else if (originalBadge.classList.contains('bg-secondary')) { statusBadge.classList.add('bg-secondary'); statusBadge.textContent = 'Offline'; }
                else if (originalBadge.classList.contains('bg-danger')) { statusBadge.classList.add('bg-danger'); statusBadge.textContent = 'Error'; }
                const locationElement = document.getElementById('assignDeviceLocation');
                if (deviceData.location && deviceData.location.includes('Unassigned')) {
                    locationElement.innerHTML = '<i class="bi bi-exclamation-circle me-1"></i> Unassigned';
                    locationElement.style.color = '#ffc107';
                } else {
                    locationElement.innerHTML = '<i class="bi bi-building me-1"></i> ' + (deviceData.location || 'Unknown');
                    locationElement.style.color = '#28a745';
                }
                document.getElementById('building_id').value = '';
                document.getElementById('buildingDetails').style.display = 'none';
                document.getElementById('assignmentSummary').style.display = 'none';
                document.getElementById('assignSubmitBtn').disabled = true;
                assignDeviceModal.show();
            });
        });
        // Similarly rebind delete/update/toggle handlers if needed in future
    }

    if (searchInput) searchInput.addEventListener('input', scheduleFetch);
    if (buildingTypeSelect) buildingTypeSelect.addEventListener('change', scheduleFetch);
    if (startDateInput) startDateInput.addEventListener('change', scheduleFetch);
    if (endDateInput) endDateInput.addEventListener('change', scheduleFetch);
    if (clearFiltersBtn) clearFiltersBtn.addEventListener('click', function(){
        if (searchInput) searchInput.value = '';
        if (buildingTypeSelect) buildingTypeSelect.value = '';
        if (startDateInput) startDateInput.value = '';
        if (endDateInput) endDateInput.value = '';
        fetchDevices();
    });

    // Real-time refresh every 12 seconds, respecting current filters
    setInterval(fetchDevices, 12000);

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

    // Update status
    document.querySelectorAll('.update-status').forEach(function(a){
        a.addEventListener('click', function(e){
            e.preventDefault();
            const status = this.getAttribute('data-status');
            const deviceId = this.getAttribute('data-device-id');
            const formData = new FormData();
            formData.append('update_status', '1');
            formData.append('device_id', deviceId);
            formData.append('status', status);
            fetch(window.location.href, { method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(r=>r.json())
                .then(data=>{ if(data.success){ location.reload(); } else { Swal.fire('Error', data.message, 'error'); } })
                .catch(()=> Swal.fire('Error', 'Request failed', 'error'));
        });
    });

    // Update WiFi credentials
    document.querySelectorAll('.update-wifi-btn').forEach(function(btn){
        btn.addEventListener('click', function(){
            const deviceId = this.getAttribute('data-device-id');
            const currentSsid = this.getAttribute('data-ssid') || '';
            Swal.fire({
                title: 'Update WiFi Credentials',
                html: `
                    <input id="wifi-ssid" class="swal2-input" placeholder="WiFi SSID" value="${currentSsid}">
                    <input id="wifi-pass" class="swal2-input" placeholder="WiFi Password" type="password">
                `,
                focusConfirm: false,
                preConfirm: () => {
                    const ssid = (document.getElementById('wifi-ssid')||{}).value || '';
                    const pass = (document.getElementById('wifi-pass')||{}).value || '';
                    return { ssid, pass };
                },
                showCancelButton: true,
                confirmButtonText: 'Save'
            }).then((res)=>{
                if(res.isConfirmed){
                    const formData = new FormData();
                    formData.append('update_wifi', '1');
                    formData.append('device_id', deviceId);
                    formData.append('wifi_ssid', res.value.ssid);
                    formData.append('wifi_password', res.value.pass);
                    fetch(window.location.href, { method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                        .then(r=>r.json())
                        .then(data=>{ if(data.success){ location.reload(); } else { Swal.fire('Error', data.message, 'error'); } })
                        .catch(()=> Swal.fire('Error', 'Request failed', 'error'));
                }
            });
        });
    });

    // Touch last activity
    document.querySelectorAll('.touch-activity-btn').forEach(function(btn){
        btn.addEventListener('click', function(){
            const deviceId = this.getAttribute('data-device-id');
            const formData = new FormData();
            formData.append('touch_last_activity', '1');
            formData.append('device_id', deviceId);
            fetch(window.location.href, { method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(r=>r.json())
                .then(data=>{ if(data.success){ location.reload(); } else { Swal.fire('Error', data.message, 'error'); } })
                .catch(()=> Swal.fire('Error', 'Request failed', 'error'));
        });
    });

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

    function createFireTooltip(element, deviceId = null) {
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
            loadFireStats(tooltip, deviceId);
        });
        
        element.addEventListener('mouseleave', function() {
            // Optional: Add any cleanup here
        });
    }

    function loadFireStats(tooltipElement, deviceId = null) {
        const now = Date.now();
        
        // Use cached data if available and not expired
        if (fireStatsCache && (now - fireStatsCacheTime) < FIRE_CACHE_DURATION) {
            renderFireStats(tooltipElement, fireStatsCache, deviceId);
            return;
        }
        
        // Build URL with device ID parameter if provided
        let url = 'get_fire_stats.php';
        if (deviceId) {
            url += '?device_id=' + encodeURIComponent(deviceId);
        }
        
        // Fetch fresh data
        fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                fireStatsCache = data;
                fireStatsCacheTime = now;
                renderFireStats(tooltipElement, data, deviceId);
            } else {
                showFireErrorInTooltip(tooltipElement, data.message || 'Failed to load fire statistics');
            }
        })
        .catch(error => {
            console.error('Error loading fire stats:', error);
            showFireErrorInTooltip(tooltipElement, 'Failed to load fire statistics');
        });
    }

    function renderFireStats(tooltipElement, data, deviceId = null) {
        const fireStats = data.fire_stats;
        const deviceStats = data.device_stats;
        const buildingStats = data.building_stats;
        const recentIncidents = data.recent_incidents;
        const calendarData = data.calendar_data;
        const buildingIncidents = data.building_incidents;
        const deviceIncidents = data.device_incidents;
        
        // If deviceId is provided, show device-specific data
        if (deviceId) {
            const deviceData = data.device_data || {};
            const deviceFireData = data.device_fire_data || [];
            const deviceBuilding = data.device_building || {};
            
            // Format calendar data for this device
            const calendarHtml = generateCalendarGrid(calendarData);
            
            // Format recent fire incidents for this device
            const deviceIncidentsHtml = deviceFireData.slice(0, 4).map(incident => {
                const riskLevel = getRiskLevel(incident.ml_confidence, incident.flame_detected, incident.smoke, incident.temp);
                return `
                    <div class="fire-incident-item">
                        <div class="fire-incident-device">${incident.timestamp}</div>
                        <div class="fire-incident-details">
                            Smoke: ${incident.smoke} | Temp: ${incident.temp}°C | Heat: ${incident.heat}
                            <br>ML: ${incident.ml_confidence}% | Flame: ${incident.flame_detected ? 'YES' : 'NO'} | Status: ${incident.status}
                        </div>
                        <span class="fire-incident-status ${riskLevel}">${riskLevel.toUpperCase()}</span>
                    </div>
                `;
            }).join('');
            
            tooltipElement.innerHTML = `
                <div class="fire-tooltip-header">
                    <i class="bi bi-fire"></i>
                    Device Fire Statistics & Calendar
                </div>
                <div class="fire-tooltip-body">
                    <div class="fire-stats-grid">
                        <div class="fire-stat-item">
                            <span class="fire-stat-number ${deviceData.status === 'online' ? 'low-value' : deviceData.status === 'offline' ? 'medium-value' : 'high-value'}">${deviceData.status || 'N/A'}</span>
                            <span class="fire-stat-label">Device Status</span>
                        </div>
                        <div class="fire-stat-item">
                            <span class="fire-stat-number ${deviceFireData.length > 10 ? 'high-value' : deviceFireData.length > 5 ? 'medium-value' : 'low-value'}">${deviceFireData.length || 0}</span>
                            <span class="fire-stat-label">Total Readings</span>
                        </div>
                        <div class="fire-stat-item">
                            <span class="fire-stat-number ${deviceFireData.filter(d => d.ml_prediction === 1).length > 3 ? 'high-value' : deviceFireData.filter(d => d.ml_prediction === 1).length > 1 ? 'medium-value' : 'low-value'}">${deviceFireData.filter(d => d.ml_prediction === 1).length || 0}</span>
                            <span class="fire-stat-label">Fire Predictions</span>
                        </div>
                        <div class="fire-stat-item">
                            <span class="fire-stat-number ${deviceFireData.filter(d => d.flame_detected === 1).length > 2 ? 'high-value' : deviceFireData.filter(d => d.flame_detected === 1).length > 0 ? 'medium-value' : 'low-value'}">${deviceFireData.filter(d => d.flame_detected === 1).length || 0}</span>
                            <span class="fire-stat-label">Flame Detections</span>
                        </div>
                        <div class="fire-stat-item">
                            <span class="fire-stat-number ${deviceData.is_active ? 'low-value' : 'high-value'}">${deviceData.is_active ? 'ACTIVE' : 'INACTIVE'}</span>
                            <span class="fire-stat-label">Device Active</span>
                        </div>
                        <div class="fire-stat-item">
                            <span class="fire-stat-number low-value">${deviceData.device_type || 'N/A'}</span>
                            <span class="fire-stat-label">Device Type</span>
                        </div>
                        <div class="fire-stat-item">
                            <span class="fire-stat-number low-value">${deviceData.device_number || 'N/A'}</span>
                            <span class="fire-stat-label">Device Number</span>
                        </div>
                    </div>
                    
                    <div class="fire-content-columns">
                        <!-- Left Column: Device Information -->
                        <div class="fire-column">
                            <div class="fire-section">
                                <div class="fire-section-title">
                                    <i class="bi bi-router"></i>
                                    Device Information
                                </div>
                                <div style="font-size: 0.5rem; color: #000000; line-height: 1.0;">
                                    <div style="margin-bottom: 0px;"><strong>Name:</strong> ${deviceData.device_name || 'N/A'}</div>
                                    <div style="margin-bottom: 0px;"><strong>Serial:</strong> ${deviceData.serial_number || 'N/A'}</div>
                                    <div style="margin-bottom: 0px;"><strong>Status:</strong> <span style="color: ${deviceData.status === 'online' ? '#28a745' : deviceData.status === 'offline' ? '#fd7e14' : '#dc3545'};">${deviceData.status || 'N/A'}</span></div>
                                    <div style="margin-bottom: 0px;"><strong>Active:</strong> <span style="color: ${deviceData.is_active ? '#28a745' : '#dc3545'};">${deviceData.is_active ? 'YES' : 'NO'}</span></div>
                                    <div><strong>WiFi:</strong> ${deviceData.wifi_ssid || 'Not Set'}</div>
                                </div>
                            </div>
                            
                            <div class="fire-section">
                                <div class="fire-section-title">
                                    <i class="bi bi-building"></i>
                                    Building Information
                                </div>
                                <div style="font-size: 0.5rem; color: #000000; line-height: 1.0;">
                                    <div style="margin-bottom: 0px;"><strong>Building:</strong> ${deviceBuilding.building_name || 'Unassigned'}</div>
                                    <div style="margin-bottom: 0px;"><strong>Type:</strong> ${deviceBuilding.building_type || 'N/A'}</div>
                                    <div style="margin-bottom: 0px;"><strong>Floors:</strong> ${deviceBuilding.total_floors || 'N/A'}</div>
                                    <div style="margin-bottom: 0px;"><strong>Fire Alarm:</strong> <span style="color: ${deviceBuilding.has_fire_alarm ? '#28a745' : '#dc3545'};">${deviceBuilding.has_fire_alarm ? 'YES' : 'NO'}</span></div>
                                    <div style="margin-bottom: 0px;"><strong>Sprinkler:</strong> <span style="color: ${deviceBuilding.has_sprinkler_system ? '#28a745' : '#dc3545'};">${deviceBuilding.has_sprinkler_system ? 'YES' : 'NO'}</span></div>
                                    <div><strong>Last Inspected:</strong> ${deviceBuilding.last_inspected || 'Never'}</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Right Column: Fire Data & Calendar -->
                        <div class="fire-column">
                            <div class="fire-section">
                                <div class="fire-section-title">
                                    <i class="bi bi-clock-history"></i>
                                    Recent Fire Readings
                                </div>
                                ${deviceIncidentsHtml || '<div style="color: #6c757d; font-size: 0.8rem; text-align: center;">No recent readings</div>'}
                            </div>
                            
                            <div class="fire-section">
                                <div class="fire-section-title">
                                    <i class="bi bi-calendar3"></i>
                                    Fire Calendar (Last 30 Days)
                                </div>
                                ${calendarHtml}
                            </div>
                            
                            <div class="fire-section">
                                <div class="fire-section-title">
                                    <i class="bi bi-graph-up"></i>
                                    Fire Data Summary
                                </div>
                                <div style="font-size: 0.5rem; color: #000000; line-height: 1.0;">
                                    <div style="margin-bottom: 0px;"><strong>Avg Smoke:</strong> ${deviceFireData.length > 0 ? (deviceFireData.reduce((sum, d) => sum + d.smoke, 0) / deviceFireData.length).toFixed(0) : 'N/A'}</div>
                                    <div style="margin-bottom: 0px;"><strong>Avg Temp:</strong> ${deviceFireData.length > 0 ? (deviceFireData.reduce((sum, d) => sum + d.temp, 0) / deviceFireData.length).toFixed(1) : 'N/A'}°C</div>
                                    <div style="margin-bottom: 0px;"><strong>Max ML:</strong> ${deviceFireData.length > 0 ? Math.max(...deviceFireData.map(d => d.ml_confidence || 0)).toFixed(1) : 'N/A'}%</div>
                                    <div><strong>High Risk:</strong> <span style="color: #dc3545;">${deviceFireData.filter(d => d.ml_confidence > 80 || d.flame_detected === 1).length || 0}</span></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        } else {
            // Original system-wide view (fallback)
            const calendarHtml = generateCalendarGrid(calendarData);
            
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
                            <span class="fire-stat-number ${fireStats.total_incidents > 10 ? 'high-value' : fireStats.total_incidents > 5 ? 'medium-value' : 'low-value'}">${fireStats.total_incidents}</span>
                            <span class="fire-stat-label">Total Incidents</span>
                        </div>
                        <div class="fire-stat-item">
                            <span class="fire-stat-number ${fireStats.ml_predictions > 5 ? 'high-value' : fireStats.ml_predictions > 2 ? 'medium-value' : 'low-value'}">${fireStats.ml_predictions}</span>
                            <span class="fire-stat-label">Fire Predictions</span>
                        </div>
                        <div class="fire-stat-item">
                            <span class="fire-stat-number ${fireStats.flame_detections > 3 ? 'high-value' : fireStats.flame_detections > 1 ? 'medium-value' : 'low-value'}">${fireStats.flame_detections}</span>
                            <span class="fire-stat-label">Flame Detections</span>
                        </div>
                        <div class="fire-stat-item">
                            <span class="fire-stat-number ${fireStats.avg_ml_confidence > 80 ? 'high-value' : fireStats.avg_ml_confidence > 60 ? 'medium-value' : 'low-value'}">${fireStats.avg_ml_confidence}%</span>
                            <span class="fire-stat-label">Avg ML Confidence</span>
                        </div>
                        <div class="fire-stat-item">
                            <span class="fire-stat-number low-value">${deviceStats.total_devices}</span>
                            <span class="fire-stat-label">Total Devices</span>
                        </div>
                        <div class="fire-stat-item">
                            <span class="fire-stat-number ${deviceStats.online_devices / deviceStats.total_devices > 0.8 ? 'low-value' : deviceStats.online_devices / deviceStats.total_devices > 0.5 ? 'medium-value' : 'high-value'}">${deviceStats.online_devices}</span>
                            <span class="fire-stat-label">Online Devices</span>
                        </div>
                        <div class="fire-stat-item">
                            <span class="fire-stat-number low-value">${buildingStats.total_buildings}</span>
                            <span class="fire-stat-label">Total Buildings</span>
                        </div>
                        <div class="fire-stat-item">
                            <span class="fire-stat-number ${buildingStats.buildings_with_alarms / buildingStats.total_buildings > 0.8 ? 'low-value' : buildingStats.buildings_with_alarms / buildingStats.total_buildings > 0.5 ? 'medium-value' : 'high-value'}">${buildingStats.buildings_with_alarms}</span>
                            <span class="fire-stat-label">With Fire Alarms</span>
                        </div>
                    </div>
                    
                    <div class="fire-content-columns">
                        <!-- Left Column: Statistics -->
                        <div class="fire-column">
                            <div class="fire-section">
                                <div class="fire-section-title">
                                    <i class="bi bi-graph-up"></i>
                                    Fire Statistics
                                </div>
                                <div style="font-size: 0.6rem; color: #000000; line-height: 1.2;">
                                    <div style="margin-bottom: 2px;"><strong>Coverage:</strong> ${((buildingStats.buildings_with_alarms / buildingStats.total_buildings) * 100).toFixed(1)}%</div>
                                    <div style="margin-bottom: 2px;"><strong>Health:</strong> ${((deviceStats.online_devices / deviceStats.total_devices) * 100).toFixed(1)}%</div>
                                    <div style="margin-bottom: 2px;"><strong>Response:</strong> ${fireStats.avg_response_time || 'N/A'} min</div>
                                    <div><strong>Updated:</strong> ${new Date().toLocaleTimeString()}</div>
                                </div>
                            </div>
                            
                            <div class="fire-section">
                                <div class="fire-section-title">
                                    <i class="bi bi-shield-check"></i>
                                    Risk Assessment
                                </div>
                                <div style="font-size: 0.6rem; color: #000000; line-height: 1.2;">
                                    <div style="margin-bottom: 2px;"><strong>High:</strong> <span style="color: #dc3545;">${fireStats.high_risk_incidents || 0}</span></div>
                                    <div style="margin-bottom: 2px;"><strong>Medium:</strong> <span style="color: #fd7e14;">${fireStats.medium_risk_incidents || 0}</span></div>
                                    <div style="margin-bottom: 2px;"><strong>Low:</strong> <span style="color: #28a745;">${fireStats.low_risk_incidents || 0}</span></div>
                                    <div><strong>Status:</strong> <span style="color: ${fireStats.total_incidents > 10 ? '#dc3545' : fireStats.total_incidents > 5 ? '#fd7e14' : '#28a745'};">${fireStats.total_incidents > 10 ? 'HIGH' : fireStats.total_incidents > 5 ? 'MOD' : 'NORM'}</span></div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Right Column: Calendar and System Overview -->
                        <div class="fire-column">
                            <div class="fire-section">
                                <div class="fire-section-title">
                                    <i class="bi bi-calendar3"></i>
                                    Fire Calendar (Last 30 Days)
                                </div>
                                ${calendarHtml}
                            </div>
                            
                            <div class="fire-section">
                                <div class="fire-section-title">
                                    <i class="bi bi-graph-up"></i>
                                    System Overview
                                </div>
                                <div style="font-size: 0.6rem; color: #000000; line-height: 1.2;">
                                    <div style="margin-bottom: 2px;"><strong>Total Devices:</strong> ${deviceStats.total_devices}</div>
                                    <div style="margin-bottom: 2px;"><strong>Online Devices:</strong> ${deviceStats.online_devices}</div>
                                    <div style="margin-bottom: 2px;"><strong>Total Buildings:</strong> ${buildingStats.total_buildings}</div>
                                    <div style="margin-bottom: 2px;"><strong>Buildings with Alarms:</strong> ${buildingStats.buildings_with_alarms}</div>
                                    <div><strong>System Status:</strong> <span style="color: ${deviceStats.online_devices / deviceStats.total_devices > 0.8 ? '#28a745' : deviceStats.online_devices / deviceStats.total_devices > 0.5 ? '#fd7e14' : '#dc3545'};">${deviceStats.online_devices / deviceStats.total_devices > 0.8 ? 'HEALTHY' : deviceStats.online_devices / deviceStats.total_devices > 0.5 ? 'WARNING' : 'CRITICAL'}</span></div>
                                </div>
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
                    </div>
                </div>
            `;
        }
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
            
            // Extract device ID from data attribute if available
            const deviceId = img.getAttribute('data-device-id') || null;
            
            // Wrap the image with tooltip container
            const wrapper = document.createElement('span');
            wrapper.className = 'fire-tooltip';
            img.parentNode.insertBefore(wrapper, img);
            wrapper.appendChild(img);
            
            // Create the tooltip with device ID
            createFireTooltip(wrapper, deviceId);
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
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

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

<!-- DataTables JavaScript -->
    

    

<!-- Custom Theme Scripts -->
<script src="../../../build/js/custom.min.js"></script>
</body>
</html>