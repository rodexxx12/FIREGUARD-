<?php
session_start();
// Try different paths for db_connection.php
if (file_exists('../db_connection.php')) {
    require_once '../db_connection.php';
} elseif (file_exists('db_connection.php')) {
    require_once 'db_connection.php';
} elseif (file_exists(__DIR__ . '/../db_connection.php')) {
    require_once __DIR__ . '/../db_connection.php';
} else {
    die('Cannot find db_connection.php');
}

class UserPhoneModel {
    private $db;

    public function __construct($dbConnection = null) {
        // Use centralized database connection if none provided
        $this->db = $dbConnection ?: getDatabaseConnection();
    }

    // Add a new phone number for a user
    public function addPhoneNumber($userId, $phoneNumber, $isPrimary = false) {
        // If setting as primary, first unset any existing primary
        if ($isPrimary) {
            $this->clearPrimaryPhone($userId);
        }

        $stmt = $this->db->prepare("INSERT INTO user_phone_numbers 
                                   (user_id, phone_number, is_primary) 
                                   VALUES (?, ?, ?)");
        return $stmt->execute([$userId, $phoneNumber, $isPrimary ? 1 : 0]);
    }

    // Get all phone numbers for a user
    public function getPhoneNumbers($userId) {
        $stmt = $this->db->prepare("SELECT * FROM user_phone_numbers 
                                   WHERE user_id = ? ORDER BY is_primary DESC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get primary phone number for a user
    public function getPrimaryPhone($userId) {
        $stmt = $this->db->prepare("SELECT phone_number FROM user_phone_numbers 
                                   WHERE user_id = ? AND is_primary = 1 LIMIT 1");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['phone_number'] : null;
    }

    // Set a phone number as primary
    public function setPrimaryPhone($userId, $phoneId) {
        // First clear any existing primary
        $this->clearPrimaryPhone($userId);

        // Set the new primary
        $stmt = $this->db->prepare("UPDATE user_phone_numbers 
                                   SET is_primary = 1 
                                   WHERE phone_id = ? AND user_id = ?");
        return $stmt->execute([$phoneId, $userId]);
    }

    // Clear primary status for all phones of a user
    private function clearPrimaryPhone($userId) {
        $stmt = $this->db->prepare("UPDATE user_phone_numbers 
                                   SET is_primary = 0 
                                   WHERE user_id = ?");
        return $stmt->execute([$userId]);
    }

    // Delete a phone number
	public function deletePhoneNumber($userId, $phoneId) {
		try {
			$this->db->beginTransaction();

			// Ensure the phone belongs to the user and get its current state
			$selectStmt = $this->db->prepare(
				"SELECT phone_id, is_primary FROM user_phone_numbers WHERE phone_id = ? AND user_id = ? LIMIT 1"
			);
			$selectStmt->execute([$phoneId, $userId]);
			$phone = $selectStmt->fetch(PDO::FETCH_ASSOC);

			if (!$phone) {
				$this->db->rollBack();
				return false; // not found or not owned
			}

			// Prevent deleting the last phone number for the user
			$countStmt = $this->db->prepare("SELECT COUNT(*) FROM user_phone_numbers WHERE user_id = ?");
			$countStmt->execute([$userId]);
			$totalForUser = (int)$countStmt->fetchColumn();
			if ($totalForUser <= 1) {
				$this->db->rollBack();
				return false; // business rule: must have at least one number
			}

			// If deleting the primary, promote another number to primary
			if ((int)$phone['is_primary'] === 1) {
				// Clear current primary flag
				$clearStmt = $this->db->prepare("UPDATE user_phone_numbers SET is_primary = 0 WHERE user_id = ?");
				$clearStmt->execute([$userId]);

				// Choose another number (prefer verified, then earliest created)
				$promoteStmt = $this->db->prepare(
					"UPDATE user_phone_numbers 
					 SET is_primary = 1 
					 WHERE user_id = ? AND phone_id <> ? 
					 ORDER BY verified DESC, created_at ASC 
					 LIMIT 1"
				);
				$promoteStmt->execute([$userId, $phoneId]);
			}

			// Perform the delete
			$deleteStmt = $this->db->prepare("DELETE FROM user_phone_numbers WHERE phone_id = ? AND user_id = ?");
			$deleteStmt->execute([$phoneId, $userId]);

			$deletedRows = $deleteStmt->rowCount();
			if ($deletedRows > 0) {
				$this->db->commit();
				return true;
			}

			$this->db->rollBack();
			return false;
		} catch (Exception $e) {
			$this->db->rollBack();
			return false;
		}
	}

    // Verify if a phone number belongs to a user
    public function verifyPhoneOwnership($userId, $phoneId) {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM user_phone_numbers 
                                   WHERE phone_id = ? AND user_id = ?");
        $stmt->execute([$phoneId, $userId]);
        return $stmt->fetchColumn() > 0;
    }

    // Check if phone number exists (for any user)
    public function phoneNumberExists($phoneNumber) {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM user_phone_numbers 
                                   WHERE phone_number = ?");
        $stmt->execute([$phoneNumber]);
        return $stmt->fetchColumn() > 0;
    }
}
?>