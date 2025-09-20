<?php
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="vocabulary-template.csv"');
// Emit UTF-8 BOM so Excel opens non-ASCII correctly
echo "\xEF\xBB\xBF";
$out = fopen('php://output', 'w');
// Header row for vocabulary bulk upload
fputcsv($out, ['entry_date', 'word', 'marathi', 'example'], ',', '"', '\\');
fclose($out);
exit;
