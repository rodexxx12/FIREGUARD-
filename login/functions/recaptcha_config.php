<?php
require_once __DIR__ . '/env.php';

/**
 * reCAPTCHA configuration shim. Site/secret keys now live in config.env
 * (RECAPTCHA_SITE_KEY / RECAPTCHA_SECRET_KEY) to keep secrets out of source.
 */
return [
    'domains' => [],
    'default' => [
        'site_key' => (string)loginEnv('RECAPTCHA_SITE_KEY', ''),
        'secret_key' => (string)loginEnv('RECAPTCHA_SECRET_KEY', '')
    ],
];
