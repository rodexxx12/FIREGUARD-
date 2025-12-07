<?php
/**
 * SMS API Diagnostic Tool
 * This script helps diagnose SMS API authentication issues
 */

// Load config - it returns an array
$config = require __DIR__ . '/config/config.php';

// Initialize variables with defaults to prevent undefined variable warnings
$apiKey = $config['api_key'] ?? '';
$deviceId = $config['device'] ?? '';
$apiUrl = $config['url'] ?? 'https://sms.pagenet.info/api/v1/sms/send';
$isConfigured = $config['is_configured'] ?? false;
$configErrors = $config['errors'] ?? [];

echo "<!DOCTYPE html>
<html>
<head>
    <title>SMS API Diagnostic Tool</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #007bff; padding-bottom: 10px; }
        .section { margin: 20px 0; padding: 15px; background: #f9f9f9; border-left: 4px solid #007bff; }
        .error { background: #fff3cd; border-left-color: #ffc107; }
        .success { background: #d4edda; border-left-color: #28a745; }
        .warning { background: #fff3cd; border-left-color: #ffc107; }
        .info { background: #d1ecf1; border-left-color: #17a2b8; }
        pre { background: #2d2d2d; color: #f8f8f2; padding: 15px; border-radius: 4px; overflow-x: auto; }
        .key-display { font-family: monospace; background: #e9ecef; padding: 5px 10px; border-radius: 4px; margin: 5px 0; }
        .match { color: #28a745; font-weight: bold; }
        .mismatch { color: #dc3545; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #007bff; color: white; }
        .status-ok { color: #28a745; }
        .status-error { color: #dc3545; }
        .status-warning { color: #ffc107; }
    </style>
</head>
<body>
<div class='container'>
    <h1>🔍 SMS API Diagnostic Tool</h1>";

// Check device/config.php for comparison
$deviceConfigPath = __DIR__ . '/../../../device/config.php';
$deviceConfig = null;
$deviceConfigApiKey = null;
$deviceConfigDevice = null;

if (file_exists($deviceConfigPath)) {
    try {
        $deviceConfig = require $deviceConfigPath;
        $deviceConfigApiKey = $deviceConfig['api_key'] ?? null;
        $deviceConfigDevice = $deviceConfig['device'] ?? null;
    } catch (Exception $e) {
        error_log("Error loading device/config.php: " . $e->getMessage());
    }
} else {
    // Try alternative path
    $altPath = __DIR__ . '/../../device/config.php';
    if (file_exists($altPath)) {
        try {
            $deviceConfig = require $altPath;
            $deviceConfigApiKey = $deviceConfig['api_key'] ?? null;
            $deviceConfigDevice = $deviceConfig['device'] ?? null;
        } catch (Exception $e) {
            error_log("Error loading device/config.php from alt path: " . $e->getMessage());
        }
    }
}

// Check .env file directly
$envPath = __DIR__ . '/../../../.env';
$envApiKey = null;
$envDeviceId = null;
$envApiUrl = null;

if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $lineNum => $line) {
        $line = trim($line);
        if (strpos($line, '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            // Remove quotes
            if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
                (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
                $value = substr($value, 1, -1);
            }
            
            if ($name === 'SMS_API_KEY') {
                $envApiKey = $value;
            } elseif ($name === 'SMS_DEVICE_ID') {
                $envDeviceId = $value;
            } elseif ($name === 'SMS_API_URL') {
                $envApiUrl = $value;
            }
        }
    }
}

echo "<div class='section info'>
    <h2>📋 Configuration Status</h2>
    <table>
        <tr>
            <th>Setting</th>
            <th>Status</th>
            <th>Value</th>
        </tr>
        <tr>
            <td>Configuration Valid</td>
            <td class='" . ($isConfigured ? 'status-ok' : 'status-error') . "'>" . 
                ($isConfigured ? '✅ Yes' : '❌ No') . "</td>
            <td>-</td>
        </tr>
        <tr>
            <td>API Key</td>
            <td class='" . (!empty($apiKey) ? 'status-ok' : 'status-error') . "'>" . 
                (!empty($apiKey) ? '✅ Set' : '❌ Missing') . "</td>
            <td class='key-display'>" . (!empty($apiKey) ? substr($apiKey, 0, 20) . '... (length: ' . strlen($apiKey) . ')' : 'Not configured') . "</td>
        </tr>
        <tr>
            <td>Device ID</td>
            <td class='" . (!empty($deviceId) ? 'status-ok' : 'status-error') . "'>" . 
                (!empty($deviceId) ? '✅ Set' : '❌ Missing') . "</td>
            <td class='key-display'>" . (!empty($deviceId) ? $deviceId : 'Not configured') . "</td>
        </tr>
        <tr>
            <td>API URL</td>
            <td class='" . (!empty($apiUrl) ? 'status-ok' : 'status-error') . "'>" . 
                (!empty($apiUrl) ? '✅ Set' : '❌ Missing') . "</td>
            <td>" . htmlspecialchars($apiUrl) . "</td>
        </tr>
    </table>";

if (!$isConfigured) {
    echo "<div class='error' style='margin-top: 15px; padding: 10px;'>
        <strong>⚠️ Configuration Errors:</strong><ul>";
    foreach ($configErrors as $error) {
        echo "<li>" . htmlspecialchars($error) . "</li>";
    }
    echo "</ul></div>";
}

echo "</div>";

// Compare .env vs device/config.php
echo "<div class='section " . ($apiKey === $deviceConfigApiKey ? 'success' : 'warning') . "'>
    <h2>🔑 API Key Comparison</h2>";

echo "<table>
    <tr>
        <th>Source</th>
        <th>API Key</th>
        <th>Device ID</th>
        <th>Status</th>
    </tr>";

// .env file
echo "<tr>
    <td><strong>.env file</strong></td>
    <td class='key-display'>" . 
        ($envApiKey ? substr($envApiKey, 0, 30) . '... (length: ' . strlen($envApiKey) . ')' : '<span class=\"status-error\">Not found</span>') . 
    "</td>
    <td class='key-display'>" . ($envDeviceId ?: '<span class=\"status-error\">Not found</span>') . "</td>
    <td>" . ($envApiKey ? '✅ Found' : '❌ Missing') . "</td>
</tr>";

// device/config.php
echo "<tr>
    <td><strong>device/config.php</strong></td>
    <td class='key-display'>" . 
        ($deviceConfigApiKey ? substr($deviceConfigApiKey, 0, 30) . '... (length: ' . strlen($deviceConfigApiKey) . ')' : '<span class=\"status-error\">Not found</span>') . 
    "</td>
    <td class='key-display'>" . ($deviceConfigDevice ?: '<span class=\"status-error\">Not found</span>') . "</td>
    <td>" . ($deviceConfigApiKey ? '✅ Found' : '❌ Missing') . "</td>
</tr>";

// Currently used
echo "<tr>
    <td><strong>Currently Used</strong></td>
    <td class='key-display'>" . 
        ($apiKey ? substr($apiKey, 0, 30) . '... (length: ' . strlen($apiKey) . ')' : '<span class=\"status-error\">Not set</span>') . 
    "</td>
    <td class='key-display'>" . ($deviceId ?: '<span class=\"status-error\">Not set</span>') . "</td>
    <td>" . ($apiKey ? '✅ Active' : '❌ Not set') . "</td>
</tr>";

echo "</table>";

// Determine source of currently used API key
$sourceInfo = '';
if ($apiKey === $envApiKey && $envApiKey) {
    $sourceInfo = "<p class='status-ok'><strong>✅ Source:</strong> API key is from .env file</p>";
} elseif ($apiKey === $deviceConfigApiKey && $deviceConfigApiKey) {
    $sourceInfo = "<p class='status-warning'><strong>⚠️ Source:</strong> API key is from device/config.php (fallback - .env not configured or not found)</p>";
} elseif ($apiKey) {
    $sourceInfo = "<p class='status-warning'><strong>⚠️ Source:</strong> API key source unknown (possibly from cache or other config)</p>";
}

echo $sourceInfo;

// Check if they match
if ($envApiKey && $deviceConfigApiKey) {
    if ($envApiKey === $deviceConfigApiKey) {
        echo "<p class='match'>✅ API keys match between .env and device/config.php</p>";
    } else {
        echo "<p class='mismatch'>⚠️ API keys DO NOT match! This could cause issues.</p>";
        echo "<p><strong>Recommendation:</strong> Use the same API key in both files, or ensure .env is properly configured.</p>";
    }
} elseif (!$envApiKey && $deviceConfigApiKey) {
    echo "<p class='status-warning'><strong>⚠️ Important:</strong> No .env file found or SMS_API_KEY not set in .env. System is using fallback from device/config.php.</p>";
    echo "<p><strong>Action Required:</strong> The API key in device/config.php (<code>" . substr($deviceConfigApiKey, 0, 20) . "...</code>) is being rejected by PageNet with Error 406. You need to:</p>";
    echo "<ol>";
    echo "<li>Contact PageNet to get a <strong>new, valid API key</strong></li>";
    echo "<li>Update your .env file (lines 76-80) with the new credentials</li>";
    echo "<li>Or update device/config.php with the new API key if you prefer</li>";
    echo "</ol>";
}

echo "</div>";

// Test API key
if (!empty($apiKey) && !empty($deviceId)) {
    echo "<div class='section info'>
        <h2>🧪 API Key Test</h2>
        <p>Testing API key with a sample request...</p>";
    
    $testPhone = '09318261972'; // Test number
    $testMessage = "Test SMS from diagnostic tool at " . date('Y-m-d H:i:s');
    
    $params = [
        'message' => $testMessage,
        'mobile_number' => $testPhone,
        'device' => $deviceId
    ];
    
    $headers = ['apikey:' . $apiKey];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    $curlErrno = curl_errno($ch);
    curl_close($ch);
    
    $json = json_decode($response, true);
    
    echo "<table>
        <tr>
            <th>Test Item</th>
            <th>Result</th>
        </tr>
        <tr>
            <td>HTTP Status Code</td>
            <td><strong>" . $httpCode . "</strong></td>
        </tr>
        <tr>
            <td>cURL Error</td>
            <td>" . ($curlErrno !== 0 ? '<span class=\"status-error\">' . htmlspecialchars($error) . '</span>' : '<span class=\"status-ok\">None</span>') . "</td>
        </tr>
        <tr>
            <td>API Response</td>
            <td><pre>" . htmlspecialchars($response ?: 'No response') . "</pre></td>
        </tr>";
    
    if ($json) {
        echo "<tr>
            <td>Success Status</td>
            <td>" . (isset($json['success']) && $json['success'] ? '<span class=\"status-ok\">✅ Success</span>' : '<span class=\"status-error\">❌ Failed</span>') . "</td>
        </tr>";
        
        if (isset($json['code'])) {
            $errorMsg = '';
            switch ($json['code']) {
                case 406:
                    $errorMsg = 'API Key mismatch or not acceptable - The API key is invalid or expired';
                    break;
                case 422:
                    $errorMsg = 'API key must be provided in request header - Header format issue';
                    break;
                case 401:
                    $errorMsg = 'Invalid API key - Authentication failed';
                    break;
                default:
                    $errorMsg = 'Error code: ' . $json['code'];
            }
            echo "<tr>
                <td>Error Code</td>
                <td><span class=\"status-error\">" . $json['code'] . " - " . $errorMsg . "</span></td>
            </tr>";
        }
        
        if (isset($json['errors']) && is_array($json['errors'])) {
            echo "<tr>
                <td>Error Messages</td>
                <td><ul>";
            foreach ($json['errors'] as $err) {
                echo "<li class=\"status-error\">" . htmlspecialchars($err) . "</li>";
            }
            echo "</ul></td>
            </tr>";
        }
    }
    
    echo "</table>";
    
    if ($json && isset($json['success']) && $json['success']) {
        echo "<p class='status-ok'><strong>✅ SUCCESS!</strong> Your API key is working correctly!</p>";
    } else {
        echo "<div class='error' style='margin-top: 15px; padding: 10px;'>";
        echo "<strong>❌ API Key Test Failed - Error 406</strong>";
        echo "<p><strong>Root Cause:</strong> The API key <code>" . substr($apiKey, 0, 20) . "...</code> is being rejected by PageNet.</p>";
        echo "<p><strong>This means:</strong></p>";
        echo "<ul>";
        if ($apiKey === $deviceConfigApiKey && $deviceConfigApiKey) {
            echo "<li>The API key in <strong>device/config.php</strong> is invalid or expired</li>";
            echo "<li>No .env file found or SMS_API_KEY not set in .env (system using fallback)</li>";
        } elseif ($apiKey === $envApiKey && $envApiKey) {
            echo "<li>The API key in your <strong>.env file</strong> is incorrect or expired</li>";
        } else {
            echo "<li>The API key being used is invalid or expired</li>";
        }
        echo "<li>You need to contact <strong>PageNet</strong> to get a new, valid API key</li>";
        echo "<li>The API key may have been revoked or expired</li>";
        echo "</ul>";
        echo "<p><strong>Immediate Action Required:</strong></p>";
        echo "<ol>";
        echo "<li>Contact PageNet support to verify your account status and get new API credentials</li>";
        echo "<li>Once you have the new API key, update your configuration:</li>";
        echo "<ul>";
        if (!$envApiKey) {
            echo "<li><strong>Option 1 (Recommended):</strong> Create/update .env file with:<pre>SMS_API_KEY=new_api_key_here\nSMS_DEVICE_ID=new_device_id_here\nSMS_API_URL=https://sms.pagenet.info/api/v1/sms/send</pre></li>";
        }
        echo "<li><strong>Option 2:</strong> Update device/config.php with the new API key</li>";
        echo "</ul>";
        echo "<li>Refresh this diagnostic page to test the new credentials</li>";
        echo "</ol>";
        echo "</div>";
    }
    
    echo "</div>";
} else {
    echo "<div class='section error'>
        <h2>❌ Cannot Test API</h2>
        <p>API key or device ID is missing. Please configure them first.</p>
    </div>";
}

// Recommendations
echo "<div class='section warning'>
    <h2>💡 Recommendations</h2>
    <ol>
        <li><strong>Check your .env file</strong> (lines 76-80):
            <pre>SMS_API_KEY=your_api_key_here
SMS_DEVICE_ID=your_device_id_here
SMS_API_URL=https://sms.pagenet.info/api/v1/sms/send</pre>
            <ul>
                <li>Make sure there are NO quotes around the values</li>
                <li>Make sure there are NO spaces before or after the = sign</li>
                <li>Make sure each value is on a single line</li>
            </ul>
        </li>
        <li><strong>Verify API Key</strong>:
            <ul>
                <li>Contact PageNet to confirm your API key is still valid</li>
                <li>API keys can expire or be revoked</li>
                <li>Make sure you're using the correct API key for your account</li>
            </ul>
        </li>
        <li><strong>Check device/config.php</strong>:
            <ul>
                <li>If .env is not working, the system falls back to device/config.php</li>
                <li>Make sure the API key there is also valid</li>
            </ul>
        </li>
        <li><strong>Test Again</strong>:
            <ul>
                <li>After updating .env, refresh this page to test again</li>
                <li>Or use the test script: <code>test_sms_api.php</code></li>
            </ul>
        </li>
    </ol>
</div>";

echo "</div>
</body>
</html>";
?>

