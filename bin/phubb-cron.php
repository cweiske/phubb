#!/usr/bin/env php
<?php
namespace phubb;
/**
 * Tasks that need to be run regularly
 *
 * - re-ping subscribers if they failed to receive an update the last time
 * - delete outdated subscriptions
 */
require_once __DIR__ . '/../vendor/autoload.php';
$log = new Logger();
$db = new Db($log);
$jobHandle = uniqid('phubb-cron-');

deleteOutdatedSubscriptions($db);

$nRePings = scheduleRePings($db);
//FIXME: --quiet parameter
if ($nRePings > 0) {
    $log->notice($nRePings . ' re-pings', array('job' => $jobHandle));
} else {
    $log->debug($nRePings . ' re-pings', array('job' => $jobHandle));
}



function deleteOutdatedSubscriptions($db)
{
    $db->query('DELETE FROM subscriptions WHERE sub_lease_end < NOW()');
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
