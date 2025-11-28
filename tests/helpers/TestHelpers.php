<?php
/**
 * Test Helper Functions
 * 
 * Common utility functions for testing
 */

/**
 * Create a mock PDO connection for testing
 * 
 * @return PDO Mock PDO connection
 */
function createMockPDO() {
    $pdo = $this->createMock(PDO::class);
    $stmt = $this->createMock(PDOStatement::class);
    $pdo->method('prepare')->willReturn($stmt);
    return $pdo;
}

/**
 * Get test database connection (if using real DB for integration tests)
 * 
 * @return PDO|null
 */
function getTestDatabaseConnection() {
    static $conn = null;
    
    if ($conn === null) {
        $dbName = getenv('DB_TEST_NAME') ?: 'test_firedb';
        $host = getenv('DB_TEST_HOST') ?: 'localhost';
        $user = getenv('DB_TEST_USER') ?: 'root';
        $pass = getenv('DB_TEST_PASS') ?: '';
        
        try {
            $dsn = "mysql:host={$host};dbname={$dbName};charset=utf8mb4";
            $conn = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
        } catch (PDOException $e) {
            return null;
        }
    }
    
    return $conn;
}

/**
 * Clean test data from database
 * 
 * @param PDO $pdo Database connection
 * @param array $tables Tables to clean
 */
function cleanTestData($pdo, array $tables) {
    foreach ($tables as $table) {
        try {
            $pdo->exec("TRUNCATE TABLE {$table}");
        } catch (PDOException $e) {
            // Table might not exist, ignore
        }
    }
}

/**
 * Create test user data
 * 
 * @return array
 */
function getTestUserData() {
    return [
        'username' => 'testuser_' . time(),
        'email' => 'test_' . time() . '@example.com',
        'password' => 'TestPassword123!',
        'fullname' => 'Test User'
    ];
}

/**
 * Assert that array contains required keys
 * 
 * @param array $array Array to check
 * @param array $keys Required keys
 * @param string $message Assertion message
 */
function assertArrayHasKeys(array $array, array $keys, string $message = '') {
    foreach ($keys as $key) {
        if (!array_key_exists($key, $array)) {
            throw new PHPUnit\Framework\AssertionFailedError(
                $message ?: "Array missing required key: {$key}"
            );
        }
    }
}

