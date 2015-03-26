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
        return $this->run($topicUrl, $subscriptionId, $fileId);
    }

    /**
     * Check if there is an update and start jobs to notify subscribers
     *
     * @param string  $topicUrl       Topic URL that was updated
     * @param integer $subscriptionId ID of subscription in database
     * @param string  $fileId         Unique ID for files with header and content
     *                                data
     *
     * @return boolean True when the notification has been sent, false otherwise
     */
    public function run($topicUrl, $subscriptionId, $fileId)
    {
        $stmt = $this->db->prepare('SELECT * FROM subscriptions WHERE sub_id = :id');
        $stmt->execute(array(':id' => $subscriptionId));
        $rowSubscription = $stmt->fetch();
        if ($rowSubscription === false) {
            return false;
        }
        //FIXME: check lease time

        list($fileHeaders, $fileContent) = $this->getTmpFilePaths($fileId);
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
            return true;
        }
        return false;
    }

    protected function getTmpFilePaths($id)
    {
        return array(
            __DIR__ . '/../../../tmp/ping-' . $id . '-headers',
            __DIR__ . '/../../../tmp/ping-' . $id . '-content'
        );
    }
}
?>
