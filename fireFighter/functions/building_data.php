<?php
/**
 * Building Data Component
 * Handles fetching buildings with coordinates
 */

function getBuildingsWithCoordinates($pdo) {
    // Fetch all buildings with coordinates
    $sql = "SELECT * FROM buildings WHERE latitude IS NOT NULL AND longitude IS NOT NULL";
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?> 