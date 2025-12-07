<?php
/**
 * Main Functions File for the Profile Page
 *
 * This file acts as a controller. It includes all necessary components,
 * fetches required data, and handles form submission routing.
 *
 * @package     FireDetectionSystem
 * @subpackage  Profile
 */

// Core components - Order is important
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db_connection.php'; // This loads main database functions
require_once __DIR__ . '/auth.php'; // Performs authentication check
require_once __DIR__ . '/utils.php';

// Handlers for different form submissions
require_once __DIR__ . '/profile_update_handler.php';
require_once __DIR__ . '/password_change_handler.php';
require_once __DIR__ . '/profile_picture_handler.php';

// --- INITIALIZATION ---

$errors = [];

// Verify session and admin_id before proceeding
if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    error_log("ERROR: admin_id not found in session. Session data: " . print_r($_SESSION, true));
    if (!headers_sent()) {
        header("Location: /DEFENDED/index.php");
        exit;
    } else {
        die("Session error: Admin ID not found. Please log in again.");
    }
}

// Get database connection
try {
    $conn = getDBConnection();
    if (!$conn) {
        throw new Exception("Database connection returned null");
    }
} catch (Exception $e) {
    error_log("ERROR: Failed to get database connection: " . $e->getMessage());
    die("Database connection error. Please contact administrator.");
}

// Fetch current admin data. auth.php handles redirection if not logged in.
$admin = getAdminDataFromDB($conn, $_SESSION['admin_id']);

// If getAdminData returns an error, it's a critical failure.
if (isset($admin['error'])) {
    error_log("ERROR: Failed to get admin data: " . $admin['error']);
    // A more user-friendly error page would be ideal in a production environment.
    if (!headers_sent()) {
        header("Location: /DEFENDED/index.php");
        exit;
    } else {
        die("A critical error occurred: " . htmlspecialchars($admin['error']));
    }
}


// --- FORM SUBMISSION ROUTING ---

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formType = $_POST['form_type'] ?? '';

    // Route to the appropriate handler based on the submitted form
    switch ($formType) {
        case 'profile_update':
            handleProfileUpdate($conn, $admin);
            break;

        case 'password_change':
            handlePasswordChange($conn, $admin);
            break;

        case 'profile_picture':
            handleProfilePictureUpload($conn, $admin);
            break;

        default:
            // This case handles submissions with an unknown or missing form_type
            $errors['general'] = "Invalid form submission. Please try again.";
            break;
    }

    // --- DATA REFRESH ON VALIDATION ERROR ---
    
    // The handlers execute a redirect on successful completion.
    // Therefore, this block is only reached if a validation error occurred
    // and the page needs to be re-rendered to display the errors.
    if (!empty($errors)) {
        // We re-fetch the admin data to ensure the view has the most
        // current information from the database, as some operations might
        // have partially succeeded before an error was encountered.
        $refreshedAdmin = getAdminDataFromDB($conn, $_SESSION['admin_id']);
        if (!isset($refreshedAdmin['error'])) {
            $admin = $refreshedAdmin;
        }
    }
} 