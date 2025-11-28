<?php
/**
 * Test UserPhone.php Database Connection
 * 
 * Tests if the database connection works for UserPhone.php
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "🔍 Testing UserPhone.php Database Connection\n";
echo "===========================================\n\n";

// Simulate the path structure
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

// Start session for testing
session_start();

// Test 1: Load database connection
echo "1. Loading database connection...\n";
try {
    require_once __DIR__ . '/../userdashboard/phone/db_connection.php';
    echo "   ✅ db_connection.php loaded\n";
} catch (Exception $e) {
    echo "   ❌ Failed to load db_connection.php: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 2: Get database connection
echo "\n2. Getting database connection...\n";
try {
    $db = getDatabaseConnection();
    if ($db) {
        echo "   ✅ Database connection successful!\n";
    } else {
        echo "   ❌ Database connection returned null\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "   ❌ Database connection failed: " . $e->getMessage() . "\n";
    echo "   📝 Error details:\n";
    echo "      File: " . $e->getFile() . "\n";
    echo "      Line: " . $e->getLine() . "\n";
    exit(1);
}

// Test 3: Check if user_phone_numbers table exists
echo "\n3. Checking user_phone_numbers table...\n";
try {
    $tableCheck = $db->query("SHOW TABLES LIKE 'user_phone_numbers'");
    if ($tableCheck && $tableCheck->rowCount() > 0) {
        echo "   ✅ Table 'user_phone_numbers' exists\n";
    } else {
        echo "   ⚠️  Table 'user_phone_numbers' does not exist (will be created on first use)\n";
    }
} catch (Exception $e) {
    echo "   ❌ Error checking table: " . $e->getMessage() . "\n";
}

// Test 4: Test a simple query
echo "\n4. Testing simple query...\n";
try {
    $stmt = $db->query("SELECT 1 as test");
    $result = $stmt->fetch();
    if ($result && $result['test'] == 1) {
        echo "   ✅ Query execution successful!\n";
    } else {
        echo "   ⚠️  Query executed but returned unexpected result\n";
    }
} catch (Exception $e) {
    echo "   ❌ Query failed: " . $e->getMessage() . "\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "✅ All tests completed!\n";
echo "\nIf you see errors above, check:\n";
echo "1. Database server is running\n";
echo "2. .env file has correct database credentials\n";
echo "3. Database user has proper permissions\n";

