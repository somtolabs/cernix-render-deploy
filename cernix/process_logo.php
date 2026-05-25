<?php
/**
 * Remove background from aaua-logo.png using scanline flood fill from edges.
 * Saves the result back to public/aaua-logo.png as a true RGBA PNG.
 */

$src  = __DIR__ . '/public/aaua-logo.png';
$dest = __DIR__ . '/public/aaua-logo.png';

$img = @imagecreatefrompng($src);
if (!$img) { die("Cannot load $src\n"); }

$W = imagesx($img);
$H = imagesy($img);
echo "Loaded: {$W}x{$H}\n";

// Create RGBA canvas
$out = imagecreatetruecolor($W, $H);
imagealphablending($out, false);
imagesavealpha($out, true);
$clear = imagecolorallocatealpha($out, 0, 0, 0, 127);
imagefill($out, 0, 0, $clear);
imagecopy($out, $img, 0, 0, 0, 0, $W, $H);
imagedestroy($img);

// Sample background color from top-left corner region (average of a 5x5 area)
$sumR = $sumG = $sumB = 0;
for ($sx = 0; $sx < 5; $sx++) {
    for ($sy = 0; $sy < 5; $sy++) {
        $c = imagecolorat($out, $sx, $sy);
        $sumR += ($c >> 16) & 0xFF;
        $sumG += ($c >> 8)  & 0xFF;
        $sumB += $c & 0xFF;
    }
}
$bgR = (int)round($sumR / 25);
$bgG = (int)round($sumG / 25);
$bgB = (int)round($sumB / 25);
echo "Detected background: rgb($bgR,$bgG,$bgB)\n";

$FUZZ = 32; // tolerance — covers the scan/photo noise

function isBg(int $r, int $g, int $b, int $bgR, int $bgG, int $bgB, int $fuzz): bool {
    return abs($r - $bgR) <= $fuzz
        && abs($g - $bgG) <= $fuzz
        && abs($b - $bgB) <= $fuzz;
}

// Mark transparent pixels in a 2D boolean grid, then apply all at once
// Grid: 1 = keep, 0 = transparent
$keep = array_fill(0, $H, array_fill(0, $W, 1));

// ── Scan from left and right (row-by-row) ──────────────────────────────
for ($y = 0; $y < $H; $y++) {
    // Left → right
    for ($x = 0; $x < $W; $x++) {
        $c = imagecolorat($out, $x, $y);
        $r = ($c >> 16) & 0xFF; $g = ($c >> 8) & 0xFF; $b = $c & 0xFF;
        if (isBg($r, $g, $b, $bgR, $bgG, $bgB, $FUZZ)) { $keep[$y][$x] = 0; }
        else { break; }
    }
    // Right → left
    for ($x = $W - 1; $x >= 0; $x--) {
        $c = imagecolorat($out, $x, $y);
        $r = ($c >> 16) & 0xFF; $g = ($c >> 8) & 0xFF; $b = $c & 0xFF;
        if (isBg($r, $g, $b, $bgR, $bgG, $bgB, $FUZZ)) { $keep[$y][$x] = 0; }
        else { break; }
    }
}

// ── Scan from top and bottom (column-by-column) ──────────────────────
for ($x = 0; $x < $W; $x++) {
    // Top → bottom
    for ($y = 0; $y < $H; $y++) {
        if ($keep[$y][$x] === 0) continue; // already marked
        $c = imagecolorat($out, $x, $y);
        $r = ($c >> 16) & 0xFF; $g = ($c >> 8) & 0xFF; $b = $c & 0xFF;
        if (isBg($r, $g, $b, $bgR, $bgG, $bgB, $FUZZ)) { $keep[$y][$x] = 0; }
        else { break; }
    }
    // Bottom → top
    for ($y = $H - 1; $y >= 0; $y--) {
        if ($keep[$y][$x] === 0) continue;
        $c = imagecolorat($out, $x, $y);
        $r = ($c >> 16) & 0xFF; $g = ($c >> 8) & 0xFF; $b = $c & 0xFF;
        if (isBg($r, $g, $b, $bgR, $bgG, $bgB, $FUZZ)) { $keep[$y][$x] = 0; }
        else { break; }
    }
}

// ── Apply transparency and a soft edge feather ─────────────────────────
$TRANS = imagecolorallocatealpha($out, 0, 0, 0, 127);
$removed = 0;
for ($y = 0; $y < $H; $y++) {
    for ($x = 0; $x < $W; $x++) {
        if ($keep[$y][$x] === 0) {
            imagesetpixel($out, $x, $y, $TRANS);
            $removed++;
        }
    }
}
echo "Removed $removed background pixels\n";

// ── Trim to bounding box ───────────────────────────────────────────────
$minX = $W; $minY = $H; $maxX = 0; $maxY = 0;
for ($y = 0; $y < $H; $y++) {
    for ($x = 0; $x < $W; $x++) {
        if ($keep[$y][$x] === 1) {
            if ($x < $minX) $minX = $x;
            if ($x > $maxX) $maxX = $x;
            if ($y < $minY) $minY = $y;
            if ($y > $maxY) $maxY = $y;
        }
    }
}
// Add small padding
$pad = 6;
$minX = max(0, $minX - $pad);
$minY = max(0, $minY - $pad);
$maxX = min($W - 1, $maxX + $pad);
$maxY = min($H - 1, $maxY + $pad);
$newW = $maxX - $minX + 1;
$newH = $maxY - $minY + 1;
echo "Trimmed bounds: ({$minX},{$minY}) → ({$maxX},{$maxY}) = {$newW}x{$newH}\n";

$trimmed = imagecreatetruecolor($newW, $newH);
imagealphablending($trimmed, false);
imagesavealpha($trimmed, true);
imagefill($trimmed, 0, 0, imagecolorallocatealpha($trimmed, 0, 0, 0, 127));
imagecopy($trimmed, $out, 0, 0, $minX, $minY, $newW, $newH);
imagedestroy($out);

// Save
imagepng($trimmed, $dest, 9);
imagedestroy($trimmed);
echo "Saved to: $dest\n";
echo "Done.\n";
