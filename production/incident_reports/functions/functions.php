<?php
// incident_reports.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../php/config.php'; // Your database configuration file

// Only enforce redirect for browser pages, not API endpoints
if (!defined('INCIDENT_REPORTS_ALLOW_API')) {
    if (!isset($_SESSION['admin_id'])) {
        header("Location: ../../index.php");
        exit();
    }
}

// Function to get incident details with additional safety information
function getIncidentDetails($incidentId) {
    $conn = getDatabaseConnection();
    
    $query = "SELECT fd.*, 
                     b.building_name, b.address as building_address, b.building_type, b.contact_person, b.contact_number,
                     b.total_floors, b.has_sprinkler_system, b.has_fire_alarm, b.has_fire_extinguishers,
                     b.has_emergency_exits, b.has_emergency_lighting, b.has_fire_escape, b.last_inspected,
                     u.fullname as owner_name, u.email_address as owner_email, u.contact_number as owner_phone,
                     d.device_name, d.device_number, d.serial_number, d.status as device_status,
                     a.acknowledged_at, a.acknowledged_by,
                     r.response_type, r.notes, r.responded_by, r.timestamp as response_time,
                     ff.name as firefighter_name, ff.rank as firefighter_rank, ff.badge_number, ff.specialization,
                     adm.full_name as admin_name, adm.role as admin_role
              FROM fire_data fd
              LEFT JOIN buildings b ON fd.building_id = b.id
              LEFT JOIN users u ON fd.user_id = u.user_id
              LEFT JOIN devices d ON fd.device_id = d.device_id
              LEFT JOIN acknowledgments a ON a.fire_data_id = fd.id
              LEFT JOIN responses r ON r.fire_data_id = fd.id
              LEFT JOIN firefighters ff ON r.firefighter_id = ff.id
              LEFT JOIN admin adm ON a.acknowledged_by = adm.admin_id
              WHERE fd.id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$incidentId]);
    
    return $stmt->fetch();
}

// Function to get sensor data for an incident with more details
function getSensorData($incidentId) {
    $conn = getDatabaseConnection();
    
    $query = "SELECT s.sensor_id, s.sensor_type, s.location, s.status,
                     sl.temperature, sl.heat_level, sl.smoke_level, sl.flame_detected, sl.created_at,
                     sl.log_message, sl.log_level
              FROM system_logs sl
              JOIN sensors s ON sl.device_id = s.sensor_id
              WHERE sl.fire_data_id = ?
              ORDER BY sl.created_at DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$incidentId]);
    
    return $stmt->fetchAll();
}

// Function to get all acknowledged incidents with pagination
function getAcknowledgedIncidents($page = 1, $perPage = 10) {
    $conn = getDatabaseConnection();
    
    $offset = ($page - 1) * $perPage;
    
    $query = "SELECT fd.id, fd.timestamp as incident_time, fd.status, fd.temp, fd.smoke, fd.heat, fd.flame_detected,
                     b.building_name, b.address as building_address,
                     u.fullname as owner_name,
                     a.acknowledged_at, a.acknowledged_by,
                     adm.full_name as admin_name
              FROM fire_data fd
              JOIN acknowledgments a ON a.fire_data_id = fd.id
              LEFT JOIN buildings b ON fd.building_id = b.id
              LEFT JOIN users u ON fd.user_id = u.user_id
              LEFT JOIN admin adm ON a.acknowledged_by = adm.admin_id
              WHERE fd.status = 'ACKNOWLEDGED'
              ORDER BY fd.timestamp DESC
              LIMIT ?, ?";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$offset, $perPage]);
    
    return $stmt->fetchAll();
}

// Function to count total incidents for pagination
function countAcknowledgedIncidents() {
    $conn = getDatabaseConnection();
    
    $query = "SELECT COUNT(*) as total FROM fire_data fd
              JOIN acknowledgments a ON a.fire_data_id = fd.id
              WHERE fd.status = 'ACKNOWLEDGED'";
    
    $result = $conn->query($query);
    return $result->fetch()['total'];
}

