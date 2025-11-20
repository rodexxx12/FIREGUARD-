<?php
// Test script to verify API endpoints work correctly
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Fire Detection System API Test</h2>";

// Test device registration
echo "<h3>1. Testing Device Registration</h3>";
$registerUrl = "https://fireguard.bccbsis.com/device/register_device.php";
$testMac = "TEST123456789";
$testName = "Test-FireGuard-Device";

$registerParams = [
    'mac_address' => $testMac,
    'device_name' => $testName,
    'device_type' => 'ESP32_Fire_Detector'
];

$registerUrl .= '?' . http_build_query($registerParams);

echo "<p>Registration URL: <a href='$registerUrl' target='_blank'>$registerUrl</a></p>";

$registerResponse = file_get_contents($registerUrl);
echo "<p>Registration Response:</p>";
echo "<pre>" . htmlspecialchars($registerResponse) . "</pre>";

// Parse device ID from response
$registerData = json_decode($registerResponse, true);
$deviceId = isset($registerData['device_id']) ? $registerData['device_id'] : null;

if ($deviceId) {
    echo "<p style='color: green;'>✓ Device registered successfully! Device ID: $deviceId</p>";
    
    // Test data transmission
    echo "<h3>2. Testing Data Transmission</h3>";
    $apiUrl = "https://fireguard.bccbsis.com/device/smoke_api.php";
    $testParams = [
        'value' => 1500,
        'detected' => 1,
        'flame_detected' => 0,
        'temperature' => 25.5,
        'humidity' => 60.0,
        'heat_index' => 26.8,
        'device_id' => $deviceId,
        'log' => 1
    ];
    
    $apiUrl .= '?' . http_build_query($testParams);
    
    echo "<p>API URL: <a href='$apiUrl' target='_blank'>$apiUrl</a></p>";
    
    $apiResponse = file_get_contents($apiUrl);
    echo "<p>API Response:</p>";
    echo "<pre>" . htmlspecialchars($apiResponse) . "</pre>";
    
    $apiData = json_decode($apiResponse, true);
    if (isset($apiData['status']) && $apiData['status'] === 'success') {
        echo "<p style='color: green;'>✓ Data transmission successful!</p>";
    } else {
        echo "<p style='color: red;'>✗ Data transmission failed!</p>";
    }
} else {
    echo "<p style='color: red;'>✗ Device registration failed!</p>";
}

echo "<h3>3. Test Summary</h3>";
echo "<ul>";
echo "<li>Device Registration: " . ($deviceId ? "✓ PASS" : "✗ FAIL") . "</li>";
echo "<li>Data Transmission: " . (isset($apiData['status']) && $apiData['status'] === 'success' ? "✓ PASS" : "✗ FAIL") . "</li>";
echo "</ul>";

echo "<h3>4. ESP32 Code Status</h3>";
echo "<p>The ESP32 code has been updated with the following features:</p>";
echo "<ul>";
echo "<li>✓ Removed duplicate code and syntax errors</li>";
echo "<li>✓ Added dynamic device registration</li>";
echo "<li>✓ Added device_id parameter to data transmission</li>";
echo "<li>✓ Added automatic re-registration on invalid device ID</li>";
echo "<li>✓ Added persistent storage of device ID in preferences</li>";
echo "</ul>";

echo "<h3>5. Next Steps</h3>";
echo "<ol>";
echo "<li>Upload the updated esp.cpp to your ESP32</li>";
echo "<li>Connect the ESP32 to WiFi</li>";
echo "<li>Monitor the Serial output to see device registration</li>";
echo "<li>Verify data is being sent to the database</li>";
echo "</ol>";
?>
