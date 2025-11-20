<?php
// Include all required files first
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/email.php';
require_once __DIR__ . '/messages.php';
require_once __DIR__ . '/ajax.php';

// Initialize secure session after including session.php
initSecureSession();

// Set security headers
setSecurityHeaders();

// Validate session integrity
if (!validateSessionIntegrity()) {
    header('Location: /login');
    exit();
}

// Check session timeout
if (!checkSessionTimeout()) {
    header('Location: /login');
    exit();
}

// Check remember me cookie if no active session
if (empty($_SESSION['user_id']) && empty($_SESSION['admin_id']) && empty($_SESSION['firefighter_id']) && empty($_SESSION['superadmin_id'])) {
    checkRememberMeCookie();
} 