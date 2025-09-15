<?php
// admin/migrate_passwords.php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/functions.php';

require_admin(); // chỉ admin mới được chạy

// Phát hiện mật khẩu đã hash hay chưa:
// - bcrypt: $2y$, $2b$, $2a$
// - argon2: $argon2i$, $argon2id$
function looks_hashed($pwd) {
  if (!is_string($pwd) || $pwd === '') return false;
  return (strpos($pwd, '$2y$') === 0)
      || (strpos($pwd, '$2b$') === 0)
      || (strpos($pwd, '$2a$') === 0)
      || (strpos($pwd, '$argon2i$') === 0)
      || (strpos($pwd, '$argon2id$') === 0);
}

if (method_is('POST')) {
  csrf_verify();

  // Đảm bảo cột password đủ dài (tuỳ schema của bạn; bỏ qua nếu đã đúng)
  // q("ALTER TABLE users MODIFY password VARCHAR(255) NOT NULL"); // uncomment nếu cần

  $users = get_all(q("SELECT id, username, password FROM users", "", []));
  $migrated = 0; $skipped = 0;

  foreach ($users as $u) {
    $pwd = (string)$u['password'];
    if ($pwd === '' || looks_hashed($pwd)) { // đã hash hoặc rỗng -> bỏ qua
      $skipped++;
      continue;
    }
    // Hiện trạng DB cũ: password đang là PLAIN -> hash luôn giá trị hiện có
    $newHash = password_hash($pwd, PASSWORD_DEFAULT);
    q("UPDATE users SET password=? WHERE id=?","si",[$newHash,(int)$u['id']]);
    $migrated++;
  }

  flash_set("Đã migrate xong: {$migrated} tài khoản chuyển sang hash, {$skipped} bỏ qua.", "success");
  redirect("migrate_passwords.php");
}

$flash = flash_get();

// Thống kê trước khi chạy
$rows = get_all(q("SELECT id, username, password FROM users", "", []));
$total = count($rows);
$need  = 0;
foreach ($rows as $r) {
  if (!looks_hashed((string)$r['password'])) $need++;
}
$done = $total - $need;
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <title>Migrate mật khẩu → password_hash()</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container py-4" style="max-width:720px">
  <h3 class="mb-3">Migrate mật khẩu người dùng sang <code>password_hash()</code></h3>

  <?php if($flash): ?>
    <div class="alert alert-<?=esc($flash['t'])?>"><?=esc($flash['m'])?></div>
  <?php endif; ?>

  <div class="card shadow-sm mb-3">
    <div class="card-body">
      <p class="mb-1"><strong>Tổng tài khoản:</strong> <?=esc($total)?></p>
      <p class="mb-1"><strong>Đã ở dạng hash:</strong> <?=esc($done)?></p>
      <p class="mb-0"><strong>Cần chuyển đổi:</strong> <?=esc($need)?></p>
    </div>
  </div>

  <?php if ($need > 0): ?>
    <div class="alert alert-warning">
      <div><strong>Lưu ý:</strong> Script sẽ HASH trực tiếp giá trị mật khẩu hiện tại đang lưu (PLAIN) trong DB của từng người dùng. Sau khi chạy, tất cả sẽ dùng <code>password_verify()</code>.</div>
    </div>
    <form method="post" class="d-inline">
      <?php csrf_field(); ?>
      <button class="btn btn-primary">Chạy migrate ngay</button>
    </form>
  <?php else: ?>
    <div class="alert alert-success">
      Tất cả tài khoản đều đã lưu mật khẩu ở dạng HASH. Không cần migrate thêm.
    </div>
  <?php endif; ?>

  <div class="mt-3">
    <a href="dashboard.php" class="btn btn-outline-secondary">« Về Dashboard</a>
  </div>
</div>
</body>
</html>
