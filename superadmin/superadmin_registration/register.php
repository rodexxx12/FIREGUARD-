<?php
require_once 'config.php';

$errors = [];
$success = false;

// Check if superadmin table exists
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'superadmin'");
    if ($stmt->rowCount() == 0) {
        // Create the superadmin table if it doesn't exist
        $createTableSQL = "
        CREATE TABLE `superadmin` (
            `superadmin_id` int(11) NOT NULL AUTO_INCREMENT,
            `username` varchar(50) NOT NULL,
            `password` varchar(255) NOT NULL,
            `email` varchar(100) NOT NULL,
            `contact_number` varchar(15) DEFAULT NULL,
            `role` enum('superadmin','fire_officer','system_admin') NOT NULL DEFAULT 'superadmin',
            `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
            `full_name` varchar(100) NOT NULL,
            `remember_token` varchar(255) DEFAULT NULL,
            `token_expiry` datetime DEFAULT NULL,
            `profile_image` varchar(255) DEFAULT NULL,
            `last_login` datetime DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`superadmin_id`),
            UNIQUE KEY `username` (`username`),
            UNIQUE KEY `email` (`email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
        
        $pdo->exec($createTableSQL);
    }
} catch (PDOException $e) {
    $errors[] = 'Database setup error: ' . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate inputs
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');

    if (empty($username)) {
        $errors[] = 'Username is required';
    } elseif (strlen($username) > 50) {
        $errors[] = 'Username must be 50 characters or less';
    }

    if (empty($password)) {
        $errors[] = 'Password is required';
    } elseif (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters';
    } elseif ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match';
    }

    if (empty($email)) {
        $errors[] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    } elseif (strlen($email) > 100) {
        $errors[] = 'Email must be 100 characters or less';
    }

    if (empty($full_name)) {
        $errors[] = 'Full name is required';
    } elseif (strlen($full_name) > 100) {
        $errors[] = 'Full name must be 100 characters or less';
    }

    // Check if username or email already exists
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM superadmin WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            $count = $stmt->fetchColumn();
            
            // Debug information
            if ($count > 0) {
                // Check which field is causing the conflict
                $stmt = $pdo->prepare("SELECT username, email FROM superadmin WHERE username = ? OR email = ?");
                $stmt->execute([$username, $email]);
                $existing = $stmt->fetch();
                
                if ($existing['username'] === $username) {
                    $errors[] = 'Username already exists';
                }
                if ($existing['email'] === $email) {
                    $errors[] = 'Email already exists';
                }
            }
        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }

    // Insert new superadmin
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $created_at = date('Y-m-d H:i:s');
        
        try {
            $stmt = $pdo->prepare("INSERT INTO superadmin 
                (username, password, email, full_name, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $username, 
                $hashed_password, 
                $email, 
                $full_name, 
                $created_at, 
                $created_at
            ]);
            
            $success = true;
        } catch (PDOException $e) {
            $errors[] = 'Registration failed: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Superadmin Registration</title>
    <style>
        .error { color: red; }
        .success { color: green; }
        .debug { background-color: #f0f0f0; padding: 10px; margin: 10px 0; border: 1px solid #ccc; font-family: monospace; font-size: 12px; }
        
        .password-container {
            position: relative;
            display: inline-block;
            width: 100%;
        }
        
        .password-container input {
            width: 100%;
            padding-right: 40px;
            box-sizing: border-box;
        }
        
        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            font-size: 16px;
            color: #666;
        }
        
        .password-toggle:hover {
            color: #333;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .form-group input {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        button[type="submit"] {
            background-color: #007bff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        
        button[type="submit"]:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>
    <h1>Superadmin Registration</h1>
    
    <!-- Debug Information -->
    <div class="debug">
        <strong>Debug Information:</strong><br>
        Database: <?php echo $db; ?><br>
        Host: <?php echo $host; ?><br>
        User: <?php echo $user; ?><br>
        <?php
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM superadmin");
            $result = $stmt->fetch();
            echo "Superadmin table exists. Records: " . $result['count'] . "<br>";
        } catch (PDOException $e) {
            echo "Error checking superadmin table: " . $e->getMessage() . "<br>";
        }
        ?>
    </div>
    
    <?php if ($success): ?>
        <p class="success">Registration successful! You can now login.</p>
    <?php else: ?>
        <?php foreach ($errors as $error): ?>
            <p class="error"><?php echo htmlspecialchars($error); ?></p>
        <?php endforeach; ?>
        
        <form method="post">
            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required maxlength="50"
                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="password">Password:</label>
                <div class="password-container">
                    <input type="password" id="password" name="password" required minlength="8">
                    <button type="button" class="password-toggle" onclick="togglePassword('password')">
                        👁️
                    </button>
                </div>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm Password:</label>
                <div class="password-container">
                    <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
                    <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">
                        👁️
                    </button>
                </div>
            </div>
            
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required maxlength="100"
                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="full_name">Full Name:</label>
                <input type="text" id="full_name" name="full_name" required maxlength="100"
                       value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>">
            </div>
            
            <button type="submit">Register</button>
        </form>
    <?php endif; ?>

    <script>
        function togglePassword(fieldId) {
            const passwordField = document.getElementById(fieldId);
            const toggleButton = passwordField.nextElementSibling;
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                toggleButton.textContent = '🙈';
            } else {
                passwordField.type = 'password';
                toggleButton.textContent = '👁️';
            }
        }
    </script>
</body>
</html>