<?php
/**
 * Recent Status Alerts Component
 * Fetches the most recent status alerts from fire_data
 */

function getRecentStatusAlerts(PDO $pdo, int $limit = 10, ?string $statusFilter = null): array {
    $safeLimit = max(1, (int)$limit);
    $baseSql = "SELECT 
            f.id, 
            f.status, 
            f.building_type, 
            f.smoke, 
            f.temp, 
            f.heat, 
            f.flame_detected, 
            f.timestamp, 
            f.ml_confidence, 
            f.ml_prediction, 
            f.ml_fire_probability, 
            f.ai_prediction, 
            f.ml_timestamp,
            f.device_id,
            f.user_id,
            f.building_id,
            f.geo_lat,
            f.geo_long,
            b.building_name,
            b.address
        FROM fire_data f
        LEFT JOIN buildings b ON f.building_id = b.id";
    $where = "";
    if ($statusFilter !== null) {
        // Case-insensitive LIKE match to allow variants like "ACKNOWLEDGED - note"
        $where = " WHERE UPPER(TRIM(status)) LIKE UPPER(:status)";
    }
    // Note: Some PDO MySQL configurations do not allow binding LIMIT. Inline sanitized integer.
    // Use ID for recency to avoid effects of ON UPDATE current_timestamp() on `timestamp`.
    $sql = $baseSql . $where . " ORDER BY f.id DESC LIMIT $safeLimit";

    $stmt = $pdo->prepare($sql);
    if ($statusFilter !== null) {
        $stmt->bindValue(':status', '%' . $statusFilter . '%', PDO::PARAM_STR);
    }
    $stmt->execute();

    $alerts = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $alerts[] = [
            'id' => (int)$row['id'],
            'status' => $row['status'],
            'building_type' => $row['building_type'],
            'smoke' => (int)$row['smoke'],
            'temp' => (int)$row['temp'],
            'heat' => (int)$row['heat'],
            'flame_detected' => (int)$row['flame_detected'],
            'timestamp' => $row['timestamp'],
            'ml_confidence' => isset($row['ml_confidence']) ? (float)$row['ml_confidence'] : null,
            'ml_prediction' => isset($row['ml_prediction']) ? (int)$row['ml_prediction'] : null,
            'ml_fire_probability' => isset($row['ml_fire_probability']) ? (float)$row['ml_fire_probability'] : null,
            'ai_prediction' => $row['ai_prediction'] ?? null,
            'ml_timestamp' => $row['ml_timestamp'] ?? null,
            'device_id' => isset($row['device_id']) ? (int)$row['device_id'] : null,
            'user_id' => isset($row['user_id']) ? (int)$row['user_id'] : null,
            'building_id' => isset($row['building_id']) ? (int)$row['building_id'] : null,
            'geo_lat' => $row['geo_lat'],
            'geo_long' => $row['geo_long'],
            'building_name' => $row['building_name'] ?? null,
            'address' => $row['address'] ?? null,
        ];
    }

    return $alerts;
}
?>

