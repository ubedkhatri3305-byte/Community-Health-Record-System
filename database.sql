-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: chr_db
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

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
-- Current Database: `chr_db`
--

/*!40000 DROP DATABASE IF EXISTS `chr_db`*/;

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `chr_db` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci */;

USE `chr_db`;

--
-- Table structure for table `appointments`
--

DROP TABLE IF EXISTS `appointments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `appointments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) DEFAULT NULL,
  `doctor_id` int(11) DEFAULT NULL,
  `appt_date` date DEFAULT NULL,
  `appt_time` time DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `status` enum('pending','accepted','rejected','rescheduled') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reschedule_note` text DEFAULT NULL,
  `rescheduled_date` date DEFAULT NULL,
  `rescheduled_time` time DEFAULT NULL,
  `reschedule_msg` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `appointments`
--

LOCK TABLES `appointments` WRITE;
/*!40000 ALTER TABLE `appointments` DISABLE KEYS */;
INSERT INTO `appointments` VALUES (1,1,2,'2025-12-27','16:14:00','','accepted','2025-12-19 10:44:13',NULL,NULL,NULL,NULL),(2,1,2,'2025-12-26','14:12:00','','accepted','2025-12-20 08:41:19',NULL,NULL,NULL,NULL),(3,1,2,'2025-12-26','15:19:00','','accepted','2025-12-20 08:49:29',NULL,NULL,NULL,NULL),(4,1,4,'2025-12-27','16:23:00','','accepted','2025-12-21 10:49:50',NULL,NULL,NULL,NULL),(5,1,5,'2026-01-01','14:40:00','','','2025-12-21 12:01:05',NULL,NULL,NULL,NULL),(6,1,5,'2026-01-07','19:39:00','','rejected','2025-12-21 12:09:50',NULL,NULL,NULL,NULL),(7,1,6,'2026-01-01','15:34:00','','pending','2025-12-22 10:02:59',NULL,NULL,NULL,NULL),(8,1,4,'2026-01-01','15:45:00','','','2025-12-22 10:13:16',NULL,NULL,NULL,NULL),(9,1,4,'2026-01-01','16:05:00','','accepted','2025-12-22 10:32:15',NULL,NULL,NULL,NULL),(10,1,4,'2025-12-30','19:45:00','','accepted','2025-12-28 14:12:12',NULL,NULL,NULL,NULL),(11,1,4,'2025-12-31','11:06:00','','accepted','2025-12-29 05:32:35',NULL,NULL,NULL,NULL),(12,1,4,'2026-01-01','00:00:00','','accepted','2025-12-29 07:00:12',NULL,NULL,NULL,NULL),(13,1,4,'2026-01-24','00:00:00','','accepted','2026-01-23 05:56:10',NULL,NULL,NULL,NULL),(14,1,7,'2026-01-24','13:30:00','','rejected','2026-01-24 07:58:12',NULL,NULL,NULL,NULL),(15,1,8,'2026-01-24','14:09:00','','pending','2026-01-24 09:39:57',NULL,NULL,NULL,NULL),(16,1,7,'2026-01-24','00:00:00','','accepted','2026-01-24 10:01:21',NULL,NULL,NULL,NULL),(17,1,7,'2026-01-25','00:00:00','','accepted','2026-01-24 10:40:18',NULL,NULL,NULL,NULL),(19,1,7,'2026-01-24',NULL,'','pending','2026-01-24 10:52:22',NULL,NULL,NULL,NULL),(20,1,7,'2026-01-24',NULL,'','pending','2026-01-24 10:54:42',NULL,NULL,NULL,NULL),(23,1,7,'2026-01-24','00:00:00','','pending','2026-01-24 11:01:02',NULL,NULL,NULL,NULL),(24,1,7,'2026-01-25','00:00:00','','pending','2026-01-24 11:01:16',NULL,NULL,NULL,NULL),(25,10,14,'2026-04-20','13:45:00','','rejected','2026-04-19 08:15:42',NULL,'2026-04-07','00:20:00','Doctor rescheduled your appointment'),(27,10,14,'2026-04-21','18:33:00','','rejected','2026-04-19 10:03:39',NULL,NULL,NULL,NULL),(28,10,14,'2026-04-21','22:48:00','','accepted','2026-04-19 17:02:43',NULL,NULL,NULL,NULL),(29,10,14,'2026-04-21','22:35:00','','accepted','2026-04-19 17:07:53',NULL,NULL,NULL,NULL),(30,10,14,'2026-04-21','22:41:00','','accepted','2026-04-19 17:08:55',NULL,NULL,NULL,NULL),(31,10,14,'2026-04-16','20:27:00','','accepted','2026-04-23 12:57:49',NULL,'2026-04-24','18:30:00','Doctor rescheduled your appointment'),(32,10,14,'2026-04-26','18:23:00','','accepted','2026-04-26 12:53:15',NULL,NULL,NULL,NULL);
/*!40000 ALTER TABLE `appointments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `doctor_leaves`
--

