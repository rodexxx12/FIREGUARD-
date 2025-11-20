<?php
/**
 * Migration Script: Add device_id column to buildings table
 * 
 * This script adds the device_id column to the buildings table if it doesn't exist.
 * The device_id column will store the validated device assigned to each building.
 */

require_once __DIR__ . '/../../db/db.php';

header('Content-Type: application/json');

try {
    $pdo = getDatabaseConnection();
    
    // Check if device_id column exists
    $checkColumn = $pdo->query("SHOW COLUMNS FROM buildings LIKE 'device_id'");
    
    if ($checkColumn->rowCount() === 0) {
        // Column doesn't exist, add it
        $pdo->exec("ALTER TABLE buildings 
                    ADD COLUMN device_id INT(11) DEFAULT NULL 
                    AFTER building_area");
        
        // Add index for better query performance
        $pdo->exec("ALTER TABLE buildings 
                    ADD INDEX idx_buildings_device_id (device_id)");
        
        // Add foreign key constraint (optional, can be removed if devices table structure differs)
        try {
            $pdo->exec("ALTER TABLE buildings 
                        ADD FOREIGN KEY (device_id) 
                        REFERENCES devices(device_id) 
                        ON DELETE SET NULL 
                        ON UPDATE CASCADE");
        } catch (PDOException $e) {
            // Foreign key might fail if devices table doesn't exist or structure differs
            // This is okay, we'll just log it
            error_log("Note: Could not add foreign key constraint: " . $e->getMessage());
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'device_id column added to buildings table successfully'
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'message' => 'device_id column already exists in buildings table'
        ]);
    }
    
} catch (PDOException $e) {
    error_log("Migration error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Migration failed: ' . $e->getMessage()
    ]);
}
?>

