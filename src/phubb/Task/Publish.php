<?php
namespace phubb;

class Task_Publish
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
        $url = $job->workload();
        return $this->run($url);
    }

    /**
     * Check if there is an update and start jobs to notify subscribers
     *
     * @param string $url Topic URL that was updated
     *
     * @return mixed False if there was no update, subscriber count otherwise
     */
    public function run($url)
    {
        list($headers, $content) = $this->checkTopicUpdate($url);
        if ($content === false) {
            return false;
        }

        $count = $this->notifySubscribers($url, $headers, $content);
        return $count;
    }

    function notifySubscribers($url, $headers, $content)
    {
        $id = uniqid();
        $fileHeaders = __DIR__ . '/../../../tmp/ping-' . $id . '-headers';
        $fileContent = __DIR__ . '/../../../tmp/ping-' . $id . '-content';
        $headers = $this->filterHeaders($headers);
        file_put_contents($fileHeaders, serialize($headers));
        file_put_contents($fileContent, $content);
        
        $gmclient= new \GearmanClient();
        $gmclient->addServer();

        $stmt = $this->db->prepare(
            'SELECT * FROM subscriptions WHERE sub_topic = :topic'
        );
        $stmt->execute(array(':topic' => $url));
        $count = 0;
        foreach ($stmt as $rowSubscription) {
            $job_handle = $gmclient->doBackground(
                'phubb_notifysubscriber',
                serialize(
                    array(
                        'topicUrl' => $url,
                        'subscriptionId' => $rowSubscription->sub_id,
                        'fileId' => $id,
                    )
                )
            );
            if ($gmclient->returnCode() != GEARMAN_SUCCESS) {
                echo "bad return code\n";            
            }
            ++$count;
        }
        return $count;
    }

    protected function filterHeaders($headers)
    {
        $headersToSend  = array();
        $allowedHeaders = array_flip(
            array(
                'content-length',
                'content-type',
                'etag',
                'last-modified',
                'link',
                'x-pingback',
            )
        );

        //drop "HTTP/1.0 ..."
        array_shift($headers);

        foreach ($headers as $header) {
            list($name, $value) = explode(':', $header, 2);
            $name = strtolower($name);
            if (isset($allowedHeaders[$name])) {
                $headersToSend[] = $header;
            }
        }
        return $headersToSend;
    }

    function checkTopicUpdate($url)
    {
        $rowTopic = $this->fetchOrCreateTopicRow($url);
        list($headers, $content) = $this->fetchTopic($rowTopic);
        if ($content === false) {
            //TODO: try again later
            return;
        }

        //TODO: extract "self" url
        //TODO: check if modified
        return array($headers, $content);
    }

    function fetchTopic($rowTopic)
    {
        $ctx = stream_context_create(
            array(
                'http' => array(
                    'header' => array(
                    //FIXME: if-modified-since
                    //'Content-type: application/x-www-form-urlencoded',
                    )
                )
            )
        );

        $content = file_get_contents($rowTopic->t_url, false, $ctx);
        list($http, $code, $rest) = explode(' ', $http_response_header[0]);
        if (intval($code / 100) === 2) {
            return array($http_response_header, $content);
        }
        return array(false, false);
    }

    function fetchOrCreateTopicRow($url)
    {
        $stmt = $this->db->prepare('SELECT * FROM topics WHERE t_url = :url');
        $stmt->execute(array(':url' => $url));
        $rowTopic = $stmt->fetch();
        if ($rowTopic === false) {
            //TODO: insert
            //FIXME: fetch URL and check if self matches
            $this->db->prepare(
                'INSERT INTO topics'
                . '(t_url, t_change_date, t_content_md5)'
                . ' VALUES(:url, "1970-01-01 00:00:00", "")'
            )->execute(array(':url' => $url));
            
            $stmt = $this->db->prepare('SELECT * FROM topics WHERE t_url = :url');
            $stmt->execute(array(':url' => $url));
            $rowTopic = $stmt->fetch();
        }
        return $rowTopic;
    }
}
?>
