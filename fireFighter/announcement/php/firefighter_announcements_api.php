<?php
/**
 * Firefighter Announcements API
 * Provides endpoints for getting announcements specific to firefighters
 */

// Start session to access user authentication
session_start();

// Include the announcement helper functions
require_once 'announcement_helper.php';

// Set JSON content type
header('Content-Type: application/json');

if (!isset($_SESSION['firefighter_id'])) {
    header("Location: ../../../index.php");
    exit();
}
$firefighter_id = $_SESSION['firefighter_id'];

// Handle different HTTP methods
switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        handleGetRequest();
        break;
    case 'POST':
        handlePostRequest();
        break;
    default:
        http_response_code(405);
        echo json_encode([
            'success' => false, 
            'message' => 'Method not allowed'
        ]);
        break;
}

/**
 * Handle GET requests
 */
function handleGetRequest() {
    global $firefighterId;
    
    $action = $_GET['action'] ?? 'get_announcements';
    
    switch ($action) {
        case 'get_announcements':
            getFirefighterAnnouncements($firefighterId);
            break;
        case 'get_all_target_announcements':
            getAllTargetAnnouncements();
            break;
        case 'get_specific_announcements':
            getSpecificFirefighterAnnouncements($firefighterId);
            break;
        case 'get_recent':
            getRecentAnnouncements($firefighterId);
            break;
        case 'get_high_priority':
            getHighPriorityAnnouncements($firefighterId);
            break;
        case 'get_announcement_details':
            getAnnouncementDetails();
            break;
        default:
            http_response_code(400);
            echo json_encode([
                'success' => false, 
                'message' => 'Invalid action'
            ]);
            break;
    }
}

/**
 * Handle POST requests
 */
function handlePostRequest() {
    $action = $_POST['action'] ?? $_GET['action'] ?? 'unknown';
    
    switch ($action) {
        case 'mark_as_read':
            markAnnouncementAsRead();
            break;
        case 'get_filtered_announcements':
            getFilteredAnnouncements();
            break;
        default:
            http_response_code(400);
            echo json_encode([
                'success' => false, 
                'message' => 'Invalid action'
            ]);
            break;
    }
}

/**
 * Get all announcements for a specific firefighter (including 'all' target)
 */
function getFirefighterAnnouncements($firefighterId) {
    try {
        $includeAllTargets = isset($_GET['include_all']) ? filter_var($_GET['include_all'], FILTER_VALIDATE_BOOLEAN) : true;
        $onlyPublished = isset($_GET['published_only']) ? filter_var($_GET['published_only'], FILTER_VALIDATE_BOOLEAN) : true;
        
        $announcements = getFirefighterAnnouncementsWithTargets(
            $firefighterId, 
            $includeAllTargets, 
            $onlyPublished
        );
        
        echo json_encode([
            'success' => true,
            'data' => $announcements,
            'count' => count($announcements),
            'firefighter_id' => $firefighterId,
            'include_all_targets' => $includeAllTargets
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false, 
            'message' => 'Error retrieving announcements: ' . $e->getMessage()
        ]);
    }
}

/**
 * Get announcements with target='all' only
 */
function getAllTargetAnnouncements() {
    try {
        $onlyPublished = isset($_GET['published_only']) ? filter_var($_GET['published_only'], FILTER_VALIDATE_BOOLEAN) : true;
        
        $announcements = getAllFirefightersAnnouncements($onlyPublished);
        
        echo json_encode([
            'success' => true,
            'data' => $announcements,
            'count' => count($announcements),
            'target_type' => 'all'
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false, 
            'message' => 'Error retrieving all target announcements: ' . $e->getMessage()
        ]);
    }
}

/**
 * Get announcements specific to the firefighter only (excluding 'all' target)
 */
function getSpecificFirefighterAnnouncements($firefighterId) {
    try {
        $onlyPublished = isset($_GET['published_only']) ? filter_var($_GET['published_only'], FILTER_VALIDATE_BOOLEAN) : true;
        
        $announcements = getFirefighterAnnouncementsWithTargets(
            $firefighterId, 
            false, // Don't include 'all' target
            $onlyPublished
        );
        
        echo json_encode([
            'success' => true,
            'data' => $announcements,
            'count' => count($announcements),
            'firefighter_id' => $firefighterId,
            'target_types' => ['firefighter', 'all_firefighters']
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false, 
            'message' => 'Error retrieving specific firefighter announcements: ' . $e->getMessage()
        ]);
    }
}

/**
 * Get recent announcements for the firefighter
 */
function getRecentAnnouncements($firefighterId) {
    try {
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 5;
        $limit = max(1, min(50, $limit)); // Limit between 1 and 50
        
        $announcements = getFirefighterAnnouncementsWithTargets(
            $firefighterId, 
            true, 
            true
        );
        
        $recentAnnouncements = array_slice($announcements, 0, $limit);
        
        echo json_encode([
            'success' => true,
            'data' => $recentAnnouncements,
            'count' => count($recentAnnouncements),
            'limit' => $limit,
            'firefighter_id' => $firefighterId
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false, 
            'message' => 'Error retrieving recent announcements: ' . $e->getMessage()
        ]);
    }
}

