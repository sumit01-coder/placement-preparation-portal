-- 🚀 COMPLETE LEETCODE-STYLE DATABASE SCHEMA
-- Version: 3.0 (Unified & Optimized)
-- This script reconstructs the entire database from scratch with all features.

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- ==========================================
-- 1. CORE USERS & AUTH
-- ==========================================

CREATE TABLE IF NOT EXISTS `roles` (
  `role_id` int(11) NOT NULL AUTO_INCREMENT,
  `role_name` varchar(50) NOT NULL UNIQUE,
  PRIMARY KEY (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `roles` (`role_id`, `role_name`) VALUES
(1, 'student'), (2, 'admin'), (3, 'moderator')
ON DUPLICATE KEY UPDATE role_name=role_name;

CREATE TABLE IF NOT EXISTS `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL UNIQUE,
  `password_hash` varchar(255) NOT NULL,
  `role_id` int(11) DEFAULT 1,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `problems_solved` int(11) DEFAULT 0,
  `total_submissions` int(11) DEFAULT 0,
  PRIMARY KEY (`user_id`),
  FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `user_profiles` (
  `profile_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL UNIQUE,
  `full_name` varchar(255) NOT NULL,
  `college_name` varchar(255) DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `github_url` varchar(255) DEFAULT NULL,
  `linkedin_url` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`profile_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==========================================
-- 2. CODING PROBLEMS & CONTENT
-- ==========================================

CREATE TABLE IF NOT EXISTS `coding_problems` (
  `problem_id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL UNIQUE,
  `description` text NOT NULL,
  `input_format` text,
  `output_format` text,
  `constraints` text,
  `difficulty` enum('Easy','Medium','Hard') DEFAULT 'Medium',
  `time_limit_ms` int(11) DEFAULT 2000,
  `memory_limit_mb` int(11) DEFAULT 256,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `acceptance_rate` decimal(5,2) DEFAULT 0.00,
  `total_submissions` int(11) DEFAULT 0,
  `total_accepted` int(11) DEFAULT 0,
  PRIMARY KEY (`problem_id`),
  FULLTEXT KEY `idx_ft_search` (`title`, `description`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `problem_tags` (
  `tag_id` int(11) NOT NULL AUTO_INCREMENT,
  `tag_name` varchar(50) NOT NULL UNIQUE,
  `category` enum('algorithm','data_structure','topic','company') NOT NULL,
  PRIMARY KEY (`tag_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `problem_tag_mapping` (
  `problem_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL,
  PRIMARY KEY (`problem_id`,`tag_id`),
  FOREIGN KEY (`problem_id`) REFERENCES `coding_problems` (`problem_id`) ON DELETE CASCADE,
  FOREIGN KEY (`tag_id`) REFERENCES `problem_tags` (`tag_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Consolidating Test Cases into one table with flags
CREATE TABLE IF NOT EXISTS `test_cases` (
  `testcase_id` int(11) NOT NULL AUTO_INCREMENT,
  `problem_id` int(11) NOT NULL,
  `input_data` text NOT NULL,
  `expected_output` text NOT NULL,
  `is_sample` tinyint(1) DEFAULT 0,
  `is_hidden` tinyint(1) DEFAULT 0,
  `weight` int(11) DEFAULT 10,
  PRIMARY KEY (`testcase_id`),
  FOREIGN KEY (`problem_id`) REFERENCES `coding_problems` (`problem_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==========================================
-- 3. SUBMISSIONS & LANGUAGES
-- ==========================================

-- Standardized Languages Table (using Judge0 IDs)
CREATE TABLE IF NOT EXISTS `languages` (
  `language_id` int(11) NOT NULL, -- Judge0 ID directly as PK
  `language_name` varchar(50) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`language_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `languages` (`language_id`, `language_name`) VALUES
(50, 'C (GCC 9.2.0)'),
(54, 'C++ (GCC 9.2.0)'),
(62, 'Java (OpenJDK 13.0.1)'),
(71, 'Python (3.8.1)'),
(63, 'JavaScript (Node.js 12.14.0)')
ON DUPLICATE KEY UPDATE language_name=language_name;

-- Unified Submissions Table
CREATE TABLE IF NOT EXISTS `submissions` (
  `submission_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `problem_id` int(11) NOT NULL,
  `language_id` int(11) NOT NULL,
  `source_code` text NOT NULL,
  `status` enum('accepted','wrong_answer','time_limit_exceeded','runtime_error','compilation_error','pending') DEFAULT 'pending',
  `runtime` decimal(10,3) DEFAULT NULL, -- in seconds
  `memory_used` int(11) DEFAULT NULL, -- in KB
  `passed_tests` int(11) DEFAULT 0,
  `total_tests` int(11) DEFAULT 0,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`submission_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  FOREIGN KEY (`problem_id`) REFERENCES `coding_problems` (`problem_id`) ON DELETE CASCADE,
  FOREIGN KEY (`language_id`) REFERENCES `languages` (`language_id`),
  KEY `idx_user_stats` (`user_id`, `status`),
  KEY `idx_problem_stats` (`problem_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==========================================
-- 4. GAMIFICATION & ENGAGEMENT (NEW)
-- ==========================================

CREATE TABLE IF NOT EXISTS `achievements` (
  `achievement_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text,
  `criteria_json` text, -- e.g., {"problems_solved": 10}
  `points` int(11) DEFAULT 10,
  PRIMARY KEY (`achievement_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `user_achievements` (
  `user_id` int(11) NOT NULL,
  `achievement_id` int(11) NOT NULL,
  `earned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`user_id`, `achievement_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  FOREIGN KEY (`achievement_id`) REFERENCES `achievements` (`achievement_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `leaderboard` (
  `user_id` int(11) NOT NULL,
  `score` int(11) DEFAULT 0,
  `rank_position` int(11) DEFAULT 0,
  `problems_solved` int(11) DEFAULT 0,
  `last_updated` timestamp DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `daily_challenges` (
  `challenge_id` int(11) NOT NULL AUTO_INCREMENT,
  `problem_id` int(11) NOT NULL,
  `date` date NOT NULL UNIQUE,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`challenge_id`),
  FOREIGN KEY (`problem_id`) REFERENCES `coding_problems` (`problem_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `user_bookmarks` (
  `bookmark_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `problem_id` int(11) NOT NULL,
  `note` text,
  `created_at` timestamp DEFAULT current_timestamp(),
  PRIMARY KEY (`bookmark_id`),
  UNIQUE KEY `unique_bookmark` (`user_id`, `problem_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  FOREIGN KEY (`problem_id`) REFERENCES `coding_problems` (`problem_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==========================================
-- 5. CONTESTS (NEW)
-- ==========================================

CREATE TABLE IF NOT EXISTS `contests` (
  `contest_id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `start_time` datetime NOT NULL,
  `end_time` datetime NOT NULL,
  `description` text,
  PRIMARY KEY (`contest_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `contest_participants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `contest_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `score` int(11) DEFAULT 0,
  `rank` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`contest_id`) REFERENCES `contests` (`contest_id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==========================================
-- 6. ANALYTICS VIEWS
-- ==========================================

-- View for extensive User Statistics
CREATE OR REPLACE VIEW v_user_stats_comprehensive AS
SELECT 
    u.user_id,
    u.email,
    up.full_name,
    COUNT(DISTINCT s.submission_id) as total_submissions,
    COUNT(DISTINCT CASE WHEN s.status = 'accepted' THEN s.problem_id END) as problems_solved,
    COALESCE(SUM(CASE WHEN s.status = 'accepted' THEN 1 ELSE 0 END) / NULLIF(COUNT(s.submission_id), 0) * 100, 0) as acceptance_rate,
    (SELECT COUNT(*) FROM user_achievements ua WHERE ua.user_id = u.user_id) as achievements_unlocked,
    l.rank_position
FROM users u
LEFT JOIN user_profiles up ON u.user_id = up.user_id
LEFT JOIN submissions s ON u.user_id = s.user_id
LEFT JOIN leaderboard l ON u.user_id = l.user_id
GROUP BY u.user_id;

-- View for Problem Statistics
CREATE OR REPLACE VIEW v_problem_stats_advanced AS
SELECT 
    p.problem_id,
    p.title,
    p.difficulty,
    COUNT(s.submission_id) as attempts,
    SUM(CASE WHEN s.status = 'accepted' THEN 1 ELSE 0 END) as accepted_count,
    COALESCE(SUM(CASE WHEN s.status = 'accepted' THEN 1 ELSE 0 END) / NULLIF(COUNT(s.submission_id), 0) * 100, 0) as acceptance_rate
FROM coding_problems p
LEFT JOIN submissions s ON p.problem_id = s.problem_id
GROUP BY p.problem_id;

-- ==========================================
-- 7. INITIAL DATA SEEDING
-- ==========================================

-- Sample Data Structure Tags
INSERT INTO `problem_tags` (`tag_name`, `category`) VALUES 
('Arrays', 'data_structure'),
('Strings', 'data_structure'),
('Dynamic Programming', 'algorithm'),
('Graphs', 'data_structure'),
('Trees', 'data_structure')
ON DUPLICATE KEY UPDATE tag_name=tag_name;

COMMIT;
