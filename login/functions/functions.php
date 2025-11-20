<?php
require_once __DIR__ . '/init.php';

// Handle AJAX requests
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    handleAjaxRequest();
}

// Check remember me cookie
$rememberMeResult = checkRememberMeCookie();
if ($rememberMeResult && $rememberMeResult['success']) {
    header("Location: " . $rememberMeResult['redirect']);
    exit();
}

// Check session timeout
if (!checkSessionTimeout()) {
    header("Location: login.php");
    exit();
}

// Check for reset token in URL
$reset_token = $_GET['token'] ?? '';
$reset_email = $_GET['email'] ?? '';
$show_reset_form = !empty($reset_token) && !empty($reset_email) && validatePasswordResetToken($reset_token, $reset_email);
?>