<?php
require_once dirname(__DIR__) . '/config/database.php';

class AuthHandler {
    private $pdo;
    
    public function __construct() {
        $this->pdo = getDatabaseConnection();
    }
    
    public function handleLogin($username, $password) {
        if (empty($username) || empty($password)) {
            return ['status' => 'error', 'message' => 'Username and password are required'];
        }
        
        try {
            $stmt = $this->pdo->prepare("SELECT user_id, username, password FROM users WHERE username = :username");
            $stmt->execute([':username' => $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                return ['status' => 'success', 'message' => 'Login successful'];
            } else {
                return ['status' => 'error', 'message' => 'Invalid username or password'];
            }
        } catch (PDOException $e) {
            return ['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    public function isAuthenticated() {
        return isset($_SESSION['user_id']);
    }
    
    public function getUserId() {
        return $_SESSION['user_id'] ?? null;
    }
}
?> 