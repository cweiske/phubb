<?php
require __DIR__ . '/../../data/phubb.config.php';
$db = new PDO(
    'mysql:dbname=' . $dbName
    . ';host=' . $dbHost,
    $dbUser, $dbPass
);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
return $db;
?>