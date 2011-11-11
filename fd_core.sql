-- phpMyAdmin SQL Dump
-- version 3.4.5
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Generation Time: Nov 12, 2011 at 01:29 AM
-- Server version: 5.1.47
-- PHP Version: 5.3.2

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Database: `newsburnin`
--

-- --------------------------------------------------------

--
-- Table structure for table `core_user`
--

CREATE TABLE IF NOT EXISTS `core_user` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `group` tinyint(3) unsigned NOT NULL DEFAULT '1' COMMENT '0=admin;1=user;1>=custom',
  `registration_time` int(10) unsigned NOT NULL,
  `login_time` int(10) unsigned NOT NULL,
  `name` varchar(30) NOT NULL DEFAULT '',
  `auth_key` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=9 ;

-- --------------------------------------------------------

--
-- Table structure for table `core_user_groups`
--

CREATE TABLE IF NOT EXISTS `core_user_groups` (
  `id` tinyint(3) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `core_user_openids`
--

CREATE TABLE IF NOT EXISTS `core_user_openids` (
  `user_id` int(10) unsigned NOT NULL,
  `open_id` varchar(255) NOT NULL,
  UNIQUE KEY `unique` (`user_id`,`open_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `core_user_permissions`
--

CREATE TABLE IF NOT EXISTS `core_user_permissions` (
  `group_id` tinyint(3) unsigned NOT NULL,
  `permission` varchar(255) NOT NULL,
  UNIQUE KEY `group_id` (`group_id`,`permission`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
