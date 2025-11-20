<?php
// Start session
session_start();

// Set content type to JSON
header('Content-Type: application/json');

if (!isset($_SESSION['firefighter_id'])) {
    header("Location: ../../../index.php");
    exit();
}
$firefighter_id = $_SESSION['firefighter_id'];

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
$last_check = $input['last_check'] ?? null;

// Database connection
try {
    $pdo = new PDO("mysql:host=auth-db1322.hstgr.io;dbname=u520834156_DBBagofire;charset=utf8mb4", "u520834156_userBagofire", "i[#[GQ!+=C9");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database connection failed']);
    exit();
}

$new_announcements = [];
$total_count = 0;

try {
    // Check for regular admin announcements
    $sql = "
        SELECT 
            a.id,
            a.title,
            a.content,
            a.priority,
            a.created_at,
            'admin' as announcement_type,
            CONCAT(adm.full_name, ' (Admin)') as author_name
        FROM announcements a
        INNER JOIN admin adm ON a.author_id = adm.admin_id
        INNER JOIN announcement_targets at ON a.id = at.announcement_id
        WHERE a.is_published = 1 
        AND a.start_date <= NOW() 
        AND (a.end_date IS NULL OR a.end_date >= NOW())
        AND (
            at.target_type = 'all' 
            OR at.target_type = 'all_firefighters'
            OR (at.target_type = 'firefighter' AND at.firefighter_id = ?)
        )
        AND a.created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ";
    
    if ($last_check) {
        $sql .= " AND a.created_at > ?";
    }
    $sql .= " ORDER BY a.created_at DESC";
    
    if ($last_check) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$firefighter_id, $last_check]);
    } else {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$firefighter_id]);
    }
    $admin_announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Check for superadmin announcements
    $sql2 = "
        SELECT 
            sa.id,
            sa.title,
            sa.content,
            sa.priority,
            sa.created_at,
            'superadmin' as announcement_type,
            CONCAT(sadm.full_name, ' (Super Admin)') as author_name
        FROM superadmin_announcements sa
        INNER JOIN superadmin sadm ON sa.author_id = sadm.superadmin_id
        INNER JOIN superadmin_announcement_targets sat ON sa.id = sat.announcement_id
        WHERE sa.is_published = 1 
        AND sa.start_date <= NOW() 
        AND (sa.end_date IS NULL OR sa.end_date >= NOW())
        AND (
            sat.target_type = 'all' 
            OR sat.target_type = 'all_firefighters'
            OR (sat.target_type = 'firefighter' AND sat.firefighter_id = ?)
        )
        AND sa.created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ";
    
    if ($last_check) {
        $sql2 .= " AND sa.created_at > ?";
    }
    $sql2 .= " ORDER BY sa.created_at DESC";
    
    if ($last_check) {
        $stmt2 = $pdo->prepare($sql2);
        $stmt2->execute([$firefighter_id, $last_check]);
    } else {
        $stmt2 = $pdo->prepare($sql2);
        $stmt2->execute([$firefighter_id]);
    }
    $superadmin_announcements = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    
    // Combine and sort announcements
    $all_announcements = array_merge($admin_announcements, $superadmin_announcements);
    usort($all_announcements, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    
    // Get new announcements (those created after last check)
    if ($last_check) {
        $new_announcements = array_filter($all_announcements, function($announcement) use ($last_check) {
            return strtotime($announcement['created_at']) > strtotime($last_check);
        });
    } else {
        $new_announcements = $all_announcements;
    }
    
    $total_count = count($all_announcements);
    
    // Update session with current check time
    $_SESSION['last_announcement_check'] = date('Y-m-d H:i:s');
    
    echo json_encode([
        'success' => true,
        'new_announcements' => array_values($new_announcements),
        'count' => $total_count,
        'new_count' => count($new_announcements)
    ]);
    
} catch (PDOException $e) {
    error_log("Error checking announcements: " . $e->getMessage());
    echo json_encode(['error' => 'Database error occurred']);
}
?> 