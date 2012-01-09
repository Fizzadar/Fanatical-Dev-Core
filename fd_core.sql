# ************************************************************
# Sequel Pro SQL dump
# Version 3408
#
# http://www.sequelpro.com/
# http://code.google.com/p/sequel-pro/
#
# Host: localhost (MySQL 5.5.9)
# Database: browserwards
# Generation Time: 2012-01-09 02:09:42 +0000
# ************************************************************


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


# Dump of table core_user
# ------------------------------------------------------------

CREATE TABLE `core_user` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `group` tinyint(3) unsigned NOT NULL DEFAULT '1' COMMENT '0=admin;1=user;1>=custom',
  `registration_time` int(10) unsigned NOT NULL,
  `login_time` int(10) unsigned NOT NULL,
  `name` varchar(30) NOT NULL DEFAULT '',
  `auth_key` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;



# Dump of table core_user_groups
# ------------------------------------------------------------

CREATE TABLE `core_user_groups` (
  `id` tinyint(3) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;



# Dump of table core_user_oauths
# ------------------------------------------------------------

CREATE TABLE `core_user_oauths` (
  `user_id` int(10) unsigned NOT NULL,
  `provider` varchar(255) NOT NULL DEFAULT '',
  `o_id` int(10) NOT NULL,
  `token` varchar(255) NOT NULL DEFAULT '',
  `secret` varchar(255) NOT NULL DEFAULT '',
  UNIQUE KEY `provider` (`provider`,`o_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;



# Dump of table core_user_openids
# ------------------------------------------------------------

CREATE TABLE `core_user_openids` (
  `user_id` int(10) unsigned NOT NULL,
  `open_id` varchar(255) NOT NULL,
  UNIQUE KEY `open_id` (`open_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;



# Dump of table core_user_permissions
# ------------------------------------------------------------

CREATE TABLE `core_user_permissions` (
  `group_id` tinyint(3) unsigned NOT NULL,
  `permission` varchar(255) NOT NULL,
  UNIQUE KEY `group_id` (`group_id`,`permission`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;




/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
