<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The materialized views used to be refreshed by stored procedures doing a TRUNCATE followed by an
 * INSERT ... SELECT: the trends and home pages were empty for as long as the aggregation ran, and
 * the shared locks taken on the rows read by the INSERT ... SELECT blocked every page view and
 * search writing to the same tables meanwhile. The refresh is now done by StatsService, with plain
 * reads and a shadow table swap, and the procedures are no longer needed.
 */
final class Version20261109200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop the materialized view refresh procedures, now done by the application';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP PROCEDURE IF EXISTS refresh_trends_mv_now');
        $this->addSql('DROP PROCEDURE IF EXISTS refresh_hot_items_mv_now');
        $this->addSql('DROP PROCEDURE IF EXISTS refresh_stats_api_mv_now');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE PROCEDURE refresh_trends_mv_now (OUT rc INT) BEGIN
      TRUNCATE TABLE trends_mv;
      INSERT INTO `trends_mv` (`type`, `platform`, `id`, `track`, `album`, `artist`, `count`)
        (SELECT "platform" as `type`, `platform`, NULL as `id`,  NULL as `track`, NULL as `album`, NULL as `artist`,  COUNT(`id`) AS `count` FROM `stats_listening` GROUP BY `platform` ORDER BY `count` DESC)
        UNION
        (SELECT "track" as `type`, NULL as `platform`, `items`.`id`, `items`.`track`, `items`.`album`, `items`.`artist`, COUNT(`stats_viewing`.`item_id`) AS `count` FROM `stats_viewing` LEFT JOIN `items` ON `items`.`id` = `stats_viewing`.`item_id` WHERE `items`.`track` IS NOT NULL GROUP BY `stats_viewing`.`item_id` ORDER BY `count` DESC LIMIT 5)
        UNION
        (SELECT "album" as `type`, NULL as `platform`, `items`.`id`, NULL as `track`, `items`.`album`, `items`.`artist`, COUNT(`stats_viewing`.`item_id`) AS `count` FROM `stats_viewing` LEFT JOIN `items` ON `items`.`id` = `stats_viewing`.`item_id` WHERE `items`.`track` IS NULL GROUP BY `stats_viewing`.`item_id` ORDER BY `count` DESC LIMIT 5)
        UNION
        (SELECT "artist" as `type`, NULL as `platform`, NULL as `id`,  NULL as `track`, NULL as `album`,`items`.`artist`, COUNT(`stats_viewing`.`item_id`) AS `count` FROM `stats_viewing` LEFT JOIN `items` ON `items`.`id` = `stats_viewing`.`item_id`  GROUP BY `items`.`artist` ORDER BY `count` DESC LIMIT 5);
      SET rc = 0;
      END');

        $this->addSql('CREATE PROCEDURE refresh_hot_items_mv_now (OUT rc INT) BEGIN
        TRUNCATE TABLE hot_items_mv;
        INSERT INTO `hot_items_mv` (`type`, `id`, `object`, `count`)
          (SELECT "track" as `type`, `items`.`id`, `items`.`object`, NULL as `count` FROM `items` WHERE `track` IS NOT NULL AND `expires_at` IS NULL AND `intent` IS NULL ORDER BY `created_at` DESC LIMIT 1) UNION (SELECT  "album" as `type`, `items`.`id`, `object`, NULL as `count` FROM `items` WHERE `track` IS NULL AND `expires_at` IS NULL AND `intent` IS NULL ORDER BY `created_at` DESC LIMIT 1)
          UNION
          (SELECT "most" as `type`, `items`.`id`, `items`.`object`, COUNT(`stats_viewing`.`item_id`) AS `count` FROM `stats_viewing` LEFT JOIN `items` ON `items`.`id` = `stats_viewing`.`item_id` WHERE `stats_viewing`.`viewed_at` > DATE_SUB(NOW(), INTERVAL 1 WEEK) GROUP BY `items`. `id` ORDER BY `count` DESC LIMIT 1);
        SET rc = 0;
      END');

        $this->addSql('CREATE PROCEDURE refresh_stats_api_mv_now (OUT rc INT) BEGIN
        TRUNCATE TABLE stats_api_mv;
        INSERT INTO `stats_api_mv` (`client_id`, `method`, `count`)
          (SELECT client_id, method, count(id) AS count from stats_api group by client_id, method);
        SET rc = 0;
      END');
    }
}
