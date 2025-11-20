<?php include('profile.php')?>
<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    return;
}

$user_id = $_SESSION['user_id'];

// Database connection
try {
    $pdo = new PDO("mysql:host=localhost;dbname=firedb", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    return;
}

// Initialize notification arrays
$notifications = [];
$notification_count = 0;


// 1. Check for unassigned devices
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as unassigned_count 
        FROM devices 
        WHERE user_id = ? AND building_id IS NULL AND is_active = 1
    ");
    $stmt->execute([$user_id]);
    $unassigned_devices = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($unassigned_devices['unassigned_count'] > 0) {
        $notifications[] = [
            'type' => 'warning',
            'title' => 'Unassigned Devices',
            'message' => "You have {$unassigned_devices['unassigned_count']} device" . ($unassigned_devices['unassigned_count'] > 1 ? 's' : '') . " that " . ($unassigned_devices['unassigned_count'] > 1 ? 'are' : 'is') . " not assigned to any building.",
            'action_text' => 'Assign Devices',
            'action_url' => '../../assigndevice/php/main.php',
            'icon' => 'fa-tablet'
        ];
        $notification_count++;
    }
} catch (PDOException $e) {
    error_log("Error checking unassigned devices: " . $e->getMessage());
}

// 2. Check for unregistered buildings
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as building_count 
        FROM buildings 
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    $building_count = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($building_count['building_count'] == 0) {
        $notifications[] = [
            'type' => 'info',
            'title' => 'No Buildings Registered',
            'message' => 'You haven\'t registered any buildings yet. Register buildings to assign devices and enable proper monitoring.',
            'action_text' => 'Register Building',
            'action_url' => '../../building_registration/index.html',
            'icon' => 'fa-building'
        ];
        $notification_count++;
    } else {
        // 2a. Check for incomplete building data
        $stmt2 = $pdo->prepare("
            SELECT id, building_name, building_type, address, total_floors, has_sprinkler_system, has_fire_alarm, has_fire_extinguishers, has_emergency_exits, has_emergency_lighting, has_fire_escape, contact_person, contact_number, last_inspected, latitude, longitude, construction_year, building_area
            FROM buildings
            WHERE user_id = ?
        ");
        $stmt2->execute([$user_id]);
        $incomplete_count = 0;
        $optional_missing = 0;
        while ($b = $stmt2->fetch(PDO::FETCH_ASSOC)) {
            // Required fields check
            $required_missing = (
                empty($b['building_name']) ||
                empty($b['building_type']) ||
                empty($b['address']) ||
                $b['total_floors'] === null || $b['total_floors'] === '' ||
                $b['has_sprinkler_system'] === null || $b['has_fire_alarm'] === null ||
                $b['has_fire_extinguishers'] === null || $b['has_emergency_exits'] === null ||
                $b['has_emergency_lighting'] === null || $b['has_fire_escape'] === null
            );
            if ($required_missing) {
                $incomplete_count++;
                continue;
            }
            // Optional fields check
            if (
                empty($b['contact_person']) || empty($b['contact_number']) ||
                empty($b['last_inspected']) || $b['latitude'] === null || $b['longitude'] === null ||
                $b['construction_year'] === null || $b['building_area'] === null
            ) {
                $optional_missing++;
            }
        }
        if ($incomplete_count > 0) {
            $notifications[] = [
                'type' => 'warning',
                'title' => 'Incomplete Building Data',
                'message' => "You have {$incomplete_count} building" . ($incomplete_count > 1 ? 's' : '') . " with missing required information. Please update all required fields for accurate monitoring.",
                'action_text' => 'Update Buildings',
                'action_url' => '../../building/php/main.php',
                'icon' => 'fa-exclamation-triangle'
            ];
            $notification_count++;
        } else if ($optional_missing > 0) {
            $notifications[] = [
                'type' => 'info',
                'title' => 'Additional Building Info Recommended',
                'message' => "You have {$optional_missing} building" . ($optional_missing > 1 ? 's' : '') . " with missing recommended information (contact, inspection, or location details). Consider updating for best results.",
                'action_text' => 'Edit Buildings',
                'action_url' => '../../building/php/main.php',
                'icon' => 'fa-info-circle'
            ];
            $notification_count++;
        }
    }
} catch (PDOException $e) {
    error_log("Error checking buildings: " . $e->getMessage());
}

