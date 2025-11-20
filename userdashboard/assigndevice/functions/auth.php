<?php
session_start();

/**
 * Authentication component
 */
class Auth {
    /**
     * Check if user is logged in
     * @return bool
     */
    public static function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
    
    /**
     * Get current user ID
     * @return int|null
     */
    public static function getUserId() {
        return $_SESSION['user_id'] ?? null;
    }
    
    /**
     * Get user information
     * @param PDO $pdo
     * @param int $user_id
     * @return array|false
     */
    public static function getUserInfo($pdo, $user_id) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Redirect to login if not authenticated
     */
    public static function requireLogin() {
        if (!self::isLoggedIn()) {
            header("Location: ../../../index.php");
            exit();
        }
    }
} 