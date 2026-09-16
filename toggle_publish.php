<?php
require 'connect.php';
if (session_status() === PHP_SESSION_NONE) session_start();
$uid = (int)($_SESSION['uid'] ?? 0);
$id  = (int)($_POST['id'] ?? 0);
if (!$uid || !$id) { header('Location: index.php'); exit; }

$q = "UPDATE models m 
      SET m.is_public = IF(m.is_public=1,0,1)
      WHERE m.id = :id AND m.user_id = :uid";
$st = $pdo->prepare($q);
$st->execute(['id'=>$id, 'uid'=>$uid]);

header('Location: profile.php'); 
exit;
