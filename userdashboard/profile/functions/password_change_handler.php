<?php
function handlePasswordChange($conn, $currentAdmin) {
    global $errors;
    
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validate current password
    if (!password_verify($current_password, $currentAdmin['password'])) {
        $errors['current_password'] = "Current password is incorrect";
    }
    
    // Validate new password
    if (empty($new_password)) {
        $errors['new_password'] = "New password is required";
    } elseif (strlen($new_password) < 8) {
        $errors['new_password'] = "Password must be at least 8 characters";
    } elseif (!preg_match('/[A-Z]/', $new_password)) {
        $errors['new_password'] = "Password must contain at least one uppercase letter";
    } elseif (!preg_match('/[a-z]/', $new_password)) {
        $errors['new_password'] = "Password must contain at least one lowercase letter";
    } elseif (!preg_match('/[0-9]/', $new_password)) {
        $errors['new_password'] = "Password must contain at least one number";
    } elseif (!preg_match('/[\W]/', $new_password)) {
        $errors['new_password'] = "Password must contain at least one special character";
    }
    
    // Confirm password match
    if ($new_password !== $confirm_password) {
        $errors['confirm_password'] = "Passwords do not match";
    }
    
    if (empty($errors)) {
        try {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE admin SET password = ? WHERE admin_id = ?");
            $stmt->execute([$hashed_password, $_SESSION['admin_id']]);
            
            $_SESSION['swal'] = [
                'title' => 'Success!',
                'text' => 'Password changed successfully!',
                'icon' => 'success',
                'confirmButtonText' => 'OK'
            ];
            
            header("Location: ".$_SERVER['PHP_SELF']);
            exit;
            
        } catch(PDOException $e) {
            error_log("Password change failed: " . $e->getMessage());
            $_SESSION['swal'] = [
                'title' => 'Error!',
                'text' => 'Failed to change password: '.$e->getMessage(),
                'icon' => 'error',
                'confirmButtonText' => 'OK'
            ];
        }
    } else {
        $_SESSION['swal'] = [
            'title' => 'Validation Error',
            'text' => 'Please correct the errors in the form',
            'icon' => 'warning',
            'confirmButtonText' => 'OK'
        ];
    }
} 