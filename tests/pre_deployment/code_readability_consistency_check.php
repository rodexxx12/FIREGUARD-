<?php

/**
 * Code Readability & Consistency Checker
 *
 * This script implements the checklist in code_readability_consistency_checklist.md.
 * It performs lightweight static checks on the codebase WITHOUT modifying any files.
 *
 * Rules (best-effort, non-breaking):
 *  - Scan PHP and JS files for:
 *      - Very long functions
 *      - Deep nesting (many consecutive indents)
 *      - Excessive line length
 *  - Exclude vendor, logs, uploads, device, FireML and other generated/third‑party folders.
 *
 * Usage (from project root):
 *  php tests/pre_deployment/code_readability_consistency_check.php
 *
 * Exit codes:
 *   0 = no issues found
 *   1 = issues detected
 */

declare(strict_types=1);

$projectRoot = realpath(__DIR__ . '/../../');

if ($projectRoot === false) {
    fwrite(STDERR, "Could not determine project root.\n");
    exit(1);
}

// Directories to ignore entirely during scan.
$ignoreDirs = [
    'vendor',
    'vendors',
    'logs',
    'uploads',
    'assets',
    'build',
    'node_modules',
    'device',   // per user request
    'FireML',   // per user request (case-sensitive match)
];

// File extensions to scan for readability/consistency.
$extensions = ['php', 'js'];

$issues = [];

/**
 * Determine if a path should be ignored based on top-level directory name.
 */
function shouldIgnorePath(string $path, array $ignoreDirs, string $projectRoot): bool
{
    $relative = ltrim(str_replace('\\', '/', substr($path, strlen($projectRoot))), '/');
    $parts = explode('/', $relative);
    $top = $parts[0] ?? '';

    return in_array($top, $ignoreDirs, true);
}

/**
 * Check if file has one of the target extensions.
 */
function hasTargetExtension(string $file, array $extensions): bool
{
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    return in_array($ext, $extensions, true);
}

/**
 * Analyze a file for basic readability/consistency issues.
 */
function analyzeFile(string $file, array &$issues): void
{
    $lines = @file($file, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        return;
    }

    $maxLineLength = 140;
    $functionStart = null;
    $braceDepth = 0;
    $maxBraceDepthInFunction = 0;

    foreach ($lines as $index => $line) {
        $lineNumber = $index + 1;

        // Rule: long lines
        if (mb_strlen($line) > $maxLineLength) {
            $issues[] = sprintf(
                '%s:%d: Line exceeds %d characters (%d).',
                $file,
                $lineNumber,
                $maxLineLength,
                mb_strlen($line)
            );
        }

        // Track braces to approximate nesting depth
        $openCount = substr_count($line, '{');
        $closeCount = substr_count($line, '}');
        $braceDepth += $openCount;
        $braceDepth -= $closeCount;
        if ($braceDepth < 0) {
            $braceDepth = 0; // safety
        }

        // Rough detection of function start (PHP & JS)
        if ($functionStart === null && preg_match('/\bfunction\b/i', $line)) {
            $functionStart = $lineNumber;
            $maxBraceDepthInFunction = $braceDepth;
        }

        if ($functionStart !== null) {
            $maxBraceDepthInFunction = max($maxBraceDepthInFunction, $braceDepth);

            // Heuristic: function likely ended when brace depth drops to 0
            if ($braceDepth === 0) {
                $functionLength = $lineNumber - $functionStart + 1;

                // Rule: very long functions
                if ($functionLength > 120) {
                    $issues[] = sprintf(
                        '%s:%d: Function appears to be very long (~%d lines). Consider refactoring.',
                        $file,
                        $functionStart,
                        $functionLength
                    );
                }

                // Rule: deep nesting
                if ($maxBraceDepthInFunction > 5) {
                    $issues[] = sprintf(
                        '%s:%d: Function appears to have deep nesting (brace depth %d). Consider simplifying logic.',
                        $file,
                        $functionStart,
                        $maxBraceDepthInFunction
                    );
                }

                $functionStart = null;
                $maxBraceDepthInFunction = 0;
            }
        }
    }
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($projectRoot, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iterator as $fileInfo) {
    $path = $fileInfo->getPathname();

    if ($fileInfo->isDir()) {
        if (shouldIgnorePath($path, $ignoreDirs, $projectRoot)) {
            $iterator->next();
            $iterator->next(); // ensure we skip children
        }
        continue;
    }

    if (!hasTargetExtension($path, $extensions)) {
        continue;
    }

    if (shouldIgnorePath($path, $ignoreDirs, $projectRoot)) {
        continue;
    }

    analyzeFile($path, $issues);
}

if (empty($issues)) {
    echo "Code Readability & Consistency Check: OK (no issues found).\n";
    exit(0);
}

echo "Code Readability & Consistency Check: issues detected:\n\n";
foreach ($issues as $issue) {
    echo " - {$issue}\n";
}

exit(1);


