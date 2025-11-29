<?php
/**
 * Dependency Audit Script
 * 
 * Audits all dependencies for known vulnerabilities
 */

echo "🔍 Dependency Audit Tool\n";
echo "=======================\n\n";

$issues = [];
$vulnerabilities = [];

// Check Composer dependencies
echo "Checking Composer dependencies...\n";
$composerLock = __DIR__ . '/../composer.lock';
if (file_exists($composerLock)) {
    $composer = json_decode(file_get_contents($composerLock), true);
    if ($composer && isset($composer['packages'])) {
        foreach ($composer['packages'] as $package) {
            $name = $package['name'] ?? 'unknown';
            $version = $package['version'] ?? 'unknown';
            echo "  - {$name} ({$version})\n";
            
            // Check for known vulnerable packages
            // This is a placeholder - use actual vulnerability database
            if (strpos($name, 'symfony') !== false && version_compare($version, '4.0.0', '<')) {
                $vulnerabilities[] = [
                    'package' => $name,
                    'version' => $version,
                    'type' => 'composer',
                    'issue' => 'Potential vulnerability in older Symfony version'
                ];
            }
        }
    }
} else {
    echo "  ⚠️  composer.lock not found\n";
}

// Check npm dependencies
echo "\nChecking npm dependencies...\n";
$packageLock = __DIR__ . '/../package-lock.json';
if (file_exists($packageLock)) {
    $npm = json_decode(file_get_contents($packageLock), true);
    if ($npm && isset($npm['packages'])) {
        foreach ($npm['packages'] as $name => $package) {
            if ($name === '') continue;
            $version = $package['version'] ?? 'unknown';
            echo "  - {$name} ({$version})\n";
        }
    }
} else {
    echo "  ℹ️  package-lock.json not found (using vendors from CDN)\n";
}

// Check vendor directories manually
echo "\nChecking vendor directories...\n";
$vendorDirs = [
    __DIR__ . '/../vendors',
    __DIR__ . '/../vendor'
];

foreach ($vendorDirs as $vendorDir) {
    if (is_dir($vendorDir)) {
        $packages = glob($vendorDir . '/*/package.json');
        foreach ($packages as $pkgFile) {
            $pkg = json_decode(file_get_contents($pkgFile), true);
            if ($pkg && isset($pkg['name'])) {
                $name = $pkg['name'];
                $version = $pkg['version'] ?? 'unknown';
                echo "  - {$name} ({$version})\n";
            }
        }
    }
}

// Summary
echo "\n" . str_repeat("=", 50) . "\n";
echo "📊 Audit Summary\n";
echo str_repeat("=", 50) . "\n";

if (empty($vulnerabilities)) {
    echo "✅ No obvious vulnerabilities found\n";
    echo "\n⚠️  NOTE: This is a basic check. For comprehensive auditing:\n";
    echo "   1. Run 'composer audit' (if using Composer)\n";
    echo "   2. Run 'npm audit' (if using npm)\n";
    echo "   3. Use Snyk (https://snyk.io) for deeper analysis\n";
    echo "   4. Check CVE database for PHP packages\n";
} else {
    echo "⚠️  Potential vulnerabilities found:\n";
    foreach ($vulnerabilities as $vuln) {
        echo "   - {$vuln['package']} ({$vuln['version']}): {$vuln['issue']}\n";
    }
}

echo "\n✅ Audit complete!\n";






