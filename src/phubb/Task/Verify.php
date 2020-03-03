<?php
namespace phubb;

/**
 * Verify a subscription request
 */
class Task_Verify extends Task_Base
{
    /**
     * @param \GearmanJob $job Job to execute
     *
     * @return FIXME
     */
    public function runJob(\GearmanJob $job)
    {
        $this->log->debug('Received job', array('job' => $this->jobHandle));
        $req = unserialize($job->workload());
        return $this->runRequest($req);
    }

    /**
     * Used by task tester
     */
    public function run($callback, $topic, $mode, $leaseSeconds, $secret)
    {
        $req = new Model_SubscriptionRequest();
        $req->callback     = $callback;
        $req->topic        = $topic;
        $req->mode         = $mode;
        $req->leaseSeconds = $leaseSeconds;
        $req->secret       = $secret;
        return $this->runRequest($req);
    }

    /**
     * Check if there is an update and start jobs to notify subscribers
     *
     * @param string $url Topic URL that was updated
     *
     * @return boolean True if all went well, false if not
     */
    public function runRequest(Model_SubscriptionRequest $req)
    {
        $this->log->info(
            'Verifying subscription',
            array_merge((array) $req, array('job' => $this->jobHandle))
        );

        if (!$this->verifyTopic($req->topic)) {
            return false;
        }
        return $this->verifySubscriber($req);
        //TODO: store topic URL if it does not exist
    }

    /**
     * Check that the topic URL exists and that it propagates this hub.
     *
     * @param string $topicUrl URL the subscriber wants to subscribe to
     *
     * @return boolean True if the topic is valid, false if we cannot
     *                 find it or accept it
     */
    protected function verifyTopic($topicUrl)
    {
        //TODO: check if topic exists
        //TODO: check if topic proposes this hub in the link header
        return true;
    }

    /**
     * Verify that the subscriber really wanted to subscribe
     *
     * @return boolean True if all went well, false if not
     */
    protected function verifySubscriber(Model_SubscriptionRequest $req)
    {
        $req->leaseSeconds = max($req->leaseSeconds, 86400 * 7);
        $req->leaseSeconds = min($req->leaseSeconds, 86400 * 365);

        $challenge = mt_rand();
        $url = $req->callback;
        $sep = strpos($url, '?') === false ? '?' : '&';
        $url .= $sep . 'hub.mode=' . urlencode($req->mode)
            . '&hub.topic=' . urlencode($req->topic)
            . '&hub.challenge=' . urlencode($challenge)
            . '&hub.lease_seconds=' . urlencode($req->leaseSeconds);
        //echo $url . "\n";

        $ctx = stream_context_create(
            [
                'http' => [
                    'ignore_errors' => true,
                    'timeout'       => 10,//this is also a connect timeout
                ]
            ]
        );
        $res = file_get_contents($url, false, $ctx);
        if ($res === false && !isset($http_response_header)) {
            $this->failSubscription(
                'verification request failed',
                $req
            );
            return false;
        }
        list(, $status, ) = explode(' ', $http_response_header[0]);
        if ($status != 200) {
            //subscription error
            $this->failSubscription(
                'verification response status not 200 but ' . (int) $status,
                $req
            );
            return false;
        } else if ($res != $challenge) {
            //challenge does not match
            $this->failSubscription(
                'verification response does not match challenge but is '
                . gettype($res) . '(' . strlen($res) . '): '
                . '"' . str_replace("\n", '\\n', substr($res, 0, 128)) . '"',
                $req
            );
            return false;
        } else {
            //subscription validated
            $this->acceptSubscription($req);
            $this->log->info(
                'Subscription accepted', array('job' => $this->jobHandle)
            );
            return true;
        }
    }

    function failSubscription($reason, Model_SubscriptionRequest $req)
    {
        //TODO: send fail message to subscriber
        $data = (array) $req;
        $data['reason'] = $reason;
        $data['job']    = $this->jobHandle;
        $this->log->notice('Verification failed', $data);
    }

    function acceptSubscription(Model_SubscriptionRequest $req)
    {
        $stmt = $this->db->prepare(
            'SELECT sub_id FROM subscriptions'
            . ' WHERE sub_callback = :callback AND sub_topic = :topic'
        );
        $stmt->execute(
            array(
                ':callback' => $req->callback,
                ':topic'    => $req->topic
            )
        );
        $rowSub = $stmt->fetch();

        $subs = new Service_Subscription($this->db);
        if ($req->mode == 'unsubscribe') {
            $subs->delete(
                $rowSub !== false ? $rowSub->sub_id : null,
                $req->callback,
                $req->topic
            );
            return;
        }
    
        if ($rowSub === false) {
            //new subscription
            $subs->create(
                $req->callback,
                $req->topic,
                $req->secret,
                $req->leaseSeconds
            );
        } else {
            //existing subscription
            $subs->update($rowSub->sub_id, $req->secret, $req->leaseSeconds);
        }
    }

}
?>
