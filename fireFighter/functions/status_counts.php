<?php
/**
 * Status Counts Component
 * Handles counting fire data by status with caching to avoid repeated scans.
 */

require_once __DIR__ . '/../components/cache.php';

function getStatusCounts(PDO $pdo, int $ttl = 30): array {
    $cacheKey = FirefighterCache::key('status_counts');

    return FirefighterCache::remember($cacheKey, $ttl, function () use ($pdo) {
        $sql = "SELECT UPPER(status) AS status, COUNT(*) AS count FROM fire_data GROUP BY UPPER(status)";
        $stmt = $pdo->query($sql);

        $counts = [
            "SAFE" => 0,
            "MONITORING" => 0,
            "PRE-DISPATCH" => 0,
            "EMERGENCY" => 0
        ];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $status = strtoupper($row['status'] ?? '');
            if (array_key_exists($status, $counts)) {
                $counts[$status] = (int) $row['count'];
            }
        }

        return $counts;
    });
}
?>