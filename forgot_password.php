<?php
require_once __DIR__ . "/../core/auth.php";
require_once __DIR__ . "/../core/functions.php";

if (is_logged_in()) redirect("index.php");

if (method_is('POST')) {
  csrf_verify();
  $username = post_str('username');
  $gmail    = post_str('gmail');
  $newpass  = post_str('newpass');
  $repass   = post_str('repass');

  if ($newpass!==$repass) {
    flash_set("Mật khẩu nhập lại không khớp.","warning"); redirect("forgot_password.php");
  }
  $u = get_row(q("SELECT * FROM users WHERE username=? AND gmail=?","ss",[$username,$gmail]));
  if(!$u){ flash_set("Không tìm thấy tài khoản.","danger"); redirect("forgot_password.php"); }

  q("UPDATE users SET password=? WHERE id=?","si",[$newpass,$u['id']]);
  flash_set("Đã đặt lại mật khẩu, hãy đăng nhập.","success");
  redirect("login.php");
}
$flash = flash_get();
?>
<!doctype html>
<html lang="vi"><head>
<meta charset="utf-8"><title>Quên mật khẩu</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head><body class="bg-light">
<div class="container py-5" style="max-width:420px">
  <h3 class="mb-3 text-center">Quên mật khẩu</h3>
  <?php if($flash): ?><div class="alert alert-<?=esc($flash['t'])?>"><?=esc($flash['m'])?></div><?php endif; ?>
  <form method="post" class="card card-body shadow-sm">
    <?php csrf_field(); ?>
    <div class="mb-3"><label class="form-label">Username</label><input name="username" class="form-control" required></div>
    <div class="mb-3"><label class="form-label">Gmail</label><input type="email" name="gmail" class="form-control" required></div>
    <div class="mb-3"><label class="form-label">Mật khẩu mới</label><input type="password" name="newpass" class="form-control" required></div>
    <div class="mb-3"><label class="form-label">Nhập lại</label><input type="password" name="repass" class="form-control" required></div>
    <button class="btn btn-primary">Đặt lại</button>
    <div class="mt-3 text-center"><a href="login.php">Quay lại đăng nhập</a></div>
  </form>
</div>
</body></html>
