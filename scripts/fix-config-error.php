<?php
/**
 * Fix Configuration Error Script
 * 
 * This script helps diagnose and fix the "System configuration error"
 */

echo "🔧 Configuration Error Fix Tool\n";
echo "===============================\n\n";

// Step 1: Check if .env exists
echo "Step 1: Checking .env file...\n";
$envPath = __DIR__ . '/../.env';
if (!file_exists($envPath)) {
    echo "❌ .env file not found at: $envPath\n";
    echo "   Creating from template...\n";
    
    // Read .env.example if exists
    $examplePath = __DIR__ . '/../.env.example';
    if (file_exists($examplePath)) {
        copy($examplePath, $envPath);
        echo "   ✅ Created .env from .env.example\n";
        echo "   ⚠️  Please update with your actual values!\n";
    } else {
        // Create basic .env
        $envContent = "# Database Configuration\n";
        $envContent .= "DB_HOST=localhost\n";
        $envContent .= "DB_NAME=firedb\n";
        $envContent .= "DB_USER=root\n";
        $envContent .= "DB_PASS=\n\n";
        $envContent .= "# Application Environment\n";
        $envContent .= "APP_ENV=development\n";
        $envContent .= "APP_DEBUG=true\n";
        
        file_put_contents($envPath, $envContent);
        echo "   ✅ Created basic .env file\n";
        echo "   ⚠️  Please update with your actual database credentials!\n";
    }
} else {
    echo "   ✅ .env file exists\n";
}

// Step 2: Load and verify configuration
echo "\nStep 2: Verifying configuration...\n";
require_once __DIR__ . '/../core/config/env.php';
require_once __DIR__ . '/../core/config/config.php';

$dbName = env('DB_NAME', '');
$dbUser = env('DB_USER', '');

if (empty($dbName) || empty($dbUser)) {
    echo "   ❌ Missing required database configuration\n";
    if (empty($dbName)) {
        echo "      - DB_NAME is empty\n";
    }
    if (empty($dbUser)) {
        echo "      - DB_USER is empty\n";
    }
    echo "\n   📝 To fix, edit .env file and add:\n";
    echo "      DB_NAME=your_database_name\n";
    echo "      DB_USER=your_database_user\n";
    echo "      DB_PASS=your_database_password\n";
} else {
    echo "   ✅ Database configuration looks good\n";
    
    // Test connection
    echo "\nStep 3: Testing database connection...\n";
    try {
        $host = env('DB_HOST', 'localhost');
        $dbName = env('DB_NAME', '');
        $username = env('DB_USER', '');
        $password = env('DB_PASS', '');
        
        $dsn = "mysql:host={$host};dbname={$dbName};charset=utf8mb4";
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5
        ]);
        
        echo "   ✅ Database connection successful!\n";
        echo "   ✅ Configuration is working correctly!\n";
    } catch (PDOException $e) {
        echo "   ❌ Database connection failed: " . $e->getMessage() . "\n";
        echo "\n   Possible issues:\n";
        echo "   1. Database server is not running\n";
        echo "   2. Wrong database credentials\n";
        echo "   3. Database doesn't exist\n";
        echo "   4. User doesn't have permissions\n";
    }
}

// Step 4: Check for common issues
echo "\nStep 4: Checking for common issues...\n";

// Check if functions are defined
$functions = [
    'config' => 'core/config/config.php',
    'env' => 'core/config/env.php',
    'getDatabaseConnection' => 'core/database/database.php'
];

foreach ($functions as $func => $file) {
    if (!function_exists($func)) {
        $fullPath = __DIR__ . '/../' . $file;
        if (file_exists($fullPath)) {
            echo "   ⚠️  Function '{$func}' not loaded. File exists: {$file}\n";
            echo "      Make sure file includes core/config/config.php first\n";
        } else {
            echo "   ❌ File missing: {$file}\n";
        }
    } else {
        echo "   ✅ Function '{$func}' is available\n";
    }
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "✅ Diagnostics complete!\n";
echo "\nIf you're still seeing 'System configuration error':\n";
echo "1. Check error logs in: logs/php_errors.log\n";
echo "2. Run: php scripts/diagnose-config.php\n";
echo "3. Verify .env file has all required values\n";
echo "4. Ensure files include core/config/config.php at the start\n";

