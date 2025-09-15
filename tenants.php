<?php
require_once __DIR__ . "/../core/auth.php";
require_admin();
require_once __DIR__ . "/../core/functions.php";

/* ========= Helpers: đảm bảo các cột cần thiết tồn tại ========= */
function col_exists($table, $col){
  $row = get_row(q("
    SELECT 1 ok
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = ?
      AND COLUMN_NAME  = ?
    LIMIT 1
  ","ss",[$table,$col]));
  return (bool)$row;
}
function ensure_users_columns(){
  // status
  if (!col_exists('users','status')) {
    q("ALTER TABLE users ADD COLUMN status ENUM('pending','active','rejected') NOT NULL DEFAULT 'pending'");
  }
  // fullname
  if (!col_exists('users','fullname')) {
    q("ALTER TABLE users ADD COLUMN fullname VARCHAR(100) NULL AFTER gmail");
  }
  // phone
  if (!col_exists('users','phone')) {
    q("ALTER TABLE users ADD COLUMN phone VARCHAR(30) NULL AFTER fullname");
  }
  // created_at
  if (!col_exists('users','created_at')) {
    q("ALTER TABLE users ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP");
  }
}
ensure_users_columns();

/* ========= Đọc tham số lọc / phân trang ========= */
$qstr   = trim($_GET['q'] ?? '');
$status = trim($_GET['status'] ?? ''); // '', pending, active, rejected
$page   = max(1, (int)($_GET['page'] ?? 1));
$per    = 15;

/* ========= Xử lý POST ========= */
if (method_is('POST')) {
  csrf_verify();
  $act = post_str('act');

  if ($act === 'create') {
    $username = trim(post_str('username'));
    $gmail    = trim(post_str('gmail'));
    $password = post_str('password');
    $fullname = trim(post_str('fullname'));
    $phone    = trim(post_str('phone'));

    if ($username === '' || $gmail === '' || $password === '') {
      flash_set("Vui lòng nhập đủ Username, Gmail, Mật khẩu.","warning");
      redirect("tenants.php");
    }

    $dup = get_row(q("SELECT id FROM users WHERE username=? OR gmail=? LIMIT 1","ss",[$username,$gmail]));
    if ($dup) {
      flash_set("Username hoặc Gmail đã tồn tại.","warning");
      redirect("tenants.php");
    }

    // Admin tạo thẳng: role=user, status=active (mật khẩu dạng thường theo yêu cầu hệ thống)
    q("INSERT INTO users (username, gmail, password, fullname, phone, role, status)
       VALUES (?,?,?,?,?,'user','active')",
      "sssss", [$username,$gmail,$password,$fullname,$phone]);
    flash_set("Đã thêm người thuê mới (trạng thái: active).","success");
    redirect("tenants.php");
  }

  if ($act === 'approve') {
    $id = post_int('id');
    q("UPDATE users SET status='active' WHERE id=? AND role='user'","i",[$id]);
    flash_set("Đã duyệt tài khoản #$id.","success");
    redirect("tenants.php");
  }

  if ($act === 'reject') {
    $id = post_int('id');
    q("UPDATE users SET status='rejected' WHERE id=? AND role='user'","i",[$id]);
    flash_set("Đã từ chối tài khoản #$id.","success");
    redirect("tenants.php");
  }

  if ($act === 'update') {
    $id       = post_int('id');
    $gmail    = trim(post_str('gmail'));
    $fullname = trim(post_str('fullname'));
    $phone    = trim(post_str('phone'));
    $statusUp = trim(post_str('status')); // optional

    if ($statusUp !== '' && in_array($statusUp, ['pending','active','rejected'], true)) {
      q("UPDATE users SET gmail=?, fullname=?, phone=?, status=? WHERE id=? AND role='user'",
        "ssssi",[$gmail,$fullname,$phone,$statusUp,$id]);
    } else {
      q("UPDATE users SET gmail=?, fullname=?, phone=? WHERE id=? AND role='user'",
        "sssi",[$gmail,$fullname,$phone,$id]);
    }
    flash_set("Đã cập nhật người thuê #$id.","success");
    redirect("tenants.php");
  }

  if ($act === 'delete') {
    $id = post_int('id');
    q("DELETE FROM users WHERE id=? AND role='user'","i",[$id]);
    flash_set("Đã xóa người thuê #$id.","success");
    redirect("tenants.php");
  }
}

/* ========= Xây where cho list ========= */
$where  = "WHERE role='user'";
$params = []; $types="";

if ($qstr !== '') {
  $where .= " AND (username LIKE CONCAT('%',?,'%') OR gmail LIKE CONCAT('%',?,'%') OR fullname LIKE CONCAT('%',?,'%'))";
  $types .= "sss"; $params[]=$qstr; $params[]=$qstr; $params[]=$qstr;
}
if ($status !== '' && in_array($status,['pending','active','rejected'],true)) {
  $where .= " AND status=?";
  $types .= "s"; $params[]=$status;
}

/* ========= Đếm & phân trang ========= */
$total = (int)(get_row(q("SELECT COUNT(*) c FROM users $where",$types,$params))['c'] ?? 0);
list($page,$pages) = paginate($total,$page,$per);
$off = ($page-1)*$per;

/* ========= Lấy danh sách ========= */
$sql = "SELECT id,username,gmail,fullname,phone,status,created_at
        FROM users
        $where
        ORDER BY (status='pending') DESC, id DESC
        LIMIT ? OFFSET ?";
$rows = get_all(q($sql, $types."ii", array_merge($params,[$per,$off])));

$flash = flash_get();
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<title>Quản lý người thuê</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<?php if (is_file(__DIR__."/_nav.php")) include __DIR__."/_nav.php"; ?>

<div class="container py-4">
  <h3 class="mb-3">Người thuê</h3>

  <?php if($flash): ?><div class="alert alert-<?=esc($flash['t'])?>"><?=esc($flash['m'])?></div><?php endif; ?>

  <!-- Tìm & lọc -->
  <form class="row g-2 mb-3">
    <div class="col-md-4">
      <input name="q" class="form-control" value="<?=esc($qstr)?>" placeholder="Tìm username/gmail/họ tên">
    </div>
    <div class="col-md-3">
      <select name="status" class="form-select">
        <option value="">-- Tất cả trạng thái --</option>
        <option value="pending"  <?= $status==='pending' ? 'selected':'' ?>>Chờ duyệt</option>
        <option value="active"   <?= $status==='active'  ? 'selected':'' ?>>Đã duyệt</option>
        <option value="rejected" <?= $status==='rejected'? 'selected':'' ?>>Từ chối</option>
      </select>
    </div>
    <div class="col-md-2">
      <button class="btn btn-outline-secondary w-100">Tìm</button>
    </div>
    <?php if($qstr!=='' || $status!==''): ?>
      <div class="col-md-2">
        <a class="btn btn-link" href="tenants.php">Xóa lọc</a>
      </div>
    <?php endif; ?>
  </form>

  <!-- Thêm người thuê mới (admin tạo thẳng là active) -->
  <div class="card mb-4">
    <div class="card-body">
      <h5 class="mb-3">Thêm người thuê mới</h5>
      <form method="post" class="row g-2">
        <?php csrf_field(); ?>
        <input type="hidden" name="act" value="create">
        <div class="col-md-3">
          <label class="form-label">Username</label>
          <input name="username" class="form-control" required>
        </div>
        <div class="col-md-3">
          <label class="form-label">Gmail</label>
          <input type="email" name="gmail" class="form-control" required>
        </div>
        <div class="col-md-3">
          <label class="form-label">Mật khẩu</label>
          <input name="password" class="form-control" required>
        </div>
        <div class="col-md-3">
          <label class="form-label">Họ tên</label>
          <input name="fullname" class="form-control">
        </div>
        <div class="col-md-3">
          <label class="form-label">SDT</label>
          <input name="phone" class="form-control">
        </div>
        <div class="col-md-2 align-self-end">
          <button class="btn btn-primary w-100">Thêm mới</button>
        </div>
      </form>
      <div class="small text-muted mt-2">
        * Người thuê do admin tạo sẽ mặc định <b>active</b>.  
        * Người thuê tạo từ form thuê phòng (public) nên để <b>pending</b> và vào đây để duyệt.
      </div>
    </div>
  </div>

  <!-- Danh sách -->
  <div class="table-responsive">
    <table class="table table-sm table-bordered bg-white align-middle">
      <thead class="table-light">
        <tr>
          <th style="width:70px">ID</th>
          <th>Username</th>
          <th>Gmail</th>
          <th>Tên</th>
          <th>Phone</th>
          <th>Trạng thái</th>
          <th>Ngày tạo</th>
          <th style="width:220px"></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach($rows as $u): ?>
        <tr>
          <td><?= (int)$u['id'] ?></td>
          <td><?= esc($u['username']) ?></td>
          <td><?= esc($u['gmail']) ?></td>
          <td><?= esc($u['fullname']) ?></td>
          <td><?= esc($u['phone']) ?></td>
          <td>
            <?php
              $map = ['pending'=>['warning','Chờ duyệt'],'active'=>['success','Đã duyệt'],'rejected'=>['secondary','Từ chối']];
              [$bg,$txt] = $map[$u['status']] ?? ['light', esc($u['status'])];
            ?>
            <span class="badge bg-<?= $bg ?>"><?= $txt ?></span>
          </td>
          <td><?= esc($u['created_at'] ?? '') ?></td>
          <td class="text-nowrap">
            <?php if($u['status']==='pending'): ?>
              <form method="post" class="d-inline">
                <?php csrf_field(); ?>
                <input type="hidden" name="act" value="approve">
                <input type="hidden" name="id" value="<?=$u['id']?>">
                <button class="btn btn-sm btn-success">Duyệt</button>
              </form>
              <form method="post" class="d-inline" onsubmit="return confirm('Từ chối tài khoản này?');">
                <?php csrf_field(); ?>
                <input type="hidden" name="act" value="reject">
                <input type="hidden" name="id" value="<?=$u['id']?>">
                <button class="btn btn-sm btn-outline-secondary">Từ chối</button>
              </form>
            <?php endif; ?>

            <button class="btn btn-sm btn-outline-primary" type="button"
                    onclick="toggleEditRow(<?= (int)$u['id'] ?>)">Sửa</button>

            <form method="post" class="d-inline" onsubmit="return confirm('Xóa người thuê này?');">
              <?php csrf_field(); ?>
              <input type="hidden" name="act" value="delete">
              <input type="hidden" name="id" value="<?=$u['id']?>">
              <button class="btn btn-sm btn-outline-danger">Xóa</button>
            </form>
          </td>
        </tr>
        <!-- Hàng edit ẩn -->
        <tr id="edit-<?= (int)$u['id'] ?>" style="display:none;background:#fbfbfb">
          <td colspan="8">
            <form method="post" class="row g-2">
              <?php csrf_field(); ?>
              <input type="hidden" name="act" value="update">
              <input type="hidden" name="id" value="<?=$u['id']?>">
              <div class="col-md-3">
                <label class="form-label">Gmail</label>
                <input name="gmail" class="form-control" value="<?=esc($u['gmail'])?>">
              </div>
              <div class="col-md-3">
                <label class="form-label">Họ tên</label>
                <input name="fullname" class="form-control" value="<?=esc($u['fullname'])?>">
              </div>
              <div class="col-md-2">
                <label class="form-label">SDT</label>
                <input name="phone" class="form-control" value="<?=esc($u['phone'])?>">
              </div>
              <div class="col-md-2">
                <label class="form-label">Trạng thái</label>
                <select name="status" class="form-select">
                  <option value="pending"  <?= $u['status']==='pending'?'selected':'' ?>>Chờ duyệt</option>
                  <option value="active"   <?= $u['status']==='active'?'selected':'' ?>>Đã duyệt</option>
                  <option value="rejected" <?= $u['status']==='rejected'?'selected':'' ?>>Từ chối</option>
                </select>
              </div>
              <div class="col-md-2 align-self-end">
                <button class="btn btn-primary w-100">Lưu</button>
              </div>
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
          <a class="page-link" href="?page=<?=$i?><?= $qstr!==''? '&q='.urlencode($qstr):'' ?><?= $status!==''? '&status='.urlencode($status):'' ?>"><?=$i?></a>
        </li>
      <?php endfor; ?>
    </ul></nav>
  <?php endif; ?>
</div>

<script>
function toggleEditRow(id){
  const el = document.getElementById('edit-'+id);
  if (!el) return;
  el.style.display = (el.style.display==='none' || el.style.display==='') ? 'table-row' : 'none';
}
</script>
</body>
</html>