// 3. Check for unverified contact numbers
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as unverified_count 
        FROM user_phone_numbers 
        WHERE user_id = ? AND verified = 0
    ");
    $stmt->execute([$user_id]);
    $unverified_phones = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($unverified_phones['unverified_count'] > 0) {
        $notifications[] = [
            'type' => 'warning',
            'title' => 'Unverified Contact Numbers',
            'message' => "You have {$unverified_phones['unverified_count']} unverified phone number" . ($unverified_phones['unverified_count'] > 1 ? 's' : '') . ". Verify your contact numbers to receive important alerts.",
            'action_text' => 'Verify Numbers',
            'action_url' => '../../phone/php/UserPhone.php',
            'icon' => 'fa-phone'
        ];
        $notification_count++;
    }
} catch (PDOException $e) {
    error_log("Error checking unverified phones: " . $e->getMessage());
}

// 4. Check if user has any devices at all
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as device_count 
        FROM devices 
        WHERE user_id = ? AND is_active = 1
    ");
    $stmt->execute([$user_id]);
    $total_devices = $stmt->fetchColumn();
    
    if ($total_devices == 0) {
        $notifications[] = [
            'type' => 'info',
            'title' => 'No Devices Found',
            'message' => 'You don\'t have any active devices. Contact your administrator to get devices assigned to your account.',
            'action_text' => 'Contact Admin',
            'action_url' => '#',
            'icon' => 'fa-exclamation-circle'
        ];
        $notification_count++;
    }
} catch (PDOException $e) {
    error_log("Error checking devices: " . $e->getMessage());
}

// 5. Check for devices without recent data (offline devices)
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as offline_count 
        FROM devices d 
        WHERE d.user_id = ? 
        AND d.is_active = 1 
        AND d.device_id NOT IN (
            SELECT DISTINCT device_id 
            FROM fire_data 
            WHERE timestamp > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        )
    ");
    $stmt->execute([$user_id]);
    $offline_devices = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($offline_devices['offline_count'] > 0) {
        $notifications[] = [
            'type' => 'danger',
            'title' => 'Offline Devices',
            'message' => "You have {$offline_devices['offline_count']} device" . ($offline_devices['offline_count'] > 1 ? 's' : '') . " that " . ($offline_devices['offline_count'] > 1 ? 'are' : 'is') . " offline or not sending data.",
            'action_text' => 'Check Devices',
            'action_url' => '../../device/php/main.php',
            'icon' => 'fa-wifi'
        ];
        $notification_count++;
    }
} catch (PDOException $e) {
    error_log("Error checking offline devices: " . $e->getMessage());
}

// Store notification count in session for navigation badge
$_SESSION['notification_count'] = $notification_count;
?>

<style>
/* Add spacing between sidebar and menu toggle */
.nav.toggle {
    margin-left: 10px;
}

/* Notification Badge Styles */
.notification-badge-nav {
    position: relative;
    display: inline-block;
}

