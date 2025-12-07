<?php
// Authentication check using main database functions
// Make sure isAdminLoggedIn function exists
if (!function_exists('isAdminLoggedIn')) {
    error_log("ERROR: isAdminLoggedIn() function not found in auth.php");
    die("Authentication system error. Please contact administrator.");
}

if (!isAdminLoggedIn()) {
    // Check if headers have already been sent
    if (!headers_sent()) {
        // Redirect to a proper login page using relative path
        $loginUrl = '/DEFENDED/index.php';
        header("Location: " . $loginUrl);
        exit;
    } else {
        // If headers already sent, we can't redirect, so we'll just return an error
        // This will be handled by the calling code
        error_log("ERROR: Cannot redirect in auth.php - headers already sent");
        return;
    }
}

// Get admin data from database using main database connection
function getAdminDataFromDB($conn, $admin_id) {
    try {
        // Validate admin_id
        if (empty($admin_id)) {
            error_log("ERROR: getAdminDataFromDB called with empty admin_id");
            return ['error' => "Invalid admin ID"];
        }
        
        $stmt = $conn->prepare("SELECT * FROM admin WHERE admin_id = ?");
        $stmt->execute([$admin_id]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$admin) {
            error_log("ERROR: Admin not found in database for admin_id: " . $admin_id);
            // Check if headers have already been sent before redirecting
            if (!headers_sent()) {
                if (function_exists('adminLogout')) {
                    adminLogout(); // Use main logout function
                }
                $loginUrl = '/DEFENDED/index.php';
                header("Location: " . $loginUrl);
                exit;
            } else {
                // If headers already sent, return error
                return ['error' => "Admin not found and cannot redirect"];
            }
        }
        return $admin;
    } catch(PDOException $e) {
        error_log("Failed to load admin data: " . $e->getMessage());
        error_log("PDO Error Code: " . $e->getCode());
        error_log("Stack trace: " . $e->getTraceAsString());
        return ['error' => "Failed to load admin data: " . $e->getMessage()];
    }
} 