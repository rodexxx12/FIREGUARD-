<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include the centralized database connection
require_once __DIR__ . '/../../db/db.php';

// Helper function to get user profile image
if (!function_exists('getUserProfileImage')) {
    function getUserProfileImage($username) {
        $conn = getDatabaseConnection();
        $default_image = '../../images/profile1.jpg';
        
        try {
            $sql = "SELECT profile_image FROM users WHERE username = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$username]);
            $result = $stmt->fetch();
            
            if ($result && !empty($result['profile_image']) && file_exists("../../profile/uploads/profile_images/" . $result['profile_image'])) {
                return '../../profile/uploads/profile_images/' . htmlspecialchars($result['profile_image']);
            }
        } catch(PDOException $e) {
            error_log("Error fetching profile image: " . $e->getMessage());
        }
        
        return $default_image;
    }
}

// Helper function to get user notifications
if (!function_exists('getUserNotifications')) {
    function getUserNotifications($user_id) {
        $conn = getDatabaseConnection();
        $notifications = [];
        
        try {
            // Check for unassigned devices
            $stmt = $conn->prepare("
                SELECT COUNT(*) as unassigned_count 
                FROM devices 
                WHERE user_id = ? AND building_id IS NULL AND is_active = 1
            ");
            $stmt->execute([$user_id]);
            $unassigned_devices = $stmt->fetch();
            
            if ($unassigned_devices['unassigned_count'] > 0) {
                $notifications[] = [
                    'type' => 'warning',
                    'title' => 'Unassigned Devices',
                    'message' => "You have {$unassigned_devices['unassigned_count']} device" . ($unassigned_devices['unassigned_count'] > 1 ? 's' : '') . " that " . ($unassigned_devices['unassigned_count'] > 1 ? 'are' : 'is') . " not assigned to any building.",
                    'action_text' => 'Assign Devices',
                    'action_url' => '../../assigndevice/php/main.php',
                    'icon' => 'fa-tablet'
                ];
            }
            
            // Check for unregistered buildings
            $stmt = $conn->prepare("
                SELECT COUNT(*) as building_count 
                FROM buildings 
                WHERE user_id = ?
            ");
            $stmt->execute([$user_id]);
            $building_count = $stmt->fetch();
            
            if ($building_count['building_count'] == 0) {
                $notifications[] = [
                    'type' => 'info',
                    'title' => 'No Buildings Registered',
                    'message' => 'You haven\'t registered any buildings yet. Register buildings to assign devices and enable proper monitoring.',
                    'action_text' => 'Register Building',
                    'action_url' => '../../building_registration/index.html',
                    'icon' => 'fa-building'
                ];
            }
            
            // Check for unverified contact numbers
            $stmt = $conn->prepare("
                SELECT COUNT(*) as unverified_count 
                FROM user_phone_numbers 
                WHERE user_id = ? AND verified = 0
            ");
            $stmt->execute([$user_id]);
            $unverified_phones = $stmt->fetch();
            
            if ($unverified_phones['unverified_count'] > 0) {
                $notifications[] = [
                    'type' => 'warning',
                    'title' => 'Unverified Contact Numbers',
                    'message' => "You have {$unverified_phones['unverified_count']} unverified phone number" . ($unverified_phones['unverified_count'] > 1 ? 's' : '') . ". Verify your contact numbers to receive important alerts.",
                    'action_text' => 'Verify Numbers',
                    'action_url' => '../../phone/php/UserPhone.php',
                    'icon' => 'fa-phone'
                ];
            }
            
            // Check for offline devices
            $stmt = $conn->prepare("
                SELECT COUNT(*) as offline_count 
                FROM devices d 
                WHERE d.user_id = ? 
                AND d.is_active = 1 
                AND d.device_id NOT IN (
                    SELECT DISTINCT device_id 
                    FROM fire_data 
                    WHERE timestamp > DATE_SUB(NOW(), INTERVAL 1 HOUR)
                )
            ");
            $stmt->execute([$user_id]);
            $offline_devices = $stmt->fetch();
            
            if ($offline_devices['offline_count'] > 0) {
                $notifications[] = [
                    'type' => 'danger',
                    'title' => 'Offline Devices',
                    'message' => "You have {$offline_devices['offline_count']} device" . ($offline_devices['offline_count'] > 1 ? 's' : '') . " that " . ($offline_devices['offline_count'] > 1 ? 'are' : 'is') . " offline or not sending data.",
                    'action_text' => 'Check Devices',
                    'action_url' => '../../device/php/main.php',
                    'icon' => 'fa-wifi'
                ];
            }
            
        } catch(PDOException $e) {
            error_log("Error fetching notifications: " . $e->getMessage());
        }
        
        return $notifications;
    }
}

// Helper function to get user announcements
if (!function_exists('getUserAnnouncements')) {
    function getUserAnnouncements($user_id, $last_check = null) {
        $conn = getDatabaseConnection();
        $announcements = [];
        
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
                    OR (at.target_type = 'user' AND at.user_id = ?)
                )
            ";
            
            if ($last_check) {
                $sql .= " AND a.created_at > ?";
            }
            $sql .= " ORDER BY a.created_at DESC";
            
            $stmt = $conn->prepare($sql);
            if ($last_check) {
                $stmt->execute([$user_id, $last_check]);
            } else {
                $stmt->execute([$user_id]);
            }
            $admin_announcements = $stmt->fetchAll();
            
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
                    OR (sat.target_type = 'user' AND sat.user_id = ?)
                )
            ";
            
            if ($last_check) {
                $sql2 .= " AND sa.created_at > ?";
            }
            $sql2 .= " ORDER BY sa.created_at DESC";
            
            $stmt2 = $conn->prepare($sql2);
            if ($last_check) {
                $stmt2->execute([$user_id, $last_check]);
            } else {
                $stmt2->execute([$user_id]);
            }
            $superadmin_announcements = $stmt2->fetchAll();
            
            // Combine and sort announcements
            $announcements = array_merge($admin_announcements, $superadmin_announcements);
            usort($announcements, function($a, $b) {
                return strtotime($b['created_at']) - strtotime($a['created_at']);
            });
            
        } catch(PDOException $e) {
            error_log("Error fetching announcements: " . $e->getMessage());
        }
        
        return $announcements;
    }
}

// Helper function to check if user exists
if (!function_exists('userExists')) {
    function userExists($username) {
        $conn = getDatabaseConnection();
        
        try {
            $sql = "SELECT COUNT(*) FROM users WHERE username = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$username]);
            return $stmt->fetchColumn() > 0;
        } catch(PDOException $e) {
            error_log("Error checking user existence: " . $e->getMessage());
            return false;
        }
    }
}

// Helper function to get user data
if (!function_exists('getUserData')) {
    function getUserData($username) {
        $conn = getDatabaseConnection();
        
        try {
            $sql = "SELECT * FROM users WHERE username = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$username]);
            return $stmt->fetch();
        } catch(PDOException $e) {
            error_log("Error fetching user data: " . $e->getMessage());
            return false;
        }
    }
}
?>
