<?php
namespace phubb;
header('HTTP/1.0 500 Internal Server Error');

require_once __DIR__ . '/../src/phubb/functions.php';

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
$req = new Model_SubscriptionRequest();
$req->callback     = $hubCallback;
$req->topic        = $hubTopic;
$req->mode         = $hubMode;
$req->leaseSeconds = $hubLeaseSeconds;
$req->secret       = $hubSecret;

storeSubscriptionRequest($req);
initiateVerification($req);

header('HTTP/1.0 202 Accepted');
exit();


function initiateVerification(Model_SubscriptionRequest $req)
{
    $gmclient= new \GearmanClient();
    $gmclient->addServer();
    $gmclient->doBackground('phubb_verify', serialize($req));
    if ($gmclient->returnCode() != GEARMAN_SUCCESS) {
        header('HTTP/1.0 500 Internal Server Error');
        echo "Error sending verification job\n";
        exit(1);
    }
}


function storeSubscriptionRequest(Model_SubscriptionRequest $req)
{
    //FIXME: handle duplicate subscription requests?
    $db = require __DIR__ . '/../src/phubb/db.php';
    $db->prepare(
        'INSERT INTO requests'
        . '(req_created, req_callback, req_topic, req_mode, req_lease_seconds'
        . ', req_secret, req_validated)'
        . ' VALUES(NOW(), :callback, :topic, :mode, :leaseSeconds, :secret, 1)'
    )->execute(
        array(
            ':callback' => $req->callback,
            ':topic' => $req->topic,
            ':mode' => $req->mode,
            ':leaseSeconds' => $req->leaseSeconds,
            ':secret' => $req->secret
        )
    );
}

function isValidTopic($url)
{
    //TODO: implement URL filtering
    return true;
}
?>
