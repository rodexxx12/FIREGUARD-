<?php
/**
 * Announcement Helper Functions
 * Provides functions to get user-specific announcements based on user type and ID
 */

// Database configuration
define('DB_HOST', 'auth-db1322.hstgr.io');
define('DB_NAME', 'u520834156_DBBagofire');
define('DB_USER', 'u520834156_userBagofire');
define('DB_PASS', 'i[#[GQ!+=C9');

/**
 * Get database connection
 */
function getAnnouncementDBConnection() {
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        return null;
    }
}

/**
 * Get superadmin announcements for display in user dashboard
 * @param bool $onlyPublished Whether to only get published announcements
 * @param string|null $currentDate Current date for filtering
 * @return array Array of superadmin announcements
 */
function getSuperadminAnnouncements($onlyPublished = true, $currentDate = null) {
    if ($currentDate === null) {
        $currentDate = date('Y-m-d H:i:s');
    }
    
    $pdo = getAnnouncementDBConnection();
    if (!$pdo) {
        return [];
    }
    
    try {
        $sql = "
            SELECT DISTINCT a.*, 
                   sa.full_name as author_name,
                   sat.target_type
            FROM superadmin_announcements a
            JOIN superadmin_announcement_targets sat ON a.id = sat.announcement_id
            LEFT JOIN superadmin sa ON a.author_id = sa.superadmin_id
            WHERE 1=1
        ";
        
        $params = [];
        
        // Add published and date conditions
        if ($onlyPublished) {
            $sql .= " AND a.is_published = 1 
                     AND a.start_date <= :currentDate 
                     AND (a.end_date IS NULL OR a.end_date >= :currentDate)";
            $params['currentDate'] = $currentDate;
        }
        
        // Add ordering
        $sql .= " ORDER BY 
                    CASE a.priority 
                        WHEN 'high' THEN 1 
                        WHEN 'medium' THEN 2 
                        WHEN 'low' THEN 3 
                    END,
                    a.start_date DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Error getting superadmin announcements: " . $e->getMessage());
        return [];
    }
}

/**
 * Get user-specific announcements
 * @param int $userId User ID
 * @param string $userType Type of user (user, firefighter, admin, superadmin)
 * @param bool $onlyPublished Whether to only get published announcements
 * @param string|null $currentDate Current date for filtering
 * @return array Array of announcements
 */
function getUserSpecificAnnouncements($userId, $userType, $onlyPublished = true, $currentDate = null) {
    if ($currentDate === null) {
        $currentDate = date('Y-m-d H:i:s');
    }
    
    $pdo = getAnnouncementDBConnection();
    if (!$pdo) {
        return [];
    }
    
    try {
        $sql = "
            SELECT DISTINCT a.*, 
                   COALESCE(ad.full_name, sad.full_name) as author_name,
                   at.target_type,
                   'regular' as announcement_type
            FROM announcements a
            JOIN announcement_targets at ON a.id = at.announcement_id
            LEFT JOIN admin ad ON a.author_id = ad.admin_id
            LEFT JOIN superadmin sad ON a.author_id = sad.superadmin_id
            WHERE 1=1
        ";
        
        $params = [];
        
        // Add user-specific conditions
        switch ($userType) {
            case 'user':
                $sql .= " AND (
                    at.target_type = 'all' 
                    OR (at.target_type = 'user' AND at.user_id = :userId)
                )";
                $params['userId'] = $userId;
                break;
                
            case 'firefighter':
                $sql .= " AND (
                    at.target_type = 'all' 
                    OR (at.target_type = 'firefighter' AND at.firefighter_id = :userId)
                    OR at.target_type = 'all_firefighters'
                )";
                $params['userId'] = $userId;
                break;
                
            case 'admin':
                $sql .= " AND (
                    at.target_type = 'all' 
                    OR (at.target_type = 'admin' AND at.admin_id = :userId)
                    OR at.target_type = 'all_admins'
                )";
                $params['userId'] = $userId;
                break;
                
            case 'superadmin':
                $sql .= " AND (
                    at.target_type = 'all' 
                    OR (at.target_type = 'admin' AND at.admin_id = :userId)
                    OR at.target_type = 'all_admins'
                )";
                $params['userId'] = $userId;
                break;
                
            default:
                // Default to all announcements
                $sql .= " AND at.target_type = 'all'";
                break;
        }
        
        // Add published and date conditions
        if ($onlyPublished) {
            $sql .= " AND a.is_published = 1 
                     AND a.start_date <= :currentDate 
                     AND (a.end_date IS NULL OR a.end_date >= :currentDate)";
            $params['currentDate'] = $currentDate;
        }
        
        // Add ordering
        $sql .= " ORDER BY 
                    CASE a.priority 
                        WHEN 'high' THEN 1 
                        WHEN 'medium' THEN 2 
                        WHEN 'low' THEN 3 
                    END,
                    a.start_date DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        $regularAnnouncements = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get superadmin announcements
        $superadminAnnouncements = getSuperadminAnnouncements($onlyPublished, $currentDate);
        
        // Add announcement type to superadmin announcements
        foreach ($superadminAnnouncements as &$announcement) {
            $announcement['announcement_type'] = 'superadmin';
        }
        
        // Merge and sort all announcements
        $allAnnouncements = array_merge($regularAnnouncements, $superadminAnnouncements);
        
        // Sort by priority and date
        usort($allAnnouncements, function($a, $b) {
            $priorityOrder = ['high' => 1, 'medium' => 2, 'low' => 3];
            $aPriority = $priorityOrder[$a['priority']] ?? 2;
            $bPriority = $priorityOrder[$b['priority']] ?? 2;
            
            if ($aPriority !== $bPriority) {
                return $aPriority - $bPriority;
            }
            
            return strtotime($b['start_date']) - strtotime($a['start_date']);
        });
        
        return $allAnnouncements;
        
    } catch (PDOException $e) {
        error_log("Error getting user announcements: " . $e->getMessage());
        return [];
    }
}

