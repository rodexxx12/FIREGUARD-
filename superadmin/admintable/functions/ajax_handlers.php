<?php
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/ajax_helpers.php';

if (is_ajax()) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {


            case 'update':
                if (isset($_POST['admin_id'])) {
                    $data = [
                        'full_name' => $_POST['full_name'] ?? '',
                        'email' => $_POST['email'] ?? '',
                        'username' => $_POST['username'] ?? '',
                        'contact_number' => $_POST['contact_number'] ?? '',
                        'role' => $_POST['role'] ?? '',
                        'status' => $_POST['status'] ?? '',
                        'admin_id' => $_POST['admin_id']
                    ];
                    $set = "full_name = :full_name, email = :email, username = :username, contact_number = :contact_number, role = :role, status = :status";
                    $stmt = $pdo->prepare("UPDATE admin SET $set WHERE admin_id = :admin_id");
                    $stmt->execute($data);
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Missing admin ID']);
                }
                exit;

            case 'get_user':
                if (isset($_POST['admin_id'])) {
                    $stmt = $pdo->prepare("SELECT admin_id, full_name, email, username, contact_number, role, status FROM admin WHERE admin_id = ?");
                    $stmt->execute([$_POST['admin_id']]);
                    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($admin) {
                        echo json_encode([
                            'success' => true,
                            'data' => $admin
                        ]);
                    } else {
                        echo json_encode([
                            'success' => false,
                            'message' => 'Admin not found.'
                        ]);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Missing admin ID']);
                }
                exit;
        }
    }
} 