.notification-badge-count {
    position: absolute;
    top: -8px;
    right: -8px;
    background: #dc3545;
    color: white;
    border-radius: 50%;
    width: 18px;
    height: 18px;
    font-size: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
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

.notification-icon {
    cursor: pointer;
    transition: all 0.3s ease;
    color: #333;
    font-size: 18px;
}

.notification-icon:hover {
    transform: scale(1.1);
    color: #007bff;
}

/* Notification Dropdown Styles */
.notification-dropdown {
    position: absolute;
    top: 100%;
    right: 0;
    width: 350px;
    max-height: 400px;
    overflow-y: auto;
    background: white;
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
    z-index: 1050;
    display: none;
    border: 1px solid #e9ecef;
}

.notification-dropdown.show {
    display: block;
    animation: slideDown 0.3s ease-out;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.notification-header {
    padding: 15px;
    border-bottom: 1px solid #e9ecef;
    background: #f8f9fa;
    border-radius: 8px 8px 0 0;
}

.notification-header h6 {
    margin: 0;
    color: #333;
    font-weight: 600;
}

.notification-list {
    max-height: 300px;
    overflow-y: auto;
}

.notification-item {
    padding: 12px 15px;
    border-bottom: 1px solid #f1f3f4;
    transition: background-color 0.2s ease;
}

.notification-item:hover {
    background-color: #f8f9fa;
}

.notification-item:last-child {
    border-bottom: none;
}

.notification-item.alert-warning {
    border-left: 4px solid #ffc107;
}

.notification-item.alert-info {
    border-left: 4px solid #17a2b8;
}

.notification-item.alert-danger {
    border-left: 4px solid #dc3545;
}

.notification-item.alert-success {
    border-left: 4px solid #28a745;
}

.notification-title {
    font-weight: 600;
    font-size: 13px;
    color: #333;
    margin-bottom: 5px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.notification-title i {
    font-size: 14px;
}

.notification-message {
    font-size: 12px;
    color: #666;
    line-height: 1.4;
    margin-bottom: 8px;
}

.notification-action {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
}

.btn-notification {
    padding: 4px 8px;
    font-size: 11px;
    border-radius: 3px;
    text-decoration: none;
    transition: all 0.2s ease;
    border: none;
    cursor: pointer;
}

.btn-notification.btn-primary {
    background: #007bff;
    color: white;
}

.btn-notification.btn-primary:hover {
    background: #0056b3;
    color: white;
}

.notification-dismiss {
    font-size: 10px;
    color: #999;
    cursor: pointer;
    text-decoration: underline;
}

.notification-dismiss:hover {
    color: #666;
}

.notification-empty {
    padding: 20px;
    text-align: center;
    color: #666;
}

.notification-empty i {
    font-size: 24px;
    color: #28a745;
    margin-bottom: 10px;
}


@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

/* Mobile responsive */
@media (max-width: 768px) {
    .notification-dropdown {
        width: 300px;
        right: -50px;
    }
    
}

/* Modern Dropdown Menu Styling */
.dropdown-menu {
    display: none;
    position: absolute;
    top: 100%;
    right: 0;
    z-index: 1000;
    min-width: 200px;
    padding: 12px 0;
    margin: 8px 0 0;
    font-size: 14px;
    text-align: left;
    list-style: none;
    background-color: #ffffff;
    background-clip: padding-box;
    border: none;
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.12), 0 4px 20px rgba(0, 0, 0, 0.08);
    backdrop-filter: blur(10px);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    transform: translateY(-5px);
    opacity: 0;
}

.dropdown-menu.show {
    display: block;
    transform: translateY(0);
    opacity: 1;
}

.dropdown-item {
    display: flex;
    align-items: center;
    width: 100%;
    padding: 14px 20px;
    clear: both;
    font-weight: 500;
    color: #374151;
    text-align: inherit;
    white-space: nowrap;
    background-color: transparent;
    border: 0;
    text-decoration: none;
    transition: all 0.2s ease;
    position: relative;
    font-size: 14px;
    line-height: 1.4;
}

.dropdown-item:first-child {
    border-radius: 12px 12px 0 0;
}

.dropdown-item:last-child {
    border-radius: 0 0 12px 12px;
}

.dropdown-item:hover {
    color: #1f2937;
    text-decoration: none;
    background-color: #f8fafc;
    transform: translateX(4px);
}

.dropdown-item:focus {
    color: #1f2937;
    text-decoration: none;
    background-color: #f1f5f9;
    outline: none;
}

.dropdown-item i {
    margin-right: 12px;
    font-size: 16px;
    color: #6b7280;
    transition: color 0.2s ease;
}

.dropdown-item:hover i {
    color: #3b82f6;
}

/* Profile item specific styling */
.dropdown-item[href*="profile"] {
    border-bottom: 1px solid #f1f5f9;
    margin-bottom: 4px;
}

/* Logout item specific styling */
.dropdown-item#logoutBtn {
    color: #dc2626;
}

.dropdown-item#logoutBtn:hover {
    background-color: #fef2f2;
    color: #b91c1c;
}

