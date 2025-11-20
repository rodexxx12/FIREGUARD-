<?php 
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Determine the absolute path to the backup directory
$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
$current_script = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : (isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : '/');
// Normalize to forward slashes
$current_script = str_replace('\\\
','/', $current_script);

// Compute the web path to the production directory regardless of whether there's a /DEFENDED prefix
$production_pos = strpos($current_script, '/production/');
if ($production_pos === false) {
    // Fallback: try request URI
    $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
    $request_uri = str_replace('\\', '/', $request_uri);
    $production_pos = strpos($request_uri, '/production/');
    $base_path = $production_pos !== false ? substr($request_uri, 0, $production_pos + strlen('/production/')) : '/production/';
} else {
    $base_path = substr($current_script, 0, $production_pos + strlen('/production/'));
}

// Ensure single slashes and no double //
$base_path = '/' . ltrim($base_path, '/');
if (substr($base_path, -1) !== '/') { $base_path .= '/'; }

// Build absolute URL to backup endpoint
$backup_url = rtrim($base_url, '/') . $base_path . 'backup/create_backup.php';
// Build absolute URL to import endpoint
$import_url = rtrim($base_url, '/') . $base_path . 'backup/import_backup.php';

// Profile data initialization
$profile_image_url = '../../images/profile1.jpg'; // Default profile image
$user_role = 'Guest';
$user_name = 'Guest';
$user_email = '';
$is_logged_in = false;

// Include database connection
require_once __DIR__ . '/../db/db.php';

// Check if admin is logged in
if (isset($_SESSION['admin_id']) && isset($_SESSION['admin_username'])) {
    $is_logged_in = true;
    $user_role = 'Admin';
    $user_name = $_SESSION['admin_full_name'] ?? $_SESSION['admin_username'];
    $user_email = $_SESSION['admin_email'] ?? '';
    
    // Set profile image from session if available
    if (isset($_SESSION['admin_profile_image']) && !empty($_SESSION['admin_profile_image'])) {
        $profile_image_url = $_SESSION['admin_profile_image'];
    }
}

// Get database connection
try {
    $pdo = getDatabaseConnection();
} catch (Exception $e) {
    error_log("Database connection failed: " . $e->getMessage());
    // Set default values to prevent errors
    $admin_notifications = [];
    $admin_notification_count = 0;
    $is_emergency = false;
}

// Initialize admin notification arrays
$admin_notifications = [];
$admin_notification_count = 0;
$is_emergency = false;

// Only proceed with database queries if connection is available
if (isset($pdo) && $pdo) {
    // SIMPLIFIED: Check latest fire_data status for emergency
    try {
        $stmt = $pdo->query("
            SELECT id, status, timestamp, building_id, smoke, temp, heat, flame_detected 
            FROM fire_data 
            ORDER BY id DESC 
            LIMIT 1
        ");
        $latest_record = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($latest_record && strtoupper(trim($latest_record['status'])) === 'EMERGENCY') {
            $is_emergency = true;
            error_log("EMERGENCY detected in latest fire_data record: ID " . ($latest_record['id'] ?? 'unknown'));
        }
    } catch (PDOException $e) {
        error_log("Error checking latest fire_data status: " . $e->getMessage());
    }
}

    // Add notification for latest responses (show 3 most recent)
    try {
        $stmt = $pdo->prepare("
            SELECT r.response_type, r.notes, r.responded_by, r.timestamp, b.building_name
            FROM responses r
            LEFT JOIN fire_data fd ON r.fire_data_id = fd.id
            LEFT JOIN buildings b ON r.building_id = b.id
            ORDER BY r.timestamp DESC
            LIMIT 3
        ");
        $stmt->execute();
        $latest_responses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($latest_responses as $resp) {
            $title = 'Latest Response: ' . htmlspecialchars($resp['response_type']);
            $building = $resp['building_name'] ? 'Building: ' . htmlspecialchars($resp['building_name']) . '. ' : '';
            $notes = $resp['notes'] ? 'Notes: ' . htmlspecialchars($resp['notes']) . '. ' : '';
            $message = $building . $notes . 'By: ' . htmlspecialchars($resp['responded_by']) . ' @ ' . date('M d, Y H:i', strtotime($resp['timestamp']));
            $admin_notifications[] = [
                'type' => 'info',
                'title' => $title,
                'message' => $message,
                'icon' => 'fa-fire-extinguisher',
                'priority' => 'low'
            ];
            $admin_notification_count++;
        }
    } catch (PDOException $e) {
        error_log("Error fetching latest responses: " . $e->getMessage());
    }

    // 1. Check for critical fire data (high smoke, temp, or flame detected)
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as critical_count 
            FROM fire_data 
            WHERE (smoke > 80 OR temp > 100 OR heat > 80 OR flame_detected = 1)
            AND timestamp > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            AND notified = 0
        ");
        $stmt->execute();
        $critical_fire = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($critical_fire['critical_count'] > 0) {
            $admin_notifications[] = [
                'type' => 'danger',
                'title' => 'Critical Fire Alerts',
                'message' => "{$critical_fire['critical_count']} critical fire alert" . ($critical_fire['critical_count'] > 1 ? 's' : '') . " detected in the last hour requiring immediate attention.",
                'icon' => 'fa-fire',
                'priority' => 'high'
            ];
            $admin_notification_count++;
        }
    } catch (PDOException $e) {
        error_log("Error checking critical fire data: " . $e->getMessage());
    }

    // 2. Check for new fire data without responses
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as unresponded_count 
            FROM fire_data fd 
            LEFT JOIN responses r ON fd.id = r.fire_data_id 
            WHERE fd.timestamp > DATE_SUB(NOW(), INTERVAL 30 MINUTE)
            AND r.id IS NULL
        ");
        $stmt->execute();
        $unresponded_fire = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($unresponded_fire['unresponded_count'] > 0) {
            $admin_notifications[] = [
                'type' => 'warning',
                'title' => 'Unresponded Fire Incidents',
                'message' => "{$unresponded_fire['unresponded_count']} fire incident" . ($unresponded_fire['unresponded_count'] > 1 ? 's' : '') . " in the last 30 minutes without response.",
                'icon' => 'fa-exclamation-triangle',
                'priority' => 'high'
            ];
            $admin_notification_count++;
        }
    } catch (PDOException $e) {
        error_log("Error checking unresponded fire data: " . $e->getMessage());
    }

    // 3. Check for water level alerts
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as water_alert_count 
            FROM water_alerts 
            WHERE alert_type IN ('empty', 'low') 
            AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
            AND dismissed = 0
        ");
        $stmt->execute();
        $water_alerts = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($water_alerts['water_alert_count'] > 0) {
            $admin_notifications[] = [
                'type' => 'warning',
                'title' => 'Water Level Alerts',
                'message' => "{$water_alerts['water_alert_count']} water level alert" . ($water_alerts['water_alert_count'] > 1 ? 's' : '') . " (empty/low) in the last 24 hours.",
                'icon' => 'fa-tint',
                'priority' => 'medium'
            ];
            $admin_notification_count++;
        }
    } catch (PDOException $e) {
        error_log("Error checking water alerts: " . $e->getMessage());
    }

    // 4. Check for new user registrations (pending approval)
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as new_users_count 
            FROM users 
            WHERE status = 'Inactive' 
            AND registration_date > DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $stmt->execute();
        $new_users = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($new_users['new_users_count'] > 0) {
            $admin_notifications[] = [
                'type' => 'info',
                'title' => 'New User Registrations',
                'message' => "{$new_users['new_users_count']} new user registration" . ($new_users['new_users_count'] > 1 ? 's' : '') . " pending approval in the last 24 hours.",
                'icon' => 'fa-user-plus',
                'priority' => 'low'
            ];
            $admin_notification_count++;
        }
    } catch (PDOException $e) {
        error_log("Error checking new users: " . $e->getMessage());
    }

    // 5. Check for buildings without recent inspections
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as inspection_due_count 
            FROM buildings 
            WHERE last_inspected IS NULL 
            OR last_inspected < DATE_SUB(NOW(), INTERVAL 6 MONTH)
        ");
        $stmt->execute();
        $inspection_due = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($inspection_due['inspection_due_count'] > 0) {
            $admin_notifications[] = [
                'type' => 'warning',
                'title' => 'Building Inspections Due',
                'message' => "{$inspection_due['inspection_due_count']} building" . ($inspection_due['inspection_due_count'] > 1 ? 's' : '') . " " . ($inspection_due['inspection_due_count'] > 1 ? 'need' : 'needs') . " fire safety inspection.",
                'icon' => 'fa-building',
                'priority' => 'medium'
            ];
            $admin_notification_count++;
        }
    } catch (PDOException $e) {
        error_log("Error checking building inspections: " . $e->getMessage());
    }

    // 6. Check for firefighter response times
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as slow_response_count 
            FROM fire_data fd 
            JOIN responses r ON fd.id = r.fire_data_id 
            WHERE TIMESTAMPDIFF(MINUTE, fd.timestamp, r.timestamp) > 10
            AND r.timestamp > DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $stmt->execute();
        $slow_responses = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($slow_responses['slow_response_count'] > 0) {
            $admin_notifications[] = [
                'type' => 'warning',
                'title' => 'Slow Firefighter Responses',
                'message' => "{$slow_responses['slow_response_count']} incident" . ($slow_responses['slow_response_count'] > 1 ? 's' : '') . " with response time over 10 minutes in the last 24 hours.",
                'icon' => 'fa-clock-o',
                'priority' => 'medium'
            ];
            $admin_notification_count++;
        }
    } catch (PDOException $e) {
        error_log("Error checking response times: " . $e->getMessage());
    }

    // 7. Check for unacknowledged fire data
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as unacknowledged_count 
            FROM fire_data fd 
            LEFT JOIN acknowledgments a ON fd.id = a.fire_data_id 
            WHERE fd.timestamp > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            AND a.id IS NULL
        ");
        $stmt->execute();
        $unacknowledged = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($unacknowledged['unacknowledged_count'] > 0) {
            $admin_notifications[] = [
                'type' => 'info',
                'title' => 'Unacknowledged Fire Data',
                'message' => "{$unacknowledged['unacknowledged_count']} fire data entry" . ($unacknowledged['unacknowledged_count'] > 1 ? 'ies' : 'y') . " in the last hour without acknowledgment.",
                'icon' => 'fa-check-circle',
                'priority' => 'low'
            ];
            $admin_notification_count++;
        }
    } catch (PDOException $e) {
        error_log("Error checking unacknowledged data: " . $e->getMessage());
    }

    // 8. Check for offline devices
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as offline_count 
            FROM devices d 
            WHERE d.is_active = 1 
            AND d.device_id NOT IN (
                SELECT DISTINCT device_id 
                FROM fire_data 
                WHERE timestamp > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            )
        ");
        $stmt->execute();
        $offline_devices = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($offline_devices['offline_count'] > 0) {
            $admin_notifications[] = [
                'type' => 'danger',
                'title' => 'Offline Devices',
                'message' => "{$offline_devices['offline_count']} device" . ($offline_devices['offline_count'] > 1 ? 's' : '') . " across the system " . ($offline_devices['offline_count'] > 1 ? 'are' : 'is') . " offline or not sending data.",
                'icon' => 'fa-wifi',
                'priority' => 'high'
            ];
            $admin_notification_count++;
        }
    } catch (PDOException $e) {
        error_log("Error checking offline devices: " . $e->getMessage());
    }

    // 10. Check for unassigned devices
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as unassigned_count 
            FROM devices 
            WHERE building_id IS NULL AND is_active = 1
        ");
        $stmt->execute();
        $unassigned_devices = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($unassigned_devices['unassigned_count'] > 0) {
            $admin_notifications[] = [
                'type' => 'warning',
                'title' => 'Unassigned Devices',
                'message' => "{$unassigned_devices['unassigned_count']} device" . ($unassigned_devices['unassigned_count'] > 1 ? 's' : '') . " " . ($unassigned_devices['unassigned_count'] > 1 ? 'are' : 'is') . " not assigned to any building.",
                'icon' => 'fa-tablet',
                'priority' => 'medium'
            ];
            $admin_notification_count++;
        }
    } catch (PDOException $e) {
        error_log("Error checking unassigned devices: " . $e->getMessage());
    }

    // 11. Check for inactive firefighters
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as inactive_count 
            FROM firefighters 
            WHERE status = 'inactive' AND is_active = 1
        ");
        $stmt->execute();
        $inactive_firefighters = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($inactive_firefighters['inactive_count'] > 0) {
            $admin_notifications[] = [
                'type' => 'warning',
                'title' => 'Inactive Firefighters',
                'message' => "{$inactive_firefighters['inactive_count']} firefighter" . ($inactive_firefighters['inactive_count'] > 1 ? 's' : '') . " " . ($inactive_firefighters['inactive_count'] > 1 ? 'are' : 'is') . " currently inactive.",
                'icon' => 'fa-user-shield',
                'priority' => 'medium'
            ];
            $admin_notification_count++;
        }
    } catch (PDOException $e) {
        error_log("Error checking inactive firefighters: " . $e->getMessage());
    }

    // 12. Check for system maintenance due
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as maintenance_count 
            FROM system_maintenance 
            WHERE scheduled_date <= DATE_ADD(NOW(), INTERVAL 7 DAY) 
            AND status = 'scheduled'
        ");
        $stmt->execute();
        $maintenance_due = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($maintenance_due['maintenance_count'] > 0) {
            $admin_notifications[] = [
                'type' => 'info',
                'title' => 'System Maintenance Due',
                'message' => "{$maintenance_due['maintenance_count']} system maintenance task" . ($maintenance_due['maintenance_count'] > 1 ? 's' : '') . " " . ($maintenance_due['maintenance_count'] > 1 ? 'are' : 'is') . " scheduled within 7 days.",
                'icon' => 'fa-tools',
                'priority' => 'low'
            ];
            $admin_notification_count++;
        }
    } catch (PDOException $e) {
        error_log("Error checking maintenance: " . $e->getMessage());
    }

