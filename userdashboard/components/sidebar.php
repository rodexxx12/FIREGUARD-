<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/asset-path.php';

$sidebarUserName = 'Admin';

$nameCandidates = [
    $_SESSION['username'] ?? null,
    $_SESSION['full_name'] ?? null,
    $_SESSION['name'] ?? null,
    $_SESSION['admin_name'] ?? null,
];

foreach ($nameCandidates as $candidate) {
    if (!empty($candidate)) {
        $sidebarUserName = htmlspecialchars($candidate, ENT_QUOTES, 'UTF-8');
        break;
    }
}
?>
<div class="col-md-3 left_col">
  <div class="left_col scroll-view">
    <div class="navbar nav_title" style="border: 0;">
      <a href="../../mapping/php/map.php" class="site_title"> <span class="fireguard-gradient">FIREGUARD</span></a>
  </div>
<div class="clearfix"></div>
  <div class="profile clearfix">
    <div class="profile_pic">
    <img src="<?= USER_DASHBOARD_ASSET_BASE ?>/components/fireguard.png" alt="Fire Guard Logo" class="img-circle profile_img">
    </div>
    <div class="profile_info">
      <span>Welcome,</span>
      <h2><?php echo $sidebarUserName; ?></h2>
    </div>
  </div>
<br />
<div id="sidebar-menu" class="main_menu_side hidden-print main_menu">
  <div class="menu_section">
    <h3>General</h3>
    <ul class="nav side-menu">
      <li><a class="home-menu-trigger"><i class="fa fa-home"></i> Home <span class="fa fa-chevron-down"></span></a>
        <ul class="nav child_menu">
          <li><a href="../../mapping/php/main.php">Locate Incident</a></li>
          <li><a href="../../sensordata/php/index.php">Sensor List</a></li>
          <li><a href="../../registerDevice/php/main.php">Devices</a></li>
          <li><a href="../../phone/php/UserPhone.php">Phone Number</a></li>
        </ul>
      </li>
      <li><a class="home-menu-trigger"><i class="fa fa-building"></i> Building<span class="fa fa-chevron-down"></span></a>
        <ul class="nav child_menu">
          <li><a href="../../building/php/main.php">Register</a></li>
          <li><a href="../../building/php/buildings-table.php">Building Lists</a></li>
        </ul>
      </li>
    </ul>
</div>

<!-- Mobile Sidebar Overlay -->
<div id="mobile-sidebar-overlay" class="mobile-sidebar-overlay">
  <ul class="mobile-sidebar-menu">
    <li><a href="../../mapping/php/main.php">Locate Incident</a></li>
    <li><a href="../../sensordata/php/index.php">Sensor List</a></li>
    <li><a href="../../registerDevice/php/main.php">Devices</a></li>
    <li><a href="../../building/php/main.php">Buildings</a></li>
    <li><a href="../../phone/php/UserPhone.php">Phone Number</a></li>
  </ul>
</div>

<style>
/* Fix sidebar position - prevent scrolling */
.left_col {
    position: fixed !important;
    top: 0;
    left: 0;
    height: 100vh;
    overflow-y: auto;
    overflow-x: hidden;
    z-index: 1000;
}

.left_col.scroll-view {
    position: relative;
    height: 100%;
    overflow-y: auto;
    overflow-x: hidden;
}

/* Center the profile section */
.profile {
    display: flex;
    align-items: center;
    justify-content: center;  /* Center the profile items horizontally */
    gap: 5px;                /* Space between image and profile info */
}

/* Styling for the profile image container */
.profile_pic {
    display: flex;            /* Make the container a flexbox */
    justify-content: center;  /* Center the profile image horizontally */
    align-items: center;      /* Center the profile image vertically */
    margin-right: 5px;
}

/* Styling for the profile image */
.profile_img {
    width: 100px;  /* Increased size */
    height: 50px; /* Increased size */
    object-fit: cover;  /* Ensures the image is properly contained in the circle */
    border-radius: 50%;  /* Makes the image round */
    border: 1px solid #007bff;  /* Adds a blue border around the profile image */
    transition: transform 0.3s ease, box-shadow 0.3s ease;  /* Smooth hover transition */
}

.profile_img:hover {
    transform: scale(1.1);  /* Slight zoom-in effect on hover */
    box-shadow: 0 4px 10px rgba(0, 123, 255, 0.5);  /* Adds a soft shadow effect on hover */
}

/* Ensures the profile image is responsive */
@media (max-width: 768px) {
    .profile_img {
        width: 60px;  /* Smaller size for mobile devices */
        height: 60px;  /* Smaller size for mobile devices */
    }
}

/* Styling for profile info - bolder and smaller */
.profile_info {
    font-weight: 800;  /* Make text bolder */
    font-size: 1rem;  /* Decrease the size */
}

.profile_info span {
    font-weight: 800;  /* Make "Welcome," text bolder */
    font-size: 1rem;  /* Decrease the size */
}

.profile_info h2 {
    font-weight: 700;  /* Make "Admin" text bolder */
    font-size: 0.85em;  /* Decrease the size */
    margin: 0;  /* Remove default margin */
}

