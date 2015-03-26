<?php
namespace phubb;

/**
 * Verify a subscription request
 */
class Task_Verify
{
    protected $db;

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    /**
     * @param \GearmanJob $job Job to execute
     *
     * @return FIXME
     */
    public function runJob(\GearmanJob $job)
    {
        echo "Received job: " . $job->handle() . "\n";
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
     * @return FIXME
     */
    public function runRequest(Model_SubscriptionRequest $req)
    {
        $challenge = mt_rand();
        $url = $req->callback;
        $sep = strpos($url, '?') === false ? '?' : ':';
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
                'verification response status not 200 but ' . (int) $status
            );
            return false;
        } else if ($res != $challenge) {
            //challenge does not match
            $this->failSubscription(
                'verification response does not match challenge but is '
                . gettype($res) . '(' . strlen($res) . '): '
                . '"' . str_replace("\n", '\\n', substr($res, 0, 128)) . '"'
            );
            return false;
        } else {
            //subscription validated
            $this->acceptSubscription($req);
            return true;
        }
    }

    function failSubscription($reason)
    {
        echo "fail: $reason\n";return;
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
