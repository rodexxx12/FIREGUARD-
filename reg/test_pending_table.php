<?php
/**
 * Test script to verify pending_registrations table setup
 * Run this file in your browser to check if the table exists and is properly configured
 */

require_once 'db_config.php';

header('Content-Type: application/json');

$results = [
    'status' => 'success',
    'checks' => []
];

try {
    $conn = getDatabaseConnection();
    
    // Check 1: Table exists
    $checkTable = $conn->query("SHOW TABLES LIKE 'pending_registrations'");
    $tableExists = $checkTable->rowCount() > 0;
    $results['checks']['table_exists'] = [
        'status' => $tableExists ? 'PASS' : 'FAIL',
        'message' => $tableExists ? 'Table pending_registrations exists' : 'Table pending_registrations does NOT exist'
    ];
    
    if ($tableExists) {
        // Check 2: Table structure
        $describe = $conn->query("DESCRIBE pending_registrations");
        $columns = $describe->fetchAll(PDO::FETCH_ASSOC);
        $columnNames = array_column($columns, 'Field');
        
        $requiredColumns = ['id', 'user_id', 'pending_data', 'created_at'];
        $hasAllColumns = empty(array_diff($requiredColumns, $columnNames));
        
        $results['checks']['table_structure'] = [
            'status' => $hasAllColumns ? 'PASS' : 'FAIL',
            'message' => $hasAllColumns ? 'All required columns present' : 'Missing columns',
            'columns' => $columnNames,
            'required' => $requiredColumns
        ];
        
        // Check 3: Foreign key constraint
        $fkQuery = $conn->query("
            SELECT CONSTRAINT_NAME 
            FROM information_schema.KEY_COLUMN_USAGE 
            WHERE TABLE_NAME = 'pending_registrations' 
            AND CONSTRAINT_NAME != 'PRIMARY' 
            AND REFERENCED_TABLE_NAME = 'users'
        ");
        $hasForeignKey = $fkQuery->rowCount() > 0;
        
        $results['checks']['foreign_key'] = [
            'status' => $hasForeignKey ? 'PASS' : 'WARNING',
            'message' => $hasForeignKey ? 'Foreign key constraint exists' : 'No foreign key constraint found (not critical)'
        ];
        
        // Check 4: JSON column type
        $jsonColumn = array_filter($columns, function($col) {
            return $col['Field'] === 'pending_data';
        });
        $jsonColumn = reset($jsonColumn);
        $isJson = strpos(strtolower($jsonColumn['Type'] ?? ''), 'json') !== false;
        
        $results['checks']['json_column'] = [
            'status' => $isJson ? 'PASS' : 'FAIL',
            'message' => $isJson ? 'pending_data is JSON type' : 'pending_data is not JSON type',
            'actual_type' => $jsonColumn['Type'] ?? 'unknown'
        ];
        
        // Check 5: Count pending records
        $countStmt = $conn->query("SELECT COUNT(*) as count FROM pending_registrations");
        $count = $countStmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        $results['checks']['record_count'] = [
            'status' => 'INFO',
            'message' => "Currently {$count} pending registration(s) in table"
        ];
    }
    
    // Overall status
    $allPassed = true;
    foreach ($results['checks'] as $check) {
        if ($check['status'] === 'FAIL') {
            $allPassed = false;
            break;
        }
    }
    
    $results['overall'] = $allPassed ? 'ALL CHECKS PASSED' : 'SOME CHECKS FAILED';
    
    if (!$tableExists) {
        $results['action_needed'] = 'Run the SQL file: setup_pending_registrations.sql';
    }
    
} catch (Exception $e) {
    $results['status'] = 'error';
    $results['error'] = $e->getMessage();
}

// Pretty print JSON
echo json_encode($results, JSON_PRETTY_PRINT);
?>







