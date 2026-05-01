-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: offline_lms_db
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
-- Table structure for table `notification_admin`
--

DROP TABLE IF EXISTS `notification_admin`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notification_admin` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` varchar(50) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notification_admin`
--

LOCK TABLES `notification_admin` WRITE;
/*!40000 ALTER TABLE `notification_admin` DISABLE KEYS */;
INSERT INTO `notification_admin` VALUES (1,'instructor_signup',NULL,'New instructor application: Beimnet (bemnet@gmail.com)',0,'2026-04-18 12:35:52');
/*!40000 ALTER TABLE `notification_admin` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admins`
--

DROP TABLE IF EXISTS `admins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('main','secondary') DEFAULT 'secondary',
  `theme_mode` varchar(20) DEFAULT 'dark',
  `notifications_enabled` tinyint(1) DEFAULT 1,
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admins`
--

LOCK TABLES `admins` WRITE;
/*!40000 ALTER TABLE `admins` DISABLE KEYS */;
INSERT INTO `admins` VALUES (2,'Ferdos',NULL,'ferdousmussa16@gmail.com','$2y$10$.r8GvRnffC.EhUQM97Egxuqt.lYtHj6qZQPwNu3UqU7TjUEuua/Iy','main','dark',1,'2026-04-18 09:54:05','2026-04-17 09:38:26'),(3,'sebrin',NULL,'sebrin@gmail.com','$2y$10$UzzzVj46k2.xO6hOmXSfEeMZylHNjR4nDYtsHTeBV0w.GCSgrTDZS','secondary','dark',1,'2026-04-18 12:33:57','2026-04-17 09:41:29');
/*!40000 ALTER TABLE `admins` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `assignments`
--

DROP TABLE IF EXISTS `assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `assignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `course_id` int(11) NOT NULL,
  `module_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `due_date` datetime DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `course_id` (`course_id`),
  KEY `module_id` (`module_id`),
  CONSTRAINT `assignments_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `content_courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `assignments_ibfk_2` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `assignments`
--

LOCK TABLES `assignments` WRITE;
/*!40000 ALTER TABLE `assignments` DISABLE KEYS */;
/*!40000 ALTER TABLE `assignments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `content_creators`
--

DROP TABLE IF EXISTS `content_creators`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `content_creators` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `bio` text DEFAULT NULL,
  `linkedin_link` varchar(255) DEFAULT NULL,
  `github_link` varchar(255) DEFAULT NULL,
  `portfolio_link` varchar(255) DEFAULT NULL,
  `is_approved` tinyint(1) DEFAULT 0 COMMENT '0=Pending, 1=Approved',
  `login_attempts` int(11) DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `recovery_token` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `recovery_token_expires` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `content_creators`
--

LOCK TABLES `content_creators` WRITE;
/*!40000 ALTER TABLE `content_creators` DISABLE KEYS */;
INSERT INTO `content_creators` VALUES (2,'Pending Bob','bob@gmail.com','123456789!','New instructor waiting for approval.',NULL,NULL,NULL,0,5,'2026-04-17 11:22:06',NULL,'2026-04-15 11:51:17',NULL),(3,'sebrin','sebrin@gmail.com','$2y$10$0jAvYBO8A8h5CklomMbdbuk8309OfONQqy0A5ho8svZq5vnIdfqz2',NULL,NULL,NULL,NULL,1,0,NULL,NULL,'2026-04-15 12:21:03',NULL),(4,'Semu','semu@gmail.com','$2y$10$ckycOY4Qn8QgPajfmOs5aOQVs2ael9j0y6Jiw1TCQDSSyEvFHkBPq',NULL,NULL,NULL,NULL,1,0,NULL,NULL,'2026-04-16 13:25:02',NULL),(5,'Beimnet','bemnet@gmail.com','$2y$10$bgXwWFUkLYaODFxWz78rteoBcFZTXcemvZYWk0crUUxT/FW2ZuEUK',NULL,'https://linkedin.com/in/bemnet','https://github.com/bemnt','',1,0,NULL,NULL,'2026-04-18 09:35:52',NULL);
/*!40000 ALTER TABLE `content_creators` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `content_course_feedback`
--

DROP TABLE IF EXISTS `content_course_feedback`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `content_course_feedback` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `course_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `reply` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `course_id` (`course_id`),
  KEY `student_id` (`student_id`),
  CONSTRAINT `content_course_feedback_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `content_courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `content_course_feedback_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `content_course_feedback`
--

LOCK TABLES `content_course_feedback` WRITE;
/*!40000 ALTER TABLE `content_course_feedback` DISABLE KEYS */;
/*!40000 ALTER TABLE `content_course_feedback` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `content_courses`
--

DROP TABLE IF EXISTS `content_courses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `content_courses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `creator_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `level` enum('Beginner','Intermediate','Advanced') DEFAULT NULL,
  `thumbnail_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_deleted` tinyint(1) DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `tags` text DEFAULT NULL,
  `is_resource` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `fk_content_courses_creators_new` (`creator_id`),
  CONSTRAINT `fk_content_courses_creators_new` FOREIGN KEY (`creator_id`) REFERENCES `content_creators` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `content_courses`
--

LOCK TABLES `content_courses` WRITE;
/*!40000 ALTER TABLE `content_courses` DISABLE KEYS */;
INSERT INTO `content_courses` VALUES (4,3,'UI/UX design','Design Website',NULL,NULL,NULL,'2026-04-15 12:31:03',0,NULL,NULL,0),(5,4,'Java','Coding','Programming','Advanced','uploads/thumbnails/thumb_69e0e8aa7732b.png','2026-04-16 13:48:26',0,NULL,NULL,0);
/*!40000 ALTER TABLE `content_courses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `enrollments`
--

DROP TABLE IF EXISTS `enrollments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `enrollments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `enrolled_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_enrollment` (`student_id`,`course_id`),
  KEY `course_id` (`course_id`),
  CONSTRAINT `enrollments_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `enrollments_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `content_courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `enrollments`
--

LOCK TABLES `enrollments` WRITE;
/*!40000 ALTER TABLE `enrollments` DISABLE KEYS */;
INSERT INTO `enrollments` VALUES (3,3,4,'2026-04-17 09:10:11'),(4,3,5,'2026-04-17 09:10:11'),(5,2,4,'2026-04-17 09:10:11'),(6,2,5,'2026-04-17 09:10:11'),(7,4,5,'2026-04-17 09:10:11'),(11,4,4,'2026-04-17 09:36:56');
/*!40000 ALTER TABLE `enrollments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `progress_lesson_progress`
--

DROP TABLE IF EXISTS `progress_lesson_progress`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `progress_lesson_progress` (
  `student_id` int(11) NOT NULL,
  `lesson_id` int(11) NOT NULL,
  `status` enum('started','completed') DEFAULT 'started',
  `last_sync` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`student_id`,`lesson_id`),
  KEY `lesson_id` (`lesson_id`),
  CONSTRAINT `fk_lp_students_new` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `progress_lesson_progress_ibfk_2` FOREIGN KEY (`lesson_id`) REFERENCES `progress_lessons` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `progress_lesson_progress`
--

LOCK TABLES `progress_lesson_progress` WRITE;
/*!40000 ALTER TABLE `progress_lesson_progress` DISABLE KEYS */;
/*!40000 ALTER TABLE `progress_lesson_progress` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `progress_lessons`
--

DROP TABLE IF EXISTS `progress_lessons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `progress_lessons` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `course_id` int(11) NOT NULL,
  `module_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `type` enum('video','pdf','text') DEFAULT 'video',
  `content` text DEFAULT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_size` varchar(50) DEFAULT NULL,
  `order_number` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `course_id` (`course_id`),
  KEY `module_id` (`module_id`),
  CONSTRAINT `progress_lessons_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `content_courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `progress_lessons_ibfk_2` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `progress_lessons`
--

LOCK TABLES `progress_lessons` WRITE;
/*!40000 ALTER TABLE `progress_lessons` DISABLE KEYS */;
INSERT INTO `progress_lessons` VALUES (3,5,NULL,'lesson 1','pdf','','uploads/pdfs/pdf_69e1e7421ea31.pdf','896 KB',1,'2026-04-17 07:54:42');
/*!40000 ALTER TABLE `progress_lessons` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `modules`
--

DROP TABLE IF EXISTS `modules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `modules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `course_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `order_number` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `course_id` (`course_id`),
  CONSTRAINT `modules_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `content_courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `modules`
--

LOCK TABLES `modules` WRITE;
/*!40000 ALTER TABLE `modules` DISABLE KEYS */;
/*!40000 ALTER TABLE `modules` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `content_resources`
--

DROP TABLE IF EXISTS `content_resources`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `content_resources` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `creator_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_type` varchar(50) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_deleted` tinyint(1) DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_content_resources_creators_new` (`creator_id`),
  CONSTRAINT `fk_content_resources_creators_new` FOREIGN KEY (`creator_id`) REFERENCES `content_creators` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `content_resources`
--

LOCK TABLES `content_resources` WRITE;
/*!40000 ALTER TABLE `content_resources` DISABLE KEYS */;
INSERT INTO `content_resources` VALUES (1,3,'IT Managment','',NULL,'Programming','2026-04-17 07:37:57',0,NULL);
/*!40000 ALTER TABLE `content_resources` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rewards`
--

DROP TABLE IF EXISTS `rewards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rewards` (
  `student_id` int(11) NOT NULL,
  `points` int(11) DEFAULT 0,
  `badges` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`badges`)),
  PRIMARY KEY (`student_id`),
  CONSTRAINT `fk_rewards_students_new` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rewards`
--

LOCK TABLES `rewards` WRITE;
/*!40000 ALTER TABLE `rewards` DISABLE KEYS */;
INSERT INTO `rewards` VALUES (2,0,'[]'),(3,0,'[]'),(4,0,'[]'),(5,0,'[]'),(6,0,'[]'),(7,0,'[]'),(8,0,'[]');
/*!40000 ALTER TABLE `rewards` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notification_student`
--

DROP TABLE IF EXISTS `notification_student`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notification_student` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) DEFAULT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  CONSTRAINT `notification_student_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notification_student`
--

LOCK TABLES `notification_student` WRITE;
/*!40000 ALTER TABLE `notification_student` DISABLE KEYS */;
/*!40000 ALTER TABLE `notification_student` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `students`
--

DROP TABLE IF EXISTS `students`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `students` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `last_login` datetime DEFAULT NULL,
  `total_time_spent` int(11) DEFAULT 0,
  `current_field` varchar(100) DEFAULT NULL,
  `login_attempts` int(11) DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `recovery_token` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `recovery_token_expires` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `students`
--

LOCK TABLES `students` WRITE;
/*!40000 ALTER TABLE `students` DISABLE KEYS */;
INSERT INTO `students` VALUES (2,'ferdos','ferdos@gmail.com','$2y$10$Ft/rlvppwkcJVH8aLIcuweU/zAEz59hmbNFEYbalyDobcOrLjJljO',NULL,0,'Undecided',0,NULL,NULL,'2026-04-15 12:35:30',NULL),(3,'elham','elham@gmail.com','$2y$10$3uvDKWJfHc0t53XuiwQhmOgJjINvugSnhraYe4AY2LoWdl.kwxNXG',NULL,0,'Undecided',0,NULL,NULL,'2026-04-15 13:38:03',NULL),(4,'ahlam helil','sebrinmahmud@gmail.com','$2y$10$sonz6panwUIQDav9J.7Uc.CT5LGIhJNuUG14m.N7f/3u08GnT1M2u','2026-04-18 09:55:37',2,'IS',0,NULL,NULL,'2026-04-16 10:37:54',NULL),(5,'Sitra','sitra@gmail.com','$2y$10$6YZKoWf6ybX8S0aEU1bnJeTaDdM..l9yMEXagBLay6ntN.k3GDDZK',NULL,0,'Undecided',0,NULL,NULL,'2026-04-17 10:59:34',NULL),(6,'mira','mira@gmail.com','$2y$10$Y8HIzeeQeueYUuxPiPzq2uibOwnyHEHQ67RVV7Ix.VPTB8dczC.3a','2026-04-18 10:20:33',0,'Undecided',0,NULL,NULL,'2026-04-18 07:20:15',NULL),(7,'eman','eman@gmail.com','$2y$10$79FJQuIomInqJqF8rH9fsOhnif31djGV57J1JMXHkqR3vqTJh/y0G','2026-04-18 11:05:39',1,'Undecided',0,NULL,NULL,'2026-04-18 08:05:19',NULL);
/*!40000 ALTER TABLE `students` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `system_logs`
--

DROP TABLE IF EXISTS `system_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `system_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_type` varchar(50) NOT NULL COMMENT 'login, sync, upload, delete, approve',
  `user_id` int(11) DEFAULT NULL,
  `target_id` int(11) DEFAULT NULL,
  `target_type` varchar(50) DEFAULT NULL,
  `description` text NOT NULL,
  `user_role` varchar(50) DEFAULT 'system',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system_logs`
--

LOCK TABLES `system_logs` WRITE;
/*!40000 ALTER TABLE `system_logs` DISABLE KEYS */;
INSERT INTO `system_logs` VALUES (1,'system',NULL,NULL,NULL,'Logging system initialized','admin','2026-04-17 12:15:39'),(2,'system',NULL,NULL,NULL,'Database schema updated to v1.2','admin','2026-04-17 12:15:39'),(3,'login',4,NULL,NULL,'User (semu@gmail.com) logged into the platform.','instructor','2026-04-17 13:04:31'),(4,'login',4,NULL,NULL,'User (semu@gmail.com) logged into the platform.','instructor','2026-04-17 13:06:41'),(5,'login',4,NULL,NULL,'User (sebrinmahmud@gmail.com) logged into the platform.','student','2026-04-17 13:26:57'),(6,'login',4,NULL,NULL,'User (semu@gmail.com) logged into the platform.','instructor','2026-04-17 13:32:32'),(7,'login',4,NULL,NULL,'User (sebrinmahmud@gmail.com) logged into the platform.','student','2026-04-18 06:55:37'),(8,'login',6,NULL,NULL,'User (mira@gmail.com) logged into the platform.','student','2026-04-18 07:20:33'),(9,'login',7,NULL,NULL,'User (eman@gmail.com) logged into the platform.','student','2026-04-18 08:05:39'),(10,'login',4,NULL,NULL,'User (semu@gmail.com) logged into the platform.','instructor','2026-04-18 08:12:59'),(11,'approve_creator',3,5,'creator','Admin approved instructor ID: 5','admin','2026-04-18 09:36:57'),(12,'login',5,NULL,NULL,'User (bemnet@gmail.com) logged into the platform.','instructor','2026-04-18 09:37:22');
/*!40000 ALTER TABLE `system_logs` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-04-18 14:58:04
