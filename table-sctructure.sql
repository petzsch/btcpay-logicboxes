-- MySQL dump 10.19  Distrib 10.3.31-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: c0_domordergw
-- ------------------------------------------------------
-- Server version	10.3.31-MariaDB-0+deb10u1

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `btcpay_id` varchar(64) DEFAULT NULL,
  `invoice_status` varchar(32) NOT NULL DEFAULT 'P',
  `paymentTypeId` int(11) NOT NULL,
  `transId` varchar(128) NOT NULL,
  `userId` int(11) NOT NULL,
  `userType` varchar(64) NOT NULL,
  `transactionType` varchar(64) NOT NULL,
  `invoiceIds` varchar(512) DEFAULT NULL,
  `debitNoteIds` varchar(512) DEFAULT NULL,
  `description` varchar(1024) DEFAULT NULL,
  `sellingCurrencyAmount` decimal(10,3) NOT NULL,
  `accountingCurrencyAmount` decimal(10,3) NOT NULL,
  `redirectUrl` varchar(512) NOT NULL,
  `checksumOK` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `btcpay_id` (`btcpay_id`)
) ENGINE=InnoDB AUTO_INCREMENT=39 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