/**
 * Get current user information from session
 * @return array|null Array with userId and userType, or null if not logged in
 */
function getCurrentUserInfo() {
    if (isset($_SESSION['user_id'])) {
        return [
            'userId' => $_SESSION['user_id'],
            'userType' => 'user'
        ];
    } elseif (isset($_SESSION['firefighter_id'])) {
        return [
            'userId' => $_SESSION['firefighter_id'],
            'userType' => 'firefighter'
        ];
    } elseif (isset($_SESSION['admin_id'])) {
        return [
            'userId' => $_SESSION['admin_id'],
            'userType' => 'admin'
        ];
    } elseif (isset($_SESSION['superadmin_id'])) {
        return [
            'userId' => $_SESSION['superadmin_id'],
            'userType' => 'superadmin'
        ];
    }
    
    return null;
}

/**
 * Get announcements for the currently logged-in user
 * @param bool $onlyPublished Whether to only get published announcements
 * @param string|null $currentDate Current date for filtering
 * @return array Array of announcements
 */
function getCurrentUserAnnouncements($onlyPublished = true, $currentDate = null) {
    $userInfo = getCurrentUserInfo();
    
    if (!$userInfo) {
        return [];
    }
    
    return getUserSpecificAnnouncements(
        $userInfo['userId'], 
        $userInfo['userType'], 
        $onlyPublished, 
        $currentDate
    );
}

/**
 * Check if user is authenticated
 * @return bool True if user is logged in
 */
function isUserAuthenticated() {
    return getCurrentUserInfo() !== null;
}

/**
 * Get recent announcements for dashboard widgets
 * @param int $limit Number of announcements to return
 * @return array Array of recent announcements
 */
function getRecentAnnouncements($limit = 5) {
    $userInfo = getCurrentUserInfo();
    
    if (!$userInfo) {
        return [];
    }
    
    $announcements = getUserSpecificAnnouncements(
        $userInfo['userId'], 
        $userInfo['userType'], 
        true
    );
    
    return array_slice($announcements, 0, $limit);
}

/**
 * Get high priority announcements count
 * @return int Number of high priority announcements
 */
function getHighPriorityAnnouncementsCount() {
    $userInfo = getCurrentUserInfo();
    
    if (!$userInfo) {
        return 0;
    }
    
    $announcements = getUserSpecificAnnouncements(
        $userInfo['userId'], 
        $userInfo['userType'], 
        true
    );
    
    $highPriorityCount = 0;
    foreach ($announcements as $announcement) {
        if ($announcement['priority'] === 'high') {
            $highPriorityCount++;
        }
    }
    
    return $highPriorityCount;
}
?> 