<?php
/**
 * Configuration Diagnostics Tool
 * 
 * Checks if all required configuration is properly set up
 */

echo "🔍 Configuration Diagnostics Tool\n";
echo "==================================\n\n";

$errors = [];
$warnings = [];

// Check .env file exists
echo "1. Checking .env file...\n";
$envPaths = [
    __DIR__ . '/../.env',
    dirname(__DIR__) . '/.env'
];

$envFound = false;
$envPath = null;

foreach ($envPaths as $path) {
    if (file_exists($path)) {
        $envFound = true;
        $envPath = $path;
        echo "   ✅ Found at: $path\n";
        break;
    }
}

if (!$envFound) {
    $errors[] = ".env file not found in any expected location";
    echo "   ❌ .env file not found\n";
    echo "   Expected locations:\n";
    foreach ($envPaths as $path) {
        echo "     - $path\n";
    }
} else {
    // Load environment
    require_once __DIR__ . '/../core/config/env.php';
    require_once __DIR__ . '/../core/config/config.php';
    
    // Check database configuration
    echo "\n2. Checking database configuration...\n";
    
    $dbHost = env('DB_HOST', '');
    $dbName = env('DB_NAME', '');
    $dbUser = env('DB_USER', '');
    $dbPass = env('DB_PASS', '');
    
    echo "   DB_HOST: " . ($dbHost ?: '(empty)') . "\n";
    echo "   DB_NAME: " . ($dbName ?: '(empty)') . "\n";
    echo "   DB_USER: " . ($dbUser ?: '(empty)') . "\n";
    echo "   DB_PASS: " . ($dbPass ? str_repeat('*', strlen($dbPass)) : '(empty)') . "\n";
    
    if (empty($dbHost)) {
        $errors[] = "DB_HOST is not set in .env";
        echo "   ❌ DB_HOST is missing\n";
    } else {
        echo "   ✅ DB_HOST is set\n";
    }
    
    if (empty($dbName)) {
        $errors[] = "DB_NAME is not set in .env";
        echo "   ❌ DB_NAME is missing\n";
    } else {
        echo "   ✅ DB_NAME is set\n";
    }
    
    if (empty($dbUser)) {
        $errors[] = "DB_USER is not set in .env";
        echo "   ❌ DB_USER is missing\n";
    } else {
        echo "   ✅ DB_USER is set\n";
    }
    
    if (empty($dbPass)) {
        $warnings[] = "DB_PASS is empty (might be intentional for local development)";
        echo "   ⚠️  DB_PASS is empty\n";
    } else {
        echo "   ✅ DB_PASS is set\n";
    }
    
    // Test database connection
    if (!empty($dbHost) && !empty($dbName) && !empty($dbUser)) {
        echo "\n3. Testing database connection...\n";
        try {
            $dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";
            $pdo = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5
            ]);
            echo "   ✅ Database connection successful!\n";
        } catch (PDOException $e) {
            $errors[] = "Database connection failed: " . $e->getMessage();
            echo "   ❌ Database connection failed: " . $e->getMessage() . "\n";
        }
    }
    
    // Check required environment variables
    echo "\n4. Checking required environment variables...\n";
    $required = ['DB_HOST', 'DB_NAME', 'DB_USER'];
    foreach ($required as $var) {
        $value = env($var, '');
        if (empty($value)) {
            echo "   ❌ $var is missing\n";
        } else {
            echo "   ✅ $var is set\n";
        }
    }
    
    // Check optional but recommended
    echo "\n5. Checking recommended environment variables...\n";
    $recommended = ['APP_ENV', 'APP_URL'];
    foreach ($recommended as $var) {
        $value = env($var, '');
        if (empty($value)) {
            echo "   ⚠️  $var is not set (optional)\n";
            $warnings[] = "$var is not set (optional but recommended)";
        } else {
            echo "   ✅ $var is set: $value\n";
        }
    }
}

// Summary
echo "\n" . str_repeat("=", 50) . "\n";
echo "📊 Diagnostics Summary\n";
echo str_repeat("=", 50) . "\n";

if (empty($errors)) {
    echo "✅ No critical errors found!\n";
} else {
    echo "❌ " . count($errors) . " error(s) found:\n";
    foreach ($errors as $error) {
        echo "   - $error\n";
    }
}

if (!empty($warnings)) {
    echo "\n⚠️  " . count($warnings) . " warning(s):\n";
    foreach ($warnings as $warning) {
        echo "   - $warning\n";
    }
}

if (empty($errors) && empty($warnings)) {
    echo "\n🎉 Configuration is perfect!\n";
} elseif (empty($errors)) {
    echo "\n✅ Configuration is functional (with warnings)\n";
} else {
    echo "\n❌ Configuration needs fixes\n";
    echo "\n📝 To fix:\n";
    echo "1. Ensure .env file exists in project root\n";
    echo "2. Add required database credentials:\n";
    echo "   DB_HOST=localhost\n";
    echo "   DB_NAME=your_database\n";
    echo "   DB_USER=your_user\n";
    echo "   DB_PASS=your_password\n";
}

echo "\n";

