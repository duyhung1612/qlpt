<?php
require_once __DIR__ . "/../core/auth.php";
require_admin();
require_once __DIR__ . "/../core/functions.php";

$rows = get_all(q("
  SELECT DATE_FORMAT(d, '%m/%Y') month_label,
         COALESCE(SUM(i.amount),0) total
  FROM (
    SELECT DATE_SUB(DATE_FORMAT(CURDATE(),'%Y-%m-01'), INTERVAL n MONTH) d
    FROM (SELECT 0 n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL
                 SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9 UNION ALL
                 SELECT 10 UNION ALL SELECT 11) t
  ) months
  LEFT JOIN invoices i
         ON i.status='paid'
        AND DATE_FORMAT(i.due_date,'%Y-%m') = DATE_FORMAT(months.d,'%Y-%m')
  GROUP BY month_label
  ORDER BY months.d ASC
"));

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="revenue_last_12_months.csv"');
$fp = fopen('php://output', 'w');
// BOM để Excel mở tiếng Việt đúng
fwrite($fp, "\xEF\xBB\xBF");
fputcsv($fp, ['Tháng','Doanh thu (VND)']);
foreach($rows as $r){
  fputcsv($fp, [$r['month_label'], $r['total']]);
}
fclose($fp);
exit;
