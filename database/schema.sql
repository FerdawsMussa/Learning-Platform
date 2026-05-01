-- JU Learn LMS Database Schema (Unified Schema)
-- Version: 4.0
-- Notification Category: notifications (Unified)
-- Progress Category: progress_streaks, progress_lesson_progress, progress_certificates
-- Content Category: content_courses, content_resources, content_course_feedback

CREATE DATABASE IF NOT EXISTS `offline_lms_db`;
USE `offline_lms_db`;

-- A. Unified User Table
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `full_name` VARCHAR(100) NOT NULL,
  `last_name` VARCHAR(100) NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('student', 'instructor', 'admin') NOT NULL DEFAULT 'student',
  `bio` TEXT NULL,
  `profile_pic` VARCHAR(255) NULL,
  `is_approved` TINYINT(1) DEFAULT 1,
  `meta` JSON NULL,
  `courses` JSON NULL,
  `resources` JSON NULL,
  `login_attempts` INT DEFAULT 0,
  `last_login` DATETIME NULL,
  `recovery_token` VARCHAR(255) NULL,
  `recovery_token_expires` DATETIME NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- B. Content Category (Unified)
CREATE TABLE IF NOT EXISTS `content` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `record_type` ENUM('course', 'resource', 'feedback') NOT NULL,
  `user_id` INT NULL COMMENT 'Creator for courses/resources, Student for feedback',
  `parent_id` INT NULL COMMENT 'For feedback this is the course id',
  `title` VARCHAR(255) NULL,
  `description` TEXT NULL COMMENT 'also used for feedback comment',
  `file_path` VARCHAR(255) NULL,
  `category` VARCHAR(100) NULL,
  `generic_value` VARCHAR(255) NULL COMMENT 'Used for Level, file_type, or rating',
  `meta_text` TEXT NULL COMMENT 'Used for tags or feedback reply',
  `is_resource` TINYINT(1) DEFAULT 0 COMMENT 'Legacy flag',
  `is_deleted` TINYINT(1) DEFAULT 0,
  `deleted_at` DATETIME NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

-- C. Progress Category (Unified)
CREATE TABLE IF NOT EXISTS `progress` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `record_type` ENUM('lesson', 'lesson_progress', 'certificate', 'streak') NOT NULL,
  `user_id` INT NULL,
  `course_id` INT NULL,
  `item_id` INT NULL COMMENT 'For lesson_progress this is the lesson id',
  `title` VARCHAR(255) NULL,
  `content_type` VARCHAR(50) NULL,
  `content_text` TEXT NULL,
  `file_path` VARCHAR(255) NULL,
  `file_size` INT NULL,
  `metric_value` VARCHAR(255) NULL COMMENT 'Used for streak_count, status, cert_id',
  `order_number` INT NULL,
  `last_activity_at` DATETIME NULL COMMENT 'Used for last_sync, last_login',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

-- D. Notification Category (Unified + Feedback)
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `type` ENUM('alert', 'feedback') NOT NULL DEFAULT 'alert',
  `user_id` INT NULL COMMENT 'Specific recipient ID',
  `target_role` ENUM('admin', 'student', 'instructor') NOT NULL,
  `message` TEXT NOT NULL COMMENT 'Alert body or Feedback comment',
  `meta` JSON NULL COMMENT 'Dynamic payload for Feedback rating/course/reply',
  `is_read` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

-- E. System Tables
CREATE TABLE IF NOT EXISTS `system_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `event_type` VARCHAR(50),
  `user_id` INT NULL,
  `user_role` VARCHAR(20),
  `description` TEXT,
  `target_id` INT NULL,
  `target_type` VARCHAR(20),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
