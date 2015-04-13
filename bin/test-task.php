#!/usr/bin/env php
<?php
namespace phubb;
require_once __DIR__ . '/../src/phubb/functions.php';
$log = new Logger();
$db = new Db($log);

$params = $argv;
array_shift($params);

$arTasks = array(
    'publish' => new Task_Publish($db, $log),
    'notifysubscriber' => new Task_NotifySubscriber($db, $log),
    'verify' => new Task_Verify($db, $log),
    'cleanup_pingrequest' => new Task_CleanupPingRequest($db, $log),
);
$taskname = array_shift($params);
if (!isset($arTasks[$taskname])) {
    echo "Unknown task name: $taskname\n";
    echo "Available tasks:\n";
    echo " " . implode(', ', array_keys($arTasks)) . "\n";
    exit(1);
}

$task = $arTasks[$taskname];
$res = call_user_func_array(
    array($task, 'run'),
    $params
);
if (is_string($res) || is_numeric($res)) {
    echo $res . "\n";
} else {
    var_export($res);
    echo "\n";
}
?>
