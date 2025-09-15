<?php
/* Người dùng gửi yêu cầu thuê phòng → tạo booking 'pending' để admin duyệt */
require_once __DIR__ . "/../core/auth.php";
require_once __DIR__ . "/../core/functions.php";

if (!is_logged_in()) {
  flash_set("Vui lòng đăng nhập để gửi yêu cầu thuê phòng.","info");
  redirect("login.php");
}
$u = current_user();

/* Chỉ chặn user chưa active (nếu có cột status) */
$statusCol = get_row(q("
  SELECT 1 ok FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='status' LIMIT 1
"));
if ($statusCol && (($u['status'] ?? 'active') !== 'active') && ($u['role'] ?? 'user') !== 'admin') {
  flash_set("Tài khoản của bạn chưa được duyệt. Vui lòng đợi quản trị viên duyệt tài khoản.","warning");
  redirect("index.php");
}

/* Đảm bảo có bảng bookings với các cột cần thiết */
q("CREATE TABLE IF NOT EXISTS bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    user_id INT NOT NULL,
    message VARCHAR(255) DEFAULT NULL,
    status ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX(room_id), INDEX(user_id), INDEX(status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

/* Lấy room */
$room_id = get_int('room_id', 0);
if ($room_id <= 0) { flash_set("Thiếu mã phòng.","warning"); redirect("index.php"); }
$room = get_row(q("SELECT * FROM rooms WHERE id=?","i",[$room_id]));
if (!$room) { flash_set("Phòng không tồn tại.","warning"); redirect("index.php"); }

/* Không cho thuê nếu không còn trống */
if (!in_array($room['status'], ['empty','reserved'], true)) {
  flash_set("Phòng hiện không thể đặt thuê (đã thuê / bảo trì).","warning");
  redirect("room_detail.php?id=".$room_id);
}

$flash = flash_get();

/* Gửi yêu cầu */
if (method_is('POST')) {
  csrf_verify();

  $message = trim(post_str('message'));
  // Chặn trùng yêu cầu đang pending hoặc vừa approved mà chưa ký HĐ
  $dup = get_row(q("
    SELECT id FROM bookings
    WHERE room_id=? AND user_id=? AND status IN ('pending')
    ORDER BY id DESC LIMIT 1
  ","ii",[$room_id,$u['id']]));
  if ($dup) {
    flash_set("Bạn đã gửi yêu cầu thuê phòng này và đang chờ duyệt.","info");
    redirect("room_detail.php?id=".$room_id);
  }

  q("INSERT INTO bookings (room_id,user_id,message,status) VALUES (?,?,?,'pending')",
    "iis", [$room_id,$u['id'],$message]);

  // Có thể đổi trạng thái phòng sang 'reserved' để giữ chỗ tạm thời
  if ($room['status']==='empty') {
    q("UPDATE rooms SET status='reserved' WHERE id=?","i",[$room_id]);
  }

  flash_set("Đã gửi yêu cầu thuê. Quản trị sẽ duyệt sớm nhất!","success");
  redirect("room_detail.php?id=".$room_id);
}
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Gửi yêu cầu thuê</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container py-4" style="max-width:760px">
  <a href="room_detail.php?id=<?= (int)$room_id ?>" class="btn btn-sm btn-outline-secondary mb-3">&laquo; Quay lại phòng</a>
  <?php if($flash): ?><div class="alert alert-<?=esc($flash['t'])?>"><?=esc($flash['m'])?></div><?php endif; ?>

  <div class="card shadow-sm">
    <div class="card-header bg-primary text-white">
      <h5 class="mb-0">Yêu cầu thuê: <?= esc($room['name']) ?></h5>
    </div>
    <div class="card-body">
      <p><b>Giá:</b> <?= number_format($room['price']) ?> đ / tháng</p>
      <p><b>Diện tích:</b> <?= esc($room['area']) ?> m²</p>
      <p><b>Trạng thái hiện tại:</b>
        <?php
          $label = ['empty'=>'Còn trống','reserved'=>'Đã giữ chỗ','occupied'=>'Đã thuê','maintenance'=>'Bảo trì'];
          $cls   = ['empty'=>'success','reserved'=>'info','occupied'=>'secondary','maintenance'=>'warning'];
          $s     = $room['status'];
        ?>
        <span class="badge bg-<?= $cls[$s] ?? 'secondary' ?>"><?= esc($label[$s] ?? $s) ?></span>
      </p>

      <form method="post">
        <?php csrf_field(); ?>
        <div class="mb-3">
          <label class="form-label">Lời nhắn cho quản trị (tuỳ chọn)</label>
          <textarea name="message" class="form-control" rows="3" placeholder="Ví dụ: mong muốn dọn vào ngày..."></textarea>
        </div>
        <button class="btn btn-primary">Gửi yêu cầu thuê</button>
      </form>

      <hr>
      <div class="small text-muted">
        Sau khi gửi, yêu cầu sẽ ở trạng thái <b>pending</b>. Quản trị sẽ duyệt (approved) hoặc từ chối (rejected).<br>
        Khi được duyệt, bạn sẽ được liên hệ/nhận hợp đồng trong phần quản trị.
      </div>
    </div>
  </div>
</div>
</body>
</html>
