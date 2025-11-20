<?php
// Session handling removed - no longer needed

// Default image path
$default_image = '../../images/profile1.jpg'; // Ensure this file exists
$profile_image_url = $default_image; // Default assignment

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

// Session-dependent code removed - using default profile image

// Debug logging removed - no longer needed
?>
