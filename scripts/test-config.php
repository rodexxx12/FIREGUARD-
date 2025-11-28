<?php
/**
 * Quick Configuration Test
 * 
 * Tests if configuration is loading correctly
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "🔍 Quick Configuration Test\n";
echo "===========================\n\n";

try {
    // Test 1: Load environment
    echo "1. Loading environment...\n";
    require_once __DIR__ . '/../core/config/env.php';
    echo "   ✅ Environment loader loaded\n";
    
    // Test 2: Load config
    echo "\n2. Loading configuration...\n";
    require_once __DIR__ . '/../core/config/config.php';
    echo "   ✅ Config loaded\n";
    
    // Test 3: Load database
    echo "\n3. Loading database connection...\n";
    require_once __DIR__ . '/../core/database/database.php';
    echo "   ✅ Database module loaded\n";
    
    // Test 4: Test database connection
    echo "\n4. Testing database connection...\n";
    try {
        $conn = getDatabaseConnection();
        if ($conn) {
            echo "   ✅ Database connection successful!\n";
        } else {
            echo "   ❌ Database connection returned null\n";
        }
    } catch (Exception $e) {
        echo "   ❌ Database connection failed: " . $e->getMessage() . "\n";
        echo "   📝 Error details:\n";
        echo "      " . $e->getTraceAsString() . "\n";
    }
    
    // Test 5: Check configuration values
    echo "\n5. Checking configuration values...\n";
    echo "   DB_HOST: " . (config('db.host', 'NOT SET') ?: 'NOT SET') . "\n";
    echo "   DB_NAME: " . (config('db.name', 'NOT SET') ?: 'NOT SET') . "\n";
    echo "   DB_USER: " . (config('db.user', 'NOT SET') ?: 'NOT SET') . "\n";
    echo "   APP_ENV: " . (config('app_env', 'NOT SET') ?: 'NOT SET') . "\n";
    
    echo "\n✅ All tests completed!\n";
    
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "📝 File: " . $e->getFile() . "\n";
    echo "📝 Line: " . $e->getLine() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
}

