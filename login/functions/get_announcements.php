<?php
require_once __DIR__ . '/db.php';

function getPublicAnnouncements() {
    try {
        $conn = getDatabaseConnection();
        
        // Get current date for filtering
        $currentDate = date('Y-m-d H:i:s');
        
        // Query to get announcements from both admin and superadmin tables
        // where target_type is 'all' and announcements are published and within date range
        $query = "
            SELECT 
                'admin' as source,
                a.id,
                a.title,
                a.content,
                a.start_date,
                a.end_date,
                a.priority,
                a.created_at,
                adm.full_name as author_name
            FROM announcements a
            INNER JOIN announcement_targets at ON a.id = at.announcement_id
            INNER JOIN admin adm ON a.author_id = adm.admin_id
            WHERE at.target_type = 'all'
            AND a.is_published = 1
            AND a.start_date <= ?
            AND (a.end_date IS NULL OR a.end_date >= ?)
            
            UNION ALL
            
            SELECT 
                'superadmin' as source,
                sa.id,
                sa.title,
                sa.content,
                sa.start_date,
                sa.end_date,
                sa.priority,
                sa.created_at,
                sadm.full_name as author_name
            FROM superadmin_announcements sa
            INNER JOIN superadmin_announcement_targets sat ON sa.id = sat.announcement_id
            INNER JOIN superadmin sadm ON sa.author_id = sadm.superadmin_id
            WHERE sat.target_type = 'all'
            AND sa.is_published = 1
            AND sa.start_date <= ?
            AND (sa.end_date IS NULL OR sa.end_date >= ?)
            
            ORDER BY priority DESC, created_at DESC
            LIMIT 10
        ";
        
        $stmt = $conn->prepare($query);
        $stmt->execute([$currentDate, $currentDate, $currentDate, $currentDate]);
        
        $announcements = $stmt->fetchAll();
        
        return [
            'success' => true,
            'announcements' => $announcements
        ];
        
    } catch (PDOException $e) {
        error_log("Error fetching announcements: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Failed to fetch announcements',
            'announcements' => []
        ];
    }
}

// If this file is called directly, return JSON response
if (basename($_SERVER['PHP_SELF']) == 'get_announcements.php') {
    // Ensure we always return JSON
    header('Content-Type: application/json');
    
    try {
        $result = getPublicAnnouncements();
        echo json_encode($result);
    } catch (Exception $e) {
        error_log("Error in get_announcements.php: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'An error occurred while fetching announcements',
            'announcements' => []
        ]);
    }
}
?> 