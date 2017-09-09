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

    private function getMostViewed(string $flavour = null, string $grouping = "GROUP BY `stats_viewing`.`item_id`")
    {
        $limit = intval($this->parameters['website']['stats_limit']);
        $statement = $this->connection->prepare('SELECT `items`.`id`, `items`.`track`, `items`.`album`, `items`.`artist`, COUNT(`stats_viewing`.`item_id`) AS `count` FROM `stats_viewing` INNER JOIN `items` ON `items`.`id` = `stats_viewing`.`item_id` '.$flavour.' '.$grouping.' ORDER BY `count` DESC LIMIT '.$limit);

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
        return $this->getMostViewed('WHERE `items`.`track` IS NULL');
    }

    public function getMostViewedArtists()
    {
        return $this->getMostViewed(null, "GROUP BY UPPER(`items`.`artist`)");
    }

    public function getMostViewedItemThisWeek()
    {
        $statement = $this->connection->prepare('SELECT `items`.`id`, `items`.`object`, COUNT(`stats_viewing`.`item_id`) AS `count` FROM `stats_viewing` INNER JOIN `items` ON `items`.`id` = `stats_viewing`.`item_id` WHERE YEARWEEK(`stats_viewing`.`viewed_at`) = YEARWEEK(NOW()) GROUP BY `items`. `id` ORDER BY `count` DESC LIMIT 1');

        $res = $statement->execute();

        if ($res === false) {
            throw new \Exception('Error getting most viewed item : '.$statement->errorInfo()[2]);
        }

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        $result = [
            "id" => $row['id'],
            "entity" => unserialize($row['object'], ['allowed_classes' => [TrackEntity::class, AlbumEntity::class]]),
        ];

        return $result;
    }

    public function getLastSharedItems(): array
    {
        $statement = $this->connection->prepare('(SELECT `items`.`id`, `items`.`object` FROM `items` WHERE `track` IS NOT NULL AND `expires_at` IS NULL AND `intent` IS NULL ORDER BY `created_at` LIMIT 1) UNION (SELECT `items`.`id`, `object` FROM `items` WHERE `track` IS NULL AND `expires_at` IS NULL AND `intent` IS NULL ORDER BY `created_at` LIMIT 1)');

        $res = $statement->execute();

        if ($res === false) {
            throw new \Exception('Error getting last shared items : '.$statement->errorInfo()[2]);
        }

        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);

        $result = [];

        if (count($rows) > 0) {
            $result["track"] = [
                "id" => $rows[0]['id'],
                "entity" => unserialize($rows[0]['object'], ['allowed_classes' => [TrackEntity::class, AlbumEntity::class]]),
            ];
        }
        if (count($rows) > 1) {
            $result["album"] = [
                "id" => $rows[1]['id'],
                "entity" => unserialize($rows[1]['object'], ['allowed_classes' => [AlbumEntity::class]]),
            ];
        }

        return $result;
    }

    public function getItemsStats(): array
    {
        $statement = $this->connection->prepare('SELECT 
            COUNT(`items`.`id`) AS `total`,
            COUNT(`items`.`track`) AS `tracks`,
            COUNT(`items`.`intent`) AS `intents`
        FROM `items`');

        $res = $statement->execute();

        if ($res === false) {
            throw new \Exception('Error getting items stats : '.$statement->errorInfo()[2]);
        }

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return ['total' => $row["total"], 'tracks' => $row["tracks"], 'intents' => $row["intents"]];
        
    }

    public function getApiClients()
    {
        $statement = $this->connection->prepare('
            SELECT clients.*, COUNT(`items`.`id`) AS `items`, COUNT(`items`.`intent`) AS `intents` FROM `oauth_clients` `clients`
            LEFT JOIN `items` ON `items`.`client_id` = `clients`.`client_id`
            GROUP BY `clients`.`client_id`
        ');

        $res = $statement->execute();

        if ($res === false) {
            throw new \Exception('Error getting api clients : '.$statement->errorInfo()[2]);
        }

        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function addApiClient(string $name, string $client_id, string $client_secret, string $description, string $email, string $url)
    {
        $statement = $this->connection->prepare('INSERT INTO `oauth_clients` (`name`, `client_id`, `client_secret`, `description`, `email`, `url`, `createdAt`) VALUES (:name, :client_id, :client_secret, :description, :email, :url, NOW())');

        $res = $statement->execute([
          ':name' => $name,
          ':client_id' => $client_id,
          ':client_secret' => $client_secret,
          ':description' => $description,
          ':email' => $email,
          ':url' => $url,
        ]);

        if ($res === false) {
            throw new \Exception('Error adding api client : '.$statement->errorInfo()[2]);
        }

        return true;
    }

}
