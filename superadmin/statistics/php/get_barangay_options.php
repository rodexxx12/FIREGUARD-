<?php
header('Content-Type: application/json');
require_once '../../../db/db.php';

try {
    $conn = getDatabaseConnection();
    
    // Get all barangays that have buildings
    $sql = "
        SELECT DISTINCT b.id, b.barangay_name
        FROM barangay b
        INNER JOIN buildings bu ON b.id = bu.barangay_id
        ORDER BY b.barangay_name ASC
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $results = $stmt->fetchAll();
    
    $options = [];
    foreach ($results as $row) {
        $options[] = [
            'value' => $row['id'],
            'text' => $row['barangay_name']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $options
    ]);
    
} catch (Exception $e) {
    error_log("Error in get_barangay_options.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch barangay options'
    ]);
}
?>
