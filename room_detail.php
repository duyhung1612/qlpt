<?php
/* public/room_detail.php */
require_once __DIR__ . "/../core/auth.php";
require_once __DIR__ . "/../core/functions.php";

$id = get_int('id',0);
$room = get_row(q("SELECT * FROM rooms WHERE id=?","i",[$id]));
if(!$room){ http_response_code(404); die("Phòng không tồn tại."); }

/* Helper: phát hiện tên cột ảnh trong room_images (path|filename|image|image_url) */
function room_images_pic_col_public(): ?string {
  $cands = ['path','filename','image','image_url'];
  $in = "'" . implode("','",$cands) . "'";
  $row = get_row(q("
    SELECT COLUMN_NAME
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'room_images'
      AND COLUMN_NAME IN ($in)
    ORDER BY FIELD(COLUMN_NAME,'path','filename','image','image_url')
    LIMIT 1
  "));
  return ($row && !empty($row['COLUMN_NAME'])) ? $row['COLUMN_NAME'] : null;
}
$PIC_COL = room_images_pic_col_public();

/* Lấy ảnh phụ (an toàn khi không có bảng/không có cột) */
$images = [];
if ($PIC_COL) {
  $images = get_all(q("SELECT `$PIC_COL` AS pic FROM room_images WHERE room_id=? AND `$PIC_COL`<>'' ORDER BY id DESC","i",[$id]));
}

$flash = flash_get();
$u = current_user();
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Chi tiết phòng</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container py-4">
  <a href="index.php" class="btn btn-sm btn-outline-secondary mb-3">&laquo; Quay lại danh sách</a>
  <?php if($flash): ?><div class="alert alert-<?=esc($flash['t'])?>"><?=esc($flash['m'])?></div><?php endif; ?>

  <div class="card shadow-sm">
    <div class="card-header bg-primary text-white"><h4 class="mb-0"><?=esc($room['name'])?></h4></div>
    <div class="card-body">
      <div class="row">
        <div class="col-md-6">
          <?php if(!empty($room['image'])): ?>
            <img src="<?=UPLOAD_URL_ROOMS.'/'.esc($room['image'])?>" class="img-fluid mb-3 rounded" alt="Ảnh chính">
          <?php endif; ?>

          <?php if($images): ?>
            <?php foreach($images as $img): if(!empty($img['pic'])): ?>
              <img src="<?=UPLOAD_URL_ROOMS.'/'.esc($img['pic'])?>" class="img-thumbnail me-2 mb-2" style="max-width:120px" alt="Ảnh phụ">
            <?php endif; endforeach; ?>
          <?php endif; ?>
        </div>

        <div class="col-md-6">
          <p><b>Giá thuê:</b> <?=number_format($room['price'])?> đ / tháng</p>
          <p><b>Diện tích:</b> <?=esc($room['area'])?> m²</p>
          <p><b>Mô tả:</b><br><?=nl2br(esc($room['description']))?></p>
          <p><b>Trạng thái:</b>
            <?php
              $label = ['empty'=>'Còn trống','reserved'=>'Đã giữ chỗ','occupied'=>'Đã thuê','maintenance'=>'Bảo trì'];
              $cls   = ['empty'=>'success','reserved'=>'info','occupied'=>'secondary','maintenance'=>'warning'];
              $s = $room['status'];
            ?>
            <span class="badge bg-<?= $cls[$s] ?? 'secondary' ?>"><?= esc($label[$s] ?? $s) ?></span>
          </p>

          <?php if(is_logged_in() && $room['status']==='empty'): ?>
            <form method="post" action="rent_room.php">
              <?php csrf_field(); ?>
              <input type="hidden" name="room_id" value="<?= (int)$room['id'] ?>">
              <button class="btn btn-primary">Thuê phòng này</button>
            </form>
          <?php elseif(!is_logged_in()): ?>
            <div class="alert alert-info">Vui lòng <a href="login.php">đăng nhập</a> để thuê phòng.</div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>
