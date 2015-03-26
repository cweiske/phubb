#!/usr/bin/env php
<?php
namespace phubb;
require_once __DIR__ . '/../src/phubb/functions.php';
$db = require __DIR__ . '/../src/phubb/db.php';

$gmworker= new \GearmanWorker();
$gmworker->addServer();

$taskPublish = new Task_Publish($db);
$gmworker->addFunction('phubb_publish', array($taskPublish, 'runJob'));

$taskNotifySubscriber = new Task_NotifySubscriber($db);
$gmworker->addFunction(
    'phubb_notifysubscriber', array($taskNotifySubscriber, 'runJob')
);

$taskVerify = new Task_Verify($db);
$gmworker->addFunction('phubb_verify', array($taskVerify, 'runJob'));

print "Waiting for job...\n";
while ($gmworker->work()) {
    echo "finished a job\n";
    if ($gmworker->returnCode() != GEARMAN_SUCCESS) {
        echo "return_code: " . $gmworker->returnCode() . "\n";
        break;
    }
}
?>
