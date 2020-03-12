<?php
namespace phubb;

class Logger extends \Psr\Log\AbstractLogger
{
    /**
     * @var \Monolog\Logger
     */
    protected $ml;

    public function __construct()
    {
        $config = Config::load();

        $this->ml = new \Monolog\Logger('phubb');
        $this->ml->pushHandler(
            new \Monolog\Handler\StreamHandler(
                $config->logFile,
                \Monolog\Logger::toMonologLevel($config->logLevel)
            )
        );
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed   $level
     * @param string  $message
     * @param mixed[] $context
     *
     * @return null
     */
    public function log($level, $message, array $context = array())
    {
        $this->ml->log($level, $message, $context);
        return null;//just for phpstan
    }
}
?>
