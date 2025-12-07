<?php
/**
 * Memory Usage & Long-Running Script Heuristic Checker
 *
 * Usage (from project root):
 *   php scripts/check-memory-usage.php
 *
 * What it does:
 *  - Scans PHP files (excluding vendor/build/logs/etc.) for patterns that often
 *    indicate long-running scripts or potential memory issues:
 *      - while (true) / for (;;)
 *      - set_time_limit(0) or very high time limits
 *      - ignore_user_abort(true)
 *  - Reports locations so you can review and ensure:
 *      - loops do useful work and eventually sleep/yield when appropriate
 *      - large in-memory collections are periodically cleared
 *      - memory_limit is configured reasonably for your workload
 *
 * NOTE: This is a heuristic tool. It does NOT prove absence of leaks, but it
 *       helps you systematically review the riskiest areas before deployment.
 */

declare(strict_types=1);

$projectRoot = realpath(__DIR__ . '/..');
if ($projectRoot === false) {
    fwrite(STDERR, "Could not determine project root.\n");
    exit(1);
}

$ignoreDirs = [
    'vendor',
    'vendors',
    'node_modules',
    'uploads',
    'logs',
    'assets',
    'build',
    '.git',
    '.idea',
    '.vscode',
    'tests',
];

function shouldIgnoreMemCheck(string $path, array $ignoreDirs, string $projectRoot): bool
{
    $relative = ltrim(str_replace('\\', '/', substr($path, strlen($projectRoot))), '/');
    $parts = explode('/', $relative);
    $top = $parts[0] ?? '';

    return in_array($top, $ignoreDirs, true);
}

echo "🔍 Scanning PHP files for potential long-running / memory-risk patterns...\n\n";

$patterns = [
    'while_true' => '/while\s*\(\s*true\s*\)/i',
    'for_ever'   => '/for\s*\(\s*;\s*;\s*\)/i',
    'no_timeout' => '/set_time_limit\s*\(\s*0\s*\)/i',
    'long_timeout' => '/set_time_limit\s*\(\s*([3-9]\d{2,}|[1-9]\d{3,})\s*\)/i', // >=300 seconds
    'ignore_abort' => '/ignore_user_abort\s*\(\s*true\s*\)/i',
];

$issues = [];

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($projectRoot, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iterator as $fileInfo) {
    /** @var SplFileInfo $fileInfo */
    $path = $fileInfo->getPathname();

    if ($fileInfo->isDir()) {
        if (shouldIgnoreMemCheck($path, $ignoreDirs, $projectRoot)) {
            $iterator->next();
            $iterator->next();
        }
        continue;
    }

    if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'php') {
        continue;
    }

    if (shouldIgnoreMemCheck($path, $ignoreDirs, $projectRoot)) {
        continue;
    }

    $lines = @file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        continue;
    }

    foreach ($lines as $idx => $line) {
        $lineNumber = $idx + 1;
        foreach ($patterns as $key => $regex) {
            if (preg_match($regex, $line)) {
                $issues[] = [
                    'type' => $key,
                    'file' => $path,
                    'line' => $lineNumber,
                    'code' => trim($line),
                ];
            }
        }
    }
}

if (empty($issues)) {
    echo "✅ No obvious long-running / memory-risk patterns detected by heuristics.\n";
    echo "   (You should still monitor memory on production using server tools.)\n";
    exit(0);
}

echo "⚠️ Potential long-running or memory-risk constructs found:\n\n";

foreach ($issues as $issue) {
    $relative = str_replace($projectRoot . DIRECTORY_SEPARATOR, '', $issue['file']);
    echo sprintf(
        " - [%s] %s:%d  %s\n",
        $issue['type'],
        $relative,
        $issue['line'],
        $issue['code']
    );
}

echo "\nReview these locations to ensure:\n";
echo " - Loops have appropriate sleep/yield and do not grow memory unbounded.\n";
echo " - Long-running scripts periodically free large arrays/objects.\n";
echo " - Time limits are intentional and appropriate for your environment.\n";

exit(1);