// Function to get acknowledged incidents with search and filter
function getAcknowledgedIncidentsFiltered($status = '', $start_date = '', $end_date = '', $page = 1, $perPage = 10) {
    $conn = getDatabaseConnection();
    $offset = ($page - 1) * $perPage;
    $params = [];
    $types = '';
    $where = ["fd.status = 'ACKNOWLEDGED'"];
    $query = "SELECT fd.id, fd.timestamp as incident_time, fd.status, fd.temp, fd.smoke, fd.heat, fd.flame_detected,
                     b.building_name, b.address as building_address,
                     u.fullname as owner_name,
                     a.acknowledged_at, a.acknowledged_by,
                     adm.full_name as admin_name
              FROM fire_data fd
              JOIN acknowledgments a ON a.fire_data_id = fd.id
              LEFT JOIN buildings b ON fd.building_id = b.id
              LEFT JOIN users u ON fd.user_id = u.user_id
              LEFT JOIN admin adm ON a.acknowledged_by = adm.admin_id";
    if ($start_date !== '') {
        $where[] = "DATE(fd.timestamp) >= ?";
        $params[] = $start_date;
        $types .= 's';
    }
    if ($end_date !== '') {
        $where[] = "DATE(fd.timestamp) <= ?";
        $params[] = $end_date;
        $types .= 's';
    }
    if ($where) {
        $query .= " WHERE " . implode(' AND ', $where);
    }
    $query .= " ORDER BY fd.timestamp DESC LIMIT ?, ?";
    $params[] = $offset;
    $params[] = $perPage;
    $types .= 'ii';
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll();
}


// Function to migrate all incidents from fire_data to incident_reports
function migrateAllIncidentsToIncidentReports() {
    $conn = getDatabaseConnection();
    // Fetch all incidents from fire_data
    $result = $conn->query("SELECT * FROM fire_data");
    if (!$result) {
        return ["success" => false, "error" => "Database query failed"];
    }
    $inserted = 0;
    $errors = [];
    while ($row = $result->fetch()) {
        // Assign each value to a variable for bind_param
        $timestamp = $row['timestamp'];
        $status = $row['status'];
        $temp = $row['temp'];
        $smoke = $row['smoke'];
        $heat = $row['heat'];
        $flame_detected = $row['flame_detected'];
        $geo_lat = $row['geo_lat'];
        $geo_long = $row['geo_long'];
        $building_id = $row['building_id'];
        $user_id = $row['user_id'];
        $device_id = $row['device_id'];
        $description = isset($row['description']) ? $row['description'] : null;
        // Prepare insert statement for incident_reports
        $stmt = $conn->prepare("INSERT INTO incident_reports (
            timestamp, status, temp, smoke, heat, flame_detected, geo_lat, geo_long, building_id, user_id, device_id, description
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt) {
            $errors[] = "Statement preparation failed";
            continue;
        }
        if ($stmt->execute([
            $timestamp,
            $status,
            $temp,
            $smoke,
            $heat,
            $flame_detected,
            $geo_lat,
            $geo_long,
            $building_id,
            $user_id,
            $device_id,
            $description
        ])) {
            $inserted++;
        } else {
            $errors[] = "Statement execution failed";
        }
    }
    return ["success" => true, "inserted" => $inserted, "errors" => $errors];
}

// Function to get user address based on device number or device_id
function getUserAddressByDevice($deviceIdentifier) {
    $conn = getDatabaseConnection();
    // Accepts either device_number (string) or device_id (int)
    $query = "SELECT u.address
              FROM devices d
              JOIN users u ON d.user_id = u.user_id
              WHERE d.device_number = ? OR d.device_id = ?
              LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->execute([$deviceIdentifier, $deviceIdentifier]);
    if ($row = $stmt->fetch()) {
        return $row['address'];
    }
    return null;
}


// Pagination setup
$currentPage = isset($_GET['page']) ? intval($_GET['page']) : 1;
$perPage = 10;
$totalIncidents = countAcknowledgedIncidents();
$totalPages = ceil($totalIncidents / $perPage);

// Get all acknowledged incidents for current page
$incidents = getAcknowledgedIncidents($currentPage, $perPage);

// Check if viewing a specific incident
$incidentDetails = null;
$sensorData = [];
if (isset($_GET['incident_id'])) {
    $incidentId = intval($_GET['incident_id']);
    $incidentDetails = getIncidentDetails($incidentId);
    $sensorData = getSensorData($incidentId);
}

