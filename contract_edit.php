<?php
/* Tạo / Sửa hợp đồng cho phòng trọ – auto fill tiền thuê & tiền cọc theo phòng */
require_once __DIR__ . "/../core/auth.php";
require_admin();
require_once __DIR__ . "/../core/functions.php";

/* ---------- Helpers ---------- */
function col_exists($table,$col){
  $row = get_row(q("
    SELECT 1 ok
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1
  ","ss",[$table,$col]));
  return (bool)$row;
}

/* Hợp đồng: phát hiện cột tiền thuê */
$CONTRACT_RENT_COL = col_exists('contracts','monthly_rent') ? 'monthly_rent'
                    : (col_exists('contracts','price') ? 'price' : null);
if (!$CONTRACT_RENT_COL) {
  q("ALTER TABLE contracts ADD COLUMN monthly_rent INT NOT NULL DEFAULT 0");
  $CONTRACT_RENT_COL = 'monthly_rent';
}
/* Hợp đồng: đảm bảo status */
if (!col_exists('contracts','status')) {
  q("ALTER TABLE contracts ADD COLUMN status ENUM('active','terminated','cancelled') NOT NULL DEFAULT 'active'");
}

/* Phòng: phát hiện cột tiền cọc nếu có */
$ROOM_DEPOSIT_COL = col_exists('rooms','deposit') ? 'deposit' : null;

/* ---------- Load data ---------- */
$id       = get_int('id',0);
$editing  = $id>0;
$contract = $editing ? get_row(q("SELECT * FROM contracts WHERE id=?","i",[$id])) : null;
if ($editing && !$contract) { http_response_code(404); die("Không tìm thấy hợp đồng."); }

/* Ứng viên phòng & người thuê */
$roomSql = "SELECT id,name,price,status".($ROOM_DEPOSIT_COL? ",`$ROOM_DEPOSIT_COL` AS deposit" : "")." FROM rooms ORDER BY id DESC";
$rooms   = get_all(q($roomSql));

$tenants = get_all(q("
  SELECT id,username,gmail,fullname
  FROM users
  WHERE role='user' AND (status IS NULL OR status='active')
  ORDER BY id DESC
"));

/* ---------- Kiểm tra chồng chéo ---------- */
function has_overlap($room_id,$start,$end,$exclude_id=0){
  $sql = "
    SELECT id FROM contracts
    WHERE room_id=? AND status='active' AND id<>?
      AND NOT (
        (? IS NOT NULL AND end_date IS NOT NULL AND end_date < ?) OR
        (? IS NOT NULL AND start_date   > ?)
      )
    LIMIT 1
  ";
  return (bool) get_row(q($sql,"iissss",[
    $room_id, $exclude_id,
    $start, $start,
    $end,   $end
  ]));
}

/* ---------- POST save ---------- */
if (method_is('POST')) {
  csrf_verify();
  $room_id    = post_int('room_id');
  $user_id    = post_int('user_id');
  $start_date = post_str('start_date');
  $end_date   = trim(post_str('end_date'));
  $deposit    = post_int('deposit',0);
  $rent       = post_int('rent',0);
  $status     = post_str('status','active');

  if (!$room_id || !$user_id || $start_date==='') {
    flash_set("Vui lòng chọn phòng, người thuê và ngày bắt đầu.","warning");
    redirect($_SERVER['REQUEST_URI']);
  }
  if ($end_date === '') $end_date = NULL;

  // Nếu người dùng để 0 → tự lấy theo phòng
  $rinfo = get_row(q("SELECT price".($ROOM_DEPOSIT_COL? ",`$ROOM_DEPOSIT_COL` AS deposit": "")." FROM rooms WHERE id=?","i",[$room_id]));
  if ($rent   <= 0) $rent   = (int)($rinfo['price'] ?? 0);
  if ($deposit<= 0) $deposit= (int)($rinfo['deposit'] ?? ($rinfo['price'] ?? 0));

  if ($status==='active' && has_overlap($room_id,$start_date,$end_date,$editing?$id:0)) {
    flash_set("Phòng này đã có hợp đồng hiệu lực bị chồng chéo thời gian.","danger");
    redirect($_SERVER['REQUEST_URI']);
  }

  $data_cols = [
    'room_id'    => $room_id,
    'user_id'    => $user_id,
    'start_date' => $start_date,
    'end_date'   => $end_date,
    'deposit'    => $deposit,
    'status'     => $status,
  ];
  $data_cols[$CONTRACT_RENT_COL] = $rent;

  if ($editing) {
    $sets=[]; $types=''; $vals=[];
    foreach($data_cols as $k=>$v){
      $sets[]="`$k`=?";
      if ($k==='end_date') { $types.='s'; $vals[] = $v; }
      else if ($k===$CONTRACT_RENT_COL || $k==='deposit' || $k==='room_id' || $k==='user_id') { $types.='i'; $vals[]=(int)$v; }
      else { $types.='s'; $vals[]=$v; }
    }
    $vals[]=$id; $types.='i';
    q("UPDATE contracts SET ".implode(',',$sets)." WHERE id=?",$types,$vals);
    flash_set("Đã cập nhật hợp đồng.","success");
  } else {
    $cols = array_keys($data_cols);
    $qs   = implode(',', array_fill(0,count($cols),'?'));
    $types=''; $vals=[];
    foreach($data_cols as $k=>$v){
      if ($k==='end_date') { $types.='s'; $vals[]=$v; }
      else if ($k===$CONTRACT_RENT_COL || $k==='deposit' || $k==='room_id' || $k==='user_id'){ $types.='i'; $vals[]=(int)$v; }
      else { $types.='s'; $vals[]=$v; }
    }
    q("INSERT INTO contracts (".implode(',', $cols).") VALUES ($qs)", $types, $vals);
$id = (int)$GLOBALS['db']->insert_id; // hoặc chỉ $db->insert_id nếu biến $db đã có sẵn
flash_set("Đã tạo hợp đồng mới.", "success");
  }

  if ($status==='active') {
    q("UPDATE rooms SET status='occupied' WHERE id=?","i",[$room_id]);
  }
  redirect("contracts.php");
}

/* ---------- Prefill form ---------- */
$val = [
  'room_id'    => $contract['room_id']    ?? get_int('room_id',0),
  'user_id'    => $contract['user_id']    ?? get_int('user_id',0),
  'start_date' => $contract['start_date'] ?? date('Y-m-d'),
  'end_date'   => $contract['end_date']   ?? '',
  'deposit'    => $contract['deposit']    ?? 0,
  'rent'       => $contract[$CONTRACT_RENT_COL] ?? 0,
  'status'     => $contract['status']     ?? 'active',
];

$flash = flash_get();
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $editing? "Sửa hợp đồng" : "Tạo hợp đồng" ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<?php if (is_file(__DIR__."/_nav.php")) include __DIR__."/_nav.php"; ?>
<div class="container py-4" style="max-width:920px">
  <a href="contracts.php" class="btn btn-sm btn-outline-secondary mb-3">&laquo; Danh sách hợp đồng</a>
  <?php if($flash): ?><div class="alert alert-<?=esc($flash['t'])?>"><?=esc($flash['m'])?></div><?php endif; ?>

  <div class="card shadow-sm">
    <div class="card-header bg-primary text-white">
      <h5 class="mb-0"><?= $editing? "Sửa hợp đồng #".(int)$id : "Tạo hợp đồng" ?></h5>
    </div>
    <div class="card-body">
      <form method="post" class="row g-3" id="contractForm">
        <?php csrf_field(); ?>
        <input type="hidden" name="act" value="save">

        <div class="col-md-6">
          <label class="form-label">Phòng</label>
          <select name="room_id" id="room_id" class="form-select" required>
            <option value="">-- Chọn phòng --</option>
            <?php foreach($rooms as $r):
              $dep = isset($r['deposit']) ? (int)$r['deposit'] : (int)$r['price']; // fallback = 1 tháng tiền thuê
            ?>
              <option
                value="<?= (int)$r['id'] ?>"
                data-price="<?= (int)$r['price'] ?>"
                data-deposit="<?= $dep ?>"
                <?= $val['room_id']==$r['id']?'selected':'' ?>
              >
                #<?= (int)$r['id'] ?> - <?= esc($r['name']) ?> (<?= number_format($r['price']) ?>đ, <?= esc($r['status']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
          <div class="form-text">Chọn phòng để tự điền tiền thuê & tiền cọc.</div>
        </div>

        <div class="col-md-6">
          <label class="form-label">Người thuê</label>
          <select name="user_id" class="form-select" required>
            <option value="">-- Chọn người thuê (đã duyệt) --</option>
            <?php foreach($tenants as $t): ?>
              <option value="<?= (int)$t['id'] ?>" <?= $val['user_id']==$t['id']?'selected':'' ?>>
                #<?= (int)$t['id'] ?> - <?= esc($t['username']) ?> (<?= esc($t['gmail']) ?><?= $t['fullname']?' - '.esc($t['fullname']):'' ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-4">
          <label class="form-label">Ngày bắt đầu</label>
          <input type="date" name="start_date" class="form-control" required value="<?= esc($val['start_date']) ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Ngày kết thúc (tuỳ chọn)</label>
          <input type="date" name="end_date" class="form-control" value="<?= esc($val['end_date']) ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Tiền cọc (đ)</label>
          <input type="number" name="deposit" id="deposit" class="form-control" value="<?= (int)$val['deposit'] ?>">
        </div>

        <div class="col-md-4">
          <label class="form-label">Tiền thuê / tháng (đ)</label>
          <input type="number" name="rent" id="rent" class="form-control" required value="<?= (int)$val['rent'] ?>">
          <div class="form-text">Lưu trong cột <code><?= esc($CONTRACT_RENT_COL) ?></code></div>
        </div>

        <div class="col-md-4">
          <label class="form-label">Trạng thái</label>
          <select name="status" class="form-select">
            <option value="active"     <?= $val['status']==='active'    ?'selected':'' ?>>Đang hiệu lực</option>
            <option value="terminated" <?= $val['status']==='terminated'?'selected':'' ?>>Đã chấm dứt</option>
            <option value="cancelled"  <?= $val['status']==='cancelled' ?'selected':'' ?>>Huỷ</option>
          </select>
        </div>

        <div class="col-12">
          <button class="btn btn-primary"><?= $editing? "Cập nhật" : "Tạo hợp đồng" ?></button>
          <a href="contracts.php" class="btn btn-outline-secondary ms-2">Huỷ</a>
        </div>
      </form>

      <hr class="my-4">
      <div class="small text-muted">
        • Chọn phòng sẽ tự điền tiền thuê (theo <b>rooms.price</b>) và tiền cọc (nếu có <b>rooms.deposit</b>, nếu không hệ thống mặc định bằng 1 tháng tiền thuê).<br>
        • Hệ thống kiểm tra chồng chéo hợp đồng <b>active</b> cùng phòng & thời gian.<br>
        • Lưu hợp đồng <b>active</b> sẽ đặt phòng thành <b>occupied</b>.
      </div>
    </div>
  </div>
</div>

<script>
// Tự điền rent & deposit khi chọn phòng
(function(){
  const sel = document.getElementById('room_id');
  const rent = document.getElementById('rent');
  const dep  = document.getElementById('deposit');

  function fillByRoom(){
    const opt = sel.options[sel.selectedIndex];
    if (!opt) return;
    const p = parseInt(opt.getAttribute('data-price')||'0',10);
    const d = parseInt(opt.getAttribute('data-deposit')||'0',10);
    if (!isNaN(p) && p>0) rent.value = p;
    if (!isNaN(d) && d>0) dep.value  = d;
  }
  sel.addEventListener('change', fillByRoom);

  // Tạo mới: nếu chưa có giá trị → set ngay theo phòng đã chọn
  <?php if(!$editing): ?>
    window.addEventListener('DOMContentLoaded', () => {
      if ((rent.value==='' || parseInt(rent.value||'0',10)<=0) && sel.value) fillByRoom();
    });
  <?php endif; ?>
})();
</script>
</body>
</html>
