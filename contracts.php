<?php
require_once __DIR__ . "/../core/auth.php";
require_admin();
require_once __DIR__ . "/../core/functions.php";

/* ===== Helpers ===== */
function col_exists($table,$col){
  $row = get_row(q("
    SELECT 1 ok
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1
  ","ss",[$table,$col]));
  return (bool)$row;
}

/* Cột tiền thuê trong bảng contracts: monthly_rent | price (auto detect) */
$RENT_COL = col_exists('contracts','monthly_rent') ? 'monthly_rent'
          : (col_exists('contracts','price') ? 'price' : null);
if (!$RENT_COL) {
  // đảm bảo có cột tiền thuê nếu DB cũ chưa có
  q("ALTER TABLE contracts ADD COLUMN monthly_rent INT NOT NULL DEFAULT 0");
  $RENT_COL = 'monthly_rent';
}
/* Đảm bảo cột status tồn tại */
if (!col_exists('contracts','status')) {
  q("ALTER TABLE contracts ADD COLUMN status ENUM('active','terminated','cancelled') NOT NULL DEFAULT 'active'");
}

/* ===== Filters & pagination ===== */
$qstr   = trim($_GET['q'] ?? '');                     // tìm theo room/user/email
$status = trim($_GET['status'] ?? '');                // '', active, terminated, cancelled
$page   = max(1,(int)($_GET['page'] ?? 1));
$per    = 15;

/* ===== Actions (POST) ===== */
if (method_is('POST')) {
  csrf_verify();
  $act = post_str('act');

  if ($act === 'terminate') {
    $id = post_int('id');
    // chấm dứt hợp đồng
    q("UPDATE contracts SET status='terminated' WHERE id=?","i",[$id]);
    // có thể mở phòng về 'empty' nếu không còn HĐ active nào
    $row = get_row(q("SELECT room_id FROM contracts WHERE id=?","i",[$id]));
    if ($row) {
      $room_id = (int)$row['room_id'];
      $hasActive = get_row(q("SELECT id FROM contracts WHERE room_id=? AND status='active' LIMIT 1","i",[$room_id]));
      if (!$hasActive) {
        q("UPDATE rooms SET status='empty' WHERE id=?","i",[$room_id]);
      }
    }
    flash_set("Đã chấm dứt hợp đồng #$id.","success");
    redirect("contracts.php");
  }

  if ($act === 'delete') {
    $id = post_int('id');
    q("DELETE FROM contracts WHERE id=?","i",[$id]);
    flash_set("Đã xóa hợp đồng #$id.","success");
    redirect("contracts.php");
  }
}

/* ===== Build WHERE ===== */
$where  = "WHERE 1=1";
$params = []; $types = "";

if ($status !== '' && in_array($status,['active','terminated','cancelled'],true)) {
  $where .= " AND c.status=?";
  $types .= "s"; $params[]=$status;
}
if ($qstr !== '') {
  // tìm theo tên phòng, username, gmail
  $where .= " AND (r.name LIKE CONCAT('%',?,'%')
               OR u.username LIKE CONCAT('%',?,'%')
               OR u.gmail LIKE CONCAT('%',?,'%'))";
  $types .= "sss"; $params[]=$qstr; $params[]=$qstr; $params[]=$qstr;
}

/* ===== Count & paginate ===== */
$cnt = get_row(q("SELECT COUNT(*) c
                  FROM contracts c
                  JOIN rooms r ON r.id=c.room_id
                  JOIN users u ON u.id=c.user_id
                  $where",$types,$params));
$total = (int)($cnt['c'] ?? 0);
list($page,$pages) = paginate($total,$page,$per);
$off = ($page-1)*$per;

/* ===== Fetch list ===== */
$sql = "
  SELECT c.id, c.room_id, c.user_id, c.start_date, c.end_date, c.deposit, c.status,
         c.`$RENT_COL` AS rent,
         r.name AS room_name,
         u.username, u.gmail, u.fullname
  FROM contracts c
  JOIN rooms r ON r.id=c.room_id
  JOIN users u ON u.id=c.user_id
  $where
  ORDER BY (c.status='active') DESC, c.start_date DESC, c.id DESC
  LIMIT ? OFFSET ?
";
$rows = get_all(q($sql, $types."ii", array_merge($params,[$per,$off])));

$flash = flash_get();
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Quản lý hợp đồng</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<?php if (is_file(__DIR__."/_nav.php")) include __DIR__."/_nav.php"; ?>

<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Hợp đồng</h3>
    <div>
      <a class="btn btn-primary" href="contract_edit.php">+ Tạo hợp đồng</a>
    </div>
  </div>

  <?php if($flash): ?><div class="alert alert-<?=esc($flash['t'])?>"><?=esc($flash['m'])?></div><?php endif; ?>

  <!-- Filters -->
  <form class="row g-2 mb-3">
    <div class="col-md-4">
      <input name="q" class="form-control" value="<?=esc($qstr)?>" placeholder="Tìm phòng / username / gmail">
    </div>
    <div class="col-md-3">
      <select name="status" class="form-select">
        <option value="">-- Tất cả trạng thái --</option>
        <option value="active"     <?= $status==='active'    ?'selected':'' ?>>Đang hiệu lực</option>
        <option value="terminated" <?= $status==='terminated'?'selected':'' ?>>Đã chấm dứt</option>
        <option value="cancelled"  <?= $status==='cancelled' ?'selected':'' ?>>Huỷ</option>
      </select>
    </div>
    <div class="col-md-2">
      <button class="btn btn-outline-secondary w-100">Lọc</button>
    </div>
    <?php if($qstr!=='' || $status!==''): ?>
      <div class="col-md-2"><a class="btn btn-link" href="contracts.php">Xóa lọc</a></div>
    <?php endif; ?>
  </form>

  <div class="table-responsive">
    <table class="table table-sm table-bordered bg-white align-middle">
      <thead class="table-light">
        <tr>
          <th>#ID</th>
          <th>Phòng</th>
          <th>Người thuê</th>
          <th>Bắt đầu</th>
          <th>Kết thúc</th>
          <th class="text-end">Cọc (đ)</th>
          <th class="text-end">Thuê/tháng (đ)</th>
          <th>Trạng thái</th>
          <th style="width:220px"></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach($rows as $r): ?>
        <tr>
          <td><?= (int)$r['id'] ?></td>
          <td><?= esc($r['room_name']) ?></td>
          <td><?= esc($r['username']) ?> (<?= esc($r['gmail']) ?>)</td>
          <td><?= esc($r['start_date']) ?></td>
          <td><?= esc($r['end_date']) ?></td>
          <td class="text-end"><?= number_format((int)$r['deposit']) ?></td>
          <td class="text-end"><?= number_format((int)$r['rent']) ?></td>
          <td>
            <?php
              $map = ['active'=>['success','Đang hiệu lực'],
                      'terminated'=>['secondary','Đã chấm dứt'],
                      'cancelled'=>['dark','Huỷ']];
              [$bg,$txt] = $map[$r['status']] ?? ['light',$r['status']];
            ?>
            <span class="badge bg-<?= $bg ?>"><?= $txt ?></span>
          </td>
          <td class="text-nowrap">
            <a class="btn btn-sm btn-outline-primary" href="contract_edit.php?id=<?= (int)$r['id'] ?>">Sửa</a>
            <?php if($r['status']==='active'): ?>
              <form method="post" class="d-inline" onsubmit="return confirm('Chấm dứt hợp đồng này?');">
                <?php csrf_field(); ?>
                <input type="hidden" name="act" value="terminate">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <button class="btn btn-sm btn-warning">Chấm dứt</button>
              </form>
            <?php endif; ?>
            <form method="post" class="d-inline" onsubmit="return confirm('Xóa hợp đồng này?');">
              <?php csrf_field(); ?>
              <input type="hidden" name="act" value="delete">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button class="btn btn-sm btn-outline-danger">Xóa</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if(!$rows): ?>
        <tr><td colspan="9" class="text-center text-muted">Chưa có hợp đồng.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php if($pages>1): ?>
    <nav><ul class="pagination">
      <?php for($i=1;$i<=$pages;$i++): ?>
        <li class="page-item <?= $i===$page?'active':'' ?>">
          <a class="page-link" href="?page=<?= $i ?><?= $qstr!==''? '&q='.urlencode($qstr):'' ?><?= $status!==''? '&status='.urlencode($status):'' ?>"><?= $i ?></a>
        </li>
      <?php endfor; ?>
    </ul></nav>
  <?php endif; ?>

</div>
</body>
</html>
