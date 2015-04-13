<?php
namespace phubb;

class Task_CleanupPingRequest extends Task_Base
{
    /**
     * @param \GearmanJob $job Job to execute
     *
     * @return mixed Status
     */
    public function runJob(\GearmanJob $job)
    {
        $this->log->debug('Received job', array('job' => $this->jobHandle));

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
        $this->log->info(
            'Starting job: cleanup ping request',
            array('pr_id' => $pingRequestId, 'job' => $this->jobHandle)
        );

        $this->deleteTmpFiles($pingRequestId);
        $this->deletePingRequestRow($pingRequestId);

        $this->log->info(
            'Finished job: cleanup ping request',
            array('pr_id' => $pingRequestId, 'job' => $this->jobHandle)
        );
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