<?php
// admin/invoice_view.php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/functions.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { flash_set("Thiếu ID hóa đơn.","warning"); redirect("invoices.php"); }

$inv = get_row(q("
  SELECT i.*, r.name AS room_name, u.username AS user_name, u.gmail, u.phone
  FROM invoices i
  LEFT JOIN rooms r ON r.id = i.room_id
  LEFT JOIN users u ON u.id = i.user_id
  WHERE i.id=?
","i",[$id]));

if (!$inv) { flash_set("Không tìm thấy hóa đơn #{$id}.","danger"); redirect("invoices.php"); }

?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<title>Hóa đơn #<?=esc($inv['id'])?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  .invoice-card{max-width:900px;margin:auto}
  .kv{display:flex;justify-content:space-between;border-bottom:1px dashed #e5e7eb;padding:.5rem 0}
  .kv .k{color:#6b7280}
  @media print { .no-print{display:none} body{margin:24px} }
</style>
</head>
<body class="bg-light">
<div class="container my-4 invoice-card">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Hóa đơn #<?=esc($inv['id'])?></h3>
    <div class="no-print">
      <a class="btn btn-outline-secondary" href="invoices.php">« Danh sách</a>
      <button class="btn btn-primary" onclick="window.print()">In / Lưu PDF</button>
    </div>
  </div>

  <div class="card shadow-sm">
    <div class="card-body">
      <div class="kv"><div class="k">Kỳ</div><div class="v"><strong><?=esc($inv['month'])?></strong></div></div>
      <div class="kv"><div class="k">Phòng</div><div class="v"><?=esc($inv['room_name'] ?? ('#'.$inv['room_id']))?></div></div>
      <div class="kv"><div class="k">Người thuê</div><div class="v"><?=esc($inv['user_name'] ?? ('#'.$inv['user_id']))?></div></div>
      <div class="kv"><div class="k">Email</div><div class="v"><?=esc($inv['gmail'] ?? '')?></div></div>
      <div class="kv"><div class="k">Điện thoại</div><div class="v"><?=esc($inv['phone'] ?? '')?></div></div>
      <div class="kv"><div class="k">Trạng thái</div>
        <div class="v">
          <?php if($inv['status']==='paid'): ?>
            <span class="badge bg-success">Đã thu</span>
          <?php else: ?>
            <span class="badge bg-warning text-dark">Chưa thu</span>
          <?php endif; ?>
        </div>
      </div>
      <div class="kv"><div class="k">Tiền phòng</div><div class="v"><?=number_format((int)$inv['rent'])?> đ</div></div>
      <div class="kv"><div class="k">Tổng tiền</div><div class="v"><strong><?=number_format((int)$inv['total'])?> đ</strong></div></div>
      <?php if(!empty($inv['due_date'])): ?>
        <div class="kv"><div class="k">Hạn thanh toán</div><div class="v"><?=esc($inv['due_date'])?></div></div>
      <?php endif; ?>
      <?php if(!empty($inv['note'])): ?>
        <div class="kv"><div class="k">Ghi chú</div><div class="v"><?=esc($inv['note'])?></div></div>
      <?php endif; ?>
      <div class="mt-3 text-muted" style="font-size:0.9rem">Ngày tạo: <?=esc($inv['created_at'] ?? '')?></div>
    </div>
  </div>
</div>
</body>
</html>
