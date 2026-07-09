<?php
$content = file_get_contents(__DIR__ . '/framework/views/f7e6f3120eaaf6ceadcb56238848175c.php');

// Find the literal @endforeach at byte 23760
$pos = 23760;
// Show 600 chars before it
echo "=== 600 chars BEFORE literal @endforeach at 23760 ===\n";
echo substr($content, $pos - 600, 700) . "\n";

// Also find where the @forelse($entries as $entry) compiles to
$foreachPos = strpos($content, 'foreach ($__currentLoopData as $entry');
if ($foreachPos !== false) {
    echo "\n\n=== COMPILED @forelse($entries) at byte $foreachPos ===\n";
    echo substr($content, $foreachPos - 100, 400) . "\n";
} else {
    // Try different pattern
    $foreachPos = strpos($content, '$entries');
    echo "\n\$entries found at: $foreachPos\n";
    // Look for the forelse pattern
    preg_match_all('/foreach.*?\$entries.*?as.*?\$entry/', $content, $m, PREG_OFFSET_CAPTURE);
    foreach ($m[0] as $match) {
        echo "Match at " . $match[1] . ": " . substr($match[0], 0, 100) . "\n";
    }
}
