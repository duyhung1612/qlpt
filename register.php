<?php
require_once __DIR__ . "/../core/auth.php";
require_once __DIR__ . "/../core/functions.php";

if (is_logged_in()) redirect("index.php");

if (method_is('POST')) {
  csrf_verify();
  $username = post_str('username');
  $gmail    = post_str('gmail');
  $password = post_str('password');
  $repass   = post_str('repass');

  // Validate cơ bản
  if ($username === '' || $gmail === '' || $password === '' || $repass === '') {
    flash_set("Vui lòng nhập đầy đủ thông tin.","warning"); redirect("register.php");
  }
  if (!filter_var($gmail, FILTER_VALIDATE_EMAIL)) {
    flash_set("Gmail không hợp lệ.","warning"); redirect("register.php");
  }
  if ($password !== $repass) {
    flash_set("Mật khẩu nhập lại không khớp.","warning"); redirect("register.php");
  }

  // Trùng username hoặc gmail?
  $dup = get_row(q("SELECT id FROM users WHERE username=? OR gmail=?","ss",[$username,$gmail]));
  if ($dup) {
    flash_set("Username hoặc Gmail đã tồn tại.","danger"); redirect("register.php");
  }

  // Hash mật khẩu
  $hash = password_hash($password, PASSWORD_DEFAULT);

  // Tạo user mới (role mặc định: user)
  q("INSERT INTO users (username,gmail,password,role,created_at) VALUES (?,?,?,?,NOW())",
    "ssss", [$username,$gmail,$hash,'user']);

  flash_set("Đăng ký thành công, vui lòng đăng nhập.","success");
  redirect("login.php");
}

$flash = flash_get();
?>
<!doctype html>
<html lang="vi"><head>
<meta charset="utf-8"><title>Đăng ký</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head><body class="bg-light">
<div class="container py-5" style="max-width:420px">
  <h3 class="mb-3 text-center">Đăng ký</h3>
  <?php if($flash): ?><div class="alert alert-<?=esc($flash['t'])?>"><?=esc($flash['m'])?></div><?php endif; ?>
  <form method="post" class="card card-body shadow-sm">
    <?php csrf_field(); ?>
    <div class="mb-3">
      <label class="form-label">Username</label>
      <input name="username" class="form-control" required>
    </div>
    <div class="mb-3">
      <label class="form-label">Gmail</label>
      <input type="email" name="gmail" class="form-control" required>
    </div>
    <div class="mb-3">
      <label class="form-label">Mật khẩu</label>
      <input type="password" name="password" class="form-control" required>
    </div>
    <div class="mb-3">
      <label class="form-label">Nhập lại mật khẩu</label>
      <input type="password" name="repass" class="form-control" required>
    </div>
    <button class="btn btn-primary">Đăng ký</button>
    <div class="mt-3 text-center"><a href="login.php">Đã có tài khoản? Đăng nhập</a></div>
  </form>
</div>
</body></html>
