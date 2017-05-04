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
        $this->parameters = $params;
        $this->connect();

        self::$instance = $this;
    }

    private function connect(): \PDO
    {
        if (is_null($this->connection)) {
            $this->connection = new \PDO(
                'mysql:host='.$this->parameters['database']['server'].';dbname='.$this->parameters['database']['name'],
                $this->parameters['database']['user'],
                $this->parameters['database']['password']
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

    public function getItemById(int $id): array
    {
        $statement = $this->connection->prepare('SELECT * FROM `items` WHERE `id` = :id AND `expires_at` IS NULL AND `intent` IS NULL');

        $res = $statement->execute([
          ':id' => $id,
        ]);

        if ($res === false) {
            throw new \Exception('Error getting item : '.$statement->errorInfo()[2]);
        }

        return $statement->fetch(\PDO::FETCH_ASSOC);
    }

    public function fixItemWithIntent(string $intent): string
    {
        /*
            To make an item permanent, we remove its intent, and remove its expiration date.
            The item thus becomes available as a conventional tuneefy link with its id and
            cannot be shared as an intent anymore.
        */

        $statementSelect = $this->connection->prepare('SELECT `id` FROM `items` WHERE `intent` = :intent');
        $statementUpdate = $this->connection->prepare('UPDATE `items` SET `expires_at` = NULL, `intent` = NULL WHERE `intent` = :intent');

        $this->connection->beginTransaction();
        $resSelect = $statementSelect->execute([':intent' => $intent]);
        $resUpdate = $statementUpdate->execute([':intent' => $intent]);
        $res = $this->connection->commit();

        if ($res === false || $resSelect === false || $resUpdate === false) {
            throw new \Exception('Error making intent : '.$intent.' permanent '.$statementSelect->errorInfo()[2].' '.$statementUpdate->errorInfo()[2]);
        }

        $row = $statementSelect->fetch(\PDO::FETCH_ASSOC);

        if ($row == null) {
            throw new \Exception('No intent with the requested uid : '.$intent);
        }

        return Utils::toUId($row['id']);
    }

    public function addItemWithIntent(string $intent, PlatformResult $object): DatabaseHandler
    {
        $statement = $this->connection->prepare('INSERT INTO `items` (`intent`, `object`, `created_at`, `expires_at`, `signature`) VALUES (:intent, :object, NOW(), :expires, :signature)');

        // Persist intent and object in DB for a later share if necessary
        $objectAsString = serialize($object);
        $expires = new \DateTime('now');
        $expires->add(new \DateInterval('PT'.$this->parameters['intents']['lifetime'].'S'));
        $res = $statement->execute([
          ':intent' => $intent,
          ':object' => $objectAsString,
          ':expires' => $expires->format('Y-m-d H:i:s'),
          ':signature' => hash_hmac('md5', $objectAsString, $this->parameters['intents']['secret']),
        ]);

        if ($res === false) {
            throw new \Exception('Error adding intent : '.$statement->errorInfo()[2]);
        }

        return $this;
    }

    public function getItemByIntent(string $intent): PlatformResult
    {
        $statement = $this->connection->prepare('SELECT `object`, `signature` FROM `items` WHERE `intent` = :intent AND `expires_at` > NOW()');

        $res = $statement->execute([
          ':intent' => $intent,
        ]);

        if ($res === false) {
            throw new \Exception('Error getting intent : '.$statement->errorInfo()[2]);
        }

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        if ($row === false) {
            throw new \Exception('No intent with the requested uid : '.$intent.' or this intent has expired.');
        }

        if ($row['signature'] !== hash_hmac('md5', $row['object'], $this->parameters['intents']['secret'])) {
            throw new \Exception('Data for intent : '.$intent.' has been tampered with, the signature is not valid.');
        }

        $result = unserialize($row['object'], ['allowed_classes' => PlatformResult::class]);

        if ($result === false || !($result instanceof PlatformResult)) {
            throw new \Exception('Stored object is not unserializable');
        }

        return $result;
    }
}
