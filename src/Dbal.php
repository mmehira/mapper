<?php

namespace SimpleMapper;

use Symfony\Component\Serializer\SerializerInterface;

class Dbal
{

    const TRANSACTION_ISOLATION_LEVEL_SERIALIZABLE = 'ISOLATION LEVEL SERIALIZABLE';
    const TRANSACTION_ISOLATION_LEVEL_REPEATABLE_READ = 'ISOLATION LEVEL REPEATABLE READ';
    const TRANSACTION_ISOLATION_LEVEL_READ_COMMITTED = 'ISOLATION LEVEL READ COMMITTED';
    const TRANSACTION_ISOLATION_LEVEL_READ_UNCOMMITTED = 'ISOLATION LEVEL READ UNCOMMITTED';
    const TRANSACTION_READ_WRITE = 'READ WRITE';
    const TRANSACTION_READ_ONLY = 'READ ONLY';
    const TRANSACTION_DEFERRABLE = 'DEFERRABLE';
    const TRANSACTION_NOT_DEFERRABLE = 'NOT DEFERRABLE';

    private const AVAILABLE_TRANSACTION_MODES = [
        self::TRANSACTION_ISOLATION_LEVEL_SERIALIZABLE,
        self::TRANSACTION_ISOLATION_LEVEL_REPEATABLE_READ,
        self::TRANSACTION_ISOLATION_LEVEL_READ_COMMITTED,
        self::TRANSACTION_ISOLATION_LEVEL_READ_UNCOMMITTED,
        self::TRANSACTION_READ_WRITE,
        self::TRANSACTION_READ_ONLY,
        self::TRANSACTION_DEFERRABLE,
        self::TRANSACTION_NOT_DEFERRABLE,
    ];

    private $transactionStarted = false;
    private $savePoints = [];
    /** @var \PDO */
    private $connection;
    private $serializer;

    public function __construct(SerializerInterface $serializer, string $dsn, array $options = [])
    {
        $this->connection = new \PDO($dsn, null, null, $options);
        $this->serializer = $serializer;
    }

    public function prepare($statement, $options = NULL)
    {
        $sql = trim($statement, " \t\n\r\0\x0B\;");

        $statement = new Statement($this, $sql, $options, $this->serializer);

        return $statement;
    }

    public function beginTransaction($mode = null)
    {
        //TODO Check if transaction already started
        //TODO Check modes
        $this->connection->exec("BEGIN $mode;");
        $this->transactionStarted = true;
    }

    public function addSavePoint(string $name)
    {
        //TODO Check if transaction already started
        //TODO check on list of savepoints
        //TODO check savepoints format

        $this->connection->exec("SAVEPOINT $name;");
        $this->savePoints[] = $name;
    }

    public function commit()
    {
        //TODO Check if transaction already started
        $this->connection->exec("COMMIT;");
        $this->transactionStarted = false;
    }

    public function rollBack($savePointName = null)
    {
        //TODO Check if transaction already started
        //TODO Check if savepoint exists on savepoint list

        if(!$savePointName) {
            $this->connection->exec("ROLLBACK;");
            $this->transactionStarted = false;
        } else {
            $this->connection->exec("ROLLBACK TO SAVEPOINT $savePointName;");
        }
    }

    /**
     * @return \PDO
     */
    public function getConnection(): \PDO
    {
        return $this->connection;
    }
}