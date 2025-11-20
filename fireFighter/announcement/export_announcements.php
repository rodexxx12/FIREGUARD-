<?php
/**
 * Export Firefighter Announcements to CSV
 */

// Start session
session_start();

// Check if firefighter is logged in
if (!isset($_SESSION['firefighter_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit();
}

// Include the announcement helper functions
require_once 'php/announcement_helper.php';

$firefighterId = $_SESSION['firefighter_id'];

// Get filter parameters
$filterType = $_GET['filter'] ?? 'all';
$priorityFilter = $_GET['priority'] ?? 'all';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;

// Get announcements based on filter
$announcements = [];
switch ($filterType) {
    case 'specific':
        $announcements = getAllFirefighterAnnouncements($firefighterId, false, true);
        break;
    case 'recent':
        $allAnnouncements = getAllFirefighterAnnouncements($firefighterId, true, true);
        $announcements = array_slice($allAnnouncements, 0, $limit);
        break;
    case 'high_priority':
        $allAnnouncements = getAllFirefighterAnnouncements($firefighterId, true, true);
        $announcements = array_filter($allAnnouncements, function($a) {
            return $a['priority'] === 'high';
        });
        break;
    default:
        $announcements = getAllFirefighterAnnouncements($firefighterId, true, true);
        break;
}

// Apply priority filter if specified
if ($priorityFilter !== 'all') {
    $announcements = array_filter($announcements, function($a) use ($priorityFilter) {
        return $a['priority'] === $priorityFilter;
    });
}

// Get firefighter info for filename
try {
    $pdo = new PDO("mysql:host=localhost;dbname=firedb;charset=utf8mb4", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $pdo->prepare("SELECT name, badge_number FROM firefighters WHERE id = ?");
    $stmt->execute([$firefighterId]);
    $firefighter = $stmt->fetch(PDO::FETCH_ASSOC);
    $firefighterName = $firefighter ? $firefighter['name'] : 'Unknown';
    $badgeNumber = $firefighter ? $firefighter['badge_number'] : 'N/A';
} catch (PDOException $e) {
    $firefighterName = 'Unknown';
    $badgeNumber = 'N/A';
}

// Set headers for CSV download
$filename = "announcements_" . str_replace(' ', '_', $firefighterName) . "_" . date('Y-m-d_H-i-s') . ".csv";
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

// Create output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for proper Excel encoding
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Write CSV header
fputcsv($output, [
    'ID',
    'Title',
    'Content',
    'Priority',
    'Announcement Type',
    'Target Type',
    'Author',
    'Start Date',
    'End Date',
    'Created At',
    'Updated At',
    'Firefighter Name',
    'Badge Number',
    'Export Date'
]);

// Write data rows
foreach ($announcements as $announcement) {
    fputcsv($output, [
        $announcement['id'],
        $announcement['title'],
        str_replace(["\r", "\n"], ' ', $announcement['content']), // Remove line breaks
        $announcement['priority'],
        $announcement['announcement_type'] ?? 'regular',
        $announcement['target_description'] ?? $announcement['target_type'],
        $announcement['author_name'] ?? 'Unknown',
        $announcement['start_date'],
        $announcement['end_date'] ?? '',
        $announcement['created_at'],
        $announcement['updated_at'],
        $firefighterName,
        $badgeNumber,
        date('Y-m-d H:i:s')
    ]);
}

fclose($output);
exit();
?> 