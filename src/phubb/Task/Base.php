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
}
?>
