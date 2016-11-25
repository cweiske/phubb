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

        $res = file_get_contents($url);
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
        if ($req->mode == 'unsubscribe') {
            $this->db->prepare(
                'DELETE FROM subscriptions'
                . ' WHERE sub_callback = :callback AND sub_topic = :topic'
            )->execute(
                array(
                    ':callback' => $req->callback,
                    ':topic'    => $req->topic
                )
            );
            $this->db->prepare(
                'UPDATE topics SET t_subscriber = t_subscriber - 1'
                . ',t_updated = NOW()'
                . ' WHERE t_url = :topic'
            )->execute(array(':topic' => $req->topic));
            return;
        }
    
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
        if ($rowSub === false) {
            //new subscription
            $this->db->prepare(
                'INSERT INTO subscriptions'
                . '(sub_created, sub_updated, sub_callback, sub_topic, sub_secret'
                . ', sub_lease_seconds, sub_lease_end)'
                . ' VALUES(NOW(), NOW(), :callback, :topic, :secret'
                . ', :leaseSeconds, :leaseEnd)'
            )->execute(
                array(
                    ':callback' => $req->callback,
                    ':topic'    => $req->topic,
                    ':secret'   => $req->secret,
                    ':leaseSeconds' => $req->leaseSeconds,
                    ':leaseEnd' => date(
                        'Y-m-d H:i:s', time() + $req->leaseSeconds
                    )
                )
            );
            $this->db->prepare(
                'UPDATE topics SET t_subscriber = t_subscriber + 1'
                . ',t_updated = NOW()'
                . ' WHERE t_url = :topic'
            )->execute(array(':topic' => $req->topic));
            return;
        }

        //existing subscription
        $this->db->prepare(
            'UPDATE subscriptions SET'
            . ' sub_updated = NOW()'
            . ', sub_secret = :secret'
            . ', sub_lease_seconds = :leaseSeconds'
            . ', sub_lease_end = :leaseEnd'
            . ' WHERE sub_id = :id'
        )->execute(
            array(
                ':secret'       => $req->secret,
                ':leaseSeconds' => $req->leaseSeconds,
                ':leaseEnd'     => date(
                    'Y-m-d H:i:s', time() + $req->leaseSeconds
                ),
                ':id'           => $rowSub->sub_id
            )
        );
    }

}
?>
