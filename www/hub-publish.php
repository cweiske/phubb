<?php
/**
 * Notify the hub that something was published
 *
 * POST with hub.mode and hub.url, form-encoded.
 *
 * @link https://github.com/pubsubhubbub/PubSubHubbub/issues/33
 */
namespace phubb;
header('HTTP/1.0 500 Internal Server Error');

require_once __DIR__ . '/../src/phubb/functions.php';
$log = new Logger();
$db = new Db($log);

if (!isset($_POST['hub_mode'])) {
    header('HTTP/1.0 400 Bad Request');
    echo "Parameter missing: hub.mode\n";
    exit(1);
}
if ($_POST['hub_mode'] != 'publish') {
    header('HTTP/1.0 400 Bad Request');
    echo "Invalid parameter value for hub.mode\n";
    exit(1);
}

if (!isset($_POST['hub_url']) && isset($_POST['hub_topic'])) {
    $_POST['hub_url'] = $_POST['hub_topic'];
}
if (!isset($_POST['hub_url'])) {
    header('HTTP/1.0 400 Bad Request');
    echo "Parameter missing: hub.url\n";
    exit(1);
}
if (!isValidUrl($_POST['hub_url'])) {
    header('HTTP/1.0 400 Bad Request');
    echo "Invalid parameter value for hub.url: Invalid URL\n";
    exit(1);
}
if (!isValidTopic($_POST['hub_url'])) {
    header('HTTP/1.0 400 Bad Request');
    echo "Invalid parameter value for hub.url: URL not allowed\n";
    exit(1);
}

$hubUrl = $_POST['hub_url'];

$log->notice('Received publish request', array('topic' => $hubUrl));

//handle task in background
$gmclient= new \GearmanClient();
$gmclient->addServer('127.0.0.1');
$gmclient->doBackground('phubb_publish', $hubUrl);
if ($gmclient->returnCode() != GEARMAN_SUCCESS) {
    header('HTTP/1.0 500 Internal Server Error');
    echo "Error sending publish job\n";
    exit(1);
}

header('HTTP/1.0 202 Accepted');
echo "We will ping the subscribers now\n";
?>
