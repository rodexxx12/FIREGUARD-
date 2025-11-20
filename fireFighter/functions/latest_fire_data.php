<?php
/**
 * Latest Fire Data Component
 * Handles fetching the most recent fire data entry
 */

function getLatestFireData($pdo) {
    // Get latest fire data
    $stmt = $pdo->prepare("SELECT * FROM fire_data ORDER BY timestamp DESC LIMIT 1");
    if ($stmt->execute()) {
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        die("Query failed.");
    }
}
?> 