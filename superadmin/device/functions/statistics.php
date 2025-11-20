<?php
require_once 'database.php';

class DeviceStatistics {
    private $pdo;
    
    public function __construct() {
        $this->pdo = Database::getConnection();
    }
    
    /**
     * Get device status statistics
     * @return array
     */
    public function getStatusStats() {
        try {
            $result = $this->pdo->query("SELECT status, COUNT(*) as count FROM admin_devices GROUP BY status")->fetchAll(PDO::FETCH_ASSOC);
            
            // Ensure we have all status types even if count is 0
            $statuses = ['approved', 'pending', 'deactivated'];
            $stats = [];
            
            foreach ($statuses as $status) {
                $found = false;
                foreach ($result as $row) {
                    if ($row['status'] === $status) {
                        $stats[] = $row;
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $stats[] = ['status' => $status, 'count' => 0];
                }
            }
            
            return $stats;
        } catch (Exception $e) {
            error_log("Error getting status stats: " . $e->getMessage());
            return [
                ['status' => 'approved', 'count' => 0],
                ['status' => 'pending', 'count' => 0],
                ['status' => 'deactivated', 'count' => 0]
            ];
        }
    }
    
    /**
     * Get total device count
     * @return int
     */
    public function getTotalDevices() {
        try {
            return $this->pdo->query("SELECT COUNT(*) as count FROM admin_devices")->fetchColumn();
        } catch (Exception $e) {
            error_log("Error getting total devices: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get recently added devices
     * @param int $limit
     * @return array
     */
    public function getRecentlyAdded($limit = 5) {
        try {
            $stmt = $this->pdo->prepare("SELECT device_number, serial_number, created_at FROM admin_devices ORDER BY created_at DESC LIMIT " . (int)$limit);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting recently added devices: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get device age distribution
     * @return array
     */
    public function getDeviceAgeStats() {
        try {
            return $this->pdo->query("
                SELECT 
                    CASE 
                        WHEN TIMESTAMPDIFF(MONTH, created_at, NOW()) < 1 THEN 'Less than 1 month'
                        WHEN TIMESTAMPDIFF(MONTH, created_at, NOW()) < 3 THEN '1-3 months'
                        WHEN TIMESTAMPDIFF(MONTH, created_at, NOW()) < 6 THEN '3-6 months'
                        WHEN TIMESTAMPDIFF(MONTH, created_at, NOW()) < 12 THEN '6-12 months'
                        ELSE 'More than 1 year'
                    END as age_group,
                    COUNT(*) as count
                FROM admin_devices
                GROUP BY age_group
                ORDER BY FIELD(age_group, 'Less than 1 month', '1-3 months', '3-6 months', '6-12 months', 'More than 1 year')
            ")->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting device age stats: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get monthly device additions
     * @param int $months
     * @return array
     */
    public function getMonthlyAdditions($months = 6) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    DATE_FORMAT(created_at, '%Y-%m') as month,
                    COUNT(*) as count 
                FROM admin_devices 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? MONTH)
                GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                ORDER BY month ASC
            ");
            $stmt->execute([$months]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting monthly additions: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get device status changes
     * @param int $months
     * @return array
     */
    public function getStatusChanges($months = 6) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    DATE_FORMAT(created_at, '%Y-%m') as month,
                    status,
                    COUNT(*) as count
                FROM admin_devices
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? MONTH)
                GROUP BY DATE_FORMAT(created_at, '%Y-%m'), status
                ORDER BY month ASC, status
            ");
            $stmt->execute([$months]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting status changes: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Prepare monthly chart data
     * @param int $months
     * @return array
     */
    public function prepareMonthlyChartData($months = 6) {
        try {
            $monthlyAdditions = $this->getMonthlyAdditions($months);
            
            $monthlyLabels = [];
            $monthlyData = [];
            $currentMonth = date('Y-m');
            $startMonth = date('Y-m', strtotime("-{$months} months"));

            // Generate all months in range
            $current = new DateTime($startMonth);
            $end = new DateTime($currentMonth);
            $interval = new DateInterval('P1M');
            $period = new DatePeriod($current, $interval, $end);

            foreach ($period as $dt) {
                $monthKey = $dt->format('Y-m');
                $monthlyLabels[] = $dt->format('M Y');
                $monthlyData[$monthKey] = 0;
            }

            // Fill in actual data
            foreach ($monthlyAdditions as $monthly) {
                $monthlyData[$monthly['month']] = (int)$monthly['count'];
            }

            return [
                'labels' => $monthlyLabels,
                'data' => array_values($monthlyData)
            ];
        } catch (Exception $e) {
            error_log("Error preparing monthly chart data: " . $e->getMessage());
            return [
                'labels' => [],
                'data' => []
            ];
        }
    }
    
    /**
     * Prepare status changes chart data
     * @param int $months
     * @return array
     */
    public function prepareStatusChartData($months = 6) {
        try {
            $statusChanges = $this->getStatusChanges($months);
            $monthlyData = $this->prepareMonthlyChartData($months);
            
            $statusChangeData = [];
            $statuses = ['approved', 'pending', 'deactivated'];

            // Initialize data structure
            foreach ($statuses as $status) {
                $statusChangeData[$status] = [];
                foreach ($monthlyData['labels'] as $month) {
                    $statusChangeData[$status][] = 0;
                }
            }

            // Fill in actual status data
            foreach ($statusChanges as $change) {
                $monthIndex = array_search(date('M Y', strtotime($change['month'])), $monthlyData['labels']);
                if ($monthIndex !== false && isset($statusChangeData[$change['status']])) {
                    $statusChangeData[$change['status']][$monthIndex] = (int)$change['count'];
                }
            }

            return $statusChangeData;
        } catch (Exception $e) {
            error_log("Error preparing status chart data: " . $e->getMessage());
            return [
                'approved' => [],
                'pending' => [],
                'deactivated' => []
            ];
        }
    }
    
    /**
     * Get all statistics data
     * @return array
     */
    public function getAllStatistics() {
        try {
            return [
                'statusStats' => $this->getStatusStats(),
                'totalDevices' => $this->getTotalDevices(),
                'recentlyAdded' => $this->getRecentlyAdded(),
                'deviceAgeStats' => $this->getDeviceAgeStats(),
                'monthlyChartData' => $this->prepareMonthlyChartData(),
                'statusChartData' => $this->prepareStatusChartData()
            ];
        } catch (Exception $e) {
            error_log("Error getting all statistics: " . $e->getMessage());
            return [
                'statusStats' => [
                    ['status' => 'approved', 'count' => 0],
                    ['status' => 'pending', 'count' => 0],
                    ['status' => 'deactivated', 'count' => 0]
                ],
                'totalDevices' => 0,
                'recentlyAdded' => [],
                'deviceAgeStats' => [],
                'monthlyChartData' => ['labels' => [], 'data' => []],
                'statusChartData' => ['approved' => [], 'pending' => [], 'deactivated' => []]
            ];
        }
    }
} 