<?php
namespace phubb;

/**
 * Notify all subscribers that a topic URL changed
 */
class Task_Publish extends Task_Base
{
    /**
     * ID of the stored ping request in the database
     * @var integer
     */
    protected $nRequestId;

    /**
     * @param \GearmanJob $job Job to execute
     *
     * @return mixed Status
     */
    public function runJob(\GearmanJob $job)
    {
        $this->log->debug('Received job', array('job' => $this->jobHandle));
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
        $this->log->info(
            'Starting job: publish',
            array('topic' => $url, 'job' => $this->jobHandle)
        );

        if (strpos($url, '*') === false) {
            $count = $this->publishSingleUrl($url);
        } else {
            //there is a wildcard. check all topics people are subscribed to
            $urls = $this->expandWildcard($url);
            $this->log->info(
                'Publishing wildcard URL',
                array(
                    'topic' => $url, 'url_count' => count($urls),
                    'job' => $this->jobHandle
                )
            );
            $count = 0;
            foreach ($urls as $singleUrl) {
                $count += $this->publishSingleUrl($singleUrl);
            }
        }

        $this->log->info(
            'Finished job: publish',
            array('topic' => $url, 'count' => $count, 'job' => $this->jobHandle)
        );
        return $count;
    }

    /**
     * Starts publish job for a single URL
     *
     * @param string $url Topic URL to send updates for
     *
     * @return int|false Number of started notification jobs, false on error
     */
    protected function publishSingleUrl($url)
    {
        $this->nRequestId = $this->storeRequest($url);

        list($rowTopic, $headers, $content) = $this->checkTopicUpdate($url);
        if ($content === false) {
            //an error occured fetching the data
            $this->log->notice(
                'Error in publish job: Error fetching data',
                array('topic' => $url, 'job' => $this->jobHandle)
            );
            return false;
        } else if ($content === true) {
            //content did not change; no need to send notifications
            $this->log->info(
                'Finishing publish job: Content did not change',
                array('topic' => $url, 'job' => $this->jobHandle)
            );
            return false;
        }

        $count = $this->notifySubscribers($url, $headers, $content);
        $this->updateRequestCount($count);
        $this->updateTopicStatus($rowTopic->t_id, $headers, $content);

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

        return (int) $this->db->lastInsertId();
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
     * @param string $content Content of URL to publish
     *
     * @return integer Number of worker jobs that have been created
     *                 (Thus the number of subscribers)
     */
    protected function notifySubscribers($url, $headers, $content)
    {
        list($fileHeaders, $fileContent) = Helper::getTmpFilePaths(
            $this->nRequestId
        );

        $headers = $this->filterHeaders($headers, strlen($content));
        file_put_contents($fileHeaders, serialize($headers));
        file_put_contents($fileContent, $content);

        $gmclient= new \GearmanClient();
        $gmclient->addServer('127.0.0.1');

        $stmt = $this->db->prepare(
            'SELECT * FROM subscriptions WHERE sub_topic = :topic'
            . ' AND sub_lease_end >= NOW()'
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
                $this->log->warning(
                    'Error queuing task to notify subscriber',
                    array(
                        'return_code' => $gmclient->returnCode(),
                        'job' => $this->jobHandle
                    )
                );
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
    protected function filterHeaders($headers, $contentLength)
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

        $hasType = false;
        $hasLength = false;
        foreach ($this->parseHeaders($headers) as $name => $values) {
            if (!isset($allowedHeaders[$name])) {
                continue;
            }
            foreach ($values as $value) {
                $headersToSend[] = $name . ': ' . $value;
                if ($name == 'content-length') {
                    $hasLength = true;
                } else if ($name == 'content-type') {
                    $hasType = true;
                }
            }
        }

        if (!$hasType) {
            //we do not know it
            $headersToSend[] = 'content-type: application/octet-stream';
        }
        if (!$hasLength) {
            $headersToSend[] = 'content-length: ' . $contentLength;
        }

        return $headersToSend;
    }

    /**
     * Parse an array of header lines
     *
     * @param array $arHeaderLines "Foo: bar\nBaz: Bat\n..."
     *
     * @return array Key is the lowercased header name.
     *               Value is an array of values, because
     *               headers may appear multiple times
     */
    protected function parseHeaders($arHeaderLines)
    {
        if (substr($arHeaderLines[0], 0, 5) == 'HTTP/') {
            //drop "HTTP/1.0 ..."
            array_shift($arHeaderLines);
        }

        $arHeaders = array();
        foreach ($arHeaderLines as $header) {
            list($name, $value) = explode(':', $header, 2);
            $name = strtolower($name);
            $arHeaders[$name][] = trim($value);
        }
        return $arHeaders;
    }

    function checkTopicUpdate($url)
    {
        $rowTopic = $this->fetchOrCreateTopicRow($url);
        list($headers, $content) = $this->fetchTopic($rowTopic);

        if ($content === false) {
            //TODO: try again later
            return array($rowTopic, false, false);
        } else if ($content === true) {
            //content did not change
            return array($rowTopic, true, true);
        }

        //TODO: extract "self" url
        return array($rowTopic, $headers, $content);
    }

    /**
     * Fetch a topic URL
     *
     * @return array key 0: HTTP response header array
     *                      or FALSE if an error occured
     *                      or TRUE if the content did not change
     *               key 1: HTTP body content
     */
    function fetchTopic($rowTopic)
    {
        $header = [
            'User-Agent: phubb/bot',
        ];
        if (strtotime($rowTopic->t_change_date) != 0) {
            $header[] = 'If-Modified-Since: '
                . date('r', strtotime($rowTopic->t_change_date));
        }
        if ($rowTopic->t_etag != '') {
            $header[] = 'If-None-Match: "' . $rowTopic->t_etag . '"';
        }
        $ctx = stream_context_create(
            array(
                'http' => array(
                    'ignore_errors' => true,
                    'timeout'       => 10,//this is also a connect timeout
                    'header' => $header
                )
            )
        );

        $content = file_get_contents($rowTopic->t_url, false, $ctx);
        list($http, $code, $rest) = explode(' ', $http_response_header[0]);
        if ($code == 304) {
            //304 Not Modified
            return array(true, true);
        } else if (intval($code / 100) !== 2) {
            return array(false, false);
        }

        $contentHash = md5($content);
        if ($contentHash == $rowTopic->t_content_md5) {
            //content did not change
            return array(true, true);
        }

        return array($http_response_header, $content);
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
                . '(t_url, t_updated, t_change_date, t_content_md5, t_etag)'
                . ' VALUES(:url, NOW(), "1970-01-01 00:00:00", "", "")'
            )->execute(array(':url' => $url));

            $stmt = $this->db->prepare('SELECT * FROM topics WHERE t_url = :url');
            $stmt->execute(array(':url' => $url));
            $rowTopic = $stmt->fetch();
        }
        return $rowTopic;
    }

