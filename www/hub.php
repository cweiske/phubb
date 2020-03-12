<?php
namespace phubb;
header('HTTP/1.0 500 Internal Server Error');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    require_once __DIR__ . '/../vendor/autoload.php';
    header('Location: ' . getHubIndex());
    exit();
} else if (!isset($_POST['hub_mode'])) {
    header('HTTP/1.0 400 Bad Request');
    echo "POST data missing\n";
    exit(1);
}

if ($_POST['hub_mode'] == 'subscribe'
    || $_POST['hub_mode'] == 'unsubscribe'
) {
    require 'hub-subscribe.php';
} else if ($_POST['hub_mode'] == 'publish') {
    require 'hub-publish.php';
} else {
    header('HTTP/1.0 400 Bad Request');
    echo "Unknown hub.mode\n";
}
?>
