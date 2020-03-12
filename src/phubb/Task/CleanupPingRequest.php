<?php
namespace phubb;

class Task_CleanupPingRequest extends Task_Base
{
    /**
     * @param \GearmanJob $job Job to execute
     *
     * @return void
     */
    public function runJob(\GearmanJob $job): void
    {
        $this->log->debug('Received job', array('job' => $this->jobHandle));

        $this->run((int)$job->workload());
    }

    /**
     * Cleanup the ping request: Delete temp files, delete row from db
     *
     * @param integer $pingRequestId Unique ID for files with header and content
     *                               data
     *
     * @return void
     */
    public function run(int $pingRequestId): void
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

    protected function deleteTmpFiles(int $pingRequestId): void
    {
        list($fileHeaders, $fileContent) = Helper::getTmpFilePaths(
            $pingRequestId
        );
        unlink($fileHeaders);
        unlink($fileContent);
    }

    protected function deletePingRequestRow(int $pingRequestId): void
    {
        $this->db->prepare('DELETE FROM pingrequests WHERE pr_id = :id')
            ->execute(array(':id' => $pingRequestId));
    }
}
?>
