<?php
require_once 'common/database_utils.php';

try {
    // Test basic connectivity
    $testResult = DatabaseUtils::executeSingleQuery("SELECT 1 as test");
    
    // Check table counts
    $fireDataCount = DatabaseUtils::executeSingleQuery("SELECT COUNT(*) as count FROM fire_data")['count'];
    $barangayCount = DatabaseUtils::executeSingleQuery("SELECT COUNT(*) as count FROM barangay")['count'];
    $devicesCount = DatabaseUtils::executeSingleQuery("SELECT COUNT(*) as count FROM devices")['count'];
    $buildingsCount = DatabaseUtils::executeSingleQuery("SELECT COUNT(*) as count FROM buildings")['count'];
    
    // Test the actual incident query (building-based barangay filtering)
    $incidentSql = "SELECT 
                        COALESCE(bld_barangay.barangay_name, fd_barangay.barangay_name, 'Unknown') as barangay_name,
                        COALESCE(fd.building_type, 'Unknown') as building_type,
                        COUNT(*) as incident_count
                    FROM fire_data fd
                    LEFT JOIN devices d ON fd.device_id = d.device_id
                    LEFT JOIN buildings bld ON (fd.building_id = bld.id OR d.building_id = bld.id)
                    LEFT JOIN barangay bld_barangay ON bld.barangay_id = bld_barangay.id
                    LEFT JOIN barangay fd_barangay ON fd.barangay_id = fd_barangay.id
                    WHERE fd.timestamp IS NOT NULL AND fd.timestamp != ''
                    AND fd.status IN ('EMERGENCY', 'ACKNOWLEDGED', 'fire')
                    GROUP BY COALESCE(bld_barangay.barangay_name, fd_barangay.barangay_name, 'Unknown'), COALESCE(fd.building_type, 'Unknown') 
                    ORDER BY incident_count DESC
                    LIMIT 5";
    
    $incidentResults = DatabaseUtils::executeQuery($incidentSql);
    
    DatabaseUtils::sendResponse(true, [
        'database_connection' => $testResult['test'] == 1 ? 'OK' : 'FAILED',
        'fire_data_records' => $fireDataCount,
        'barangay_records' => $barangayCount,
        'devices_records' => $devicesCount,
        'buildings_records' => $buildingsCount,
        'sample_incidents' => $incidentResults
    ], 'System health check completed');
    
} catch (Exception $e) {
    DatabaseUtils::sendError('System health check failed', $e->getMessage());
}
?>