.dropdown-item#logoutBtn i {
    color: #dc2626;
}

.dropdown-item#logoutBtn:hover i {
    color: #b91c1c;
}

/* Enhanced dropdown animations */
@keyframes dropdownFadeIn {
    from {
        opacity: 0;
        transform: translateY(-10px) scale(0.95);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.dropdown-menu.show {
    display: block;
    transform: translateY(0);
    opacity: 1;
    animation: dropdownFadeIn 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

/* Responsive improvements */
@media (max-width: 768px) {
    .dropdown-menu {
        min-width: 180px;
        right: -10px;
        margin-top: 5px;
    }
    
    .dropdown-item {
        padding: 12px 16px;
        font-size: 13px;
    }
    
    .dropdown-item i {
        font-size: 14px;
        margin-right: 10px;
    }
}
</style>

<div class="top_nav">
  <div class="nav_menu">
    <div class="nav toggle">
      <a id="menu_toggle"><i class="fa fa-bars"></i></a>
    </div>
    <nav class="nav navbar-nav">
      <ul class="navbar-right">
        <li class="nav-item dropdown open" style="padding-left: 15px;">
          <a href="javascript:;" class="user-profile dropdown-toggle" aria-haspopup="true" id="navbarDropdown" data-toggle="dropdown" aria-expanded="false">
          <img src="<?php echo $profile_image_url; ?>" alt="Profile Image"><?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Guest'; ?>
          </a>
          <div class="dropdown-menu dropdown-usermenu pull-right" aria-labelledby="navbarDropdown">
            <a class="dropdown-item" href="../../profile/php/main.php">
              <i class="fa fa-user"></i> Profile
            </a>
            <a class="dropdown-item" href="javascript:;" id="logoutBtn">
              <i class="fa fa-sign-out"></i> Log Out
            </a>
          </div>
        </li>

        <!-- Notification Badge and Guides -->
        <li class="nav-item" style="padding-left: 15px; position: relative; display: flex; align-items: center;">
          <div class="notification-badge-nav">
            <i class="fa fa-bell notification-icon" id="navNotificationIcon" title="Notifications"></i>
            <?php if ($notification_count > 0): ?>
              <span class="notification-badge-count"><?php echo $notification_count; ?></span>
            <?php endif; ?>
          </div>
          
          
          <!-- Guides Notification -->
          <?php include('guides_notification.php'); ?>
          
          <!-- Notification Dropdown -->
          <div class="notification-dropdown" id="notificationDropdown">
            <div class="notification-header">
              <h6><i class="fa fa-bell"></i> Notifications</h6>
            </div>
            <div class="notification-list">
              <?php if (empty($notifications)): ?>
                <div class="notification-item alert-success">
                  <div class="notification-title">
                    <i class="fa fa-check-circle"></i>
                    All Set!
                  </div>
                  <div class="notification-message">
                    Your system is properly configured. All devices are assigned, buildings are registered, and contact numbers are verified.
                  </div>
                </div>
              <?php else: ?>
                <?php foreach ($notifications as $notification): ?>
                  <div class="notification-item alert-<?php echo $notification['type']; ?>" data-notification-id="<?php echo md5($notification['title']); ?>">
                    <div class="notification-title">
                      <i class="fa <?php echo $notification['icon']; ?>"></i>
                      <?php echo htmlspecialchars($notification['title']); ?>
                    </div>
                    <div class="notification-message">
                      <?php echo htmlspecialchars($notification['message']); ?>
                    </div>
                    <div class="notification-action">
                      <a href="<?php echo $notification['action_url']; ?>" class="btn-notification btn-primary">
                        <i class="fa fa-arrow-right"></i>
                        <?php echo htmlspecialchars($notification['action_text']); ?>
                      </a>
                      <span class="notification-dismiss" onclick="dismissNotification(this)">
                        Dismiss
                      </span>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>
        </li>

        <li role="presentation" class="nav-item dropdown open">
          <ul class="dropdown-menu list-unstyled msg_list" role="menu" aria-labelledby="navbarDropdown1">
            <li class="nav-item">
              <a class="dropdown-item">
                <span class="image"><img src="<?php echo $profile_image_url; ?>" alt="Profile Image" class="img-circle profile_img"></span>
                <span>
                  <span class="time">3 mins ago</span>
                </span>
                <span class="message">
                  Film festivals used to be do-or-die moments for movie makers. They were where...
                </span>
              </a>
            </li>
            <!-- More list items here -->
          </ul>
        </li>
      </ul>
    </nav>
  </div>
</div>

<!-- SweetAlert JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Enhanced logout functionality
document.addEventListener('DOMContentLoaded', function() {
  // Function to clear all application data
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

  // Logout functionality
  document.getElementById('logoutBtn').addEventListener('click', function(e) {
    e.preventDefault();
    
    Swal.fire({
      title: 'Confirm Logout',
      html: 'Are you sure you want to log out?<br><small>This will end your current session.</small>',
      icon: 'question',
      showCancelButton: true,
      confirmButtonColor: '#d33',
      cancelButtonColor: '#3085d6',
      confirmButtonText: '<i class="fa fa-sign-out"></i> Log Out',
      cancelButtonText: 'Cancel',
      reverseButtons: true,
      backdrop: 'rgba(0,0,0,0.7)',
      allowOutsideClick: false,
      showLoaderOnConfirm: true,
      preConfirm: () => {
        return fetch('../../logout/logout.php', {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Cache-Control': 'no-cache, no-store',
            'Pragma': 'no-cache',
            'Content-Type': 'application/json'
          }
        })
        .then(response => {
          if (!response.ok) {
            throw new Error('Network response was not ok');
          }
          return response.json();
        })
        .catch(error => {
          Swal.showValidationMessage(`Request failed: ${error}`);
        });
      }
    }).then((result) => {
      if (result.isConfirmed) {
        clearAppData();
        
        // Final logout confirmation
        Swal.fire({
          title: 'Session Terminated',
          html: 'You have been securely logged out.<br>Redirecting to login page...',
          icon: 'success',
          timer: 2000,
          timerProgressBar: true,
          showConfirmButton: false,
          allowOutsideClick: false,
          willClose: () => {
            // Nuclear option for back button prevention
            window.location.replace('../../../index.php?logout=success');
            window.history.pushState(null, '', '../../../index.php');
            window.addEventListener('popstate', () => {
              window.history.pushState(null, '', '../../../index.php');
              window.location.replace('../../../index.php');
            });
          }
        });
      }
    });
  });

  // Strict back button prevention
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

  // Location permission modal (show only once)
  if (navigator.geolocation && !localStorage.getItem('locationModalShown')) {
    navigator.geolocation.getCurrentPosition(
      function(position) {
        // Location access granted, do nothing
      },
      function(error) {
        if (error.code === error.PERMISSION_DENIED) {
          Swal.fire({
            title: 'Enable Location',
            html: 'This application requires your location to provide full functionality. Please enable location services in your browser.',
            icon: 'info',
            confirmButtonText: 'Enable Location',
            allowOutsideClick: false,
            allowEscapeKey: false
          }).then(() => {
            // Try to request location again
            navigator.geolocation.getCurrentPosition(function() {}, function() {});
          });
          localStorage.setItem('locationModalShown', 'true');
        }
      }
    );
  } else if (!navigator.geolocation && !localStorage.getItem('locationModalShown')) {
    Swal.fire({
      title: 'Geolocation Not Supported',
      html: 'Your browser does not support geolocation. Some features may not work properly.',
      icon: 'warning',
      confirmButtonText: 'OK',
      allowOutsideClick: false,
      allowEscapeKey: false
    });
    localStorage.setItem('locationModalShown', 'true');
  }

  // User dropdown functionality
  const userDropdownToggle = document.querySelector('.user-profile.dropdown-toggle');
  const userDropdownMenu = document.querySelector('.dropdown-usermenu');
  
  if (userDropdownToggle && userDropdownMenu) {
    userDropdownToggle.addEventListener('click', function(e) {
      e.preventDefault();
      e.stopPropagation();
      userDropdownMenu.classList.toggle('show');
    });
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
      if (!userDropdownToggle.contains(e.target) && !userDropdownMenu.contains(e.target)) {
        userDropdownMenu.classList.remove('show');
      }
    });
    
    // Close dropdown when pressing Escape key
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') {
        userDropdownMenu.classList.remove('show');
      }
    });
  }

  // Notification dropdown functionality
  const navIcon = document.getElementById('navNotificationIcon');
  const notificationDropdown = document.getElementById('notificationDropdown');
  
  if (navIcon && notificationDropdown) {
    navIcon.addEventListener('click', function(e) {
      e.stopPropagation();
      notificationDropdown.classList.toggle('show');
    });
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
      if (!navIcon.contains(e.target) && !notificationDropdown.contains(e.target)) {
        notificationDropdown.classList.remove('show');
      }
    });
    
    // Close dropdown when pressing Escape key
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') {
        notificationDropdown.classList.remove('show');
      }
    });
  }

});

