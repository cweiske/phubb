<?php
namespace phubb;

abstract class Task_Base
{
    /**
     * @var Db
     */
    protected $db;

    /**
     * @var Logger
     */
    protected $log;

    /**
     * @var string
     */
    public $jobHandle;

    /**
     * HTTP request object that's used to do the requests
     *
     * @var \HTTP_Request2
     */
    protected $request;

    public function __construct(Db $db, Logger $log)
    {
        $this->db = $db;
        $this->log = $log;
    }

    /**
     * @param \GearmanJob $job Job to execute
     *
     * @return mixed Status
     */
    public function checkAndRunJob(\GearmanJob $job)
    {
        $this->jobHandle = $job->handle();
        $this->db->reconnect();
        return $this->runJob($job);
    }

    /**
     * @param \GearmanJob $job Job to execute
     *
     * @return mixed Status
     */
    abstract public function runJob(\GearmanJob $job);

    /**
     * Returns the HTTP request object clone that can be used
     * for one HTTP request.
     *
     * @return \HTTP_Request2 Clone of the setRequest() object
     */
    public function getRequest(string $url, string $method = 'GET')
    {
        if ($this->request === null) {
            $request = new HttpRequest();
            $this->setRequestTemplate($request);
        }

        //we need to clone because previous requests could have
        //set internal variables like POST data that we don't want now
        $req = clone $this->request;
        $req->setUrl($url);
        $req->setMethod($method);
        return $req;
    }

    /**
     * Sets a custom HTTP request object that will be used to do HTTP requests
     *
     * @param \HTTP_Request2 $request Request object
     *
     * @return self
     */
    public function setRequestTemplate(\HTTP_Request2 $request)
    {
        $this->request = $request;
        return $this;
    }
}
?>
