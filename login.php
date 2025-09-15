<?php
require_once __DIR__ . "/../core/auth.php"; // đã có session + functions

if (method_is('POST')) {
    csrf_verify();
    $username = trim(post_str('username'));
    $password = post_str('password');

    if ($username === '' || $password === '') {
        flash_set("Vui lòng nhập đủ Username và Mật khẩu.","warning");
        redirect("login.php");
    }

    // Tìm user theo username hoặc gmail
    $u = get_row(q("SELECT * FROM users WHERE username=? OR gmail=? LIMIT 1","ss",[$username,$username]));
    if (!$u) {
        flash_set("Sai tài khoản hoặc mật khẩu.","danger");
        redirect("login.php");
    }

    $ok = false;

    // Thử hash
    if (password_verify($password, $u['password'])) {
        $ok = true;
    } else {
        // Fallback plain → hash ngay
        if (hash_equals((string)$u['password'], (string)$password)) {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            q("UPDATE users SET password=? WHERE id=?","si",[$newHash,(int)$u['id']]);
            $u['password'] = $newHash;
            $ok = true;
        }
    }

    if (!$ok) {
        flash_set("Sai tài khoản hoặc mật khẩu.","danger");
        redirect("login.php");
    }

    // Không kiểm tra status nữa → cho login thẳng
    login_user($u);

    if ($u['role'] === 'admin') {
        redirect("/qlpt/admin/dashboard.php");
    } else {
        $back = $_GET['back'] ?? "/qlpt/public/index.php";
        redirect($back);
    }
}

$flash = flash_get();
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8"><title>Đăng nhập</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-lg-4 col-md-6">
      <h3 class="mb-3 text-center">Đăng nhập</h3>
      <?php if($flash): ?><div class="alert alert-<?=esc($flash['t'])?>"><?=esc($flash['m'])?></div><?php endif; ?>
      <form method="post" class="card card-body shadow-sm">
        <?php csrf_field(); ?>
        <div class="mb-3">
          <label class="form-label">Username hoặc Gmail</label>
          <input name="username" class="form-control" required autofocus>
        </div>
        <div class="mb-3">
          <label class="form-label">Mật khẩu</label>
          <input name="password" type="password" class="form-control" required>
        </div>
        <button class="btn btn-primary w-100">Đăng nhập</button>
      </form>
      <div class="text-center mt-3">
        <a href="index.php" class="small">« Về trang chủ</a>
      </div>
    </div>
  </div>
</div>
</body>
</html>
