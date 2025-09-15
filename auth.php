<?php
// core/auth.php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/functions.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Lấy user hiện tại từ session (trả về mảng ['id','username','role','gmail',...]
 * hoặc null nếu chưa đăng nhập)
 */
function current_user() {
    if (!empty($_SESSION['uid'])) {
        // lấy nhanh từ session nếu đã cache
        if (!empty($_SESSION['user_cache'])) return $_SESSION['user_cache'];

        $u = get_row(q("SELECT * FROM users WHERE id=? LIMIT 1","i", [$_SESSION['uid']]));
        if ($u) {
            $_SESSION['user_cache'] = $u;
            return $u;
        }
    }
    return null;
}

/** Đã đăng nhập chưa */
function is_logged_in() {
    return !empty($_SESSION['uid']);
}

/** Có phải admin không */
function is_admin() {
    $u = current_user();
    return $u && ($u['role'] === 'admin');
}

/** Yêu cầu đăng nhập (dùng cho trang public cần user đã login) */
function require_login() {
    if (!is_logged_in()) {
        flash_set("Vui lòng đăng nhập trước.","warning");
        redirect("/qlpt/public/login.php");
    }
}

/** Yêu cầu admin (dùng cho trang /admin) */
function require_admin() {
    if (!is_admin()) {
        flash_set("Bạn không có quyền truy cập khu vực quản trị.","danger");
        redirect("/qlpt/public/login.php");
    }
}

/** Đăng nhập: set session từ một row user hợp lệ */
function login_user(array $user) {
    $_SESSION['uid'] = (int)$user['id'];
    unset($_SESSION['user_cache']); // sẽ load lại bằng current_user()
}

/** Đăng xuất */
function logout_user() {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}
