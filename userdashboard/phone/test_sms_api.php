<?php
/**
 * SMS API Test Script
 * This script tests the SMS API credentials to verify they are working correctly
 */

require_once __DIR__ . '/config/config.php';

// Get config
$apiKey = $config['api_key'];
$deviceId = $config['device'];
$apiUrl = $config['url'];

echo "=== SMS API Credential Test ===\n\n";
echo "API URL: $apiUrl\n";
echo "API Key: " . substr($apiKey, 0, 10) . "... (length: " . strlen($apiKey) . ")\n";
echo "Device ID: $deviceId\n";
echo "Is Configured: " . ($config['is_configured'] ? 'Yes' : 'No') . "\n\n";

if (!$config['is_configured']) {
    echo "ERROR: SMS is not properly configured!\n";
    echo "Errors: " . implode(', ', $config['errors']) . "\n";
    exit(1);
}

// Test phone number (use a test number or your own)
$testPhone = '09318261972'; // Change this to a valid test number
$testMessage = "Test SMS from DEFENDED system at " . date('Y-m-d H:i:s');

echo "Testing SMS API with:\n";
echo "  Phone: $testPhone\n";
echo "  Message: $testMessage\n\n";

// Try both header formats
$headerFormats = [
    ['apikey:' . $apiKey],  // device/sms.php format
    ["Content-Type: application/x-www-form-urlencoded", "apikey: $apiKey"]  // smokestore.php format
];

$params = [
    'message' => $testMessage,
    'mobile_number' => $testPhone,
    'device' => $deviceId
];

foreach ($headerFormats as $index => $headers) {
    echo "--- Testing Format " . ($index + 1) . " ---\n";
    echo "Headers: " . json_encode($headers) . "\n";
    
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
    
    echo "HTTP Code: $httpCode\n";
    echo "Response: $response\n";
    
    if ($curlErrno !== 0) {
        echo "cURL Error: $error (Code: $curlErrno)\n";
    } else {
        $json = json_decode($response, true);
        if ($json && isset($json['success'])) {
            if ($json['success']) {
                echo "✅ SUCCESS! SMS sent successfully!\n";
                break;
            } else {
                echo "❌ FAILED: " . (isset($json['errors']) ? implode(', ', $json['errors']) : 'Unknown error') . "\n";
                if (isset($json['code'])) {
                    echo "Error Code: " . $json['code'] . "\n";
                    if ($json['code'] == 406) {
                        echo "⚠️  This means the API key is invalid or expired. Contact your SMS provider (PageNet) to get valid credentials.\n";
                    } elseif ($json['code'] == 422) {
                        echo "⚠️  This means the header format is not recognized. Trying next format...\n";
                        continue;
                    }
                }
            }
        } else {
            echo "⚠️  Unexpected response format\n";
        }
    }
    echo "\n";
}

echo "\n=== Test Complete ===\n";
echo "If both formats failed with error 406, your API key is invalid or expired.\n";
echo "Contact your SMS service provider (PageNet) to get valid credentials.\n";
?>











