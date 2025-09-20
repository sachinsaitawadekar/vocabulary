<?php
// Simple image-based math CAPTCHA using GD
session_start();

$for = isset($_GET['for']) ? strtolower(trim($_GET['for'])) : 'register';
$w = 180; $h = 60;

// Generate two numbers and compute answer
$a = random_int(1, 9);
$b = random_int(1, 9);
$op = '+'; $ans = $a + $b;

// Store answer in session under context-specific key
if ($for === 'check') {
  $_SESSION['captcha_check_answer'] = $ans;
} else {
  $_SESSION['captcha_register_answer'] = $ans;
}

// Create image
$img = imagecreatetruecolor($w, $h);
$bg = imagecolorallocate($img, 250, 250, 252);
$fg = imagecolorallocate($img, 17, 24, 39); // dark text
$accent = imagecolorallocate($img, 0, 123, 255); // blue accents
$noise = imagecolorallocate($img, 200, 200, 210);
imagefilledrectangle($img, 0, 0, $w, $h, $bg);

// Add noise: lines
for ($i = 0; $i < 6; $i++) {
    $x1 = random_int(0, $w); $y1 = random_int(0, $h);
    $x2 = random_int(0, $w); $y2 = random_int(0, $h);
    imageline($img, $x1, $y1, $x2, $y2, $noise);
}
// Add noise: dots
for ($i = 0; $i < 150; $i++) {
    imagesetpixel($img, random_int(0, $w-1), random_int(0, $h-1), $noise);
}

// Render expression using built-in font to avoid font dependency
$expr = sprintf('%d %s %d = ?', $a, $op, $b);
$font = 5; // built-in font size
$text_w = imagefontwidth($font) * strlen($expr);
$text_h = imagefontheight($font);
$x = (int)(($w - $text_w) / 2);
$y = (int)(($h - $text_h) / 2);

// Draw a rounded-ish backdrop
imagefilledrectangle($img, $x - 8, $y - 6, $x + $text_w + 8, $y + $text_h + 6, imagecolorallocate($img, 235, 243, 255));
imagerectangle($img, $x - 8, $y - 6, $x + $text_w + 8, $y + $text_h + 6, $accent);

// Slight jitter on characters for obfuscation
$cx = $x;
for ($i = 0, $len = strlen($expr); $i < $len; $i++) {
    $ch = $expr[$i];
    imagestring($img, $font, $cx, $y + random_int(-1, 1), $ch, $fg);
    $cx += imagefontwidth($font);
}

// Output
header('Content-Type: image/png');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
imagepng($img);
imagedestroy($img);
exit;

