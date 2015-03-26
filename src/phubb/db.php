<?php
$db = new PDO('mysql:dbname=phubb;host=127.0.0.1', 'phubb', 'phubb');
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
return $db;
?>