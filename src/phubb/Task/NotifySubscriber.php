<?php
namespace phubb;

/**
 * Notify a single subscriber about a topic update
 */
class Task_NotifySubscriber
{
    protected $db;

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    /**
     * @param \GearmanJob $job Job to execute
     *
     * @return mixed Status
     */
    public function runJob(\GearmanJob $job)
    {
        echo "Received job: " . $job->handle() . "\n";
        
        $data = unserialize($job->workload());
        extract($data);
        return $this->run($topicUrl, $subscriptionId, $pingRequestId);
    }

    /**
     * Notify a single subscriber about an update
     *
     * @param string  $topicUrl       Topic URL that was updated
     * @param integer $subscriptionId ID of subscription in database
     * @param string  $pingRequestId  Unique ID for files with header and content
     *                                data
     *
     * @return boolean True when the notification has been sent, false otherwise
     */
    public function run($topicUrl, $subscriptionId, $pingRequestId)
    {
        $stmt = $this->db->prepare('SELECT * FROM subscriptions WHERE sub_id = :id');
        $stmt->execute(array(':id' => $subscriptionId));
        $rowSubscription = $stmt->fetch();
        if ($rowSubscription === false) {
            return false;
        }
        //FIXME: check lease time

        list($fileHeaders, $fileContent) = Helper::getTmpFilePaths($pingRequestId);
        $headers = unserialize(file_get_contents($fileHeaders));
        $content = file_get_contents($fileContent);

        if ($rowSubscription->sub_secret != '') {
            $headers[] = 'X-Hub-Signature: sha1='
                . hash_hmac('sha1', $content, $rowSubscription->sub_secret);
        }

        $ctx = stream_context_create(
            array(
                'http' => array(
                    'method'  => 'POST',
                    'header'  => $headers,
                    'content' => $content,
                    'ignore_errors' => true,
                )
            )
        );

        $res = file_get_contents($rowSubscription->sub_callback, false, $ctx);
        list($http, $code, $rest) = explode(' ', $http_response_header[0]);
        if (intval($code / 100) === 2) {
            $this->storeSuccess($pingRequestId, $rowSubscription->sub_id);
            $this->checkRequestCleanup($pingRequestId);
            return true;
        } else {
            $this->storeFail($pingRequestId, $rowSubscription->sub_id);
            $hasNext = $this->scheduleRePing(
                $pingRequestId, $rowSubscription->sub_id, $http_response_header[0]
            );
            if (!$hasNext) {
                $this->cancelRePing($pingRequestId, $rowSubscription->sub_id);
            }
            $this->checkRequestCleanup($pingRequestId);
            return false;
        }
    }

    protected function storeSuccess($pingRequestId, $subscriptionId)
    {
        $this->db->prepare(
            'UPDATE pingrequests'
            . ' SET pr_ping_ok = pr_ping_ok + 1'
            . ', pr_updated = NOW()'
            . ' WHERE pr_id = :id'
        )->execute(array(':id' => $pingRequestId));

        $this->db->prepare(
            'UPDATE subscriptions'
            . ' SET sub_ping_ok = sub_ping_ok + 1'
            . ', sub_updated = NOW()'
            . ' WHERE sub_id = :id'
        )->execute(array(':id' => $subscriptionId));

        if (false !== $this->getRePing($pingRequestId, $subscriptionId)) {
            $this->db->prepare(
                'UPDATE pingrequests'
                . ' SET pr_ping_reping = pr_ping_reping - 1'
                . ', pr_updated = NOW()'
                . ' WHERE pr_id = :id'
            )->execute(array(':id' => $pingRequestId));

            $this->deleteRePing($pingRequestId, $subscriptionId);
        }
    }

    protected function storeFail($pingRequestId, $subscriptionId)
    {
        $this->db->prepare(
            'UPDATE subscriptions'
            . ' SET sub_ping_error = sub_ping_error + 1'
            . ', sub_updated = NOW()'
            . ' WHERE sub_id = :id'
        )->execute(array(':id' => $subscriptionId));
    }

    protected function checkRequestCleanup($pingRequestId)
    {
        //FIXME: this should not be done after every ping
        //we should use a real scheduler here.
        $stmt = $this->db->prepare(
            'SELECT pr_id FROM pingrequests'
            . ' WHERE pr_id = :id AND pr_subscribers = pr_ping_ok + pr_ping_error'
        );
        $stmt->execute(array(':id' => $pingRequestId));
        $res = $stmt->fetch();
        if ($res === false) {
            return;
        }

        $gmclient= new \GearmanClient();
        $gmclient->addServer('127.0.0.1');
        $gmclient->doBackground('phubb_cleanup_pingrequest', $pingRequestId);
    }

