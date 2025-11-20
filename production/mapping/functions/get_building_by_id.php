<?php
require_once __DIR__ . '/db_utils.php';

function getBuildingById($pdo, $buildingId) {
    try {
        if (!validateBuildingId($buildingId)) {
            return [
                'success' => false,
                'message' => 'Invalid building ID'
            ];
        }

        $stmt = $pdo->prepare("SELECT * FROM buildings WHERE id = ?");
        $stmt->execute([$buildingId]);
        $building = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($building) {
            return [
                'success' => true,
                'building' => $building
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Building not found'
            ];
        }
    } catch (PDOException $e) {
        return handleDatabaseError($e);
    }
}
?> 