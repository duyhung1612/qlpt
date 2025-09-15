<?php
/* models/Invoice.php — CRUD hóa đơn */
require_once __DIR__ . '/../core/functions.php';

function invoice_get($id){
  return get_row(q("SELECT * FROM invoices WHERE id=?","i",[$id]));
}

function invoice_list(){
  return get_all(q("SELECT i.*, c.room_id, r.name AS room_name, u.username
                    FROM invoices i
                    JOIN contracts c ON c.id=i.contract_id
                    JOIN rooms r ON r.id=c.room_id
                    JOIN users u ON u.id=c.user_id
                    ORDER BY i.id DESC"));
}

function invoice_create($data){
  q("INSERT INTO invoices (contract_id,amount,due_date,status,note,created_at) VALUES (?,?,?,?,?,NOW())",
    "iisss", [
      $data['contract_id'],
      $data['amount'],
      $data['due_date'],
      $data['status'],
      $data['note']
    ]);
  global $conn; return $conn->insert_id;
}

function invoice_update($id,$data){
  q("UPDATE invoices SET contract_id=?,amount=?,due_date=?,status=?,note=? WHERE id=?",
    "iisssi", [
      $data['contract_id'],
      $data['amount'],
      $data['due_date'],
      $data['status'],
      $data['note'],
      $id
    ]);
}

function invoice_delete($id){
  q("DELETE FROM invoices WHERE id=?","i",[$id]);
}
