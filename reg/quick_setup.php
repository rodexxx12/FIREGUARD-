<?php
/**
 * Quick Setup Script for Pending Registrations System
 * 
 * This script will:
 * 1. Create the pending_registrations table if it doesn't exist
 * 2. Verify the table structure
 * 3. Show current status
 * 
 * Run this once after updating the registration system
 */

require_once 'db_config.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Pending Registrations</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { padding: 20px; background: #f5f5f5; }
        .setup-container { max-width: 800px; margin: 0 auto; }
        .status-pass { color: #28a745; }
        .status-fail { color: #dc3545; }
        .status-warning { color: #ffc107; }
        .status-info { color: #17a2b8; }
        .code-block { background: #f8f9fa; padding: 15px; border-radius: 5px; border-left: 4px solid #007bff; margin: 10px 0; }
    </style>
</head>
<body>
    <div class="setup-container">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h3><i class="fas fa-cog"></i> Pending Registrations Setup</h3>
            </div>
            <div class="card-body">
                <?php
                try {
                    $conn = getDatabaseConnection();
                    $results = [];
                    
                    // Step 1: Check if table exists
                    echo "<h5><i class='fas fa-check-circle'></i> Step 1: Checking Table Existence</h5>";
                    $checkTable = $conn->query("SHOW TABLES LIKE 'pending_registrations'");
                    $tableExists = $checkTable->rowCount() > 0;
                    
                    if (!$tableExists) {
                        echo "<p class='status-warning'><i class='fas fa-exclamation-triangle'></i> Table does not exist. Creating now...</p>";
                        
                        // Create the table
                        $createSQL = "CREATE TABLE IF NOT EXISTS `pending_registrations` (
                            `id` int(11) NOT NULL AUTO_INCREMENT,
                            `user_id` int(11) NOT NULL,
                            `pending_data` json NOT NULL,
                            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                            PRIMARY KEY (`id`),
                            KEY `idx_user_id` (`user_id`),
                            KEY `idx_created_at` (`created_at`),
                            CONSTRAINT `fk_pending_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
                        
                        $conn->exec($createSQL);
                        echo "<p class='status-pass'><i class='fas fa-check'></i> Table created successfully!</p>";
                        $tableExists = true;
                    } else {
                        echo "<p class='status-pass'><i class='fas fa-check'></i> Table already exists.</p>";
                    }
                    
                    // Step 2: Verify structure
                    echo "<hr><h5><i class='fas fa-check-circle'></i> Step 2: Verifying Table Structure</h5>";
                    $describe = $conn->query("DESCRIBE pending_registrations");
                    $columns = $describe->fetchAll(PDO::FETCH_ASSOC);
                    
                    echo "<div class='code-block'>";
                    echo "<strong>Table Structure:</strong><br>";
                    echo "<table class='table table-sm table-bordered mt-2'>";
                    echo "<thead><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr></thead>";
                    echo "<tbody>";
                    foreach ($columns as $col) {
                        echo "<tr>";
                        echo "<td>{$col['Field']}</td>";
                        echo "<td>{$col['Type']}</td>";
                        echo "<td>{$col['Null']}</td>";
                        echo "<td>{$col['Key']}</td>";
                        echo "<td>{$col['Default']}</td>";
                        echo "</tr>";
                    }
                    echo "</tbody></table>";
                    echo "</div>";
                    
                    $requiredColumns = ['id', 'user_id', 'pending_data', 'created_at'];
                    $columnNames = array_column($columns, 'Field');
                    $hasAllColumns = empty(array_diff($requiredColumns, $columnNames));
                    
                    if ($hasAllColumns) {
                        echo "<p class='status-pass'><i class='fas fa-check'></i> All required columns present.</p>";
                    } else {
                        echo "<p class='status-fail'><i class='fas fa-times'></i> Missing columns!</p>";
                    }
                    
                    // Step 3: Check foreign key
                    echo "<hr><h5><i class='fas fa-check-circle'></i> Step 3: Checking Foreign Key Constraint</h5>";
                    $fkQuery = $conn->query("
                        SELECT CONSTRAINT_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
                        FROM information_schema.KEY_COLUMN_USAGE 
                        WHERE TABLE_SCHEMA = DATABASE()
                        AND TABLE_NAME = 'pending_registrations' 
                        AND CONSTRAINT_NAME != 'PRIMARY' 
                        AND REFERENCED_TABLE_NAME IS NOT NULL
                    ");
                    $foreignKeys = $fkQuery->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (count($foreignKeys) > 0) {
                        echo "<p class='status-pass'><i class='fas fa-check'></i> Foreign key constraint exists:</p>";
                        echo "<div class='code-block'>";
                        foreach ($foreignKeys as $fk) {
                            echo "• {$fk['CONSTRAINT_NAME']} → {$fk['REFERENCED_TABLE_NAME']}.{$fk['REFERENCED_COLUMN_NAME']}<br>";
                        }
                        echo "</div>";
                    } else {
                        echo "<p class='status-warning'><i class='fas fa-exclamation-triangle'></i> No foreign key constraint found (may need manual setup)</p>";
                    }
                    
                    // Step 4: Current status
                    echo "<hr><h5><i class='fas fa-info-circle'></i> Step 4: Current Status</h5>";
                    $countStmt = $conn->query("SELECT COUNT(*) as count FROM pending_registrations");
                    $count = $countStmt->fetch(PDO::FETCH_ASSOC)['count'];
                    
                    echo "<div class='alert alert-info'>";
                    echo "<strong>Pending Registrations:</strong> {$count} record(s)<br>";
                    
                    $unverifiedStmt = $conn->query("SELECT COUNT(*) as count FROM users WHERE email_verified = 0");
                    $unverifiedCount = $unverifiedStmt->fetch(PDO::FETCH_ASSOC)['count'];
                    echo "<strong>Unverified Users:</strong> {$unverifiedCount} user(s)";
                    echo "</div>";
                    
                    // Final status
                    echo "<hr><div class='alert alert-success'>";
                    echo "<h5><i class='fas fa-check-circle'></i> Setup Complete!</h5>";
                    echo "<p>The pending registrations system is ready to use.</p>";
                    echo "<p><strong>Next Steps:</strong></p>";
                    echo "<ul>";
                    echo "<li>Test registration flow at <a href='registration.php'>registration.php</a></li>";
                    echo "<li>Monitor pending registrations in phpMyAdmin</li>";
                    echo "<li>Check error logs if issues occur</li>";
                    echo "</ul>";
                    echo "</div>";
                    
                } catch (Exception $e) {
                    echo "<div class='alert alert-danger'>";
                    echo "<h5><i class='fas fa-exclamation-circle'></i> Error</h5>";
                    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
                    echo "</div>";
                }
                ?>
                
                <div class="mt-4">
                    <a href="registration.php" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i> Go to Registration
                    </a>
                    <a href="test_pending_table.php" class="btn btn-info">
                        <i class="fas fa-vial"></i> Run Tests
                    </a>
                    <a href="../index.php" class="btn btn-secondary">
                        <i class="fas fa-home"></i> Home
                    </a>
                </div>
            </div>
        </div>
        
        <div class="card mt-3">
            <div class="card-header bg-secondary text-white">
                <h5><i class="fas fa-book"></i> Documentation</h5>
            </div>
            <div class="card-body">
                <p>For more information, see:</p>
                <ul>
                    <li><a href="IMPLEMENTATION_SUMMARY.md" target="_blank">Implementation Summary</a></li>
                    <li><a href="README_REGISTRATION_CHANGES.md" target="_blank">Detailed Changes</a></li>
                    <li><a href="setup_pending_registrations.sql" target="_blank">SQL Setup Script</a></li>
                </ul>
            </div>
        </div>
    </div>
</body>
</html>







