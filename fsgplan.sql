-- Adminer 3.6.3 MySQL dump

SET NAMES utf8;
SET foreign_key_checks = 0;
SET time_zone = 'SYSTEM';
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

CREATE TABLE `fsgapi_changes` (
  `id` int(11) NOT NULL auto_increment,
  `class` varchar(5) NOT NULL,
  `time` int(11) NOT NULL,
  `lesson` int(11) NOT NULL,
  `teacher` varchar(64) NOT NULL,
  `course` varchar(20) character set utf8 collate utf8_bin NOT NULL,
  `room` varchar(255) NOT NULL,
  `type` varchar(255) NOT NULL,
  `from` varchar(255) NOT NULL,
  `to` varchar(255) NOT NULL,
  `comment` varchar(255) NOT NULL,
  `found` int(11) NOT NULL,
  `updated` int(11) NOT NULL,
  `replaced` int(11) NOT NULL,
  PRIMARY KEY  (`class`,`time`,`lesson`,`room`),
  UNIQUE KEY `id` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `fsgapi_config` (
  `name` varchar(255) NOT NULL,
  `data` varchar(255) NOT NULL,
  PRIMARY KEY  (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `fsgplan_admin` (
  `uid` varchar(255) NOT NULL,
  UNIQUE KEY `uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `fsgplan_holiday` (
  `id` int(11) NOT NULL auto_increment,
  `name` varchar(255) NOT NULL,
  `from` int(11) NOT NULL,
  `until` int(11) NOT NULL,
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `fsgplan_request` (
  `id` varchar(255) NOT NULL,
  `user` varchar(255) NOT NULL,
  `expires` varchar(255) NOT NULL,
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `fsgplan_user` (
  `id` varchar(255) NOT NULL,
  `class` varchar(255) NOT NULL,
  `courses` varchar(255) NOT NULL,
  `update` int(11) NOT NULL,
  `installed` tinyint(1) NOT NULL default '0',
  `notify` tinyint(1) NOT NULL default '1',
  `finished` tinyint(1) NOT NULL default '0',
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


-- 2013-05-01 01:08:34
