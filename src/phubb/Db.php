<?php
namespace phubb;
use PDO;

/**
 * @method \PDOStatement prepare(string $sql)
 * @method string        lastInsertId()
 */
class Db
{
    /**
     * @var \PDO
     */
    protected $db;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $log;

    public function __construct(\Psr\Log\LoggerInterface $log)
    {
        $this->log = $log;
        $this->connect();
    }

    protected function connect(): void
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

    /**
     * @param string  $method PDO method to call
     * @param mixed[] $args
     *
     * @return mixed
     */
    public function __call(string $method, array $args)
    {
        return call_user_func_array(array($this->db, $method), $args);
    }

    public function reconnect(): void
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
