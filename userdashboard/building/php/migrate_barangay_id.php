<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

include('../../db/db.php');

header('Content-Type: application/json');

try {
    $conn = getDatabaseConnection();
    
    // Check if barangay_id column exists
    $stmt = $conn->query("SHOW COLUMNS FROM buildings LIKE 'barangay_id'");
    if ($stmt->rowCount() === 0) {
        // Add barangay_id column if it doesn't exist
        $conn->exec("ALTER TABLE buildings ADD COLUMN barangay_id INT(11) AFTER building_area");
        $conn->exec("ALTER TABLE buildings ADD INDEX idx_barangay_id (barangay_id)");
        $conn->exec("ALTER TABLE buildings ADD FOREIGN KEY (barangay_id) REFERENCES barangay(id) ON UPDATE CASCADE");
    }
    
    // Get buildings without barangay_id
    $stmt = $conn->prepare("SELECT id, latitude, longitude FROM buildings WHERE barangay_id IS NULL AND latitude IS NOT NULL AND longitude IS NOT NULL");
    $stmt->execute();
    $buildings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $updated = 0;
    $errors = 0;
    
    foreach ($buildings as $building) {
        try {
            // Find the closest barangay
            $stmt = $conn->prepare("
                SELECT id, 
                (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance
                FROM barangay 
                ORDER BY distance 
                LIMIT 1
            ");
            $stmt->execute([$building['latitude'], $building['longitude'], $building['latitude']]);
            $barangay = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($barangay) {
                // Update the building with the closest barangay
                $updateStmt = $conn->prepare("UPDATE buildings SET barangay_id = ? WHERE id = ?");
                $updateStmt->execute([$barangay['id'], $building['id']]);
                $updated++;
            } else {
                $errors++;
            }
        } catch (Exception $e) {
            error_log("Error updating building {$building['id']}: " . $e->getMessage());
            $errors++;
        }
    }
    
    echo json_encode([
        'status' => 'success',
        'message' => "Migration completed. Updated: $updated, Errors: $errors",
        'updated' => $updated,
        'errors' => $errors
    ]);
    
} catch (Exception $e) {
    error_log("Migration error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Migration failed: ' . $e->getMessage()
    ]);
}
?>
