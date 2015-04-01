<?php
namespace phubb;

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
     * Check if there is an update and start jobs to notify subscribers
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
        }

        $this->storeError($pingRequestId, $rowSubscription->sub_id);
        //FIXME: schedule for re-pinging
        return false;
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
    }

    protected function storeError($pingRequestId, $subscriptionId)
    {
        $this->db->prepare(
            'UPDATE pingrequests'
            . ' SET pr_ping_error = pr_ping_error + 1'
            . ', pr_updated = NOW()'
            . ' WHERE pr_id = :id'
        )->execute(array(':id' => $pingRequestId));

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
            . ' WHERE pr_id = :id AND pr_subscribers = pr_ping_ok'
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
}
?>
