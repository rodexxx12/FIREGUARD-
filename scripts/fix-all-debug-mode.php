<?php
/**
 * Script to fix all debug mode issues in PHP files
 * Replaces display_errors = 1 with centralized error handling
 */

$rootDir = __DIR__ . '/..';
$filesToFix = [];
$fixedCount = 0;

// Files to skip (these may intentionally have debug mode)
$skipFiles = [
    'core/bootstrap.php',
    'core/config/config.php',
    'scripts/fix-debug-mode.php',
    'scripts/fix-all-debug-mode.php'
];

// Find all PHP files with display_errors = 1
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($rootDir),
    RecursiveIteratorIterator::LEAVES_ONLY
);

foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $relativePath = str_replace($rootDir . DIRECTORY_SEPARATOR, '', $file->getRealPath());
        $relativePath = str_replace('\\', '/', $relativePath);
        
        // Skip vendor directories and specific files
        if (strpos($relativePath, '/vendor/') !== false || 
            strpos($relativePath, '\\vendor\\') !== false ||
            strpos($relativePath, '/vendors/') !== false ||
            strpos($relativePath, '\\vendors\\') !== false ||
            strpos($relativePath, '/node_modules/') !== false ||
            in_array($relativePath, $skipFiles)) {
            continue;
        }
        
        $content = file_get_contents($file->getRealPath());
        
        // Check if file has display_errors = 1 or error_reporting(E_ALL) without proper handling
        if (preg_match('/ini_set\s*\(\s*[\'"]display_errors[\'"]\s*,\s*[\'"]?1[\'"]?\s*\)/i', $content)) {
            // Check if it already uses centralized error handling
            if (strpos($content, 'initializeErrorHandling') === false && 
                strpos($content, 'isProduction') === false) {
                $filesToFix[] = [
                    'path' => $file->getRealPath(),
                    'relative' => $relativePath
                ];
            }
        }
    }
}

echo "Found " . count($filesToFix) . " files that need fixing\n\n";

// Error handling pattern to add at the top
$errorHandlingCode = <<<'PHP'
// Use centralized error handling
require_once __DIR__ . '/../core/error_handler.php';
initializeErrorHandling();
PHP;

// Alternative pattern for files in root or different locations
$errorHandlingCodeRoot = <<<'PHP'
// Use centralized error handling
require_once __DIR__ . '/core/error_handler.php';
initializeErrorHandling();
PHP;

foreach ($filesToFix as $fileInfo) {
    $filePath = $fileInfo['path'];
    $relativePath = $fileInfo['relative'];
    $content = file_get_contents($filePath);
    $originalContent = $content;
    
    // Determine the correct path to error_handler.php
    $depth = substr_count($relativePath, '/');
    $errorHandlerPath = str_repeat('../', $depth) . 'core/error_handler.php';
    
    // Remove old error reporting
    $content = preg_replace(
        '/error_reporting\s*\(\s*E_ALL\s*\)\s*;\s*\n\s*ini_set\s*\(\s*[\'"]display_errors[\'"]\s*,\s*[\'"]?1[\'"]?\s*\)\s*;/i',
        '',
        $content
    );
    
    $content = preg_replace(
        '/ini_set\s*\(\s*[\'"]display_errors[\'"]\s*,\s*[\'"]?1[\'"]?\s*\)\s*;/i',
        '',
        $content
    );
    
    // Add centralized error handling after opening PHP tag
    if (preg_match('/^<\?php\s*\n/', $content)) {
        $content = preg_replace(
            '/^(<\?php\s*\n)/',
            "$1$errorHandlingCode\n\n",
            $content
        );
    } else {
        // Try different patterns
        $content = preg_replace(
            '/^(<\?php)/',
            "$1\n\n$errorHandlingCode\n",
            $content,
            1
        );
    }
    
    // Only write if content changed
    if ($content !== $originalContent) {
        file_put_contents($filePath, $content);
        echo "Fixed: $relativePath\n";
        $fixedCount++;
    }
}

echo "\nDone! Fixed $fixedCount files.\n";

