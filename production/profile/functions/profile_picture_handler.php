<?php
/**
 * Profile Picture Upload Handler
 * SECURITY: Improved validation and reduced debug logging
 */

// Include security utilities
require_once __DIR__ . '/../../../components/security.php';

function handleProfilePictureUpload($conn, $currentAdmin) {
    global $errors;
    
    // Minimal logging in production
    $appEnv = getenv('APP_ENV') ?: 'production';
    if ($appEnv !== 'production') {
        error_log("Profile picture upload handler called");
    }
    
    // Check if user is logged in
    if (!isset($_SESSION['admin_id'])) {
        $errors['profile_image'] = "You must be logged in as an admin to upload a profile picture. Please log in first.";
        error_log("User not logged in - no admin_id in session");
        return;
    }
    
    // Validate admin_id exists in database
    try {
        $stmt = $conn->prepare("SELECT admin_id FROM admin WHERE admin_id = ?");
        $stmt->execute([$_SESSION['admin_id']]);
        if (!$stmt->fetch()) {
            $errors['profile_image'] = "Invalid admin session. Please log in again.";
            error_log("Admin ID not found in database: " . $_SESSION['admin_id']);
            session_destroy();
            return;
        }
    } catch(PDOException $e) {
        $errors['profile_image'] = "Database error. Please try again.";
        error_log("Database error checking admin: " . $e->getMessage());
        return;
    }
    
    if (!isset($_FILES['profile_image']) || $_FILES['profile_image']['error'] === UPLOAD_ERR_NO_FILE) {
        $errors['profile_image'] = "No file uploaded. Please select an image file.";
        error_log("No file uploaded or file error: " . (isset($_FILES['profile_image']) ? $_FILES['profile_image']['error'] : 'No file'));
        return;
    }
    
    $file = $_FILES['profile_image'];
    
    // Define allowed MIME types for better security
    $allowedMimeTypes = [
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/gif'
    ];
    
    // Use security utility for file validation
    $validation = validateFileUpload($file, $allowedMimeTypes, MAX_PROFILE_IMG_SIZE);
    
    if (!$validation['valid']) {
        $errors['profile_image'] = $validation['error'];
        error_log("File upload validation failed: " . $validation['error']);
        return;
    }
    
    // Additional validation: Check file extension
    $fileInfo = pathinfo($file['name']);
    $extension = strtolower($fileInfo['extension'] ?? '');
    
    if (!in_array($extension, ALLOWED_IMG_TYPES)) {
        $errors['profile_image'] = "Only JPG, JPEG, PNG, and GIF files are allowed";
        error_log("Invalid file extension: " . $extension);
        return;
    }
    
    // Verify MIME type matches extension (additional security check)
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $detectedMimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($detectedMimeType, $allowedMimeTypes)) {
        $errors['profile_image'] = "Invalid file type detected";
        error_log("MIME type mismatch: detected={$detectedMimeType}, extension={$extension}");
        return;
    }
    
    // Generate unique filename
    $newFilename = uniqid('profile_', true) . '.' . $extension;
    
    // Ensure upload directory exists
    $uploadDir = __DIR__ . '/../php/uploads/profile_images/';
    if (!file_exists($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            $errors['profile_image'] = "Failed to create upload directory. Please contact administrator.";
            error_log("Failed to create upload directory: " . $uploadDir);
            return;
        }
        error_log("Created upload directory: " . $uploadDir);
    }
    
    // Check if directory is writable
    if (!is_writable($uploadDir)) {
        $errors['profile_image'] = "Upload directory is not writable. Please contact administrator.";
        error_log("Upload directory not writable: " . $uploadDir);
        return;
    }
    
    $destination = $uploadDir . $newFilename;
    
    error_log("Upload destination: " . $destination);
    error_log("Upload directory exists: " . (file_exists($uploadDir) ? 'Yes' : 'No'));
    error_log("Upload directory writable: " . (is_writable($uploadDir) ? 'Yes' : 'No'));

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        $errors['profile_image'] = "Failed to save uploaded file. Please try again.";
        error_log("Failed to move uploaded file from " . $file['tmp_name'] . " to " . $destination);
        error_log("File exists: " . (file_exists($file['tmp_name']) ? 'Yes' : 'No'));
        error_log("Destination writable: " . (is_writable(dirname($destination)) ? 'Yes' : 'No'));
        return;
    }
    
    error_log("File successfully moved to: " . $destination);
    
    // Delete old profile image if it exists
    $oldImage = $currentAdmin['profile_image'];
    if (!empty($oldImage)) {
        $oldImagePath = $uploadDir . $oldImage;
        if (file_exists($oldImagePath)) {
            if (unlink($oldImagePath)) {
                error_log("Deleted old profile image: " . $oldImagePath);
            } else {
                error_log("Failed to delete old profile image: " . $oldImagePath);
            }
        }
    }
    
    // Update database
    try {
        $stmt = $conn->prepare("UPDATE admin SET profile_image = ? WHERE admin_id = ?");
        $result = $stmt->execute([$newFilename, $_SESSION['admin_id']]);
        
        if ($result) {
            error_log("Database updated successfully for admin_id: " . $_SESSION['admin_id']);
            
            $_SESSION['swal'] = [
                'title' => 'Success!',
                'text' => 'Profile picture updated successfully!',
                'icon' => 'success',
                'confirmButtonText' => 'OK'
            ];
            
            // Redirect to refresh the page
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        } else {
            throw new Exception("Database update failed");
        }
        
    } catch(PDOException $e) {
        error_log("Profile picture update failed: " . $e->getMessage());
        // Delete the uploaded file since DB update failed
        if (file_exists($destination)) {
            unlink($destination);
        }
        
        $errors['profile_image'] = "Failed to update profile picture. Please try again.";
    }
} 