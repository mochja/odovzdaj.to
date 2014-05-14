-- Adminer 4.0.3 MySQL dump

SET NAMES utf8;
SET foreign_key_checks = 0;
SET time_zone = '+02:00';
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

DROP TABLE IF EXISTS `odovzdania`;
CREATE TABLE `odovzdania` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `poznamka` text,
  `zadanie_id` int(10) unsigned NOT NULL,
  `pouzivatel_id` int(10) unsigned NOT NULL,
  `cas_odovzdania` datetime NOT NULL,
  `cas_upravenia` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `zadanie_id_pouzivatel_id` (`zadanie_id`,`pouzivatel_id`),
  KEY `pouzivatel_id` (`pouzivatel_id`),
  CONSTRAINT `odovzdania_ibfk_1` FOREIGN KEY (`zadanie_id`) REFERENCES `zadania` (`id`) ON DELETE CASCADE,
  CONSTRAINT `odovzdania_ibfk_2` FOREIGN KEY (`pouzivatel_id`) REFERENCES `pouzivatelia` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `odovzdania` (`id`, `poznamka`, `zadanie_id`, `pouzivatel_id`, `cas_odovzdania`, `cas_upravenia`) VALUES
(8,	'co ak tu dam <strong>asdf</strong>',	3,	3,	'2014-05-12 14:43:24',	'2014-05-12 14:45:46');

DROP TABLE IF EXISTS `pouzivatelia`;
CREATE TABLE `pouzivatelia` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `trieda_id` int(11) DEFAULT NULL,
  `meno` varchar(32) NOT NULL,
  `login` varchar(16) NOT NULL,
  `skratka` varchar(3) DEFAULT NULL,
  `role` tinyint(3) unsigned NOT NULL DEFAULT '1' COMMENT '1 - student; 2 - teacher; 10 - admin',
  PRIMARY KEY (`id`),
  KEY `trieda_id` (`trieda_id`),
  CONSTRAINT `pouzivatelia_ibfk_1` FOREIGN KEY (`trieda_id`) REFERENCES `triedy` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `pouzivatelia` (`id`, `trieda_id`, `meno`, `login`, `skratka`, `role`) VALUES
(2,	1,	'asdf',	'asdf',	NULL,	2),
(3,	1,	'Janko',	'jan',	NULL,	1),
(4,	2,	'cuitel',	'',	NULL,	1);

DROP TABLE IF EXISTS `predmety`;
CREATE TABLE `predmety` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nazov` varchar(128) NOT NULL,
  `skratka` varchar(6) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `predmety` (`id`, `nazov`, `skratka`) VALUES
(1,	'asdf',	'PRO');

DROP TABLE IF EXISTS `subory`;
CREATE TABLE `subory` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `odovzdanie_id` int(10) unsigned NOT NULL,
  `nazov` varchar(128) NOT NULL,
  `cesta` varchar(512) NOT NULL,
  `velkost` bigint(20) unsigned NOT NULL,
  `cas_odovzdania` datetime NOT NULL,
  `cas_upravenia` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `odovzdanie_id` (`odovzdanie_id`),
  CONSTRAINT `subory_ibfk_2` FOREIGN KEY (`odovzdanie_id`) REFERENCES `odovzdania` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `subory` (`id`, `odovzdanie_id`, `nazov`, `cesta`, `velkost`, `cas_odovzdania`, `cas_upravenia`) VALUES
(9,	8,	'256x256.jpg',	'a8864935_256x256.jpg',	23313,	'2014-05-12 14:43:24',	NULL);

DROP TABLE IF EXISTS `triedy`;
CREATE TABLE `triedy` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rocnik` tinyint(3) unsigned NOT NULL,
  `kod` varchar(3) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `triedy` (`id`, `rocnik`, `kod`) VALUES
(1,	1,	'A'),
(2,	1,	'SA');

DROP TABLE IF EXISTS `zadania`;
CREATE TABLE `zadania` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nazov` varchar(32) NOT NULL,
  `trieda_id` int(11) NOT NULL,
  `pouzivatel_id` int(10) unsigned NOT NULL,
  `predmet_id` int(10) unsigned NOT NULL,
  `stav` tinyint(3) unsigned DEFAULT '0' COMMENT '0 - uzatvorene; 1 - otvorene; 2 - otvorene aj po uzavierke',
  `cas_uzatvorenia` datetime NOT NULL,
  `cas_vytvorenia` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `trieda_id` (`trieda_id`),
  KEY `predmet_id` (`predmet_id`),
  KEY `pouzivatel_id` (`pouzivatel_id`),
  CONSTRAINT `zadania_ibfk_1` FOREIGN KEY (`trieda_id`) REFERENCES `triedy` (`id`) ON DELETE CASCADE,
  CONSTRAINT `zadania_ibfk_2` FOREIGN KEY (`predmet_id`) REFERENCES `predmety` (`id`) ON DELETE CASCADE,
  CONSTRAINT `zadania_ibfk_3` FOREIGN KEY (`pouzivatel_id`) REFERENCES `pouzivatelia` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `zadania` (`id`, `nazov`, `trieda_id`, `pouzivatel_id`, `predmet_id`, `stav`, `cas_uzatvorenia`, `cas_vytvorenia`) VALUES
(2,	'iPod shuffle',	1,	2,	1,	0,	'2014-05-13 05:47:07',	NULL),
(3,	'Merania c.2',	2,	2,	1,	1,	'2014-05-18 02:15:56',	NULL),
(6,	'gasdf',	1,	2,	1,	2,	'2014-05-14 04:25:00',	NULL),
(7,	'som kikos',	1,	2,	1,	2,	'2014-05-14 04:25:00',	NULL),
(8,	'gasdf',	1,	2,	1,	NULL,	'2014-05-14 04:27:00',	NULL),
(9,	'2345245',	1,	2,	1,	NULL,	'2014-05-14 04:27:00',	NULL),
(10,	'gadf',	1,	2,	1,	1,	'2014-05-14 04:31:00',	'2014-05-14 04:31:10'),
(11,	'xcvnxcvbc',	1,	2,	1,	1,	'2014-05-14 04:31:00',	'2014-05-14 04:31:39'),
(12,	'dfadf2123',	1,	2,	1,	1,	'2014-05-14 04:32:00',	'2014-05-14 04:32:30'),
(13,	'12412',	1,	2,	1,	1,	'2014-05-14 04:32:00',	'2014-05-14 04:32:34');

-- 2014-05-14 05:15:14