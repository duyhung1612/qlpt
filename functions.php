<?php
/* core/functions.php — helpers, CSRF, upload ảnh an toàn */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . "/config.php";

/* ====== Cấu hình linh hoạt đường dẫn ====== */
define('APP_BASE', dirname(__DIR__));                 // /.../qlpt
define('PUBLIC_URL_BASE', '/qlpt/public');            // đổi nếu thư mục khác
define('UPLOAD_DIR_ROOMS', APP_BASE.'/uploads/rooms');// fs path
define('UPLOAD_URL_ROOMS', '/qlpt/uploads/rooms');    // public URL

/* ====== DB helpers ====== */
function q(string $sql, string $types = "", array $params = []) : mysqli_stmt {
  global $conn;
  $stmt = $conn->prepare($sql);
  if (!$stmt) die("Lỗi prepare: ".$conn->error);
  if ($types && $params) $stmt->bind_param($types, ...$params);
  if (!$stmt->execute()) die("Lỗi execute: ".$stmt->error);
  return $stmt;
}
function get_row(mysqli_stmt $stmt) { $rs = $stmt->get_result(); return $rs? $rs->fetch_assoc() : null; }
function get_all(mysqli_stmt $stmt) { $rs = $stmt->get_result(); return $rs? $rs->fetch_all(MYSQLI_ASSOC):[]; }

/* ====== Output & string helpers ====== */
function esc($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
function str_limit($s,$n=120){ $s=(string)$s; return mb_strlen($s)>$n? (mb_substr($s,0,$n).'…'):$s; }

/* ====== Flash ====== */
function flash_set($msg,$type='info'){ $_SESSION['flash']=['m'=>$msg,'t'=>$type]; }
function flash_get(){ $f=$_SESSION['flash']??null; if($f) unset($_SESSION['flash']); return $f; }

/* ====== Redirect ====== */
function redirect($rel){ header("Location: ".$rel); exit; }

/* ====== CSRF ====== */
function csrf_token(){ 
  if(empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32)); 
  return $_SESSION['csrf']; 
}
function csrf_field(){ echo '<input type="hidden" name="csrf" value="'.esc(csrf_token()).'">'; }
function csrf_verify(){
  $ok = !empty($_POST['csrf']) && hash_equals($_SESSION['csrf']??'', $_POST['csrf']);
  if(!$ok) { http_response_code(400); die("CSRF token không hợp lệ."); }
}

/* ====== Request helpers ====== */
function method_is($m){ return strtoupper($_SERVER['REQUEST_METHOD']??'GET')===strtoupper($m); }
function post_str($k,$def=''){ return isset($_POST[$k])? trim((string)$_POST[$k]) : $def; }
function post_int($k,$def=0){ return isset($_POST[$k])? (int)$_POST[$k] : $def; }
function get_int($k,$def=0){ return isset($_GET[$k])? (int)$_GET[$k] : $def; }

/* ====== Pagination ====== */
function paginate($total,$page,$per){
  $pages = max(1,(int)ceil($total / max(1,$per)));
  $page = max(1, min($page, $pages));
  return [$page,$pages];
}

/* ====== Upload helpers (ảnh phòng) ====== */
function ensure_upload_dirs(){
  if(!is_dir(UPLOAD_DIR_ROOMS)) @mkdir(UPLOAD_DIR_ROOMS,0777,true);
}
function safe_filename($name){
  $name = preg_replace('/[^a-zA-Z0-9_\.-]/','_', $name);
  return ltrim($name,'.'); // tránh .htaccess/.php
}
function accept_image_upload($field='image',$max=5242880,$allow=['jpg','jpeg','png','gif','webp']){
  if(empty($_FILES[$field]) || $_FILES[$field]['error']===UPLOAD_ERR_NO_FILE) return [null,null]; 
  $f = $_FILES[$field];
  if($f['error']!==UPLOAD_ERR_OK) return [null,"Lỗi upload: ".$f['error']];
  if($f['size']>$max) return [null,"File quá lớn (> ".(int)($max/1024/1024)."MB)"];
  $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
  if(!in_array($ext,$allow,true)) return [null,"Định dạng không được phép"];
  $fi = new finfo(FILEINFO_MIME_TYPE);
  $mime = $fi->file($f['tmp_name']);
  if(strpos($mime,'image/')!==0) return [null,"MIME không hợp lệ"];
  $new = time().'_'.bin2hex(random_bytes(4)).'.'.$ext;
  $new = safe_filename($new);
  ensure_upload_dirs();
  $dest = UPLOAD_DIR_ROOMS.'/'.$new;
  if(!move_uploaded_file($f['tmp_name'],$dest)) return [null,"Không thể lưu file"];
  return [$new,null];
}
