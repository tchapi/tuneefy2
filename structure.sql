/* Tuneefy tables */

CREATE TABLE `items` (
  `id` INT(11) unsigned NOT NULL AUTO_INCREMENT,
  `intent` VARCHAR(170) DEFAULT NULL,
  `object` BLOB NOT NULL,
  `track` VARCHAR(170) DEFAULT NULL,
  `album` VARCHAR(170) DEFAULT NULL,
  `artist` VARCHAR(170) DEFAULT NULL,
  `created_at` DATETIME NOT NULL,
  `expires_at` DATETIME DEFAULT NULL,
  `signature` VARCHAR(170) NOT NULL DEFAULT '',
  `client_id` VARCHAR(80) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `track` (`track`),
  KEY `album` (`album`),
  KEY `artist` (`artist`),
  KEY `intent` (`intent`),
  KEY `signature` (`signature`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;

CREATE TABLE `stats_viewing` (
  `id` INT(11) unsigned NOT NULL AUTO_INCREMENT,
  `item_id` INT(11) unsigned NOT NULL,
  `referer` VARCHAR(170) DEFAULT NULL,
  `viewed_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `item_id` (`item_id`),
  KEY `viewed_at` (`viewed_at`)
  CONSTRAINT `stats_viewing_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

CREATE TABLE `stats_listening` (
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

CREATE TABLE `stats_api` (
  `id` INT(11) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` VARCHAR(80) NOT NULL,
  `method` VARCHAR(170) DEFAULT NULL,
  `called_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `client_id` (`client_id`),
  KEY `called_at` (`called_at`),
  KEY `method` (`method`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

/* OAuth tables */
CREATE TABLE oauth_clients (
  client_id VARCHAR(80) NOT NULL,
  client_secret VARCHAR(80),
  `created_at` DATETIME NOT NULL,
  `email` VARCHAR(170) NOT NULL,
  `name` VARCHAR(170) NOT NULL,
  `url` VARCHAR(170) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `active` BOOLEAN DEFAULT 0,
  redirect_uri VARCHAR(2000) DEFAULT  NULL,
  grant_types VARCHAR(80),
  scope VARCHAR(100),
  user_id VARCHAR(80),
  CONSTRAINT clients_client_id_pk PRIMARY KEY (client_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE oauth_access_tokens (access_token VARCHAR(40) NOT NULL, client_id VARCHAR(80) NOT NULL, user_id VARCHAR(255), expires TIMESTAMP NOT NULL, scope VARCHAR(2000), CONSTRAINT access_token_pk PRIMARY KEY (access_token));
CREATE TABLE oauth_authorization_codes (authorization_code VARCHAR(40) NOT NULL, client_id VARCHAR(80) NOT NULL, user_id VARCHAR(255), redirect_uri VARCHAR(2000), expires TIMESTAMP NOT NULL, scope VARCHAR(2000), CONSTRAINT auth_code_pk PRIMARY KEY (authorization_code));
CREATE TABLE oauth_refresh_tokens (refresh_token VARCHAR(40) NOT NULL, client_id VARCHAR(80) NOT NULL, user_id VARCHAR(255), expires TIMESTAMP NOT NULL, scope VARCHAR(2000), CONSTRAINT refresh_token_pk PRIMARY KEY (refresh_token));
CREATE TABLE oauth_users (username VARCHAR(255) NOT NULL, password VARCHAR(2000), first_name VARCHAR(255), last_name VARCHAR(255), CONSTRAINT username_pk PRIMARY KEY (username));
CREATE TABLE oauth_scopes (scope TEXT, is_default BOOLEAN);
CREATE TABLE oauth_jwt (client_id VARCHAR(80) NOT NULL, subject VARCHAR(80), public_key VARCHAR(2000), CONSTRAINT jwt_client_id_pk PRIMARY KEY (client_id));


-- Materialised views for stats / trends

CREATE TABLE trends_mv (
    `type` VARCHAR(20)  NOT NULL,
    `id` INT(11),
    `track` VARCHAR(170),
    `album` VARCHAR(170),
    `artist` VARCHAR(170),
    `platform` VARCHAR(170),
    `count` INT(11) NOT NULL
);

DROP PROCEDURE refresh_trends_mv_now;

DELIMITER $$

CREATE PROCEDURE refresh_trends_mv_now (
    OUT rc INT
)
BEGIN

  TRUNCATE TABLE trends_mv;

  INSERT INTO `trends_mv` (`type`, `platform`, `id`, `track`, `album`, `artist`, `count`)
    (SELECT 'platform' as `type`, `platform`, NULL as `id`,  NULL as `track`, NULL as `album`, NULL as `artist`,  COUNT(`id`) AS `count` FROM `stats_listening` GROUP BY `platform` ORDER BY `count` DESC)
    UNION
    (SELECT 'track' as `type`, NULL as `platform`, `items`.`id`, `items`.`track`, `items`.`album`, `items`.`artist`, COUNT(`stats_viewing`.`item_id`) AS `count` FROM `stats_viewing` LEFT JOIN `items` ON `items`.`id` = `stats_viewing`.`item_id` WHERE `items`.`track` IS NOT NULL GROUP BY `stats_viewing`.`item_id` ORDER BY `count` DESC LIMIT 5)
    UNION
    (SELECT 'album' as `type`, NULL as `platform`, `items`.`id`, NULL as `track`, `items`.`album`, `items`.`artist`, COUNT(`stats_viewing`.`item_id`) AS `count` FROM `stats_viewing` LEFT JOIN `items` ON `items`.`id` = `stats_viewing`.`item_id` WHERE `items`.`track` IS NULL GROUP BY `stats_viewing`.`item_id` ORDER BY `count` DESC LIMIT 5)
    UNION
    (SELECT 'artist' as `type`, NULL as `platform`, NULL as `id`,  NULL as `track`, NULL as `album`,`items`.`artist`, COUNT(`stats_viewing`.`item_id`) AS `count` FROM `stats_viewing` LEFT JOIN `items` ON `items`.`id` = `stats_viewing`.`item_id`  GROUP BY `items`.`artist` ORDER BY `count` DESC LIMIT 5);

  SET rc = 0;
END;
$$

DELIMITER ;


-- Materialised views for hot items (home page)

CREATE TABLE hot_items_mv (
    `type` VARCHAR(20)  NOT NULL,
    `id` INT(11),
    `object` BLOB,
    `count` INT(11)
);

DROP PROCEDURE refresh_hot_items_mv_now;

DELIMITER $$

CREATE PROCEDURE refresh_hot_items_mv_now (
    OUT rc INT
)
BEGIN

  TRUNCATE TABLE hot_items_mv;

  INSERT INTO `hot_items_mv` (`type`, `id`, `object`, `count`)
    (SELECT "track" as `type`, `items`.`id`, `items`.`object`, NULL as `count` FROM `items` WHERE `track` IS NOT NULL AND `expires_at` IS NULL AND `intent` IS NULL ORDER BY `created_at` DESC LIMIT 1) UNION (SELECT  "album" as `type`, `items`.`id`, `object`, NULL as `count` FROM `items` WHERE `track` IS NULL AND `expires_at` IS NULL AND `intent` IS NULL ORDER BY `created_at` DESC LIMIT 1)
    UNION
    (SELECT 'most' as `type`, `items`.`id`, `items`.`object`, COUNT(`stats_viewing`.`item_id`) AS `count` FROM `stats_viewing` LEFT JOIN `items` ON `items`.`id` = `stats_viewing`.`item_id` WHERE `stats_viewing`.`viewed_at` > DATE_SUB(NOW(), INTERVAL 1 WEEK) GROUP BY `items`. `id` ORDER BY `count` DESC LIMIT 1);

  SET rc = 0;
END;
$$

DELIMITER ;


-- Materialised views for api items

CREATE TABLE stats_api_mv (
    `client_id` VARCHAR(80) NOT NULL,
    `method` VARCHAR(170) DEFAULT NULL,
    `count` INT(11)
);

DROP PROCEDURE refresh_stats_api_mv_now;

DELIMITER $$

CREATE PROCEDURE refresh_stats_api_mv_now (
    OUT rc INT
)
BEGIN

  TRUNCATE TABLE stats_api_mv;

  INSERT INTO `stats_api_mv` (`client_id`, `method`, `count`)
    (SELECT client_id, method, count(id) AS count from stats_api group by client_id, method);

  SET rc = 0;
END;
$$

DELIMITER ;