    protected function updateTopicStatus($topicId, $headers, $content)
    {
        $arHeaders = $this->parseHeaders($headers);

        $lastChangeDate = gmdate('Y-m-d H:i:s');
        if (isset($arHeaders['last-modified'][0])) {
            $lastChangeDate = gmdate(
                'Y-m-d H:i:s', strtotime($arHeaders['last-modified'][0])
            );
        }

        $etag = '';
        if (isset($arHeaders['etag'][0])) {
            $etag = trim($arHeaders['etag'][0], '"');
        }

        $this->db->prepare(
            'UPDATE topics'
            . ' SET t_change_date = :date'
            . ',t_content_md5 = :md5'
            . ',t_etag = :etag'
            . ',t_updated = NOW()'
            . ' WHERE t_id = :id'
        )->execute(
            array(
                ':date' => $lastChangeDate,
                ':md5'  => md5($content),
                ':etag' => $etag,
                ':id'   => $topicId,
            )
        );
    }

    /**
     * Fetches all "real" URLs from a wildcard URL.
     * "*" is supported.
     *
     * @param string $url Wildcard URL to expand
     *
     * @return string[] URLs that match the pattern.
     */
    protected function expandWildcard(string $url)
    {
        if ($url == '*') {
            return array();
        }
        $host = parse_url($url, PHP_URL_HOST);
        if (strpos($host, '*') !== false) {
            //no wildcards in domains allowed
            return array();
        }

        $stmt = $this->db->prepare(
            'SELECT DISTINCT sub_topic FROM subscriptions'
            . ' WHERE sub_topic LIKE :topic'
            . ' AND sub_lease_end >= NOW()'
        );
        $stmt->execute(array(':topic' => str_replace('*', '%', $url)));
        $urls = array();
        foreach ($stmt as $rowSubscription) {
            $urls[] = $rowSubscription->sub_topic;
        }
        return $urls;
    }
}
?>
