<?php
function handleProfileUpdate($conn, $currentAdmin) {
    global $errors;
    
    $updates = [];
    $validationRules = [
        'full_name' => [
            'filter' => FILTER_SANITIZE_STRING,
            'required' => true,
            'min_length' => 3,
            'max_length' => 100
        ],
        'email' => [
            'filter' => FILTER_SANITIZE_EMAIL,
            'required' => true,
            'validate_email' => true,
            'unique' => true,
            'current_value' => $currentAdmin['email']
        ],
        'contact_number' => [
            'filter' => FILTER_SANITIZE_STRING,
            'required' => true,
            'pattern' => '/^[0-9]{10,15}$/',
            'error_msg' => 'Contact number must be 10-15 digits',
            'unique' => true,
            'current_value' => $currentAdmin['contact_number']
        ]
    ];
    
    foreach ($validationRules as $field => $rules) {
        $value = filter_input(INPUT_POST, $field, $rules['filter']);
        
        if ($rules['required'] && empty($value)) {
            $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . " is required";
            continue;
        }
        
        if (isset($rules['min_length']) && strlen($value) < $rules['min_length']) {
            $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . " must be at least {$rules['min_length']} characters";
            continue;
        }
        
        if (isset($rules['max_length']) && strlen($value) > $rules['max_length']) {
            $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . " must not exceed {$rules['max_length']} characters";
            continue;
        }
        
        if ($field === 'contact_number' && isset($rules['pattern'])) {
            if (!preg_match($rules['pattern'], $value)) {
                $errors[$field] = $rules['error_msg'] ?? "Invalid format";
                continue;
            }
            
            // Check if contact number is unique (if changed)
            if ($rules['unique'] && $value !== $rules['current_value']) {
                try {
                    $stmt = $conn->prepare("SELECT admin_id FROM admin WHERE contact_number = ?");
                    $stmt->execute([$value]);
                    if ($stmt->fetch()) {
                        $errors[$field] = "Contact number is already in use";
                        continue;
                    }
                } catch(PDOException $e) {
                    error_log("Contact number uniqueness check failed: " . $e->getMessage());
                    $errors['general'] = "Validation error occurred";
                    continue;
                }
            }
        }
        
        if ($field === 'email' && $rules['validate_email']) {
            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[$field] = "Invalid email address";
                continue;
            }
            
            // Check if email is unique (if changed)
            if ($rules['unique'] && $value !== $rules['current_value']) {
                try {
                    $stmt = $conn->prepare("SELECT admin_id FROM admin WHERE email = ?");
                    $stmt->execute([$value]);
                    if ($stmt->fetch()) {
                        $errors[$field] = "Email address is already in use";
                        continue;
                    }
                } catch(PDOException $e) {
                    error_log("Email uniqueness check failed: " . $e->getMessage());
                    $errors['general'] = "Validation error occurred";
                    continue;
                }
            }
        }
        
        $updates[$field] = $value;
    }
    
    // Update if no errors
    if (empty($errors)) {
        try {
            $setParts = [];
            $params = [];
            foreach ($updates as $field => $value) {
                $setParts[] = "$field = ?";
                $params[] = $value;
            }
            $params[] = $_SESSION['admin_id'];
            
            $sql = "UPDATE admin SET " . implode(', ', $setParts) . " WHERE admin_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            
            $_SESSION['swal'] = [
                'title' => 'Success!',
                'text' => 'Profile updated successfully!',
                'icon' => 'success',
                'confirmButtonText' => 'OK'
            ];
            
            header("Location: ".$_SERVER['PHP_SELF']);
            exit;
            
        } catch(PDOException $e) {
            error_log("Profile update failed: " . $e->getMessage());
            $_SESSION['swal'] = [
                'title' => 'Error!',
                'text' => 'Failed to update profile: '.$e->getMessage(),
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