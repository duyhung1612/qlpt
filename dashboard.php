<?php
// admin/dashboard.php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/functions.php';

require_admin();

/* Helpers */
function scalar($sql,$types="",$params=[]){
  $row = get_row(q($sql,$types,$params));
  if(!$row) return 0;
  $v = reset($row);
  return (int)$v;
}

/* Đếm số liệu */
$total_rooms     = scalar("SELECT COUNT(*) FROM rooms");
$rooms_occupied  = scalar("SELECT COUNT(*) FROM rooms WHERE status='occupied'");
$rooms_empty     = scalar("SELECT COUNT(*) FROM rooms WHERE status='empty'");
$rooms_reserved  = scalar("SELECT COUNT(*) FROM rooms WHERE status='reserved'");
$rooms_maint     = scalar("SELECT COUNT(*) FROM rooms WHERE status='maintenance'");
$total_tenants   = scalar("SELECT COUNT(*) FROM users WHERE role='user'");

$contracts_cnt   = scalar("SELECT COUNT(*) FROM contracts");
$unpaid_cnt      = scalar("SELECT COUNT(*) FROM invoices WHERE status='unpaid'");

$flash = flash_get();
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <title>Bảng điều khiển Admin</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .stat-card{border:none;color:#fff;transition:transform .08s,box-shadow .08s;cursor:pointer}
    .stat-card:hover{transform:translateY(-2px);box-shadow:0 .5rem 1rem rgba(0,0,0,.15)}
    .stat-card .count{font-size:2rem;font-weight:700}
    .link-unstyled{display:block;text-decoration:none;color:inherit}
  </style>
</head>
<body>
<?php include __DIR__ . '/_nav.php'; ?>

<div class="container my-4">
  <h2 class="mb-4">Bảng điều khiển Admin</h2>

  <?php if($flash): ?>
    <div class="alert alert-<?=esc($flash['t'])?>"><?=esc($flash['m'])?></div>
  <?php endif; ?>

  <!-- Hàng thống kê: 6 ô, tất cả đều bấm được -->
  <div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-lg-4 col-xxl-2">
      <a class="link-unstyled" href="rooms.php">
        <div class="card stat-card" style="background:#0d6efd">
          <div class="card-body"><div class="mb-1">Tổng số phòng</div><div class="count"><?=esc($total_rooms)?></div></div>
        </div>
      </a>
    </div>
    <div class="col-12 col-sm-6 col-lg-4 col-xxl-2">
      <a class="link-unstyled" href="rooms.php?status=occupied">
        <div class="card stat-card" style="background:#198754">
          <div class="card-body"><div class="mb-1">Đang thuê</div><div class="count"><?=esc($rooms_occupied)?></div></div>
        </div>
      </a>
    </div>
    <div class="col-12 col-sm-6 col-lg-4 col-xxl-2">
      <a class="link-unstyled" href="rooms.php?status=empty">
        <div class="card stat-card" style="background:#ffc107;color:#111">
          <div class="card-body"><div class="mb-1">Còn trống</div><div class="count"><?=esc($rooms_empty)?></div></div>
        </div>
      </a>
    </div>
    <div class="col-12 col-sm-6 col-lg-4 col-xxl-2">
      <a class="link-unstyled" href="tenants.php">
        <div class="card stat-card" style="background:#0dcaf0;color:#111">
          <div class="card-body"><div class="mb-1">Người thuê</div><div class="count"><?=esc($total_tenants)?></div></div>
        </div>
      </a>
    </div>
    <div class="col-12 col-sm-6 col-lg-4 col-xxl-2">
      <a class="link-unstyled" href="rooms.php?status=reserved">
        <div class="card stat-card" style="background:#20c997">
          <div class="card-body"><div class="mb-1">Giữ chỗ</div><div class="count"><?=esc($rooms_reserved)?></div></div>
        </div>
      </a>
    </div>
    <div class="col-12 col-sm-6 col-lg-4 col-xxl-2">
      <a class="link-unstyled" href="rooms.php?status=maintenance">
        <div class="card stat-card" style="background:#6c757d">
          <div class="card-body"><div class="mb-1">Bảo trì</div><div class="count"><?=esc($rooms_maint)?></div></div>
        </div>
      </a>
    </div>
  </div>

  <!-- Hàng card chức năng -->
  <div class="row g-3">
    <div class="col-12 col-lg-3">
      <div class="card h-100 shadow-sm">
        <div class="card-body d-flex flex-column">
          <h5 class="card-title">Hợp đồng</h5>
          <p class="card-text mb-3">Có <?=esc($contracts_cnt)?> hợp đồng hiện tại.</p>
          <div class="mt-auto">
            <a class="btn btn-outline-primary" href="contracts.php">Quản lý hợp đồng</a>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-3">
      <div class="card h-100 shadow-sm">
        <div class="card-body d-flex flex-column">
          <h5 class="card-title">Hóa đơn</h5>
          <p class="card-text mb-3">Có <?=esc($unpaid_cnt)?> hóa đơn chưa thanh toán.</p>
          <div class="mt-auto">
            <a class="btn btn-outline-danger" href="invoices.php">Quản lý hóa đơn</a>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-3">
      <div class="card h-100 shadow-sm">
        <div class="card-body d-flex flex-column">
          <h5 class="card-title">Báo cáo</h5>
          <p class="card-text mb-3">Xem doanh thu, khách đang thuê, phòng theo trạng thái…</p>
          <div class="mt-auto">
            <a class="btn btn-outline-secondary" href="reports.php">Xem thống kê</a>
          </div>
        </div>
      </div>
    </div>

    <!-- Card: Tạo hóa đơn -->
    <div class="col-12 col-lg-3">
      <div class="card h-100 shadow-sm">
        <div class="card-body d-flex flex-column">
          <h5 class="card-title">Tạo hóa đơn</h5>
          <p class="card-text mb-3">Tạo hóa đơn cho khách hàng.</p>
          <div class="mt-auto">
            <a class="btn btn-primary" href="invoices.php?mode=create">Tạo hóa đơn</a>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="mt-4">
    <a href="../public/index.php" class="btn btn-outline-secondary">« Quay lại trang chính</a>
  </div>
</div>
</body>
</html>
