<?php
/**
 * Check reCAPTCHA Configuration
 * This script helps diagnose reCAPTCHA setup issues
 */

require_once 'db_config.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>reCAPTCHA Configuration Check</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { padding: 20px; background: #f5f5f5; }
        .check-container { max-width: 900px; margin: 0 auto; }
        .status-pass { color: #28a745; }
        .status-fail { color: #dc3545; }
        .status-warning { color: #ffc107; }
        .code-block { background: #f8f9fa; padding: 15px; border-radius: 5px; font-family: monospace; font-size: 0.9em; }
    </style>
</head>
<body>
    <div class="check-container">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h3><i class="fas fa-shield-alt"></i> reCAPTCHA Configuration Check</h3>
            </div>
            <div class="card-body">
                <?php
                $checks = [];
                
                // Check 1: Config file exists
                echo "<h5><i class='fas fa-check-circle'></i> Step 1: Checking Configuration Files</h5>";
                $recaptcha_config_file = dirname(__DIR__) . '/login/functions/recaptcha_config.php';
                $recaptcha_env_file = dirname(__DIR__) . '/login/functions/env.php';
                
                if (file_exists($recaptcha_config_file)) {
                    echo "<p class='status-pass'><i class='fas fa-check'></i> recaptcha_config.php exists</p>";
                    $checks['config_file'] = true;
                } else {
                    echo "<p class='status-fail'><i class='fas fa-times'></i> recaptcha_config.php NOT found</p>";
                    $checks['config_file'] = false;
                }
                
                if (file_exists($recaptcha_env_file)) {
                    echo "<p class='status-pass'><i class='fas fa-check'></i> env.php exists</p>";
                    $checks['env_file'] = true;
                } else {
                    echo "<p class='status-fail'><i class='fas fa-times'></i> env.php NOT found</p>";
                    $checks['env_file'] = false;
                }
                
                // Check 2: Load configuration
                echo "<hr><h5><i class='fas fa-check-circle'></i> Step 2: Loading reCAPTCHA Keys</h5>";
                
                if ($checks['config_file']) {
                    $config = require $recaptcha_config_file;
                    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                    $site_key = $config['domains'][$host]['site_key'] ?? $config['default']['site_key'] ?? '';
                    $secret_key = $config['domains'][$host]['secret_key'] ?? $config['default']['secret_key'] ?? '';
                    
                    echo "<div class='code-block'>";
                    echo "<strong>Host:</strong> " . htmlspecialchars($host) . "<br>";
                    echo "<strong>Site Key:</strong> " . (empty($site_key) ? '<span class="status-fail">NOT CONFIGURED</span>' : '<span class="status-pass">' . substr($site_key, 0, 20) . '...</span>') . "<br>";
                    echo "<strong>Secret Key:</strong> " . (empty($secret_key) ? '<span class="status-fail">NOT CONFIGURED</span>' : '<span class="status-pass">Configured (hidden)</span>');
                    echo "</div>";
                    
                    if (!empty($site_key) && !empty($secret_key)) {
                        echo "<p class='status-pass mt-2'><i class='fas fa-check'></i> reCAPTCHA keys are configured</p>";
                        $checks['keys_configured'] = true;
                    } else {
                        echo "<p class='status-fail mt-2'><i class='fas fa-times'></i> reCAPTCHA keys are NOT configured</p>";
                        $checks['keys_configured'] = false;
                    }
                } else {
                    echo "<p class='status-fail'><i class='fas fa-times'></i> Cannot load configuration</p>";
                    $checks['keys_configured'] = false;
                }
                
                // Check 3: .env file
                echo "<hr><h5><i class='fas fa-check-circle'></i> Step 3: Checking .env File</h5>";
                $env_path = dirname(__DIR__) . '/.env';
                
                if (file_exists($env_path)) {
                    echo "<p class='status-pass'><i class='fas fa-check'></i> .env file exists</p>";
                    
                    $env_contents = file_get_contents($env_path);
                    $has_site_key = strpos($env_contents, 'RECAPTCHA_SITE_KEY') !== false;
                    $has_secret_key = strpos($env_contents, 'RECAPTCHA_SECRET_KEY') !== false;
                    
                    echo "<div class='code-block mt-2'>";
                    echo "<strong>RECAPTCHA_SITE_KEY:</strong> " . ($has_site_key ? '<span class="status-pass">Present</span>' : '<span class="status-fail">Missing</span>') . "<br>";
                    echo "<strong>RECAPTCHA_SECRET_KEY:</strong> " . ($has_secret_key ? '<span class="status-pass">Present</span>' : '<span class="status-fail">Missing</span>');
                    echo "</div>";
                    
                    $checks['env_configured'] = $has_site_key && $has_secret_key;
                } else {
                    echo "<p class='status-fail'><i class='fas fa-times'></i> .env file NOT found at: " . htmlspecialchars($env_path) . "</p>";
                    $checks['env_configured'] = false;
                }
                
                // Check 4: JavaScript loading
                echo "<hr><h5><i class='fas fa-check-circle'></i> Step 4: Testing reCAPTCHA Script Loading</h5>";
                echo "<div id='recaptcha-test-result'></div>";
                
                if ($checks['keys_configured']) {
                    echo "<p class='status-pass'><i class='fas fa-check'></i> Will attempt to load reCAPTCHA script...</p>";
                } else {
                    echo "<p class='status-fail'><i class='fas fa-times'></i> Cannot test - keys not configured</p>";
                }
                
                // Summary
                echo "<hr><div class='alert " . (array_sum($checks) === count($checks) ? "alert-success" : "alert-warning") . "'>";
                echo "<h5><strong>Summary</strong></h5>";
                echo "<ul>";
                foreach ($checks as $check => $status) {
                    $icon = $status ? '<i class="fas fa-check status-pass"></i>' : '<i class="fas fa-times status-fail"></i>';
                    echo "<li>{$icon} " . ucwords(str_replace('_', ' ', $check)) . "</li>";
                }
                echo "</ul>";
                echo "</div>";
                
                // Instructions
                if (!$checks['keys_configured'] || !$checks['env_configured']) {
                    echo "<div class='alert alert-info'>";
                    echo "<h5><i class='fas fa-info-circle'></i> How to Fix</h5>";
                    echo "<ol>";
                    echo "<li><strong>Get reCAPTCHA keys from Google:</strong><br>";
                    echo "Visit <a href='https://www.google.com/recaptcha/admin' target='_blank'>https://www.google.com/recaptcha/admin</a><br>";
                    echo "Create a new site with reCAPTCHA v3</li>";
                    
                    echo "<li><strong>Add keys to .env file:</strong><br>";
                    echo "Open: <code>" . htmlspecialchars($env_path) . "</code><br>";
                    echo "Add these lines:<br>";
                    echo "<div class='code-block mt-2'>";
                    echo "RECAPTCHA_SITE_KEY=your_site_key_here<br>";
                    echo "RECAPTCHA_SECRET_KEY=your_secret_key_here";
                    echo "</div></li>";
                    
                    echo "<li><strong>Refresh this page</strong> to verify the configuration</li>";
                    echo "</ol>";
                    echo "</div>";
                }
                ?>
                
                <div class="mt-4">
                    <a href="registration.php" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i> Go to Registration
                    </a>
                    <button onclick="location.reload()" class="btn btn-info">
                        <i class="fas fa-sync"></i> Refresh Check
                    </button>
                    <a href="SECURITY_VERIFICATION_GUIDE.md" target="_blank" class="btn btn-secondary">
                        <i class="fas fa-book"></i> User Guide
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <?php if ($checks['keys_configured']): ?>
    <script src="https://www.google.com/recaptcha/api.js?render=<?php echo htmlspecialchars($site_key); ?>"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const resultDiv = document.getElementById('recaptcha-test-result');
            
            if (typeof grecaptcha === 'undefined') {
                resultDiv.innerHTML = '<p class="status-fail"><i class="fas fa-times"></i> reCAPTCHA script failed to load</p>';
            } else {
                resultDiv.innerHTML = '<p class="status-pass"><i class="fas fa-check"></i> reCAPTCHA script loaded successfully</p>';
                
                grecaptcha.ready(function() {
                    resultDiv.innerHTML += '<p class="status-pass"><i class="fas fa-check"></i> reCAPTCHA is ready</p>';
                    
                    // Test execution
                    grecaptcha.execute('<?php echo htmlspecialchars($site_key); ?>', {action: 'test'})
                        .then(function(token) {
                            resultDiv.innerHTML += '<p class="status-pass"><i class="fas fa-check"></i> reCAPTCHA execution successful<br><small>Token: ' + token.substring(0, 30) + '...</small></p>';
                        })
                        .catch(function(error) {
                            resultDiv.innerHTML += '<p class="status-fail"><i class="fas fa-times"></i> reCAPTCHA execution failed: ' + error + '</p>';
                        });
                });
            }
        });
    </script>
    <?php endif; ?>
</body>
</html>

