<?php
function getBuildings($pdo) {
    $sql = "SELECT * FROM buildings WHERE latitude IS NOT NULL AND longitude IS NOT NULL";
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
} 