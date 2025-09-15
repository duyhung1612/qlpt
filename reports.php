<?php
// admin/reports.php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/functions.php';

require_admin();

/* ================= Helpers ================= */
function scalar($sql, $types = "", $params = []) {
  $row = get_row(q($sql, $types, $params));
  if (!$row) return 0;
  $v = reset($row);
  return (int)$v;
}

// Trả về mảng các tháng 'YYYY-MM' từ start → end (bao gồm end)
function month_seq($startYm, $endYm) {
  $out = [];
  $d   = DateTime::createFromFormat('Y-m', $startYm);
  $end = DateTime::createFromFormat('Y-m', $endYm);
  if (!$d || !$end) return $out;
  $d->modify('first day of this month');
  $end->modify('first day of this month');
  while ($d <= $end) {
    $out[] = $d->format('Y-m');
    $d->modify('+1 month');
  }
  return $out;
}

/* ================= Tiles nhanh ================= */
$total_rooms    = scalar("SELECT COUNT(*) FROM rooms");
$rented_rooms   = scalar("SELECT COUNT(*) FROM rooms WHERE status='occupied'");
$reserved_rooms = scalar("SELECT COUNT(*) FROM rooms WHERE status='reserved'");
$empty_rooms    = scalar("SELECT COUNT(*) FROM rooms WHERE status='empty'");
$maint_rooms    = scalar("SELECT COUNT(*) FROM rooms WHERE status='maintenance'");
$active_ctrs    = scalar("SELECT COUNT(*) FROM contracts WHERE status='active'");

/* ================= Bộ lọc chế độ xem =================
   mode:
     - month_single (mặc định): 1 tháng
     - month_range            : khoảng nhiều tháng
     - year_range             : khoảng nhiều năm
======================================================*/
$mode    = $_GET['mode'] ?? 'month_single';
$nowYm   = date('Y-m');
$nowYear = (int)date('Y');

$ms_month = $_GET['ms_month'] ?? $nowYm;       // month_single
$mr_from  = $_GET['mr_from']  ?? $nowYm;       // month_range
$mr_to    = $_GET['mr_to']    ?? $nowYm;
$yr_from  = isset($_GET['yr_from']) ? (int)$_GET['yr_from'] : $nowYear; // year_range
$yr_to    = isset($_GET['yr_to'])   ? (int)$_GET['yr_to']   : $nowYear;

// Chuẩn hóa tham số
if ($mode === 'month_range') {
  if (preg_match('/^\d{4}-\d{2}$/',$mr_from) && preg_match('/^\d{4}-\d{2}$/',$mr_to)) {
    if ($mr_from > $mr_to) { $tmp=$mr_from; $mr_from=$mr_to; $mr_to=$tmp; }
  } else {
    $mr_from = $mr_to = $nowYm;
  }
}
if ($mode === 'year_range') {
  if ($yr_from > $yr_to) { $tmp=$yr_from; $yr_from=$yr_to; $yr_to=$tmp; }
  if ($yr_from < 2000 || $yr_from > 2100) $yr_from = $nowYear;
  if ($yr_to   < 2000 || $yr_to   > 2100) $yr_to   = $nowYear;
}

/* ================= Dữ liệu Doanh thu =================
   invoices:
     - month  : 'YYYY-MM'
     - total  : INT
     - status : 'paid' → tính doanh thu
======================================================*/
$labels = [];
$data   = [];
$title  = "";

