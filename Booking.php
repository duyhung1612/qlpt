<?php
/* models/Booking.php — CRUD booking */
require_once __DIR__ . '/../core/functions.php';

function booking_get($id){
  return get_row(q("SELECT * FROM bookings WHERE id=?","i",[$id]));
}

function booking_list($status=''){
  $where=""; $params=[]; $types='';
  if($status!==''){ $where="WHERE b.status=?"; $types="s"; $params[]=$status; }
  return get_all(q("SELECT b.*, r.name AS room_name, u.username,u.gmail
                    FROM bookings b
                    JOIN rooms r ON r.id=b.room_id
                    JOIN users u ON u.id=b.user_id
                    $where
                    ORDER BY b.id DESC",$types,$params));
}

function booking_create($data){
  q("INSERT INTO bookings (user_id,room_id,status,created_at) VALUES (?,?,?,NOW())",
    "iis", [$data['user_id'],$data['room_id'],$data['status']??'pending']);
  global $conn; return $conn->insert_id;
}

function booking_update_status($id,$status){
  q("UPDATE bookings SET status=? WHERE id=?","si",[$status,$id]);
}

function booking_delete($id){
  q("DELETE FROM bookings WHERE id=?","i",[$id]);
}
