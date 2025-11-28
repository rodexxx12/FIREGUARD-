<?php
/**
 * Script to fix debug mode in all PHP files
 * This script finds and fixes display_errors = 1 in production files
 */

$filesToFix = [];
$rootDir = __DIR__ . '/..';

// Find all PHP files with display_errors = 1
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($rootDir),
    RecursiveIteratorIterator::LEAVES_ONLY
);

foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $filePath = $file->getRealPath();
        $content = file_get_contents($filePath);
        
        // Skip vendor directories
        if (strpos($filePath, '/vendor/') !== false || 
            strpos($filePath, '\\vendor\\') !== false ||
            strpos($filePath, '/vendors/') !== false ||
            strpos($filePath, '\\vendors\\') !== false) {
            continue;
        }
        
        // Check if file has display_errors = 1
        if (preg_match('/ini_set\s*\(\s*[\'"]display_errors[\'"]\s*,\s*[\'"]?1[\'"]?\s*\)/i', $content)) {
            $filesToFix[] = $filePath;
        }
    }
}

echo "Found " . count($filesToFix) . " files with display_errors = 1\n\n";

$secureErrorHandling = '// Environment-aware error handling
$isProduction = (getenv(\'APP_ENV\') === \'production\' || 
                 (isset($_SERVER[\'HTTP_HOST\']) && 
                  strpos($_SERVER[\'HTTP_HOST\'], \'localhost\') === false &&
                  strpos($_SERVER[\'HTTP_HOST\'], \'127.0.0.1\') === false));

if ($isProduction) {
    error_reporting(E_ALL);
    ini_set(\'display_errors\', \'0\');
    ini_set(\'log_errors\', \'1\');
    $logDir = __DIR__ . \'/../../logs\';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    ini_set(\'error_log\', $logDir . \'/php_errors.log\');
} else {
    error_reporting(E_ALL);
    ini_set(\'display_errors\', \'1\');
}';

foreach ($filesToFix as $file) {
    $content = file_get_contents($file);
    
    // Replace error_reporting(E_ALL) and ini_set('display_errors', 1)
    $patterns = [
        '/error_reporting\s*\(\s*E_ALL\s*\)\s*;\s*\n\s*ini_set\s*\(\s*[\'"]display_errors[\'"]\s*,\s*[\'"]?1[\'"]?\s*\)\s*;/i',
        '/error_reporting\s*\(\s*E_ALL\s*\)\s*;\s*\n\s*ini_set\s*\(\s*[\'"]display_errors[\'"]\s*,\s*1\s*\)\s*;/i',
    ];
    
    foreach ($patterns as $pattern) {
        $content = preg_replace($pattern, $secureErrorHandling, $content);
    }
    
    // Also handle standalone ini_set('display_errors', 1)
    $content = preg_replace(
        '/ini_set\s*\(\s*[\'"]display_errors[\'"]\s*,\s*[\'"]?1[\'"]?\s*\)\s*;/i',
        'ini_set(\'display_errors\', $isProduction ? \'0\' : \'1\');',
        $content
    );
    
    file_put_contents($file, $content);
    echo "Fixed: " . basename($file) . "\n";
}

echo "\nDone! Fixed " . count($filesToFix) . " files.\n";

