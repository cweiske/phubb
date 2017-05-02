#!/usr/bin/env php
<?php
namespace phubb;
require_once __DIR__ . '/../src/phubb/functions.php';
$log = new Logger();
$db = new Db($log);
$jobHandle = uniqid('phubb-cron-');

$nRePings = scheduleRePings($db);
//FIXME: --quiet parameter
if ($nRePings > 0) {
    $log->notice($nRePings . ' re-pings', array('job' => $jobHandle));
} else {
    $log->debug($nRePings . ' re-pings', array('job' => $jobHandle));
}

function scheduleRePings($db)
{
    global $log;

    $gmclient = new \GearmanClient();
    $gmclient->addServer('127.0.0.1');

    $res = $db->query(
        'SELECT rp_id, rp_sub_id, rp_pr_id, pr_url FROM repings'
        . ' JOIN pingrequests ON rp_pr_id = pr_id'
        . ' WHERE rp_scheduled = 0 AND rp_next_try <= NOW()'
    );
    $count = 0;
    foreach ($res as $rowRePing) {
        $gmclient->doBackground(
            'phubb_notifysubscriber',
            serialize(
                array(
                    'topicUrl' => $rowRePing->pr_url,
                    'subscriptionId' => $rowRePing->rp_sub_id,
                    'pingRequestId' => $rowRePing->rp_pr_id,
                )
            )
        );
        if ($gmclient->returnCode() == GEARMAN_SUCCESS) {
            $db->prepare(
                'UPDATE repings SET rp_scheduled = 1'
                . ' WHERE rp_id = :id'
            )->execute(array(':id' => $rowRePing->rp_id));
        } else {
            $log->warning(
                'Error queueing re-ping task',
                array(
                    'job' => $jobHandle,
                    'return_code' => $gmclient->returnCode(),
                    'topic'  => $rowRePing->pr_url,
                    'reping' => $rowRePing->rp_id
                )
            );
        }
        ++$count;
    }
    return $count;
}
?>
