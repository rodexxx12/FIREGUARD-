<?php
/**
 * Build & Dependency Verification Script
 *
 * Usage (from project root):
 *   php scripts/verify-build.php
 *
 * This script checks that:
 *  - composer.json is valid
 *  - a composer.lock file exists (for reproducible installs)
 *  - composer install --no-dev --dry-run succeeds
 *
 * Exit codes:
 *   0 = all checks passed
 *   1 = one or more checks failed
 */

declare(strict_types=1);

$projectRoot = realpath(__DIR__ . '/..');
if ($projectRoot === false) {
    fwrite(STDERR, "Could not determine project root.\n");
    exit(1);
}

chdir($projectRoot);

function runCommand(string $command): bool
{
    echo "Running: {$command}\n";
    passthru($command, $exitCode);
    echo "\n";
    return $exitCode === 0;
}

$allOk = true;

// 1) Validate composer.json
if (!file_exists('composer.json')) {
    echo "❌ composer.json not found in project root ({$projectRoot}).\n";
    $allOk = false;
} else {
    if (!runCommand('composer validate --no-check-publish')) {
        echo "❌ composer.json validation failed.\n";
        $allOk = false;
    } else {
        echo "✅ composer.json is valid.\n\n";
    }
}

// 2) Ensure composer.lock exists
if (!file_exists('composer.lock')) {
    echo "❌ composer.lock is missing. Run 'composer install' and commit the lock file for reproducible builds.\n";
    $allOk = false;
} else {
    echo "✅ composer.lock found.\n\n";
}

// 3) Dry-run install (no-dev) to ensure dependencies are resolvable
if (!runCommand('composer install --no-dev --prefer-dist --no-interaction --dry-run')) {
    echo "❌ composer install (dry-run) failed. Check dependency constraints.\n";
    $allOk = false;
} else {
    echo "✅ composer install (dry-run) succeeded.\n\n";
}

if ($allOk) {
    echo "Build verification: OK (dependencies are reproducible and installable).\n";
    exit(0);
}

echo "Build verification: FAILED (see messages above).\n";
exit(1);















