<?php
/**
 * Batch Update Script: Update building_id for all devices based on GPS location
 * 
 * This script checks all devices with building_id = NULL and updates them
 * if they are within any building's radius based on their latest GPS coordinates.
 * 
 * Usage:
 *   - Via web browser: http://your-domain/userdashboard/mapping/php/update_all_devices_building_id.php
 *   - Via command line: php update_all_devices_building_id.php
 */

// Set execution time limit for batch processing
set_time_limit(300); // 5 minutes

// Check if running from command line
$isCli = php_sapi_name() === 'cli';

if (!$isCli) {
    // For web browser, start session and check login (optional)
    session_start();
    header('Content-Type: text/html; charset=utf-8');
    echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Update Devices Building ID</title>";
    echo "<style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #4CAF50; padding-bottom: 10px; }
        h2 { color: #555; margin-top: 30px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border: 1px solid #ddd; }
        th { background-color: #4CAF50; color: white; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .success { color: #4CAF50; font-weight: bold; }
        .error { color: #f44336; font-weight: bold; }
        .warning { color: #ff9800; font-weight: bold; }
        .info { color: #2196F3; }
        .summary { background: #e8f5e9; padding: 15px; border-radius: 5px; margin: 20px 0; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 4px; overflow-x: auto; }
    </style></head><body><div class='container'>";
}

require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/../functions/device_location_validator.php';

try {
    $pdo = getMappingDBConnection();
    
    if (!$isCli) {
        echo "<h1>Batch Update: Devices Building ID</h1>";
        echo "<p class='info'>This script will update building_id for all devices that are within building areas.</p>";
        echo "<hr>";
    } else {
        echo "=== Batch Update: Devices Building ID ===\n\n";
    }
    
    // Get all devices with building_id = NULL
    $devicesSql = "SELECT device_id, user_id, device_name, device_number, building_id 
                   FROM devices 
                   WHERE building_id IS NULL
                   ORDER BY device_id";
    
    $devicesStmt = $pdo->prepare($devicesSql);
    $devicesStmt->execute();
    $devices = $devicesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($devices)) {
        $message = "No devices found with building_id = NULL. All devices already have building assignments.";
        if ($isCli) {
            echo $message . "\n";
        } else {
            echo "<p class='info'>{$message}</p>";
        }
        exit;
    }
    
    $totalDevices = count($devices);
    $updatedCount = 0;
    $skippedCount = 0;
    $errorCount = 0;
    $results = [];
    
    if (!$isCli) {
        echo "<h2>Processing {$totalDevices} Device(s)</h2>";
        echo "<table>";
        echo "<tr><th>Device ID</th><th>Device Name</th><th>Device Number</th><th>Status</th><th>Building ID</th><th>Distance (m)</th><th>Message</th></tr>";
    } else {
        echo "Processing {$totalDevices} device(s)...\n\n";
    }
    
    foreach ($devices as $device) {
        $device_id = $device['device_id'];
        $device_name = $device['device_name'];
        $device_number = $device['device_number'];
        
        // Check if device has GPS data
        $gpsCheckSql = "SELECT COUNT(*) as count 
                       FROM fire_data 
                       WHERE device_id = ? 
                       AND gps_latitude IS NOT NULL 
                       AND gps_longitude IS NOT NULL";
        $gpsCheckStmt = $pdo->prepare($gpsCheckSql);
        $gpsCheckStmt->execute([$device_id]);
        $gpsCheck = $gpsCheckStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($gpsCheck['count'] == 0) {
            $result = [
                'device_id' => $device_id,
                'device_name' => $device_name,
                'device_number' => $device_number,
                'status' => 'skipped',
                'message' => 'No GPS data found in fire_data table',
                'building_id' => null,
                'distance' => null
            ];
            $results[] = $result;
            $skippedCount++;
            
            if ($isCli) {
                echo "[SKIP] Device ID {$device_id} ({$device_name}): No GPS data\n";
            } else {
                echo "<tr>";
                echo "<td>{$device_id}</td>";
                echo "<td>{$device_name}</td>";
                echo "<td>{$device_number}</td>";
                echo "<td class='warning'>SKIPPED</td>";
                echo "<td>-</td>";
                echo "<td>-</td>";
                echo "<td>No GPS data found</td>";
                echo "</tr>";
            }
            continue;
        }
        
        // Try to auto-validate device location
        $validationResult = autoValidateDeviceLocation($device_id, $pdo);
        
        if ($validationResult['success']) {
            $result = [
                'device_id' => $device_id,
                'device_name' => $device_name,
                'device_number' => $device_number,
                'status' => 'updated',
                'message' => $validationResult['message'],
                'building_id' => $validationResult['building_id'],
                'building_name' => $validationResult['building_name'] ?? null,
                'distance' => $validationResult['distance'] ?? null
            ];
            $results[] = $result;
            $updatedCount++;
            
            if ($isCli) {
                echo "[SUCCESS] Device ID {$device_id} ({$device_name}): Assigned to Building ID {$validationResult['building_id']}";
                if (isset($validationResult['building_name'])) {
                    echo " ({$validationResult['building_name']})";
                }
                echo " - Distance: " . ($validationResult['distance'] ?? 'N/A') . " meters\n";
            } else {
                echo "<tr>";
                echo "<td>{$device_id}</td>";
                echo "<td>{$device_name}</td>";
                echo "<td>{$device_number}</td>";
                echo "<td class='success'>UPDATED</td>";
                echo "<td class='success'>{$validationResult['building_id']}</td>";
                echo "<td>" . ($validationResult['distance'] ?? 'N/A') . "</td>";
                echo "<td>Assigned to building" . (isset($validationResult['building_name']) ? ": {$validationResult['building_name']}" : "") . "</td>";
                echo "</tr>";
            }
        } else {
            $result = [
                'device_id' => $device_id,
                'device_name' => $device_name,
                'device_number' => $device_number,
                'status' => 'not_found',
                'message' => $validationResult['message'],
                'building_id' => null,
                'distance' => null
            ];
            $results[] = $result;
            $skippedCount++;
            
            if ($isCli) {
                echo "[SKIP] Device ID {$device_id} ({$device_name}): {$validationResult['message']}\n";
            } else {
                echo "<tr>";
                echo "<td>{$device_id}</td>";
                echo "<td>{$device_name}</td>";
                echo "<td>{$device_number}</td>";
                echo "<td class='warning'>NOT FOUND</td>";
                echo "<td>-</td>";
                echo "<td>-</td>";
                echo "<td>{$validationResult['message']}</td>";
                echo "</tr>";
            }
        }
    }
    
    if (!$isCli) {
        echo "</table>";
    }
    
    // Summary
    if (!$isCli) {
        echo "<div class='summary'>";
        echo "<h2>Summary</h2>";
        echo "<p><strong>Total Devices Processed:</strong> {$totalDevices}</p>";
        echo "<p class='success'><strong>Successfully Updated:</strong> {$updatedCount}</p>";
        echo "<p class='warning'><strong>Skipped:</strong> {$skippedCount}</p>";
        echo "<p class='error'><strong>Errors:</strong> {$errorCount}</p>";
        echo "</div>";
        
        // Show updated devices details
        if ($updatedCount > 0) {
            echo "<h2>Updated Devices Details</h2>";
            echo "<table>";
            echo "<tr><th>Device ID</th><th>Device Name</th><th>Building ID</th><th>Building Name</th><th>Distance (m)</th></tr>";
            foreach ($results as $result) {
                if ($result['status'] === 'updated') {
                    echo "<tr>";
                    echo "<td>{$result['device_id']}</td>";
                    echo "<td>{$result['device_name']}</td>";
                    echo "<td class='success'>{$result['building_id']}</td>";
                    echo "<td>" . ($result['building_name'] ?? 'N/A') . "</td>";
                    echo "<td>" . ($result['distance'] ?? 'N/A') . "</td>";
                    echo "</tr>";
                }
            }
            echo "</table>";
        }
    } else {
        echo "\n=== Summary ===\n";
        echo "Total Devices Processed: {$totalDevices}\n";
        echo "Successfully Updated: {$updatedCount}\n";
        echo "Skipped: {$skippedCount}\n";
        echo "Errors: {$errorCount}\n";
        
        if ($updatedCount > 0) {
            echo "\n=== Updated Devices ===\n";
            foreach ($results as $result) {
                if ($result['status'] === 'updated') {
                    echo "Device ID {$result['device_id']} ({$result['device_name']}) -> Building ID {$result['building_id']}";
                    if (isset($result['building_name'])) {
                        echo " ({$result['building_name']})";
                    }
                    echo " - Distance: " . ($result['distance'] ?? 'N/A') . " meters\n";
                }
            }
        }
    }
    
    // Verify final state
    $verifySql = "SELECT COUNT(*) as count FROM devices WHERE building_id IS NULL";
    $verifyStmt = $pdo->prepare($verifySql);
    $verifyStmt->execute();
    $verify = $verifyStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$isCli) {
        echo "<hr>";
        echo "<p class='info'><strong>Remaining devices with building_id = NULL:</strong> {$verify['count']}</p>";
        echo "<p><a href='?'>Refresh</a> | <a href='../main.php'>Back to Mapping</a></p>";
    } else {
        echo "\nRemaining devices with building_id = NULL: {$verify['count']}\n";
    }
    
} catch (Exception $e) {
    $errorMessage = "Error: " . $e->getMessage();
    if ($isCli) {
        echo "\n{$errorMessage}\n";
        echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    } else {
        echo "<p class='error'>{$errorMessage}</p>";
        echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    }
    exit(1);
}

if (!$isCli) {
    echo "</div></body></html>";
}
?>

