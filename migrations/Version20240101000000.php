<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240101000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial tables';
    }

    public function up(Schema $schema): void
    {
      $this->addSql('CREATE TABLE api_client (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, url VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, created_at DATE NOT NULL, oauth2_client_identifier VARCHAR(80) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

      $this->addSql('CREATE TABLE `items` (
        `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
        `intent` varchar(170) DEFAULT NULL,
        `object` blob NOT NULL,
        `track` varchar(170) DEFAULT NULL,
        `album` varchar(170) DEFAULT NULL,
        `artist` varchar(170) DEFAULT NULL,
        `created_at` datetime NOT NULL,
        `expires_at` datetime DEFAULT NULL,
        `signature` varchar(170) NOT NULL,
        `client_id` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `track` (`track`),
        KEY `album` (`album`),
        KEY `artist` (`artist`),
        KEY `intent` (`intent`),
        KEY `signature` (`signature`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');
      
      /* Stats tables */
      $this->addSql('CREATE TABLE `stats_viewing` (
        `id` INT(11) unsigned NOT NULL AUTO_INCREMENT,
        `item_id` INT(11) unsigned NOT NULL,
        `referer` VARCHAR(170) DEFAULT NULL,
        `viewed_at` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        KEY `item_id` (`item_id`),
        KEY `viewed_at` (`viewed_at`),
        CONSTRAINT `stats_viewing_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`)
      ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
      ');

      $this->addSql('CREATE TABLE `stats_listening` (
        `id` INT(11) unsigned NOT NULL AUTO_INCREMENT,
        `item_id` INT(11) unsigned DEFAULT NULL,
        `platform` VARCHAR(25) NOT NULL,
        `index` INT(11) unsigned DEFAULT NULL,
        `listened_at` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        KEY `item_id` (`item_id`),
        KEY `platform` (`platform`),
        CONSTRAINT `stats_listening_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`)
      ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
      ');

      $this->addSql('CREATE TABLE `stats_api` (
        `id` INT(11) unsigned NOT NULL AUTO_INCREMENT,
        `client_id` VARCHAR(80) NOT NULL,
        `method` VARCHAR(170) DEFAULT NULL,
        `called_at` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        KEY `client_id` (`client_id`),
        KEY `called_at` (`called_at`),
        KEY `method` (`method`)
      ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
      ');

      // Materialised views for stats / trends
      $this->addSql('CREATE TABLE trends_mv (
        `type` VARCHAR(20)  NOT NULL,
        `id` INT(11),
        `track` VARCHAR(170),
        `album` VARCHAR(170),
        `artist` VARCHAR(170),
        `platform` VARCHAR(170),
        `count` INT(11) NOT NULL
      );');

      $this->addSql('DROP PROCEDURE IF EXISTS refresh_trends_mv_now;
      DELIMITER $$
      CREATE PROCEDURE refresh_trends_mv_now (OUT rc INT) BEGIN
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
      END;
      $$
      DELIMITER ;
      ');

      // Materialized views for hot items (home page)
      $this->addSql('CREATE TABLE hot_items_mv (
          `type` VARCHAR(20)  NOT NULL,
          `id` INT(11),
          `object` BLOB,
          `count` INT(11)
      );');
      
      $this->addSql('DROP PROCEDURE IF EXISTS refresh_hot_items_mv_now;
      DELIMITER $$
      CREATE PROCEDURE refresh_hot_items_mv_now (OUT rc INT) BEGIN
        TRUNCATE TABLE hot_items_mv;
        INSERT INTO `hot_items_mv` (`type`, `id`, `object`, `count`)
          (SELECT "track" as `type`, `items`.`id`, `items`.`object`, NULL as `count` FROM `items` WHERE `track` IS NOT NULL AND `expires_at` IS NULL AND `intent` IS NULL ORDER BY `created_at` DESC LIMIT 1) UNION (SELECT  "album" as `type`, `items`.`id`, `object`, NULL as `count` FROM `items` WHERE `track` IS NULL AND `expires_at` IS NULL AND `intent` IS NULL ORDER BY `created_at` DESC LIMIT 1)
          UNION
          (SELECT "most" as `type`, `items`.`id`, `items`.`object`, COUNT(`stats_viewing`.`item_id`) AS `count` FROM `stats_viewing` LEFT JOIN `items` ON `items`.`id` = `stats_viewing`.`item_id` WHERE `stats_viewing`.`viewed_at` > DATE_SUB(NOW(), INTERVAL 1 WEEK) GROUP BY `items`. `id` ORDER BY `count` DESC LIMIT 1);
        SET rc = 0;
      END;
      $$
      DELIMITER ;
      ');
      
      // Materialized views for api items
      $this->addSql('CREATE TABLE stats_api_mv (
          `client_id` VARCHAR(80) NOT NULL,
          `method` VARCHAR(170) DEFAULT NULL,
          `count` INT(11)
      );');
      
      $this->addSql('DROP PROCEDURE IF EXISTS refresh_stats_api_mv_now;
      DELIMITER $$
      CREATE PROCEDURE refresh_stats_api_mv_now (OUT rc INT) BEGIN
        TRUNCATE TABLE stats_api_mv;
        INSERT INTO `stats_api_mv` (`client_id`, `method`, `count`)
          (SELECT client_id, method, count(id) AS count from stats_api group by client_id, method);
        SET rc = 0;
      END;
      $$
      DELIMITER ;
      ');
    
    }

    public function down(Schema $schema): void
    {
      $this->addSql('DROP TABLE api_client');

      $this->addSql('DROP PROCEDURE IF EXISTS refresh_stats_api_mv_now;');
      $this->addSql('DROP TABLE stats_api_mv');
      $this->addSql('DROP PROCEDURE IF EXISTS refresh_hot_items_mv_now;');
      $this->addSql('DROP TABLE hot_items_mv');
      $this->addSql('DROP PROCEDURE IF EXISTS refresh_trends_mv_now;');
      $this->addSql('DROP TABLE trends_nv');

      $this->addSql('DROP TABLE stats_viewing');
      $this->addSql('DROP TABLE stats_listening');
      $this->addSql('DROP TABLE stats_api');
    }
}
