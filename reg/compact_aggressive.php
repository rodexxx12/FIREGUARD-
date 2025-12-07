<?php
$file = 'registration.php';
$content = file_get_contents($file);
$lines = explode("\n", $content);
$output = [];
$prevBlank = false;
$inPhp = false;
$inCss = false;
$inJs = false;
$inHtml = false;

foreach ($lines as $i => $line) {
    // Remove trailing whitespace
    $trimmedLine = rtrim($line);
    
    // Check if line is blank or only whitespace
    $isBlank = trim($line) === '';
    
    // Detect context
    if (strpos($trimmedLine, '<?php') !== false || strpos($trimmedLine, '<?=') !== false) {
        $inPhp = true;
        $inHtml = false;
    } elseif (strpos($trimmedLine, '?>') !== false) {
        $inPhp = false;
        $inHtml = true;
    } elseif (strpos($trimmedLine, '<style') !== false) {
        $inCss = true;
    } elseif (strpos($trimmedLine, '</style>') !== false) {
        $inCss = false;
    } elseif (strpos($trimmedLine, '<script') !== false) {
        $inJs = true;
    } elseif (strpos($trimmedLine, '</script>') !== false) {
        $inJs = false;
    }
    
    // Handle blank lines
    if ($isBlank) {
        // In CSS or JS, be more aggressive with blank line removal
        if (($inCss || $inJs)) {
            // Check if next line is a closing brace or if previous was opening
            $prevLine = $i > 0 ? trim($lines[$i-1]) : '';
            $nextLine = isset($lines[$i+1]) ? trim($lines[$i+1]) : '';
            
            // Keep blank line if it's between major sections
            if (preg_match('/\{$/', $prevLine) || preg_match('/^\}/', $nextLine)) {
                // Skip blank line after { or before }
                continue;
            } elseif ($prevLine !== '' && $nextLine !== '' && !preg_match('/^\s*(\/\/|\/\*)/', $nextLine)) {
                // Keep single blank line between code blocks
                if (!$prevBlank) {
                    $output[] = '';
                    $prevBlank = true;
                }
            }
        } else {
            // Keep single blank lines in PHP/HTML
            if (!$prevBlank) {
                $output[] = '';
                $prevBlank = true;
            }
        }
    } else {
        $output[] = $trimmedLine;
        $prevBlank = false;
    }
}

// Write to file
file_put_contents('registration_compact.php', implode(PHP_EOL, $output) . PHP_EOL);
echo 'Aggressively compacted file created: registration_compact.php' . PHP_EOL;
echo 'Original lines: ' . count($lines) . PHP_EOL;
echo 'Compacted lines: ' . count($output) . PHP_EOL;
echo 'Lines saved: ' . (count($lines) - count($output)) . PHP_EOL;
echo 'Space saved: ' . number_format(strlen($content) - strlen(implode(PHP_EOL, $output)), 0) . ' bytes' . PHP_EOL;







