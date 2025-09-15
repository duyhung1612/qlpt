<?php
// public/logout.php
require_once __DIR__ . "/../core/auth.php";
logout_user();
flash_set("Bạn đã đăng xuất.","success");
redirect("/qlpt/public/index.php");
