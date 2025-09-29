<?php
// Simple math CAPTCHA rendered as SVG to avoid GD dependency
session_start();

$for = isset($_GET['for']) ? strtolower(trim($_GET['for'])) : 'register';

$a = random_int(1, 9);
$b = random_int(1, 9);
$ans = $a + $b;
$expr = sprintf('%d + %d = ?', $a, $b);

if ($for === 'check') {
  $_SESSION['captcha_check_answer'] = $ans;
} else {
  $_SESSION['captcha_register_answer'] = $ans;
}

$width = 220;
$height = 80;
$noiseLines = 6;
$noiseDots = 50;

header('Content-Type: image/svg+xml');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

echo '<?xml version="1.0" encoding="UTF-8"?>';
echo "<svg xmlns='http://www.w3.org/2000/svg' width='{$width}' height='{$height}' viewBox='0 0 {$width} {$height}' role='img' aria-label='Captcha'>";
echo "<defs><linearGradient id='bg' x1='0' x2='1' y1='0' y2='1'><stop offset='0%' stop-color='#f8fbff'/><stop offset='100%' stop-color='#e8f0ff'/></linearGradient></defs>";
echo "<rect width='{$width}' height='{$height}' fill='url(#bg)' rx='12' ry='12'/>";

for ($i = 0; $i < $noiseLines; $i++) {
  $x1 = random_int(0, $width);
  $y1 = random_int(0, $height);
  $x2 = random_int(0, $width);
  $y2 = random_int(0, $height);
  $opacity = mt_rand(10, 30) / 100;
  echo "<line x1='{$x1}' y1='{$y1}' x2='{$x2}' y2='{$y2}' stroke='#9fb7ff' stroke-opacity='{$opacity}' stroke-width='2'/>";
}
for ($i = 0; $i < $noiseDots; $i++) {
  $cx = random_int(0, $width);
  $cy = random_int(0, $height);
  $r = mt_rand(1, 2);
  $opacity = mt_rand(10, 35) / 100;
  echo "<circle cx='{$cx}' cy='{$cy}' r='{$r}' fill='#6c8cff' fill-opacity='{$opacity}'/>";
}

$offset = mt_rand(-4, 4);
$cx = $width / 2;
$cy = $height / 2;
echo "<text x='50%' y='55%' fill='#0f172a' font-family='\"Gill Sans\", \"Segoe UI\", sans-serif' font-size='28' font-weight='600' text-anchor='middle' dominant-baseline='middle' letter-spacing='2' transform='rotate({$offset} {$cx} {$cy})'>{$expr}</text>";

echo "</svg>";
exit;