DROP TABLE IF EXISTS `doctor_leaves`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `doctor_leaves` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `doctor_id` int(11) NOT NULL,
  `leave_date` date NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `doctor_leaves`
--

LOCK TABLES `doctor_leaves` WRITE;
/*!40000 ALTER TABLE `doctor_leaves` DISABLE KEYS */;
INSERT INTO `doctor_leaves` VALUES (3,7,'2026-01-26','','2026-01-24 10:23:01'),(4,7,'2026-01-26','','2026-01-24 10:25:02'),(5,13,'2026-04-18','abc','2026-04-18 18:38:35'),(6,14,'2026-04-22','','2026-04-19 10:04:33');
/*!40000 ALTER TABLE `doctor_leaves` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `doctor_profiles`
--

DROP TABLE IF EXISTS `doctor_profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `doctor_profiles` (
  `doctor_id` int(11) NOT NULL,
  `qualification` varchar(100) DEFAULT NULL,
  `experience` int(11) DEFAULT NULL,
  `specialization` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  PRIMARY KEY (`doctor_id`),
  CONSTRAINT `doctor_profiles_ibfk_1` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `doctor_profiles`
--

LOCK TABLES `doctor_profiles` WRITE;
/*!40000 ALTER TABLE `doctor_profiles` DISABLE KEYS */;
INSERT INTO `doctor_profiles` VALUES (2,'mbbs',3,'md',NULL),(4,'mbbs',3,'md',NULL),(5,'mbbs',3,'md',NULL),(6,'mbbs',3,'md',NULL),(7,'mbbs',3,'md',NULL),(8,'mbbs',3,'md',NULL),(11,'mbbs',3,'md',NULL),(12,'mbbs',3,'md',NULL),(13,'mbbs',3,'md',NULL),(14,'mbbs',NULL,'md','');
/*!40000 ALTER TABLE `doctor_profiles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hospitals`
--

DROP TABLE IF EXISTS `hospitals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hospitals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `address` text DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `open_time` time NOT NULL,
  `close_time` time NOT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `type` enum('hospital','laboratory') NOT NULL DEFAULT 'hospital',
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `city` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hospitals`
--

LOCK TABLES `hospitals` WRITE;
/*!40000 ALTER TABLE `hospitals` DISABLE KEYS */;
INSERT INTO `hospitals` VALUES (1,'kk','',NULL,'00:00:00','00:00:00',22.28551680,70.78215680,'2025-12-21 10:32:14','hospital','','',NULL),(2,'leva','',NULL,'00:00:00','00:00:00',22.28551680,70.78215680,'2025-12-21 10:51:45','hospital','','',NULL),(3,'khatri hospital','bhujpur',NULL,'00:00:00','00:00:00',22.28551680,70.78871040,'2025-12-28 14:15:44','hospital','','',NULL),(4,'cv hospital','bhuj',NULL,'00:00:00','00:00:00',23.49514560,73.29937680,'2025-12-29 05:31:07','hospital','','',NULL),(5,'kkkk','bhuj',NULL,'00:00:00','00:00:00',23.45638580,73.29195760,'2026-01-23 05:55:04','hospital','','',NULL),(6,'xyz','mundra',NULL,'09:01:00','17:07:00',23.45631290,73.29188520,'2026-01-24 07:37:26','hospital','','',NULL),(7,'pqr','bhuj',NULL,'09:00:00','09:00:00',23.45610941,73.29165155,'2026-01-25 06:42:49','hospital','','',NULL),(8,'xyzz','Modasa, Gujarat, India','+91 12235466','11:00:00','23:00:00',NULL,NULL,'2026-04-09 07:06:04','hospital','ubedkhatri2608@gmail.com','$2y$10$uImn3V3dAEhn/USzVt7e9eTg26wTLwClLaLQMVv4okCtkDudrwB8C','modasa'),(9,'abcd','Modasa, Gujarat, India','+91 9898270987','09:00:00','12:10:00',NULL,NULL,'2026-04-18 17:39:22','hospital','abcd@hospital.com','$2y$10$aj3p72o7Q5PAO3TMLh8fmuyUmTd/3OCgeifsaWtl7CruvmmJkVPmm','modasa'),(10,'lab','Modasa, Gujarat, India','+91 12235466','12:57:00','12:56:00',NULL,NULL,'2026-04-19 07:27:16','laboratory','lab@gmail.com','$2y$10$gn2cO3w4VjEsuBy1qIOr6ukjpG.cUlMmKwhvLlL7XD1X9S.6V8b7S','modasa');
/*!40000 ALTER TABLE `hospitals` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lab_appointments`
--

DROP TABLE IF EXISTS `lab_appointments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lab_appointments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) DEFAULT NULL,
  `lab_id` int(11) DEFAULT NULL,
  `test_name` varchar(100) DEFAULT NULL,
  `appointment_date` date DEFAULT NULL,
  `status` enum('pending','accepted','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lab_appointments`
--

LOCK TABLES `lab_appointments` WRITE;
/*!40000 ALTER TABLE `lab_appointments` DISABLE KEYS */;
INSERT INTO `lab_appointments` VALUES (1,10,10,'bloood test','2026-04-19','accepted','2026-04-19 07:32:00'),(2,10,10,'bloood test','2026-04-22','accepted','2026-04-20 16:52:45'),(3,10,10,'bloood test','2026-04-21','accepted','2026-04-20 16:59:42'),(4,10,10,'bloood test','2026-04-26','accepted','2026-04-26 11:48:30'),(5,10,10,'bloood test','2026-04-26','accepted','2026-04-26 12:54:20');
/*!40000 ALTER TABLE `lab_appointments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lab_reports`
--

DROP TABLE IF EXISTS `lab_reports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lab_reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `appointment_id` int(11) DEFAULT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `lab_id` int(11) DEFAULT NULL,
  `report_file` varchar(255) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lab_reports`
--

LOCK TABLES `lab_reports` WRITE;
/*!40000 ALTER TABLE `lab_reports` DISABLE KEYS */;
INSERT INTO `lab_reports` VALUES (3,4,10,10,'Screenshot 2026-04-26 150854.png','2026-04-26 11:54:31'),(4,5,10,10,'Screenshot 2026-04-26 131221.png','2026-04-26 12:56:48');
/*!40000 ALTER TABLE `lab_reports` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `seen` tinyint(4) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES (1,1,'Your appointment has been accepted',0,'2025-12-19 10:44:34'),(2,1,'Your appointment has been accepted',0,'2025-12-20 08:42:40'),(3,1,'Your appointment has been accepted',0,'2025-12-20 08:50:13'),(4,1,'Your appointment has been accepted',0,'2025-12-21 10:50:30'),(5,1,'Your appointment has been rescheduled to 2026-01-01 at 14:40',0,'2025-12-21 12:10:56'),(6,1,'Your appointment has been rejected',0,'2025-12-21 12:11:01'),(7,1,'Your appointment has been rescheduled to 2026-01-01 at 15:45',0,'2025-12-22 10:14:34'),(8,1,'Your appointment has been rescheduled to 2026-01-01 at 16:05',0,'2025-12-22 10:33:06'),(9,1,'Your appointment has been accepted',0,'2025-12-22 10:35:44'),(10,1,'Your appointment has been accepted',0,'2025-12-28 14:13:28'),(11,1,'Your appointment has been accepted',0,'2025-12-29 05:33:42'),(12,1,'Your appointment has been accepted',0,'2025-12-29 07:00:57'),(13,1,'Your appointment has been accepted',0,'2026-01-23 05:57:12'),(14,1,'Your appointment has been accepted',0,'2026-01-24 10:22:25'),(15,1,'Your appointment has been rejected',0,'2026-01-24 10:22:39'),(16,1,'Your appointment has been accepted',0,'2026-01-24 10:40:51'),(17,10,'Your appointment has been accepted',0,'2026-04-19 08:16:43'),(18,10,'Your appointment has been accepted',0,'2026-04-19 09:59:42'),(19,10,'Your appointment has been accepted',0,'2026-04-19 10:04:10'),(20,10,'Your appointment has been accepted',0,'2026-04-20 17:16:49'),(21,10,'Your appointment has been accepted',0,'2026-04-20 17:16:51'),(22,10,'Your appointment has been accepted',0,'2026-04-20 17:16:53'),(23,10,'Your appointment has been rejected',0,'2026-04-20 17:17:29'),(24,10,'Your appointment has been rescheduled',0,'2026-04-20 17:17:46'),(25,10,'Your appointment has been rescheduled',0,'2026-04-20 17:17:52'),(26,10,'Your appointment has been rescheduled',0,'2026-04-20 17:17:59'),(27,10,'Your appointment has been accepted',0,'2026-04-20 17:18:00'),(28,10,'Your appointment has been rescheduled to 2026-04-21 at 22:54',0,'2026-04-20 17:24:21'),(29,10,'Doctor rescheduled your appointment to 2026-04-26 at 23:00',0,'2026-04-20 17:30:06'),(30,10,'Your appointment was rescheduled to 2026-04-22 at 23:19',0,'2026-04-21 16:49:33'),(31,10,'Your appointment was rescheduled to 2026-05-02 at 22:21',0,'2026-04-21 16:49:56'),(32,10,'Your appointment was rescheduled to 2026-04-07 at 00:20',0,'2026-04-21 16:50:18'),(33,10,'Your appointment has been rejected',0,'2026-04-21 16:55:37'),(34,10,'Your appointment has been accepted by your doctor.',0,'2026-04-23 12:58:54'),(35,10,'Your appointment has been rescheduled to 2026-04-24 at 18:30 by your doctor.',0,'2026-04-23 12:59:06'),(36,10,'Your appointment has been accepted by your doctor.',0,'2026-04-23 12:59:29'),(37,10,'Your lab appointment for bloood test has been accepted by the laboratory.',0,'2026-04-26 11:58:39'),(38,10,'Your appointment has been accepted by your doctor.',0,'2026-04-26 12:55:10'),(39,10,'Your lab appointment for bloood test has been accepted by the laboratory.',0,'2026-04-26 12:56:36');
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `records`
--

DROP TABLE IF EXISTS `records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `records` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `record_date` date DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `records_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `records`
--

LOCK TABLES `records` WRITE;
/*!40000 ALTER TABLE `records` DISABLE KEYS */;
INSERT INTO `records` VALUES (1,1,'report','tb','2025-12-02','uploads/1766220110_aeiou .jpg','2025-12-20 08:41:50'),(2,1,'report','tb','2025-12-03','uploads/1766220544_ideation.jpg','2025-12-20 08:49:04'),(4,10,'tb','xyz','2026-04-26','uploads/1777203983_Screenshot 2026-04-26 150854.png','2026-04-26 11:46:23');
/*!40000 ALTER TABLE `records` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_locations`
--

DROP TABLE IF EXISTS `user_locations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_locations` (
  `user_id` int(11) NOT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_locations`
--

LOCK TABLES `user_locations` WRITE;
/*!40000 ALTER TABLE `user_locations` DISABLE KEYS */;
INSERT INTO `user_locations` VALUES (1,23.45659500,73.29209100,'2026-01-24 11:01:16'),(10,23.45658966,73.29163898,'2026-04-19 10:03:39');
/*!40000 ALTER TABLE `user_locations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `role` enum('patient','doctor','admin','laboratory') DEFAULT 'patient',
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `location` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `hospital` varchar(255) DEFAULT NULL,
  `hospital_id` int(11) DEFAULT NULL,
  `specialization` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'abc','abc@gmail.com','$2y$10$7DElAXoc7rU33Hm6Je2K0usaC4Pnf2pOPDRzgSmdOsgoeB.WhtTFG','','1234567890','',NULL,'2025-12-19 10:27:58',NULL,NULL,NULL),(2,'dr.ubed','admin2@college.com','$2y$10$goAQzjGbJyyUOemVlFr0t.A.ZX26hw9oeFy1kIEqsUtoIw8Meo2CK','',NULL,NULL,'','2025-12-19 10:43:23','kk',NULL,NULL),(3,'Hospital Admin','admin@hospital.com','$2y$10$KZQ9m5nJHuj5dY9Yp0RjA.8pU.4iHDwQxX.rS7Y.0y.7tWl1Yb1i2','',NULL,NULL,NULL,'2025-12-21 04:31:01',NULL,NULL,NULL),(4,'dr.ubed','us@admin.com','$2y$10$3yNb/CAwHlN8HbUAWaRTzun/8TgqlGIqDutp/HxtEv3jyavKMOdHy','',NULL,NULL,'','2025-12-21 10:48:52',NULL,1,NULL),(5,'sami','sami@doctor.com','$2y$10$HXWta3aoeIKwWPTAkc8n.u3YiI8auNtfF8Aj/prLcQZtcUeTxF9qy','',NULL,NULL,'','2025-12-21 11:59:06',NULL,2,NULL),(6,'abcd','accd@gmail.com','$2y$10$Fs0g/YyweHyOm7CV2uxxU.3wMbKShwFdD4STrHN2MSOFTdxK30WRq','',NULL,NULL,'xyz','2025-12-22 09:59:05',NULL,1,NULL),(7,'danish','danish@gmail.com','$2y$10$e2eHS/IykRPF38dBb/od/OJvTQ8cSzPha/ZvzzbRmKij07m/Y93dO','',NULL,NULL,'mundra','2026-01-24 07:38:58',NULL,6,NULL),(8,'faiz','faiz@gmail.com','$2y$10$P6gQF6eN6KtbFcL4eGhx0ejC3WDn4Dwkn5k5RcRxoJZHF5rp/2RGe','','','mandvi','mandvi','2026-01-24 08:12:41',NULL,6,NULL),(10,'abc','abcd@gmail.com','$2y$10$9eo9LdkfIf/ggqWpAfdN7eBV.aXxQyDAPv6nPJGyOgI4MlE1Qrxgm','patient','1234567890','modasa',NULL,'2026-01-25 07:23:48',NULL,NULL,NULL),(11,'ubed','ubed3305@gmail.com','$2y$10$WMnQ6o4JwFvib5XCIjedhu1USur1Sm2zNulHtM2ExJ0svHoiTNcbS','doctor',NULL,NULL,'mundra','2026-01-25 07:25:53',NULL,6,NULL),(12,'dr.us','us@doctor.com','$2y$10$HJPnK8S7eGjy1JHPJDY5a.4B8zSwZl8ng3OnBX7qEvE9uyzFID5du','doctor',NULL,NULL,'modasa','2026-04-18 18:08:23',NULL,9,NULL),(13,'dr ubed ','ubed@doctor.com','$2y$10$ro5ZmlSJ5PO1QRSRP8XV6eCFlsRs25N5cDlOH08l2O4AKnoZzAOny','doctor',NULL,NULL,'modasa','2026-04-18 18:36:22',NULL,9,NULL),(14,'dr','dr@doctor.com','$2y$10$.S67yMSEU1wFn8hugS4.ZepdUlWYeQ/QFekszasjoUp9wRoKyJ4RK','doctor','9898270987',NULL,'modasa','2026-04-18 18:53:24',NULL,8,'md'),(15,'Test Patient','patient@test.com','$2y$10$zUTXkTeT0VMNw0LqFGuyAe/CqE8MZDyAmCdnoTLKWYLewdn2exMSC','patient','','',NULL,'2026-04-23 12:51:16',NULL,NULL,NULL);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-09-25 15:19:06
