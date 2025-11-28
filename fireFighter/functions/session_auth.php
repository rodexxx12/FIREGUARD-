<?php

function validateFirefighterSession(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_set_cookie_params([
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        session_start();
    }

    if (!isset($_SESSION['firefighter_id'])) {
        header("Location: ../../../index.php");
        exit();
    }

    // Regenerate session ID every 5 minutes to mitigate fixation.
    $lastRotation = $_SESSION['last_rotation'] ?? 0;
    if (time() - $lastRotation > 300) {
        session_regenerate_id(true);
        $_SESSION['last_rotation'] = time();
    }

    // Idle timeout (30 minutes)
    $lastActivity = $_SESSION['last_activity'] ?? 0;
    if ($lastActivity && time() - $lastActivity > 1800) {
        session_unset();
        session_destroy();
        header("Location: ../../../index.php?timeout=1");
        exit();
    }

    $_SESSION['last_activity'] = time();
}
?> 