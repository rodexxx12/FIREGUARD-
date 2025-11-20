<?php 
// Profile data initialization
$profile_image_url = '../../images/profile1.jpg'; // Default profile image
// require_once __DIR__ . '../../alarm/alarm.php';

// Session-dependent code removed - navigation renders for all users
$superadmin_id = null; // No longer using session-based admin ID

// Database connection removed - no longer needed for navigation





?>

<div class="top_nav">
  <div class="nav_menu">
    <div class="nav toggle">
      <a id="menu_toggle"><i class="fa fa-bars"></i></a>
    </div>
    <nav class="nav navbar-nav">
      <ul class="navbar-right">
        <!-- Static Logout Button -->
        <li class="nav-item" style="padding-left: 15px;">
          <a href="javascript:;" class="user-profile" id="logoutBtn">
            <span class="role-badge role-admin">Logout</span>
          </a>
        </li>
        <!-- Backup Database Button -->
        <li class="nav-item" style="padding-left: 15px;">
          <a href="javascript:;" class="user-profile" id="backupBtn">
            <span class="role-badge role-admin" style="background: #28a745;"><i class="fa fa-hdd-o"></i> Backup DB</span>
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

    // Enhanced logout functionality (your exact implementation)
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
                return fetch('../../logout/php/logout.php', {
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

    // Backup Database functionality
    document.getElementById('backupBtn').addEventListener('click', function(e) {
        e.preventDefault();
        
        Swal.fire({
            title: 'Create Database Backup',
            html: 'Are you sure you want to create a backup of the database?<br><br><strong>What will be backed up:</strong><ul style="text-align: left;"><li>All tables and data</li><li>Database structure</li><li>Stored procedures</li><li>Triggers and events</li></ul>',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fa fa-download"></i> Create Backup',
            cancelButtonText: 'Cancel',
            reverseButtons: true,
            backdrop: 'rgba(0,0,0,0.7)',
            allowOutsideClick: false,
            showLoaderOnConfirm: true,
            preConfirm: () => {
                return fetch('../../backup/create_backup.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'backup_type=manual'
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
            if (result.isConfirmed && result.value) {
                if (result.value.success) {
                    Swal.fire({
                        title: 'Backup Successful!',
                        html: `<strong>Backup created successfully!</strong><br><br>
                               <div style="text-align: left;">
                               <p><strong>Filename:</strong> ${result.value.backup_filename}</p>
                               <p><strong>File size:</strong> ${result.value.file_size_mb} MB</p>
                               </div>`,
                        icon: 'success',
                        showCancelButton: true,
                        confirmButtonColor: '#28a745',
                        confirmButtonText: '<i class="fa fa-download"></i> Download',
                        cancelButtonText: 'Close',
                        reverseButtons: true
                    }).then((downloadResult) => {
                        if (downloadResult.isConfirmed) {
                            // Store download URL for verification
                            const downloadUrl = result.value.download_url;
                            const filename = result.value.backup_filename;
                            
                            // Open download URL - handle errors via window.onerror
                            const downloadWindow = window.open(downloadUrl, '_blank');
                            
                            // Check if file exists by attempting to fetch it
                            fetch(downloadUrl, { method: 'HEAD' })
                                .then(response => {
                                    if (!response.ok) {
                                        // File doesn't exist or there was an error
                                        downloadWindow.close();
                                        Swal.fire({
                                            title: 'File Not Found',
                                            html: `The backup file was not found on the server.<br><br>
                                                   <strong>Filename:</strong> ${filename}<br><br>
                                                   This may happen if:<br>
                                                   • The backup process was interrupted<br>
                                                   • The file was moved or deleted<br>
                                                   • There's a server path mismatch<br><br>
                                                   Please try creating a new backup or check the <a href="../../backup/index.php">Backup Files</a> page.`,
                                            icon: 'error',
                                            confirmButtonText: 'Go to Backup Files',
                                            cancelButtonText: 'Close',
                                            showCancelButton: true
                                        }).then((result) => {
                                            if (result.isConfirmed) {
                                                window.location.href = '../../backup/index.php';
                                            }
                                        });
                                    } else {
                                        // File exists, download should work
                                        setTimeout(() => {
                                            Swal.fire({
                                                title: 'Download Started',
                                                text: 'Your backup file should start downloading shortly.',
                                                icon: 'success',
                                                timer: 2000,
                                                showConfirmButton: false
                                            });
                                        }, 500);
                                    }
                                })
                                .catch(error => {
                                    downloadWindow.close();
                                    console.error('Download check failed:', error);
                                    Swal.fire({
                                        title: 'Download Error',
                                        html: 'Unable to verify the backup file. Please try again or visit the <a href="../../backup/index.php">Backup Files</a> page to download it manually.',
                                        icon: 'warning',
                                        confirmButtonText: 'Go to Backup Files',
                                        cancelButtonText: 'Close',
                                        showCancelButton: true
                                    }).then((result) => {
                                        if (result.isConfirmed) {
                                            window.location.href = '../../backup/index.php';
                                        }
                                    });
                                });
                        }
                    });
                } else {
                    Swal.fire({
                        title: 'Backup Failed',
                        text: result.value.message || 'Failed to create backup',
                        icon: 'error',
                        confirmButtonColor: '#dc3545'
                    });
                }
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
    
    // Announcement system (guarded)
    (function initAnnouncements() {
        const announcementIcon = document.getElementById('announcementIcon');
        const announcementBadge = document.getElementById('announcementCount');
        const ANNOUNCEMENT_API_URL = '../../../login/functions/get_announcements.php';
        const HIGH_PRIORITY_WINDOW_MS = 24 * 60 * 60 * 1000; // 24 hours

        if (!announcementIcon || !announcementBadge) {
            console.log('Announcement UI not found; skipping announcement polling.');
            return;
        }

        let announcementCount = 0;
        let announcements = [];

        checkAnnouncements();
        setInterval(checkAnnouncements, 60000); // Check every minute

        function checkAnnouncements() {
            fetch(ANNOUNCEMENT_API_URL, {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'Cache-Control': 'no-cache'
                }
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Announcement API responded with ${response.status}`);
                }

                const contentType = response.headers.get('content-type') || '';
                if (!contentType.includes('application/json')) {
                    return response.text().then(text => {
                        throw new Error('Announcement API returned non-JSON payload');
                    });
                }

                return response.json();
            })
            .then(data => {
                if (!(data && data.success)) {
                    return;
                }

                const allAnnouncements = Array.isArray(data.announcements) ? data.announcements : [];
                const cutoffTime = Date.now() - HIGH_PRIORITY_WINDOW_MS;
                const newAnnouncements = allAnnouncements.filter(announcement => {
                    if (!announcement || !announcement.priority || !announcement.start_date) {
                        return false;
                    }
                    const priority = String(announcement.priority).toLowerCase();
                    const startTime = new Date(announcement.start_date).getTime();
                    return priority === 'high' && !Number.isNaN(startTime) && startTime >= cutoffTime;
                });

                if (newAnnouncements.length !== announcementCount) {
                    announcementCount = newAnnouncements.length;
                    announcements = newAnnouncements;
                    updateAnnouncementBadge();

                    if (newAnnouncements.length > 0) {
                        showAnnouncementToast(newAnnouncements[0]);
                    }
                }
            })
            .catch(error => {
                console.error('Error checking announcements:', error);
            });
        }

        function updateAnnouncementBadge() {
            if (announcementCount > 0) {
                announcementBadge.textContent = announcementCount;
                announcementBadge.style.display = 'block';
                announcementIcon.classList.add('has-announcements');

                if (announcementCount > 3) {
                    announcementBadge.className = 'badge bg-danger announcement-badge';
                } else if (announcementCount > 1) {
                    announcementBadge.className = 'badge bg-warning announcement-badge';
                } else {
                    announcementBadge.className = 'badge bg-orange announcement-badge';
                }
            } else {
                announcementBadge.style.display = 'none';
                announcementIcon.classList.remove('has-announcements');
            }
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

        announcementIcon.addEventListener('click', function() {
            this.style.transform = 'scale(0.95)';
            setTimeout(() => {
                this.style.transform = 'scale(1)';
            }, 150);
        });
    })();

});
</script>

<style>

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


/* Hide Bootstrap caret on profile dropdown */
#navbarDropdown.dropdown-toggle::after {
    display: none !important;
    content: none !important;
}


/* User menu actions only - no header needed */
.user-menu-actions {
    padding: 8px 4px;
}

/* Hide sidebar burger/toggle */
.nav_menu .nav.toggle, #menu_toggle { display: none !important; }

/* Backup button styles */
#backupBtn .role-badge {
    background: #28a745 !important;
}

#backupBtn .role-badge i {
    margin-right: 5px;
}

#backupBtn:hover .role-badge {
    transform: translateY(-1px);
    background: #218838 !important;
}

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
</style>