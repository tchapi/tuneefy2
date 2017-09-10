/* Tuneefy tables */

-- Create syntax for TABLE 'items'
CREATE TABLE `items` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `intent` varchar(170) DEFAULT NULL,
  `object` blob NOT NULL,
  `track` varchar(170) DEFAULT NULL,
  `album` varchar(170) DEFAULT NULL,
  `artist` varchar(170) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `expires_at` datetime DEFAULT NULL,
  `signature` varchar(170) NOT NULL DEFAULT '',
  `client_id` varchar(80) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;

CREATE TABLE `stats_viewing` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `item_id` int(11) unsigned NOT NULL,
  `referer` varchar(170) DEFAULT NULL,
  `viewed_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `item_id` (`item_id`),
  CONSTRAINT `stats_viewing_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

CREATE TABLE `stats_listening` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `item_id` int(11) unsigned DEFAULT NULL,
  `platform` varchar(25) NOT NULL,
  `index` int(11) unsigned DEFAULT NULL,
  `listened_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `item_id` (`item_id`),
  CONSTRAINT `stats_listening_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

/* OAuth tables */
CREATE TABLE oauth_clients (
  client_id VARCHAR(80) NOT NULL,
  client_secret VARCHAR(80),
  `created_at` datetime NOT NULL,
  `email` varchar(170) NOT NULL,
  `name` varchar(170) NOT NULL,
  `url` varchar(170) DEFAULT NULL,
  `description` text DEFAULT NULL,
  redirect_uri VARCHAR(2000) DEFAULT  NULL,
  grant_types VARCHAR(80),
  scope VARCHAR(100),
  user_id VARCHAR(80),
  CONSTRAINT clients_client_id_pk PRIMARY KEY (client_id)
);

CREATE TABLE oauth_access_tokens (access_token VARCHAR(40) NOT NULL, client_id VARCHAR(80) NOT NULL, user_id VARCHAR(255), expires TIMESTAMP NOT NULL, scope VARCHAR(2000), CONSTRAINT access_token_pk PRIMARY KEY (access_token));
CREATE TABLE oauth_authorization_codes (authorization_code VARCHAR(40) NOT NULL, client_id VARCHAR(80) NOT NULL, user_id VARCHAR(255), redirect_uri VARCHAR(2000), expires TIMESTAMP NOT NULL, scope VARCHAR(2000), CONSTRAINT auth_code_pk PRIMARY KEY (authorization_code));
CREATE TABLE oauth_refresh_tokens (refresh_token VARCHAR(40) NOT NULL, client_id VARCHAR(80) NOT NULL, user_id VARCHAR(255), expires TIMESTAMP NOT NULL, scope VARCHAR(2000), CONSTRAINT refresh_token_pk PRIMARY KEY (refresh_token));
CREATE TABLE oauth_users (username VARCHAR(255) NOT NULL, password VARCHAR(2000), first_name VARCHAR(255), last_name VARCHAR(255), CONSTRAINT username_pk PRIMARY KEY (username));
CREATE TABLE oauth_scopes (scope TEXT, is_default BOOLEAN);
CREATE TABLE oauth_jwt (client_id VARCHAR(80) NOT NULL, subject VARCHAR(80), public_key VARCHAR(2000), CONSTRAINT jwt_client_id_pk PRIMARY KEY (client_id));
