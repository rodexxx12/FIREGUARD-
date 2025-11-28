<?php
/**
 * Latest Fire Data Component
 * Handles fetching the most recent fire data entry with graceful failures.
 */

require_once __DIR__ . '/../components/cache.php';

function getLatestFireData(PDO $pdo, int $ttl = 10): ?array {
    $cacheKey = FirefighterCache::key('latest_fire_data');

    return FirefighterCache::remember($cacheKey, $ttl, function () use ($pdo) {
        $stmt = $pdo->query("SELECT * FROM fire_data ORDER BY timestamp DESC LIMIT 1");
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    });
}
?>