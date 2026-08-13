<?php
/**
 * Generates placeholder product images for CampusMart demo data.
 * Run once: php database/seed_images.php
 */
$dir = __DIR__ . '/../assets/images/uploads';
if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
}

$font = 'C:/Windows/Fonts/arialbd.ttf';
if (!file_exists($font)) {
    $font = 'C:/Windows/Fonts/arial.ttf';
}

$palette = [
    [52, 152, 219], [46, 204, 113], [155, 89, 182], [241, 196, 15],
    [230, 126, 34], [231, 76, 60], [26, 188, 156], [52, 73, 94],
    [93, 109, 126], [149, 165, 166], [211, 84, 0], [41, 128, 185],
];

$names = [
    'p01' => 'Calculus Textbook', 'p02' => 'Data Structures', 'p03' => 'Casio FX-991EX', 'p04' => 'TI-84 Calculator',
    'p05' => 'Dell Laptop', 'p06' => 'HP Printer', 'p07' => 'Anker Power Bank', 'p08' => 'Fast Charger',
    'p09' => 'Digital Multimeter', 'p10' => 'Lab Coat', 'p11' => 'Hero Bicycle', 'p12' => 'Study Table',
    'p13' => 'Nike Shoes', 'p14' => 'Cricket Bat', 'p15' => 'Drawing Set', 'p16' => 'Wireless Earbuds',
    'p17' => 'Eng. Drawing Book', 'p18' => 'Desk Fan', 'p19' => 'Casio FX-82MS', 'p20' => 'C Programming',
    'p21' => 'Vernier Calliper', 'p22' => 'Dictionary', 'p23' => 'Laptop Backpack', 'p24' => 'Stethoscope',
    'p25' => 'Electric Kettle', 'p26' => 'Notebooks',
];

$files = [];
for ($p = 1; $p <= 26; $p++) {
    $base = sprintf('p%02d', $p);
    $count = ($p == 1 || $p == 2 || $p == 3 || $p == 5 || $p == 7 || $p == 11 || $p == 12 || $p == 16 || $p == 23) ? 2 : 1;
    $count = ($p == 2) ? 3 : $count;
    for ($i = 1; $i <= $count; $i++) {
        $files[] = $base . '_' . $i;
    }
}

$i = 0;
foreach ($files as $f) {
    // Never overwrite existing images (real product photos or previous output).
    if (file_exists($dir . '/' . $f . '.jpg')) {
        echo 'skipped (exists) ' . $f . '.jpg' . PHP_EOL;
        continue;
    }
    $w = 900;
    $h = 600;
    $img = imagecreatetruecolor($w, $h);
    [$r, $g, $b] = $palette[$i % count($palette)];
    // Vertical gradient
    for ($y = 0; $y < $h; $y++) {
        $t = $y / $h;
        $cr = (int)(($r + 60) * (1 - $t) + ($r - 70) * $t);
        $cg = (int)(($g + 60) * (1 - $t) + ($g - 70) * $t);
        $cb = (int)(($b + 60) * (1 - $t) + ($b - 70) * $t);
        $cr = max(0, min(255, $cr));
        $cg = max(0, min(255, $cg));
        $cb = max(0, min(255, $cb));
        imageline($img, 0, $y, $w, $y, imagecolorallocate($img, $cr, $cg, $cb));
    }
    $base = preg_replace('/_\d+$/', '', $f);
    $label = $names[$base] ?? $base;
    $white = imagecolorallocate($img, 255, 255, 255);
    $gray = imagecolorallocate($img, 230, 230, 230);
    // Decor circles
    imagefilledellipse($img, (int)($w * 0.85), (int)($h * 0.2), 160, 160, imagecolorallocatealpha($img, 255, 255, 255, 60));
    imagefilledellipse($img, (int)($w * 0.1), (int)($h * 0.85), 120, 120, imagecolorallocatealpha($img, 255, 255, 255, 50));
    // Shopping-bag style glyph (simple box + handle)
    $gx = (int)($w / 2) - 90;
    $gy = (int)($h / 2) - 110;
    imagesetthickness($img, 8);
    imagerectangle($img, $gx, $gy + 40, $gx + 180, $gy + 170, $white);
    imagearc($img, $gx + 90, $gy + 40, 110, 90, 0, 180, $white);
    imageline($img, $gx + 40, $gy + 40, $gx + 40, $gy + 10, $white);
    imageline($img, $gx + 140, $gy + 40, $gx + 140, $gy + 10, $white);
    if (function_exists('imagettftext') && file_exists($font)) {
        imagettftext($img, 30, 0, (int)($w / 2) - 90, (int)($h / 2) + 220, $white, $font, 'CampusMart');
        imagettftext($img, 16, 0, (int)($w / 2) - 90, (int)($h / 2) + 255, $gray, $font, $label);
    }
    imagejpeg($img, $dir . '/' . $f . '.jpg', 85);
    imagedestroy($img);
    $i++;
    echo 'generated ' . $f . '.jpg' . PHP_EOL;
}
echo 'Done. ' . count($files) . ' images generated.' . PHP_EOL;
