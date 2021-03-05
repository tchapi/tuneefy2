<?php

namespace tuneefy\DB;

use tuneefy\MusicalEntity\Entities\AlbumEntity;
use tuneefy\MusicalEntity\Entities\TrackEntity;
use tuneefy\MusicalEntity\MusicalEntityInterface;
use tuneefy\Platform\PlatformResult;
use tuneefy\Utils\Utils;

class DatabaseHandler
{
    const METHOD_OTHER = 0;
    const METHOD_PLATFORMS = 1;
    const METHOD_LOOKUP = 2;
    const METHOD_SEARCH = 3;
    const METHOD_AGGREGATE = 4;
    const METHOD_SHARE = 5;

    const METHOD_NAMES = [
        self::METHOD_OTHER => 'misc',
        self::METHOD_PLATFORMS => 'platforms',
        self::METHOD_LOOKUP => 'lookup',
        self::METHOD_SEARCH => 'search',
        self::METHOD_AGGREGATE => 'aggregate',
        self::METHOD_SHARE => 'share',
    ];

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
                'mysql:host='.$this->parameters['database']['server'].':'.($this->parameters['database']['port']?:3306).';dbname='.$this->parameters['database']['name'],
                $this->parameters['database']['user'],
                $this->parameters['database']['password']
            );
            $this->connection->exec("SET NAMES 'utf8';");
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

        if (false === $res) {
            throw new \Exception('Error getting item : '.$statement->errorInfo()[2]);
        }

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        if (false === $row) {
            throw new \Exception('No item with the requested id : '.$id);
        }

        if ($row['signature'] !== hash_hmac('md5', $row['object'], $this->parameters['intents']['secret'])) {
            throw new \Exception('Data for id : '.$id.' has been tampered with, the signature is not valid.');
        }

        $result = unserialize($row['object'], ['allowed_classes' => [TrackEntity::class, AlbumEntity::class]]);

        if (false === $result || !($result instanceof MusicalEntityInterface)) {
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
        $statementSelectIdenticals = $this->connection->prepare('SELECT `id`, `object` FROM `items` WHERE `signature` = :signature AND `expires_at` IS NULL AND `intent` IS NULL LIMIT 1');
        $statementUpdate = $this->connection->prepare('UPDATE `items` SET `expires_at` = NULL, `intent` = NULL WHERE `intent` = :intent');
        $statementDelete = $this->connection->prepare('DELETE FROM `items` WHERE `intent` = :intent');

        $resSelect = $statementSelect->execute([':intent' => $intent]);

        if (false === $resSelect) {
            throw new \Exception('Error getting intent : '.$intent.' - '.$statementSelect->errorInfo()[2]);
        }

        $row = $statementSelect->fetch(\PDO::FETCH_ASSOC);

        if (null == $row) {
            throw new \Exception('NO_OR_EXPIRED_INTENT');
        }

        $resSelectIdenticals = $statementSelectIdenticals->execute([':signature' => $row['signature']]);

        if (false === $resSelectIdenticals) {
            throw new \Exception('Error getting identical intents : '.$intent.' - '.$statementSelectIdenticals->errorInfo()[2]);
        }

        $rowIdenticals = $statementSelectIdenticals->fetch(\PDO::FETCH_ASSOC);

        // If we have already tracks/albums of the same signature
        if (false !== $rowIdenticals && count($rowIdenticals) > 0) {
            $resultIdentical = unserialize($rowIdenticals['object'], ['allowed_classes' => [TrackEntity::class, AlbumEntity::class]]);

            if (false === $resultIdentical || !($resultIdentical instanceof MusicalEntityInterface)) {
                throw new \Exception('SERIALIZATION_ERROR');
            }

            // Delete the intent — we have found something identical
            $statementDelete->execute([':intent' => $intent]);

            return [$resultIdentical->getType(), Utils::toUId($rowIdenticals['id'])];
        }

        // No identical intent in database, "fix" this one
        $resUpdate = $statementUpdate->execute([':intent' => $intent]);

        if (false === $resUpdate) {
            throw new \Exception('Error making intent : '.$intent.' permanent '.$statementUpdate->errorInfo()[2]);
        }

        if ($row['signature'] !== hash_hmac('md5', $row['object'], $this->parameters['intents']['secret'])) {
            throw new \Exception('INVALID_INTENT_SIGNATURE');
        }

        $result = unserialize($row['object'], ['allowed_classes' => [TrackEntity::class, AlbumEntity::class]]);

        if (false === $result || !($result instanceof MusicalEntityInterface)) {
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
          ':track' => ('track' === $entity->getType()) ? $entity->getSafeTitle() : null,
          ':album' => ('track' === $entity->getType()) ? $entity->getAlbum()->getSafeTitle() : $entity->getSafeTitle(),
          ':artist' => $entity->getArtist(),
          ':expires' => $expires->format('Y-m-d H:i:s'),
          ':signature' => hash_hmac('md5', $entityAsString, $this->parameters['intents']['secret']),
          ':client_id' => $client_id,
        ]);

        if (false === $res) {
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

        if (false === $res) {
            throw new \Exception('Error adding listening stat : '.$statement->errorInfo()[2]);
        }
    }

    public function addListeningStatDirect(string $platformTag)
    {
        $statement = $this->connection->prepare('INSERT INTO `stats_listening` (`platform`, `listened_at`) VALUES (:platform, NOW())');

        $res = $statement->execute([
          ':platform' => $platformTag,
        ]);

        if (false === $res) {
            throw new \Exception('Error adding listening stat : '.$statement->errorInfo()[2]);
        }
    }

    public function addViewingStat(int $item_id)
    {
        $statement = $this->connection->prepare('INSERT INTO `stats_viewing` (`item_id`, `referer`, `viewed_at`) VALUES (:item_id, :referer, NOW())');

        $res = $statement->execute([
          ':item_id' => $item_id,
          ':referer' => isset($_SERVER['HTTP_REFERER']) ?: null,
        ]);

        if (false === $res) {
            throw new \Exception('Error adding viewing stat : '.$statement->errorInfo()[2]);
        }
    }

    public function addApiCallingStat(?string $client_id = null, int $method)
    {
        $statement = $this->connection->prepare('INSERT INTO `stats_api` (`client_id`, `method`, `called_at`) VALUES (:client_id, :method, NOW())');

        $res = $statement->execute([
          ':client_id' => $client_id,
          ':method' => $method,
        ]);

        if (false === $res) {
            throw new \Exception('Error adding api calling stat : '.$statement->errorInfo()[2]);
        }
    }

    public function getAllTrends()
    {
        $statement = $this->connection->prepare('SELECT * FROM `trends_mv` ORDER BY type, count DESC');

        $res = $statement->execute();

        if (false === $res) {
            throw new \Exception('Error getting trends : '.$statement->errorInfo()[2]);
        }

        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getHotItems()
    {
        $statement = $this->connection->prepare('SELECT * FROM `hot_items_mv`');

        $res = $statement->execute();

        if (false === $res) {
            throw new \Exception('Error getting stats for hot items : '.$statement->errorInfo()[2]);
        }

        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);

        // Unserialize the object column
        $result = [];
        foreach ($rows as $row) {
            $result[$row['type']] = [
                'id' => $row['id'],
                'entity' => unserialize($row['object'], ['allowed_classes' => [TrackEntity::class, AlbumEntity::class]]),
            ];
        }

        return $result;
    }

    public function getApiStats()
    {
        $statement = $this->connection->prepare('SELECT * FROM `stats_api_mv` ORDER BY client_id, method DESC');

        $res = $statement->execute();

        if (false === $res) {
            throw new \Exception('Error getting api stats : '.$statement->errorInfo()[2]);
        }

        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function updateMaterializedViews()
    {
        $statement = $this->connection->prepare('CALL refresh_trends_mv_now(@rc); CALL refresh_hot_items_mv_now(@rc); CALL refresh_stats_api_mv_now(@rc);');
        $res = $statement->execute();

        if (false === $res) {
            throw new \Exception('Error refreshing views : '.$statement->errorInfo()[2]);
        }
    }

    public function getItemsStats(): array
    {
        $statement = $this->connection->prepare('SELECT 
            COUNT(`items`.`id`) AS `total`,
            COUNT(`items`.`track`) AS `tracks`,
            COUNT(`items`.`intent`) AS `intents`
        FROM `items`');

        $res = $statement->execute();

        if (false === $res) {
            throw new \Exception('Error getting items stats : '.$statement->errorInfo()[2]);
        }

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return ['total' => $row['total'], 'tracks' => $row['tracks'], 'intents' => $row['intents']];
    }

    public function getApiClients()
    {
        $statement = $this->connection->prepare('
            SELECT clients.*, COUNT(`items`.`id`) AS `items` FROM `oauth_clients` `clients`
            LEFT JOIN `items` ON `items`.`client_id` = `clients`.`client_id`
            GROUP BY `clients`.`client_id`
        ');

        $res = $statement->execute();

        if (false === $res) {
            throw new \Exception('Error getting api clients : '.$statement->errorInfo()[2]);
        }

        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function isClientActive(string $clientId)
    {
        $statement = $this->connection->prepare('SELECT active FROM `oauth_clients` WHERE client_id = :client_id');

        $res = $statement->execute([
          ':client_id' => $clientId,
        ]);
        if (false === $res) {
            throw new \Exception('Error retrieving client : '.$statement->errorInfo()[2]);
        }

        return $statement->fetch(\PDO::FETCH_ASSOC);
    }

    public function addApiClient(string $name, string $client_id, string $client_secret, string $description, string $email, string $url, bool $active)
    {
        $statement = $this->connection->prepare('INSERT INTO `oauth_clients` (`name`, `client_id`, `client_secret`, `description`, `email`, `url`, `created_at`, `active`) VALUES (:name, :client_id, :client_secret, :description, :email, :url, NOW(), :active)');

        $res = $statement->execute([
          ':name' => $name,
          ':client_id' => $client_id,
          ':client_secret' => $client_secret,
          ':description' => $description,
          ':email' => $email,
          ':url' => $url,
          ':active' => $active,
        ]);

        if (false === $res) {
            throw new \Exception('Error adding api client : '.$statement->errorInfo()[2]);
        }

        return true;
    }

    public function cleanExpiredIntents()
    {
        $statement = $this->connection->prepare('DELETE FROM items WHERE intent IS NOT NULL AND expires_at IS NOT NULL AND expires_at < NOW()');
        $res = $statement->execute();

        if (false === $res) {
            throw new \Exception('Error deleting expired intents : '.$statement->errorInfo()[2]);
        }
    }

    public function cleanExpiredAccessTokens()
    {
        $statement = $this->connection->prepare('DELETE FROM oauth_access_tokens WHERE expires < NOW() - INTERVAL 2 MONTH');
        $res = $statement->execute();

        if (false === $res) {
            throw new \Exception('Error deleting expired access tokens : '.$statement->errorInfo()[2]);
        }
    }
}
