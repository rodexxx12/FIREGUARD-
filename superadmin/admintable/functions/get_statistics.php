<?php
require_once __DIR__ . '/db_connect.php';

header('Content-Type: application/json');

try {
    // Get total users
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
    $totalUsers = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Get active users
    $stmt = $pdo->prepare("SELECT COUNT(*) as active FROM users WHERE LOWER(status) = 'active'");
    $stmt->execute();
    $activeUsers = $stmt->fetch(PDO::FETCH_ASSOC)['active'];

    // Get users registered this month
    $stmt = $pdo->prepare("SELECT COUNT(*) as recent FROM users WHERE registration_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stmt->execute();
    $recentUsers = $stmt->fetch(PDO::FETCH_ASSOC)['recent'];

    // Get average age
    $stmt = $pdo->query("SELECT AVG(age) as avg_age FROM users WHERE age IS NOT NULL AND age > 0");
    $avgAge = round($stmt->fetch(PDO::FETCH_ASSOC)['avg_age']);

    // Get age distribution
    $stmt = $pdo->query("
        SELECT 
            CASE 
                WHEN age BETWEEN 18 AND 25 THEN '18-25'
                WHEN age BETWEEN 26 AND 35 THEN '26-35'
                WHEN age BETWEEN 36 AND 50 THEN '36-50'
                WHEN age > 50 THEN '50+'
                ELSE 'Unknown'
            END as age_group,
            COUNT(*) as count
        FROM users 
        WHERE age IS NOT NULL AND age > 0
        GROUP BY age_group
        ORDER BY age_group
    ");
    $ageDistribution = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get status distribution
    $stmt = $pdo->query("
        SELECT status, COUNT(*) as count 
        FROM users 
        GROUP BY status
    ");
    $statusDistribution = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get registration trend (last 6 months)
    $stmt = $pdo->query("
        SELECT 
            DATE_FORMAT(registration_date, '%Y-%m') as month,
            COUNT(*) as count
        FROM users 
        WHERE registration_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY month
        ORDER BY month
    ");
    $registrationTrend = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => [
            'totalUsers' => (int)$totalUsers,
            'activeUsers' => (int)$activeUsers,
            'recentUsers' => (int)$recentUsers,
            'avgAge' => (int)$avgAge,
            'ageDistribution' => $ageDistribution,
            'statusDistribution' => $statusDistribution,
            'registrationTrend' => $registrationTrend
        ]
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?> 