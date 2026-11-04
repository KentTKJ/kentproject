<?php
require 'lib.php';
$u = user();
if (!$u) { header('Location: login.php'); exit; }
$to = ['admin' => 'admin.php', 'piket' => 'piket.php', 'pembagi' => 'pembagi.php'][$u['role']];
header('Location: ' . $to);
