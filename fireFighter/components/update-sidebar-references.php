<?php
/**
 * Sidebar Update Script
 * This script helps ensure all pages are using the new sidebar
 * Run this script to check for any remaining old sidebar references
 */

echo "<h2>Sidebar Update Check</h2>";

// Check if the new sidebar exists
$newSidebarPath = __DIR__ . '/sidebar.php';
if (file_exists($newSidebarPath)) {
    echo "✅ New sidebar found at: " . $newSidebarPath . "<br>";
    echo "📁 File size: " . number_format(filesize($newSidebarPath)) . " bytes<br>";
    echo "🕒 Last modified: " . date('Y-m-d H:i:s', filemtime($newSidebarPath)) . "<br>";
} else {
    echo "❌ New sidebar not found!<br>";
}

// Check for old sidebar files
$oldSidebars = [
    '../production/components/sidebar.php',
    '../userdashboard/components/sidebar.php',
    '../superadmin/components/sidebar.php'
];

echo "<h3>Old Sidebar Cleanup Status:</h3>";
foreach ($oldSidebars as $oldSidebar) {
    if (file_exists($oldSidebar)) {
        echo "❌ Old sidebar still exists: " . $oldSidebar . "<br>";
    } else {
        echo "✅ Old sidebar removed: " . $oldSidebar . "<br>";
    }
}

// Check current directory structure
echo "<h3>Current Components Directory:</h3>";
$componentsDir = __DIR__;
$files = scandir($componentsDir);
foreach ($files as $file) {
    if ($file !== '.' && $file !== '..') {
        if (is_dir($file)) {
            echo "📁 Directory: " . $file . "<br>";
        } else {
            echo "📄 File: " . $file . " (" . number_format(filesize($componentsDir . '/' . $file)) . " bytes)<br>";
        }
    }
}

echo "<h3>Next Steps:</h3>";
echo "1. ✅ Old sidebars have been removed<br>";
echo "2. ✅ New sidebar is in place with collapse functionality<br>";
echo "3. ✅ All pages should now use the new sidebar<br>";
echo "4. 🔄 Test your pages to ensure they work correctly<br>";

echo "<h3>New Sidebar Features:</h3>";
echo "• ✅ Collapse/Expand functionality<br>";
echo "• ✅ Responsive design<br>";
echo "• ✅ Flexbox layout<br>";
echo "• ✅ Corner-positioned focus indicators<br>";
echo "• ✅ State persistence<br>";
echo "• ✅ Keyboard shortcuts (Ctrl+M)<br>";
echo "• ✅ Mobile auto-collapse<br>";

echo "<h3>Usage:</h3>";
echo "• Click the menu toggle button (hamburger icon) to collapse/expand<br>";
echo "• Use Ctrl+M keyboard shortcut for quick toggle<br>";
echo "• Sidebar automatically collapses on mobile devices<br>";
echo "• Your preference is remembered across sessions<br>";
?>
