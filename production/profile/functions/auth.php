<?php
// Authentication check using main database functions
if (!isAdminLoggedIn()) {
    // Check if headers have already been sent
    if (!headers_sent()) {
        // Redirect to a proper login page.
        header("Location: " . BASE_URL . "../../index.php");
        exit;
    } else {
        // If headers already sent, we can't redirect, so we'll just return an error
        // This will be handled by the calling code
        return;
    }
}

// Get admin data from database using main database connection
function getAdminDataFromDB($conn, $admin_id) {
    try {
        $stmt = $conn->prepare("SELECT * FROM admin WHERE admin_id = ?");
        $stmt->execute([$admin_id]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$admin) {
            // Check if headers have already been sent before redirecting
            if (!headers_sent()) {
                adminLogout(); // Use main logout function
                header("Location: " . BASE_URL . "/login/php/login.php");
                exit;
            } else {
                // If headers already sent, return error
                return ['error' => "Admin not found and cannot redirect"];
            }
        }
        return $admin;
    } catch(PDOException $e) {
        error_log("Failed to load admin data: " . $e->getMessage());
        return ['error' => "Failed to load admin data"];
    }
} 