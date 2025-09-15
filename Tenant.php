<?php
/* models/Tenant.php — CRUD người thuê (role='user') */
require_once __DIR__ . '/../core/functions.php';

function tenant_get($id){
  return get_row(q("SELECT * FROM users WHERE id=? AND role='user'","i",[$id]));
}

function tenant_list($kw=''){
  $where="WHERE role='user'"; $params=[]; $types='';
  if($kw!==''){ 
    $where.=" AND (username LIKE CONCAT('%',?,'%') OR gmail LIKE CONCAT('%',?,'%'))"; 
    $types.="ss"; $params[]=$kw; $params[]=$kw; 
  }
  return get_all(q("SELECT * FROM users $where ORDER BY id DESC",$types,$params));
}

function tenant_create($data){
  q("INSERT INTO users (username,gmail,password,role,full_name,phone,created_at) VALUES (?,?,?,?,?,?,NOW())",
    "ssssss", [
      $data['username'],
      $data['gmail'],
      $data['password'],
      'user',
      $data['full_name'],
      $data['phone']
    ]);
  global $conn; return $conn->insert_id;
}

function tenant_update($id,$data){
  q("UPDATE users SET username=?,gmail=?,password=?,full_name=?,phone=? WHERE id=? AND role='user'",
    "sssssi", [
      $data['username'],
      $data['gmail'],
      $data['password'],
      $data['full_name'],
      $data['phone'],
      $id
    ]);
}

function tenant_delete($id){
  q("DELETE FROM users WHERE id=? AND role='user'","i",[$id]);
}
