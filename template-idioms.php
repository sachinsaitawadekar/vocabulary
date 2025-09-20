<?php
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="idioms-template.csv"');
// Emit UTF-8 BOM so Excel opens non-ASCII correctly
echo "\xEF\xBB\xBF";
$out = fopen('php://output', 'w');
// Header row for idioms bulk upload
fputcsv($out, ['entry_date', 'idiom', 'marathi', 'example'], ',', '"', '\\');
fclose($out);
exit;
