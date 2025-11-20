<?php
/**
 * Remote Database Backup Script
 * 
 * This script backs up a remote MySQL database using mysqldump or PHP fallback.
 * 
 * Usage: php backup_remote_db.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Remote database credentials
$db_host = 'srv1322.hstgr.io';
$db_name = 'u520834156_DBBagofire';
$db_user = 'u520834156_userBagofire';
$db_pass = 'i[#[GQ!+=C9';

echo "Starting remote database backup...\n";
echo "Database: {$db_name}\n";
echo "Host: {$db_host}\n";
echo "\n";

// Create backup directory
$backup_dir = __DIR__ . '/backups/remote';
if (!is_dir($backup_dir)) {
    if (!mkdir($backup_dir, 0755, true)) {
        die("ERROR: Could not create backup directory: {$backup_dir}\n");
    }
    echo "Created backup directory: {$backup_dir}\n";
}

// Generate filename
$timestamp = date('Y-m-d_H-i-s');
$filename = "{$db_name}_{$timestamp}.sql";
$backup_path = $backup_dir . '/' . $filename;

echo "Backup file: {$filename}\n";
echo "\n";

// Try mysqldump first
$mysqldump_paths = [
    'C:\\xampp\\mysql\\bin\\mysqldump.exe',
    'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysqldump.exe',
    'C:\\Program Files\\MySQL\\MySQL Server 5.7\\bin\\mysqldump.exe',
    'mysqldump'
];

$mysqldump_cmd = null;
foreach ($mysqldump_paths as $path) {
    if (file_exists($path)) {
        $mysqldump_cmd = $path;
        echo "Found mysqldump at: {$path}\n";
        break;
    }
}

$success = false;

// Try mysqldump
if ($mysqldump_cmd) {
    echo "Attempting backup using mysqldump...\n";
    
    $command = sprintf(
        '"%s" --host=%s --user=%s --password=%s --single-transaction --routines --triggers --events --complete-insert --default-character-set=utf8mb4 --add-drop-database --add-locks --extended-insert --quick --lock-tables=false %s > "%s" 2>&1',
        $mysqldump_cmd,
        escapeshellarg($db_host),
        escapeshellarg($db_user),
        escapeshellarg($db_pass),
        escapeshellarg($db_name),
        escapeshellarg($backup_path)
    );
    
    exec($command, $output, $return_var);
    
    if ($return_var === 0 && file_exists($backup_path) && filesize($backup_path) > 0) {
        $success = true;
        echo "mysqldump backup successful!\n";
    } else {
        echo "mysqldump failed or incomplete:\n";
        echo implode("\n", $output);
        echo "\n";
    }
}

// Fallback to PHP-based backup
if (!$success) {
    echo "Falling back to PHP-based backup...\n";
    
    try {
        // Connect to remote database
        $dsn = "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4";
        $pdo = new PDO($dsn, $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        
        echo "Connected to remote database successfully.\n";
        
        $backup_content = "-- Database Backup\n";
        $backup_content .= "-- Created: " . date('Y-m-d H:i:s') . "\n";
        $backup_content .= "-- Database: {$db_name}\n";
        $backup_content .= "-- Host: {$db_host}\n\n";
        $backup_content .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
        $backup_content .= "SET time_zone = \"+00:00\";\n\n";
        $backup_content .= "-- --------------------------------------------------------\n";
        $backup_content .= "-- Create database if not exists\n";
        $backup_content .= "-- --------------------------------------------------------\n\n";
        $backup_content .= "CREATE DATABASE IF NOT EXISTS `{$db_name}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;\n";
        $backup_content .= "USE `{$db_name}`;\n\n";
        
        // Get all tables
        echo "Getting list of tables...\n";
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo "Found " . count($tables) . " tables.\n\n";
        
        if (count($tables) == 0) {
            throw new Exception('No tables found in database');
        }
        
        foreach ($tables as $index => $table) {
            echo "Processing table " . ($index + 1) . "/" . count($tables) . ": {$table}...";
            
            $backup_content .= "\n-- --------------------------------------------------------\n";
            $backup_content .= "-- Table structure for table `{$table}`\n";
            $backup_content .= "-- --------------------------------------------------------\n\n";
            $backup_content .= "DROP TABLE IF EXISTS `{$table}`;\n";
            
            // Get table structure
            $stmt = $pdo->query("SHOW CREATE TABLE `{$table}`");
            $create = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Handle both possible column names returned by MySQL
            $create_statement = null;
            if (isset($create['Create Table'])) {
                $create_statement = $create['Create Table'];
            } elseif (isset($create['create table'])) {
                $create_statement = $create['create table'];
            } elseif (isset($create['CREATE TABLE'])) {
                $create_statement = $create['CREATE TABLE'];
            }
            
            if ($create_statement) {
                $backup_content .= $create_statement . ";\n\n";
            } else {
                // Fallback: manually reconstruct table structure if SHOW CREATE TABLE fails
                echo " (could not get structure)\n";
                $backup_content .= "-- Note: Could not get CREATE TABLE statement for `{$table}`\n";
            }
            
            // Get ALL columns from table structure
            $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}`");
            $columns_info = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $all_cols = array_column($columns_info, 'Field');
            
            // Count total rows for verification
            $stmt_count = $pdo->query("SELECT COUNT(*) as total FROM `{$table}`");
            $count_result = $stmt_count->fetch(PDO::FETCH_ASSOC);
            $total_in_table = $count_result['total'] ?? 0;
            
            if ($total_in_table > 0) {
                echo " ({$total_in_table} rows)\n";
                $backup_content .= "-- Dumping data for table `{$table}`\n";
                $backup_content .= "-- Total rows in table: {$total_in_table}\n\n";
                
                // Fetch ALL data in batches to avoid memory issues
                $batch_size = 500;
                $offset = 0;
                $total_fetched = 0;
                
                while ($offset < $total_in_table) {
                    $stmt = $pdo->query("SELECT * FROM `{$table}` LIMIT {$batch_size} OFFSET {$offset}");
                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (count($rows) > 0) {
                        // Get column names from the actual data
                        $data_cols = array_keys($rows[0]);
                        
                        // Ensure we have all columns (some might be NULL)
                        foreach ($all_cols as $col) {
                            if (!in_array($col, $data_cols)) {
                                $data_cols[] = $col;
                            }
                        }
                        
                        $backup_content .= "INSERT INTO `{$table}` (";
                        $backup_content .= "`" . implode('`, `', $data_cols) . "`";
                        $backup_content .= ") VALUES\n";
                        
                        $values = array();
                        foreach ($rows as $row) {
                            $vals = array();
                            foreach ($data_cols as $col) {
                                $val = $row[$col] ?? null;
                                if ($val === null) {
                                    $vals[] = "NULL";
                                } elseif (is_bool($val)) {
                                    $vals[] = $val ? '1' : '0';
                                } else {
                                    // Properly escape and quote values, preserving exact data
                                    $vals[] = $pdo->quote($val);
                                }
                            }
                            $values[] = "(" . implode(', ', $vals) . ")";
                        }
                        
                        $backup_content .= implode(",\n", $values) . ";\n\n";
                        
                        $total_fetched += count($rows);
                    }
                    
                    $offset += $batch_size;
                }
                
                // Verify all data was backed up
                $backup_content .= "-- Verification: Expected {$total_in_table} rows, Backed up {$total_fetched} rows\n\n";
                if ($total_fetched != $total_in_table) {
                    $backup_content .= "-- WARNING: Row count mismatch! Expected: {$total_in_table}, Backed up: {$total_fetched}\n\n";
                }
            } else {
                echo " (empty)\n";
                $backup_content .= "-- No data in table `{$table}`\n\n";
            }
        }
        
        // Backup views
        try {
            $stmt = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'VIEW'");
            $views = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (count($views) > 0) {
                echo "\nBacking up " . count($views) . " views...\n";
            }
            
            foreach ($views as $view_name) {
                $backup_content .= "\n-- --------------------------------------------------------\n";
                $backup_content .= "-- View structure for view `{$view_name}`\n";
                $backup_content .= "-- --------------------------------------------------------\n\n";
                
                $stmt = $pdo->query("SHOW CREATE VIEW `{$view_name}`");
                $create = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $create_view = null;
                if (isset($create['Create View'])) {
                    $create_view = $create['Create View'];
                } elseif (isset($create['create view'])) {
                    $create_view = $create['create view'];
                }
                
                if ($create_view) {
                    $backup_content .= "DROP VIEW IF EXISTS `{$view_name}`;\n";
                    $backup_content .= $create_view . ";\n\n";
                }
            }
        } catch (Exception $e) {
            // Skip if views not supported
        }
        
        // Backup triggers
        try {
            $stmt = $pdo->query("SHOW TRIGGERS");
            $triggers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($triggers) > 0) {
                echo "Backing up " . count($triggers) . " triggers...\n";
            }
            
            foreach ($triggers as $trigger) {
                $trigger_name = $trigger['Trigger'];
                
                $backup_content .= "\n-- --------------------------------------------------------\n";
                $backup_content .= "-- Trigger structure for trigger `{$trigger_name}`\n";
                $backup_content .= "-- --------------------------------------------------------\n\n";
                
                $stmt = $pdo->query("SHOW CREATE TRIGGER `{$trigger_name}`");
                $create = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $create_trigger = null;
                if (isset($create['SQL Original Statement'])) {
                    $create_trigger = $create['SQL Original Statement'];
                }
                
                if ($create_trigger) {
                    $backup_content .= "DROP TRIGGER IF EXISTS `{$trigger_name}`;\n";
                    $backup_content .= "DELIMITER ;;\n";
                    $backup_content .= $create_trigger . ";;\n";
                    $backup_content .= "DELIMITER ;\n\n";
                }
            }
        } catch (Exception $e) {
            // Skip if triggers not supported
        }
        
        // Backup stored procedures
        try {
            $stmt = $pdo->query("SHOW PROCEDURE STATUS WHERE Db = DATABASE()");
            $procedures = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($procedures) > 0) {
                echo "\nBacking up " . count($procedures) . " stored procedures...\n";
            }
            
            foreach ($procedures as $proc) {
                $proc_name = $proc['Name'];
                $backup_content .= "\n-- --------------------------------------------------------\n";
                $backup_content .= "-- Dumping routines for procedure `{$proc_name}`\n";
                $backup_content .= "-- --------------------------------------------------------\n\n";
                
                $stmt = $pdo->query("SHOW CREATE PROCEDURE `{$proc_name}`");
                $create = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $create_proc = null;
                if (isset($create['Create Procedure'])) {
                    $create_proc = $create['Create Procedure'];
                } elseif (isset($create['Procedure'])) {
                    $create_proc = $create['Procedure'];
                }
                
                if ($create_proc) {
                    $backup_content .= "DROP PROCEDURE IF EXISTS `{$proc_name}`;\n";
                    $backup_content .= "DELIMITER ;;\n";
                    $backup_content .= $create_proc . ";;\n";
                    $backup_content .= "DELIMITER ;\n\n";
                }
            }
        } catch (Exception $e) {
            // Skip if procedures not supported
        }
        
        // Backup stored functions
        try {
            $stmt = $pdo->query("SHOW FUNCTION STATUS WHERE Db = DATABASE()");
            $functions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($functions) > 0) {
                echo "Backing up " . count($functions) . " stored functions...\n";
            }
            
            foreach ($functions as $func) {
                $func_name = $func['Name'];
                $backup_content .= "\n-- --------------------------------------------------------\n";
                $backup_content .= "-- Dumping routines for function `{$func_name}`\n";
                $backup_content .= "-- --------------------------------------------------------\n\n";
                
                $stmt = $pdo->query("SHOW CREATE FUNCTION `{$func_name}`");
                $create = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $create_func = null;
                if (isset($create['Create Function'])) {
                    $create_func = $create['Create Function'];
                } elseif (isset($create['Function'])) {
                    $create_func = $create['Function'];
                }
                
                if ($create_func) {
                    $backup_content .= "DROP FUNCTION IF EXISTS `{$func_name}`;\n";
                    $backup_content .= "DELIMITER ;;\n";
                    $backup_content .= $create_func . ";;\n";
                    $backup_content .= "DELIMITER ;\n\n";
                }
            }
        } catch (Exception $e) {
            // Skip if functions not supported
        }
        
        if (file_put_contents($backup_path, $backup_content) === false) {
            throw new Exception('Could not write backup file');
        }
        
        $success = true;
        echo "\nPHP-based backup successful!\n";
        
    } catch (PDOException $e) {
        die("Database error: " . $e->getMessage() . "\n");
    } catch (Exception $e) {
        die("Error: " . $e->getMessage() . "\n");
    }
}

if (!$success || !file_exists($backup_path) || filesize($backup_path) == 0) {
    die("ERROR: Failed to create backup file.\n");
}

$file_size = filesize($backup_path);
$file_size_mb = round($file_size / 1024 / 1024, 2);

echo "\n";
echo "========================================\n";
echo "✓ BACKUP COMPLETED SUCCESSFULLY!\n";
echo "========================================\n";
echo "File: {$filename}\n";
echo "Size: {$file_size_mb} MB (" . number_format($file_size) . " bytes)\n";
echo "Path: {$backup_path}\n";
echo "========================================\n";

