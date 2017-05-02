<?php

namespace tuneefy\DB;

use tuneefy\Platform\PlatformResult;
use tuneefy\Utils\Utils;

class DatabaseHandler
{
    /**
     * The singleton instance of the class.
     */
    protected static $instance = null;

    private $parameters = [];
    private $connection;

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
        $statement = $this->connection->prepare('SELECT * FROM `items` WHERE `id` = :id');

        $res = $statement->execute([
          ':id' => $item_id,
        ]);

        if ($res === false) {
            throw new \Exception('Error getting item : '.$statement->errorInfo()[2]);
        }

        return $statement->fetch(\PDO::FETCH_ASSOC);
    }

    public function addItem(PlatformResult $result): string
    {
        $statement = $this->connection->prepare('INSERT INTO `items` (`type`, `created_at`) VALUES (:type, NOW()) ');

        $res = $statement->execute([
          ':type' => $result->getMusicalEntity()->getType(),
          //':object' => serialize($object),
        ]);

        // Get last insert id
        $lastId = $this->connection->lastInsertId();

        if ($lastId == 0) {
            throw new \Exception('Error creating item : '.$statement->errorInfo()[2]);
        }

        return Utils::toUId($lastId);
    }

    public function addIntent(string $uid, PlatformResult $object): DatabaseHandler
    {
        // FIX ME expires_at et signature
        $statement = $this->connection->prepare('INSERT INTO `intents` (`uid`, `object`, `created_at`, `expires_at`) VALUES (:uid, :object, NOW(), NOW())');

        // Persist intent and object in DB for a later share if necessary
        $res = $statement->execute([
          ':uid' => $uid,
          ':object' => serialize($object),
          //':signature' => hash_hmac("md5", data, key)
        ]);

        if ($res === false) {
            throw new \Exception('Error adding intent : '.$statement->errorInfo()[2]);
        }

        return $this;
    }

    public function getIntent(string $uid): PlatformResult
    {
        $statement = $this->connection->prepare('SELECT * FROM `intents` WHERE `uid` = :uid');

        $res = $statement->execute([
          ':uid' => $uid,
        ]);

        if ($res === false) {
            throw new \Exception('Error getting intent : '.$statement->errorInfo()[2]);
        }

        $serializedObject = $statement->fetch(\PDO::FETCH_ASSOC);

        if ($serializedObject === false) {
            throw new \Exception('No intent with the requested uid : '.$uid.' or this intent has expired.');
        }

        $result = unserialize($serializedObject['object'], ['allowed_classes' => PlatformResult::class]);

        // FIX ME verify signature
        if ($result === false || !($result instanceof PlatformResult)) {
            throw new \Exception('Stored object is not unserializable');
        }

        return $result;
    }
}
