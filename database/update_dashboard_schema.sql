-- Update schema with tables required for Dashboard
USE `offline_lms_db`;
-- 1. Feedback Table
CREATE TABLE IF NOT EXISTS `feedback` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `material_id` INT NOT NULL, -- Ties to progress_lessons or content_resources
  `material_type` ENUM('lesson', 'resource') DEFAULT 'lesson',
  `rating` INT CHECK (rating >= 1 AND rating <= 5),
  `comment` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. System Logs Table (for Administrator Tracking)
CREATE TABLE IF NOT EXISTS `system_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `event_type` VARCHAR(50) NOT NULL, -- e.g., 'login', 'sync', 'upload'
  `user_id` INT,
  `user_role` VARCHAR(20),
  `description` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Add some dummy log data for Admin Dashboard demonstration
INSERT INTO `system_logs` (`event_type`, `user_id`, `user_role`, `description`) VALUES 
('login', 1, 'student', 'Student logged in successfully'),
('sync', 1, 'student', 'Offline sync requested for course 1'),
('upload', 1, 'creator', 'Creator uploaded new PHP syllabus PDF'),
('login', 1, 'admin', 'Admin logged in');

-- 3. Notifications Table
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_role` VARCHAR(20) NOT NULL, -- target audience (e.g., 'student', 'all')
  `message` VARCHAR(255) NOT NULL,
  `link` VARCHAR(255) DEFAULT '#',
  `is_read` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 4. Downloads Table (Analytics for Offline Sync tracking)
CREATE TABLE IF NOT EXISTS `downloads` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `material_id` INT NOT NULL,
  `material_type` ENUM('lesson', 'resource') DEFAULT 'resource',
  `downloaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
