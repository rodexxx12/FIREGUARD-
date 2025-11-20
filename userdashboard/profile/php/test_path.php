<?php
// Test file to verify path resolution
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Path Test</h2>";

// Test the path to functions.php
$functionsPath = __DIR__ . '/../functions/functions.php';
echo "<p><strong>Functions path:</strong> " . $functionsPath . "</p>";
echo "<p><strong>File exists:</strong> " . (file_exists($functionsPath) ? 'Yes' : 'No') . "</p>";

if (file_exists($functionsPath)) {
    echo "<p style='color: green;'><strong>✓ Functions file found!</strong></p>";
    
    // Try to include it
    try {
        require_once $functionsPath;
        echo "<p style='color: green;'><strong>✓ Functions file loaded successfully!</strong></p>";
        
        // Check if variables are available
        if (isset($errors)) {
            echo "<p><strong>✓ \$errors variable is available</strong></p>";
        }
        
        if (isset($conn)) {
            echo "<p><strong>✓ \$conn variable is available</strong></p>";
        }
        
        if (isset($admin)) {
            echo "<p><strong>✓ \$admin variable is available</strong></p>";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'><strong>✗ Error loading functions file:</strong> " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p style='color: red;'><strong>✗ Functions file not found!</strong></p>";
    
    // List directory contents to help debug
    $functionsDir = __DIR__ . '/../functions/';
    echo "<p><strong>Functions directory:</strong> " . $functionsDir . "</p>";
    echo "<p><strong>Functions directory exists:</strong> " . (file_exists($functionsDir) ? 'Yes' : 'No') . "</p>";
    
    if (file_exists($functionsDir)) {
        echo "<p><strong>Files in functions directory:</strong></p>";
        echo "<ul>";
        $files = scandir($functionsDir);
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..') {
                echo "<li>" . $file . "</li>";
            }
        }
        echo "</ul>";
    }
}

echo "<p><a href='main.php'>Try Main Page</a></p>";
?> 