// Dismiss notification (store in localStorage to not show again)
function dismissNotification(element) {
    const notification = element.closest('.notification-item');
    const notificationId = notification.dataset.notificationId;
    
    // Store dismissed notification in localStorage
    const dismissedNotifications = JSON.parse(localStorage.getItem('dismissedNotifications') || '[]');
    dismissedNotifications.push(notificationId);
    localStorage.setItem('dismissedNotifications', JSON.stringify(dismissedNotifications));
    
    // Remove notification with animation
    notification.style.opacity = '0';
    notification.style.transform = 'translateX(100%)';
    notification.style.transition = 'all 0.3s ease';
    
    setTimeout(() => {
        notification.remove();
        updateNotificationCount();
    }, 300);
}

// Update notification count
function updateNotificationCount() {
    const remainingNotifications = document.querySelectorAll('.notification-item').length;
    const badge = document.querySelector('.notification-badge-count');
    const navIcon = document.getElementById('navNotificationIcon');
    
    if (badge) {
        if (remainingNotifications > 0) {
            badge.textContent = remainingNotifications;
        } else {
            badge.style.display = 'none';
        }
    }
    
    // Hide notification icon if no notifications
    if (remainingNotifications === 0 && navIcon) {
        navIcon.style.display = 'none';
    }
}

// Check for dismissed notifications on page load
document.addEventListener('DOMContentLoaded', function() {
    const dismissedNotifications = JSON.parse(localStorage.getItem('dismissedNotifications') || '[]');
    const notifications = document.querySelectorAll('.notification-item');
    
    notifications.forEach(notification => {
        const notificationId = notification.dataset.notificationId;
        if (dismissedNotifications.includes(notificationId)) {
            notification.remove();
        }
    });
    
    updateNotificationCount();
});
</script>