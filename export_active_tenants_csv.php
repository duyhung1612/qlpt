<?php
require_once __DIR__ . "/../core/auth.php";
require_admin();
require_once __DIR__ . "/../core/functions.php";

$rows = get_all(q("
  SELECT c.id AS contract_id,
         r.name AS room_name,
         u.username, u.gmail, u.phone,
         c.start_date, c.end_date, c.price
  FROM contracts c
  JOIN rooms r ON r.id=c.room_id
  JOIN users u ON u.id=c.user_id
  WHERE c.status='active'
    AND c.start_date<=CURDATE()
    AND (c.end_date IS NULL OR c.end_date>=CURDATE())
  ORDER BY r.name ASC, c.start_date DESC
"));

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="active_tenants.csv"');
$fp = fopen('php://output', 'w');
fwrite($fp, "\xEF\xBB\xBF");
fputcsv($fp, ['#HĐ','Phòng','Người thuê','Email','Điện thoại','Bắt đầu','Kết thúc','Giá (VND)']);
foreach($rows as $r){
  fputcsv($fp, [
    $r['contract_id'], $r['room_name'], $r['username'], $r['gmail'], $r['phone'],
    $r['start_date'], $r['end_date'], $r['price']
  ]);
}
fclose($fp);
exit;
