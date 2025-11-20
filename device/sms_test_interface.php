<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fire Detection System - SMS Test Interface</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #d32f2f;
            text-align: center;
            margin-bottom: 30px;
        }
        .test-section {
            margin-bottom: 30px;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background-color: #fafafa;
        }
        .test-section h3 {
            margin-top: 0;
            color: #333;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input, textarea, select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        textarea {
            height: 100px;
            resize: vertical;
        }
        button {
            background-color: #d32f2f;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin-right: 10px;
            margin-bottom: 10px;
        }
        button:hover {
            background-color: #b71c1c;
        }
        .success {
            background-color: #4caf50;
        }
        .success:hover {
            background-color: #45a049;
        }
        .warning {
            background-color: #ff9800;
        }
        .warning:hover {
            background-color: #f57c00;
        }
        .results {
            margin-top: 20px;
            padding: 15px;
            border-radius: 4px;
            white-space: pre-wrap;
            font-family: monospace;
            font-size: 14px;
        }
        .success-result {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .error-result {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .info-result {
            background-color: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }
        .phone-list {
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 4px;
            margin-top: 10px;
        }
        .phone-item {
            padding: 5px 0;
            border-bottom: 1px solid #eee;
        }
        .phone-item:last-child {
            border-bottom: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚨 Fire Detection System - SMS Test Interface</h1>
        
        <!-- Database Status Section -->
        <div class="test-section">
            <h3>📊 Database Status</h3>
            <button onclick="checkDatabaseStatus()">Check Database & Phone Numbers</button>
            <div id="dbResults" class="results" style="display: none;"></div>
        </div>

        <!-- Basic SMS Test Section -->
        <div class="test-section">
            <h3>📱 Basic SMS Test</h3>
            <div class="form-group">
                <label for="testMessage">Test Message:</label>
                <textarea id="testMessage" placeholder="Enter your test message here...">🚨 FIRE DETECTION SYSTEM TEST 🚨

This is a test message from your Fire Detection System.
Time: <?php echo date('Y-m-d H:i:s'); ?>

If you received this message, the SMS system is working correctly!</textarea>
            </div>
            <button onclick="sendBasicSMS()">Send Test SMS to All Users</button>
            <button onclick="sendBasicSMS(true)" class="warning">Send to Specific User</button>
            <div id="basicResults" class="results" style="display: none;"></div>
        </div>

        <!-- Emergency SMS Test Section -->
        <div class="test-section">
            <h3>🚨 Emergency SMS Test</h3>
            <div class="form-group">
                <label for="emergencyMessage">Emergency Message:</label>
                <textarea id="emergencyMessage" placeholder="Emergency message will be auto-generated...">🚨 FIRE EMERGENCY DETECTED! 

Please evacuate immediately and call 911.
This is a test emergency alert.

Time: <?php echo date('Y-m-d H:i:s'); ?></textarea>
            </div>
            <button onclick="sendEmergencySMS()" class="warning">Send Emergency Alert</button>
            <button onclick="sendAutoEmergencySMS()" class="warning">Send Auto Emergency Alert</button>
            <div id="emergencyResults" class="results" style="display: none;"></div>
        </div>

        <!-- SMS History Section -->
        <div class="test-section">
            <h3>📋 SMS History</h3>
            <button onclick="viewSMSHistory()">View Recent SMS Logs</button>
            <div id="historyResults" class="results" style="display: none;"></div>
        </div>

        <!-- API Configuration Section -->
        <div class="test-section">
            <h3>⚙️ API Configuration</h3>
            <button onclick="checkAPIConfig()">Check SMS API Configuration</button>
            <div id="configResults" class="results" style="display: none;"></div>
        </div>
    </div>

    <script>
        function showResults(elementId, content, type = 'info') {
            const element = document.getElementById(elementId);
            element.style.display = 'block';
            element.className = 'results ' + type + '-result';
            element.textContent = content;
        }

        function checkDatabaseStatus() {
            showResults('dbResults', 'Checking database status...', 'info');
            
            fetch('sms_test_api.php?action=check_database')
                .then(response => response.json())
                .then(data => {
                    let result = 'Database Status:\n';
                    result += '================\n';
                    result += `Connection: ${data.success ? '✅ SUCCESS' : '❌ FAILED'}\n`;
                    
                    if (data.success) {
                        result += `Users: ${data.users_count}\n`;
                        result += `Phone Numbers: ${data.phone_numbers_count}\n`;
                        result += `Verified Phones: ${data.verified_phones_count}\n\n`;
                        
                        if (data.phone_numbers && data.phone_numbers.length > 0) {
                            result += 'Available Phone Numbers:\n';
                            result += '=======================\n';
                            data.phone_numbers.forEach(phone => {
                                result += `• ${phone.fullname} (ID: ${phone.user_id}) - ${phone.phone_number} ${phone.verified ? '✅' : '❌'}\n`;
                            });
                        }
                    } else {
                        result += `Error: ${data.message}\n`;
                    }
                    
                    showResults('dbResults', result, data.success ? 'success' : 'error');
                })
                .catch(error => {
                    showResults('dbResults', 'Error: ' + error.message, 'error');
                });
        }

        function sendBasicSMS(specificUser = false) {
            const message = document.getElementById('testMessage').value;
            if (!message.trim()) {
                showResults('basicResults', 'Please enter a test message!', 'error');
                return;
            }

            showResults('basicResults', 'Sending SMS...', 'info');
            
            const formData = new FormData();
            formData.append('message', message);
            if (specificUser) {
                formData.append('user_id', '1'); // Default to user ID 1
            }

            fetch('sms.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                let result = 'SMS Test Results:\n';
                result += '=================\n';
                result += `Status: ${data.success ? '✅ SUCCESS' : '❌ FAILED'}\n`;
                result += `Message: ${data.message}\n`;
                result += `Sent: ${data.sent || 0}\n`;
                result += `Failed: ${data.failed || 0}\n`;
                result += `Total: ${data.total || 0}\n\n`;
                
                if (data.results && data.results.length > 0) {
                    result += 'Individual Results:\n';
                    result += '==================\n';
                    data.results.forEach((result_item, index) => {
                        result += `${index + 1}. User ID: ${result_item.user_id}\n`;
                        result += `   Phone: ${result_item.phone_number}\n`;
                        result += `   Status: ${result_item.status}\n`;
                        result += `   HTTP Code: ${result_item.http_code}\n`;
                        if (result_item.error) {
                            result += `   Error: ${result_item.error}\n`;
                        }
                        result += '\n';
                    });
                }
                
                showResults('basicResults', result, data.success ? 'success' : 'error');
            })
            .catch(error => {
                showResults('basicResults', 'Error: ' + error.message, 'error');
            });
        }

        function sendEmergencySMS() {
            const message = document.getElementById('emergencyMessage').value;
            showResults('emergencyResults', 'Sending emergency SMS...', 'info');
            
            const formData = new FormData();
            formData.append('message', message);
            formData.append('emergency', '1');

            fetch('sms.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                let result = 'Emergency SMS Results:\n';
                result += '=====================\n';
                result += `Status: ${data.success ? '✅ SUCCESS' : '❌ FAILED'}\n`;
                result += `Message: ${data.message}\n`;
                result += `Sent: ${data.sent || 0}\n`;
                result += `Failed: ${data.failed || 0}\n`;
                result += `Total: ${data.total || 0}\n`;
                
                showResults('emergencyResults', result, data.success ? 'success' : 'error');
            })
            .catch(error => {
                showResults('emergencyResults', 'Error: ' + error.message, 'error');
            });
        }

        function sendAutoEmergencySMS() {
            showResults('emergencyResults', 'Sending auto emergency SMS...', 'info');
            
            const formData = new FormData();
            formData.append('auto_emergency', '1');

            fetch('sms.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                let result = 'Auto Emergency SMS Results:\n';
                result += '===========================\n';
                result += `Status: ${data.success ? '✅ SUCCESS' : '❌ FAILED'}\n`;
                result += `Message: ${data.message}\n`;
                result += `Sent: ${data.sent || 0}\n`;
                result += `Failed: ${data.failed || 0}\n`;
                result += `Total: ${data.total || 0}\n`;
                
                showResults('emergencyResults', result, data.success ? 'success' : 'error');
            })
            .catch(error => {
                showResults('emergencyResults', 'Error: ' + error.message, 'error');
            });
        }

        function viewSMSHistory() {
            showResults('historyResults', 'Loading SMS history...', 'info');
            
            fetch('sms_test_api.php?action=view_history')
                .then(response => response.json())
                .then(data => {
                    let result = 'SMS History (Last 10 records):\n';
                    result += '==============================\n';
                    
                    if (data.success && data.logs && data.logs.length > 0) {
                        data.logs.forEach((log, index) => {
                            result += `${index + 1}. ID: ${log.id}\n`;
                            result += `   User ID: ${log.user_id}\n`;
                            result += `   Phone: ${log.phone_number}\n`;
                            result += `   Status: ${log.status}\n`;
                            result += `   Provider: ${log.provider}\n`;
                            result += `   HTTP Code: ${log.http_code}\n`;
                            result += `   Time: ${log.created_at}\n`;
                            if (log.error) {
                                result += `   Error: ${log.error}\n`;
                            }
                            result += '\n';
                        });
                    } else {
                        result += 'No SMS logs found.\n';
                    }
                    
                    showResults('historyResults', result, data.success ? 'success' : 'error');
                })
                .catch(error => {
                    showResults('historyResults', 'Error: ' + error.message, 'error');
                });
        }

        function checkAPIConfig() {
            showResults('configResults', 'Checking API configuration...', 'info');
            
            fetch('sms_test_api.php?action=check_config')
                .then(response => response.json())
                .then(data => {
                    let result = 'SMS API Configuration:\n';
                    result += '======================\n';
                    result += `API Key: ${data.api_key ? data.api_key.substring(0, 10) + '...' : 'Not set'}\n`;
                    result += `Device ID: ${data.device_id || 'Not set'}\n`;
                    result += `API URL: ${data.api_url || 'Not set'}\n`;
                    result += `Status: ${data.valid ? '✅ Valid' : '❌ Invalid'}\n`;
                    
                    showResults('configResults', result, data.valid ? 'success' : 'error');
                })
                .catch(error => {
                    showResults('configResults', 'Error: ' + error.message, 'error');
                });
        }

        // Auto-load database status on page load
        window.onload = function() {
            checkDatabaseStatus();
        };
    </script>
</body>
</html>
