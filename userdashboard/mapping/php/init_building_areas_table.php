<?php
/**
 * Initialize building_areas table
 * Run this file once to create the table in your database
 */

require_once __DIR__ . '/../db/db.php';

try {
    $pdo = getMappingDBConnection();
    
    // Read the SQL file
    $sqlFile = __DIR__ . '/../db/create_building_areas_table.sql';
    
    if (!file_exists($sqlFile)) {
        throw new Exception("SQL file not found: $sqlFile");
    }
    
    $sql = file_get_contents($sqlFile);
    
    // Execute the SQL
    $pdo->exec($sql);
    
    echo json_encode([
        'success' => true,
        'message' => 'building_areas table created successfully'
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>