if ($mode === 'month_single') {
  // 1 THÁNG
  $valid = preg_match('/^\d{4}-\d{2}$/',$ms_month) ? $ms_month : $nowYm;
  $title = "Doanh thu tháng ".$valid;
  $sum   = scalar("SELECT COALESCE(SUM(total),0) FROM invoices WHERE status='paid' AND month=?","s",[$valid]);
  $labels = [$valid];
  $data   = [(int)$sum];

} elseif ($mode === 'month_range') {
  // KHOẢNG THÁNG
  $title = "Doanh thu theo tháng (".$mr_from." → ".$mr_to.")";
  $seq   = month_seq($mr_from, $mr_to);
  if (empty($seq)) { $seq = [$nowYm]; }

  $rows = get_all(q("
    SELECT month, COALESCE(SUM(total),0) AS rev
    FROM invoices
    WHERE status='paid' AND month BETWEEN ? AND ?
    GROUP BY month
    ORDER BY month ASC
  ","ss",[$seq[0], end($seq)]));

  $map = [];
  foreach($rows as $r) $map[$r['month']] = (int)$r['rev'];

  foreach($seq as $m) { $labels[]=$m; $data[]=$map[$m] ?? 0; }

} else {
  // KHOẢNG NĂM
  $title = "Doanh thu theo năm (".$yr_from." → ".$yr_to.")";
  $rows = get_all(q("
    SELECT LEFT(month,4) AS y, COALESCE(SUM(total),0) AS rev
    FROM invoices
    WHERE status='paid' AND LEFT(month,4) BETWEEN ? AND ?
    GROUP BY y
    ORDER BY y ASC
  ","ii",[$yr_from,$yr_to]));

  $map=[];
  foreach($rows as $r) $map[$r['y']] = (int)$r['rev'];

  for($y=$yr_from; $y<=$yr_to; $y++){ $labels[]=(string)$y; $data[]=$map[$y] ?? 0; }
}

/* ================= Donut: phòng theo trạng thái ================= */
$room_status = [
  'Còn trống' => $empty_rooms,
  'Đã thuê'   => $rented_rooms,
  'Giữ chỗ'   => $reserved_rooms,
  'Bảo trì'   => $maint_rooms,
];

/* ================= Năm cho dropdown ================= */
$year_opts = range($nowYear - 5, $nowYear + 1);
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <title>Báo cáo & Thống kê</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
  <style>
    .stat-card{border:none;color:#fff}
    .stat-card .count{font-size:2rem;font-weight:700}
    .card-section-title{font-weight:600}
    .chart-wrap{position:relative;height:360px;width:100%}
    .donut-wrap{position:relative;height:320px;width:100%}
    /* Legend HTML tự chế cho donut */
    .legend-container{
      display:flex;flex-wrap:wrap;gap:.5rem 1rem;justify-content:center;
      margin-top:.25rem
    }
    .legend-item{display:flex;align-items:center;gap:.5rem;font-size:.95rem}
    .legend-swatch{width:12px;height:12px;border-radius:3px;display:inline-block}
  </style>
</head>
<body>
<?php include __DIR__ . '/_nav.php'; ?>

<div class="container my-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Báo cáo & Thống kê</h3>
    <div>
      <a class="btn btn-outline-secondary" href="dashboard.php">« Trang chính</a>
    </div>
  </div>

  <!-- Tiles -->
  <div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-lg-2">
      <div class="card stat-card" style="background:#0d6efd"><div class="card-body"><div class="mb-1">Tổng phòng</div><div class="count"><?=esc($total_rooms)?></div></div></div>
    </div>
    <div class="col-12 col-sm-6 col-lg-2">
      <div class="card stat-card" style="background:#198754"><div class="card-body"><div class="mb-1">Đang thuê</div><div class="count"><?=esc($rented_rooms)?></div></div></div>
    </div>
    <div class="col-12 col-sm-6 col-lg-2">
      <div class="card stat-card" style="background:#0dcaf0;color:#111"><div class="card-body"><div class="mb-1">Giữ chỗ</div><div class="count"><?=esc($reserved_rooms)?></div></div></div>
    </div>
    <div class="col-12 col-sm-6 col-lg-2">
      <div class="card stat-card" style="background:#ffc107;color:#111"><div class="card-body"><div class="mb-1">Còn trống</div><div class="count"><?=esc($empty_rooms)?></div></div></div>
    </div>
    <div class="col-12 col-sm-6 col-lg-2">
      <div class="card stat-card" style="background:#6c757d"><div class="card-body"><div class="mb-1">Bảo trì</div><div class="count"><?=esc($maint_rooms)?></div></div></div>
    </div>
    <div class="col-12 col-sm-6 col-lg-2">
      <div class="card stat-card" style="background:#dc3545"><div class="card-body"><div class="mb-1">HĐ hiệu lực</div><div class="count"><?=esc($active_ctrs)?></div></div></div>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-lg-8">
      <div class="card shadow-sm">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
            <div class="card-section-title"><?=$title?></div>

            <!-- FORM BỘ LỌC -->
            <form class="row g-2 align-items-end" method="get">
              <div class="col-12">
                <div class="d-flex align-items-center flex-wrap gap-3">
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" id="m1" name="mode" value="month_single" <?= $mode==='month_single'?'checked':'' ?>>
                    <label class="form-check-label" for="m1">Một tháng</label>
                  </div>
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" id="m2" name="mode" value="month_range" <?= $mode==='month_range'?'checked':'' ?>>
                    <label class="form-check-label" for="m2">Khoảng tháng</label>
                  </div>
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" id="m3" name="mode" value="year_range" <?= $mode==='year_range'?'checked':'' ?>>
                    <label class="form-check-label" for="m3">Khoảng năm</label>
                  </div>
                </div>
              </div>

              <!-- Inputs cho từng mode -->
              <div class="col-12 col-md-5 mode-field mode-month-single" <?= $mode==='month_single'?'':'style="display:none;"' ?>>
                <label class="form-label mb-1">Tháng</label>
                <input type="month" class="form-control" name="ms_month" value="<?=esc($ms_month)?>">
              </div>

              <div class="col-6 col-md-4 mode-field mode-month-range" <?= $mode==='month_range'?'':'style="display:none;"' ?>>
                <label class="form-label mb-1">Từ tháng</label>
                <input type="month" class="form-control" name="mr_from" value="<?=esc($mr_from)?>">
              </div>
              <div class="col-6 col-md-4 mode-field mode-month-range" <?= $mode==='month_range'?'':'style="display:none;"' ?>>
                <label class="form-label mb-1">Đến tháng</label>
                <input type="month" class="form-control" name="mr_to" value="<?=esc($mr_to)?>">
              </div>

              <div class="col-6 col-md-3 mode-field mode-year-range" <?= $mode==='year_range'?'':'style="display:none;"' ?>>
                <label class="form-label mb-1">Từ năm</label>
                <select class="form-select" name="yr_from">
                  <?php foreach($year_opts as $y): ?>
                    <option value="<?=$y?>" <?= $yr_from===$y?'selected':'' ?>><?=$y?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-6 col-md-3 mode-field mode-year-range" <?= $mode==='year_range'?'':'style="display:none;"' ?>>
                <label class="form-label mb-1">Đến năm</label>
                <select class="form-select" name="yr_to">
                  <?php foreach($year_opts as $y): ?>
                    <option value="<?=$y?>" <?= $yr_to===$y?'selected':'' ?>><?=$y?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="col-auto">
                <button class="btn btn-primary">Xem</button>
                <a class="btn btn-outline-secondary" href="reports.php">Reset</a>
              </div>
            </form>
          </div>

          <!-- Biểu đồ Doanh thu -->
          <div class="chart-wrap">
            <canvas id="revenueChart"></canvas>
          </div>
          <div class="small text-muted mt-2">* Doanh thu tính theo <strong>hóa đơn đã thu</strong>.</div>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <div class="card-section-title mb-2">Phòng theo trạng thái</div>
          <div class="donut-wrap">
            <canvas id="roomStatusChart"></canvas>
          </div>
          <!-- Legend HTML tự chế, luôn căn giữa / wrap đẹp -->
          <div id="room-legend" class="legend-container"></div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
// Toggle inputs theo mode (client)
(function(){
  const radios = document.querySelectorAll('input[name="mode"]');
  const show = (cls, on) => document.querySelectorAll('.' + cls).forEach(el => el.style.display = on ? '' : 'none');
  function apply() {
    const val = document.querySelector('input[name="mode"]:checked')?.value || 'month_single';
    show('mode-month-single', val==='month_single');
    show('mode-month-range',  val==='month_range');
    show('mode-year-range',   val==='year_range');
  }
  radios.forEach(r => r.addEventListener('change', apply));
  apply();
})();

// ===== Revenue Chart (legend bên dưới, căn giữa) =====
const revenueLabels = <?=json_encode(array_values($labels))?>;
const revenueData   = <?=json_encode(array_values($data))?>;
const isLine = revenueLabels.length >= 6;

new Chart(document.getElementById('revenueChart').getContext('2d'), {
  type: isLine ? 'line' : 'bar',
  data: {
    labels: revenueLabels,
    datasets: [{
      label: 'Doanh thu',
      data: revenueData,
      borderWidth: 2,
      tension: .2,
      fill: false
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        display: true,
        position: 'bottom',
        align: 'center',
        labels: { padding: 16, boxWidth: 18 }
      },
      tooltip: {
        callbacks: {
          label: (ctx) => {
            const v = ctx.parsed.y || 0;
            return ' ' + v.toLocaleString('vi-VN') + ' đ';
          }
        }
      }
    },
    scales: {
      y: {
        beginAtZero: true,
        ticks: { callback: (val) => val.toLocaleString('vi-VN') }
      }
    }
  }
});

// ===== Donut + Legend HTML tuỳ chỉnh (cân, gọn, không rớt lộn xộn) =====
const rsLabels = <?=json_encode(array_keys($room_status), JSON_UNESCAPED_UNICODE)?>;
const rsData   = <?=json_encode(array_values($room_status))?>;

// Plugin render legend HTML
const htmlLegendPlugin = {
  id: 'htmlLegend',
  afterUpdate(chart, args, opts) {
    const container = document.getElementById(opts.containerID);
    if (!container) return;
    container.innerHTML = '';
    const items = chart.options.plugins.legend.labels.generateLabels(chart);
    items.forEach(item => {
      const wrap = document.createElement('div');
      wrap.className = 'legend-item';
      const box = document.createElement('span');
      box.className = 'legend-swatch';
      box.style.background = item.fillStyle;
      box.style.borderColor = item.strokeStyle;
      const text = document.createElement('span');
      text.textContent = item.text;
      wrap.appendChild(box);
      wrap.appendChild(text);
      // toggle dataset visibility
      wrap.onclick = () => chart.toggleDataVisibility(item.index) || chart.update();
      container.appendChild(wrap);
    });
  }
};

new Chart(document.getElementById('roomStatusChart').getContext('2d'), {
  type: 'doughnut',
  data: { labels: rsLabels, datasets: [{ data: rsData }] },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { display: false },                  // Ẩn legend mặc định
      htmlLegend: { containerID: 'room-legend' }   // Dùng legend HTML
    },
    cutout: '55%'
  },
  plugins: [htmlLegendPlugin]
});
</script>
</body>
</html>
