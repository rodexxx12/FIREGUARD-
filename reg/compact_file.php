<?php
$file = 'registration.php';
$lines = file($file);
$output = [];
$prevBlank = false;

foreach ($lines as $line) {
    // Remove trailing whitespace
    $trimmedLine = rtrim($line);
    
    // Check if line is blank or only whitespace
    $isBlank = trim($line) === '';
    
    // Skip multiple consecutive blank lines
    if ($isBlank) {
        if (!$prevBlank) {
            $output[] = '';
            $prevBlank = true;
        }
    } else {
        $output[] = $trimmedLine;
        $prevBlank = false;
    }
}

// Write to temporary file first
file_put_contents('registration_compact.php', implode(PHP_EOL, $output) . PHP_EOL);
echo 'Compacted file created: registration_compact.php' . PHP_EOL;
echo 'Original lines: ' . count($lines) . PHP_EOL;
echo 'Compacted lines: ' . count($output) . PHP_EOL;
echo 'Lines saved: ' . (count($lines) - count($output)) . PHP_EOL;







