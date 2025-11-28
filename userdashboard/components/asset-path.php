<?php
/**
 * Define USER_DASHBOARD_ASSET_BASE once so every module can reference shared assets
 * without relying on fragile relative paths.
 */
if (!defined('USER_DASHBOARD_ASSET_BASE')) {
    $scriptPath = isset($_SERVER['SCRIPT_NAME'])
        ? str_replace('\\', '/', $_SERVER['SCRIPT_NAME'])
        : '';

    $segment = '/userdashboard';
    $base = $segment;

    if ($scriptPath !== '') {
        $segmentPos = strpos($scriptPath, $segment . '/');
        if ($segmentPos !== false) {
            $base = substr($scriptPath, 0, $segmentPos + strlen($segment));
        } else {
            $scriptDir = rtrim(dirname($scriptPath), '/');
            $base = ($scriptDir === '' || $scriptDir === '.')
                ? $segment
                : $scriptDir . $segment;
        }
    }

    $base = rtrim(preg_replace('#/+#', '/', $base), '/');
    if ($base === '') {
        $base = $segment;
    }

    define('USER_DASHBOARD_ASSET_BASE', $base);
}
















