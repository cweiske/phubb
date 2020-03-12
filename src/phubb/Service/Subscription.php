<?php
namespace phubb;

/**
 * Manage subscriptions in database
 */
class Service_Subscription
{
    /**
     * @var Db
     */
    public $db;

    public function __construct(Db $db)
    {
        $this->db = $db;
    }

    /**
     * Create a new subscription in database.
     * Updates topic
     *
     * @param string  $callback     Notification URL
     * @param string  $topic        Feed URL
     * @param string  $secret       Secret hash
     * @param integer $leaseSeconds Subscription timeout
     *
     * @return void
     */
    public function create($callback, $topic, $secret, $leaseSeconds)
    {
        $this->db->prepare(
            'INSERT INTO subscriptions'
            . '(sub_created, sub_updated, sub_callback, sub_topic, sub_secret'
            . ', sub_lease_seconds, sub_lease_end)'
            . ' VALUES(NOW(), NOW(), :callback, :topic, :secret'
            . ', :leaseSeconds, :leaseEnd)'
        )->execute(
            array(
                ':callback' => $callback,
                ':topic'    => $topic,
                ':secret'   => $secret,
                ':leaseSeconds' => $leaseSeconds,
                ':leaseEnd' => date(
                    'Y-m-d H:i:s', time() + $leaseSeconds
                )
            )
        );
        $this->db->prepare(
            'UPDATE topics SET t_subscriber = t_subscriber + 1'
            . ',t_updated = NOW()'
            . ' WHERE t_url = :topic'
        )->execute(array(':topic' => $topic));
    }

    /**
     * Update an existing subscription
     *
     * @param integer $sub_id       Subscription ID
     * @param string  $secret       Secret hash
     * @param integer $leaseSeconds Subscription timeout
     *
     * @return void
     */
    public function update($sub_id, $secret, $leaseSeconds)
    {
        $this->db->prepare(
            'UPDATE subscriptions SET'
            . ' sub_updated = NOW()'
            . ', sub_secret = :secret'
            . ', sub_lease_seconds = :leaseSeconds'
            . ', sub_lease_end = :leaseEnd'
            . ' WHERE sub_id = :id'
        )->execute(
            array(
                ':secret'       => $secret,
                ':leaseSeconds' => $leaseSeconds,
                ':leaseEnd'     => date(
                    'Y-m-d H:i:s', time() + $leaseSeconds
                ),
                ':id'           => $sub_id
            )
        );
    }

    /**
     * Delete subscription from database
     *
     * @param integer $sub_id   Subscription ID
     * @param string  $callback Notification callback
     * @param string  $topic    Feed
     *
     * @return void
     */
    public function delete($sub_id, $callback, $topic)
    {
        //repings
        if ($sub_id !== false) {
            $this->db->prepare(
                'DELETE FROM repings WHERE rp_sub_id = :sub_id'
            )->execute([':sub_id' => $sub_id]);
        }

        //subscription itself
        $this->db->prepare(
            'DELETE FROM subscriptions'
            . ' WHERE sub_callback = :callback AND sub_topic = :topic'
        )->execute(
            array(
                ':callback' => $callback,
                ':topic'    => $topic
            )
        );
        $this->db->prepare(
            'UPDATE topics SET t_subscriber = t_subscriber - 1'
            . ',t_updated = NOW()'
            . ' WHERE t_url = :topic'
        )->execute(array(':topic' => $topic));
    }
}
?>
