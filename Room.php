<?php
/* models/Room.php — CRUD phòng */
require_once __DIR__ . '/../core/functions.php';

function room_get($id){
  return get_row(q("SELECT * FROM rooms WHERE id=?","i",[$id]));
}

function room_list($kw='',$status=''){
  $where="WHERE 1=1"; $params=[]; $types='';
  if($kw!==''){ 
    $where.=" AND (name LIKE CONCAT('%',?,'%') OR description LIKE CONCAT('%',?,'%'))"; 
    $types.="ss"; $params[]=$kw; $params[]=$kw; 
  }
  if($status!==''){ 
    $where.=" AND status=?"; 
    $types.="s"; $params[]=$status; 
  }
  return get_all(q("SELECT * FROM rooms $where ORDER BY id DESC",$types,$params));
}

function room_create($data){
  q("INSERT INTO rooms (name,price,area,description,status,image,created_at) VALUES (?,?,?,?,?,?,NOW())",
    "siisss", [
      $data['name'],
      $data['price'],
      $data['area'],
      $data['description'],
      $data['status'],
      $data['image']??null
    ]);
  global $conn; return $conn->insert_id;
}

function room_update($id,$data){
  q("UPDATE rooms SET name=?,price=?,area=?,description=?,status=?,image=? WHERE id=?",
    "siisssi", [
      $data['name'],
      $data['price'],
      $data['area'],
      $data['description'],
      $data['status'],
      $data['image']??null,
      $id
    ]);
}

function room_delete($id){
  q("DELETE FROM rooms WHERE id=?","i",[$id]);
}
