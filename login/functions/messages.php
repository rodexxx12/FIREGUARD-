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
            sanitizeInput($name, 80),
            filter_var($email, FILTER_SANITIZE_EMAIL),
            sanitizeInput($subject, 120),
            sanitizeInput($message, 2000)
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
        if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'], 'contact_form')) {
            throw new Exception('Invalid CSRF token');
        }
        $name = normalizeInput($_POST['name'] ?? '', 80);
        $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
        $subject = normalizeInput($_POST['subject'] ?? '', 120);
        $message = normalizeInput($_POST['message'] ?? '', 2000);

        if ($name === '' || !$email || $message === '') {
            throw new Exception('Please provide your name, a valid email, and a message.');
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