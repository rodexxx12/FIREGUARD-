<?php
// Start session to access user authentication
session_start();

// Include the announcement helper functions
require_once 'php/announcement_helper.php';

// Check if user is logged in
if (!isUserAuthenticated()) {
    header('Location: ../../../index.php');
    exit();
}

// Get superadmin announcements
$superadminAnnouncements = getSuperadminAnnouncements(true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Superadmin Announcements - Fire Detection System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link href="../../vendors/nprogress/nprogress.css" rel="stylesheet">
    <link href="../../vendors/bootstrap-progressbar/css/bootstrap-progressbar-3.3.4.min.css" rel="stylesheet">
    <link href="../../vendors/bootstrap-daterangepicker/daterangepicker.css" rel="stylesheet">
    <link href="../../build/css/custom.min.css" rel="stylesheet">
    <style>
        .superadmin-announcement {
            border-radius: 15px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
            border: none;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .superadmin-announcement:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .superadmin-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0;
            padding: 1.5rem;
            position: relative;
            overflow: hidden;
        }
        
        .superadmin-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="white" opacity="0.1"/><circle cx="75" cy="75" r="1" fill="white" opacity="0.1"/><circle cx="50" cy="10" r="0.5" fill="white" opacity="0.1"/><circle cx="10" cy="60" r="0.5" fill="white" opacity="0.1"/><circle cx="90" cy="40" r="0.5" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            opacity: 0.3;
        }
        
        .superadmin-badge {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-weight: bold;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.3);
        }
        
        .priority-high { 
            border-left: 5px solid #dc3545; 
            background: linear-gradient(135deg, #fff5f5 0%, #ffe6e6 100%);
        }
        
        .priority-medium { 
            border-left: 5px solid #ffc107; 
            background: linear-gradient(135deg, #fffcf5 0%, #fff8e6 100%);
        }
        
        .priority-low { 
            border-left: 5px solid #28a745; 
            background: linear-gradient(135deg, #f5fff7 0%, #e6ffe6 100%);
        }
        
        .announcement-content {
            padding: 2rem;
            line-height: 1.8;
            font-size: 1.1rem;
        }
        
        .announcement-meta {
            background: #f8f9fa;
            padding: 1rem 2rem;
            border-radius: 0 0 15px 15px;
            border-top: 1px solid #e9ecef;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .meta-item i {
            color: #667eea;
            width: 16px;
        }
        
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: #dee2e6;
            margin-bottom: 1rem;
        }
        
        .refresh-btn {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 50%;
            width: 60px;
            height: 60px;
            color: white;
            font-size: 1.5rem;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
            transition: all 0.3s ease;
        }
        
        .refresh-btn:hover {
            transform: rotate(180deg);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
        }
        
        .announcement-date {
            font-size: 0.9rem;
            color: #6c757d;
            font-style: italic;
        }
        
        .author-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }
        
        .author-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 0.9rem;
        }
        
        .priority-indicator {
            position: absolute;
            top: 1rem;
            right: 1rem;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            border: 2px solid white;
        }
        
        .priority-indicator.high { background-color: #dc3545; }
        .priority-indicator.medium { background-color: #ffc107; }
        .priority-indicator.low { background-color: #28a745; }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .announcement-item {
            animation: fadeInUp 0.6s ease-out;
        }
        
        .announcement-item:nth-child(1) { animation-delay: 0.1s; }
        .announcement-item:nth-child(2) { animation-delay: 0.2s; }
        .announcement-item:nth-child(3) { animation-delay: 0.3s; }
        .announcement-item:nth-child(4) { animation-delay: 0.4s; }
        .announcement-item:nth-child(5) { animation-delay: 0.5s; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1 class="h3 mb-0">
                            <i class="fas fa-bullhorn text-primary me-2"></i>
                            Superadmin Announcements
                        </h1>
                        <p class="text-muted mb-0">Important announcements from system administrators</p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="php/index.php" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-left me-2"></i>
                            Back to All Announcements
                        </a>
                        <button class="btn btn-primary" onclick="refreshAnnouncements()">
                            <i class="fas fa-sync-alt me-2"></i>
                            Refresh
                        </button>
                    </div>
                </div>

                <!-- Announcements Container -->
                <div id="announcementsContainer">
                    <?php if (empty($superadminAnnouncements)): ?>
                        <div class="empty-state">
                            <i class="fas fa-bullhorn"></i>
                            <h4>No Superadmin Announcements</h4>
                            <p>There are currently no announcements from superadmins.</p>
                            <p class="text-muted">Check back later for important updates.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($superadminAnnouncements as $index => $announcement): ?>
                            <div class="card superadmin-announcement announcement-item priority-<?php echo htmlspecialchars($announcement['priority']); ?>">
                                <div class="superadmin-header">
                                    <div class="priority-indicator <?php echo htmlspecialchars($announcement['priority']); ?>"></div>
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h3 class="h5 mb-2"><?php echo htmlspecialchars($announcement['title']); ?></h3>
                                            <div class="superadmin-badge">
                                                <i class="fas fa-crown me-2"></i>
                                                Superadmin Announcement
                                            </div>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-<?php echo $announcement['priority'] === 'high' ? 'danger' : ($announcement['priority'] === 'medium' ? 'warning' : 'success'); ?>">
                                                <?php echo ucfirst(htmlspecialchars($announcement['priority'])); ?> Priority
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="announcement-content">
                                    <div class="mb-3">
                                        <?php echo nl2br(htmlspecialchars($announcement['content'])); ?>
                                    </div>
                                    
                                    <div class="author-info">
                                        <div class="author-avatar">
                                            <?php 
                                            $authorName = htmlspecialchars($announcement['author_full_name'] ?? $announcement['author_name'] ?? 'Superadmin');
                                            echo strtoupper(substr($authorName, 0, 1)); 
                                            ?>
                                        </div>
                                        <div>
                                            <strong><?php echo $authorName; ?></strong>
                                            <div class="announcement-date">
                                                Published on <?php echo date('F j, Y \a\t g:i A', strtotime($announcement['start_date'])); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="announcement-meta">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="meta-item">
                                                <i class="fas fa-calendar-alt"></i>
                                                <span>Start: <?php echo date('M j, Y g:i A', strtotime($announcement['start_date'])); ?></span>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="meta-item">
                                                <i class="fas fa-clock"></i>
                                                <span>Priority: <?php echo ucfirst(htmlspecialchars($announcement['priority'])); ?></span>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="meta-item">
                                                <i class="fas fa-bullseye"></i>
                                                <span>Target: <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($announcement['target_type']))); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    <?php if ($announcement['end_date']): ?>
                                        <div class="row mt-2">
                                            <div class="col-12">
                                                <div class="meta-item">
                                                    <i class="fas fa-calendar-times"></i>
                                                    <span>Expires: <?php echo date('M j, Y g:i A', strtotime($announcement['end_date'])); ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Floating Refresh Button -->
    <button class="refresh-btn" onclick="refreshAnnouncements()" title="Refresh Announcements">
        <i class="fas fa-sync-alt"></i>
    </button>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function refreshAnnouncements() {
            const refreshBtn = document.querySelector('.refresh-btn i');
            refreshBtn.style.animation = 'spin 1s linear infinite';
            
            // Reload the page
            setTimeout(() => {
                location.reload();
            }, 500);
        }

        // Auto-refresh every 5 minutes
        setInterval(() => {
            refreshAnnouncements();
        }, 300000);

        // Add CSS for spin animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes spin {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
        `;
        document.head.appendChild(style);

        // Show welcome message for first-time visitors
        if (!localStorage.getItem('superadminAnnouncementsViewed')) {
            Swal.fire({
                title: 'Welcome to Superadmin Announcements!',
                text: 'Here you can view important announcements from system administrators.',
                icon: 'info',
                confirmButtonText: 'Got it!',
                confirmButtonColor: '#667eea'
            });
            localStorage.setItem('superadminAnnouncementsViewed', 'true');
        }
    </script>
</body>
</html> 