<?php
namespace phubb;

class Task_CleanupPingRequest
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
        
        return $this->run($job->workload());
    }

    /**
     * Cleanup the ping request: Delete temp files, delete row from db
     *
     * @param integer $pingRequestId Unique ID for files with header and content
     *                               data
     *
     * @return boolean True when the notification has been sent, false otherwise
     */
    public function run($pingRequestId)
    {
        $this->deleteTmpFiles($pingRequestId);
        $this->deletePingRequestRow($pingRequestId);
        //TODO: log
    }

    protected function deleteTmpFiles($pingRequestId)
    {
        list($fileHeaders, $fileContent) = Helper::getTmpFilePaths(
            $pingRequestId
        );
        unlink($fileHeaders);
        unlink($fileContent);
    }

    protected function deletePingRequestRow($pingRequestId)
    {
        $this->db->prepare('DELETE FROM pingrequests WHERE pr_id = :id')
            ->execute(array(':id' => $pingRequestId));
    }
}
?>