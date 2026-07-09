<?php
$content = file_get_contents(__DIR__ . '/framework/views/f7e6f3120eaaf6ceadcb56238848175c.php');
$chunks = explode('?>', $content);
$found = false;
foreach ($chunks as $i => $chunk) {
    if (str_contains($chunk, 'endforeach')) {
        echo "=== Chunk $i (has endforeach) ===\n";
        echo substr($chunk, -300) . "?>\n";
        if ($i >= 1) {
            echo "\n=== Chunk " . ($i-1) . " ===\n";
            echo substr($chunks[$i-1], -300) . "?>\n";
        }
        if ($i >= 2) {
            echo "\n=== Chunk " . ($i-2) . " ===\n";
            echo substr($chunks[$i-2], -300) . "?>\n";
        }
        $found = true;
        break;
    }
}
if (!$found) echo "endforeach not found\n";

// Also find all if( without matching endif
echo "\n\n=== Counting if/endif in whole file ===\n";
preg_match_all('/<?php\s+if\s*\(/', $content, $m1);
preg_match_all('/<?php\s+endif/', $content, $m2);
echo "if( count: " . count($m1[0]) . "\n";
echo "endif count: " . count($m2[0]) . "\n";
