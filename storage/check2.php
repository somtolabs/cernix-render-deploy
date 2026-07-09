<?php
$content = file_get_contents(__DIR__ . '/framework/views/f7e6f3120eaaf6ceadcb56238848175c.php');

// Find ALL endforeach/endforelse positions
preg_match_all('/endforeach[^;]*;/', $content, $matches, PREG_OFFSET_CAPTURE);
echo "All endforeach occurrences:\n";
foreach ($matches[0] as $m) {
    $pos = $m[1];
    $before = substr($content, max(0, $pos - 150), 150);
    echo "\n--- at byte $pos ---\n";
    echo $before . $m[0] . "\n";
}

// Count if/endif before LAST endforeach
$lastPos = end($matches[0])[1];
$beforeLast = substr($content, 0, $lastPos);
preg_match_all('/<\?php\s+if\s*\(/', $beforeLast, $m1);
preg_match_all('/<\?php\s+endif/', $beforeLast, $m2);
echo "\n\nBefore LAST endforeach:\n";
echo "if( count: " . count($m1[0]) . "\n";
echo "endif count: " . count($m2[0]) . "\n";
