<?php
/**
 * DataTables API Endpoint for Phone Numbers
 * Provides server-side processing for DataTables with filtering and search
 */

session_start();
require_once 'UserPhoneModel.php';
require_once '../db_connection.php';
require_once '../config/config.php';

header('Content-Type: application/json');

try {
    $db = getDatabaseConnection();
    
    // Initialize phone model with SMS credentials
    $phoneModel = new UserPhoneModel($db, $config['api_key'], $config['device'], $config['url']);
    
    // Get current user ID (from session)
    $userId = $_SESSION['user_id'] ?? null;
    
    if (!$userId) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit();
    }
    
    // Get DataTables parameters
    $draw = intval($_GET['draw'] ?? 1);
    $start = intval($_GET['start'] ?? 0);
    $length = intval($_GET['length'] ?? 10);
    $searchValue = $_GET['search']['value'] ?? '';
    $orderColumn = intval($_GET['order'][0]['column'] ?? 0);
    $orderDir = $_GET['order'][0]['dir'] ?? 'desc';
    
    // Column mapping for ordering
    $columns = [
        0 => 'phone_number',
        1 => 'label',
        2 => 'verified',
        3 => 'created_at',
        4 => 'phone_id' // Actions column - order by ID
    ];
    
    $orderBy = $columns[$orderColumn] ?? 'created_at';
    $orderDirection = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
    
    // Get user's phone numbers
    $phoneNumbers = $phoneModel->getPhoneNumbers($userId);
    
    // Apply search filter
    if (!empty($searchValue)) {
        $phoneNumbers = array_filter($phoneNumbers, function($phone) use ($searchValue) {
            $searchLower = strtolower($searchValue);
            return (
                strpos(strtolower($phone['phone_number']), $searchLower) !== false ||
                strpos(strtolower($phone['label'] ?? ''), $searchLower) !== false ||
                strpos(strtolower($phone['verified'] ? 'verified' : 'unverified'), $searchLower) !== false ||
                strpos(strtolower($phone['is_primary'] ? 'primary' : 'not primary'), $searchLower) !== false
            );
        });
    }
    
    // Apply custom filters
    $statusFilter = $_GET['status_filter'] ?? '';
    $primaryFilter = $_GET['primary_filter'] ?? '';
    $labelFilter = $_GET['label_filter'] ?? '';
    $dateRangeFilter = $_GET['date_range_filter'] ?? '';
    
    if ($statusFilter && $statusFilter !== 'all') {
        $phoneNumbers = array_filter($phoneNumbers, function($phone) use ($statusFilter) {
            if ($statusFilter === 'verified') {
                return $phone['verified'] == 1;
            } elseif ($statusFilter === 'unverified') {
                return $phone['verified'] == 0;
            }
            return true;
        });
    }
    
    if ($primaryFilter && $primaryFilter !== 'all') {
        $phoneNumbers = array_filter($phoneNumbers, function($phone) use ($primaryFilter) {
            if ($primaryFilter === 'yes') {
                return $phone['is_primary'] == 1;
            } elseif ($primaryFilter === 'no') {
                return $phone['is_primary'] == 0;
            }
            return true;
        });
    }
    
    if ($labelFilter) {
        $phoneNumbers = array_filter($phoneNumbers, function($phone) use ($labelFilter) {
            return strpos(strtolower($phone['label'] ?? ''), strtolower($labelFilter)) !== false;
        });
    }
    
    if ($dateRangeFilter && $dateRangeFilter !== 'all') {
        $phoneNumbers = array_filter($phoneNumbers, function($phone) use ($dateRangeFilter) {
            $createdDate = new DateTime($phone['created_at']);
            $now = new DateTime();
            
            switch ($dateRangeFilter) {
                case 'today':
                    return $createdDate->format('Y-m-d') === $now->format('Y-m-d');
                case 'week':
                    $weekAgo = clone $now;
                    $weekAgo->modify('-1 week');
                    return $createdDate >= $weekAgo;
                case 'month':
                    $monthAgo = clone $now;
                    $monthAgo->modify('-1 month');
                    return $createdDate >= $monthAgo;
                case 'year':
                    $yearAgo = clone $now;
                    $yearAgo->modify('-1 year');
                    return $createdDate >= $yearAgo;
                default:
                    return true;
            }
        });
    }
    
    // Sort the filtered results
    usort($phoneNumbers, function($a, $b) use ($orderBy, $orderDirection) {
        $valueA = $a[$orderBy] ?? '';
        $valueB = $b[$orderBy] ?? '';
        
        if ($orderBy === 'verified' || $orderBy === 'is_primary') {
            $valueA = (int)$valueA;
            $valueB = (int)$valueB;
        }
        
        if ($orderDirection === 'ASC') {
            return $valueA <=> $valueB;
        } else {
            return $valueB <=> $valueA;
        }
    });
    
    // Get total records count
    $totalRecords = count($phoneNumbers);
    
    // Apply pagination
    $filteredData = array_slice($phoneNumbers, $start, $length);
    
    // Format data for DataTables
    $data = [];
    foreach ($filteredData as $phone) {
        $data[] = [
            'phone_id' => $phone['phone_id'],
            'phone_number' => $phone['phone_number'],
            'label' => $phone['label'] ?? '',
            'verified' => (bool)$phone['verified'],
            'is_primary' => (bool)$phone['is_primary'],
            'created_at' => $phone['created_at'],
            'formatted_number' => '+63' . substr($phone['phone_number'], 1),
            'status_text' => $phone['verified'] ? 'verified' : 'unverified',
            'primary_text' => $phone['is_primary'] ? 'primary' : 'not primary'
        ];
    }
    
    // Prepare response
    $response = [
        'draw' => $draw,
        'recordsTotal' => $totalRecords,
        'recordsFiltered' => $totalRecords,
        'data' => $data,
        'meta' => [
            'user_id' => $userId,
            'total_numbers' => $totalRecords,
            'verified_count' => count(array_filter($phoneNumbers, function($p) { return $p['verified']; })),
            'unverified_count' => count(array_filter($phoneNumbers, function($p) { return !$p['verified']; })),
            'primary_count' => count(array_filter($phoneNumbers, function($p) { return $p['is_primary']; })),
            'last_updated' => date('Y-m-d H:i:s')
        ]
    ];
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error: ' . $e->getMessage(),
        'draw' => intval($_GET['draw'] ?? 1),
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => []
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error: ' . $e->getMessage(),
        'draw' => intval($_GET['draw'] ?? 1),
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => []
    ]);
}
?>
