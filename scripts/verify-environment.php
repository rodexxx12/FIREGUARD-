<?php
/**
 * Environment Configuration Verifier
 *
 * Usage (from project root):
 *   php scripts/verify-environment.php
 *
 * Exit codes:
 *   0 = all required environment variables are present and non-empty
 *   1 = one or more required variables are missing or empty
 */

declare(strict_types=1);

require_once __DIR__ . '/../core/config/config.php';

// Core application / environment variables to verify
$requiredEnvKeys = [
    // Application
    'APP_ENV',
    'APP_URL',

    // Database
    'DB_HOST',
    'DB_NAME',
    'DB_USER',
    'DB_PASS',

    // SMTP / mail
    'SMTP_HOST',
    'SMTP_PORT',
    'SMTP_USER',
    'SMTP_PASS',
    'MAIL_FROM_EMAIL',
    'MAIL_FROM_NAME',

    // reCAPTCHA
    'RECAPTCHA_SITE_KEY',
    'RECAPTCHA_SECRET_KEY',

    // SMS alerts
    'SMS_API_KEY',
    'SMS_DEVICE_ID',
    'SMS_API_URL',
];

$missing = [];

foreach ($requiredEnvKeys as $key) {
    // Use env() helper so core/config/env.php loading is respected
    $value = env($key, null);

    if ($value === null || $value === '') {
        $missing[] = $key;
    }
}

if (empty($missing)) {
    echo "Environment check: OK (all required variables are set).\n";
    exit(0);
}

echo "Environment check: missing or empty environment variables detected:\n\n";
foreach ($missing as $key) {
    echo " - {$key}\n";
}

echo "\nPlease set these in your .env file (or server environment) before deploying to production.\n";
exit(1);















