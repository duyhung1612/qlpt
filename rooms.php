<?php
/* admin/rooms.php — Danh sách + xóa/cập nhật trạng thái (POST + CSRF) */
require_once __DIR__ . "/../core/auth.php";
require_admin();
require_once __DIR__ . "/../core/functions.php";

if (method_is('POST')) {
  csrf_verify();
  $act = post_str('act');

  if ($act === 'delete') {
    $id = post_int('id');
    // Xóa ảnh phụ (cột đúng là path)
    $imgs = get_all(q("SELECT path FROM room_images WHERE room_id=?","i",[$id]));
    foreach ($imgs as $im) { @unlink(UPLOAD_DIR_ROOMS.'/'.$im['path']); }
    q("DELETE FROM room_images WHERE room_id=?","i",[$id]);
    q("DELETE FROM rooms WHERE id=?","i",[$id]);
    flash_set("Đã xóa phòng.","success");
    redirect("rooms.php");
  }

  if ($act === 'status') {
    $id = post_int('id');
    $status = post_str('status','empty'); // empty|reserved|occupied|maintenance
    q("UPDATE rooms SET status=? WHERE id=?","si",[$status,$id]);
    flash_set("Đã đổi trạng thái phòng.","success");
    redirect("rooms.php");
  }
}

/* ====== Lọc & phân trang ====== */
$kw   = trim($_GET['kw'] ?? '');
$stt  = trim($_GET['status'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$per  = 12;

$where = "WHERE 1=1"; $params=[]; $types='';
if ($kw!=='') { $where.=" AND (name LIKE CONCAT('%',?,'%') OR description LIKE CONCAT('%',?,'%'))"; $types.="ss"; $params[]=$kw; $params[]=$kw; }
if ($stt!=='') { $where.=" AND status=?"; $types.="s"; $params[]=$stt; }

$total = (int)(get_row(q("SELECT COUNT(*) c FROM rooms $where", $types, $params))['c'] ?? 0);
list($page,$pages) = paginate($total,$page,$per);
$off = ($page-1)*$per;

$stmt = q("SELECT * FROM rooms $where ORDER BY id DESC LIMIT ? OFFSET ?", $types."ii", array_merge($params,[$per,$off]));
$rows = get_all($stmt);
$flash = flash_get();
?>
<!doctype html>
<html lang="vi"><head>
<meta charset="utf-8"><title>Quản lý phòng</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head><body class="bg-light">
  <?php include __DIR__ . "/_nav.php"; ?>

<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
  <h3 class="mb-0">Phòng</h3>
  <div>
    <a class="btn btn-outline-secondary me-2" href="../public/index.php">&laquo; Quay lại trang chính</a>
    <a class="btn btn-primary" href="room_edit.php">+ Thêm phòng</a>
  </div>
</div>
  <?php if($flash): ?><div class="alert alert-<?=esc($flash['t'])?>"><?=esc($flash['m'])?></div><?php endif; ?>

  <form class="row g-2 mb-3">
    <div class="col-md-4">
      <input class="form-control" name="kw" placeholder="Tìm tên/mô tả" value="<?=esc($kw)?>">
    </div>
    <div class="col-md-4">
      <select name="status" class="form-select">
        <option value="">-- Tất cả trạng thái --</option>
        <option value="empty"        <?= $stt==='empty'?'selected':'' ?>>Còn trống</option>
        <option value="reserved"     <?= $stt==='reserved'?'selected':'' ?>>Đã giữ chỗ</option>
        <option value="occupied"     <?= $stt==='occupied'?'selected':'' ?>>Đã thuê</option>
        <option value="maintenance"  <?= $stt==='maintenance'?'selected':'' ?>>Bảo trì</option>
      </select>
    </div>
    <div class="col-md-2">
      <button class="btn btn-outline-secondary w-100">Lọc</button>
    </div>
    <?php if($kw!=='' || $stt!==''): ?>
      <div class="col-md-2"><a class="btn btn-link" href="rooms.php">Xóa lọc</a></div>
    <?php endif; ?>
  </form>

  <div class="table-responsive">
    <table class="table table-sm table-bordered bg-white">
      <thead class="table-light">
        <tr><th>ID</th><th>Ảnh</th><th>Tên</th><th>Giá</th><th>Diện tích</th><th>Trạng thái</th><th></th></tr>
      </thead>
      <tbody>
      <?php foreach($rows as $r): ?>
        <tr>
          <td><?= (int)$r['id'] ?></td>
          <td style="width:110px">
            <?php if($r['image']): ?>
              <img src="<?=UPLOAD_URL_ROOMS.'/'.esc($r['image'])?>" class="img-thumbnail" style="max-width:100px">
            <?php endif; ?>
          </td>
          <td><?= esc($r['name']) ?></td>
          <td><?= number_format($r['price']) ?> đ</td>
          <td><?= esc($r['area']) ?> m²</td>
          <td>
            <?php
              $cls = ['empty'=>'success','reserved'=>'info','occupied'=>'secondary','maintenance'=>'warning'];
              $label = ['empty'=>'Còn trống','reserved'=>'Đã giữ chỗ','occupied'=>'Đã thuê','maintenance'=>'Bảo trì'];
            ?>
            <span class="badge bg-<?= $cls[$r['status']] ?? 'secondary' ?>"><?= esc($label[$r['status']] ?? $r['status']) ?></span>
          </td>
          <td class="text-nowrap">
            <a class="btn btn-sm btn-outline-primary" href="room_edit.php?id=<?= (int)$r['id'] ?>">Sửa</a>
            <form method="post" class="d-inline">
              <?php csrf_field(); ?>
              <input type="hidden" name="act" value="status">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button name="status" value="empty"       class="btn btn-sm btn-success">Empty</button>
              <button name="status" value="reserved"    class="btn btn-sm btn-info">Reserved</button>
              <button name="status" value="occupied"    class="btn btn-sm btn-secondary">Occupied</button>
              <button name="status" value="maintenance" class="btn btn-sm btn-warning">Maintenance</button>
            </form>
            <form method="post" class="d-inline" onsubmit="return confirm('Xóa phòng này?');">
              <?php csrf_field(); ?>
              <input type="hidden" name="act" value="delete">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button class="btn btn-sm btn-outline-danger">Xóa</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if($pages>1): ?>
    <nav><ul class="pagination">
      <?php for($i=1;$i<=$pages;$i++): ?>
        <li class="page-item <?= $i===$page?'active':'' ?>">
          <a class="page-link" href="?page=<?=$i?><?= $kw!==''? '&kw='.urlencode($kw):'' ?><?= $stt!==''? '&status='.urlencode($stt):'' ?>"><?=$i?></a>
        </li>
      <?php endfor; ?>
    </ul></nav>
  <?php endif; ?>
</div>
</body></html>