// Notification count stored in variable (no session)
?>

<div class="top_nav">
  <div class="nav_menu">
    <div class="nav toggle">
      <a id="menu_toggle"><i class="fa fa-bars"></i></a>
    </div>
    <nav class="nav navbar-nav">
      <ul class="navbar-right">
        <!-- User Profile Dropdown (now first) -->
        <li class="nav-item dropdown open" style="padding-left: 15px;">
          <a href="javascript:;" class="user-profile dropdown-toggle" aria-haspopup="true" id="navbarDropdown" data-toggle="dropdown" aria-expanded="false">
            <span class="role-badge <?php echo $is_logged_in ? 'role-admin' : 'role-guest'; ?>">
              <i class="fa fa-user-circle nav-user-icon"></i><?php echo htmlspecialchars($user_role); ?>
            </span>
          </a>
          <div class="dropdown-menu dropdown-usermenu user-menu-panel pull-right" aria-labelledby="navbarDropdown">
            <div class="user-menu">
              <div class="user-menu-header">
                <div class="user-menu-meta">
                  <div class="user-menu-name"><?php echo htmlspecialchars($user_name); ?></div>
                  <div class="user-menu-role">Signed in as <strong><?php echo htmlspecialchars($user_role); ?></strong></div>
                  <?php if ($is_logged_in && !empty($user_email)): ?>
                    <div class="user-menu-email"><?php echo htmlspecialchars($user_email); ?></div>
                  <?php endif; ?>
                </div>
              </div>
              <div class="user-menu-actions">
                <?php if ($is_logged_in): ?>
                  <a class="menu-btn menu-btn--primary" href="../../profile/php/main.php">
                    <span class="menu-btn-left">
                      <i class="fa fa-id-badge"></i>
                      <span class="menu-btn-text">Profile</span>
                    </span>
                    <i class="fa fa-chevron-right menu-btn-chevron"></i>
                  </a>
                <?php else: ?>
                  <a class="menu-btn menu-btn--primary" href="../../login/login.php">
                    <span class="menu-btn-left">
                      <i class="fa fa-sign-in"></i>
                      <span class="menu-btn-text">Login</span>
                    </span>
                    <i class="fa fa-chevron-right menu-btn-chevron"></i>
                  </a>
                <?php endif; ?>
                <?php if ($is_logged_in): ?>
                  <a class="menu-btn menu-btn--danger" href="javascript:;" id="logoutBtn">
                    <span class="menu-btn-left">
                      <i class="fa fa-sign-out"></i>
                      <span class="menu-btn-text">Log Out</span>
                    </span>
                    <i class="fa fa-chevron-right menu-btn-chevron"></i>
                  </a>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </li>
        <!-- Admin Notification Dropdown (now after profile) -->
        <li class="nav-item dropdown open" style="padding-left: 15px;">
          <a href="javascript:;" class="user-profile dropdown-toggle" aria-haspopup="true" id="notificationDropdown" data-toggle="dropdown" aria-expanded="false">
            <i class="fa fa-bell"></i>
            <span class="badge bg-red" id="notificationCount" style="position: absolute; top: -5px; right: -5px; display: <?php echo $admin_notification_count > 0 ? 'block' : 'none'; ?>;">
              <?php echo $admin_notification_count; ?>
            </span>
          </a>
          <div class="dropdown-menu dropdown-usermenu pull-right" aria-labelledby="notificationDropdown" style="width: 350px; max-height: 400px; overflow-y: auto; background-color: white;">
            <div class="dropdown-header">
              <h6><i class="fa fa-bell"></i> Admin Notifications</h6>
              <a href="javascript:;" id="markAllRead" style="font-size: 10px; color: #007bff;">Mark all as read</a>
            </div>
            <div class="dropdown-divider"></div>
            <div id="notificationList">
              <?php if (empty($admin_notifications)): ?>
                <div class="dropdown-item text-center text-muted">
                  <i class="fa fa-check-circle"></i> All systems operational
                </div>
              <?php else: ?>
                <?php foreach ($admin_notifications as $notification): ?>
                  <div class="dropdown-item notification-item notification-<?php echo $notification['type']; ?>" data-id="<?php echo uniqid(); ?>" data-priority="<?php echo $notification['priority']; ?>">
                    <div class="d-flex">
                      <div class="notification-icon-wrapper">
                        <i class="fa <?php echo $notification['icon']; ?> notification-icon-<?php echo $notification['type']; ?>"></i>
                      </div>
                      <div class="flex-grow-1">
                        <div class="notification-title"><?php echo htmlspecialchars($notification['title']); ?></div>
                        <div class="notification-message"><?php echo htmlspecialchars($notification['message']); ?></div>
                        <div class="notification-actions">
                          <small class="text-muted"><?php echo ucfirst($notification['priority']); ?> priority</small>
                        </div>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
            <div class="dropdown-divider"></div>
            <div class="dropdown-item text-center">
              <a href="../notifications/" style="color: #007bff; text-decoration: none;">
                <i class="fa fa-eye"></i> View All System Notifications
              </a>
            </div>
          </div>
        </li>
        <!-- Database Backup Button -->
        <?php if ($is_logged_in): ?>
        <li class="nav-item dropdown open" style="padding-left: 15px;">
          <a href="javascript:;" class="user-profile dropdown-toggle" aria-haspopup="true" id="backupDropdown" data-toggle="dropdown" aria-expanded="false">
            <i class="fa fa-database"></i>
          </a>
          <div class="dropdown-menu dropdown-usermenu pull-right" aria-labelledby="backupDropdown" style="min-width: 250px; background-color: white;">
            <div class="dropdown-header">
              <h6><i class="fa fa-database"></i> Database Backup</h6>
            </div>
            <div class="dropdown-divider"></div>
            <div class="dropdown-item" style="padding: 12px;">
              <button class="btn btn-success btn-sm btn-block" id="backupAllBtn" style="text-align: left;">
                <i class="fa fa-database"></i> Backup All
              </button>
            </div>
          </div>
        </li>
        <?php endif; ?>
        <!-- Database Import Button -->
        <?php if ($is_logged_in): ?>
        <li class="nav-item dropdown open" style="padding-left: 15px;">
          <a href="javascript:;" class="user-profile dropdown-toggle" aria-haspopup="true" id="importDropdown" data-toggle="dropdown" aria-expanded="false">
            <i class="fa fa-upload"></i>
          </a>
          <div class="dropdown-menu dropdown-usermenu pull-right" aria-labelledby="importDropdown" style="min-width: 250px; background-color: white;">
            <div class="dropdown-header">
              <h6><i class="fa fa-upload"></i> Database Import</h6>
            </div>
            <div class="dropdown-divider"></div>
            <div class="dropdown-item" style="padding: 12px;">
              <button class="btn btn-primary btn-sm btn-block" id="importBackupBtn" style="text-align: left;">
                <i class="fa fa-upload"></i> Import Backup
              </button>
            </div>
          </div>
        </li>
        <?php endif; ?>
        <!-- Speaker Icon -->
        <li class="nav-item" style="padding-left: 15px;">
          <a href="javascript:;" class="user-profile sound-icon <?php echo $is_emergency ? 'emergency-active' : 'normal-status'; ?>" title="<?php echo $is_emergency ? 'EMERGENCY - Alarm Active' : 'Normal Status - No Emergency'; ?>" id="speakerIcon">
            <i class="fa fa-volume-up"></i>
          </a>
        </li>
      </ul>
    </nav>
  </div>