    protected function scheduleRePing($pingRequestId, $subscriptionId, $error)
    {
        $rowRePing = $this->getRePing($pingRequestId, $subscriptionId);
        if ($rowRePing === false) {
            //no, it's new
            $this->db->prepare(
                'INSERT INTO repings'
                . '(rp_pr_id, rp_sub_id, rp_created, rp_updated, rp_iteration'
                . ', rp_next_try, rp_last_error, rp_scheduled)'
                . ' VALUES(:pr, :sub, NOW(), NOW(), 1, :next, :error, 0)'
            )->execute(
                array(
                    ':pr'    => $pingRequestId,
                    ':sub'   => $subscriptionId,
                    ':next'  => $this->getNextTryTime(0),
                    ':error' => $error,
                )
            );

            //say we're re-pinging
            $this->db->prepare(
                'UPDATE pingrequests'
                . ' SET pr_ping_reping = pr_ping_reping + 1'
                . ', pr_updated = NOW()'
                . ' WHERE pr_id = :id'
            )->execute(array(':id' => $pingRequestId));
        } else {
            //update
            $nextTry = $this->getNextTryTime(
                $rowRePing->rp_iteration, strtotime($rowRePing->rp_next_try)
            );
            if ($nextTry === false) {
                //we do not try again
                return false;
            }
            $this->db->prepare(
                'UPDATE repings SET'
                . ' rp_updated = NOW()'
                . ', rp_iteration = :iteration'
                . ', rp_next_try = :next'
                . ', rp_last_error = :error'
                . ', rp_scheduled = 0'
                . ' WHERE rp_pr_id = :pr AND rp_sub_id = :sub'
            )->execute(
                array(
                    ':pr'        => $pingRequestId,
                    ':sub'       => $subscriptionId,
                    ':iteration' => $rowRePing->rp_iteration + 1,
                    ':next'      => $nextTry,
                    ':error'     => $error,
                )
            );
        }
        return true;
    }

    /**
     * Cancel repinging because we had too many failures.
     */
    protected function cancelRePing($pingRequestId, $subscriptionId)
    {
        $this->deleteRePing($pingRequestId, $subscriptionId);
        //tell that we stopped repinging
        $this->db->prepare(
            'UPDATE pingrequests'
            . ' SET pr_ping_reping = pr_ping_reping - 1'
            . ', pr_ping_error = pr_ping_error + 1'
            . ', pr_updated = NOW()'
                . ' WHERE pr_id = :id'
        )->execute(array(':id' => $pingRequestId));
    }

    /**
     * Simply delete a reping request from DB
     */
    protected function deleteRePing($pingRequestId, $subscriptionId)
    {
        $this->db->prepare(
            'DELETE FROM repings'
            . ' WHERE rp_pr_id = :pr AND rp_sub_id = :sub'
        )->execute(
            array(
                ':pr'  => $pingRequestId,
                ':sub' => $subscriptionId
            )
        );
    }

    /**
     * Load the reping request from DB.
     *
     * @return object Row object, boolean false if it does not exist
     */
    protected function getRePing($pingRequestId, $subscriptionId)
    {
        //check if we already have it
        $stmt = $this->db->prepare(
            'SELECT rp_id, rp_iteration, rp_next_try FROM repings'
            . ' WHERE rp_pr_id = :pr AND rp_sub_id = :sub'
        );
        $stmt->execute(
            array(':pr' => $pingRequestId, ':sub' => $subscriptionId)
        );
        return $stmt->fetch();
    }

    protected function getNextTryTime($iteration, $lastTime = null)
    {
        if ($lastTime === null) {
            $lastTime = time();
        } else if ($lastTime < time() - 30) {
            //maybe the worker crashed and we're behind schedule quite a bit
            $lastTime = time();
        }
        $arMinutes = array(
            0.1,
            1,
            5,
            15,
            60,
            6 * 60,
            24 * 60,
        );
        if (!isset($arMinutes[$iteration])) {
            return false;
        }

        return date('Y-m-d H:i:s', $lastTime + 60 * $arMinutes[$iteration]);
    }
}
?>
