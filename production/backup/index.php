<?php
session_start();
// Check if user is logged in (either officer or admin)
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['officer_id'])) {
    header('Location: ../../login/index.php');
    exit;
}

// Get existing backups
function getBackups($type) {
    $backup_dir = __DIR__ . '/backups/' . $type;
    $backups = array();
    
    if (is_dir($backup_dir)) {
        $files = scandir($backup_dir);
        foreach ($files as $file) {
            if ($file != '.' && $file != '..' && pathinfo($file, PATHINFO_EXTENSION) == 'sql') {
                $file_path = $backup_dir . '/' . $file;
                $backups[] = array(
                    'name' => $file,
                    'size' => filesize($file_path),
                    'size_mb' => round(filesize($file_path) / 1024 / 1024, 2),
                    'modified' => date('Y-m-d H:i:s', filemtime($file_path))
                );
            }
        }
        
        // Sort by modification time (newest first)
        usort($backups, function($a, $b) {
            return strtotime($b['modified']) - strtotime($a['modified']);
        });
    }
    
    return $backups;
}

$manual_backups = getBackups('manual');
$weekly_backups = getBackups('weekly');
$monthly_backups = getBackups('monthly');
$yearly_backups = getBackups('yearly');
$all_backups = getBackups('all');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Backup System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 2em;
            margin-bottom: 10px;
        }
        
        .header p {
            opacity: 0.9;
        }
        
        .content {
            padding: 30px;
        }
        
        .backup-controls {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .backup-btn {
            padding: 15px 25px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .backup-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
        }
        
        .backup-btn.manual {
            background: #4CAF50;
            color: white;
        }
        
        .backup-btn.weekly {
            background: #2196F3;
            color: white;
        }
        
        .backup-btn.monthly {
            background: #FF9800;
            color: white;
        }
        
        .backup-btn.yearly {
            background: #9C27B0;
            color: white;
        }
        
        .backup-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }
        
        .section {
            margin-bottom: 30px;
        }
        
        .section h2 {
            color: #333;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }
        
        .backup-list {
            background: #f5f5f5;
            border-radius: 8px;
            padding: 15px;
        }
        
        .backup-item {
            background: white;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 6px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .backup-info {
            flex: 1;
        }
        
        .backup-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        
        .backup-meta {
            font-size: 0.9em;
            color: #666;
        }
        
        .backup-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 8px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s;
        }
        
        .btn-download {
            background: #4CAF50;
            color: white;
        }
        
        .btn-download:hover {
            background: #45a049;
        }
        
        .btn-delete {
            background: #f44336;
            color: white;
        }
        
        .btn-delete:hover {
            background: #da190b;
        }
        
        .btn-import {
            background: #9C27B0;
            color: white;
        }
        
        .btn-import:hover {
            background: #7B1FA2;
        }
        
        .status-message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 6px;
            display: none;
        }
        
        .status-message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .status-message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .loading {
            display: none;
            text-align: center;
            padding: 20px;
        }
        
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .empty {
            text-align: center;
            padding: 40px;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🗄️ Database Backup System</h1>
            <p>Complete backup including <strong>table structures</strong> and data (Tables, Views, Triggers, Stored Procedures, Functions, Events)</p>
        </div>
        
        <div class="content">
            <div id="status-message" class="status-message"></div>
            
            <div class="section">
                <h2>Create New Backup</h2>
                <div class="backup-controls">
                    <button class="backup-btn manual" onclick="createBackup('all')">
                        💾 Backup All
                    </button>
                </div>
            </div>
            
            <div class="loading" id="loading">
                <div class="spinner"></div>
                <p style="margin-top: 10px;">Creating backup... This may take a few moments.</p>
            </div>
            
            <div class="section">
                <h2>Import Database Backup</h2>
                <div class="backup-list">
                    <div style="padding: 20px;">
                        <form id="import-form" enctype="multipart/form-data">
                            <div style="margin-bottom: 15px;">
                                <label for="backup_file" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                                    Select SQL Backup File:
                                </label>
                                <input type="file" id="backup_file" name="backup_file" accept=".sql,.txt" required 
                                       style="width: 100%; padding: 10px; border: 2px dashed #667eea; border-radius: 8px; background: #f8f9fa; cursor: pointer;">
                                <p style="margin-top: 5px; font-size: 0.9em; color: #666;">
                                    Maximum file size: 500MB. Supported formats: .sql, .txt
                                </p>
                            </div>
                            <button type="submit" class="backup-btn manual" style="width: 100%;">
                                📥 Import Database
                            </button>
                        </form>
                    </div>
                </div>
                <div class="loading" id="import-loading" style="display: none;">
                    <div class="spinner"></div>
                    <p style="margin-top: 10px;">Importing database... This may take a few moments.</p>
                </div>
            </div>
            
            <?php if (count($all_backups) > 0): ?>
            <div class="section">
                <h2>All Backups</h2>
                <div class="backup-list">
                    <?php foreach ($all_backups as $backup): ?>
                    <div class="backup-item">
                        <div class="backup-info">
                            <div class="backup-name"><?php echo htmlspecialchars($backup['name']); ?></div>
                            <div class="backup-meta">
                                <?php echo $backup['size_mb']; ?> MB • <?php echo $backup['modified']; ?>
                            </div>
                        </div>
                        <div class="backup-actions">
                            <button class="btn btn-import" onclick="importExistingBackup('<?php echo htmlspecialchars($backup['name']); ?>', 'all')" style="background: #9C27B0; color: white;">
                                Import
                            </button>
                            <button class="btn btn-download" onclick="downloadBackup('<?php echo htmlspecialchars($backup['name']); ?>', 'all')">
                                Download
                            </button>
                            <button class="btn btn-delete" onclick="deleteBackup('<?php echo htmlspecialchars($backup['name']); ?>', 'all')">
                                Delete
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (count($manual_backups) > 0): ?>
            <div class="section">
                <h2>Manual Backups</h2>
                <div class="backup-list">
                    <?php foreach ($manual_backups as $backup): ?>
                    <div class="backup-item">
                        <div class="backup-info">
                            <div class="backup-name"><?php echo htmlspecialchars($backup['name']); ?></div>
                            <div class="backup-meta">
                                <?php echo $backup['size_mb']; ?> MB • <?php echo $backup['modified']; ?>
                            </div>
                        </div>
                        <div class="backup-actions">
                            <button class="btn btn-import" onclick="importExistingBackup('<?php echo htmlspecialchars($backup['name']); ?>', 'manual')" style="background: #9C27B0; color: white;">
                                Import
                            </button>
                            <button class="btn btn-download" onclick="downloadBackup('<?php echo htmlspecialchars($backup['name']); ?>', 'manual')">
                                Download
                            </button>
                            <button class="btn btn-delete" onclick="deleteBackup('<?php echo htmlspecialchars($backup['name']); ?>', 'manual')">
                                Delete
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (count($weekly_backups) > 0): ?>
            <div class="section">
                <h2>Weekly Backups</h2>
                <div class="backup-list">
                    <?php foreach ($weekly_backups as $backup): ?>
                    <div class="backup-item">
                        <div class="backup-info">
                            <div class="backup-name"><?php echo htmlspecialchars($backup['name']); ?></div>
                            <div class="backup-meta">
                                <?php echo $backup['size_mb']; ?> MB • <?php echo $backup['modified']; ?>
                            </div>
                        </div>
                        <div class="backup-actions">
                            <button class="btn btn-import" onclick="importExistingBackup('<?php echo htmlspecialchars($backup['name']); ?>', 'weekly')" style="background: #9C27B0; color: white;">
                                Import
                            </button>
                            <button class="btn btn-download" onclick="downloadBackup('<?php echo htmlspecialchars($backup['name']); ?>', 'weekly')">
                                Download
                            </button>
                            <button class="btn btn-delete" onclick="deleteBackup('<?php echo htmlspecialchars($backup['name']); ?>', 'weekly')">
                                Delete
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (count($monthly_backups) > 0): ?>
            <div class="section">
                <h2>Monthly Backups</h2>
                <div class="backup-list">
                    <?php foreach ($monthly_backups as $backup): ?>
                    <div class="backup-item">
                        <div class="backup-info">
                            <div class="backup-name"><?php echo htmlspecialchars($backup['name']); ?></div>
                            <div class="backup-meta">
                                <?php echo $backup['size_mb']; ?> MB • <?php echo $backup['modified']; ?>
                            </div>
                        </div>
                        <div class="backup-actions">
                            <button class="btn btn-import" onclick="importExistingBackup('<?php echo htmlspecialchars($backup['name']); ?>', 'monthly')" style="background: #9C27B0; color: white;">
                                Import
                            </button>
                            <button class="btn btn-download" onclick="downloadBackup('<?php echo htmlspecialchars($backup['name']); ?>', 'monthly')">
                                Download
                            </button>
                            <button class="btn btn-delete" onclick="deleteBackup('<?php echo htmlspecialchars($backup['name']); ?>', 'monthly')">
                                Delete
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (count($yearly_backups) > 0): ?>
            <div class="section">
                <h2>Yearly Backups</h2>
                <div class="backup-list">
                    <?php foreach ($yearly_backups as $backup): ?>
                    <div class="backup-item">
                        <div class="backup-info">
                            <div class="backup-name"><?php echo htmlspecialchars($backup['name']); ?></div>
                            <div class="backup-meta">
                                <?php echo $backup['size_mb']; ?> MB • <?php echo $backup['modified']; ?>
                            </div>
                        </div>
                        <div class="backup-actions">
                            <button class="btn btn-import" onclick="importExistingBackup('<?php echo htmlspecialchars($backup['name']); ?>', 'yearly')" style="background: #9C27B0; color: white;">
                                Import
                            </button>
                            <button class="btn btn-download" onclick="downloadBackup('<?php echo htmlspecialchars($backup['name']); ?>', 'yearly')">
                                Download
                            </button>
                            <button class="btn btn-delete" onclick="deleteBackup('<?php echo htmlspecialchars($backup['name']); ?>', 'yearly')">
                                Delete
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <div style="text-align: center; margin-top: 30px;">
                <a href="../main.php" style="color: #667eea; text-decoration: none;">← Back to Dashboard</a>
            </div>
        </div>
    </div>
    
    <script>
        function createBackup(type) {
            // Disable buttons
            const buttons = document.querySelectorAll('.backup-btn');
            buttons.forEach(btn => btn.disabled = true);
            
            // Show loading
            document.getElementById('loading').style.display = 'block';
            document.getElementById('status-message').style.display = 'none';
            
            const formData = new FormData();
            formData.append('backup_type', type);
            
            fetch('create_backup.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('loading').style.display = 'none';
                
                const statusDiv = document.getElementById('status-message');
                if (data.success) {
                    statusDiv.className = 'status-message success';
                    statusDiv.innerHTML = '<strong>✓ Success!</strong> ' + data.message + ' (Size: ' + data.file_size_mb + ' MB)';
                    statusDiv.style.display = 'block';
                    
                    // Reload page after 2 seconds
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                } else {
                    statusDiv.className = 'status-message error';
                    statusDiv.innerHTML = '<strong>✗ Error:</strong> ' + data.message;
                    statusDiv.style.display = 'block';
                }
                
                // Re-enable buttons
                buttons.forEach(btn => btn.disabled = false);
            })
            .catch(error => {
                document.getElementById('loading').style.display = 'none';
                const statusDiv = document.getElementById('status-message');
                statusDiv.className = 'status-message error';
                statusDiv.innerHTML = '<strong>✗ Error:</strong> ' + error.message;
                statusDiv.style.display = 'block';
                
                // Re-enable buttons
                buttons.forEach(btn => btn.disabled = false);
            });
        }
        
        function downloadBackup(filename, type) {
            window.location.href = 'download_backup.php?file=' + encodeURIComponent(filename) + '&type=' + encodeURIComponent(type);
        }
        
        function deleteBackup(filename, type) {
            if (!confirm('Are you sure you want to delete this backup?')) {
                return;
            }
            
            // You can implement delete functionality here if needed
            alert('Delete functionality can be added here');
        }
        
        function importExistingBackup(filename, type) {
            if (!confirm('Are you sure you want to import this database backup? This will modify your database.')) {
                return;
            }
            
            // Show loading
            document.getElementById('import-loading').style.display = 'block';
            document.getElementById('status-message').style.display = 'none';
            
            fetch('import_existing_backup.php?file=' + encodeURIComponent(filename) + '&type=' + encodeURIComponent(type))
            .then(response => response.json())
            .then(data => {
                document.getElementById('import-loading').style.display = 'none';
                
                const statusDiv = document.getElementById('status-message');
                if (data.success) {
                    statusDiv.className = 'status-message success';
                    let message = '<strong>✓ Success!</strong> ' + data.message;
                    if (data.errors && data.errors.length > 0) {
                        message += '<br><small>Some queries failed: ' + data.errors.length + ' errors</small>';
                    }
                    statusDiv.innerHTML = message;
                    statusDiv.style.display = 'block';
                } else {
                    statusDiv.className = 'status-message error';
                    statusDiv.innerHTML = '<strong>✗ Error:</strong> ' + data.message;
                    statusDiv.style.display = 'block';
                }
            })
            .catch(error => {
                document.getElementById('import-loading').style.display = 'none';
                const statusDiv = document.getElementById('status-message');
                statusDiv.className = 'status-message error';
                statusDiv.innerHTML = '<strong>✗ Error:</strong> ' + error.message;
                statusDiv.style.display = 'block';
            });
        }
        
        // Handle import form submission
        document.getElementById('import-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const fileInput = document.getElementById('backup_file');
            
            if (!fileInput.files || !fileInput.files[0]) {
                alert('Please select a file to import');
                return;
            }
            
            // Validate file size (500MB)
            const maxSize = 500 * 1024 * 1024;
            if (fileInput.files[0].size > maxSize) {
                alert('File size exceeds maximum limit of 500MB');
                return;
            }
            
            // Confirm import
            if (!confirm('Are you sure you want to import this database file? This will modify your database.')) {
                return;
            }
            
            // Show loading
            document.getElementById('import-loading').style.display = 'block';
            document.getElementById('status-message').style.display = 'none';
            
            fetch('import_backup.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('import-loading').style.display = 'none';
                
                const statusDiv = document.getElementById('status-message');
                if (data.success) {
                    statusDiv.className = 'status-message success';
                    let message = '<strong>✓ Success!</strong> ' + data.message;
                    if (data.errors && data.errors.length > 0) {
                        message += '<br><small>Some queries failed: ' + data.errors.length + ' errors</small>';
                    }
                    statusDiv.innerHTML = message;
                    statusDiv.style.display = 'block';
                    
                    // Reset form
                    document.getElementById('import-form').reset();
                } else {
                    statusDiv.className = 'status-message error';
                    statusDiv.innerHTML = '<strong>✗ Error:</strong> ' + data.message;
                    statusDiv.style.display = 'block';
                }
            })
            .catch(error => {
                document.getElementById('import-loading').style.display = 'none';
                const statusDiv = document.getElementById('status-message');
                statusDiv.className = 'status-message error';
                statusDiv.innerHTML = '<strong>✗ Error:</strong> ' + error.message;
                statusDiv.style.display = 'block';
            });
        });
    </script>
</body>
</html>

