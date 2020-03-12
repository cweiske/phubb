<?php
namespace phubb;

class Config
{
    /**
     * @var string
     */
    public $dbHost;

    /**
     * @var string
     */
    public $dbName;

    /**
     * @var string
     */
    public $dbUser;

    /**
     * @var string
     */
    public $dbPass;

    /**
     * Options: debug, info, notice, warning, error
     *
     * @var string
     */
    public $logLevel;

    /**
     * @var string
     */
    public $logFile;

    /**
     * @var string
     */
    public $baseUrl;

    /**
     * List of domains
     *
     * @var string[]
     */
    public $topicBlacklist = [];

    /**
     * Enable development test scripts
     *
     * @var bool
     */
    public $devMode;

    public static function load(): Config
    {
        require __DIR__ . '/../../data/phubb.config.php';
        $vars = get_defined_vars();

        return unserialize(
            preg_replace(
                '#^O:8:"stdClass":#',
                'O:12:"phubb\Config":',
                serialize((object) $vars)
            )
        );
    }
}
