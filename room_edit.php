<?php
/* admin/room_edit.php — Sửa/Thêm phòng + Ảnh phụ (tự phát hiện cột ảnh) */
require_once __DIR__ . "/../core/auth.php";
require_admin();
require_once __DIR__ . "/../models/Room.php";
require_once __DIR__ . "/../core/functions.php";

$id = get_int('id',0);
$editing = $id>0;
$room = $editing? get_row(q("SELECT * FROM rooms WHERE id=?","i",[$id])) : null;
if($editing && !$room){ http_response_code(404); die("Không tìm thấy phòng."); }

/* ----- TẠO BẢNG room_images nếu chưa có (chuẩn: path + is_primary) ----- */
q("CREATE TABLE IF NOT EXISTS room_images (
      id INT AUTO_INCREMENT PRIMARY KEY,
      room_id INT NOT NULL,
      path VARCHAR(255) NOT NULL,
      is_primary TINYINT(1) DEFAULT 0,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      INDEX (room_id)
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

/* ----- Helper: phát hiện tên cột ảnh trong room_images (KHÔNG dùng SHOW ... LIKE ?) ----- */
function room_images_picture_col(): string {
  // chỉ chấp nhận các cột whitelisted để chống injection
  $cands = ['path','filename','image','image_url'];
  $in = "'" . implode("','", $cands) . "'";
  $sql = "
    SELECT COLUMN_NAME 
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'room_images'
      AND COLUMN_NAME IN ($in)
    ORDER BY FIELD(COLUMN_NAME,'path','filename','image','image_url')
    LIMIT 1
  ";
  $row = get_row(q($sql));
  if ($row && !empty($row['COLUMN_NAME'])) return $row['COLUMN_NAME'];

  // nếu bảng cũ không có bất kỳ cột nào ở trên, thêm 'path' cho chuẩn
  q("ALTER TABLE room_images ADD COLUMN path VARCHAR(255) NOT NULL");
  return 'path';
}
$PIC_COL = room_images_picture_col();

/* Xử lý xóa ảnh phụ */
if (method_is('POST') && post_str('act')==='delimg') {
  csrf_verify();
  $img_id = post_int('id');
  $img = get_row(q("SELECT id, room_id, `$PIC_COL` AS pic FROM room_images WHERE id=?","i",[$img_id]));
  if($img){
    if (!empty($img['pic'])) @unlink(UPLOAD_DIR_ROOMS.'/'.$img['pic']);
    q("DELETE FROM room_images WHERE id=?","i",[$img_id]);
    flash_set("Đã xóa ảnh phụ.","success");
  } else {
    flash_set("Không tìm thấy ảnh phụ.","warning");
  }
  redirect("room_edit.php?id=".$id);
}

/* Lưu phòng (thêm/sửa) */
if (method_is('POST') && post_str('act')==='save') {
  csrf_verify();
  $data = [
    'name'        => post_str('name'),
    'price'       => post_int('price'),
    'area'        => post_int('area'),
    'description' => post_str('description'),
    // DB: empty | reserved | occupied | maintenance
    'status'      => post_str('status','empty')
  ];

  // Ảnh chính (optional)
  [$new,$err] = accept_image_upload('image');
  if($err){ flash_set($err,'warning'); redirect($_SERVER['REQUEST_URI']); }
  if($new) $data['image']=$new;

  if($editing){
    room_update($id,$data);
    flash_set("Đã cập nhật phòng.","success");
  } else {
    $id = room_create($data);
    flash_set("Đã thêm phòng mới.","success");
  }
  redirect("room_edit.php?id=".$id);
}

/* Thêm ảnh phụ */
if (method_is('POST') && post_str('act')==='addimg') {
  csrf_verify();
  if ($id<=0) { flash_set("Cần lưu phòng trước khi thêm ảnh phụ.","warning"); redirect("room_edit.php"); }

  [$new,$err] = accept_image_upload('imgfile');
  if($err){ flash_set("Không thể upload ảnh phụ: ".$err,'warning'); redirect("room_edit.php?id=".$id); }
  if(!$new){ flash_set("Bạn chưa chọn file ảnh phụ.",'warning'); redirect("room_edit.php?id=".$id); }

  $sql = "INSERT INTO room_images (room_id, `$PIC_COL`) VALUES (?,?)";
  q($sql,"is",[$id,$new]);
  flash_set("Đã thêm ảnh phụ.","success");
  redirect("room_edit.php?id=".$id);
}

$flash = flash_get();
$images = $editing? get_all(q("SELECT id, `$PIC_COL` AS pic FROM room_images WHERE room_id=? ORDER BY id DESC","i",[$id])) : [];
?>
<!doctype html>
<html lang="vi"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $editing? "Sửa phòng" : "Thêm phòng" ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head><body class="bg-light">
<div class="container py-4">
  <a href="rooms.php" class="btn btn-sm btn-outline-secondary mb-3">&laquo; Danh sách phòng</a>
  <?php if($flash): ?><div class="alert alert-<?=esc($flash['t'])?>"><?=esc($flash['m'])?></div><?php endif; ?>

  <form method="post" enctype="multipart/form-data" class="card card-body shadow-sm mb-4">
    <?php csrf_field(); ?>
    <input type="hidden" name="act" value="save">
    <input type="hidden" name="id" value="<?= (int)$id ?>">
    <h4 class="mb-3"><?= $editing? "Sửa phòng" : "Thêm phòng" ?></h4>

    <div class="mb-3">
      <label class="form-label">Tên phòng</label>
      <input name="name" class="form-control" required value="<?=esc($room['name']??'')?>">
    </div>

    <div class="row">
      <div class="col-md-4 mb-3">
        <label class="form-label">Giá (VNĐ)</label>
        <input type="number" name="price" class="form-control" required value="<?=esc($room['price']??'')?>">
      </div>
      <div class="col-md-4 mb-3">
        <label class="form-label">Diện tích (m²)</label>
        <input type="number" name="area" class="form-control" required value="<?=esc($room['area']??'')?>">
      </div>
      <div class="col-md-4 mb-3">
        <label class="form-label">Trạng thái</label>
        <select name="status" class="form-select">
          <?php
            $opts = ['empty'=>'Còn trống','reserved'=>'Đã giữ chỗ','occupied'=>'Đã thuê','maintenance'=>'Bảo trì'];
            foreach($opts as $k=>$v): ?>
            <option value="<?=$k?>" <?=($room['status']??'')===$k?'selected':''?>><?=$v?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="mb-3">
      <label class="form-label">Mô tả</label>
      <textarea name="description" rows="4" class="form-control"><?=esc($room['description']??'')?></textarea>
    </div>

    <div class="mb-3">
      <label class="form-label">Ảnh chính</label><br>
      <?php if($room && !empty($room['image'])): ?>
        <img src="<?=UPLOAD_URL_ROOMS.'/'.esc($room['image'])?>" class="img-thumbnail mb-2" style="max-width:200px"><br>
      <?php endif; ?>
      <input type="file" name="image" class="form-control">
    </div>

    <button class="btn btn-primary"><?= $editing? "Cập nhật" : "Thêm mới" ?></button>
  </form>

  <?php if($editing): ?>
  <div class="card card-body shadow-sm">
    <h5>Ảnh phụ</h5>

    <div class="d-flex flex-wrap mb-3">
      <?php foreach($images as $img): ?>
        <div class="me-2 mb-2 text-center">
          <?php if(!empty($img['pic'])): ?>
            <img src="<?=UPLOAD_URL_ROOMS.'/'.esc($img['pic'])?>" class="img-thumbnail" style="max-width:120px"><br>
          <?php endif; ?>
          <form method="post" class="d-inline">
            <?php csrf_field(); ?>
            <input type="hidden" name="act" value="delimg">
            <input type="hidden" name="id" value="<?= (int)$img['id'] ?>">
            <button class="btn btn-sm btn-danger mt-1" onclick="return confirm('Xóa ảnh này?')">Xóa</button>
          </form>
        </div>
      <?php endforeach; ?>
      <?php if(!$images): ?>
        <div class="text-muted">Chưa có ảnh phụ.</div>
      <?php endif; ?>
    </div>

    <form method="post" enctype="multipart/form-data" class="row g-2">
      <?php csrf_field(); ?>
      <input type="hidden" name="act" value="addimg">
      <div class="col-md-6">
        <input type="file" name="imgfile" required class="form-control">
      </div>
      <div class="col-md-3">
        <button class="btn btn-secondary w-100">Thêm ảnh phụ</button>
      </div>
      <div class="col-12">
        <small class="text-muted">Chấp nhận: jpg, jpeg, png, gif, webp. Tối đa 5MB.</small>
      </div>
    </form>
  </div>
  <?php endif; ?>
</div>
</body></html>
