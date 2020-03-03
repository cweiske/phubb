-- MySQL dump 10.13  Distrib 5.5.41, for debian-linux-gnu (x86_64)
--
-- Host: 127.0.0.1    Database: phubb
-- ------------------------------------------------------
-- Server version	5.5.41-0ubuntu0.14.04.1

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `pingrequests`
--

DROP TABLE IF EXISTS `pingrequests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pingrequests` (
  `pr_id` int(11) NOT NULL AUTO_INCREMENT,
  `pr_created` datetime NOT NULL,
  `pr_updated` datetime NOT NULL,
  `pr_url` varchar(8192) NOT NULL,
  `pr_subscribers` int(11) NOT NULL DEFAULT '0',
  `pr_ping_ok` int(11) NOT NULL DEFAULT '0',
  `pr_ping_reping` int(11) NOT NULL DEFAULT '0',
  `pr_ping_error` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`pr_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `repings`
--

DROP TABLE IF EXISTS `repings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `repings` (
  `rp_id` int(11) NOT NULL AUTO_INCREMENT,
  `rp_pr_id` int(11) NOT NULL,
  `rp_sub_id` int(11) NOT NULL,
  `rp_created` datetime NOT NULL,
  `rp_updated` datetime NOT NULL,
  `rp_iteration` int(11) NOT NULL DEFAULT '0',
  `rp_scheduled` int(11) NOT NULL,
  `rp_next_try` datetime NOT NULL,
  `rp_last_error` varchar(256) NOT NULL,
  PRIMARY KEY (`rp_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `subscriptions`
--

DROP TABLE IF EXISTS `subscriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `subscriptions` (
  `sub_id` int(11) NOT NULL AUTO_INCREMENT,
  `sub_created` datetime NOT NULL,
  `sub_updated` datetime NOT NULL,
  `sub_callback` varchar(8192) NOT NULL,
  `sub_topic` varchar(8192) NOT NULL,
  `sub_lease_seconds` int(11) NOT NULL,
  `sub_lease_end` datetime NOT NULL,
  `sub_secret` varchar(512) NOT NULL,
  `sub_ping_ok` int(11) NOT NULL DEFAULT '0',
  `sub_ping_error` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`sub_id`),
  UNIQUE KEY `req_id` (`sub_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Subscription requests';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `topics`
--

DROP TABLE IF EXISTS `topics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `topics` (
  `t_id` int(11) NOT NULL AUTO_INCREMENT,
  `t_updated` datetime NOT NULL,
  `t_url` varchar(8192) NOT NULL,
  `t_subscriber` int(11) NOT NULL DEFAULT '0',
  `t_change_date` datetime NOT NULL,
  `t_content_md5` varchar(32) NOT NULL,
  `t_etag` varchar(32) NOT NULL,
  PRIMARY KEY (`t_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2015-04-08 21:42:58
