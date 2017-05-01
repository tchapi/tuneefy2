<?php

namespace tuneefy\DB;

use tuneefy\Platform\PlatformResult;

class DatabaseHandler
{
    /**
     * The singleton instance of the class.
     */
    protected static $instance = null;

    private $parameters = [];
    private $connection;

    /*
      Table and columns names
    */
    const TABLE_ITEMS = 'items';
    const TABLE_ITEMS_COL_ID = 'id';
    const TABLE_ITEMS_COL_TYPE = 'type';
    const TABLE_ITEMS_COL_CREATED_AT = 'created_at';

    const TABLE_INTENTS = 'intents';
    const TABLE_INTENTS_COL_UID = 'uid';
    const TABLE_INTENTS_COL_OBJECT = 'object';
    const TABLE_INTENTS_COL_CREATED_AT = 'created_at';

    public function __construct(array $params)
    {
        $this->parameters = $params['database'];
        $this->connect();

        self::$instance = $this;
    }

    private function connect(): \PDO
    {
        if (is_null($this->connection)) {
            $this->connection = new \PDO(
                'mysql:host='.$this->parameters['server'].';dbname='.$this->parameters['name'],
                $this->parameters['user'],
                $this->parameters['password']
            );
        }

        return $this->connection;
    }

    public function getConnection(): \PDO
    {
        return $this->connection;
    }

    /**
     * Retrieves the singleton instance.
     */
    public static function getInstance(): DatabaseHandler
    {
        return self::$instance;
    }

    public function getItem(int $item_id): array
    {
        $query = sprintf('SELECT * FROM `%s` WHERE `%s` = %d',
            self::TABLE_ITEMS,
            self::TABLE_ITEMS_COL_ID,
            $item_id
        );

        $res = $this->connection->query($query);

        if ($res === false) {
            // Error
            throw new \Exception('Error getting item : '.$this->connection->error);
        }

        $rows = $res->mapRowsTyped();

        return $rows[0];
    }

    public function addItem(/* TBC */): DatabaseHandler
    {
        $res = $this->connection->query('INSERT INTO %T (%C, %C, %C) VALUES (%s, %s, NOW()) ',
        self::TABLE_ITEMS,
        /* TBC*/
        self::TABLE_ITEMS_COL_CREATED_AT);

        return $this;
    }

    public function addIntent(string $uid, PlatformResult $object): DatabaseHandler
    {
        $query = sprintf('INSERT INTO `%s` (`%s`, `%s`, `%s`) VALUES ("%s", "%s", NOW()) ',
          self::TABLE_INTENTS,
          self::TABLE_INTENTS_COL_UID,
          self::TABLE_INTENTS_COL_OBJECT,
          self::TABLE_INTENTS_COL_CREATED_AT,
          $this->connection->real_escape_string($uid),
          $this->connection->real_escape_string(serialize($object))
        );

        // Persist intent and object in DB for a later share if necessary
        $res = $this->connection->query($query);

        if ($res === false || $this->connection->affected_rows !== 1) {
            // Error
            throw new \Exception('Error adding intent : '.$this->connection->error);
        }

        return $this;
    }
}