</div>

<!-- SweetAlert JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Global error handler
    window.addEventListener('error', function(e) {
        console.error('JavaScript Error:', e.error);
        console.error('Error details:', e.message, 'at', e.filename, ':', e.lineno);
    });
    
    // Track user interaction for audio autoplay
    let userInteractionEvents = ['click', 'keydown', 'touchstart', 'mousedown'];
    let interactionDetected = false;
    
    userInteractionEvents.forEach(event => {
        document.addEventListener(event, function() {
            if (!interactionDetected) {
                interactionDetected = true;
                window.userHasInteracted = true;
                console.log('✅ User interaction detected - audio playback enabled');
                
                // If there's an emergency and alarm should be playing, start it now
                if (isEmergency && (!window.emergencyAlarm || window.emergencyAlarm.paused)) {
                    console.log('🚨 User interacted during emergency - starting alarm immediately');
                    startEmergencyAlarmSimple();
                }
            }
        }, { once: true });
    });
    
    // Admin notification system
    let notificationCount = <?php echo $admin_notification_count; ?>;
    let notifications = <?php echo json_encode($admin_notifications); ?>;
    const isEmergency = <?php echo $is_emergency ? 'true' : 'false'; ?>;
    
    // Set up real-time notification checking
    // setInterval(checkNewNotifications, 30000); // Check every 30 seconds
    
    /*
    function checkNewNotifications() {
        fetch('../notifications/check_admin_notifications.php', {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'Cache-Control': 'no-cache'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.newCount !== notificationCount) {
                notificationCount = data.newCount;
                updateNotificationBadge();
                if (data.newNotifications && data.newNotifications.length > 0) {
                    showNotificationToast(data.newNotifications[0]);
                }
                location.reload(); // Reload to get updated notifications
            }
        })
        .catch(error => {
            console.error('Error checking notifications:', error);
        });
    }
    */
    
    function updateNotificationBadge() {
        const badge = document.getElementById('notificationCount');
        if (notificationCount > 0) {
            badge.textContent = notificationCount;
            badge.style.display = 'block';
            
            // Update badge color based on priority
            if (notificationCount > 5) {
                badge.className = 'badge bg-danger';
            } else if (notificationCount > 2) {
                badge.className = 'badge bg-warning';
            } else {
                badge.className = 'badge bg-info';
            }
        } else {
            badge.style.display = 'none';
        }
    }
    
    function showNotificationToast(notification) {
        if (notification) {
            Swal.fire({
                title: notification.title,
                text: notification.message,
                icon: notification.type === 'danger' ? 'error' : notification.type === 'warning' ? 'warning' : 'info',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 5000,
                timerProgressBar: true
            });
        }
    }
    
    // Mark all as read functionality
    const markAllReadBtn = document.getElementById('markAllRead');
    if (markAllReadBtn) {
        markAllReadBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            Swal.fire({
                title: 'Mark All as Read',
                text: 'Are you sure you want to mark all notifications as read?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#007bff',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, mark all read',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Here you would typically make an AJAX call to mark all as read
                    // For now, we'll just hide the notifications
                    const notificationList = document.getElementById('notificationList');
                    if (notificationList) {
                        notificationList.innerHTML = `
                            <div class="dropdown-item text-center text-muted">
                                <i class="fa fa-check-circle"></i> All notifications marked as read
                            </div>
                        `;
                    }
                    notificationCount = 0;
                    updateNotificationBadge();
                }
            });
        });
    }

    // Enhanced logout functionality with session management
    const clearAppData = () => {
        // Preserve captcha verification flag before clearing
        const captchaVerified = sessionStorage.getItem('captchaVerified');
        
        // Clear localStorage and sessionStorage
        localStorage.clear();
        sessionStorage.clear();
        
        // Restore captcha verification if it existed
        if (captchaVerified) {
            sessionStorage.setItem('captchaVerified', captchaVerified);
        }
        
        // Clear all cookies
        document.cookie.split(";").forEach(cookie => {
            const eqPos = cookie.indexOf("=");
            const name = eqPos > -1 ? cookie.substr(0, eqPos) : cookie;
            document.cookie = name + "=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/";
        });
        
        // Clear cache and force reload
        if ('caches' in window) {
            caches.keys().then(names => {
                names.forEach(name => caches.delete(name));
            });
        }
    };
    
    // Function to logout via AJAX to destroy server session
    const logoutViaServer = () => {
        return fetch('../../login/logout.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            credentials: 'same-origin'
        }).catch(error => {
            console.log('Logout request failed:', error);
            // Continue with client-side cleanup even if server request fails
        });
    };

    // Enhanced logout functionality with session management
    const logoutBtn = document.getElementById('logoutBtn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            Swal.fire({
                title: 'Confirm Logout',
                html: 'Are you sure you want to log out?<br><small>This will end your session and clear your data.</small>',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: '<i class="fa fa-sign-out"></i> Log Out',
                cancelButtonText: 'Cancel',
                reverseButtons: true,
                backdrop: 'rgba(0,0,0,0.7)',
                allowOutsideClick: false
            }).then((result) => {
                if (result.isConfirmed) {
                    // Show loading state
                    Swal.fire({
                        title: 'Logging out...',
                        text: 'Please wait while we end your session.',
                        icon: 'info',
                        allowOutsideClick: false,
                        showConfirmButton: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    
                    // First try to logout via server to destroy session
                    logoutViaServer().then(() => {
                        // Clear client-side data
                        clearAppData();
                        
                        // Final logout confirmation
                        Swal.fire({
                            title: 'Logged Out Successfully',
                            html: 'Your session has been ended.<br>Redirecting to home page...',
                            icon: 'success',
                            timer: 2000,
                            timerProgressBar: true,
                            showConfirmButton: false,
                            allowOutsideClick: false,
                            willClose: () => {
                                // Redirect to home page
                                window.location.replace('../../../index.php');
                            }
                        });
                    }).catch(() => {
                        // Even if server logout fails, clear client data and redirect
                        clearAppData();
                        Swal.fire({
                            title: 'Logged Out',
                            html: 'You have been logged out locally.<br>Redirecting to home page...',
                            icon: 'success',
                            timer: 2000,
                            timerProgressBar: true,
                            showConfirmButton: false,
                            allowOutsideClick: false,
                            willClose: () => {
                                window.location.replace('../../../index.php');
                            }
                        });
                    });
                }
            });
        });
    }

    // Force refresh profile images to prevent caching issues
    document.addEventListener('DOMContentLoaded', function() {
        const navProfileImages = document.querySelectorAll('.user-menu-avatar');
        navProfileImages.forEach(function(img) {
            const currentSrc = img.src;
            if (currentSrc && !currentSrc.includes('?v=')) {
                img.src = currentSrc + '?refresh=' + Date.now();
            }
            img.addEventListener('load', function() {
                console.log('Navigation profile image loaded successfully:', this.src);
            });
            img.addEventListener('error', function() {
                console.log('Navigation profile image failed to load:', this.src);
                this.src = '../../images/profile1.jpg';
            });
        });
    });

    // Basic back button handling (no session-specific logic)
    (function() {
        window.history.pushState(null, '', window.location.href);
        window.onpopstate = function(event) {
            window.history.pushState(null, '', window.location.href);
            if (!window.location.href.includes('../../../index.php')) {
                window.location.replace('../../../index.php');
            }
        };
        
        // Additional protection against cached pages
        window.onpageshow = function(event) {
            if (event.persisted) {
                window.location.reload();
            }
        };
    })();
    
    // Announcement system
    let announcementCount = 0;
    let announcements = [];
    
    // Simplified announcement checking (disabled to prevent errors)
    function checkAnnouncements() {
        console.log('Announcement checking disabled to prevent errors');
    }
    
    function updateAnnouncementBadge() {
        const badge = document.getElementById('announcementCount');
        const icon = document.getElementById('announcementIcon');
        
        // Check if elements exist before trying to access them
        if (!badge || !icon) {
            console.log('Announcement elements not found, skipping badge update');
            return;
        }
        
        if (announcementCount > 0) {
            badge.textContent = announcementCount;
            badge.style.display = 'block';
            icon.classList.add('has-announcements');
            
            // Update badge color based on count
            if (announcementCount > 3) {
                badge.className = 'badge bg-danger announcement-badge';
            } else if (announcementCount > 1) {
                badge.className = 'badge bg-warning announcement-badge';
            } else {
                badge.className = 'badge bg-orange announcement-badge';
            }
        } else {
            badge.style.display = 'none';
            icon.classList.remove('has-announcements');
        }
    }
    
    function getAlarmPaths() {
        const origin = window.location.origin || '';
        return [
            // Corrected paths - remove double production
            'truck.mp3',
            './alarm.mp3',
            '../alarm.mp3',
            '../../alarm.mp3',
            // Common component locations
            'components/alarm.mp3',
            './components/alarm.mp3',
            '../components/alarm.mp3',
            '../../components/alarm.mp3',
            // Correct production path (no double production)
            'production/components/alarm.mp3',
            '../production/components/alarm.mp3',
            '../../production/components/alarm.mp3',
            // Absolute paths
            origin + '/production/components/alarm.mp3',
            origin + '/components/alarm.mp3'
        ];
    }

    function playAnnouncementSound() {
        const possiblePaths = getAlarmPaths();
        let audio = null;
        let started = false;
        for (let path of possiblePaths) {
            try {
                audio = new Audio(path);
                audio.volume = 0.3;
                audio.play().then(() => { started = true; }).catch((e) => { console.log('Announcement play attempt failed for', path, e); });
                if (started) { break; }
            } catch (e) { continue; }
        }
        if (!started && audio) {
            audio.load();
            audio.play().catch(error => console.log('Audio play failed:', error));
        }
    }

    function playAlarmSound(userInitiated = false) {
        // Ensure a persistent, DOM-attached audio element with autoplay muted
        const possiblePaths = getAlarmPaths();
        let audioEl = document.getElementById('alarmAudio');
        if (!audioEl) {
            audioEl = document.createElement('audio');
            audioEl.id = 'alarmAudio';
            audioEl.setAttribute('playsinline', 'playsinline');
            audioEl.loop = true;
            audioEl.muted = !userInitiated; // unmute only on user gesture
            audioEl.preload = 'auto';
            audioEl.style.display = 'none';
            document.body.appendChild(audioEl);
        }

        // Prefer absolute FireDetectionSystem path first
        const preferred = [
            (window.location.origin || '') + '/production/components/alarm.mp3'
        ];
        const paths = [...preferred, ...possiblePaths];
        // Set first candidate src; if it errors, try next
        let idx = 0;
        const tryNext = () => {
            if (idx >= paths.length) { console.log('No alarm sources could be started'); return false; }
            const src = paths[idx++];
            audioEl.src = src;
            const p = audioEl.play();
            if (p && typeof p.then === 'function') {
                p.then(() => {
                    console.log('Alarm playing from', src);
                    if (!audioEl.muted) { audioEl.volume = 0.6; }
                }).catch((e) => {
                    console.log('Alarm autoplay attempt failed for', src, e);
                    // On failure, try next path on user-initiated action only
                    if (userInitiated) {
                        audioEl.load();
                        audioEl.play().then(() => {
                            console.log('Alarm started after load() from', src);
                            if (!audioEl.muted) { audioEl.volume = 0.6; }
                        }).catch(() => tryNext());
                    }
                });
            }
            return true;
        };
        tryNext();
        window.__alarmAudio = audioEl;
        return !audioEl.paused;
    }
    
    function showAnnouncementToast(announcement) {
        Swal.fire({
            title: '📢 New Announcement',
            text: announcement.title,
            icon: 'info',
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 6000,
            timerProgressBar: true,
            customClass: {
                popup: 'announcement-toast'
            }
        });
    }
    
    // Add click event to announcement icon (if it exists)
    const announcementIcon = document.getElementById('announcementIcon');
    if (announcementIcon) {
        announcementIcon.addEventListener('click', function(e) {
            // Add a subtle animation when clicked
            this.style.transform = 'scale(0.95)';
            setTimeout(() => {
                this.style.transform = 'scale(1)';
            }, 150);
        });
    }

    // Function to update speaker icon color based on emergency status
    function updateSpeakerIconColor(isEmergencyStatus) {
        const speakerIcon = document.getElementById('speakerIcon');
        if (!speakerIcon) {
            console.log('⚠️ Speaker icon not found for color update, retrying...');
            setTimeout(() => updateSpeakerIconColor(isEmergencyStatus), 100);
            return;
        }
        
        if (isEmergencyStatus) {
            speakerIcon.classList.remove('normal-status');
            speakerIcon.classList.add('emergency-active');
            speakerIcon.title = 'EMERGENCY - Alarm Active';
            console.log('🔴 Speaker icon set to EMERGENCY (red)');
        } else {
            speakerIcon.classList.remove('emergency-active');
            speakerIcon.classList.add('normal-status');
            speakerIcon.title = 'Normal Status - No Emergency';
            console.log('🟢 Speaker icon set to NORMAL (green)');
        }
    }

    // SIMPLIFIED SPEAKER ICON HANDLER WITH NULL CHECKS
    function initializeSpeakerIcon() {
        const speakerIcon = document.getElementById('speakerIcon');
        if (!speakerIcon) {
            console.log('⚠️ Speaker icon not found, retrying in 500ms...');
            setTimeout(initializeSpeakerIcon, 500);
            return;
        }
        
        console.log('✅ Speaker icon found, adding event listener');
        speakerIcon.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('🔊 Speaker icon clicked');
            
            const icon = this.querySelector('i');
            if (!icon) {
                console.log('⚠️ Speaker icon has no child icon element');
                return;
            }
            
            // Check if emergency alarm is playing
            if (window.emergencyAlarm && !window.emergencyAlarm.paused) {
                // Toggle mute/unmute
                if (window.emergencyAlarm.muted) {
                    window.emergencyAlarm.muted = false;
                    icon.className = 'fa fa-volume-up';
                    this.title = 'EMERGENCY - Alarm playing (unmuted)';
                    console.log('🔊 Alarm unmuted');
                } else {
                    window.emergencyAlarm.muted = true;
                    icon.className = 'fa fa-volume-off';
                    this.title = 'EMERGENCY - Alarm playing (muted)';
                    console.log('🔇 Alarm muted');
                }
            } else if (isEmergency) {
                // Start alarm if emergency but not playing
                console.log('🚨 Starting alarm from speaker click');
                startEmergencyAlarmSimple();
            } else {
                // No emergency - just log, no toast
                console.log('ℹ️ No emergency - cannot start alarm');
            }
        });
    }
    
    // Initialize speaker icon when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeSpeakerIcon);
    } else {
        initializeSpeakerIcon();
    }

    // Auto-play alarm on load if server reports EMERGENCY
    function isAlarmPlaying() {
        return window.__alarmAudio && !window.__alarmAudio.paused;
    }

    function ensureAlarmElement() {
        let audioEl = document.getElementById('alarmAudio');
        if (!audioEl) {
            audioEl = document.createElement('audio');
            audioEl.id = 'alarmAudio';
            audioEl.setAttribute('playsinline', 'playsinline');
            audioEl.loop = true;
            audioEl.muted = true; // start muted to pass autoplay
            audioEl.preload = 'auto';
            audioEl.style.display = 'none';
            document.body.appendChild(audioEl);
        }
        window.__alarmAudio = audioEl;
        return audioEl;
    }

    function tryStartAlarmIfEmergency() {
        if (!isEmergency) return;
        const el = ensureAlarmElement();
        if (el.paused) {
            try { 
                playAlarmSound(false); 
                console.log('Emergency alarm started automatically');
                
                // Emergency notification removed - alarm will start automatically on user interaction
            } catch (e) { 
                console.log('Could not start emergency alarm:', e); 
            }
        }
    }

    // Enhanced emergency alarm function with better error handling
    function startEmergencyAlarm() {
        console.log('🔊 startEmergencyAlarm() called');
        console.log('isEmergency:', isEmergency);
        
        if (!isEmergency) {
            console.log('❌ No emergency detected, alarm not started');
            return false;
        }

        const el = ensureAlarmElement();
        console.log('Audio element created:', el);
        
        // Try multiple audio sources for better compatibility
        const audioSources = [
            'alarm.mp3',
            './alarm.mp3',
            '../alarm.mp3',
            '../../alarm.mp3',
            'components/alarm.mp3',
            './components/alarm.mp3',
            '../components/alarm.mp3',
            '../../components/alarm.mp3',
            'production/components/alarm.mp3',
            '../production/components/alarm.mp3',
            '../../production/components/alarm.mp3'
        ];

        console.log('Trying audio sources:', audioSources);
        let audioStarted = false;
        
        for (let i = 0; i < audioSources.length && !audioStarted; i++) {
            try {
                console.log(`🎵 Attempting audio source ${i + 1}/${audioSources.length}: ${audioSources[i]}`);
                el.src = audioSources[i];
                el.loop = true;
                el.muted = false; // Try unmuted first
                el.volume = 0.7;
                
                console.log('Audio element properties:', {
                    src: el.src,
                    loop: el.loop,
                    muted: el.muted,
                    volume: el.volume,
                    readyState: el.readyState
                });
                
                const playPromise = el.play();
                if (playPromise !== undefined) {
                    playPromise.then(() => {
                        console.log('✅ Emergency alarm playing from:', audioSources[i]);
                        audioStarted = true;
                        updateSpeakerIcon(true);
                        
                        // Show success notification
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                title: '🔊 Alarm Started',
                                text: `Emergency alarm is now playing from: ${audioSources[i]}`,
                                icon: 'success',
                                toast: true,
                                position: 'top-end',
                                timer: 3000,
                                showConfirmButton: false
                            });
                        }
                    }).catch((error) => {
                        console.log('❌ Audio play failed for', audioSources[i], error);
                        console.log('Error details:', error);
                        
                        // Try muted version
                        console.log('🔄 Trying muted version...');
                        el.muted = true;
                        el.play().then(() => {
                            console.log('✅ Emergency alarm playing muted from:', audioSources[i]);
                            audioStarted = true;
                            updateSpeakerIcon(true);
                            
                            // Show muted notification
                            if (typeof Swal !== 'undefined') {
                                Swal.fire({
                                    title: '🔇 Alarm Started (Muted)',
                                    text: `Emergency alarm is playing muted from: ${audioSources[i]}. Click speaker to unmute.`,
                                    icon: 'info',
                                    toast: true,
                                    position: 'top-end',
                                    timer: 5000,
                                    showConfirmButton: false
                                });
                            }
                        }).catch((mutedError) => {
                            console.log('❌ Muted audio also failed for', audioSources[i], mutedError);
                        });
                    });
                } else {
                    console.log('⚠️ play() returned undefined for', audioSources[i]);
                }
            } catch (error) {
                console.log('❌ Error setting up audio source', audioSources[i], error);
            }
        }

        console.log('Audio started:', audioStarted);
        return audioStarted;
    }

    function updateSpeakerIcon(isPlaying) {
        const speakerIcon = document.getElementById('speakerIcon');
        const icon = speakerIcon?.querySelector('i');
        
        if (speakerIcon && icon) {
            if (isPlaying) {
                icon.classList.remove('fa-volume-off');
                icon.classList.add('fa-volume-up');
                speakerIcon.classList.remove('sound-muted');
                speakerIcon.title = 'Emergency alarm is playing - Click to mute';
            } else {
                icon.classList.remove('fa-volume-up');
                icon.classList.add('fa-volume-off');
                speakerIcon.classList.add('sound-muted');
                speakerIcon.title = 'Emergency alarm is muted - Click to unmute';
            }
        }
    }

    function tryUnmuteAndPlay() {
        const el = window.__alarmAudio;
        if (!el) return;
        if (el.muted) {
            try { el.muted = false; el.volume = 0.6; el.play().catch(()=>{}); } catch(e) {}
        }
    }

    // Enhanced alarm state persistence across reloads
    const ALARM_PREF_KEY = 'alarmPreference'; // 'on' | 'off'
    const ALARM_STATE_KEY = 'alarmIsPlaying'; // 'true' | 'false'
    const ALARM_MUTED_KEY = 'alarmIsMuted';   // 'true' | 'false'
    const EMERGENCY_STATE_KEY = 'emergencyActive'; // 'true' | 'false'
    const USER_INTERACTION_KEY = 'userHasInteracted'; // 'true' | 'false'

    function persistAlarmState() {
        try {
            const el = window.__alarmAudio;
            localStorage.setItem(ALARM_STATE_KEY, el && !el.paused ? 'true' : 'false');
            localStorage.setItem(ALARM_MUTED_KEY, el ? (el.muted ? 'true' : 'false') : 'true');
            localStorage.setItem(EMERGENCY_STATE_KEY, isEmergency ? 'true' : 'false');
            localStorage.setItem(USER_INTERACTION_KEY, window.userHasInteracted ? 'true' : 'false');
            console.log('💾 Alarm state persisted to localStorage');
        } catch(e) {
            console.log('❌ Failed to persist alarm state:', e);
        }
    }

    // Persist state on various events
    window.addEventListener('beforeunload', persistAlarmState);
    window.addEventListener('pagehide', persistAlarmState);
    
    // Also persist periodically
    setInterval(persistAlarmState, 5000); // Every 5 seconds

    function restoreAlarmState() {
        let savedPref = 'off';
        let wasPlaying = false;
        let wasMuted = true;
        let wasEmergency = false;
        let hadUserInteraction = false;
        
        try {
            savedPref = localStorage.getItem(ALARM_PREF_KEY) || 'off';
            wasPlaying = (localStorage.getItem(ALARM_STATE_KEY) === 'true');
            wasMuted = (localStorage.getItem(ALARM_MUTED_KEY) !== 'false');
            wasEmergency = (localStorage.getItem(EMERGENCY_STATE_KEY) === 'true');
            hadUserInteraction = (localStorage.getItem(USER_INTERACTION_KEY) === 'true');
        } catch(e) {
            console.log('❌ Failed to restore alarm state:', e);
        }

        console.log('🔄 Restoring alarm state:', {
            wasPlaying, wasMuted, wasEmergency, hadUserInteraction, currentEmergency: isEmergency
        });

        // Restore user interaction state
        if (hadUserInteraction) {
            window.userHasInteracted = true;
            console.log('✅ User interaction state restored');
        }

        // Auto-start if there was an emergency or if alarm was playing
        if (isEmergency || (wasEmergency && wasPlaying)) {
            console.log('🚨 Auto-resuming alarm after page reload');
            ensureAlarmElement();
            
            // Try to start alarm immediately
            setTimeout(() => {
                try { 
                    playAlarmSound(false); 
                    console.log('✅ Alarm auto-resumed after reload');
                } catch(e) {
                    console.log('❌ Failed to auto-resume alarm:', e);
                    // Fallback to simple method
                    startEmergencyAlarmSimple();
                }
            }, 1000);
            
            const el = window.__alarmAudio;
            if (el) {
                el.muted = wasMuted;
                if (!wasMuted) {
                    setTimeout(() => { tryUnmuteAndPlay(); }, 2000);
                }
            }
        } else {
            // Not emergency: ensure alarm is stopped
            if (window.__alarmAudio && !window.__alarmAudio.paused) {
                try { window.__alarmAudio.pause(); } catch(e) {}
            }
        }

        // Sync speaker icon: force ON during EMERGENCY; otherwise reflect preference
        const speaker = document.getElementById('speakerIcon');
        if (speaker) {
            const icon = speaker.querySelector('i');
            const preferOff = (savedPref === 'off');
            if (icon) {
                if (isEmergency || (wasEmergency && wasPlaying)) {
                    icon.classList.remove('fa-volume-off');
                    icon.classList.add('fa-volume-up');
                    speaker.classList.remove('sound-muted');
                    updateSpeakerIconColor(true);
                } else {
                    if (preferOff) {
                        icon.classList.remove('fa-volume-up');
                        icon.classList.add('fa-volume-off');
                        speaker.classList.add('sound-muted');
                    } else {
                        icon.classList.remove('fa-volume-off');
                        icon.classList.add('fa-volume-up');
                        speaker.classList.remove('sound-muted');
                    }
                    updateSpeakerIconColor(false);
                }
            }
        }
    }

    // Restore any persisted state immediately on load
    restoreAlarmState();
    
    // Additional auto-resume check after a short delay
    setTimeout(() => {
        if (isEmergency && (!window.emergencyAlarm || window.emergencyAlarm.paused)) {
            console.log('🔄 Additional auto-resume check - starting alarm');
            startEmergencyAlarmSimple();
        }
    }, 3000);

    // Simplified emergency monitoring (disabled for now to prevent errors)
    let emergencyMonitoringInterval = null;
    
    function startEmergencyMonitoring() {
        console.log('Emergency monitoring disabled to prevent errors');
    }
    
    function stopEmergencyMonitoring() {
        if (emergencyMonitoringInterval) {
            clearInterval(emergencyMonitoringInterval);
            emergencyMonitoringInterval = null;
        }
    }

    // SIMPLIFIED EMERGENCY ALARM SYSTEM - GUARANTEED TO WORK
    function startEmergencyAlarmSimple() {
        console.log('🚨 EMERGENCY ALARM STARTING...');
        
        // Check if user has interacted with the page
        if (!window.userHasInteracted) {
            console.log('⚠️ User interaction required for audio playback');
            showUserInteractionPrompt();
            return;
        }
        
        try {
            // Create audio element
            const audio = new Audio();
            audio.loop = true;
            audio.volume = 0.8;
            
            // Try different paths
            const paths = getAlarmPaths();
            
            let pathIndex = 0;
            let attempts = 0;
            const maxAttempts = 3;
            
            function tryNextPath() {
                if (pathIndex >= paths.length) {
                    attempts++;
                    if (attempts < maxAttempts) {
                        console.log(`🔄 Retry attempt ${attempts}/${maxAttempts} - resetting paths`);
                        pathIndex = 0;
                        setTimeout(tryNextPath, 1000);
                        return;
                    }
                    console.log('❌ All audio paths failed after multiple attempts');
                    return;
                }
                
                const currentPath = paths[pathIndex];
                console.log(`🎵 Trying path ${pathIndex + 1}: ${currentPath}`);
                
                audio.src = currentPath;
                
                // Try to play
                const playPromise = audio.play();
                
                if (playPromise !== undefined) {
                    playPromise.then(() => {
                        console.log(`✅ SUCCESS! Alarm playing from: ${currentPath}`);
                        window.emergencyAlarm = audio; // Store globally
                        
                        // Update speaker icon to red
                        updateSpeakerIconColor(true);
                        
                    }).catch((error) => {
                        console.log(`❌ Failed: ${currentPath} - ${error.message}`);
                        pathIndex++;
                        setTimeout(tryNextPath, 100);
                    });
                } else {
                    console.log(`⚠️ play() returned undefined for: ${currentPath}`);
                    pathIndex++;
                    setTimeout(tryNextPath, 100);
                }
            }
            
            tryNextPath();
            
        } catch (error) {
            console.log('❌ Error creating alarm:', error);
        }
    }
    
    // Function to show user interaction prompt (simplified - no modal)
    function showUserInteractionPrompt() {
        console.log('🔊 Audio permission required - waiting for user interaction');
        // Just log the message, no modal popup
        // The global click handler will automatically enable audio when user clicks
    }
    
    // Start alarm immediately if emergency
    if (isEmergency) {
        console.log('🚨 EMERGENCY DETECTED - Starting alarm NOW!');
        updateSpeakerIconColor(true); // Set to red
        
        // Try to start alarm immediately
        startEmergencyAlarmSimple();
        
        // Also try the original method as backup
        setTimeout(() => {
            if (!window.emergencyAlarm || window.emergencyAlarm.paused) {
                console.log('🔄 Backup alarm method...');
                tryStartAlarmIfEmergency();
            }
        }, 2000);
        
        // Emergency notification removed - alarm will start automatically on user interaction
    } else {
        console.log('✅ No emergency detected');
        updateSpeakerIconColor(false); // Set to green
    }
    
    // Start emergency monitoring regardless of current status
    startEmergencyMonitoring();
    
    // Global click handler to enable audio immediately
    document.addEventListener('click', function() {
        if (!window.userHasInteracted) {
            window.userHasInteracted = true;
            console.log('🔊 Global click detected - audio enabled');
            
            // If there's an emergency, start alarm immediately
            if (isEmergency && (!window.emergencyAlarm || window.emergencyAlarm.paused)) {
                console.log('🚨 Emergency alarm starting after global click');
                startEmergencyAlarmSimple();
            }
        }
    }, { once: true });
    
    // Stop monitoring when page is unloaded
    window.addEventListener('beforeunload', stopEmergencyMonitoring);
    
    // Database Backup Handler
    const backupAllBtn = document.getElementById('backupAllBtn');
    
    if (backupAllBtn) {
        backupAllBtn.addEventListener('click', function() {
            performBackup('all');
        });
    }
    
    function performBackup(type) {
        // Show confirmation first
        Swal.fire({
            title: 'Create Database Backup?',
            html: `Are you sure you want to create a complete database backup?<br><br><small>This will export your entire database to an SQL file.</small>`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, Create Backup',
            cancelButtonText: 'Cancel',
            showLoaderOnConfirm: false
        }).then((result) => {
            if (result.isConfirmed) {
                // Start backup process
                Swal.fire({
                    title: 'Creating Database Backup',
                    html: 'Please wait while we backup your database...<br><small>This may take a few moments.</small>',
                    icon: 'info',
                    allowOutsideClick: false,
                    showConfirmButton: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                fetch('<?php echo $backup_url; ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'backup_type=all'
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        const fileSizeMB = data.file_size_mb || (data.file_size / 1024 / 1024).toFixed(2);
                        Swal.fire({
                            title: '✅ Backup Successful!',
                            html: `${data.message}<br><br><small>Filename: ${data.backup_filename}<br>Size: ${fileSizeMB} MB</small>`,
                            icon: 'success',
                            confirmButtonText: 'Download Backup',
                            showCancelButton: true,
                            cancelButtonText: 'Close',
                            confirmButtonColor: '#007bff'
                        }).then((result) => {
                            if (result.isConfirmed && data.download_url) {
                                // Download the backup file
                                window.location.href = data.download_url;
                            }
                        });
                    } else {
                        Swal.fire({
                            title: '❌ Backup Failed',
                            html: `<strong>Error:</strong> ${data.message || 'An error occurred while creating the backup.'}`,
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });
                    }
                })
                .catch(error => {
                    console.error('Backup error:', error);
                    Swal.fire({
                        title: '❌ Backup Error',
                        html: `<strong>Failed to connect to server.</strong><br><small>${error.message}</small>`,
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                });
            }
        });
    }
    
    // Database Import Handler
    const importBackupBtn = document.getElementById('importBackupBtn');
    
    if (importBackupBtn) {
        importBackupBtn.addEventListener('click', function() {
            performImport();
        });
    }
    
    function performImport() {
        // Create a file input element
        const fileInput = document.createElement('input');
        fileInput.type = 'file';
        fileInput.accept = '.sql,.txt';
        fileInput.style.display = 'none';
        
        fileInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (!file) {
                return;
            }
            
            // Validate file type
            const allowedExtensions = ['sql', 'txt'];
            const fileExtension = file.name.split('.').pop().toLowerCase();
            if (!allowedExtensions.includes(fileExtension)) {
                Swal.fire({
                    title: '❌ Invalid File Type',
                    html: 'Only .sql and .txt files are allowed.',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
                return;
            }
            
            // Validate file size (500MB max)
            const maxSize = 500 * 1024 * 1024; // 500MB
            if (file.size > maxSize) {
                Swal.fire({
                    title: '❌ File Too Large',
                    html: 'File size exceeds maximum limit of 500MB.',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
                return;
            }
            
            // Show confirmation
            Swal.fire({
                title: 'Import Database Backup?',
                html: `Are you sure you want to import this database backup?<br><br><strong>WARNING:</strong> This will modify your database and cannot be undone.<br><small>File: ${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB)</small>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, Import Database',
                cancelButtonText: 'Cancel',
                showLoaderOnConfirm: false,
                input: 'text',
                inputLabel: 'Type "IMPORT" to confirm',
                inputPlaceholder: 'Type here...',
                inputValidator: (value) => {
                    if (value !== 'IMPORT') {
                        return 'You must type "IMPORT" to confirm!';
                    }
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    // Start import process
                    Swal.fire({
                        title: 'Importing Database',
                        html: 'Please wait while we import your database...<br><small>This may take a few moments. Do not close this window.</small>',
                        icon: 'info',
                        allowOutsideClick: false,
                        showConfirmButton: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    
                    // Create FormData
                    const formData = new FormData();
                    formData.append('backup_file', file);
                    
                    fetch('<?php echo $import_url; ?>', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            let message = `<strong>✅ Import Successful!</strong><br><br>${data.message}`;
                            if (data.executed_queries) {
                                message += `<br><small>Executed: ${data.executed_queries} queries</small>`;
                            }
                            if (data.failed_queries && data.failed_queries > 0) {
                                message += `<br><small>Failed: ${data.failed_queries} queries</small>`;
                            }
                            if (data.errors && data.errors.length > 0) {
                                message += `<br><small>Errors: ${data.errors.length} errors encountered</small>`;
                            }
                            
                            Swal.fire({
                                title: '✅ Import Completed!',
                                html: message,
                                icon: 'success',
                                confirmButtonText: 'OK',
                                confirmButtonColor: '#28a745'
                            }).then(() => {
                                // Optionally reload the page to reflect changes
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                title: '❌ Import Failed',
                                html: `<strong>Error:</strong> ${data.message || 'An error occurred while importing the backup.'}`,
                                icon: 'error',
                                confirmButtonText: 'OK'
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Import error:', error);
                        Swal.fire({
                            title: '❌ Import Error',
                            html: `<strong>Failed to connect to server.</strong><br><small>${error.message}</small>`,
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });
                    });
                }
            });
            
            // Clean up
            document.body.removeChild(fileInput);
        });
        
        // Trigger file selection
        document.body.appendChild(fileInput);
        fileInput.click();
    }
});
</script>

<style>
/* Admin Notification Styles */
.notification-item {
    padding: 8px 12px;
    border-bottom: 1px solid #f0f0f0;
    cursor: pointer;
    transition: all 0.3s ease;
    border-left: 3px solid transparent;
    background-color: white;
}

.notification-item:hover {
    background-color: #f8f9fa;
    transform: translateX(-1px);
}

.notification-item.notification-danger {
    border-left-color: #dc3545;
    background-color: white;
}

.notification-item.notification-warning {
    border-left-color: #ffc107;
    background-color: white;
}

.notification-item.notification-info {
    border-left-color: #17a2b8;
    background-color: white;
}

.notification-icon-wrapper {
    margin-right: 8px;
    display: flex;
    align-items: center;
}

.notification-icon-danger {
    color: #dc3545;
    font-size: 14px;
}

.notification-icon-warning {
    color: #ffc107;
    font-size: 14px;
}

.notification-icon-info {
    color: #17a2b8;
    font-size: 14px;
}

.notification-title {
    font-weight: 600;
    font-size: 12px;
    margin-bottom: 2px;
    color: #333;
}

.notification-message {
    font-size: 10px;
    color: #666;
    margin-bottom: 4px;
    line-height: 1.3;
}

.notification-actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.notification-actions .btn {
    font-size: 9px;
    padding: 1px 6px;
}

.dropdown-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 12px;
    background-color: white;
    border-bottom: 1px solid #dee2e6;
}

.dropdown-header h6 {
    margin: 0;
    font-size: 12px;
    font-weight: 600;
    color: #333;
}

#notificationCount {
    font-size: 8px;
    padding: 2px 5px;
    border-radius: 8px;
    font-weight: bold;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% {
        box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7);
    }
    70% {
        box-shadow: 0 0 0 6px rgba(220, 53, 69, 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(220, 53, 69, 0);
    }
}

.dropdown-usermenu {
    border: 1px solid #ddd;
    background-color: white;
    box-shadow: 0 4px 15px rgba(0,0,0,0.15);
    border-radius: 8px;
}

/* Priority indicators */
.notification-item[data-priority="high"] {
    border-left-width: 6px;
}

.notification-item[data-priority="medium"] {
    border-left-width: 5px;
}

.notification-item[data-priority="low"] {
    border-left-width: 4px;
}

/* Mobile responsive */
@media (max-width: 768px) {
    .dropdown-usermenu {
        width: 320px !important;
        max-height: 400px !important;
    }
    
    .notification-actions {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .notification-actions .btn {
        margin-bottom: 4px;
    }
}

/* Announcement Icon Styles */
.announcement-icon {
    position: relative;
    transition: all 0.3s ease;
    color: #666;
}

.announcement-icon:hover {
    color: #ff6b35;
    transform: scale(1.1);
}

.announcement-icon.has-announcements {
    color: #ff6b35;
    animation: announcementPulse 2s infinite;
}

.announcement-badge {
    font-size: 10px;
    padding: 3px 6px;
    border-radius: 10px;
    font-weight: bold;
    animation: announcementPulse 2s infinite;
}

@keyframes announcementPulse {
    0% {
        box-shadow: 0 0 0 0 rgba(255, 107, 53, 0.7);
    }
    70% {
        box-shadow: 0 0 0 6px rgba(255, 107, 53, 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(255, 107, 53, 0);
    }
}

.announcement-toast {
    background: linear-gradient(135deg, #ff6b35, #f7931e);
    color: white;
    border-radius: 10px;
    box-shadow: 0 4px 15px rgba(255, 107, 53, 0.3);
}

.announcement-toast .swal2-title {
    color: white;
}

.announcement-toast .swal2-content {
    color: rgba(255, 255, 255, 0.9);
}

/* Role badge styles */
.user-profile .role-badge {
    display: inline-block;
    margin-left: 8px;
    padding: 4px 10px;
    border-radius: 999px;
    font-weight: 700;
    font-size: 12px;
    letter-spacing: 0.3px;
    text-transform: uppercase;
    vertical-align: middle;
    line-height: 1;
}

.user-profile .role-admin {
    color: #ffffff;
    background: linear-gradient(135deg, #ff6b35, #f7931e);
    box-shadow: 0 2px 6px rgba(255, 107, 53, 0.35);
    border: 1px solid rgba(255, 107, 53, 0.5);
}

.user-profile .role-guest {
    color: #495057;
    background: #f1f3f5;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
    border: 1px solid #e0e4e8;
}

/* Subtle hover lift */
.user-profile:hover .role-badge {
    transform: translateY(-1px);
    transition: transform 0.15s ease;
}

/* Modern user menu (white background) */
 .dropdown-usermenu.user-menu-panel {
     background: #ffffff;
     padding: 12px;
 }

.user-menu-actions {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.menu-btn {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    text-decoration: none;
    background: #ffffff;
    border: 1px solid #e9ecef;
    border-radius: 12px;
    padding: 12px 14px;
    color: #343a40;
    font-weight: 600;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
}

.menu-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 18px rgba(0,0,0,0.10);
}

.menu-btn-left {
    display: inline-flex;
    align-items: center;
    gap: 10px;
}

.menu-btn .fa {
    font-size: 18px;
}

.menu-btn-text {
    font-size: 14px;
    letter-spacing: 0.2px;
}

.menu-btn-chevron {
    color: #adb5bd;
    font-size: 14px;
    transition: transform 0.15s ease;
}

.menu-btn:hover .menu-btn-chevron {
    transform: translateX(2px);
}

.menu-btn--primary {
    border-color: #cfe2ff;
}

.menu-btn--primary .fa:first-child {
    color: #0d6efd;
}

.menu-btn--primary:hover {
    border-color: #0d6efd;
}

.menu-btn--danger {
    border-color: #f8d7da;
}

.menu-btn--danger .fa:first-child {
    color: #dc3545;
}

.menu-btn--danger:hover {
    border-color: #dc3545;
}

@media (max-width: 768px) {
    .menu-btn {
        padding: 12px;
    }
}

/* === Overrides: modern 3D look without shadows/gradients === */
/* Role badges without gradients/shadows; 3D via dual-edge borders */
.user-profile .role-admin {
    background: #ff6b35 !important;
    border: 2px solid #ff6b35 !important;
    border-top-color: #ffa27f !important;
    border-left-color: #ffa27f !important;
    border-bottom-color: #cc522a !important;
    border-right-color: #cc522a !important;
    box-shadow: none !important;
}

.user-profile .role-guest {
    color: #343a40 !important;
    background: #f1f3f5 !important;
    border: 2px solid #e0e4e8 !important;
    border-top-color: #ffffff !important;
    border-left-color: #ffffff !important;
    border-bottom-color: #cfd4da !important;
    border-right-color: #cfd4da !important;
    box-shadow: none !important;
}

/* User menu panel: white, beveled edges, no shadow */
.dropdown-usermenu.user-menu-panel {
    box-shadow: none !important;
    border-radius: 12px !important;
    border: 2px solid #e9ecef !important;
    border-top-color: #ffffff !important;
    border-left-color: #ffffff !important;
    border-bottom-color: #dee2e6 !important;
    border-right-color: #dee2e6 !important;
}

/* Menu buttons: beveled edges; no shadows */
.menu-btn {
    border: 2px solid #e9ecef !important;
    border-top-color: #f8f9fa !important;
    border-left-color: #f8f9fa !important;
    border-bottom-color: #dee2e6 !important;
    border-right-color: #dee2e6 !important;
    box-shadow: none !important;
    transition: transform 0.12s ease, border-color 0.12s ease !important;
}

.menu-btn:hover {
    transform: translateY(-1px) !important;
    border-top-color: #ffffff !important;
    border-left-color: #ffffff !important;
    border-bottom-color: #ced4da !important;
    border-right-color: #ced4da !important;
    box-shadow: none !important;
}

.menu-btn:active {
    transform: translateY(0) !important;
    border-top-color: #ced4da !important;
    border-left-color: #ced4da !important;
    border-bottom-color: #ffffff !important;
    border-right-color: #ffffff !important;
}

/* Variants: light tints with colored edges and icons */
.menu-btn--primary {
    background: #eef5ff !important;
    border-color: #b9d7ff !important;
    border-top-color: #e3f0ff !important;
    border-left-color: #e3f0ff !important;
    border-bottom-color: #9fc2ff !important;
    border-right-color: #9fc2ff !important;
}
.menu-btn--primary:hover {
    border-top-color: #f4f9ff !important;
    border-left-color: #f4f9ff !important;
    border-bottom-color: #8eb8ff !important;
    border-right-color: #8eb8ff !important;
}
.menu-btn--primary .menu-btn-chevron { color: #0d6efd !important; }

.menu-btn--danger {
    background: #fff1f3 !important;
    border-color: #f3b5bd !important;
    border-top-color: #ffe6e9 !important;
    border-left-color: #ffe6e9 !important;
    border-bottom-color: #e59aa3 !important;
    border-right-color: #e59aa3 !important;
}
.menu-btn--danger:hover {
    border-top-color: #fff6f7 !important;
    border-left-color: #fff6f7 !important;
    border-bottom-color: #dd8e98 !important;
    border-right-color: #dd8e98 !important;
}
.menu-btn--danger .menu-btn-chevron { color: #dc3545 !important; }

/* Replace shadow-based pulse with scale pulse */
#notificationCount {
    animation: pulse 2s infinite !important;
}
.announcement-badge {
    animation: announcementPulse 2s infinite !important;
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.06); }
    100% { transform: scale(1); }
}

@keyframes announcementPulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.06); }
    100% { transform: scale(1); }
}

/* Announcement toast without gradients/shadows */
.announcement-toast {
    background: #ffffff !important;
    color: #ff6b35 !important;
    border-radius: 10px !important;
    border: 2px solid #ff6b35 !important;
    box-shadow: none !important;
}
.announcement-toast .swal2-title { color: #ff6b35 !important; }
.announcement-toast .swal2-content { color: #676767 !important; }

/* Emergency alert toast styles */
.emergency-alert-toast {
    background: #ffffff !important;
    color: #dc3545 !important;
    border-radius: 10px !important;
    border: 3px solid #dc3545 !important;
    box-shadow: none !important;
    animation: emergencyPulse 1s infinite;
}
.emergency-alert-toast .swal2-title { 
    color: #dc3545 !important; 
    font-weight: bold !important;
    font-size: 16px !important;
}
.emergency-alert-toast .swal2-content { 
    color: #721c24 !important; 
    font-weight: 500 !important;
}

@keyframes emergencyPulse {
    0% { 
        border-color: #dc3545;
        transform: scale(1);
    }
    50% { 
        border-color: #ff6b6b;
        transform: scale(1.02);
    }
    100% { 
        border-color: #dc3545;
        transform: scale(1);
    }
}

/* Top nav icon styling without shadows/gradients */
#notificationDropdown .fa-bell,
#announcementIcon .fa-bullhorn,
#backupDropdown .fa-database,
#importDropdown .fa-upload,
.sound-icon .fa,
.nav-user-icon {
    background: #ffffff !important;
    border: 2px solid #e9ecef !important;
    border-top-color: #ffffff !important;
    border-left-color: #ffffff !important;
    border-bottom-color: #dee2e6 !important;
    border-right-color: #dee2e6 !important;
    border-radius: 999px !important;
    padding: 6px !important;
    line-height: 1 !important;
}
#notificationDropdown .fa-bell { color: #0d6efd !important; }
#announcementIcon .fa-bullhorn { color: #ff6b35 !important; }
#backupDropdown .fa-database { color: #28a745 !important; }
#importDropdown .fa-upload { color: #007bff !important; }

/* Speaker Icon Color States */
.sound-icon.normal-status .fa { 
    color: #28a745 !important; /* Green for normal status */
    transition: all 0.3s ease;
    animation: pulse-green 3s infinite;
}

.sound-icon.emergency-active .fa { 
    color: #dc3545 !important; /* Red for emergency */
    transition: all 0.3s ease;
    animation: pulse-red 1s infinite;
    box-shadow: 0 0 15px rgba(220, 53, 69, 0.6) !important;
}

.nav-user-icon { color: #ff6b35 !important; }

#notificationDropdown:hover .fa-bell,
#announcementIcon:hover .fa-bullhorn,
#backupDropdown:hover .fa-database,
#importDropdown:hover .fa-upload {
    border-top-color: #f8f9fa !important;
    border-left-color: #f8f9fa !important;
    border-bottom-color: #ced4da !important;
    border-right-color: #ced4da !important;
    transform: scale(1.1) rotate(5deg);
    box-shadow: 0 0 15px rgba(255, 107, 53, 0.6);
}
#backupDropdown:hover .fa-database {
    box-shadow: 0 0 15px rgba(40, 167, 69, 0.6) !important;
}
#importDropdown:hover .fa-upload {
    box-shadow: 0 0 15px rgba(0, 123, 255, 0.6) !important;
}

/* Speaker icon hover effects based on state */
.sound-icon.normal-status:hover .fa {
    border-top-color: #f8f9fa !important;
    border-left-color: #f8f9fa !important;
    border-bottom-color: #ced4da !important;
    border-right-color: #ced4da !important;
    transform: scale(1.1) rotate(5deg);
    color: #1e7e34 !important; /* Darker green on hover */
    box-shadow: 0 0 20px rgba(40, 167, 69, 0.8) !important;
}

.sound-icon.emergency-active:hover .fa {
    border-top-color: #f8f9fa !important;
    border-left-color: #f8f9fa !important;
    border-bottom-color: #ced4da !important;
    border-right-color: #ced4da !important;
    transform: scale(1.1) rotate(5deg);
    color: #b02a37 !important; /* Darker red on hover */
    box-shadow: 0 0 25px rgba(220, 53, 69, 1) !important;
}

/* Speaker icon active/muted state */
#speakerIcon.sound-muted .fa { 
    color: #adb5bd !important; 
    animation: none;
}

/* Pulse animations for speaker icon states */
@keyframes pulse-green {
    0% {
        box-shadow: 0 0 5px rgba(40, 167, 69, 0.4);
        transform: scale(1);
    }
    50% {
        box-shadow: 0 0 15px rgba(40, 167, 69, 0.8);
        transform: scale(1.02);
    }
    100% {
        box-shadow: 0 0 5px rgba(40, 167, 69, 0.4);
        transform: scale(1);
    }
}

@keyframes pulse-red {
    0% {
        box-shadow: 0 0 10px rgba(220, 53, 69, 0.6);
        transform: scale(1);
    }
    50% {
        box-shadow: 0 0 25px rgba(220, 53, 69, 1);
        transform: scale(1.05);
    }
    100% {
        box-shadow: 0 0 10px rgba(220, 53, 69, 0.6);
        transform: scale(1);
    }
}

/* Legacy pulse-glow animation (kept for compatibility) */
@keyframes pulse-glow {
    0% {
        box-shadow: 0 0 5px rgba(255, 107, 53, 0.4);
        transform: scale(1);
    }
    50% {
        box-shadow: 0 0 20px rgba(255, 107, 53, 0.8);
        transform: scale(1.05);
    }
    100% {
        box-shadow: 0 0 5px rgba(255, 107, 53, 0.4);
        transform: scale(1);
    }
}

/* Hide Bootstrap caret on notification and profile dropdowns */
#notificationDropdown.dropdown-toggle::after,
#navbarDropdown.dropdown-toggle::after,
#backupDropdown.dropdown-toggle::after,
#importDropdown.dropdown-toggle::after {
    display: none !important;
    content: none !important;
}

/* Nav user icon (in toggle) */
.nav-user-icon {
    color: #ff6b35;
    background: #ffffff;
    border: 2px solid #e9ecef;
    border-top-color: #ffffff;
    border-left-color: #ffffff;
    border-bottom-color: #dee2e6;
    border-right-color: #dee2e6;
    border-radius: 999px;
    font-size: 18px;
    margin-right: 8px;
    padding: 6px;
    line-height: 1;
}

/* User menu header with avatar inside dropdown */
.user-menu-header {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    padding: 8px 4px 12px 4px;
    border-bottom: 1px solid #edf0f2;
    margin-bottom: 10px;
}

.user-menu-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
    background: #ffffff;
    border: 2px solid #e9ecef;
    border-top-color: #ffffff;
    border-left-color: #ffffff;
    border-bottom-color: #dee2e6;
    border-right-color: #dee2e6;
}

.user-menu-meta {
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
}

.user-menu-name {
    font-weight: 700;
    color: #212529;
    font-size: 14px;
}

.user-menu-role {
    font-size: 12px;
    color: #6c757d;
}

.user-menu-email {
    font-size: 11px;
    color: #868e96;
    margin-top: 2px;
}
/* Icon inside role badge: remove ring, add spacing */
.user-profile .role-badge .nav-user-icon {
    margin-right: 6px;
    border: 0 !important;
    background: transparent !important;
    padding: 0 !important;
    font-size: 16px;
}
.user-profile .role-badge.role-admin .nav-user-icon { color: #ffffff !important; }
.user-profile .role-badge.role-guest .nav-user-icon { color: #ff6b35 !important; }

/* Show sidebar burger/toggle */
.nav_menu .nav.toggle, #menu_toggle { display: block !important; }

/* Make dropdown menu buttons fully white */
.menu-btn, .menu-btn--primary, .menu-btn--danger {
    background: #ffffff !important;
    border-color: #ffffff !important;
    border-top-color: #ffffff !important;
    border-left-color: #ffffff !important;
    border-bottom-color: #ffffff !important;
    border-right-color: #ffffff !important;
}
.menu-btn:hover, .menu-btn:active,
.menu-btn--primary:hover, .menu-btn--danger:hover {
    background: #ffffff !important;
    border-color: #ffffff !important;
    border-top-color: #ffffff !important;
    border-left-color: #ffffff !important;
    border-bottom-color: #ffffff !important;
    border-right-color: #ffffff !important;
}
</style>