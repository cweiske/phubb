<?php
header('HTTP/1.0 500 Internal Server Error');
$defaultLeaseSeconds = 86400;
//PHP converts dots to underscore, so hub.mode becomes hub_mode
if (!isset($_POST['hub_callback'])) {
    header('HTTP/1.0 400 Bad Request');
    echo "Parameter missing: hub.callback\n";
    exit(1);
}
if (!isValidUrl($_POST['hub_callback'])) {
    header('HTTP/1.0 400 Bad Request');
    echo "Invalid parameter value for hub.callback: Invalid URL\n";
    exit(1);
}
$hubCallback = $_POST['hub_callback'];

if (!isset($_POST['hub_topic'])) {
    header('HTTP/1.0 400 Bad Request');
    echo "Parameter missing: hub.topic\n";
    exit(1);
}
if (!isValidUrl($_POST['hub_topic'])) {
    header('HTTP/1.0 400 Bad Request');
    echo "Invalid parameter value for hub.topic: Invalid URL\n";
    exit(1);
}
if (!isValidTopic($_POST['hub_topic'])) {
    header('HTTP/1.0 400 Bad Request');
    echo "Invalid parameter value for hub.topic: URL not allowed\n";
    exit(1);
}
$hubTopic = $_POST['hub_topic'];

if (!isset($_POST['hub_mode'])) {
    header('HTTP/1.0 400 Bad Request');
    echo "Parameter missing: hub.mode\n";
    exit(1);
}
if ($_POST['hub_mode'] != 'subscribe' && $_POST['hub_mode'] != 'unsubscribe') {
    header('HTTP/1.0 400 Bad Request');
    echo "Invalid parameter value for hub.mode\n";
    exit(1);
}
$hubMode = $_POST['hub_mode'];

if (isset($_POST['hub_lease_seconds'])) {
    if (!is_numeric($_POST['hub_lease_seconds'])) {
        header('HTTP/1.0 400 Bad Request');
        echo "Invalid parameter value for hub.lease_seconds\n";
        exit(1);
    }
    $hubLeaseSeconds = (int) $_POST['hub_lease_seconds'];
    if ($hubLeaseSeconds > 7 * 86400) {
        $hubLeaseSeconds = 7 * 86400;
    }
} else {
    $hubLeaseSeconds = $defaultLeaseSeconds;
}

if (isset($_POST['hub_secret'])) {
    if (strlen($_POST['hub_secret']) >= 200) {
        header('HTTP/1.0 400 Bad Request');
        echo "Invalid parameter value for hub.secret: too long\n";
        exit(1);
    }
    $hubSecret = $_POST['hub_secret'];
} else {
    $hubSecret = null;
}

storeSubscriptionRequest(
    $hubCallback,
    $hubTopic,
    $hubMode,
    $hubLeaseSeconds,
    $hubSecret
);
header('HTTP/1.0 202 Accepted');
exit();


function storeSubscriptionRequest($callback, $topic, $mode, $leaseSeconds, $secret)
{
    //FIXME: handle duplicate subscription requests?
    $db = new PDO('mysql:dbname=phubb;host=127.0.0.1', 'phubb', 'phubb');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->prepare(
        'INSERT INTO requests'
        . '(req_created, req_callback, req_topic, req_mode, req_lease_seconds'
        . ', req_secret, req_validated)'
        . ' VALUES(NOW(), :callback, :topic, :mode, :leaseSeconds, :secret, 1)'
    )->execute(
        array(
            ':callback' => $callback,
            ':topic' => $topic,
            ':mode' => $mode,
            ':leaseSeconds' => $leaseSeconds,
            ':secret' => $secret
        )
    );
}

function isValidTopic($url)
{
    //TODO: implement URL filtering
    return true;
}

function isValidUrl($url)
{
    if (filter_var($url, FILTER_VALIDATE_URL) === false) {
        return false;
    }
    if (substr($url, 0, 7) == 'http://'
        || substr($url, 0, 8) == 'https://'
    ) {
        return true;
    }
    return false;
}
?>
