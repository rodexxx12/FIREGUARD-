<?php
/**
 * Unused Code Finder (best-effort, non-destructive)
 *
 * Scans PHP and JS files to find functions that are never referenced
 * anywhere else in the project (by simple name matching).
 *
 * Usage (from project root):
 *   php scripts/find_unused_code.php
 *
 * Notes / Limitations:
 *  - This is a heuristic tool. It can produce false positives and
 *    false negatives, especially for:
 *      - Dynamically-called functions / methods
 *      - Callbacks referenced as strings or via reflection
 *      - Framework magic (e.g., routes/controllers inferred by name)
 *  - It does NOT delete anything. It only reports candidates.
 */

declare(strict_types=1);

$projectRoot = realpath(__DIR__ . '/..');
if ($projectRoot === false) {
    fwrite(STDERR, "Could not determine project root.\n");
    exit(1);
}

// Directories to ignore
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
    'tests',       // we care about unused code in app, not tests
];

// File extensions to scan
$extensions = ['php', 'js'];

/**
 * Determine if a path should be ignored based on top-level directory name.
 */
function shouldIgnore(string $path, array $ignoreDirs, string $projectRoot): bool
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
 * Collect function definitions from a file.
 *
 * Returns array of ['name' => string, 'file' => string, 'line' => int].
 */
function collectFunctionDefinitions(string $file): array
{
    $results = [];

    $contents = @file_get_contents($file);
    if ($contents === false) {
        return $results;
    }

    $lines = preg_split('/\R/', $contents);
    if (!is_array($lines)) {
        return $results;
    }

    // Simple regex for PHP/JS functions:
    // - PHP: function foo( or function foo (
    // - JS:  function foo( or const foo = (...) => { ... }
    $patternPhp = '/\bfunction\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/';
    $patternJsArrow = '/\bconst\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*=\s*\([^)]*\)\s*=>/';

    foreach ($lines as $idx => $line) {
        if (preg_match_all($patternPhp, $line, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $results[] = [
                    'name' => $m[1],
                    'file' => $file,
                    'line' => $idx + 1,
                ];
            }
        }

        if (preg_match_all($patternJsArrow, $line, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $results[] = [
                    'name' => $m[1],
                    'file' => $file,
                    'line' => $idx + 1,
                ];
            }
        }
    }

    return $results;
}

/**
 * Collect all function *references* (simple name + "(" match).
 */
function collectFunctionReferences(string $file): array
{
    $results = [];

    $contents = @file_get_contents($file);
    if ($contents === false) {
        return $results;
    }

    // Very simple tokenization: match foo( and capture foo
    if (preg_match_all('/\b([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/', $contents, $matches)) {
        foreach ($matches[1] as $name) {
            $results[] = $name;
        }
    }

    return $results;
}

echo "🔍 Scanning project for unused functions (PHP/JS)...\n\n";

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($projectRoot, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

$definitions = []; // name => [defs...]
$references = [];  // name => count

foreach ($iterator as $fileInfo) {
    /** @var SplFileInfo $fileInfo */
    $path = $fileInfo->getPathname();

    if ($fileInfo->isDir()) {
        if (shouldIgnore($path, $ignoreDirs, $projectRoot)) {
            $iterator->next();
            $iterator->next();
        }
        continue;
    }

    if (!hasTargetExtension($path, $extensions)) {
        continue;
    }

    if (shouldIgnore($path, $ignoreDirs, $projectRoot)) {
        continue;
    }

    // Collect definitions
    foreach (collectFunctionDefinitions($path) as $def) {
        $name = $def['name'];
        $definitions[$name] = $definitions[$name] ?? [];
        $definitions[$name][] = $def;
    }

    // Collect references
    foreach (collectFunctionReferences($path) as $refName) {
        if (!isset($references[$refName])) {
            $references[$refName] = 0;
        }
        $references[$refName]++;
    }
}

// Now find functions that are defined but never referenced
$unused = [];

foreach ($definitions as $name => $defs) {
    $refCount = $references[$name] ?? 0;

    // If only one reference and it is the definition itself, we still treat as 0 external references.
    if ($refCount === 0) {
        foreach ($defs as $def) {
            $unused[] = [
                'name' => $name,
                'file' => $def['file'],
                'line' => $def['line'],
                'refs' => $refCount,
            ];
        }
    }
}

if (empty($unused)) {
    echo "✅ No obviously unused functions detected (by simple name matching).\n";
    exit(0);
}

echo "⚠️ Potentially unused functions (no references found by name):\n\n";

foreach ($unused as $item) {
    echo sprintf(
        " - %s() in %s:%d (references: %d)\n",
        $item['name'],
        str_replace($projectRoot . DIRECTORY_SEPARATOR, '', $item['file']),
        $item['line'],
        $item['refs']
    );
}

echo "\nReview these manually before deleting anything. Some may be false positives (e.g. used via callbacks or reflection).\n";
exit(1);















