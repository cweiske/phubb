<?php
namespace phubb;

class Model_SubscriptionRequest
{
    /**
     * @var string
     */
    public $callback;

    /**
     * @var string
     */
    public $topic;

    /**
     * @var string
     */
    public $mode;

    /**
     * @var integer
     */
    public $leaseSeconds;

    /**
     * @var string
     */
    public $secret;
}
?>
