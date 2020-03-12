<?php
/**
 * Show subscriber count as image for a given feed
 */
namespace phubb;
header('HTTP/1.0 500 Internal Server Error');

require_once __DIR__ . '/../vendor/autoload.php';

if (!isset($_GET['topic'])) {
    header('HTTP/1.0 400 Bad Request');
    echo "Parameter missing: topic\n";
    exit(1);
}
if (!isValidUrl($_GET['topic'])) {
    header('HTTP/1.0 400 Bad Request');
    echo "Invalid parameter value for topic: Invalid URL\n";
    exit(1);
}
$topic = $_GET['topic'];

$db = new Db(new Logger());
$stmt = $db->prepare(
    'SELECT COUNT(*) as count FROM subscriptions'
    . ' WHERE sub_topic = :topic'
);
$stmt->execute([':topic' => $topic]);
$row = $stmt->fetch();

//header('Content-type: text/plain');
//echo $row->count . "\n";

$tpl = file_get_contents(__DIR__ . '/../data/templates/counter.svg');
header('Content-type: image/svg+xml');
echo str_replace('{%COUNT%}', $row->count, $tpl);
?>