// Generate PDF report
if (isset($_POST['generate_pdf'])) {
    require_once '../TCPDF-main/tcpdf.php'; // Make sure to include TCPDF library
    
    $incidentId = intval($_POST['incident_id']);
    $incident = getIncidentDetails($incidentId);
    $sensors = getSensorData($incidentId);
    
    // Create new PDF document
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('Bago City Fire Station');
    $pdf->SetAuthor('Fire Incident System');
    $pdf->SetTitle('Fire Incident Report - ' . $incidentId);
    $pdf->SetSubject('Fire Incident Report');
    
    // Add a page
    $pdf->AddPage();
    
    // Set font
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'FIRE INCIDENT REPORT', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Ln(10);
    
    // Incident Details
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, '1. Incident Details', 0, 1);
    $pdf->SetFont('helvetica', '', 12);
    
    $html = '<table border="1" cellpadding="5">
        <tr>
            <th width="40%">Field</th>
            <th width="80%">Value</th>
        </tr>
        <tr>
            <td>Incident ID</td>
            <td>' . $incident['id'] . '</td>
        </tr>
        <tr>
            <td>Date and Time</td>
            <td>' . $incident['timestamp'] . '</td>
        </tr>
        <tr>
            <td>Location</td>
            <td>' . $incident['building_name'] . ', ' . $incident['building_address'] . '</td>
        </tr>
        <tr>
            <td>Geographic Coordinates</td>
            <td>Latitude: ' . $incident['geo_lat'] . ', Longitude: ' . $incident['geo_long'] . '</td>
        </tr>
        <tr>
            <td>Trigger Source</td>
            <td>' . ($incident['smoke'] > 0 ? 'Smoke ' : '') . 
                  ($incident['heat'] > 0 ? 'Heat ' : '') . 
                  ($incident['flame_detected'] ? 'Flame' : '') . '</td>
        </tr>
        <tr>
            <td>Severity Level</td>
            <td>' . $incident['status'] . '</td>
        </tr>
        <tr>
            <td>Temperature</td>
            <td>' . $incident['temp'] . '°C</td>
        </tr>
        <tr>
            <td>Smoke Level</td>
            <td>' . $incident['smoke'] . ' ppm</td>
        </tr>
        <tr>
            <td>Heat Level</td>
            <td>' . $incident['heat'] . '</td>
        </tr>
    </table>';
    
    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Ln(10);
    
    // Sensor Data
    if (!empty($sensors)) {
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 10, '2. Sensor Data', 0, 1);
        $pdf->SetFont('helvetica', '', 12);
        
        $html = '<table border="1" cellpadding="5">
            <tr>
                <th>Sensor ID</th>
                <th>Type</th>
                <th>Location</th>
                <th>Temperature</th>
                <th>Heat Level</th>
                <th>Smoke Level</th>
                <th>Flame Detected</th>
                <th>Timestamp</th>
                <th>Log Level</th>
            </tr>';
        
        foreach ($sensors as $sensor) {
            $html .= '<tr>
                <td>' . $sensor['sensor_id'] . '</td>
                <td>' . $sensor['sensor_type'] . '</td>
                <td>' . $sensor['location'] . '</td>
                <td>' . $sensor['temperature'] . '°C</td>
                <td>' . $sensor['heat_level'] . '</td>
                <td>' . $sensor['smoke_level'] . ' ppm</td>
                <td>' . ($sensor['flame_detected'] ? 'Yes' : 'No') . '</td>
                <td>' . $sensor['created_at'] . '</td>
                <td>' . $sensor['log_level'] . '</td>
            </tr>';
        }
        
        $html .= '</table>';
        $pdf->writeHTML($html, true, false, true, false, '');
        $pdf->Ln(10);
    }
    
    // Alert and Response Information
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, '3. Alert and Response Information', 0, 1);
    $pdf->SetFont('helvetica', '', 12);
    
    $html = '<table border="1" cellpadding="5">
        <tr>
            <th width="30%">Field</th>
            <th width="70%">Value</th>
        </tr>
        <tr>
            <td>Alert Timestamp</td>
            <td>' . $incident['timestamp'] . '</td>
        </tr>
        <tr>
            <td>Notification Recipients</td>
            <td>' . $incident['owner_name'] . ' (Owner), Bago City Fire Station</td>
        </tr>
        <tr>
            <td>Acknowledged At</td>
            <td>' . $incident['acknowledged_at'] . '</td>
        </tr>
        <tr>
            <td>Acknowledged By</td>
            <td>' . ($incident['admin_name'] ?? $incident['acknowledged_by']) . ' (' . ($incident['admin_role'] ?? 'System') . ')</td>
        </tr>
        <tr>
            <td>Response Actions</td>
            <td>' . ($incident['response_type'] ?? 'N/A') . '</td>
        </tr>
        <tr>
            <td>Response Notes</td>
            <td>' . ($incident['notes'] ?? 'No notes available') . '</td>
        </tr>
        <tr>
            <td>Responded By</td>
            <td>' . ($incident['responded_by'] ?? 'N/A') . '</td>
        </tr>
        <tr>
            <td>Firefighter Details</td>
            <td>' . ($incident['firefighter_name'] ?? 'N/A') . ' (Badge: ' . ($incident['badge_number'] ?? 'N/A') . ', Rank: ' . ($incident['firefighter_rank'] ?? 'N/A') . ', Specialization: ' . ($incident['specialization'] ?? 'N/A') . ')</td>
        </tr>
    </table>';
    
    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Ln(10);
    
    // Building Information
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, '4. Building Information', 0, 1);
    $pdf->SetFont('helvetica', '', 12);
    
    $html = '<table border="1" cellpadding="5">
        <tr>
            <th width="30%">Field</th>
            <th width="70%">Value</th>
        </tr>
        <tr>
            <td>Building Name</td>
            <td>' . $incident['building_name'] . '</td>
        </tr>
        <tr>
            <td>Building Type</td>
            <td>' . $incident['building_type'] . '</td>
        </tr>
        <tr>
            <td>Address</td>
            <td>' . $incident['building_address'] . '</td>
        </tr>
        <tr>
            <td>Total Floors</td>
            <td>' . $incident['total_floors'] . '</td>
        </tr>
        <tr>
            <td>Contact Person</td>
            <td>' . ($incident['contact_person'] ?? 'N/A') . ' (' . ($incident['contact_number'] ?? 'N/A') . ')</td>
        </tr>
        <tr>
            <td>Safety Features</td>
            <td>' . 
                ($incident['has_sprinkler_system'] ? 'Sprinkler System, ' : '') .
                ($incident['has_fire_alarm'] ? 'Fire Alarm, ' : '') .
                ($incident['has_fire_extinguishers'] ? 'Fire Extinguishers, ' : '') .
                ($incident['has_emergency_exits'] ? 'Emergency Exits, ' : '') .
                ($incident['has_emergency_lighting'] ? 'Emergency Lighting, ' : '') .
                ($incident['has_fire_escape'] ? 'Fire Escape' : '') . 
            '</td>
        </tr>
        <tr>
            <td>Last Inspected</td>
            <td>' . ($incident['last_inspected'] ?? 'N/A') . '</td>
        </tr>
    </table>';
    
    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Ln(10);
    
    // Device Information
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, '5. Device Information', 0, 1);
    $pdf->SetFont('helvetica', '', 12);
    
    $html = '<table border="1" cellpadding="5">
        <tr>
            <th width="30%">Field</th>
            <th width="70%">Value</th>
        </tr>
        <tr>
            <td>Device Name</td>
            <td>' . ($incident['device_name'] ?? 'N/A') . '</td>
        </tr>
        <tr>
            <td>Device Number</td>
            <td>' . ($incident['device_number'] ?? 'N/A') . '</td>
        </tr>
        <tr>
            <td>Serial Number</td>
            <td>' . ($incident['serial_number'] ?? 'N/A') . '</td>
        </tr>
        <tr>
            <td>Device Status</td>
            <td>' . ($incident['device_status'] ?? 'N/A') . '</td>
        </tr>
    </table>';
    
    $pdf->writeHTML($html, true, false, true, false, '');
    
    // Output PDF
    $pdf->Output('incident_report_' . $incidentId . '.pdf', 'D');
    exit();
}

