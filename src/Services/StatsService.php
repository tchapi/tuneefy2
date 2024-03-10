<?php

namespace App\Services;

use App\Dataclass\MusicalEntity\Entities\Album;
use App\Dataclass\MusicalEntity\Entities\Track;
use Doctrine\Persistence\ManagerRegistry;

final class StatsService
{
    public const METHOD_OTHER = 0;
    public const METHOD_PLATFORMS = 1;
    public const METHOD_LOOKUP = 2;
    public const METHOD_SEARCH = 3;
    public const METHOD_AGGREGATE = 4;
    public const METHOD_SHARE = 5;

    public const METHOD_NAMES = [
        self::METHOD_OTHER => 'misc',
        self::METHOD_PLATFORMS => 'platforms',
        self::METHOD_LOOKUP => 'lookup',
        self::METHOD_SEARCH => 'search',
        self::METHOD_AGGREGATE => 'aggregate',
        self::METHOD_SHARE => 'share',
    ];

    /**
     * Database connection.
     */
    private $connection;

    public function __construct(ManagerRegistry $doctrine)
    {
        $this->connection = $doctrine->getConnection();
    }

    public function addListeningStat(int $item_id, string $platformTag, int $index)
    {
        $statement = $this->connection->prepare('INSERT INTO `stats_listening` (`item_id`, `platform`, `index`, `listened_at`) VALUES (:item_id, :platform, :index, NOW())');

        $statement->bindValue('item_id', $item_id);
        $statement->bindValue('platform', $platformTag);
        $statement->bindValue('index', $index);

        $res = $statement->executeStatement();

        if (false === $res) {
            throw new \Exception('Error adding listening stat: '.$statement->errorInfo()[2]);
        }
    }

    public function addListeningStatDirect(string $platformTag)
    {
        $statement = $this->connection->prepare('INSERT INTO `stats_listening` (`platform`, `listened_at`) VALUES (:platform, NOW())');

        $statement->bindValue('platform', $platformTag);

        $res = $statement->executeStatement();

        if (false === $res) {
            throw new \Exception('Error adding listening stat: '.$statement->errorInfo()[2]);
        }
    }

    public function addViewingStat(int $item_id)
    {
        $statement = $this->connection->prepare('INSERT INTO `stats_viewing` (`item_id`, `referer`, `viewed_at`) VALUES (:item_id, :referer, NOW())');

        $referer = isset($_SERVER['HTTP_REFERER']) ?: null;

        $statement->bindValue('item_id', $item_id);
        $statement->bindValue('referer', $referer);

        $res = $statement->executeStatement();

        if (false === $res) {
            throw new \Exception('Error adding viewing stat: '.$statement->errorInfo()[2]);
        }
    }

    public function addApiCallingStat(int $method, ?string $client_id = null)
    {
        $statement = $this->connection->prepare('INSERT INTO `stats_api` (`client_id`, `method`, `called_at`) VALUES (:client_id, :method, NOW())');

        $statement->bindValue('client_id', $client_id);
        $statement->bindValue('method', $method);

        $res = $statement->executeStatement();

        if (false === $res) {
            throw new \Exception('Error adding api calling stat: '.$statement->errorInfo()[2]);
        }
    }

    public function getAllTrends()
    {
        $statement = $this->connection->prepare('SELECT * FROM `trends_mv` ORDER BY type, count DESC');

        $res = $statement->executeQuery();

        if (false === $res) {
            throw new \Exception('Error getting trends: '.$statement->errorInfo()[2]);
        }

        return $res->fetchAllAssociative();
    }

    public function getHotItems()
    {
        $statement = $this->connection->prepare('SELECT * FROM `hot_items_mv`');

        $res = $statement->executeQuery();

        if (false === $res) {
            throw new \Exception('Error getting stats for hot items: '.$statement->errorInfo()[2]);
        }

        $rows = $res->fetchAllAssociative();

        // Unserialize the object column
        $result = ['track' => null, 'album' => null];
        foreach ($rows as $row) {
            $result[$row['type']] = [
                'id' => $row['id'],
                // TODO: fix, use getMusicalEntity here
                'entity' => unserialize($row['object'], ['allowed_classes' => [Track::class, Album::class]]),
            ];
        }

        return $result;
    }

    public function getApiStats()
    {
        $statement = $this->connection->prepare('SELECT * FROM `stats_api_mv` ORDER BY client_id, method DESC');

        $res = $statement->executeQuery();

        if (false === $res) {
            throw new \Exception('Error getting api stats: '.$statement->errorInfo()[2]);
        }

        return $res->fetchAllAssociative();
    }

    public function updateMaterializedViews()
    {
        $statement = $this->connection->prepare('CALL refresh_trends_mv_now(@rc); CALL refresh_hot_items_mv_now(@rc); CALL refresh_stats_api_mv_now(@rc);');

        $res = $statement->executeStatement();

        if (false === $res) {
            throw new \Exception('Error refreshing views: '.$statement->errorInfo()[2]);
        }
    }

    public function getItemsStats(): array
    {
        $statement = $this->connection->prepare('SELECT 
            COUNT(`items`.`id`) AS `total`,
            COUNT(`items`.`track`) AS `tracks`,
            COUNT(`items`.`intent`) AS `intents`
        FROM `items`');

        $res = $statement->executeQuery();

        if (false === $res) {
            throw new \Exception('Error getting items stats: '.$statement->errorInfo()[2]);
        }

        $row = $res->fetchAssociative();

        return ['total' => $row['total'], 'tracks' => $row['tracks'], 'intents' => $row['intents']];
    }
}
