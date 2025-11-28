<?php
/**
 * Emergency Alerts Component
 * Handles fetching recent emergency alerts with caching and pagination guards.
 */

require_once __DIR__ . '/../components/cache.php';

function getEmergencyAlerts(PDO $pdo, int $limit = 5, int $ttl = 15): array {
    $limit = max(1, min($limit, 25)); // keep payloads bounded
    $cacheKey = FirefighterCache::key('emergency_alerts', ['limit' => $limit]);

    return FirefighterCache::remember($cacheKey, $ttl, function () use ($pdo, $limit) {
        $sql = "SELECT id, status, building_type, smoke, temp, heat, flame_detected, timestamp 
                FROM fire_data 
                WHERE status = :status 
                ORDER BY timestamp DESC 
                LIMIT :limit";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':status', 'EMERGENCY', PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    });
}
?>