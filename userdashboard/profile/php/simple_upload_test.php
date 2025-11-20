<?php
// Include database connection
require_once '../functions/db_connection.php';

session_start();

// Check if admin is logged in
if (!isset($_SESSION['user_id'])) {
    echo "<h2>Not Logged In</h2>";
    echo "<p>Please log in as an admin to test profile picture uploads.</p>";
    echo "<a href='../../../login/php/login.php'>Go to Login</a>";
    exit;
}

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_image'])) {
    $file = $_FILES['profile_image'];
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        // Validate file type
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        if (!in_array($file['type'], $allowed_types)) {
            $error = "Invalid file type. Only JPG, PNG, and GIF are allowed.";
        } else {
            // Validate file size (2MB)
            if ($file['size'] > 2 * 1024 * 1024) {
                $error = "File size too large. Maximum 2MB allowed.";
            } else {
                // Generate unique filename
                $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $new_filename = 'test_' . time() . '_' . uniqid() . '.' . $extension;
                $upload_path = __DIR__ . '/uploads/profile_images/' . $new_filename;
                
                if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                    // Update database
                    try {
                        $pdo = getDatabaseConnection();
                        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                        
                        $stmt = $pdo->prepare("UPDATE admin SET profile_image = ? WHERE admin_id = ?");
                        if ($stmt->execute([$new_filename, $_SESSION['admin_id']])) {
                            $success = "Profile picture updated successfully!";
                        } else {
                            $error = "Failed to update database.";
                        }
                    } catch (PDOException $e) {
                        $error = "Database error: " . $e->getMessage();
                    }
                } else {
                    $error = "Failed to upload file.";
                }
            }
        }
    } else {
        $error = "File upload error: " . $file['error'];
    }
}

// Get current profile image
try {
    $pdo = getDatabaseConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $pdo->prepare("SELECT profile_image FROM admin WHERE admin_id = ?");
    $stmt->execute([$_SESSION['admin_id']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $current_image = $result['profile_image'] ?? null;
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Picture Upload Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3>Profile Picture Upload Test</h3>
                    </div>
                    <div class="card-body">
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        
                        <?php if (isset($success)): ?>
                            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                        <?php endif; ?>
                        
                        <!-- Current Profile Picture -->
                        <div class="text-center mb-4">
                            <h5>Current Profile Picture</h5>
                            <?php if ($current_image && file_exists(__DIR__ . '/uploads/profile_images/' . $current_image)): ?>
                                <img src="uploads/profile_images/<?php echo htmlspecialchars($current_image); ?>" 
                                     alt="Current Profile" class="img-fluid rounded" style="max-width: 200px;">
                                <p class="mt-2"><small>File: <?php echo htmlspecialchars($current_image); ?></small></p>
                            <?php else: ?>
                                <div class="bg-secondary text-white rounded d-flex align-items-center justify-content-center" 
                                     style="width: 200px; height: 200px; margin: 0 auto;">
                                    <span>No Profile Picture</span>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Upload Form -->
                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="profile_image" class="form-label">Select New Profile Picture</label>
                                <input type="file" class="form-control" name="profile_image" id="profile_image" 
                                       accept="image/*" required>
                                <div class="form-text">JPG, PNG, or GIF (Max 2MB)</div>
                            </div>
                            <button type="submit" class="btn btn-primary">Upload Profile Picture</button>
                        </form>
                        
                        <hr>
                        
                        <!-- Test Links -->
                        <div class="mt-4">
                            <h5>Test Links</h5>
                            <a href="test_profile_fix.php" class="btn btn-outline-info btn-sm">Run Diagnostic Test</a>
                            <a href="main.php" class="btn btn-outline-success btn-sm">Go to Profile Page</a>
                            <a href="../../../production/" class="btn btn-outline-secondary btn-sm">Go to Dashboard</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html> 