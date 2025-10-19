<?php
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="everyday-essentials-template.csv"');
// Emit UTF-8 BOM so Excel opens correctly
echo "\xEF\xBB\xBF";
$out = fopen('php://output', 'w');
// Header row for everyday essentials bulk upload
fputcsv($out, ['entry_date', 'category', 'name', 'marathi', 'image_url'], ',', '"', '\\');
fclose($out);
exit;
