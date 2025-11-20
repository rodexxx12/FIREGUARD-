<?php
$scriptPath = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', $_SERVER['SCRIPT_NAME']) : '';
$loginSegment = '/login';
$loginAssetBase = $loginSegment;

if (!empty($scriptPath)) {
    $loginPos = strpos($scriptPath, $loginSegment . '/');
    if ($loginPos !== false) {
        $loginAssetBase = substr($scriptPath, 0, $loginPos + strlen($loginSegment));
    } else {
        $scriptDir = rtrim(dirname($scriptPath), '/');
        if ($scriptDir === '' || $scriptDir === '.') {
            $loginAssetBase = $loginSegment;
        } else {
            $loginAssetBase = $scriptDir . $loginSegment;
        }
    }
}

$loginAssetBase = rtrim(preg_replace('#/+#', '/', $loginAssetBase), '/');
if ($loginAssetBase === '') {
    $loginAssetBase = $loginSegment;
}

if (!defined('LOGIN_ASSET_BASE')) {
    define('LOGIN_ASSET_BASE', $loginAssetBase);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>FIREGUARD</title>
    <link rel="icon" type="image/png" href="<?= LOGIN_ASSET_BASE ?>/php/components/fireguard.png?v=1">
    <link rel="shortcut icon" type="image/png" href="<?= LOGIN_ASSET_BASE ?>/php/components/fireguard.png?v=1">
    <link rel="apple-touch-icon" href="<?= LOGIN_ASSET_BASE ?>/php/components/fireguard.png?v=1">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= LOGIN_ASSET_BASE ?>/css/login.css">
    <!-- Google reCAPTCHA -->
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
</head>
<body>

<!-- Header -->
<header id="header">
    <div class="container header-container">
        <a href="#" class="logo">
            <img src="<?= LOGIN_ASSET_BASE ?>/php/components/fireguardlogo.png" alt="Fire Guard Logo" class="logo-img">
            <span>FIREGUARD</span>
        </a>
    
        <button class="mobile-menu-btn" id="mobileMenuBtn">
            <i class="fas fa-bars"></i>
        </button>
        
        <ul class="nav-links" id="navLinks">
            <li><a href="#home">Home</a></li>
            <li><a href="#about">About</a></li>
            <li style="display: none;"><a href="#" id="navLoginBtn" class="btn btn-outline">Login</a></li>
        </ul>
    </div>
</header> 