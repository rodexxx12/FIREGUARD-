<?php
require_once 'database.php';
require_once 'validation.php';
require_once 'activity_logger.php';

class DeviceDisplay {
    private $pdo;
    
    public function __construct() {
        $this->pdo = Database::getConnection();
    }
    
    /**
     * Generate device information display HTML
     * @param string $device_number
     * @param string $serial_number
     * @param string $device_type
     * @param string $status
     * @return string HTML for device display
     */
    public static function generateDeviceDisplayHTML($device_number, $serial_number, $device_type = 'Fire Detection Device', $status = 'approved') {
        $statusClass = $status === 'approved' ? 'status-approved' : 'status-pending';
        $statusText = ucfirst($status);
        
        $html = '<div class="device-info-card">';
        $html .= '<div class="device-header">';
        $html .= '<h3>Device Information</h3>';
        $html .= '<span class="status-badge ' . $statusClass . '">' . $statusText . '</span>';
        $html .= '</div>';
        
        $html .= '<div class="device-details">';
        $html .= '<div class="detail-row">';
        $html .= '<label>Device Number:</label>';
        $html .= '<span class="device-number">' . htmlspecialchars($device_number) . '</span>';
        $html .= '</div>';
        
        $html .= '<div class="detail-row">';
        $html .= '<label>Serial Number:</label>';
        $html .= '<span class="serial-number">' . htmlspecialchars($serial_number) . '</span>';
        $html .= '</div>';
        
        $html .= '<div class="detail-row">';
        $html .= '<label>Device Type:</label>';
        $html .= '<span class="device-type">' . htmlspecialchars($device_type) . '</span>';
        $html .= '</div>';
        
        $html .= '<div class="detail-row">';
        $html .= '<label>Generated Date:</label>';
        $html .= '<span class="generated-date">' . date('Y-m-d H:i:s') . '</span>';
        $html .= '</div>';
        $html .= '</div>';
        
        $html .= '<div class="device-actions">';
        $html .= '<button class="btn btn-primary download-btn" onclick="downloadDeviceInfo(\'' . $device_number . '\', \'' . $serial_number . '\')">';
        $html .= '<i class="fa fa-download"></i> Download Info';
        $html .= '</button>';
        
        $html .= '<button class="btn btn-secondary print-btn" onclick="printDeviceInfo(\'' . $device_number . '\', \'' . $serial_number . '\')">';
        $html .= '<i class="fa fa-print"></i> Print';
        $html .= '</button>';
        
        $html .= '<button class="btn btn-info copy-btn" onclick="copyDeviceInfo(\'' . $device_number . '\', \'' . $serial_number . '\')">';
        $html .= '<i class="fa fa-copy"></i> Copy';
        $html .= '</button>';
        
        $html .= '<button class="btn btn-success csv-btn" onclick="downloadDeviceCSV(\'' . $device_number . '\', \'' . $serial_number . '\')">';
        $html .= '<i class="fa fa-file-csv"></i> Export CSV';
        $html .= '</button>';
        $html .= '</div>';
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Generate device information as text
     * @param string $device_number
     * @param string $serial_number
     * @param string $device_type
     * @param string $status
     * @return string
     */
    public static function generateDeviceText($device_number, $serial_number, $device_type = 'Fire Detection Device', $status = 'approved') {
        $text = "FIRE DETECTION SYSTEM - DEVICE INFORMATION\n";
        $text .= "==========================================\n\n";
        $text .= "Device Number: " . $device_number . "\n";
        $text .= "Serial Number: " . $serial_number . "\n";
        $text .= "Device Type: " . $device_type . "\n";
        $text .= "Status: " . ucfirst($status) . "\n";
        $text .= "Generated: " . date('Y-m-d H:i:s') . "\n\n";
        $text .= "This device is registered in the Fire Detection System.\n";
        $text .= "For technical support, contact the system administrator.\n";
        
        return $text;
    }
    
    /**
     * Generate device information as CSV with enhanced formatting
     * @param string $device_number
     * @param string $serial_number
     * @param string $device_type
     * @param string $status
     * @return string
     */
    public static function generateDeviceCSV($device_number, $serial_number, $device_type = 'Fire Detection Device', $status = 'approved') {
        $csv = "Device Number,Serial Number,Device Type,Status,Generated Date,System,Description\n";
        $csv .= "\"" . $device_number . "\",\"" . $serial_number . "\",\"" . $device_type . "\",\"" . ucfirst($status) . "\",\"" . date('Y-m-d H:i:s') . "\",\"Fire Detection System\",\"Fire detection device for safety monitoring\"\n";
        
        return $csv;
    }
    
    /**
     * Generate device information as JSON
     * @param string $device_number
     * @param string $serial_number
     * @param string $device_type
     * @param string $status
     * @return string
     */
    public static function generateDeviceJSON($device_number, $serial_number, $device_type = 'Fire Detection Device', $status = 'approved') {
        $data = [
            'device_number' => $device_number,
            'serial_number' => $serial_number,
            'device_type' => $device_type,
            'status' => $status,
            'generated_date' => date('Y-m-d H:i:s'),
            'system' => 'Fire Detection System'
        ];
        
        return json_encode($data, JSON_PRETTY_PRINT);
    }
    
    /**
     * Download device information in specified format
     * @param string $device_number
     * @param string $serial_number
     * @param string $format (txt, csv, json)
     * @param string $device_type
     * @param string $status
     */
    public static function downloadDeviceInfo($device_number, $serial_number, $format = 'txt', $device_type = 'Fire Detection Device', $status = 'approved') {
        $filename = 'device_' . $device_number . '_' . date('Y-m-d_H-i-s');
        
        switch ($format) {
            case 'csv':
                $content = self::generateDeviceCSV($device_number, $serial_number, $device_type, $status);
                $filename .= '.csv';
                $contentType = 'text/csv';
                break;
                
            case 'json':
                $content = self::generateDeviceJSON($device_number, $serial_number, $device_type, $status);
                $filename .= '.json';
                $contentType = 'application/json';
                break;
                
            case 'txt':
            default:
                $content = self::generateDeviceText($device_number, $serial_number, $device_type, $status);
                $filename .= '.txt';
                $contentType = 'text/plain';
                break;
        }
        
        // Set headers for download
        header('Content-Type: ' . $contentType);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($content));
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
        
        echo $content;
        exit;
    }
    
