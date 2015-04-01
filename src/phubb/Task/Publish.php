<?php
namespace phubb;

class Task_Publish
{
    protected $db;

    /**
     * ID of the stored ping request in the database
     * @var integer
     */
    protected $nRequestId;

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
        $this->nRequestId = $this->storeRequest($url);

        list($headers, $content) = $this->checkTopicUpdate($url);
        if ($content === false) {
            return false;
        }

        $count = $this->notifySubscribers($url, $headers, $content);
        $this->updateRequestCount($count);
        return $count;
    }

    /**
     * Store the ping request in the database for status tracking.
     *
     * @param string $url Topic URL that was updated
     *
     * @return integer ID of the stored request
     */
    protected function storeRequest($url)
    {
        //TODO: what about duplicates?
        $this->db->prepare(
            'INSERT INTO pingrequests'
            . ' (pr_created, pr_updated, pr_url)'
            . ' VALUES(NOW(), NOW(), :url)'
        )->execute(array(':url' => $url));

        return $this->db->lastInsertId();
    }

    /**
     * Set the subscriber count of the current ping request
     *
     * @param integer $count Number of subscribers
     *
     * @return void
     */
    protected function updateRequestCount($count)
    {
        $this->db->prepare(
            'UPDATE pingrequests'
            . ' SET pr_subscribers = :count'
            . ', pr_updated = NOW()'
            . ' WHERE pr_id = :id'
        )->execute(array(':count' => $count, ':id' => $this->nRequestId));
    }

    /**
     * Initiate the worker jobs that notify the subscribers about
     * the topic update
     *
     * @param string $url     Topic URL
     * @param array  $headers Array of HTTP headers from fetching the URL
     * @param string $content Content
     *
     * @return integer Number of worker jobs that have been created
     *                 (Thus the number of subscribers)
     */
    protected function notifySubscribers($url, $headers, $content)
    {
        list($fileHeaders, $fileContent) = Helper::getTmpFilePaths(
            $this->nRequestId
        );

        $headers = $this->filterHeaders($headers);
        file_put_contents($fileHeaders, serialize($headers));
        file_put_contents($fileContent, $content);

        $gmclient= new \GearmanClient();
        $gmclient->addServer('127.0.0.1');

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
                        'pingRequestId' => $this->nRequestId,
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

    /**
     * Filter an array with HTTP headers so that it can be sent to a subscriber.
     *
     * @param array $headers Array of HTTP header strings
     *
     * @return array Filtered array of HTTP headers
     */
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
