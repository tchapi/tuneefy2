<?php

namespace App\Services;

use App\Dataclass\MusicalEntity\Entities\Album;
use App\Dataclass\MusicalEntity\Entities\Track;
use App\Utils\LegacyEncoding;
use Doctrine\DBAL\Connection;

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

    public function __construct(private Connection $connection)
    {
    }

    public function addListeningStat(int $item_id, string $platformTag, int $index): void
    {
        $statement = $this->connection->prepare('INSERT INTO `stats_listening` (`item_id`, `platform`, `index`, `listened_at`) VALUES (:item_id, :platform, :index, NOW())');

        $statement->bindValue('item_id', $item_id);
        $statement->bindValue('platform', $platformTag);
        $statement->bindValue('index', $index);

        $res = $statement->executeStatement();
    }

    public function addListeningStatDirect(string $platformTag): void
    {
        $statement = $this->connection->prepare('INSERT INTO `stats_listening` (`platform`, `listened_at`) VALUES (:platform, NOW())');

        $statement->bindValue('platform', $platformTag);

        $res = $statement->executeStatement();
    }

    public function addViewingStat(int $item_id): void
    {
        $statement = $this->connection->prepare('INSERT INTO `stats_viewing` (`item_id`, `referer`, `viewed_at`) VALUES (:item_id, :referer, NOW())');

        $referer = $_SERVER['HTTP_REFERER'] ?? null;

        $statement->bindValue('item_id', $item_id);
        $statement->bindValue('referer', $referer);

        $res = $statement->executeStatement();
    }

    public function addApiCallingStat(int $method, ?string $client_id = null): void
    {
        $statement = $this->connection->prepare('INSERT INTO `stats_api` (`client_id`, `method`, `called_at`) VALUES (:client_id, :method, NOW())');

        $statement->bindValue('client_id', $client_id);
        $statement->bindValue('method', $method);

        $res = $statement->executeStatement();
    }

    public function getAllTrends(): array
    {
        $statement = $this->connection->prepare('SELECT * FROM `trends_mv` ORDER BY type, count DESC');

        $res = $statement->executeQuery();

        return $res->fetchAllAssociative();
    }

    public function getHotItems(): array
    {
        $statement = $this->connection->prepare('SELECT * FROM `hot_items_mv`');

        $res = $statement->executeQuery();

        $rows = $res->fetchAllAssociative();

        // Unserialize the object column
        $result = ['track' => null, 'album' => null];
        foreach ($rows as $row) {
            $migratedObject = str_replace(
                ['tuneefy\MusicalEntity\Entities\AlbumEntity', 'tuneefy\MusicalEntity\Entities\TrackEntity'],
                ['App\Dataclass\MusicalEntity\Entities\Album', 'App\Dataclass\MusicalEntity\Entities\Track'],
                (string) $row['object']
            );

            $entity = unserialize($migratedObject, ['allowed_classes' => [Track::class, Album::class]]);
            if (\is_object($entity)) {
                LegacyEncoding::repair($entity);
            }

            $result[$row['type']] = [
                'id' => $row['id'],
                'entity' => $entity,
            ];
        }

        return $result;
    }

    public function getApiStats(): array
    {
        $statement = $this->connection->prepare('SELECT * FROM `stats_api_mv` ORDER BY client_id, method DESC');

        $res = $statement->executeQuery();

        return $res->fetchAllAssociative();
    }

    // The aggregations behind the materialized views. They are run as plain SELECTs, which read a
    // consistent snapshot without locking anything: an INSERT ... SELECT would hold shared locks on
    // every row it reads for its whole duration, blocking the page views and searches that write
    // to the same tables.
    private const string TRENDS_SQL = '
        (SELECT "platform" AS `type`, `platform`, NULL AS `id`, NULL AS `track`, NULL AS `album`, NULL AS `artist`, COUNT(`id`) AS `count` FROM `stats_listening` GROUP BY `platform` ORDER BY `count` DESC)
        UNION
        (SELECT "track" AS `type`, NULL AS `platform`, `items`.`id`, `items`.`track`, `items`.`album`, `items`.`artist`, COUNT(`stats_viewing`.`item_id`) AS `count` FROM `stats_viewing` LEFT JOIN `items` ON `items`.`id` = `stats_viewing`.`item_id` WHERE `items`.`track` IS NOT NULL GROUP BY `stats_viewing`.`item_id` ORDER BY `count` DESC LIMIT 5)
        UNION
        (SELECT "album" AS `type`, NULL AS `platform`, `items`.`id`, NULL AS `track`, `items`.`album`, `items`.`artist`, COUNT(`stats_viewing`.`item_id`) AS `count` FROM `stats_viewing` LEFT JOIN `items` ON `items`.`id` = `stats_viewing`.`item_id` WHERE `items`.`track` IS NULL GROUP BY `stats_viewing`.`item_id` ORDER BY `count` DESC LIMIT 5)
        UNION
        (SELECT "artist" AS `type`, NULL AS `platform`, NULL AS `id`, NULL AS `track`, NULL AS `album`, `items`.`artist`, COUNT(`stats_viewing`.`item_id`) AS `count` FROM `stats_viewing` LEFT JOIN `items` ON `items`.`id` = `stats_viewing`.`item_id` GROUP BY `items`.`artist` ORDER BY `count` DESC LIMIT 5)
    ';

    private const string HOT_ITEMS_SQL = '
        (SELECT "track" AS `type`, `items`.`id`, `items`.`object`, NULL AS `count` FROM `items` WHERE `track` IS NOT NULL AND `expires_at` IS NULL AND `intent` IS NULL ORDER BY `created_at` DESC LIMIT 1)
        UNION
        (SELECT "album" AS `type`, `items`.`id`, `items`.`object`, NULL AS `count` FROM `items` WHERE `track` IS NULL AND `expires_at` IS NULL AND `intent` IS NULL ORDER BY `created_at` DESC LIMIT 1)
        UNION
        (SELECT "most" AS `type`, `items`.`id`, `items`.`object`, COUNT(`stats_viewing`.`item_id`) AS `count` FROM `stats_viewing` LEFT JOIN `items` ON `items`.`id` = `stats_viewing`.`item_id` WHERE `stats_viewing`.`viewed_at` > DATE_SUB(NOW(), INTERVAL 1 WEEK) GROUP BY `items`.`id` ORDER BY `count` DESC LIMIT 1)
    ';

    private const string API_STATS_SQL = '
        SELECT `client_id`, `method`, COUNT(`id`) AS `count` FROM `stats_api` GROUP BY `client_id`, `method`
    ';

    public function updateMaterializedViews(): void
    {
        $this->refreshMaterializedView('trends_mv', ['type', 'platform', 'id', 'track', 'album', 'artist', 'count'], self::TRENDS_SQL);
        $this->refreshMaterializedView('hot_items_mv', ['type', 'id', 'object', 'count'], self::HOT_ITEMS_SQL);
        $this->refreshMaterializedView('stats_api_mv', ['client_id', 'method', 'count'], self::API_STATS_SQL);
    }

    /**
     * Computes the rows with a plain read, writes them to a shadow table and swaps it in atomically,
     * so the view is never empty while it is being refreshed.
     *
     * @param list<string> $columns
     */
    private function refreshMaterializedView(string $table, array $columns, string $sql): void
    {
        $rows = $this->connection->fetchAllAssociative($sql);

        $new = $table.'_new';
        $old = $table.'_old';
        $quotedColumns = implode(', ', array_map(fn (string $column): string => '`'.$column.'`', $columns));
        $placeholders = '('.implode(', ', array_fill(0, \count($columns), '?')).')';

        $this->connection->executeStatement(sprintf('DROP TABLE IF EXISTS `%s`', $new));
        $this->connection->executeStatement(sprintf('CREATE TABLE `%s` LIKE `%s`', $new, $table));

        if ([] !== $rows) {
            $values = [];
            foreach ($rows as $row) {
                foreach ($columns as $column) {
                    $values[] = $row[$column];
                }
            }

            $this->connection->executeStatement(
                sprintf('INSERT INTO `%s` (%s) VALUES %s', $new, $quotedColumns, implode(', ', array_fill(0, \count($rows), $placeholders))),
                $values
            );
        }

        $this->connection->executeStatement(sprintf('DROP TABLE IF EXISTS `%s`', $old));
        $this->connection->executeStatement(sprintf('RENAME TABLE `%s` TO `%s`, `%s` TO `%s`', $table, $old, $new, $table));
        $this->connection->executeStatement(sprintf('DROP TABLE `%s`', $old));
    }

    public function getItemsStats(): array
    {
        $statement = $this->connection->prepare('SELECT 
            COUNT(`items`.`id`) AS `total`,
            COUNT(`items`.`track`) AS `tracks`,
            COUNT(`items`.`intent`) AS `intents`
        FROM `items`');

        $res = $statement->executeQuery();

        $row = $res->fetchAssociative();
        if (false === $row) {
            throw new \RuntimeException('Could not read the items stats');
        }

        return ['total' => $row['total'], 'tracks' => $row['tracks'], 'intents' => $row['intents']];
    }
}
