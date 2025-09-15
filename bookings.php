<?php
/* admin/bookings.php — Duyệt/Từ chối/Huỷ (POST + CSRF) */
require_once __DIR__ . "/../core/auth.php";
require_admin();
require_once __DIR__ . "/../core/functions.php";

/* ====== Actions ====== */
if (method_is('POST')) {
  csrf_verify();
  $act = post_str('act');
  $id  = post_int('id');

  if ($act==='approve') {
    // đánh dấu approved
    q("UPDATE bookings SET status='approved' WHERE id=?","i",[$id]);
    flash_set("Đã duyệt đơn thuê.","success");
  }
  if ($act==='reject') {
    q("UPDATE bookings SET status='rejected' WHERE id=?","i",[$id]);
    flash_set("Đã từ chối đơn thuê.","success");
  }
  if ($act==='cancel') {
    q("UPDATE bookings SET status='cancelled' WHERE id=?","i",[$id]);
    flash_set("Đã hủy đơn thuê.","success");
  }
  redirect("bookings.php");
}

/* ====== Data ====== */
$filter = $_GET['filter'] ?? 'pending';
$allow  = ['pending','approved','rejected','cancelled'];
if(!in_array($filter,$allow,true)) $filter='pending';

$sql = "SELECT b.*, r.name AS room_name, u.username, u.gmail
        FROM bookings b
        JOIN rooms r ON r.id=b.room_id
        JOIN users u ON u.id=b.user_id
        WHERE b.status=?
        ORDER BY b.id DESC";
$list = get_all(q($sql,"s",[$filter]));
$flash = flash_get();
?>
<!doctype html>
<html lang="vi"><head>
<meta charset="utf-8"><title>Quản lý Booking</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head><body class="bg-light">
  <?php include __DIR__ . "/_nav.php"; ?>

<div class="container py-4">
  <h3 class="mb-3">Booking</h3>
  <?php if($flash): ?><div class="alert alert-<?=esc($flash['t'])?>"><?=esc($flash['m'])?></div><?php endif; ?>

  <div class="mb-3">
    Lọc:
    <a class="btn btn-sm btn-outline-secondary <?= $filter==='pending'?'active':'' ?>" href="?filter=pending">Pending</a>
    <a class="btn btn-sm btn-outline-secondary <?= $filter==='approved'?'active':'' ?>" href="?filter=approved">Approved</a>
    <a class="btn btn-sm btn-outline-secondary <?= $filter==='rejected'?'active':'' ?>" href="?filter=rejected">Rejected</a>
    <a class="btn btn-sm btn-outline-secondary <?= $filter==='cancelled'?'active':'' ?>" href="?filter=cancelled">Cancelled</a>
  </div>

  <table class="table table-sm table-bordered bg-white">
    <thead class="table-light"><tr>
      <th>ID</th><th>Phòng</th><th>Người thuê</th><th>Trạng thái</th><th>Thời điểm</th><th></th>
    </tr></thead>
    <tbody>
    <?php foreach($list as $r): ?>
      <tr>
        <td><?= (int)$r['id'] ?></td>
        <td><?= esc($r['room_name']) ?></td>
        <td><?= esc($r['username'].' ('.$r['gmail'].')') ?></td>
        <td><?= esc($r['status']) ?></td>
        <td><?= esc($r['created_at']) ?></td>
        <td class="text-nowrap">
          <form method="post" class="d-inline">
            <?php csrf_field(); ?>
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button name="act" value="approve" class="btn btn-sm btn-success">Duyệt</button>
            <button name="act" value="reject"  class="btn btn-sm btn-warning">Từ chối</button>
            <button name="act" value="cancel"  class="btn btn-sm btn-danger" onclick="return confirm('Hủy đơn này?')">Hủy</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
</body></html>