/**
 * Get high priority announcements for the firefighter
 */
function getHighPriorityAnnouncements($firefighterId) {
    try {
        $announcements = getFirefighterAnnouncementsWithTargets(
            $firefighterId, 
            true, 
            true
        );
        
        $highPriorityAnnouncements = array_filter($announcements, function($announcement) {
            return $announcement['priority'] === 'high';
        });
        
        echo json_encode([
            'success' => true,
            'data' => array_values($highPriorityAnnouncements),
            'count' => count($highPriorityAnnouncements),
            'firefighter_id' => $firefighterId,
            'priority' => 'high'
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false, 
            'message' => 'Error retrieving high priority announcements: ' . $e->getMessage()
        ]);
    }
}

/**
 * Get details of a specific announcement
 */
function getAnnouncementDetails() {
    try {
        $announcementId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        if ($announcementId <= 0) {
            http_response_code(400);
            echo json_encode([
                'success' => false, 
                'message' => 'Invalid announcement ID'
            ]);
            return;
        }
        
        $pdo = getAnnouncementDBConnection();
        if (!$pdo) {
            http_response_code(500);
            echo json_encode([
                'success' => false, 
                'message' => 'Database connection failed'
            ]);
            return;
        }
        
        $sql = "
            SELECT a.*, 
                   COALESCE(ad.username, sad.username) as author_name,
                   at.target_type,
                   CASE 
                       WHEN at.target_type = 'firefighter' THEN 'Specific Firefighter'
                       WHEN at.target_type = 'all_firefighters' THEN 'All Firefighters'
                       WHEN at.target_type = 'all' THEN 'All Users'
                       ELSE 'Unknown'
                   END as target_description
            FROM announcements a
            JOIN announcement_targets at ON a.id = at.announcement_id
            LEFT JOIN admin ad ON a.author_id = ad.admin_id
            LEFT JOIN superadmin sad ON a.author_id = sad.superadmin_id
            WHERE a.id = :announcementId
            AND (
                (at.target_type = 'firefighter' AND at.firefighter_id = :firefighterId)
                OR at.target_type = 'all_firefighters'
                OR at.target_type = 'all'
            )
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'announcementId' => $announcementId,
            'firefighterId' => $GLOBALS['firefighterId']
        ]);
        
        $announcement = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$announcement) {
            http_response_code(404);
            echo json_encode([
                'success' => false, 
                'message' => 'Announcement not found or not accessible'
            ]);
            return;
        }
        
        echo json_encode([
            'success' => true,
            'data' => $announcement
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false, 
            'message' => 'Error retrieving announcement details: ' . $e->getMessage()
        ]);
    }
}

/**
 * Mark an announcement as read (placeholder for future implementation)
 */
function markAnnouncementAsRead() {
    // This is a placeholder for future implementation
    // You can implement a read tracking system here
    
    $announcementId = isset($_POST['announcement_id']) ? (int)$_POST['announcement_id'] : 0;
    
    if ($announcementId <= 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'message' => 'Invalid announcement ID'
        ]);
        return;
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Announcement marked as read',
        'announcement_id' => $announcementId
    ]);
}

/**
 * Get filtered announcements based on criteria
 */
function getFilteredAnnouncements() {
    global $firefighterId;
    
    try {
        $priority = $_POST['priority'] ?? null;
        $targetType = $_POST['target_type'] ?? null;
        $dateFrom = $_POST['date_from'] ?? null;
        $dateTo = $_POST['date_to'] ?? null;
        
        $announcements = getFirefighterAnnouncementsWithTargets(
            $firefighterId, 
            true, 
            true
        );
        
        // Apply filters
        $filteredAnnouncements = array_filter($announcements, function($announcement) use ($priority, $targetType, $dateFrom, $dateTo) {
            // Priority filter
            if ($priority && $announcement['priority'] !== $priority) {
                return false;
            }
            
            // Target type filter
            if ($targetType && $announcement['target_type'] !== $targetType) {
                return false;
            }
            
            // Date range filter
            if ($dateFrom && strtotime($announcement['start_date']) < strtotime($dateFrom)) {
                return false;
            }
            
            if ($dateTo && strtotime($announcement['start_date']) > strtotime($dateTo)) {
                return false;
            }
            
            return true;
        });
        
        echo json_encode([
            'success' => true,
            'data' => array_values($filteredAnnouncements),
            'count' => count($filteredAnnouncements),
            'filters' => [
                'priority' => $priority,
                'target_type' => $targetType,
                'date_from' => $dateFrom,
                'date_to' => $dateTo
            ]
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false, 
            'message' => 'Error filtering announcements: ' . $e->getMessage()
        ]);
    }
}
?> 