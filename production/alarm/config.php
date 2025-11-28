<?php
/**
 * Alarm Configuration
 * SECURITY: Uses environment variables instead of hardcoded API keys
 */

// Load environment configuration if available
if (file_exists(__DIR__ . '/../../db/load_env.php')) {
    require_once __DIR__ . '/../../db/load_env.php';
}

return [
    'api_key' => getenv('API_KEY') ?: '',
    'device'  => getenv('API_DEVICE_ID') ?: '',
    'url'     => getenv('API_URL') ?: 'https://sms.pagenet.info/api/v1/sms/send'
];
