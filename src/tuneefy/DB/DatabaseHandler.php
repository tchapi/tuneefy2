<?php

namespace tuneefy\DB;

use Symfony\Component\Yaml\Yaml;
use tuneefy\Application;
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

    /**
     * Protected constructor to ensure there are no instantiations.
     */
    protected function __construct()
    {
        try {
            $params = Yaml::parse(file_get_contents(Application::getPath('parameters')));
        } catch (\Exception $e) {
            // TODO  : translate
            throw new \Exception('No config file found');
        }

        if ($params['database'] === null) {
            // TODO  : translate
            throw new \Exception('No DB parameters');
        }

        $this->parameters = $params['database'];
        $this->connect();
    }

    private function connect(): \mysqli
    {
        $this->connection = new \mysqli(
            $this->parameters['server'],
            $this->parameters['user'],
            $this->parameters['password'],
            $this->parameters['name']
        );

        return $this->connection;
    }

    /**
     * Retrieves the singleton instance.
     */
    public static function getInstance(): DatabaseHandler
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

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
