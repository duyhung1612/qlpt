<?php
// admin/billing_run.php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/functions.php';

require_admin();

if (!method_is('POST')) {
  flash_set("Phương thức không hợp lệ.","danger");
  redirect("dashboard.php");
}

csrf_verify();

// Lấy tham số POST
$contract_id = (int)($_POST['contract_id'] ?? 0);
$monthNum    = (int)($_POST['month'] ?? date('n'));
$yearNum     = (int)($_POST['year']  ?? date('Y'));

if ($contract_id <= 0) {
  flash_set("Thiếu hợp đồng cần tạo hóa đơn.","warning");
  redirect("dashboard.php");
}
if ($monthNum < 1 || $monthNum > 12 || $yearNum < 2000 || $yearNum > 2100) {
  flash_set("Tháng/năm không hợp lệ.","warning");
  redirect("dashboard.php");
}

// Chuỗi kỳ hoá đơn 'YYYY-MM' (bảng invoices.month là CHAR(7))
$monthStr = sprintf("%04d-%02d", $yearNum, $monthNum);

// Lấy hợp đồng đang hiệu lực + thông tin phòng + người thuê
$contract = get_row(q("
  SELECT c.*, r.id AS room_id, r.price AS room_price, u.id AS user_id
  FROM contracts c
  JOIN rooms  r ON r.id = c.room_id
  JOIN users  u ON u.id = c.user_id
  WHERE c.id=? AND c.status='active'
","i",[$contract_id]));

if (!$contract) {
  flash_set("Không tìm thấy hợp đồng đang hiệu lực (#{$contract_id}).","danger");
  redirect("dashboard.php");
}

// Chặn tạo trùng kỳ
$dup = get_row(q("
  SELECT id FROM invoices WHERE contract_id=? AND month=? LIMIT 1
","is",[$contract_id,$monthStr]));
if ($dup) {
  flash_set("Đã tồn tại hóa đơn của hợp đồng #{$contract_id} cho kỳ {$monthStr}.","warning");
  redirect("invoices.php?contract_id=".$contract_id);
}

// Tính tiền: mặc định = giá phòng (có thể cộng thêm điện/nước/phí khác nếu bạn muốn)
$rent  = (int)$contract['room_price'];
$total = $rent;

// Thêm hoá đơn (status cố định 'unpaid')
q("
  INSERT INTO invoices
    (contract_id, month, rent, electric_kwh, electric_price, water_m3, water_price, other_fee, discount, total, status, room_id, user_id)
  VALUES
    (?,?,?,?,?,?,?,?,?,?, 'unpaid', ?, ?)
","isiiiiiiiiii", [
  $contract_id,              // i
  $monthStr,                 // s
  $rent,                     // i
  0, 0,                      // i, i
  0, 0,                      // i, i
  0,                         // i
  0,                         // i
  $total,                    // i
  (int)$contract['room_id'], // i
  (int)$contract['user_id']  // i
]);

// Lấy ID hóa đơn vừa tạo
global $conn;
$inv_id = $conn->insert_id;

flash_set("Đã tạo hóa đơn #{$inv_id} cho hợp đồng #{$contract_id} kỳ {$monthStr}.","success");
redirect("invoices.php?contract_id=".$contract_id);
