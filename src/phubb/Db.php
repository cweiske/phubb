<?php
namespace phubb;
use PDO;

class Db
{
    /**
     * @var \PDO
     */
    protected $db;

    protected $log;

    public function __construct(\Psr\Log\LoggerInterface $log)
    {
        $this->log = $log;
        $this->connect();
    }

    protected function connect()
    {
        $config = Config::load();
        $db = new PDO(
            'mysql:dbname=' . $config->dbName
            . ';host=' . $config->dbHost,
            $config->dbUser, $config->dbPass
        );
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->db = $db;
    }

    public function __call($method, $args)
    {
        return call_user_func_array(array($this->db, $method), $args);
    }

    public function reconnect()
    {
        try {
            $this->db->query('SELECT 1')->fetchAll();
        } catch (\PDOException $e) {
            list($sqlState, $errorCode, ) = $this->db->errorInfo();
            if ($errorCode === 2006) {
                $this->log->debug('Reconnecting to DB');
                $this->connect();
            } else {
                throw $e;
            }
        }
    }
}
?>
