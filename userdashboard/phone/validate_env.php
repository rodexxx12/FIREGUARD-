<?php
/**
 * .env File Validator for SMS Configuration
 * This script validates and shows the exact format needed for .env file
 */

$envPath = __DIR__ . '/../../../.env';

echo "<!DOCTYPE html>
<html>
<head>
    <title>.env SMS Configuration Validator</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #007bff; padding-bottom: 10px; }
        .section { margin: 20px 0; padding: 15px; background: #f9f9f9; border-left: 4px solid #007bff; }
        .success { background: #d4edda; border-left-color: #28a745; }
        .error { background: #fff3cd; border-left-color: #ffc107; }
        .warning { background: #fff3cd; border-left-color: #ffc107; }
        pre { background: #2d2d2d; color: #f8f8f2; padding: 15px; border-radius: 4px; overflow-x: auto; }
        .key-display { font-family: monospace; background: #e9ecef; padding: 5px 10px; border-radius: 4px; margin: 5px 0; }
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
    <h1>📋 .env File SMS Configuration Validator</h1>";

if (!file_exists($envPath)) {
    echo "<div class='section error'>
        <h2>❌ .env File Not Found</h2>
        <p>The .env file was not found at: <code>" . htmlspecialchars($envPath) . "</code></p>
        <p><strong>Create the file with the following content:</strong></p>
        <pre># SMS API Configuration
SMS_API_KEY=your_api_key_from_pagenet
SMS_DEVICE_ID=your_device_id_from_pagenet
SMS_API_URL=https://sms.pagenet.info/api/v1/sms/send</pre>
    </div>";
} else {
    echo "<div class='section success'>
        <h2>✅ .env File Found</h2>
        <p>Location: <code>" . htmlspecialchars($envPath) . "</code></p>
    </div>";
    
    // Read and analyze the file
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $smsConfig = [];
    $lineNumbers = [];
    
    foreach ($lines as $lineNum => $line) {
        $line = trim($line);
        if (strpos($line, '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            
            if ($name === 'SMS_API_KEY' || $name === 'SMS_DEVICE_ID' || $name === 'SMS_API_URL') {
                $smsConfig[$name] = $value;
                $lineNumbers[$name] = $lineNum + 1; // 1-based line numbers
            }
        }
    }
    
    echo "<div class='section " . (count($smsConfig) === 3 ? 'success' : 'warning') . "'>
        <h2>📝 SMS Configuration Analysis</h2>
        <table>
            <tr>
                <th>Setting</th>
                <th>Status</th>
                <th>Value</th>
                <th>Line #</th>
            </tr>";
    
    $required = ['SMS_API_KEY', 'SMS_DEVICE_ID', 'SMS_API_URL'];
    foreach ($required as $key) {
        $found = isset($smsConfig[$key]);
        $value = $found ? $smsConfig[$key] : 'Not found';
        $lineNum = isset($lineNumbers[$key]) ? $lineNumbers[$key] : '-';
        
        // Check for common issues
        $issues = [];
        if ($found) {
            // Check for quotes
            if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
                (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
                $issues[] = 'Has quotes (remove them)';
            }
            // Check for spaces
            if (strpos($value, ' ') !== false) {
                $issues[] = 'Contains spaces';
            }
            // Check if empty
            if (empty($value)) {
                $issues[] = 'Value is empty';
            }
        }
        
        echo "<tr>
            <td><strong>$key</strong></td>
            <td class='" . ($found && empty($issues) ? 'status-ok' : 'status-error') . "'>" . 
                ($found && empty($issues) ? '✅ OK' : ($found ? '⚠️ Issues' : '❌ Missing')) . "</td>
            <td class='key-display'>" . 
                ($found ? htmlspecialchars(substr($value, 0, 40) . (strlen($value) > 40 ? '...' : '') . ' (length: ' . strlen($value) . ')') : 'Not configured') . 
            "</td>
            <td>$lineNum</td>
        </tr>";
        
        if (!empty($issues)) {
            echo "<tr><td colspan='4'><ul>";
            foreach ($issues as $issue) {
                echo "<li class='status-error'>⚠️ $issue</li>";
            }
            echo "</ul></td></tr>";
        }
    }
    
    echo "</table>";
    
    if (count($smsConfig) === 3 && empty($issues)) {
        echo "<p class='status-ok'><strong>✅ Format is correct!</strong></p>";
        echo "<p>However, if you're getting Error 406, the API key itself is invalid or expired.</p>";
        echo "<p><strong>Action Required:</strong> Contact PageNet to get a new, valid API key.</p>";
    } elseif (count($smsConfig) < 3) {
        echo "<p class='status-error'><strong>❌ Missing Configuration</strong></p>";
        echo "<p>Add the missing configuration to your .env file (around lines 76-80):</p>";
    }
    
    echo "</div>";
    
    // Show correct format
    echo "<div class='section warning'>
        <h2>📋 Correct Format for .env File (Lines 76-80)</h2>
        <p>Your .env file should have these exact lines (no quotes, no spaces around =):</p>
        <pre># SMS API Configuration
SMS_API_KEY=your_api_key_from_pagenet
SMS_DEVICE_ID=your_device_id_from_pagenet
SMS_API_URL=https://sms.pagenet.info/api/v1/sms/send</pre>
        <p><strong>Important:</strong></p>
        <ul>
            <li>❌ NO quotes around values (not <code>SMS_API_KEY=\"value\"</code>)</li>
            <li>❌ NO spaces before or after = (not <code>SMS_API_KEY = value</code>)</li>
            <li>✅ Each value on a single line</li>
            <li>✅ No trailing spaces</li>
        </ul>
    </div>";
    
    // Show current values if found
    if (!empty($smsConfig)) {
        echo "<div class='section info'>
            <h2>🔍 Current Values in .env</h2>
            <table>
                <tr><th>Setting</th><th>Current Value</th><th>Line #</th></tr>";
        foreach ($smsConfig as $key => $value) {
            echo "<tr>
                <td><strong>$key</strong></td>
                <td class='key-display'>" . htmlspecialchars($value) . "</td>
                <td>" . (isset($lineNumbers[$key]) ? $lineNumbers[$key] : '-') . "</td>
            </tr>";
        }
        echo "</table>";
        
        // Check if API key matches device/config.php
        $deviceConfigPath = __DIR__ . '/../../../device/config.php';
        if (file_exists($deviceConfigPath)) {
            $deviceConfig = require $deviceConfigPath;
            if (isset($smsConfig['SMS_API_KEY']) && $smsConfig['SMS_API_KEY'] === $deviceConfig['api_key']) {
                echo "<p class='status-warning'><strong>⚠️ Note:</strong> Your .env API key matches device/config.php. If you're getting Error 406, both keys are invalid and need to be updated.</p>";
            }
        }
        
        echo "</div>";
    }
}

echo "</div>
</body>
</html>";
?>











