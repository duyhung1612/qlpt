<?php
// admin/_nav.php
require_once __DIR__ . "/../core/auth.php";
require_admin();
$u = current_user();
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
  <div class="container">
    <a class="navbar-brand" href="dashboard.php">QLPT Admin</a>
    <div class="collapse navbar-collapse">
      <ul class="navbar-nav me-auto">
        <li class="nav-item"><a class="nav-link" href="dashboard.php">Dashboard</a></li>
        <li class="nav-item"><a class="nav-link" href="rooms.php">Phòng</a></li>
        <li class="nav-item"><a class="nav-link" href="tenants.php">Người thuê</a></li>
        <li class="nav-item"><a class="nav-link" href="contracts.php">Hợp đồng</a></li>
        <li class="nav-item"><a class="nav-link" href="invoices.php">Hóa đơn</a></li>
        <li class="nav-item"><a class="nav-link" href="reports.php">Báo cáo</a></li>
      </ul>
      <div class="d-flex">
        <a class="btn btn-sm btn-outline-light me-2" href="../public/index.php">Trang chính</a>
        <form method="post" action="../public/logout.php">
          <?php csrf_field(); ?>
          <button class="btn btn-sm btn-danger">Đăng xuất</button>
        </form>
      </div>
    </div>
  </div>
</nav>
