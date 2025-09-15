<?php
require_once __DIR__ . "/../core/auth.php";
require_admin();
require_once __DIR__ . "/../core/functions.php";

/* Doanh thu 12 tháng */
$revenue = get_all(q("
  SELECT DATE_FORMAT(d, '%m/%Y') label,
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
  GROUP BY label
  ORDER BY months.d ASC
"));

/* Khách đang thuê */
$tenants = get_all(q("
  SELECT c.id AS contract_id, r.name AS room_name, u.username, u.gmail, u.phone,
         c.start_date, c.end_date, c.price
  FROM contracts c
  JOIN rooms r ON r.id=c.room_id
  JOIN users u ON u.id=c.user_id
  WHERE c.status='active'
    AND c.start_date<=CURDATE()
    AND (c.end_date IS NULL OR c.end_date>=CURDATE())
  ORDER BY r.name ASC, c.start_date DESC
"));
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<title>Báo cáo tổng hợp</title>
<style>
  body{font-family:Arial,Helvetica,sans-serif;margin:24px;color:#222;}
  h1{margin:0 0 8px;} h2{margin:24px 0 8px;}
  table{width:100%;border-collapse:collapse;margin-top:8px;}
  th,td{border:1px solid #999;padding:6px 8px;font-size:13px}
  th{background:#f2f2f2;text-align:left}
  .muted{color:#666;font-size:12px}
  .header{display:flex;justify-content:space-between;align-items:flex-end;border-bottom:2px solid #000;margin-bottom:12px;padding-bottom:8px}
  .btn{display:inline-block;padding:8px 12px;border:1px solid #333;border-radius:6px;text-decoration:none;color:#000;margin-right:8px}
  @media print {.no-print{display:none}}
</style>
</head>
<body>
    <?php include __DIR__ . "/_nav.php"; ?>

<div class="header">
  <div>
    <h1>Báo cáo tổng hợp</h1>
    <div class="muted">Ngày in: <?=esc(date('d/m/Y H:i'))?></div>
  </div>
  <div class="no-print">
    <a class="btn" href="reports.php">&laquo; Quay lại</a>
    <a class="btn" href="#" onclick="window.print()">In / Lưu PDF</a>
  </div>
</div>

<h2>Doanh thu 12 tháng gần nhất</h2>
<table>
  <thead><tr><th>Tháng</th><th>Doanh thu (VND)</th></tr></thead>
  <tbody>
  <?php foreach($revenue as $r): ?>
    <tr><td><?=esc($r['label'])?></td><td><?=number_format($r['total'])?></td></tr>
  <?php endforeach; ?>
  </tbody>
</table>

<h2>Khách đang thuê (Hợp đồng hiệu lực)</h2>
<table>
  <thead>
    <tr><th>#HĐ</th><th>Phòng</th><th>Người thuê</th><th>Email</th><th>Điện thoại</th><th>Bắt đầu</th><th>Kết thúc</th><th>Giá (VND)</th></tr>
  </thead>
  <tbody>
  <?php if(!$tenants): ?>
    <tr><td colspan="8" class="muted">Không có dữ liệu.</td></tr>
  <?php endif; ?>
  <?php foreach($tenants as $t): ?>
    <tr>
      <td><?= (int)$t['contract_id'] ?></td>
      <td><?= esc($t['room_name']) ?></td>
      <td><?= esc($t['username']) ?></td>
      <td><?= esc($t['gmail']) ?></td>
      <td><?= esc($t['phone']) ?></td>
      <td><?= esc($t['start_date']) ?></td>
      <td><?= esc($t['end_date']) ?></td>
      <td><?= number_format($t['price']) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</body>
</html>
