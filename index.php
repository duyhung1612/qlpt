<?php
// qlpt/public/index.php
require_once __DIR__ . "/../core/auth.php";
require_once __DIR__ . "/../core/functions.php";

$me = current_user();

/* --------- Lọc & tìm kiếm --------- */
$q      = trim($_GET['q'] ?? '');
$status = trim($_GET['status'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$per    = 9;

$where  = "WHERE 1=1";
$types  = "";
$params = [];

if ($q !== "") {
  // tìm trong name + description nếu có cột
  $where .= " AND (name LIKE CONCAT('%',?,'%') OR description LIKE CONCAT('%',?,'%'))";
  $types .= "ss";
  $params[] = $q; $params[] = $q;
}
if ($status !== "" && in_array($status, ['empty','reserved','occupied','maintenance','available','còn trống','đã giữ chỗ','đã thuê'], true)) {
  $where .= " AND status=?";
  $types .= "s";
  $params[] = $status;
}

/* --------- Đếm & phân trang --------- */
$total = (int)(get_row(q("SELECT COUNT(*) c FROM rooms $where", $types, $params))['c'] ?? 0);
$pages = max(1, (int)ceil($total / $per));
if ($page > $pages) $page = $pages;
$off = ($page - 1) * $per;

/* --------- Lấy danh sách phòng --------- */
$rooms = get_all(q("
  SELECT *        /* lấy * để linh hoạt cột ảnh: thumbnail/image/photo/main_image */
  FROM rooms
  $where
  ORDER BY id DESC
  LIMIT ? OFFSET ?
", $types."ii", array_merge($params, [$per, $off])));

/* --------- Helper hiển thị --------- */
function room_img_path(array $r): string {
  $file = $r['thumbnail'] ?? ($r['image'] ?? ($r['photo'] ?? ($r['main_image'] ?? "")));
  if ($file && file_exists(__DIR__."/../uploads/rooms/".$file)) {
    return "/qlpt/uploads/rooms/".rawurlencode($file);
  }
  // placeholder
  return "https://via.placeholder.com/640x360?text=No+Image";
}

function status_badge(string $s): string {
  $s2 = strtolower(trim($s));
  $map = [
    'empty'        => ['success','Còn trống'],
    'available'    => ['success','Còn trống'],
    'còn trống'    => ['success','Còn trống'],
    'reserved'     => ['info','Đã giữ chỗ'],
    'đã giữ chỗ'   => ['info','Đã giữ chỗ'],
    'occupied'     => ['secondary','Đã thuê'],
    'đã thuê'      => ['secondary','Đã thuê'],
    'maintenance'  => ['warning','Bảo trì'],
  ];
  [$bg,$txt] = $map[$s2] ?? ['light', $s];
  return '<span class="badge bg-'.$bg.'">'.$txt.'</span>';
}

?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>QLPT - Danh sách phòng</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<style>
.card-img-top{ object-fit:cover; height:260px; }
.badge{ font-size:.85rem; }
</style>
</head>
<body class="bg-light">

<!-- Navbar: đọc trạng thái đăng nhập -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
  <div class="container">
    <a class="navbar-brand fw-bold" href="index.php">QLPT</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#topnav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="topnav">
      <div class="ms-auto d-flex gap-2">
        <?php if ($me): ?>
          <?php if (is_admin()): ?>
            <a class="btn btn-warning" href="/qlpt/admin/dashboard.php">Trang quản trị</a>
          <?php endif; ?>
          <span class="btn btn-outline-light disabled">Xin chào, <?= esc($me['username']) ?></span>
          <a class="btn btn-light" href="logout.php">Đăng xuất</a>
        <?php else: ?>
          <a class="btn btn-outline-light" href="login.php">Đăng nhập</a>
          <a class="btn btn-light" href="register.php">Đăng ký</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</nav>

<div class="container py-4">

  <!-- Thanh lọc -->
  <form class="row g-2 mb-3">
    <div class="col-lg-6">
      <input class="form-control" name="q" value="<?= esc($q) ?>" placeholder="Tìm tên/mô tả phòng">
    </div>
    <div class="col-lg-3">
      <select class="form-select" name="status">
        <option value="">-- Tất cả trạng thái --</option>
        <?php
          $opts = [
            'empty' => 'Còn trống',
            'reserved' => 'Đã giữ chỗ',
            'occupied' => 'Đã thuê',
            'maintenance' => 'Bảo trì',
          ];
          foreach($opts as $k=>$label):
        ?>
          <option value="<?= $k ?>" <?= $status===$k?'selected':'' ?>><?= $label ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-lg-2 d-grid">
      <button class="btn btn-outline-secondary">Lọc</button>
    </div>
    <?php if($q!=='' || $status!==''): ?>
    <div class="col-lg-1 d-grid">
      <a class="btn btn-link" href="index.php">Xóa</a>
    </div>
    <?php endif; ?>
  </form>

  <!-- Danh sách phòng -->
  <div class="row g-4">
    <?php foreach($rooms as $r): ?>
      <div class="col-md-6 col-lg-4">
        <div class="card h-100 shadow-sm">
          <img class="card-img-top" src="<?= esc(room_img_path($r)) ?>" alt="<?= esc($r['name'] ?? ('Room #'.$r['id'])) ?>">
          <div class="card-body">
            <h5 class="card-title mb-2"><?= esc($r['name'] ?? ('Phòng #'.$r['id'])) ?></h5>
            <div class="mb-1"><b>Giá:</b> <?= number_format((int)($r['price'] ?? 0)) ?> đ/tháng</div>
            <?php if(isset($r['area'])): ?>
              <div class="mb-1"><b>Diện tích:</b> <?= (float)$r['area'] ?> m<sup>2</sup></div>
            <?php endif; ?>
            <div class="mb-2">
              <?= status_badge((string)($r['status'] ?? '')) ?>
            </div>
            <a class="btn btn-primary" href="room_detail.php?id=<?= (int)$r['id'] ?>">Xem chi tiết</a>
          </div>
        </div>
      </div>
    <?php endforeach; ?>

    <?php if(!$rooms): ?>
      <div class="col-12">
        <div class="alert alert-info">Không tìm thấy phòng phù hợp.</div>
      </div>
    <?php endif; ?>
  </div>

  <!-- Phân trang -->
  <?php if($pages > 1): ?>
    <nav class="mt-4">
      <ul class="pagination justify-content-center">
        <?php for($i=1;$i<=$pages;$i++): ?>
          <li class="page-item <?= $i===$page?'active':'' ?>">
            <a class="page-link" href="?page=<?= $i ?><?= $q!==''? '&q='.urlencode($q):'' ?><?= $status!==''? '&status='.urlencode($status):'' ?>">
              <?= $i ?>
            </a>
          </li>
        <?php endfor; ?>
      </ul>
    </nav>
  <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
