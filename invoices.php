<?php
// admin/invoices.php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/functions.php';

require_admin();

$flash = flash_get();

function scalar($sql,$types="",$params=[]){
  $row = get_row(q($sql,$types,$params));
  if(!$row) return 0;
  $v = reset($row);
  return (int)$v;
}

$default_back = 'dashboard.php';
$back = $_GET['back'] ?? ($_SERVER['HTTP_REFERER'] ?? $default_back);
$mode = $_GET['mode'] ?? 'list'; // list | create | edit

// =================== ACTIONS (POST) ===================
if (method_is('POST')) {
  csrf_verify();
  $act = post_str('act');
  $back_post = $_POST['back'] ?? 'invoices.php';

  if ($act === 'mark_paid' || $act === 'mark_unpaid') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id<=0){ flash_set("Thiếu ID hóa đơn.","warning"); redirect($back_post); }
    $status = ($act==='mark_paid') ? 'paid' : 'unpaid';
    q("UPDATE invoices SET status=? WHERE id=?","si",[$status,$id]);
    flash_set("Đã cập nhật hóa đơn #{$id} thành ".($status==='paid'?'Đã thu':'Chưa thu').".","success");
    redirect($back_post);
  }

  if ($act === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id<=0){ flash_set("Thiếu ID hóa đơn.","warning"); redirect($back_post); }
    q("DELETE FROM invoices WHERE id=?","i",[$id]);
    flash_set("Đã xóa hóa đơn #{$id}.","success");
    redirect($back_post);
  }

  if ($act === 'create_one') {
    $contract_id = (int)($_POST['contract_id'] ?? 0);
    $due_date    = post_str('due_date');
    $note        = post_str('note');
    $status      = post_str('status') ?: 'unpaid';
    $amount      = (int)($_POST['amount'] ?? 0);
    $back_post   = $_POST['back'] ?? 'invoices.php';

    if ($contract_id<=0){ flash_set("Vui lòng chọn hợp đồng.","warning"); redirect("invoices.php?mode=create&back=".urlencode($back_post)); }

    $c = get_row(q("
      SELECT c.*, r.id AS room_id, r.price AS room_price, u.id AS user_id
      FROM contracts c
      JOIN rooms  r ON r.id = c.room_id
      JOIN users  u ON u.id = c.user_id
      WHERE c.id=? AND c.status='active'
    ","i",[$contract_id]));
    if (!$c) {
      flash_set("Không tìm thấy hợp đồng đang hiệu lực.","danger");
      redirect("invoices.php?mode=create&back=".urlencode($back_post));
    }

    if ($amount<=0) $amount = (int)$c['room_price'];

    $monthStr = $_POST['month'] ?? '';           // ưu tiên month nếu form gửi lên
    if ($monthStr && preg_match('/^\d{4}-\d{2}$/',$monthStr)) {
      // ok
    } else {
      $monthStr = date('Y-m');                   // fallback
      if ($due_date && preg_match('/^\d{4}-\d{2}-\d{2}$/',$due_date)) {
        $monthStr = substr($due_date,0,7);
      }
    }

    $dup = get_row(q("SELECT id FROM invoices WHERE contract_id=? AND month=? LIMIT 1","is",[$contract_id,$monthStr]));
    if ($dup) {
      flash_set("Đã có hóa đơn của hợp đồng #{$contract_id} cho kỳ {$monthStr}.","warning");
      redirect("invoices.php?mode=create&back=".urlencode($back_post));
    }

    q("
      INSERT INTO invoices
        (contract_id, month, rent, electric_kwh, electric_price, water_m3, water_price, other_fee, discount, total, status, room_id, user_id, note, due_date)
      VALUES
        (?,?,?,?,?,?,?,?,?,?, ?, ?, ?, ?, ?)
    ","isiiiiiiiiiiiss", [
      (int)$c['id'],
      $monthStr,
      (int)$amount,
      0,0,
      0,0,
      0,
      0,
      (int)$amount,
      $status,
      (int)$c['room_id'],
      (int)$c['user_id'],
      $note,
      ($due_date ?: NULL)
    ]);

    global $conn; $inv_id = $conn->insert_id;
    flash_set("Đã tạo hóa đơn #{$inv_id}.","success");
    redirect("invoices.php?back=".urlencode($back_post));
  }

  if ($act === 'update_one') {
    $id       = (int)($_POST['id'] ?? 0);
    $monthStr = post_str('month');               // YYYY-MM
    $amount   = (int)($_POST['amount'] ?? 0);
    $status   = post_str('status') ?: 'unpaid';
    $due_date = post_str('due_date');
    $note     = post_str('note');
    $back_post= $_POST['back'] ?? 'invoices.php';

    if ($id<=0 || !preg_match('/^\d{4}-\d{2}$/',$monthStr)) {
      flash_set("Dữ liệu không hợp lệ.","warning"); redirect($back_post);
    }
    if ($amount<0) $amount = 0;

    // cập nhật: rent = amount, total = amount
    q("UPDATE invoices SET month=?, rent=?, total=?, status=?, due_date=?, note=? WHERE id=?",
      "siisssi", [$monthStr, $amount, $amount, $status, ($due_date?:NULL), $note, $id]);

    flash_set("Đã cập nhật hóa đơn #{$id}.","success");
    redirect($back_post);
  }

  flash_set("Hành động không hợp lệ.","danger");
  redirect($back_post);
}

