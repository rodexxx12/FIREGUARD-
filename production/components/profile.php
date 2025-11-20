<?php
// Include database connection
require_once __DIR__ . '/db_connection.php';

// Default image path
$default_image = '../../images/profile1.jpg'; // Ensure this file exists
$profile_image_url = $default_image; // Default assignment

// Initialize admin data variables
$admin_data = [
    'admin_id' => null,
    'username' => '',
    'full_name' => '',
    'email' => '',
    'contact_number' => '',
    'role' => '',
    'status' => '',
    'profile_image' => null
];

// Function to get the base URL for the image serving script
if (!function_exists('getBaseUrl')) {
    function getBaseUrl() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        
        // Try to get the current script path
        $current_script = $_SERVER['SCRIPT_NAME'] ?? '';
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        
        // Extract the base path
        $path_parts = explode('/', trim($current_script, '/'));
        
        // Find the position of 'FireDetectionSystem' in the path
        $fire_detection_index = array_search('FireDetectionSystem', $path_parts);
        
        if ($fire_detection_index !== false) {
            // Build the path from FireDetectionSystem onwards
            $base_path = implode('/', array_slice($path_parts, 0, $fire_detection_index + 1));
            return $protocol . '://' . $host . '/' . $base_path . '/production/components/';
        }
        
        // Alternative: try to extract from REQUEST_URI
        $uri_parts = explode('/', trim($request_uri, '/'));
        $fire_detection_uri_index = array_search('FireDetectionSystem', $uri_parts);
        
        if ($fire_detection_uri_index !== false) {
            $base_path = implode('/', array_slice($uri_parts, 0, $fire_detection_uri_index + 1));
            return $protocol . '://' . $host . '/' . $base_path . '/production/components/';
        }
        
        // Fallback to a default path
        return $protocol . '://' . $host . '/FireDetectionSystem/production/components/';
    }
}

// Check if admin is logged in
if (isset($_GET['admin_id']) && !empty($_GET['admin_id'])) {
    $admin_id = $_GET['admin_id'];
    error_log("Admin ID found in parameter: " . $admin_id);

    // Database connection using centralized connection
    try {
        $pdo = getDatabaseConnection();
        
        // Get complete admin data from database
        $sql = "SELECT admin_id, username, full_name, email, contact_number, role, status, profile_image, created_at, updated_at FROM admin WHERE admin_id = ? AND status = 'Active'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$admin_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            // Store admin data for use in the component
            $admin_data = $result;
            
            error_log("Retrieved admin data for admin_id: " . $admin_id);
            
            // Handle profile image
            if (!empty($result['profile_image'])) {
                $profile_image = $result['profile_image'];
                
                // Check multiple possible locations for the profile image
                $possible_paths = [
                    __DIR__ . '/../profile/php/uploads/profile_images/' . $profile_image,
                    __DIR__ . '/uploads/' . $profile_image,
                    __DIR__ . '/../uploads/' . $profile_image,
                    '../../uploads/' . $profile_image,
                    '../../profile/php/uploads/profile_images/' . $profile_image
                ];
                
                $image_found = false;
                foreach ($possible_paths as $image_path) {
                    if (file_exists($image_path)) {
                        // Use the absolute path that's working (as shown in the browser)
                        // Add cache-busting parameter to prevent browser caching issues
                        $cache_buster = '?v=' . filemtime($image_path);
                        
                        // Determine the correct URL path based on which file was found
                        if (strpos($image_path, '/profile/php/uploads/profile_images/') !== false) {
                            $profile_image_url = '/production/profile/php/uploads/profile_images/' . $profile_image . $cache_buster;
                        } elseif (strpos($image_path, '/uploads/') !== false) {
                            $profile_image_url = '/production/components/uploads/' . $profile_image . $cache_buster;
                        } else {
                            $profile_image_url = '/production/components/' . $profile_image . $cache_buster;
                        }
                        
                        $image_found = true;
                        error_log("Profile image found at: " . $image_path);
                        error_log("Profile image URL generated: " . $profile_image_url);
                        break;
                    }
                }
                
                if (!$image_found) {
                    error_log("Profile image file not found in any expected location for: " . $profile_image);
                    error_log("Searched paths: " . implode(', ', $possible_paths));
                }
            } else {
                error_log("No profile image set in database for admin_id: " . $admin_id);
            }
            
        } else {
            error_log("No active admin found in database for admin_id: " . $admin_id);
        }
        
    } catch (PDOException $e) {
        error_log("Database connection failed in profile.php: " . $e->getMessage());
        // Keep default image if database fails
    }
} else {
    error_log("No admin_id parameter provided");
}

// Debug logging (remove in production)
if (isset($_GET['debug']) && $_GET['debug'] === 'profile') {
    error_log("Profile.php debug - admin_id: " . (isset($_GET['admin_id']) ? $_GET['admin_id'] : 'NOT SET'));
    error_log("Profile.php debug - profile_image_url: " . $profile_image_url);
    error_log("Profile.php debug - admin_data: " . print_r($admin_data, true));
    error_log("Profile.php debug - current_dir: " . __DIR__);
    error_log("Profile.php debug - base_url: " . getBaseUrl());
}
?>