    /**
     * Get device by ID and return display data
     * @param int $device_id
     * @return array|false
     */
    public function getDeviceDisplayData($device_id) {
        $stmt = $this->pdo->prepare("SELECT device_number, serial_number, device_type, status FROM admin_devices WHERE admin_device_id = ?");
        $stmt->execute([$device_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get all devices for bulk download with enhanced information
     * @return array
     */
    public function getAllDevicesForDownload() {
        $stmt = $this->pdo->prepare("
            SELECT 
                device_number, 
                serial_number, 
                device_type, 
                status, 
                created_at,
                updated_at
            FROM admin_devices 
            ORDER BY created_at DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Generate bulk device list as CSV with enhanced formatting
     * @return string
     */
    public function generateBulkDeviceCSV() {
        $devices = $this->getAllDevicesForDownload();
        
        $csv = "Device Number,Serial Number,Device Type,Status,Created Date,Last Updated,System,Description\n";
        
        foreach ($devices as $device) {
            $csv .= "\"" . $device['device_number'] . "\",\"" . 
                    $device['serial_number'] . "\",\"" . 
                    $device['device_type'] . "\",\"" . 
                    ucfirst($device['status']) . "\",\"" . 
                    $device['created_at'] . "\",\"" . 
                    ($device['updated_at'] ? $device['updated_at'] : 'N/A') . "\",\"" . 
                    "Fire Detection System" . "\",\"" . 
                    "Fire detection device for safety monitoring" . "\"\n";
        }
        
        return $csv;
    }
    
    /**
     * Download bulk device list
     */
    public function downloadBulkDeviceList() {
        $content = $this->generateBulkDeviceCSV();
        $filename = 'all_devices_' . date('Y-m-d_H-i-s') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($content));
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
        
        echo $content;
        exit;
    }
    
    /**
     * Generate device statistics for CSV export
     * @return string
     */
    public function generateDeviceStatisticsCSV() {
        $stats = $this->getDeviceStatistics();
        
        $csv = "Statistic,Value,Description\n";
        $csv .= "\"Total Devices\",\"" . $stats['total'] . "\",\"Total number of devices in system\"\n";
        $csv .= "\"Approved Devices\",\"" . $stats['approved'] . "\",\"Number of approved devices\"\n";
        $csv .= "\"Pending Devices\",\"" . $stats['pending'] . "\",\"Number of pending devices\"\n";
        $csv .= "\"Deactivated Devices\",\"" . $stats['deactivated'] . "\",\"Number of deactivated devices\"\n";
        $csv .= "\"Latest Device Added\",\"" . $stats['latest_added'] . "\",\"Date of most recently added device\"\n";
        $csv .= "\"Report Generated\",\"" . date('Y-m-d H:i:s') . "\",\"Date and time of report generation\"\n";
        
        return $csv;
    }
    
    /**
     * Get device statistics
     * @return array
     */
    private function getDeviceStatistics() {
        $stats = [];
        
        // Total devices
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM admin_devices");
        $stmt->execute();
        $stats['total'] = $stmt->fetchColumn();
        
        // Approved devices
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM admin_devices WHERE status = 'approved'");
        $stmt->execute();
        $stats['approved'] = $stmt->fetchColumn();
        
        // Pending devices
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM admin_devices WHERE status = 'pending'");
        $stmt->execute();
        $stats['pending'] = $stmt->fetchColumn();
        
        // Deactivated devices
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM admin_devices WHERE status = 'deactivated'");
        $stmt->execute();
        $stats['deactivated'] = $stmt->fetchColumn();
        
        // Latest device added
        $stmt = $this->pdo->prepare("SELECT created_at FROM admin_devices ORDER BY created_at DESC LIMIT 1");
        $stmt->execute();
        $stats['latest_added'] = $stmt->fetchColumn() ?: 'N/A';
        
        return $stats;
    }
    
    /**
     * Download device statistics as CSV
     */
    public function downloadDeviceStatistics() {
        $content = $this->generateDeviceStatisticsCSV();
        $filename = 'device_statistics_' . date('Y-m-d_H-i-s') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($content));
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
        
        echo $content;
        exit;
    }
}
?> 