/* Dark to light orange gradient styling for FIREGUARD */
.fireguard-gradient {
    background: linear-gradient(135deg, #B84500 0%, #CC5500 20%, #FF6B00 40%, #FF8C42 60%, #FFA366 80%, #FFB380 100%);
    background-size: 200% 200%;
    text-align: center;
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    font-weight: 900;
    font-size: 1.2em;
    letter-spacing: 2px;
    text-shadow: none;
    display: inline-block;
    animation: gradient-shift 3s ease infinite;
}

/* Animated gradient effect */
@keyframes gradient-shift {
    0% {
        background-position: 0% 50%;
    }
    50% {
        background-position: 100% 50%;
    }
    100% {
        background-position: 0% 50%;
    }
}

/* Fallback for browsers that don't support gradient text */
@supports not (-webkit-background-clip: text) {
    .fireguard-gradient {
        color: #FF8C42;
        font-weight: 900;
        font-size: 1.2em;
        letter-spacing: 2px;
    }
}

/* Mobile Sidebar Overlay Styles */
.mobile-sidebar-overlay {
    display: none;
    position: fixed;
    width: 200px;
    background: #2A3F54;
    border-radius: 4px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4);
    z-index: 9999;
    overflow: hidden;
    opacity: 0;
    transform: translateX(-10px);
    transition: all 0.2s ease;
}

.mobile-sidebar-overlay.active {
    display: block;
    opacity: 1;
    transform: translateX(0);
}

.mobile-sidebar-menu {
    list-style: none;
    padding: 8px 0;
    margin: 0;
}

.mobile-sidebar-menu li {
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

.mobile-sidebar-menu li:last-child {
    border-bottom: none;
}

.mobile-sidebar-menu li a {
    display: block;
    padding: 12px 16px;
    color: #E7E7E7;
    text-decoration: none;
    transition: all 0.15s ease;
    font-size: 0.9em;
    font-weight: 400;
}

.mobile-sidebar-menu li a:hover {
    background: rgba(255, 255, 255, 0.1);
    color: #fff;
    padding-left: 20px;
}

/* Show overlay only on mobile devices */
@media (max-width: 768px) {
    .mobile-sidebar-overlay {
        display: block;
    }
    
    /* Make sidebar more compact on mobile but keep it visible */
    .left_col {
        width: 60px !important;
        min-width: 60px;
    }
    
    /* Hide text, show only icons on mobile */
    .left_col .profile_info,
    .left_col .site_title,
    .left_col .menu_section h3,
    .left_col .nav.side-menu > li > a {
        font-size: 0;
        padding: 10px 5px;
    }
    
    .left_col .nav.side-menu > li > a i {
        font-size: 20px;
        display: block;
        text-align: center;
        margin: 0;
    }
    
    .left_col .nav.side-menu > li > a .fa-chevron-down {
        display: none;
    }
    
    /* Hide child menu on mobile sidebar */
    .left_col .child_menu {
        display: none !important;
    }
    
    /* Keep profile image visible but smaller */
    .left_col .profile {
        flex-direction: column;
        padding: 10px 5px;
    }
    
    .left_col .profile_img {
        width: 40px;
        height: 40px;
    }
    
    .left_col .profile_pic {
        margin-right: 0;
        margin-bottom: 5px;
    }
}

/* Desktop: Hide overlay */
@media (min-width: 769px) {
    .mobile-sidebar-overlay {
        display: none !important;
    }
}

</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const homeTrigger = document.querySelector('.home-menu-trigger');
    const overlay = document.getElementById('mobile-sidebar-overlay');
    
    // Check if we're on mobile
    function isMobile() {
        return window.innerWidth <= 768;
    }
    
    // Position overlay beside home icon
    function positionOverlay() {
        if (!homeTrigger || !overlay || !isMobile()) return;
        
        const rect = homeTrigger.getBoundingClientRect();
        const sidebarWidth = 60; // Mobile sidebar width
        const overlayWidth = 200;
        const spacing = 8;
        
        overlay.style.left = (sidebarWidth + spacing) + 'px';
        overlay.style.top = rect.top + 'px';
    }
    
    // Toggle overlay
    function toggleOverlay() {
        if (isMobile() && overlay) {
            const isActive = overlay.classList.contains('active');
            if (!isActive) {
                positionOverlay();
            }
            overlay.classList.toggle('active');
        }
    }
    
    // Close overlay
    function closeOverlay() {
        if (overlay) {
            overlay.classList.remove('active');
        }
    }
    
    // Event listeners
    if (homeTrigger) {
        homeTrigger.addEventListener('click', function(e) {
            if (isMobile()) {
                e.preventDefault();
                e.stopPropagation();
                toggleOverlay();
            }
        });
    }
    
    // Close overlay when clicking outside
    document.addEventListener('click', function(e) {
        if (isMobile() && overlay && overlay.classList.contains('active')) {
            if (!overlay.contains(e.target) && !homeTrigger.contains(e.target)) {
                closeOverlay();
            }
        }
    });
    
    // Close overlay when clicking on menu items
    const menuItems = document.querySelectorAll('.mobile-sidebar-menu a');
    menuItems.forEach(function(item) {
        item.addEventListener('click', function() {
            setTimeout(closeOverlay, 100);
        });
    });
    
    // Handle window resize
    window.addEventListener('resize', function() {
        if (!isMobile()) {
            closeOverlay();
        } else if (overlay && overlay.classList.contains('active')) {
            positionOverlay();
        }
    });
    
    // Reposition on scroll
    window.addEventListener('scroll', function() {
        if (isMobile() && overlay && overlay.classList.contains('active')) {
            positionOverlay();
        }
    });
});
</script>
