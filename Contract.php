<?php
/* models/Contract.php — CRUD hợp đồng */
require_once __DIR__ . '/../core/functions.php';

function contract_get($id){
  return get_row(q("SELECT * FROM contracts WHERE id=?","i",[$id]));
}

function contract_list(){
  return get_all(q("SELECT c.*, r.name AS room_name, u.username,u.gmail
                    FROM contracts c
                    JOIN rooms r ON r.id=c.room_id
                    JOIN users u ON u.id=c.user_id
                    ORDER BY c.id DESC"));
}

function contract_create($data){
  q("INSERT INTO contracts (room_id,user_id,start_date,end_date,price,deposit,status,created_at) 
     VALUES (?,?,?,?,?,?,?,NOW())",
    "iisssis", [
      $data['room_id'],
      $data['user_id'],
      $data['start_date'],
      $data['end_date'],
      $data['price'],
      $data['deposit'],
      $data['status']
    ]);
  global $conn; return $conn->insert_id;
}

function contract_update($id,$data){
  q("UPDATE contracts SET room_id=?,user_id=?,start_date=?,end_date=?,price=?,deposit=?,status=? WHERE id=?",
    "iisssisi", [
      $data['room_id'],
      $data['user_id'],
      $data['start_date'],
      $data['end_date'],
      $data['price'],
      $data['deposit'],
      $data['status'],
      $id
    ]);
}

function contract_delete($id){
  q("DELETE FROM contracts WHERE id=?","i",[$id]);
}
