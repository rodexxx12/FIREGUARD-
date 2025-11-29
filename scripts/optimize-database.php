<?php
/**
 * Database Optimization Script
 * 
 * Analyzes and optimizes database tables
 */

require_once __DIR__ . '/../core/config/config.php';
require_once __DIR__ . '/../core/database/database.php';

echo "🗄️  Database Optimization Tool\n";
echo "==============================\n\n";

try {
    $conn = getDatabaseConnection();
    
    // Get all tables
    $stmt = $conn->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Found " . count($tables) . " tables\n\n";
    
    $optimizations = [];
    
    foreach ($tables as $table) {
        echo "Analyzing table: {$table}\n";
        
        // Check for indexes
        $stmt = $conn->query("SHOW INDEXES FROM `{$table}`");
        $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get table info
        $stmt = $conn->query("SHOW TABLE STATUS LIKE '{$table}'");
        $status = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "  Rows: " . number_format($status['Rows'] ?? 0) . "\n";
        echo "  Data Length: " . formatBytes($status['Data_length'] ?? 0) . "\n";
        echo "  Index Length: " . formatBytes($status['Index_length'] ?? 0) . "\n";
        echo "  Indexes: " . count($indexes) . "\n";
        
        // Suggest indexes for common query patterns
        $suggestions = suggestIndexes($table, $conn);
        if (!empty($suggestions)) {
            $optimizations[$table] = $suggestions;
            echo "  ⚠️  Suggested indexes:\n";
            foreach ($suggestions as $suggestion) {
                echo "     - {$suggestion}\n";
            }
        }
        
        echo "\n";
    }
    
    // Optimization recommendations
    echo str_repeat("=", 50) . "\n";
    echo "📋 Optimization Recommendations\n";
    echo str_repeat("=", 50) . "\n";
    
    if (empty($optimizations)) {
        echo "✅ No obvious optimizations needed\n";
    } else {
        echo "⚠️  Consider adding the following indexes:\n\n";
        foreach ($optimizations as $table => $suggestions) {
            echo "Table: {$table}\n";
            foreach ($suggestions as $suggestion) {
                echo "  CREATE INDEX ... ON {$table} ({$suggestion});\n";
            }
            echo "\n";
        }
    }
    
    // Analyze query performance
    echo "\n💡 Additional Recommendations:\n";
    echo "  1. Enable MySQL slow query log\n";
    echo "  2. Monitor queries taking > 1 second\n";
    echo "  3. Use EXPLAIN to analyze query plans\n";
    echo "  4. Consider partitioning large tables\n";
    echo "  5. Regular OPTIMIZE TABLE for fragmented tables\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

function suggestIndexes($table, $conn) {
    $suggestions = [];
    
    // Common patterns that benefit from indexes
    $patterns = [
        'user_id' => 'user_id',
        'device_id' => 'device_id',
        'building_id' => 'building_id',
        'status' => 'status',
        'timestamp' => 'timestamp',
        'created_at' => 'created_at',
        'updated_at' => 'updated_at'
    ];
    
    // Check if columns exist and don't have indexes
    try {
        $stmt = $conn->query("SHOW COLUMNS FROM `{$table}`");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $stmt = $conn->query("SHOW INDEXES FROM `{$table}`");
        $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $indexedColumns = array_column($indexes, 'Column_name');
        
        foreach ($patterns as $pattern => $column) {
            if (in_array($column, $columns) && !in_array($column, $indexedColumns)) {
                $suggestions[] = $column;
            }
        }
    } catch (Exception $e) {
        // Table might not exist or permission issue
    }
    
    return $suggestions;
}

function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}






