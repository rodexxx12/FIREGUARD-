<?php
// Guides Notification Component
// This file handles guides-related notifications and displays

// Check if guides notifications are enabled
$guides_enabled = true; // You can make this configurable

if ($guides_enabled) {
    // Get all available guides
    $guides_notifications = [
        [
            'type' => 'info',
            'title' => 'Getting Started Guide',
            'message' => 'Learn the basics of using the DEFENDED system and its core features.',
            'icon' => 'fa-play-circle'
        ],
        [
            'type' => 'info',
            'title' => 'Device Management',
            'message' => 'How to assign, configure, and manage devices in your system.',
            'icon' => 'fa-microchip'
        ],
        [
            'type' => 'info',
            'title' => 'Sensor Data Monitoring',
            'message' => 'Understanding sensor readings and data interpretation.',
            'icon' => 'fa-chart-line'
        ],
        [
            'type' => 'info',
            'title' => 'Mapping & Visualization',
            'message' => 'Creating and managing maps for your devices and sensors.',
            'icon' => 'fa-map'
        ],
        [
            'type' => 'info',
            'title' => 'User Dashboard',
            'message' => 'Navigating and customizing your personal dashboard.',
            'icon' => 'fa-tachometer-alt'
        ],
        [
            'type' => 'info',
            'title' => 'System Administration',
            'message' => 'Advanced configuration and system management features.',
            'icon' => 'fa-cogs'
        ],
        [
            'type' => 'info',
            'title' => 'Troubleshooting',
            'message' => 'Common issues and their solutions for system problems.',
            'icon' => 'fa-tools'
        ],
        [
            'type' => 'info',
            'title' => 'Security Best Practices',
            'message' => 'Guidelines for maintaining system security and data protection.',
            'icon' => 'fa-shield-alt'
        ]
    ];
    
    // Display guides notifications if any exist
    if (!empty($guides_notifications)): ?>
        <div class="guides-notification-nav" style="margin-left: 15px; display: flex; align-items: center; justify-content: center;">
            <i class="fa fa-book guides-icon" id="guidesIcon" title="Guides" style="color: #28a745; font-size: 18px; cursor: pointer; vertical-align: middle;"></i>
            <span class="guides-badge-count"><?php echo count($guides_notifications); ?></span>
            
            <!-- Guides Dropdown -->
            <div class="guides-dropdown" id="guidesDropdown">
                <div class="guides-header">
                    <h6><i class="fa fa-book"></i> Help & Guides</h6>
                </div>
                <div class="guides-list">
                    <?php foreach ($guides_notifications as $guide): ?>
                        <div class="guides-item">
                            <div class="guides-title">
                                <i class="fa <?php echo $guide['icon']; ?>"></i>
                                <?php echo htmlspecialchars($guide['title']); ?>
                            </div>
                            <div class="guides-message">
                                <?php echo htmlspecialchars($guide['message']); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <style>
        /* Guides Notification Styles */
        .guides-notification-nav {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            vertical-align: middle;
        }
        
        .guides-badge-count {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #28a745;
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
        
        .guides-icon {
            cursor: pointer;
            transition: all 0.3s ease;
            color: #28a745;
            font-size: 18px;
        }
        
        .guides-icon:hover {
            transform: scale(1.1);
            color: #1e7e34;
        }
        
        /* Guides Dropdown Styles */
        .guides-dropdown {
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
            margin-top: 5px;
        }
        
        .guides-dropdown.show {
            display: block;
            animation: slideDown 0.3s ease-out;
        }
        
        .guides-header {
            padding: 15px;
            border-bottom: 1px solid #e9ecef;
            background: #f8f9fa;
            border-radius: 8px 8px 0 0;
        }
        
        .guides-header h6 {
            margin: 0;
            color: #333;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .guides-list {
            max-height: 300px;
            overflow-y: auto;
        }
        
        .guides-item {
            padding: 15px;
            border-bottom: 1px solid #f1f3f4;
            transition: background-color 0.2s ease;
        }
        
        .guides-item:hover {
            background-color: #f8f9fa;
        }
        
        .guides-item:last-child {
            border-bottom: none;
        }
        
        .guides-item .guides-title {
            font-weight: 600;
            font-size: 14px;
            color: #333;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .guides-item .guides-title i {
            font-size: 14px;
            color: #28a745;
        }
        
        .guides-item .guides-message {
            font-size: 12px;
            color: #666;
            line-height: 1.4;
            margin-bottom: 0;
        }
        
        
        /* Mobile responsive */
        @media (max-width: 768px) {
            .guides-dropdown {
                width: 300px;
                right: -50px;
            }
            
            .guides-notification-nav {
                margin-left: 10px !important;
            }
        }
        </style>
        
        <script>
        // Guides dropdown functionality
        document.addEventListener('DOMContentLoaded', function() {
            const guidesIcon = document.getElementById('guidesIcon');
            const guidesDropdown = document.getElementById('guidesDropdown');
            
            if (guidesIcon && guidesDropdown) {
                guidesIcon.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    guidesDropdown.classList.toggle('show');
                });
                
                // Close dropdown when clicking outside
                document.addEventListener('click', function(e) {
                    if (!guidesIcon.contains(e.target) && !guidesDropdown.contains(e.target)) {
                        guidesDropdown.classList.remove('show');
                    }
                });
                
                // Close dropdown when pressing Escape key
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') {
                        guidesDropdown.classList.remove('show');
                    }
                });
            }
        });
        </script>
    <?php endif;
}
?>
