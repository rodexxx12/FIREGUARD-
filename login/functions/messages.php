<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security.php';

function saveContactMessage($name, $email, $subject, $message) {
    $conn = getDatabaseConnection();
    try {
        $stmt = $conn->prepare("
            INSERT INTO messages (name, email, subject, message) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            sanitizeInput($name),
            filter_var($email, FILTER_SANITIZE_EMAIL),
            sanitizeInput($subject),
            sanitizeInput($message)
        ]);
        return $stmt->rowCount() > 0;
    } catch(PDOException $e) {
        error_log("Failed to save message: " . $e->getMessage());
        return false;
    }
}

function handleContactFormSubmission() {
    header('Content-Type: application/json');
    try {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            throw new Exception('Invalid request method');
        }
        if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
            throw new Exception('Invalid CSRF token');
        }
        $name = $_POST['name'] ?? '';
        $email = $_POST['email'] ?? '';
        $subject = $_POST['subject'] ?? '';
        $message = $_POST['message'] ?? '';
        if (empty($name) || empty($email) || empty($message)) {
            throw new Exception('Please fill in all required fields');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Please enter a valid email address');
        }
        if (saveContactMessage($name, $email, $subject, $message)) {
            echo json_encode(['success' => true, 'message' => 'Your message has been sent successfully!']);
        } else {
            throw new Exception('Failed to send message. Please try again.');
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
} 