// =================== DATA for UI ===================
$filter_status = $_GET['status'] ?? '';
$filter_ct     = (int)($_GET['contract_id'] ?? 0);
$filter_month  = $_GET['month'] ?? ''; // YYYY-MM (có lịch)

$contracts = get_all(q("
  SELECT c.id, CONCAT('#',c.id,' • Phòng ',r.name,' • ',u.username) AS label
  FROM contracts c
  JOIN rooms r ON r.id = c.room_id
  JOIN users u ON u.id = c.user_id
  ORDER BY r.name ASC, c.id ASC
","",[]));

$where=[]; $argsT=""; $argsV=[];
if ($filter_status!==''){ $where[]="i.status=?";      $argsT.="s"; $argsV[]=$filter_status; }
if ($filter_ct>0)        { $where[]="i.contract_id=?"; $argsT.="i"; $argsV[]=$filter_ct; }
if ($filter_month!=='')  { $where[]="i.month=?";       $argsT.="s"; $argsV[]=$filter_month; }
$sqlWhere = $where?("WHERE ".implode(" AND ",$where)):"";

$list = get_all(q("
  SELECT i.*, r.name AS room_name, u.username AS user_name
  FROM invoices i
  LEFT JOIN rooms r ON r.id = i.room_id
  LEFT JOIN users u ON u.id = i.user_id
  $sqlWhere
  ORDER BY i.id DESC
",$argsT,$argsV));

$active_contracts = get_all(q("
  SELECT c.id, r.name AS room_name, u.username AS tenant_name
  FROM contracts c
  JOIN rooms r ON r.id = c.room_id
  JOIN users u ON u.id = c.user_id
  WHERE c.status='active'
  ORDER BY r.name ASC, c.id ASC
","",[]));

// Nếu là edit, lấy invoice
$edit_invoice = null;
if ($mode==='edit') {
  $eid = (int)($_GET['id'] ?? 0);
  if ($eid>0) {
    $edit_invoice = get_row(q("
      SELECT i.*, r.name AS room_name, u.username AS user_name,
             CONCAT('#',i.contract_id,' • Phòng ',COALESCE(r.name, CONCAT('#',i.room_id)),' • ',COALESCE(u.username, CONCAT('#',i.user_id))) AS contract_label
      FROM invoices i
      LEFT JOIN rooms r ON r.id = i.room_id
      LEFT JOIN users u ON u.id = i.user_id
      WHERE i.id=?
    ","i",[$eid]));
    if (!$edit_invoice) { flash_set("Không tìm thấy hóa đơn.","warning"); redirect("invoices.php?back=".urlencode($back)); }
  }
}
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <title>Hóa đơn</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .table thead th{white-space:nowrap}
    .badge-status{font-size:.85rem}
  </style>
</head>
<body>
<?php include __DIR__ . '/_nav.php'; ?>

<div class="container my-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div class="d-flex gap-2">
      <a class="btn btn-outline-secondary" href="<?=esc($back)?>">« Quay lại</a>
      <h3 class="mb-0">Hóa đơn</h3>
    </div>
    <div>
      <?php if($mode!=='create' && $mode!=='edit'): ?>
        <a class="btn btn-primary" href="invoices.php?mode=create&back=<?=urlencode($back)?>">+ Tạo hóa đơn</a>
      <?php elseif($mode==='create'): ?>
        <a class="btn btn-outline-secondary" href="invoices.php?back=<?=urlencode($back)?>">« Danh sách hóa đơn</a>
      <?php else: ?>
        <a class="btn btn-outline-secondary" href="<?=esc($_GET['back'] ?? 'invoices.php')?>">« Quay lại danh sách</a>
      <?php endif; ?>
    </div>
  </div>

  <?php if($flash): ?>
    <div class="alert alert-<?=esc($flash['t'])?>"><?=esc($flash['m'])?></div>
  <?php endif; ?>

  <?php if ($mode === 'create'): ?>

    <div class="card shadow-sm">
      <div class="card-header bg-primary text-white">Tạo hóa đơn</div>
      <div class="card-body">
        <form method="post">
          <?php csrf_field(); ?>
          <input type="hidden" name="act" value="create_one">
          <input type="hidden" name="back" value="<?=esc($back)?>">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Hợp đồng</label>
              <select name="contract_id" class="form-select" required>
                <option value="">-- Chọn hợp đồng --</option>
                <?php foreach($active_contracts as $c): ?>
                  <option value="<?=esc($c['id'])?>">#<?=esc($c['id'])?> • Phòng <?=esc($c['room_name'])?> • <?=esc($c['tenant_name'])?></option>
                <?php endforeach; ?>
              </select>
              <div class="form-text">Chọn hợp đồng để tự điền số tiền nếu bạn để 0.</div>
            </div>
            <div class="col-md-6">
              <label class="form-label">Kỳ (YYYY-MM)</label>
              <input type="month" name="month" class="form-control" value="<?=date('Y-m')?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Số tiền (đ)</label>
              <input type="number" name="amount" value="0" class="form-control" min="0">
            </div>
            <div class="col-md-6">
              <label class="form-label">Trạng thái</label>
              <select name="status" class="form-select">
                <option value="unpaid">Chưa thu</option>
                <option value="paid">Đã thu</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Hạn thanh toán</label>
              <input type="date" name="due_date" class="form-control" value="<?=date('Y-m-d', strtotime('first day of next month'))?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Ghi chú</label>
              <input name="note" class="form-control" placeholder="Ví dụ: Tháng <?=date('m/Y')?>, thêm phí...">
            </div>
          </div>
          <div class="mt-3">
            <button class="btn btn-primary">Tạo hóa đơn</button>
            <a class="btn btn-outline-secondary" href="invoices.php?back=<?=urlencode($back)?>">Hủy</a>
          </div>
        </form>
      </div>
    </div>

  <?php elseif ($mode === 'edit' && $edit_invoice): ?>

    <div class="card shadow-sm">
      <div class="card-header bg-primary text-white">Sửa hóa đơn #<?=esc($edit_invoice['id'])?></div>
      <div class="card-body">
        <form method="post">
          <?php csrf_field(); ?>
          <input type="hidden" name="act" value="update_one">
          <input type="hidden" name="id" value="<?=esc($edit_invoice['id'])?>">
          <input type="hidden" name="back" value="<?=esc($_GET['back'] ?? 'invoices.php')?>">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Kỳ (YYYY-MM)</label>
              <input type="month" name="month" class="form-control" value="<?=esc($edit_invoice['month'])?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Trạng thái</label>
              <select name="status" class="form-select">
                <option value="unpaid" <?= $edit_invoice['status']==='unpaid'?'selected':'' ?>>Chưa thu</option>
                <option value="paid"   <?= $edit_invoice['status']==='paid'?'selected':'' ?>>Đã thu</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Số tiền (đ)</label>
              <input type="number" name="amount" value="<?=esc((int)$edit_invoice['total'])?>" class="form-control" min="0" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Hạn thanh toán</label>
              <input type="date" name="due_date" value="<?=esc($edit_invoice['due_date'] ?? '')?>" class="form-control">
            </div>
            <div class="col-12">
              <label class="form-label">Ghi chú</label>
              <input name="note" value="<?=esc($edit_invoice['note'] ?? '')?>" class="form-control">
              <div class="form-text">Hợp đồng: <?=esc($edit_invoice['contract_label'])?></div>
            </div>
          </div>
          <div class="mt-3">
            <button class="btn btn-primary">Lưu thay đổi</button>
            <a class="btn btn-outline-secondary" href="<?=esc($_GET['back'] ?? 'invoices.php')?>">Hủy</a>
          </div>
        </form>
      </div>
    </div>

  <?php else: ?>

    <!-- BỘ LỌC (có lịch) -->
    <form class="card card-body shadow-sm mb-3" method="get">
      <input type="hidden" name="back" value="<?=esc($back)?>">
      <div class="row g-3">
        <div class="col-sm-3">
          <label class="form-label">Trạng thái</label>
          <select name="status" class="form-select">
            <option value="">-- Tất cả --</option>
            <option value="unpaid" <?= $filter_status==='unpaid'?'selected':'' ?>>Chưa thu</option>
            <option value="paid"   <?= $filter_status==='paid'?'selected':'' ?>>Đã thu</option>
          </select>
        </div>
        <div class="col-sm-4">
          <label class="form-label">Hợp đồng</label>
          <select name="contract_id" class="form-select">
            <option value="0">-- Tất cả --</option>
            <?php foreach($contracts as $c): ?>
              <option value="<?=esc($c['id'])?>" <?= $filter_ct===(int)$c['id']?'selected':'' ?>><?=esc($c['label'])?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-sm-3">
          <label class="form-label">Kỳ (YYYY-MM)</label>
          <input type="month" name="month" class="form-control" value="<?=esc($filter_month ?: date('Y-m'))?>">
        </div>
        <div class="col-sm-2 d-flex align-items-end">
          <button class="btn btn-primary me-2">Lọc</button>
          <a class="btn btn-outline-secondary" href="invoices.php?back=<?=urlencode($back)?>">Xóa lọc</a>
        </div>
      </div>
    </form>

    <!-- DANH SÁCH -->
    <div class="card shadow-sm">
      <div class="card-header bg-light"><strong>Danh sách hóa đơn</strong></div>
      <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
          <thead>
            <tr>
              <th>#</th>
              <th>Kỳ</th>
              <th>Phòng</th>
              <th>Người thuê</th>
              <th class="text-end">Tổng tiền</th>
              <th>Trạng thái</th>
              <th style="width:260px">Thao tác</th>
            </tr>
          </thead>
          <tbody>
          <?php if(empty($list)): ?>
            <tr><td colspan="7" class="text-center text-muted py-4">Chưa có hóa đơn nào.</td></tr>
          <?php else: foreach($list as $r): $currUrl = $_SERVER['REQUEST_URI']; ?>
            <tr>
              <td><?=esc($r['id'])?></td>
              <td><span class="badge bg-secondary-subtle text-dark border"><?=esc($r['month'])?></span></td>
              <td><?=esc($r['room_name'] ?? ('#'.$r['room_id']))?></td>
              <td><?=esc($r['user_name'] ?? ('#'.$r['user_id']))?></td>
              <td class="text-end"><?=number_format((int)$r['total'])?></td>
              <td>
                <?php if($r['status']==='paid'): ?>
                  <span class="badge bg-success badge-status">Đã thu</span>
                <?php else: ?>
                  <span class="badge bg-warning text-dark badge-status">Chưa thu</span>
                <?php endif; ?>
              </td>
              <td>
                <form class="d-inline" method="post">
                  <?php csrf_field(); ?>
                  <input type="hidden" name="back" value="<?=esc($currUrl)?>">
                  <input type="hidden" name="id" value="<?=esc($r['id'])?>">
                  <input type="hidden" name="act" value="<?= $r['status']==='paid' ? 'mark_unpaid':'mark_paid' ?>">
                  <button class="btn btn-sm <?= $r['status']==='paid' ? 'btn-outline-warning':'btn-success' ?>">
                    <?= $r['status']==='paid' ? 'Đánh dấu chưa thu':'Đánh dấu đã thu' ?>
                  </button>
                </form>

                <a class="btn btn-sm btn-outline-primary" href="print_report.php?invoice_id=<?=esc($r['id'])?>" target="_blank">Xem</a>

                <a class="btn btn-sm btn-outline-secondary" href="invoices.php?mode=edit&id=<?=esc($r['id'])?>&back=<?=urlencode($currUrl)?>">
                  Sửa
                </a>

                <form class="d-inline" method="post" onsubmit="return confirm('Xóa hóa đơn #<?=esc($r['id'])?>?');">
                  <?php csrf_field(); ?>
                  <input type="hidden" name="back" value="<?=esc($currUrl)?>">
                  <input type="hidden" name="id" value="<?=esc($r['id'])?>">
                  <input type="hidden" name="act" value="delete">
                  <button class="btn btn-sm btn-outline-danger">Xóa</button>
                </form>
              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  <?php endif; ?>
</div>
</body>
</html>
