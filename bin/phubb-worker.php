#!/usr/bin/env php
<?php
namespace phubb;
require_once __DIR__ . '/../src/phubb/functions.php';
$log = new Logger();
$db = new Db($log);

$gmworker= new \GearmanWorker();
$gmworker->addServer('127.0.0.1');

$taskCleanupPingRequest = new Task_CleanupPingRequest($db, $log);
$gmworker->addFunction(
    'phubb_cleanup_pingrequest', array($taskCleanupPingRequest, 'checkAndRunJob')
);

$taskPublish = new Task_Publish($db, $log);
$gmworker->addFunction('phubb_publish', array($taskPublish, 'checkAndRunJob'));

$taskNotifySubscriber = new Task_NotifySubscriber($db, $log);
$gmworker->addFunction(
    'phubb_notifysubscriber', array($taskNotifySubscriber, 'checkAndRunJob')
);

$taskVerify = new Task_Verify($db, $log);
$gmworker->addFunction('phubb_verify', array($taskVerify, 'checkAndRunJob'));

$log->info('Waiting for a job');
while ($gmworker->work()) {
    //$log->debug('Finished job');
    if ($gmworker->returnCode() != GEARMAN_SUCCESS) {
        $log->error(
            'Error running job',
            array('return_code' => $gmworker->returnCode())
        );
        break;
    }
}
?>