// Function to get chart data for incidents by day (current month)
function getIncidentsByDay() {
    try {
        $conn = getDatabaseConnection();
        
        $query = "SELECT 
                    DATE(fd.timestamp) as date,
                    COUNT(*) as incident_count,
                    SUM(CASE WHEN fd.flame_detected = 1 THEN 1 ELSE 0 END) as flame_incidents,
                    SUM(CASE WHEN fd.smoke > 0 THEN 1 ELSE 0 END) as smoke_incidents,
                    SUM(CASE WHEN fd.temp > 50 THEN 1 ELSE 0 END) as high_temp_incidents
                  FROM fire_data fd
                  JOIN acknowledgments a ON a.fire_data_id = fd.id
                  WHERE DATE_FORMAT(fd.timestamp, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')
                  GROUP BY DATE(fd.timestamp)
                  ORDER BY date DESC";
        
        $stmt = $conn->prepare($query);
        $stmt->execute();
        
        $result = $stmt->fetchAll();
        if (empty($result)) {
            return [
                [
                    'date' => date('Y-m-d'),
                    'incident_count' => 0,
                    'flame_incidents' => 0,
                    'smoke_incidents' => 0,
                    'high_temp_incidents' => 0
                ]
            ];
        }
        return $result;
    } catch (Exception $e) {
        error_log("Error in getIncidentsByDay: " . $e->getMessage());
        return [];
    }
}

// Function to get chart data for incidents by month (bar chart)
function getIncidentsByMonth() {
    try {
        $conn = getDatabaseConnection();
        
        $query = "SELECT 
                    DATE_FORMAT(fd.timestamp, '%Y-%m') as month,
                    COUNT(*) as incident_count,
                    SUM(CASE WHEN fd.flame_detected = 1 THEN 1 ELSE 0 END) as flame_incidents,
                    SUM(CASE WHEN fd.smoke > 0 THEN 1 ELSE 0 END) as smoke_incidents,
                    SUM(CASE WHEN fd.temp > 50 THEN 1 ELSE 0 END) as high_temp_incidents
                  FROM fire_data fd
                  JOIN acknowledgments a ON a.fire_data_id = fd.id
                  WHERE fd.timestamp >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                  GROUP BY DATE_FORMAT(fd.timestamp, '%Y-%m')
                  ORDER BY month DESC";
        
        $stmt = $conn->prepare($query);
        $stmt->execute();
        
        $result = $stmt->fetchAll();
        
        // If no data, return sample data for testing
        if (empty($result)) {
            return [
                [
                    'month' => date('Y-m'),
                    'incident_count' => 5,
                    'flame_incidents' => 2,
                    'smoke_incidents' => 3,
                    'high_temp_incidents' => 1
                ]
            ];
        }
        
        return $result;
    } catch (Exception $e) {
        error_log("Error in getIncidentsByMonth: " . $e->getMessage());
        return [];
    }
}

// Function to get chart data for incidents by year
function getIncidentsByYear() {
    try {
        $conn = getDatabaseConnection();
        
        $query = "SELECT 
                    YEAR(fd.timestamp) as year,
                    COUNT(*) as incident_count,
                    SUM(CASE WHEN fd.flame_detected = 1 THEN 1 ELSE 0 END) as flame_incidents,
                    SUM(CASE WHEN fd.smoke > 0 THEN 1 ELSE 0 END) as smoke_incidents,
                    SUM(CASE WHEN fd.temp > 50 THEN 1 ELSE 0 END) as high_temp_incidents
                  FROM fire_data fd
                  JOIN acknowledgments a ON a.fire_data_id = fd.id
                  GROUP BY YEAR(fd.timestamp)
                  ORDER BY year DESC";
        
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $result = $stmt->fetchAll();
        if (empty($result)) {
            return [
                [
                    'year' => date('Y'),
                    'incident_count' => 0,
                    'flame_incidents' => 0,
                    'smoke_incidents' => 0,
                    'high_temp_incidents' => 0
                ]
            ];
        }
        return $result;
    } catch (Exception $e) {
        error_log("Error in getIncidentsByYear: " . $e->getMessage());
        return [];
    }
}

// Function to get chart data for incidents by building type (pie chart)
function getIncidentsByBuildingType($severity = '', $start = '', $end = '', $incidentType = '') {
    try {
        $conn = getDatabaseConnection();

        // Severity filter removed (kept signature for compatibility)
        $severityWhere = '';

        // Incident type filter from monthly chart clicks
        $incidentWhere = '';
        if (!empty($incidentType)) {
            if ($incidentType === 'flame') {
                $incidentWhere = " AND fd.flame_detected = 1";
            } elseif ($incidentType === 'smoke') {
                $incidentWhere = " AND fd.smoke > 0";
            } elseif ($incidentType === 'high_temp') {
                $incidentWhere = " AND fd.temp > 50";
            }
        }

        // Optional time filtering
        $timeWhere = '';
        $params = [];
        if (!empty($start) && !empty($end)) {
            // Normalize potential ISO datetimes to MySQL DATETIME
            $startTs = strtotime($start);
            $endTs = strtotime($end);
            $startNorm = $startTs ? date('Y-m-d H:i:s', $startTs) : $start;
            $endNorm = $endTs ? date('Y-m-d H:i:s', $endTs) : $end;
            $timeWhere = " AND fd.timestamp BETWEEN ? AND ?";
            $params[] = $startNorm;
            $params[] = $endNorm;
        } else {
            // Default range
            $timeWhere = " AND fd.timestamp >= DATE_SUB(NOW(), INTERVAL 6 MONTH)";
        }
        
        $query = "SELECT 
                    COALESCE(NULLIF(TRIM(b.building_type), ''), NULLIF(TRIM(fd.building_type), ''), 'Unknown') as building_type,
                    COUNT(*) as incident_count
                  FROM fire_data fd
                  JOIN acknowledgments a ON a.fire_data_id = fd.id
                  LEFT JOIN buildings b ON fd.building_id = b.id
                  WHERE 1=1 $timeWhere $incidentWhere
                  GROUP BY COALESCE(NULLIF(TRIM(b.building_type), ''), NULLIF(TRIM(fd.building_type), ''), 'Unknown')
                  ORDER BY incident_count DESC";
        
        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        
        $result = $stmt->fetchAll();
        
        // Normalize building types to canonical labels
        $normalized = array_map(function($row) {
            $label = isset($row['building_type']) ? strtolower(trim($row['building_type'])) : 'unknown';
            if (strpos($label, 'residential') !== false) $row['building_type'] = 'Residential';
            else if (strpos($label, 'commercial') !== false || strpos($label, 'commericial') !== false) $row['building_type'] = 'Commercial';
            else if (strpos($label, 'institution') !== false) $row['building_type'] = 'Institutional';
            else if (strpos($label, 'industrial') !== false) $row['building_type'] = 'Industrial';
            else if (empty($row['building_type'])) $row['building_type'] = 'Unknown';
            else $row['building_type'] = ucfirst(trim($row['building_type']));
            return $row;
        }, $result);

        // Aggregate duplicates after normalization
        $aggregated = [];
        foreach ($normalized as $row) {
            $bt = $row['building_type'];
            $cnt = isset($row['incident_count']) ? (int)$row['incident_count'] : 0;
            if (!isset($aggregated[$bt])) { $aggregated[$bt] = 0; }
            $aggregated[$bt] += $cnt;
        }
        $final = [];
        foreach ($aggregated as $bt => $cnt) {
            $final[] = ['building_type' => $bt, 'incident_count' => $cnt];
        }
        
        // If no data, return sample data for testing
        if (empty($final)) {
            return [
                ['building_type' => 'Residential', 'incident_count' => 0],
                ['building_type' => 'Commercial', 'incident_count' => 0],
                ['building_type' => 'Industrial', 'incident_count' => 0]
            ];
        }
        
        return $final;
    } catch (Exception $e) {
        error_log("Error in getIncidentsByBuildingType: " . $e->getMessage());
        return [];
    }
}

// Function to get real-time incident statistics
function getRealTimeIncidentStats() {
    try {
        $conn = getDatabaseConnection();
        
        $query = "SELECT 
                    COUNT(*) as total_incidents,
                    SUM(CASE WHEN fd.timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 ELSE 0 END) as incidents_24h,
                    SUM(CASE WHEN fd.timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as incidents_7d,
                    SUM(CASE WHEN fd.flame_detected = 1 THEN 1 ELSE 0 END) as flame_incidents,
                    SUM(CASE WHEN fd.smoke > 0 THEN 1 ELSE 0 END) as smoke_incidents,
                    AVG(fd.temp) as avg_temperature,
                    AVG(fd.smoke) as avg_smoke_level
                  FROM fire_data fd
                  JOIN acknowledgments a ON a.fire_data_id = fd.id";
        
        $stmt = $conn->prepare($query);
        $stmt->execute();
        
        $result = $stmt->fetch();
        
        // If no data, return sample data for testing
        if (!$result || $result['total_incidents'] == 0) {
            return [
                'total_incidents' => 6,
                'incidents_24h' => 2,
                'incidents_7d' => 5,
                'flame_incidents' => 1,
                'smoke_incidents' => 4,
                'avg_temperature' => 45.5,
                'avg_smoke_level' => 250.0
            ];
        }
        
        return $result;
    } catch (Exception $e) {
        error_log("Error in getRealTimeIncidentStats: " . $e->getMessage());
        return [
            'total_incidents' => 0,
            'incidents_24h' => 0,
            'incidents_7d' => 0,
            'flame_incidents' => 0,
            'smoke_incidents' => 0,
            'avg_temperature' => 0,
            'avg_smoke_level' => 0
        ];
    }
}

// Function to get incidents by severity level
function getIncidentsBySeverity() {
    try {
        $conn = getDatabaseConnection();
        
        $query = "SELECT 
                    CASE 
                        WHEN fd.temp > 80 OR fd.smoke > 1000 OR fd.flame_detected = 1 THEN 'Critical'
                        WHEN fd.temp > 60 OR fd.smoke > 500 THEN 'High'
                        WHEN fd.temp > 40 OR fd.smoke > 200 THEN 'Medium'
                        ELSE 'Low'
                    END as severity_level,
                    COUNT(*) as incident_count
                  FROM fire_data fd
                  JOIN acknowledgments a ON a.fire_data_id = fd.id
                  WHERE fd.timestamp >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
                  GROUP BY severity_level
                  ORDER BY 
                    CASE severity_level
                        WHEN 'Critical' THEN 1
                        WHEN 'High' THEN 2
                        WHEN 'Medium' THEN 3
                        WHEN 'Low' THEN 4
                    END";
        
        $stmt = $conn->prepare($query);
        $stmt->execute();
        
        $result = $stmt->fetchAll();
        
        // If no data, return sample data for testing
        if (empty($result)) {
            return [
                ['severity_level' => 'Critical', 'incident_count' => 1],
                ['severity_level' => 'High', 'incident_count' => 2],
                ['severity_level' => 'Medium', 'incident_count' => 3],
                ['severity_level' => 'Low', 'incident_count' => 1]
            ];
        }
        
        return $result;
    } catch (Exception $e) {
        error_log("Error in getIncidentsBySeverity: " . $e->getMessage());
        return [];
    }
}

function getIncidentsByDayRange($start, $end) {
    try {
        $conn = getDatabaseConnection();
        $startTs = strtotime($start);
        $endTs = strtotime($end);
        $startNorm = $startTs ? date('Y-m-d H:i:s', $startTs) : $start;
        $endNorm = $endTs ? date('Y-m-d H:i:s', $endTs) : $end;
        $query = "SELECT 
                    DATE(fd.timestamp) as date,
                    COUNT(*) as incident_count,
                    SUM(CASE WHEN fd.flame_detected = 1 THEN 1 ELSE 0 END) as flame_incidents,
                    SUM(CASE WHEN fd.smoke > 0 THEN 1 ELSE 0 END) as smoke_incidents,
                    SUM(CASE WHEN fd.temp > 50 THEN 1 ELSE 0 END) as high_temp_incidents
                  FROM fire_data fd
                  JOIN acknowledgments a ON a.fire_data_id = fd.id
                  WHERE fd.timestamp BETWEEN ? AND ?
                  GROUP BY DATE(fd.timestamp)
                  ORDER BY date DESC";
        $stmt = $conn->prepare($query);
        $stmt->execute([$startNorm, $endNorm]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Error in getIncidentsByDayRange: " . $e->getMessage());
        return [];
    }
}

function getIncidentsByMonthRange($start, $end) {
    try {
        $conn = getDatabaseConnection();
        $startTs = strtotime($start);
        $endTs = strtotime($end);
        $startNorm = $startTs ? date('Y-m-d H:i:s', $startTs) : $start;
        $endNorm = $endTs ? date('Y-m-d H:i:s', $endTs) : $end;
        $query = "SELECT 
                    DATE_FORMAT(fd.timestamp, '%Y-%m') as month,
                    COUNT(*) as incident_count,
                    SUM(CASE WHEN fd.flame_detected = 1 THEN 1 ELSE 0 END) as flame_incidents,
                    SUM(CASE WHEN fd.smoke > 0 THEN 1 ELSE 0 END) as smoke_incidents,
                    SUM(CASE WHEN fd.temp > 50 THEN 1 ELSE 0 END) as high_temp_incidents
                  FROM fire_data fd
                  JOIN acknowledgments a ON a.fire_data_id = fd.id
                  WHERE fd.timestamp BETWEEN ? AND ?
                  GROUP BY DATE_FORMAT(fd.timestamp, '%Y-%m')
                  ORDER BY month DESC";
        $stmt = $conn->prepare($query);
        $stmt->execute([$startNorm, $endNorm]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Error in getIncidentsByMonthRange: " . $e->getMessage());
        return [];
    }
}
?>