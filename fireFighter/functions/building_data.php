<?php
/**
 * Building Data Component
 * Handles fetching buildings with coordinates while minimizing payload size.
 */

require_once __DIR__ . '/../components/cache.php';

function getBuildingsWithCoordinates(PDO $pdo, int $ttl = 60): array {
    $cacheKey = FirefighterCache::key('buildings_with_coordinates');

    return FirefighterCache::remember($cacheKey, $ttl, function () use ($pdo) {
        $sql = <<<SQL
            SELECT 
                id,
                building_name,
                building_type,
                address,
                latitude,
                longitude,
                contact_person,
                contact_number,
                total_floors,
                has_sprinkler_system,
                has_fire_alarm,
                has_fire_extinguishers,
                has_emergency_exits,
                has_emergency_lighting,
                has_fire_escape,
                building_area,
                construction_year,
                last_inspected
            FROM buildings
            WHERE latitude IS NOT NULL 
              AND longitude IS NOT NULL
        SQL;

        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    });
}
?>