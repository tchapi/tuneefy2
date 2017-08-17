<?php

namespace tuneefy\DB;

use tuneefy\MusicalEntity\MusicalEntityInterface;
use tuneefy\MusicalEntity\Entities\TrackEntity;
use tuneefy\MusicalEntity\Entities\AlbumEntity;
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

    public function getItemById(int $id): MusicalEntityInterface
    {
        $statement = $this->connection->prepare('SELECT `object`, `signature` FROM `items` WHERE `id` = :id AND `expires_at` IS NULL AND `intent` IS NULL');

        $res = $statement->execute([
          ':id' => $id,
        ]);

        if ($res === false) {
            throw new \Exception('Error getting item : '.$statement->errorInfo()[2]);
        }

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        if ($row === false) {
            throw new \Exception('No item with the requested id : '.$id);
        }

        if ($row['signature'] !== hash_hmac('md5', $row['object'], $this->parameters['intents']['secret'])) {
            throw new \Exception('Data for id : '.$id.' has been tampered with, the signature is not valid.');
        }

        $result = unserialize($row['object'], ['allowed_classes' => [TrackEntity::class, AlbumEntity::class]]);

        if ($result === false || !($result instanceof MusicalEntityInterface)) {
            throw new \Exception('Stored object is not unserializable');
        }

        return $result;
    }

    public function fixItemWithIntent(string $intent): array
    {
        /*
            To make an item permanent, we remove its intent, and remove its expiration date.
            The item thus becomes available as a conventional tuneefy link with its id and
            cannot be shared as an intent anymore.
        */

        $statementSelect = $this->connection->prepare('SELECT `id`, `object`, `signature` FROM `items` WHERE `intent` = :intent');
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
            throw new \Exception('NO_OR_EXPIRED_INTENT');
        }

        if ($row['signature'] !== hash_hmac('md5', $row['object'], $this->parameters['intents']['secret'])) {
            throw new \Exception('INVALID_INTENT_SIGNATURE');
        }

        $result = unserialize($row['object'], ['allowed_classes' => [TrackEntity::class, AlbumEntity::class]]);

        if ($result === false || !($result instanceof MusicalEntityInterface)) {
            throw new \Exception('SERIALIZATION_ERROR');
        }

        return [$result->getType(), Utils::toUId($row['id'])];
    }

    public function addItemForClient(PlatformResult $result, string $client_id = null): \DateTime
    {
        $statement = $this->connection->prepare('INSERT INTO `items` (`intent`, `object`, `track`, `album`, `artist`, `created_at`, `expires_at`, `signature`, `client_id`) VALUES (:intent, :object, :track, :album, :artist, NOW(), :expires, :signature, :client_id)');

        // Persist intent and object in DB for a later share if necessary
        $entity = $result->getMusicalEntity();
        if (!$entity) {
            throw new \Exception('Error adding intent : this result does not have a musical entity bound to it.');
        }

        $entityAsString = serialize($entity);
        $expires = new \DateTime('now');
        $expires->add(new \DateInterval('PT'.$this->parameters['intents']['lifetime'].'S'));
        $res = $statement->execute([
          ':intent' => $result->getIntent(),
          ':object' => $entityAsString,
          ':track' => ($entity->getType() === 'track') ? $entity->getSafeTitle() : null,
          ':album' => ($entity->getType() === 'track') ? $entity->getAlbum()->getSafeTitle() : $entity->getSafeTitle(),
          ':artist' => $entity->getArtist(),
          ':expires' => $expires->format('Y-m-d H:i:s'),
          ':signature' => hash_hmac('md5', $entityAsString, $this->parameters['intents']['secret']),
          ':client_id' => $client_id,
        ]);

        if ($res === false) {
            throw new \Exception('Error adding intent : '.$statement->errorInfo()[2]);
        }

        return $expires;
    }

    public function addListeningStat(int $item_id, string $platformTag, int $index)
    {
        $statement = $this->connection->prepare('INSERT INTO `stats_listening` (`item_id`, `platform`, `index`, `listened_at`) VALUES (:item_id, :platform, :index, NOW())');

        $res = $statement->execute([
          ':item_id' => $item_id,
          ':platform' => $platformTag,
          ':index' => $index,
        ]);

        if ($res === false) {
            throw new \Exception('Error adding listening stat : '.$statement->errorInfo()[2]);
        }
    }

    public function addListeningStatDirect(string $platformTag)
    {
        $statement = $this->connection->prepare('INSERT INTO `stats_listening` (`platform`, `listened_at`) VALUES (:platform, NOW())');

        $res = $statement->execute([
          ':platform' => $platformTag,
        ]);

        if ($res === false) {
            throw new \Exception('Error adding listening stat : '.$statement->errorInfo()[2]);
        }
    }

    public function addViewingStat(int $item_id)
    {
        $statement = $this->connection->prepare('INSERT INTO `stats_viewing` (`item_id`, `viewed_at`) VALUES (:item_id, NOW())');

        $res = $statement->execute([
          ':item_id' => $item_id,
        ]);

        if ($res === false) {
            throw new \Exception('Error adding viewing stat : '.$statement->errorInfo()[2]);
        }
    }

    public function getPlatformShares()
    {
        $statement = $this->connection->prepare('SELECT `platform`, COUNT(`id`) AS `count` FROM `stats_listening` GROUP BY `platform`');

        $res = $statement->execute();

        if ($res === false) {
            throw new \Exception('Error getting platform share stats : '.$statement->errorInfo()[2]);
        }

        return $statement->fetchAll(\PDO::FETCH_UNIQUE);
    }

    private function getMostViewed(string $flavour)
    {
        $limit = intval($this->parameters['website']['stats_limit']);
        $statement = $this->connection->prepare('SELECT `items`.`id`, `items`.`track`, `items`.`album`, `items`.`artist`, COUNT(`stats_viewing`.`item_id`) AS `count` FROM `stats_viewing` INNER JOIN `items` ON `items`.`id` = `stats_viewing`.`item_id` '.$flavour.' GROUP BY `stats_viewing`.`item_id` LIMIT '.$limit);

        $res = $statement->execute();

        if ($res === false) {
            throw new \Exception('Error getting most viewed items : '.$statement->errorInfo()[2]);
        }

        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getMostViewedTracks()
    {
        return $this->getMostViewed('WHERE `items`.`track` IS NOT NULL');
    }

    public function getMostViewedAlbums()
    {
        return $this->getMostViewed('WHERE `items`.`album` IS NULL');
    }

    public function getApiClients()
    {
        $statement = $this->connection->prepare('SELECT * FROM `oauth_clients`');

        $res = $statement->execute();

        if ($res === false) {
            throw new \Exception('Error getting api clients : '.$statement->errorInfo()[2]);
        }
    }

    public function addApiClient(ApiClientEntity $client)
    {
        $statement = $this->connection->prepare('INSERT INTO `oauth_clients` (`item_id`, `platform`, `index`, `listened_at`) VALUES (:item_id, :platform, :index, NOW())');

        $res = $statement->execute([
          ':item_id' => $item_id,
          ':platform' => $platformTag,
          ':index' => $index,
        ]);

        if ($res === false) {
            throw new \Exception('Error adding api client : '.$statement->errorInfo()[2]);
        }
    }

}
