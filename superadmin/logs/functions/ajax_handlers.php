<?php
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/ajax_helpers.php';

$pdo = getDatabaseConnection();

if (is_ajax()) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {


            case 'filter_logs':
                $where_conditions = [];
                $params = [];

                if (!empty($_POST['event_type'])) {
                    $where_conditions[] = "event_type = ?";
                    $params[] = $_POST['event_type'];
                }

                if (!empty($_POST['log_level'])) {
                    $where_conditions[] = "log_level = ?";
                    $params[] = $_POST['log_level'];
                }

                if (!empty($_POST['location'])) {
                    $where_conditions[] = "location LIKE ?";
                    $params[] = '%' . $_POST['location'] . '%';
                }

                if (!empty($_POST['date_from'])) {
                    $where_conditions[] = "created_at >= ?";
                    $params[] = $_POST['date_from'] . ' 00:00:00';
                }

                if (!empty($_POST['date_to'])) {
                    $where_conditions[] = "created_at <= ?";
                    $params[] = $_POST['date_to'] . ' 23:59:59';
                }

                $sql = "SELECT * FROM system_logs";
                if (!empty($where_conditions)) {
                    $sql .= " WHERE " . implode(" AND ", $where_conditions);
                }
                $sql .= " ORDER BY created_at DESC";

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode([
                    'success' => true,
                    'data' => $logs
                ]);
                exit;
        }
    }
}
?> 