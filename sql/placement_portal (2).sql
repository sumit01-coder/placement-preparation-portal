-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 22, 2026 at 04:39 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `placement_portal`
--

-- --------------------------------------------------------

--
-- Table structure for table `achievements`
--

CREATE TABLE `achievements` (
  `achievement_id` int(11) NOT NULL,
  `achievement_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `icon_url` varchar(255) DEFAULT NULL,
  `criteria` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`criteria`)),
  `points` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `achievements`
--

INSERT INTO `achievements` (`achievement_id`, `achievement_name`, `description`, `icon_url`, `criteria`, `points`, `created_at`) VALUES
(1, 'First Blood', 'Solve your first problem', NULL, '{\"problems_solved\": 1}', 10, '2026-01-15 06:48:02'),
(2, 'Problem Solver', 'Solve 10 problems', NULL, '{\"problems_solved\": 10}', 50, '2026-01-15 06:48:02'),
(3, 'Century', 'Solve 100 problems', NULL, '{\"problems_solved\": 100}', 500, '2026-01-15 06:48:02'),
(4, 'Easy Rider', 'Solve 25 easy problems', NULL, '{\"easy_solved\": 25}', 100, '2026-01-15 06:48:02'),
(5, 'Medium Master', 'Solve 25 medium problems', NULL, '{\"medium_solved\": 25}', 250, '2026-01-15 06:48:02'),
(6, 'Hard Core', 'Solve 10 hard problems', NULL, '{\"hard_solved\": 10}', 500, '2026-01-15 06:48:02'),
(7, 'Speed Demon', 'Solve a problem in under 5 minutes', NULL, '{\"solve_time\": 300}', 75, '2026-01-15 06:48:02'),
(8, 'Week Warrior', '7-day coding streak', NULL, '{\"streak_days\": 7}', 150, '2026-01-15 06:48:02'),
(9, 'First Blood', 'Solve your first problem', NULL, '{\"problems_solved\": 1}', 10, '2026-01-22 03:28:15'),
(10, 'Problem Solver', 'Solve 10 problems', NULL, '{\"problems_solved\": 10}', 50, '2026-01-22 03:28:15'),
(11, 'Century', 'Solve 100 problems', NULL, '{\"problems_solved\": 100}', 500, '2026-01-22 03:28:15'),
(12, 'Easy Rider', 'Solve 25 easy problems', NULL, '{\"easy_solved\": 25}', 100, '2026-01-22 03:28:15'),
(13, 'Medium Master', 'Solve 25 medium problems', NULL, '{\"medium_solved\": 25}', 250, '2026-01-22 03:28:15'),
(14, 'Hard Core', 'Solve 10 hard problems', NULL, '{\"hard_solved\": 10}', 500, '2026-01-22 03:28:15'),
(15, 'Speed Demon', 'Solve a problem in under 5 minutes', NULL, '{\"solve_time\": 300}', 75, '2026-01-22 03:28:15'),
(16, 'Week Warrior', '7-day coding streak', NULL, '{\"streak_days\": 7}', 150, '2026-01-22 03:28:15');

-- --------------------------------------------------------

--
-- Table structure for table `admin_logs`
--

CREATE TABLE `admin_logs` (
  `log_id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `action_type` varchar(100) NOT NULL,
  `table_name` varchar(100) DEFAULT NULL,
  `record_id` int(11) DEFAULT NULL,
  `action_description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `answers`
--

CREATE TABLE `answers` (
  `answer_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `answer_body` text NOT NULL,
  `upvotes` int(11) DEFAULT 0,
  `downvotes` int(11) DEFAULT 0,
  `is_verified` tinyint(1) DEFAULT 0,
  `verified_by` int(11) DEFAULT NULL,
  `is_accepted` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Triggers `answers`
--
DELIMITER $$
CREATE TRIGGER `after_answer_insert` AFTER INSERT ON `answers` FOR EACH ROW BEGIN
    INSERT INTO leaderboard (user_id, answers_posted, reputation_score)
    VALUES (NEW.user_id, 1, 10)
    ON DUPLICATE KEY UPDATE 
        answers_posted = answers_posted + 1,
        reputation_score = reputation_score + 10;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `aptitude_answers`
--

CREATE TABLE `aptitude_answers` (
  `answer_id` int(11) NOT NULL,
  `attempt_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `selected_answer` enum('a','b','c','d') DEFAULT NULL,
  `is_correct` tinyint(1) DEFAULT NULL,
  `time_taken_seconds` int(11) DEFAULT NULL,
  `answered_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `aptitude_attempts`
--

CREATE TABLE `aptitude_attempts` (
  `attempt_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `test_id` int(11) NOT NULL,
  `start_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `end_time` timestamp NULL DEFAULT NULL,
  `duration_seconds` int(11) DEFAULT NULL,
  `score` int(11) DEFAULT 0,
  `total_marks` int(11) DEFAULT NULL,
  `percentage` decimal(5,2) DEFAULT NULL,
  `status` enum('in_progress','completed','abandoned') DEFAULT 'in_progress',
  `focus_score` decimal(5,2) DEFAULT 100.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `aptitude_categories`
--

CREATE TABLE `aptitude_categories` (
  `category_id` int(11) NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `category_slug` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `aptitude_categories`
--

INSERT INTO `aptitude_categories` (`category_id`, `category_name`, `category_slug`, `description`, `icon`, `is_active`, `created_at`) VALUES
(1, 'Quantitative Aptitude', 'quantitative', 'Mathematical and numerical reasoning', 'calculator', 1, '2026-01-15 06:17:43'),
(2, 'Logical Reasoning', 'logical', 'Pattern recognition and logical thinking', 'brain', 1, '2026-01-15 06:17:43'),
(3, 'Verbal Ability', 'verbal', 'English comprehension and grammar', 'book', 1, '2026-01-15 06:17:43');

-- --------------------------------------------------------

--
-- Table structure for table `aptitude_questions`
--

CREATE TABLE `aptitude_questions` (
  `question_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `question_text` text NOT NULL,
  `option_a` varchar(500) NOT NULL,
  `option_b` varchar(500) NOT NULL,
  `option_c` varchar(500) NOT NULL,
  `option_d` varchar(500) NOT NULL,
  `correct_answer` enum('a','b','c','d') NOT NULL,
  `explanation` text DEFAULT NULL,
  `difficulty` enum('easy','medium','hard') DEFAULT 'medium',
  `marks` int(11) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `aptitude_questions`
--

INSERT INTO `aptitude_questions` (`question_id`, `category_id`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_answer`, `explanation`, `difficulty`, `marks`, `created_by`, `created_at`) VALUES
(1, 1, 'What is 15% of 200?', '25', '30', '35', '40', 'b', '15% of 200 = (15/100) × 200 = 30', 'easy', 1, NULL, '2026-01-15 06:17:44'),
(2, 1, 'If a train travels 120 km in 2 hours, what is its speed?', '50 km/h', '60 km/h', '70 km/h', '80 km/h', 'b', 'Speed = Distance/Time = 120/2 = 60 km/h', 'easy', 1, NULL, '2026-01-15 06:17:44'),
(3, 2, 'Complete the series: 2, 6, 12, 20, ?', '28', '30', '32', '34', 'b', 'Differences are 4, 6, 8, so next is 10. 20 + 10 = 30', 'medium', 1, NULL, '2026-01-15 06:17:44'),
(4, 3, 'Choose the correctly spelled word:', 'Accommodate', 'Acommodate', 'Accomodate', 'Acomodate', 'a', 'Accommodate is the correct spelling', 'easy', 1, NULL, '2026-01-15 06:17:44');

-- --------------------------------------------------------

--
-- Table structure for table `aptitude_tests`
--

CREATE TABLE `aptitude_tests` (
  `test_id` int(11) NOT NULL,
  `test_name` varchar(255) NOT NULL,
  `category` enum('Quantitative','Logical','Verbal') NOT NULL DEFAULT 'Quantitative',
  `test_description` text DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `duration_minutes` int(11) NOT NULL,
  `total_marks` int(11) NOT NULL,
  `total_questions` int(11) NOT NULL,
  `passing_score` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `difficulty` enum('easy','medium','hard','mixed') DEFAULT 'mixed',
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `aptitude_tests`
--

INSERT INTO `aptitude_tests` (`test_id`, `test_name`, `category`, `test_description`, `category_id`, `duration_minutes`, `total_marks`, `total_questions`, `passing_score`, `description`, `difficulty`, `is_active`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'Quantitative Aptitude - Basic', 'Quantitative', NULL, NULL, 30, 0, 20, NULL, 'Basic quantitative aptitude covering numbers, percentages, and ratios', 'easy', 1, NULL, '2026-01-22 03:29:46', '2026-01-22 03:29:46'),
(2, 'Logical Reasoning - Pattern Recognition', 'Logical', NULL, NULL, 45, 0, 25, NULL, 'Test your pattern recognition and logical thinking skills', 'medium', 1, NULL, '2026-01-22 03:29:46', '2026-01-22 03:29:46'),
(3, 'Verbal Ability - Reading Comprehension', 'Verbal', NULL, NULL, 40, 0, 20, NULL, 'Assess your reading comprehension and grammar skills', 'medium', 1, NULL, '2026-01-22 03:29:46', '2026-01-22 03:29:46'),
(4, 'Advanced Quantitative', 'Quantitative', NULL, NULL, 60, 0, 30, NULL, 'Advanced problems on algebra, geometry, and data interpretation', 'hard', 1, NULL, '2026-01-22 03:29:46', '2026-01-22 03:29:46'),
(5, 'Logical Puzzles & Brain Teasers', 'Logical', NULL, NULL, 50, 0, 25, NULL, 'Challenging puzzles and logical problems', 'hard', 1, NULL, '2026-01-22 03:29:46', '2026-01-22 03:29:46');

-- --------------------------------------------------------

--
-- Table structure for table `coding_problems`
--

CREATE TABLE `coding_problems` (
  `problem_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `input_format` text DEFAULT NULL,
  `output_format` text DEFAULT NULL,
  `constraints` text DEFAULT NULL,
  `sample_input` text DEFAULT NULL,
  `sample_output` text DEFAULT NULL,
  `explanation` text DEFAULT NULL,
  `difficulty` enum('easy','medium','hard') DEFAULT 'medium',
  `tags` varchar(255) DEFAULT NULL,
  `time_limit` int(11) DEFAULT 2,
  `memory_limit` int(11) DEFAULT 256,
  `time_limit_ms` int(11) DEFAULT 2000,
  `memory_limit_mb` int(11) DEFAULT 256,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `acceptance_rate` decimal(5,2) DEFAULT 0.00,
  `total_submissions` int(11) DEFAULT 0,
  `total_accepted` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `coding_problems`
--

INSERT INTO `coding_problems` (`problem_id`, `title`, `slug`, `description`, `input_format`, `output_format`, `constraints`, `sample_input`, `sample_output`, `explanation`, `difficulty`, `tags`, `time_limit`, `memory_limit`, `time_limit_ms`, `memory_limit_mb`, `created_by`, `created_at`, `updated_at`, `acceptance_rate`, `total_submissions`, `total_accepted`) VALUES
(1, 'Two Sum', 'two-sum', 'Given an array of integers nums and an integer target, return indices of the two numbers such that they add up to target.', NULL, NULL, NULL, NULL, NULL, NULL, 'easy', NULL, 2, 256, 2000, 256, NULL, '2026-01-15 06:17:44', '2026-01-22 03:28:40', 0.00, 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `coding_submissions`
--

CREATE TABLE `coding_submissions` (
  `submission_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `problem_id` int(11) NOT NULL,
  `language_id` int(11) NOT NULL,
  `source_code` text NOT NULL,
  `status` enum('pending','accepted','wrong_answer','runtime_error','time_limit','compile_error') DEFAULT 'pending',
  `execution_time_ms` int(11) DEFAULT NULL,
  `memory_used_kb` int(11) DEFAULT NULL,
  `time_complexity` varchar(50) DEFAULT NULL,
  `space_complexity` varchar(50) DEFAULT NULL,
  `passed_testcases` int(11) DEFAULT 0,
  `total_testcases` int(11) DEFAULT 0,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `comments`
--

CREATE TABLE `comments` (
  `comment_id` int(11) NOT NULL,
  `parent_type` enum('question','answer') NOT NULL,
  `parent_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `comment_text` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `community_answers`
--

CREATE TABLE `community_answers` (
  `answer_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `content` text NOT NULL,
  `votes` int(11) DEFAULT 0,
  `is_accepted` tinyint(1) DEFAULT 0,
  `is_verified` tinyint(1) DEFAULT 0,
  `verified_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `community_comments`
--

CREATE TABLE `community_comments` (
  `comment_id` int(11) NOT NULL,
  `parent_type` enum('question','answer') NOT NULL,
  `parent_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `content` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `community_questions`
--

CREATE TABLE `community_questions` (
  `question_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `tags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tags`)),
  `views` int(11) DEFAULT 0,
  `votes` int(11) DEFAULT 0,
  `answer_count` int(11) DEFAULT 0,
  `is_solved` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `community_tags`
--

CREATE TABLE `community_tags` (
  `tag_id` int(11) NOT NULL,
  `tag_name` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `question_count` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `community_votes`
--

CREATE TABLE `community_votes` (
  `vote_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `target_type` enum('question','answer') NOT NULL,
  `target_id` int(11) NOT NULL,
  `vote_value` tinyint(4) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `companies`
--

CREATE TABLE `companies` (
  `company_id` int(11) NOT NULL,
  `company_name` varchar(255) NOT NULL,
  `company_slug` varchar(255) NOT NULL,
  `logo_url` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `website_url` varchar(255) DEFAULT NULL,
  `package_range` varchar(100) DEFAULT NULL,
  `eligibility_criteria` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `companies`
--

INSERT INTO `companies` (`company_id`, `company_name`, `company_slug`, `logo_url`, `description`, `website_url`, `package_range`, `eligibility_criteria`, `is_active`, `created_at`) VALUES
(1, 'TCS', 'tcs', NULL, 'Tata Consultancy Services - Leading IT services company', NULL, '3.5 - 7 LPA', NULL, 1, '2026-01-15 06:17:43'),
(2, 'Infosys', 'infosys', NULL, 'Global leader in consulting and technology services', NULL, '3.5 - 6 LPA', NULL, 1, '2026-01-15 06:17:43'),
(3, 'Wipro', 'wipro', NULL, 'Information technology, consulting and business process services', NULL, '3.5 - 7.5 LPA', NULL, 1, '2026-01-15 06:17:43'),
(4, 'Cognizant', 'cognizant', NULL, 'Multinational IT services and consulting company', NULL, '4 - 7 LPA', NULL, 1, '2026-01-15 06:17:43'),
(5, 'Accenture', 'accenture', NULL, 'Global professional services company', NULL, '4.5 - 8 LPA', NULL, 1, '2026-01-15 06:17:43');

-- --------------------------------------------------------

--
-- Table structure for table `company_questions`
--

CREATE TABLE `company_questions` (
  `cq_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `round_id` int(11) DEFAULT NULL,
  `question_text` text NOT NULL,
  `question_type` enum('aptitude','coding','technical','hr') NOT NULL,
  `difficulty` enum('easy','medium','hard') DEFAULT 'medium',
  `year` int(11) DEFAULT NULL,
  `answer` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `company_resources`
--

CREATE TABLE `company_resources` (
  `resource_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `resource_type` enum('pdf','video','link','article') NOT NULL,
  `resource_url` varchar(500) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `company_rounds`
--

CREATE TABLE `company_rounds` (
  `round_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `round_name` varchar(255) NOT NULL,
  `round_type` enum('aptitude','technical','hr','coding','group_discussion') NOT NULL,
  `round_order` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `duration_minutes` int(11) DEFAULT NULL,
  `cutoff_percentage` decimal(5,2) DEFAULT NULL,
  `tips` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `contests`
--

CREATE TABLE `contests` (
  `contest_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `start_time` datetime NOT NULL,
  `end_time` datetime NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `contest_participants`
--

CREATE TABLE `contest_participants` (
  `id` int(11) NOT NULL,
  `contest_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `score` int(11) DEFAULT 0,
  `rank` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `contest_problems`
--

CREATE TABLE `contest_problems` (
  `id` int(11) NOT NULL,
  `contest_id` int(11) NOT NULL,
  `problem_id` int(11) NOT NULL,
  `points` int(11) DEFAULT 100,
  `problem_order` int(11) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `daily_challenges`
--

CREATE TABLE `daily_challenges` (
  `challenge_id` int(11) NOT NULL,
  `problem_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `discussion_replies`
--

CREATE TABLE `discussion_replies` (
  `reply_id` bigint(20) NOT NULL,
  `discussion_id` bigint(20) NOT NULL,
  `user_id` int(11) NOT NULL,
  `content` text NOT NULL,
  `code_snippet` text DEFAULT NULL,
  `upvotes` int(11) DEFAULT 0,
  `downvotes` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `documents`
--

CREATE TABLE `documents` (
  `document_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `document_name` varchar(255) NOT NULL,
  `original_filename` varchar(255) DEFAULT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `file_type` varchar(50) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `document_categories`
--

CREATE TABLE `document_categories` (
  `category_id` int(11) NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `category_slug` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `document_categories`
--

INSERT INTO `document_categories` (`category_id`, `category_name`, `category_slug`) VALUES
(1, 'Certificates', 'certificates'),
(2, 'Marksheets', 'marksheets'),
(3, 'ID Proofs', 'id-proofs'),
(4, 'Other Documents', 'other');

-- --------------------------------------------------------

--
-- Table structure for table `focus_analytics`
--

CREATE TABLE `focus_analytics` (
  `analytics_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `total_study_time` int(11) DEFAULT 0,
  `total_violations` int(11) DEFAULT 0,
  `average_focus_score` decimal(5,2) DEFAULT 100.00,
  `sessions_count` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `focus_sessions`
--

CREATE TABLE `focus_sessions` (
  `session_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `session_type` enum('test','coding','study') NOT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `start_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `end_time` timestamp NULL DEFAULT NULL,
  `duration_seconds` int(11) DEFAULT NULL,
  `violation_count` int(11) DEFAULT 0,
  `focus_score` decimal(5,2) DEFAULT 100.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `focus_sessions`
--

INSERT INTO `focus_sessions` (`session_id`, `user_id`, `session_type`, `reference_id`, `start_time`, `end_time`, `duration_seconds`, `violation_count`, `focus_score`) VALUES
(1, 2, 'coding', 1, '2026-01-15 06:35:04', NULL, NULL, 13, 35.00),
(2, 2, 'coding', 1, '2026-01-15 06:38:24', NULL, NULL, 11, 45.00),
(3, 2, 'coding', 1, '2026-01-15 06:40:51', NULL, NULL, 17, 15.00),
(4, 2, 'coding', 1, '2026-01-15 06:43:34', NULL, NULL, 5, 75.00);

-- --------------------------------------------------------

--
-- Table structure for table `focus_violations`
--

CREATE TABLE `focus_violations` (
  `violation_id` int(11) NOT NULL,
  `session_id` int(11) NOT NULL,
  `violation_type` enum('tab_switch','window_blur','copy_paste','right_click','devtools') NOT NULL,
  `violation_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `duration_seconds` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `focus_violations`
--

INSERT INTO `focus_violations` (`violation_id`, `session_id`, `violation_type`, `violation_time`, `duration_seconds`) VALUES
(1, 1, 'window_blur', '2026-01-15 06:35:10', 0),
(2, 1, 'window_blur', '2026-01-15 06:35:27', 0),
(3, 1, 'window_blur', '2026-01-15 06:35:32', 0),
(4, 1, 'window_blur', '2026-01-15 06:35:39', 0),
(5, 1, 'window_blur', '2026-01-15 06:35:41', 0),
(6, 1, 'window_blur', '2026-01-15 06:35:45', 0),
(7, 1, 'window_blur', '2026-01-15 06:35:58', 0),
(8, 1, 'window_blur', '2026-01-15 06:36:05', 0),
(9, 1, 'window_blur', '2026-01-15 06:36:06', 0),
(10, 1, 'window_blur', '2026-01-15 06:36:07', 0),
(11, 1, 'window_blur', '2026-01-15 06:36:07', 0),
(12, 1, 'tab_switch', '2026-01-15 06:36:08', 0),
(13, 1, 'tab_switch', '2026-01-15 06:36:08', 0),
(14, 2, '', '2026-01-15 06:38:28', 0),
(15, 2, 'window_blur', '2026-01-15 06:38:29', 0),
(16, 2, 'window_blur', '2026-01-15 06:38:33', 0),
(17, 2, 'window_blur', '2026-01-15 06:38:37', 0),
(18, 2, 'window_blur', '2026-01-15 06:38:39', 0),
(19, 2, 'window_blur', '2026-01-15 06:38:41', 0),
(20, 2, 'window_blur', '2026-01-15 06:38:43', 0),
(21, 2, 'window_blur', '2026-01-15 06:38:58', 0),
(22, 2, 'window_blur', '2026-01-15 06:39:17', 0),
(23, 2, 'window_blur', '2026-01-15 06:39:49', 0),
(24, 2, 'window_blur', '2026-01-15 06:40:22', 0),
(25, 3, '', '2026-01-15 06:40:54', 0),
(26, 3, 'window_blur', '2026-01-15 06:40:59', 0),
(27, 3, 'window_blur', '2026-01-15 06:40:59', 0),
(28, 3, 'window_blur', '2026-01-15 06:41:01', 0),
(29, 3, 'window_blur', '2026-01-15 06:41:38', 0),
(30, 3, 'window_blur', '2026-01-15 06:42:17', 0),
(31, 3, 'window_blur', '2026-01-15 06:42:19', 0),
(32, 3, 'window_blur', '2026-01-15 06:43:10', 0),
(33, 3, 'window_blur', '2026-01-15 06:43:10', 0),
(34, 3, 'window_blur', '2026-01-15 06:43:10', 0),
(35, 3, 'window_blur', '2026-01-15 06:43:10', 0),
(36, 3, 'window_blur', '2026-01-15 06:43:10', 0),
(37, 3, 'window_blur', '2026-01-15 06:43:10', 0),
(38, 3, 'tab_switch', '2026-01-15 06:43:10', 0),
(39, 3, 'tab_switch', '2026-01-15 06:43:10', 0),
(40, 3, 'tab_switch', '2026-01-15 06:43:10', 0),
(41, 3, 'tab_switch', '2026-01-15 06:43:10', 0),
(42, 4, '', '2026-01-15 06:43:41', 0),
(43, 4, 'window_blur', '2026-01-15 06:43:45', 0),
(44, 4, 'window_blur', '2026-01-15 06:43:45', 0),
(45, 4, 'window_blur', '2026-01-15 06:43:52', 0),
(46, 4, 'tab_switch', '2026-01-15 06:44:00', 0);

--
-- Triggers `focus_violations`
--
DELIMITER $$
CREATE TRIGGER `after_violation_insert` AFTER INSERT ON `focus_violations` FOR EACH ROW BEGIN
    UPDATE focus_sessions 
    SET violation_count = violation_count + 1,
        focus_score = GREATEST(0, focus_score - 5)
    WHERE session_id = NEW.session_id;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `languages`
--

CREATE TABLE `languages` (
  `language_id` int(11) NOT NULL,
  `language_name` varchar(50) NOT NULL,
  `judge0_id` int(11) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `languages`
--

INSERT INTO `languages` (`language_id`, `language_name`, `judge0_id`, `is_active`, `created_at`) VALUES
(50, 'C', 50, 1, '2026-01-15 06:48:02'),
(54, 'C++', 54, 1, '2026-01-15 06:48:02'),
(62, 'Java', 62, 1, '2026-01-15 06:48:02'),
(63, 'JavaScript', 63, 1, '2026-01-15 06:48:02'),
(71, 'Python', 71, 1, '2026-01-15 06:48:02');

-- --------------------------------------------------------

--
-- Table structure for table `leaderboard`
--

CREATE TABLE `leaderboard` (
  `user_id` int(11) NOT NULL,
  `reputation_score` int(11) DEFAULT 0,
  `questions_asked` int(11) DEFAULT 0,
  `answers_posted` int(11) DEFAULT 0,
  `solutions_verified` int(11) DEFAULT 0,
  `total_upvotes` int(11) DEFAULT 0,
  `rank_position` int(11) DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` bigint(20) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` enum('achievement','contest','reply','system','daily_challenge') NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `link_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `problem_discussions`
--

CREATE TABLE `problem_discussions` (
  `discussion_id` bigint(20) NOT NULL,
  `problem_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `content` text NOT NULL,
  `code_snippet` text DEFAULT NULL,
  `language_id` int(11) DEFAULT NULL,
  `upvotes` int(11) DEFAULT 0,
  `downvotes` int(11) DEFAULT 0,
  `is_solution` tinyint(1) DEFAULT 0,
  `is_pinned` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `problem_tags`
--

CREATE TABLE `problem_tags` (
  `tag_id` int(11) NOT NULL,
  `tag_name` varchar(50) NOT NULL,
  `category` enum('algorithm','data_structure','difficulty','company','topic') NOT NULL,
  `color` varchar(7) DEFAULT '#667eea',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `problem_tags`
--

INSERT INTO `problem_tags` (`tag_id`, `tag_name`, `category`, `color`, `created_at`) VALUES
(1, 'Arrays', 'data_structure', '#3b82f6', '2026-01-15 06:48:02'),
(2, 'Strings', 'data_structure', '#8b5cf6', '2026-01-15 06:48:02'),
(3, 'Linked List', 'data_structure', '#ec4899', '2026-01-15 06:48:02'),
(4, 'Trees', 'data_structure', '#10b981', '2026-01-15 06:48:02'),
(5, 'Dynamic Programming', 'algorithm', '#f59e0b', '2026-01-15 06:48:02'),
(6, 'Greedy', 'algorithm', '#14b8a6', '2026-01-15 06:48:02'),
(7, 'Backtracking', 'algorithm', '#ef4444', '2026-01-15 06:48:02'),
(8, 'Graphs', 'data_structure', '#6366f1', '2026-01-15 06:48:02'),
(9, 'Hash Table', 'data_structure', '#a855f7', '2026-01-15 06:48:02'),
(10, 'Two Pointers', 'algorithm', '#06b6d4', '2026-01-15 06:48:02'),
(11, 'Binary Search', 'algorithm', '#84cc16', '2026-01-15 06:48:02'),
(12, 'Sorting', 'algorithm', '#f97316', '2026-01-15 06:48:02'),
(13, 'Recursion', 'algorithm', '#ec4899', '2026-01-15 06:48:02'),
(14, 'Google', 'company', '#4285f4', '2026-01-15 06:48:02'),
(15, 'Amazon', 'company', '#ff9900', '2026-01-15 06:48:02'),
(16, 'Microsoft', 'company', '#00a4ef', '2026-01-15 06:48:02'),
(17, 'TCS', 'company', '#0066cc', '2026-01-15 06:48:02'),
(18, 'Infosys', 'company', '#007cc3', '2026-01-15 06:48:02'),
(19, 'Wipro', 'company', '#ea5b0c', '2026-01-15 06:48:02');

-- --------------------------------------------------------

--
-- Table structure for table `problem_tag_mapping`
--

CREATE TABLE `problem_tag_mapping` (
  `problem_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `questions`
--

CREATE TABLE `questions` (
  `question_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(500) NOT NULL,
  `question_body` text NOT NULL,
  `views` int(11) DEFAULT 0,
  `upvotes` int(11) DEFAULT 0,
  `downvotes` int(11) DEFAULT 0,
  `is_solved` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Triggers `questions`
--
DELIMITER $$
CREATE TRIGGER `after_question_insert` AFTER INSERT ON `questions` FOR EACH ROW BEGIN
    INSERT INTO leaderboard (user_id, questions_asked, reputation_score)
    VALUES (NEW.user_id, 1, 5)
    ON DUPLICATE KEY UPDATE 
        questions_asked = questions_asked + 1,
        reputation_score = reputation_score + 5;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `question_tags`
--

CREATE TABLE `question_tags` (
  `tag_id` int(11) NOT NULL,
  `tag_name` varchar(50) NOT NULL,
  `tag_slug` varchar(50) NOT NULL,
  `usage_count` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `question_tag_mapping`
--

CREATE TABLE `question_tag_mapping` (
  `question_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `resumes`
--

CREATE TABLE `resumes` (
  `resume_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `template_id` int(11) DEFAULT NULL,
  `resume_title` varchar(255) DEFAULT NULL,
  `personal_info` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`personal_info`)),
  `education` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`education`)),
  `experience` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`experience`)),
  `skills` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`skills`)),
  `projects` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`projects`)),
  `certifications` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`certifications`)),
  `achievements` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`achievements`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `resume_templates`
--

CREATE TABLE `resume_templates` (
  `template_id` int(11) NOT NULL,
  `template_name` varchar(100) NOT NULL,
  `template_slug` varchar(100) NOT NULL,
  `preview_image` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `resume_templates`
--

INSERT INTO `resume_templates` (`template_id`, `template_name`, `template_slug`, `preview_image`, `is_active`) VALUES
(1, 'Professional', 'professional', NULL, 1),
(2, 'Modern', 'modern', NULL, 1),
(3, 'Creative', 'creative', NULL, 1),
(4, 'Minimalist', 'minimalist', NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `role_id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`role_id`, `role_name`, `description`, `created_at`) VALUES
(1, 'student', 'Regular student user', '2026-01-15 06:17:43'),
(2, 'admin', 'Administrator with full access', '2026-01-15 06:17:43'),
(3, 'moderator', NULL, '2026-01-15 06:17:43');

-- --------------------------------------------------------

--
-- Table structure for table `submissions`
--

CREATE TABLE `submissions` (
  `submission_id` bigint(20) NOT NULL,
  `user_id` int(11) NOT NULL,
  `problem_id` int(11) NOT NULL,
  `language_id` int(11) NOT NULL,
  `source_code` text NOT NULL,
  `status` enum('accepted','wrong_answer','time_limit_exceeded','memory_limit_exceeded','runtime_error','compilation_error','pending','judging') DEFAULT 'pending',
  `runtime` decimal(10,3) DEFAULT NULL,
  `memory` int(11) DEFAULT NULL,
  `test_cases_passed` int(11) DEFAULT 0,
  `total_test_cases` int(11) DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `memory_used` int(11) DEFAULT NULL,
  `passed_tests` int(11) DEFAULT 0,
  `total_tests` int(11) DEFAULT 0,
  `time_complexity` varchar(50) DEFAULT NULL,
  `space_complexity` varchar(50) DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `supported_languages`
--

CREATE TABLE `supported_languages` (
  `language_id` int(11) NOT NULL,
  `language_name` varchar(50) NOT NULL,
  `language_code` varchar(20) NOT NULL,
  `judge0_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `supported_languages`
--

INSERT INTO `supported_languages` (`language_id`, `language_name`, `language_code`, `judge0_id`, `is_active`) VALUES
(1, 'C', 'c', 50, 1),
(2, 'C++', 'cpp', 54, 1),
(3, 'Java', 'java', 62, 1),
(4, 'Python', 'python', 71, 1),
(5, 'JavaScript', 'javascript', 63, 1);

-- --------------------------------------------------------

--
-- Table structure for table `support_tickets`
--

CREATE TABLE `support_tickets` (
  `ticket_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `status` enum('open','in_progress','resolved','closed') DEFAULT 'open',
  `priority` enum('low','medium','high') DEFAULT 'medium',
  `assigned_to` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `resolved_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `setting_id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` enum('string','integer','boolean','json') DEFAULT 'string',
  `description` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`setting_id`, `setting_key`, `setting_value`, `setting_type`, `description`, `updated_at`) VALUES
(1, 'site_name', 'Placement Portal', 'string', 'Website name', '2026-01-15 06:17:44'),
(2, 'site_email', 'admin@placementportal.com', 'string', 'Contact email', '2026-01-15 06:17:44'),
(3, 'maintenance_mode', 'false', 'boolean', 'Enable/disable maintenance mode', '2026-01-15 06:17:44'),
(4, 'max_file_upload_mb', '10', 'integer', 'Maximum file upload size in MB', '2026-01-15 06:17:44');

-- --------------------------------------------------------

--
-- Table structure for table `test_answers`
--

CREATE TABLE `test_answers` (
  `answer_id` int(11) NOT NULL,
  `attempt_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `selected_answer` enum('A','B','C','D') DEFAULT NULL,
  `is_correct` tinyint(1) DEFAULT 0,
  `time_spent` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `test_attempts`
--

CREATE TABLE `test_attempts` (
  `attempt_id` int(11) NOT NULL,
  `test_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `score` int(11) NOT NULL,
  `total_questions` int(11) NOT NULL,
  `time_taken` int(11) DEFAULT NULL,
  `percentage` decimal(5,2) DEFAULT NULL,
  `attempted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `test_cases`
--

CREATE TABLE `test_cases` (
  `testcase_id` int(11) NOT NULL,
  `problem_id` int(11) NOT NULL,
  `input_data` text NOT NULL,
  `expected_output` text NOT NULL,
  `is_sample` tinyint(1) DEFAULT 0,
  `testcase_order` int(11) DEFAULT NULL,
  `is_hidden` tinyint(1) DEFAULT 0,
  `weight` int(11) DEFAULT 10,
  `time_limit_ms` int(11) DEFAULT 2000,
  `memory_limit_mb` int(11) DEFAULT 256
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `test_cases`
--

INSERT INTO `test_cases` (`testcase_id`, `problem_id`, `input_data`, `expected_output`, `is_sample`, `testcase_order`, `is_hidden`, `weight`, `time_limit_ms`, `memory_limit_mb`) VALUES
(1, 1, '[2,7,11,15]\n9', '[0,1]', 1, NULL, 0, 10, 2000, 256),
(2, 1, '[3,2,4]\n6', '[1,2]', 1, NULL, 0, 10, 2000, 256);

-- --------------------------------------------------------

--
-- Table structure for table `test_cases_hidden`
--

CREATE TABLE `test_cases_hidden` (
  `test_case_id` int(11) NOT NULL,
  `problem_id` int(11) NOT NULL,
  `input_data` text NOT NULL,
  `expected_output` text NOT NULL,
  `weight` int(11) DEFAULT 10,
  `is_sample` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `test_performance`
-- (See below for the actual view)
--
CREATE TABLE `test_performance` (
`test_id` int(11)
,`test_name` varchar(255)
,`total_attempts` bigint(21)
,`average_score` decimal(9,6)
,`highest_score` decimal(5,2)
,`lowest_score` decimal(5,2)
,`avg_duration_minutes` decimal(18,8)
);

-- --------------------------------------------------------

--
-- Table structure for table `test_questions`
--

CREATE TABLE `test_questions` (
  `id` int(11) NOT NULL,
  `test_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `question_order` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ticket_replies`
--

CREATE TABLE `ticket_replies` (
  `reply_id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `reply_message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role_id` int(11) DEFAULT 1,
  `is_active` tinyint(1) DEFAULT 1,
  `email_verified` tinyint(1) DEFAULT 0,
  `verification_token` varchar(100) DEFAULT NULL,
  `reset_token` varchar(100) DEFAULT NULL,
  `reset_token_expiry` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  `problems_solved` int(11) DEFAULT 0,
  `total_submissions` int(11) DEFAULT 0,
  `last_submission_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `email`, `password_hash`, `role_id`, `is_active`, `email_verified`, `verification_token`, `reset_token`, `reset_token_expiry`, `created_at`, `last_login`, `problems_solved`, `total_submissions`, `last_submission_at`) VALUES
(2, 'sumit@gmail.com', '$2y$10$qTKzYqOW6VdGb5lBexIux.kFH6oRjYar1XpRm5EvMhzrMwOBtEPfy', 1, 1, 0, 'd9e63f415b6f6dbaaae07cac9ea7440e766bde6bb8ab20084d9676345447f0a4', NULL, NULL, '2026-01-15 06:18:14', '2026-01-22 03:26:15', 0, 0, NULL),
(3, 'admin@placementcode.com', '$2y$10$YM63Am9rmu5Y5wld3y0Pdeqh6SQCshGYBFk9yEZfGlzoeBLwFSNiG', 2, 1, 0, '2f628de1278e047ca6dac9cb66576151acc02030d15eeaed1be8d8d2849474ad', NULL, NULL, '2026-01-22 03:35:22', '2026-01-22 03:36:52', 0, 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_achievements`
--

CREATE TABLE `user_achievements` (
  `user_id` int(11) NOT NULL,
  `achievement_id` int(11) NOT NULL,
  `earned_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_activity`
--

CREATE TABLE `user_activity` (
  `activity_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `activity_type` varchar(50) NOT NULL,
  `details` text DEFAULT NULL,
  `activity_time` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_activity`
--

INSERT INTO `user_activity` (`activity_id`, `user_id`, `activity_type`, `details`, `activity_time`) VALUES
(1, 2, 'login', 'User logged in', '2026-01-22 03:29:01'),
(4, 3, 'login', 'User logged in', '2026-01-22 03:36:52');

-- --------------------------------------------------------

--
-- Table structure for table `user_bookmarks`
--

CREATE TABLE `user_bookmarks` (
  `bookmark_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `problem_id` int(11) NOT NULL,
  `note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_documents`
--

CREATE TABLE `user_documents` (
  `document_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_size` bigint(20) NOT NULL,
  `category` enum('resume','certificate','other') DEFAULT 'other',
  `description` text DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_profiles`
--

CREATE TABLE `user_profiles` (
  `profile_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `college` varchar(255) DEFAULT NULL,
  `college_name` varchar(255) DEFAULT NULL,
  `branch` varchar(100) DEFAULT NULL,
  `graduation_year` int(11) DEFAULT NULL,
  `avatar_url` varchar(255) DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `linkedin_url` varchar(255) DEFAULT NULL,
  `github_url` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_profiles`
--

INSERT INTO `user_profiles` (`profile_id`, `user_id`, `full_name`, `phone`, `college`, `college_name`, `branch`, `graduation_year`, `avatar_url`, `profile_picture`, `bio`, `linkedin_url`, `github_url`, `updated_at`) VALUES
(2, 2, 'sumit', NULL, NULL, 'p p savani university', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-15 06:18:14'),
(3, 3, 'admin', NULL, NULL, 'p p savani university', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-22 03:35:22');

-- --------------------------------------------------------

--
-- Table structure for table `user_reputation`
--

CREATE TABLE `user_reputation` (
  `reputation_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `points` int(11) DEFAULT 0,
  `questions_asked` int(11) DEFAULT 0,
  `answers_given` int(11) DEFAULT 0,
  `solutions_accepted` int(11) DEFAULT 0,
  `helpful_votes` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `user_stats`
-- (See below for the actual view)
--
CREATE TABLE `user_stats` (
`user_id` int(11)
,`full_name` varchar(255)
,`email` varchar(255)
,`total_tests_taken` bigint(21)
,`avg_test_score` decimal(9,6)
,`total_submissions` bigint(21)
,`solved_problems` bigint(21)
,`reputation` int(11)
,`community_rank` int(11)
);

-- --------------------------------------------------------

--
-- Table structure for table `user_streaks`
--

CREATE TABLE `user_streaks` (
  `user_id` int(11) NOT NULL,
  `current_streak` int(11) DEFAULT 0,
  `longest_streak` int(11) DEFAULT 0,
  `last_activity_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `votes`
--

CREATE TABLE `votes` (
  `vote_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `vote_type` enum('question','answer') NOT NULL,
  `target_id` int(11) NOT NULL,
  `vote_value` tinyint(4) DEFAULT NULL CHECK (`vote_value` in (-1,1)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Triggers `votes`
--
DELIMITER $$
CREATE TRIGGER `after_vote_insert` AFTER INSERT ON `votes` FOR EACH ROW BEGIN
    DECLARE target_user_id INT;
    
    IF NEW.vote_type = 'question' THEN
        SELECT user_id INTO target_user_id FROM questions WHERE question_id = NEW.target_id;
    ELSE
        SELECT user_id INTO target_user_id FROM answers WHERE answer_id = NEW.target_id;
    END IF;
    
    UPDATE leaderboard 
    SET total_upvotes = total_upvotes + IF(NEW.vote_value = 1, 1, 0),
        reputation_score = reputation_score + (NEW.vote_value * 2)
    WHERE user_id = target_user_id;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_problem_advanced_stats`
-- (See below for the actual view)
--
CREATE TABLE `v_problem_advanced_stats` (
`problem_id` int(11)
,`title` varchar(255)
,`difficulty` enum('easy','medium','hard')
,`slug` varchar(255)
,`attempts` bigint(21)
,`accepted` decimal(22,0)
,`acceptance_rate` decimal(27,1)
,`avg_runtime_sec` decimal(14,7)
,`avg_memory_kb` decimal(14,4)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_problem_stats`
-- (See below for the actual view)
--
CREATE TABLE `v_problem_stats` (
`problem_id` int(11)
,`title` varchar(255)
,`difficulty` enum('easy','medium','hard')
,`total_submissions` bigint(21)
,`accepted_submissions` decimal(22,0)
,`unique_solvers` bigint(21)
,`acceptance_rate` decimal(28,2)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_problem_stats_advanced`
-- (See below for the actual view)
--
CREATE TABLE `v_problem_stats_advanced` (
`problem_id` int(11)
,`title` varchar(255)
,`difficulty` enum('easy','medium','hard')
,`attempts` bigint(21)
,`accepted_count` decimal(22,0)
,`acceptance_rate` decimal(29,4)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_user_comprehensive_stats`
-- (See below for the actual view)
--
CREATE TABLE `v_user_comprehensive_stats` (
`user_id` int(11)
,`email` varchar(255)
,`full_name` varchar(255)
,`total_submissions` bigint(21)
,`problems_solved` bigint(21)
,`total_accepted_submissions` decimal(22,0)
,`submission_acceptance_rate` decimal(29,4)
,`easy_solved` bigint(21)
,`medium_solved` bigint(21)
,`hard_solved` bigint(21)
,`global_rank` int(11)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_user_stats_comprehensive`
-- (See below for the actual view)
--
CREATE TABLE `v_user_stats_comprehensive` (
`user_id` int(11)
,`email` varchar(255)
,`full_name` varchar(255)
,`total_submissions` bigint(21)
,`problems_solved` bigint(21)
,`acceptance_rate` decimal(29,4)
,`achievements_unlocked` bigint(21)
,`rank_position` int(11)
);

-- --------------------------------------------------------

--
-- Structure for view `test_performance`
--
DROP TABLE IF EXISTS `test_performance`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `test_performance`  AS SELECT `t`.`test_id` AS `test_id`, `t`.`test_name` AS `test_name`, count(distinct `aa`.`user_id`) AS `total_attempts`, avg(`aa`.`percentage`) AS `average_score`, max(`aa`.`percentage`) AS `highest_score`, min(`aa`.`percentage`) AS `lowest_score`, avg(`aa`.`duration_seconds` / 60) AS `avg_duration_minutes` FROM (`aptitude_tests` `t` left join `aptitude_attempts` `aa` on(`t`.`test_id` = `aa`.`test_id` and `aa`.`status` = 'completed')) GROUP BY `t`.`test_id` ;

-- --------------------------------------------------------

--
-- Structure for view `user_stats`
--
DROP TABLE IF EXISTS `user_stats`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `user_stats`  AS SELECT `u`.`user_id` AS `user_id`, `up`.`full_name` AS `full_name`, `u`.`email` AS `email`, count(distinct `aa`.`attempt_id`) AS `total_tests_taken`, avg(`aa`.`percentage`) AS `avg_test_score`, count(distinct `cs`.`submission_id`) AS `total_submissions`, count(distinct case when `cs`.`status` = 'accepted' then `cs`.`submission_id` end) AS `solved_problems`, coalesce(`l`.`reputation_score`,0) AS `reputation`, coalesce(`l`.`rank_position`,0) AS `community_rank` FROM ((((`users` `u` left join `user_profiles` `up` on(`u`.`user_id` = `up`.`user_id`)) left join `aptitude_attempts` `aa` on(`u`.`user_id` = `aa`.`user_id` and `aa`.`status` = 'completed')) left join `coding_submissions` `cs` on(`u`.`user_id` = `cs`.`user_id`)) left join `leaderboard` `l` on(`u`.`user_id` = `l`.`user_id`)) WHERE `u`.`role_id` = 1 GROUP BY `u`.`user_id` ;

-- --------------------------------------------------------

--
-- Structure for view `v_problem_advanced_stats`
--
DROP TABLE IF EXISTS `v_problem_advanced_stats`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_problem_advanced_stats`  AS SELECT `p`.`problem_id` AS `problem_id`, `p`.`title` AS `title`, `p`.`difficulty` AS `difficulty`, `p`.`slug` AS `slug`, count(`s`.`submission_id`) AS `attempts`, sum(case when `s`.`status` = 'accepted' then 1 else 0 end) AS `accepted`, CASE WHEN count(`s`.`submission_id`) > 0 THEN round(sum(case when `s`.`status` = 'accepted' then 1 else 0 end) * 100.0 / count(`s`.`submission_id`),1) ELSE 0 END AS `acceptance_rate`, avg(case when `s`.`status` = 'accepted' then `s`.`runtime` else NULL end) AS `avg_runtime_sec`, avg(case when `s`.`status` = 'accepted' then `s`.`memory_used` else NULL end) AS `avg_memory_kb` FROM (`coding_problems` `p` left join `submissions` `s` on(`p`.`problem_id` = `s`.`problem_id`)) GROUP BY `p`.`problem_id` ;

-- --------------------------------------------------------

--
-- Structure for view `v_problem_stats`
--
DROP TABLE IF EXISTS `v_problem_stats`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_problem_stats`  AS SELECT `p`.`problem_id` AS `problem_id`, `p`.`title` AS `title`, `p`.`difficulty` AS `difficulty`, count(`s`.`submission_id`) AS `total_submissions`, sum(case when `s`.`status` = 'accepted' then 1 else 0 end) AS `accepted_submissions`, count(distinct `s`.`user_id`) AS `unique_solvers`, round(sum(case when `s`.`status` = 'accepted' then 1 else 0 end) * 100.0 / nullif(count(`s`.`submission_id`),0),2) AS `acceptance_rate` FROM (`coding_problems` `p` left join `submissions` `s` on(`p`.`problem_id` = `s`.`problem_id`)) GROUP BY `p`.`problem_id`, `p`.`title`, `p`.`difficulty` ;

-- --------------------------------------------------------

--
-- Structure for view `v_problem_stats_advanced`
--
DROP TABLE IF EXISTS `v_problem_stats_advanced`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_problem_stats_advanced`  AS SELECT `p`.`problem_id` AS `problem_id`, `p`.`title` AS `title`, `p`.`difficulty` AS `difficulty`, count(`s`.`submission_id`) AS `attempts`, sum(case when `s`.`status` = 'accepted' then 1 else 0 end) AS `accepted_count`, coalesce(sum(case when `s`.`status` = 'accepted' then 1 else 0 end) / nullif(count(`s`.`submission_id`),0) * 100,0) AS `acceptance_rate` FROM (`coding_problems` `p` left join `submissions` `s` on(`p`.`problem_id` = `s`.`problem_id`)) GROUP BY `p`.`problem_id` ;

-- --------------------------------------------------------

--
-- Structure for view `v_user_comprehensive_stats`
--
DROP TABLE IF EXISTS `v_user_comprehensive_stats`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_user_comprehensive_stats`  AS SELECT `u`.`user_id` AS `user_id`, `u`.`email` AS `email`, `up`.`full_name` AS `full_name`, count(distinct `s`.`submission_id`) AS `total_submissions`, count(distinct case when `s`.`status` = 'accepted' then `s`.`problem_id` end) AS `problems_solved`, sum(case when `s`.`status` = 'accepted' then 1 else 0 end) AS `total_accepted_submissions`, CASE WHEN count(`s`.`submission_id`) > 0 THEN sum(case when `s`.`status` = 'accepted' then 1 else 0 end) / count(`s`.`submission_id`) * 100 ELSE 0 END AS `submission_acceptance_rate`, count(distinct case when `s`.`status` = 'accepted' and `p`.`difficulty` = 'Easy' then `p`.`problem_id` end) AS `easy_solved`, count(distinct case when `s`.`status` = 'accepted' and `p`.`difficulty` = 'Medium' then `p`.`problem_id` end) AS `medium_solved`, count(distinct case when `s`.`status` = 'accepted' and `p`.`difficulty` = 'Hard' then `p`.`problem_id` end) AS `hard_solved`, coalesce(`l`.`rank_position`,0) AS `global_rank` FROM ((((`users` `u` left join `user_profiles` `up` on(`u`.`user_id` = `up`.`user_id`)) left join `submissions` `s` on(`u`.`user_id` = `s`.`user_id`)) left join `coding_problems` `p` on(`s`.`problem_id` = `p`.`problem_id`)) left join `leaderboard` `l` on(`u`.`user_id` = `l`.`user_id`)) GROUP BY `u`.`user_id` ;

-- --------------------------------------------------------

--
-- Structure for view `v_user_stats_comprehensive`
--
DROP TABLE IF EXISTS `v_user_stats_comprehensive`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_user_stats_comprehensive`  AS SELECT `u`.`user_id` AS `user_id`, `u`.`email` AS `email`, `up`.`full_name` AS `full_name`, count(distinct `s`.`submission_id`) AS `total_submissions`, count(distinct case when `s`.`status` = 'accepted' then `s`.`problem_id` end) AS `problems_solved`, coalesce(sum(case when `s`.`status` = 'accepted' then 1 else 0 end) / nullif(count(`s`.`submission_id`),0) * 100,0) AS `acceptance_rate`, (select count(0) from `user_achievements` `ua` where `ua`.`user_id` = `u`.`user_id`) AS `achievements_unlocked`, `l`.`rank_position` AS `rank_position` FROM (((`users` `u` left join `user_profiles` `up` on(`u`.`user_id` = `up`.`user_id`)) left join `submissions` `s` on(`u`.`user_id` = `s`.`user_id`)) left join `leaderboard` `l` on(`u`.`user_id` = `l`.`user_id`)) GROUP BY `u`.`user_id` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `achievements`
--
ALTER TABLE `achievements`
  ADD PRIMARY KEY (`achievement_id`);

--
-- Indexes for table `admin_logs`
--
ALTER TABLE `admin_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_admin` (`admin_id`),
  ADD KEY `idx_action` (`action_type`);

--
-- Indexes for table `answers`
--
ALTER TABLE `answers`
  ADD PRIMARY KEY (`answer_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `verified_by` (`verified_by`),
  ADD KEY `idx_question` (`question_id`);

--
-- Indexes for table `aptitude_answers`
--
ALTER TABLE `aptitude_answers`
  ADD PRIMARY KEY (`answer_id`),
  ADD UNIQUE KEY `unique_attempt_question` (`attempt_id`,`question_id`),
  ADD KEY `question_id` (`question_id`);

--
-- Indexes for table `aptitude_attempts`
--
ALTER TABLE `aptitude_attempts`
  ADD PRIMARY KEY (`attempt_id`),
  ADD KEY `test_id` (`test_id`),
  ADD KEY `idx_user_test` (`user_id`,`test_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_attempts_user_date` (`user_id`,`start_time`);

--
-- Indexes for table `aptitude_categories`
--
ALTER TABLE `aptitude_categories`
  ADD PRIMARY KEY (`category_id`),
  ADD UNIQUE KEY `category_slug` (`category_slug`);

--
-- Indexes for table `aptitude_questions`
--
ALTER TABLE `aptitude_questions`
  ADD PRIMARY KEY (`question_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_category` (`category_id`),
  ADD KEY `idx_difficulty` (`difficulty`);

--
-- Indexes for table `aptitude_tests`
--
ALTER TABLE `aptitude_tests`
  ADD PRIMARY KEY (`test_id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `coding_problems`
--
ALTER TABLE `coding_problems`
  ADD PRIMARY KEY (`problem_id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_difficulty` (`difficulty`),
  ADD KEY `idx_slug` (`slug`);
ALTER TABLE `coding_problems` ADD FULLTEXT KEY `idx_problem_search` (`title`,`description`);

--
-- Indexes for table `coding_submissions`
--
ALTER TABLE `coding_submissions`
  ADD PRIMARY KEY (`submission_id`),
  ADD KEY `problem_id` (`problem_id`),
  ADD KEY `language_id` (`language_id`),
  ADD KEY `idx_user_problem` (`user_id`,`problem_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_submissions_user_date` (`user_id`,`submitted_at`);

--
-- Indexes for table `comments`
--
ALTER TABLE `comments`
  ADD PRIMARY KEY (`comment_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_parent` (`parent_type`,`parent_id`);

--
-- Indexes for table `community_answers`
--
ALTER TABLE `community_answers`
  ADD PRIMARY KEY (`answer_id`),
  ADD KEY `verified_by` (`verified_by`),
  ADD KEY `idx_question_id` (`question_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_votes` (`votes`);

--
-- Indexes for table `community_comments`
--
ALTER TABLE `community_comments`
  ADD PRIMARY KEY (`comment_id`),
  ADD KEY `idx_parent` (`parent_type`,`parent_id`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Indexes for table `community_questions`
--
ALTER TABLE `community_questions`
  ADD PRIMARY KEY (`question_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_votes` (`votes`);
ALTER TABLE `community_questions` ADD FULLTEXT KEY `idx_title_content` (`title`,`content`);

--
-- Indexes for table `community_tags`
--
ALTER TABLE `community_tags`
  ADD PRIMARY KEY (`tag_id`),
  ADD UNIQUE KEY `tag_name` (`tag_name`),
  ADD KEY `idx_tag_name` (`tag_name`);

--
-- Indexes for table `community_votes`
--
ALTER TABLE `community_votes`
  ADD PRIMARY KEY (`vote_id`),
  ADD UNIQUE KEY `unique_vote` (`user_id`,`target_type`,`target_id`),
  ADD KEY `idx_target` (`target_type`,`target_id`);

--
-- Indexes for table `companies`
--
ALTER TABLE `companies`
  ADD PRIMARY KEY (`company_id`),
  ADD UNIQUE KEY `company_slug` (`company_slug`),
  ADD KEY `idx_slug` (`company_slug`);

--
-- Indexes for table `company_questions`
--
ALTER TABLE `company_questions`
  ADD PRIMARY KEY (`cq_id`),
  ADD KEY `company_id` (`company_id`),
  ADD KEY `round_id` (`round_id`);

--
-- Indexes for table `company_resources`
--
ALTER TABLE `company_resources`
  ADD PRIMARY KEY (`resource_id`),
  ADD KEY `company_id` (`company_id`),
  ADD KEY `uploaded_by` (`uploaded_by`);

--
-- Indexes for table `company_rounds`
--
ALTER TABLE `company_rounds`
  ADD PRIMARY KEY (`round_id`),
  ADD KEY `company_id` (`company_id`);

--
-- Indexes for table `contests`
--
ALTER TABLE `contests`
  ADD PRIMARY KEY (`contest_id`);

--
-- Indexes for table `contest_participants`
--
ALTER TABLE `contest_participants`
  ADD PRIMARY KEY (`id`),
  ADD KEY `contest_id` (`contest_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `contest_problems`
--
ALTER TABLE `contest_problems`
  ADD PRIMARY KEY (`id`),
  ADD KEY `contest_id` (`contest_id`),
  ADD KEY `problem_id` (`problem_id`);

--
-- Indexes for table `daily_challenges`
--
ALTER TABLE `daily_challenges`
  ADD PRIMARY KEY (`challenge_id`),
  ADD UNIQUE KEY `date` (`date`),
  ADD KEY `problem_id` (`problem_id`);

--
-- Indexes for table `discussion_replies`
--
ALTER TABLE `discussion_replies`
  ADD PRIMARY KEY (`reply_id`),
  ADD KEY `discussion_id` (`discussion_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `documents`
--
ALTER TABLE `documents`
  ADD PRIMARY KEY (`document_id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `idx_user` (`user_id`);

--
-- Indexes for table `document_categories`
--
ALTER TABLE `document_categories`
  ADD PRIMARY KEY (`category_id`),
  ADD UNIQUE KEY `category_slug` (`category_slug`);

--
-- Indexes for table `focus_analytics`
--
ALTER TABLE `focus_analytics`
  ADD PRIMARY KEY (`analytics_id`),
  ADD UNIQUE KEY `unique_user_date` (`user_id`,`date`);

--
-- Indexes for table `focus_sessions`
--
ALTER TABLE `focus_sessions`
  ADD PRIMARY KEY (`session_id`),
  ADD KEY `idx_user` (`user_id`);

--
-- Indexes for table `focus_violations`
--
ALTER TABLE `focus_violations`
  ADD PRIMARY KEY (`violation_id`),
  ADD KEY `session_id` (`session_id`);

--
-- Indexes for table `languages`
--
ALTER TABLE `languages`
  ADD PRIMARY KEY (`language_id`);

--
-- Indexes for table `leaderboard`
--
ALTER TABLE `leaderboard`
  ADD PRIMARY KEY (`user_id`),
  ADD KEY `idx_reputation` (`reputation_score`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `problem_discussions`
--
ALTER TABLE `problem_discussions`
  ADD PRIMARY KEY (`discussion_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_problem_created` (`problem_id`,`created_at`);

--
-- Indexes for table `problem_tags`
--
ALTER TABLE `problem_tags`
  ADD PRIMARY KEY (`tag_id`),
  ADD UNIQUE KEY `tag_name` (`tag_name`);

--
-- Indexes for table `problem_tag_mapping`
--
ALTER TABLE `problem_tag_mapping`
  ADD PRIMARY KEY (`problem_id`,`tag_id`),
  ADD KEY `tag_id` (`tag_id`);

--
-- Indexes for table `questions`
--
ALTER TABLE `questions`
  ADD PRIMARY KEY (`question_id`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `idx_votes` (`upvotes`),
  ADD KEY `idx_questions_user_date` (`user_id`,`created_at`);
ALTER TABLE `questions` ADD FULLTEXT KEY `idx_search` (`title`,`question_body`);

--
-- Indexes for table `question_tags`
--
ALTER TABLE `question_tags`
  ADD PRIMARY KEY (`tag_id`),
  ADD UNIQUE KEY `tag_name` (`tag_name`),
  ADD UNIQUE KEY `tag_slug` (`tag_slug`);

--
-- Indexes for table `question_tag_mapping`
--
ALTER TABLE `question_tag_mapping`
  ADD PRIMARY KEY (`question_id`,`tag_id`),
  ADD KEY `tag_id` (`tag_id`);

--
-- Indexes for table `resumes`
--
ALTER TABLE `resumes`
  ADD PRIMARY KEY (`resume_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `template_id` (`template_id`);

--
-- Indexes for table `resume_templates`
--
ALTER TABLE `resume_templates`
  ADD PRIMARY KEY (`template_id`),
  ADD UNIQUE KEY `template_slug` (`template_slug`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`role_id`),
  ADD UNIQUE KEY `role_name` (`role_name`);

--
-- Indexes for table `submissions`
--
ALTER TABLE `submissions`
  ADD PRIMARY KEY (`submission_id`),
  ADD KEY `idx_user_problem` (`user_id`,`problem_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_submitted_at` (`submitted_at`),
  ADD KEY `problem_id` (`problem_id`);

--
-- Indexes for table `supported_languages`
--
ALTER TABLE `supported_languages`
  ADD PRIMARY KEY (`language_id`),
  ADD UNIQUE KEY `language_code` (`language_code`);

--
-- Indexes for table `support_tickets`
--
ALTER TABLE `support_tickets`
  ADD PRIMARY KEY (`ticket_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `assigned_to` (`assigned_to`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`setting_id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `test_answers`
--
ALTER TABLE `test_answers`
  ADD PRIMARY KEY (`answer_id`),
  ADD KEY `question_id` (`question_id`),
  ADD KEY `idx_attempt_id` (`attempt_id`);

--
-- Indexes for table `test_attempts`
--
ALTER TABLE `test_attempts`
  ADD PRIMARY KEY (`attempt_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_test_id` (`test_id`),
  ADD KEY `idx_attempted_at` (`attempted_at`);

--
-- Indexes for table `test_cases`
--
ALTER TABLE `test_cases`
  ADD PRIMARY KEY (`testcase_id`),
  ADD KEY `problem_id` (`problem_id`);

--
-- Indexes for table `test_cases_hidden`
--
ALTER TABLE `test_cases_hidden`
  ADD PRIMARY KEY (`test_case_id`),
  ADD KEY `idx_problem_sample` (`problem_id`,`is_sample`);

--
-- Indexes for table `test_questions`
--
ALTER TABLE `test_questions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_test_question` (`test_id`,`question_id`),
  ADD KEY `question_id` (`question_id`);

--
-- Indexes for table `ticket_replies`
--
ALTER TABLE `ticket_replies`
  ADD PRIMARY KEY (`reply_id`),
  ADD KEY `ticket_id` (`ticket_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_role` (`role_id`);

--
-- Indexes for table `user_achievements`
--
ALTER TABLE `user_achievements`
  ADD PRIMARY KEY (`user_id`,`achievement_id`),
  ADD KEY `achievement_id` (`achievement_id`);

--
-- Indexes for table `user_activity`
--
ALTER TABLE `user_activity`
  ADD PRIMARY KEY (`activity_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_time` (`activity_time`);

--
-- Indexes for table `user_bookmarks`
--
ALTER TABLE `user_bookmarks`
  ADD PRIMARY KEY (`bookmark_id`),
  ADD UNIQUE KEY `unique_bookmark` (`user_id`,`problem_id`),
  ADD KEY `problem_id` (`problem_id`);

--
-- Indexes for table `user_documents`
--
ALTER TABLE `user_documents`
  ADD PRIMARY KEY (`document_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_category` (`category`);

--
-- Indexes for table `user_profiles`
--
ALTER TABLE `user_profiles`
  ADD PRIMARY KEY (`profile_id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `user_reputation`
--
ALTER TABLE `user_reputation`
  ADD PRIMARY KEY (`reputation_id`),
  ADD UNIQUE KEY `unique_user` (`user_id`),
  ADD KEY `idx_points` (`points`);

--
-- Indexes for table `user_streaks`
--
ALTER TABLE `user_streaks`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `votes`
--
ALTER TABLE `votes`
  ADD PRIMARY KEY (`vote_id`),
  ADD UNIQUE KEY `unique_vote` (`user_id`,`vote_type`,`target_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `achievements`
--
ALTER TABLE `achievements`
  MODIFY `achievement_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `admin_logs`
--
ALTER TABLE `admin_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `answers`
--
ALTER TABLE `answers`
  MODIFY `answer_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `aptitude_answers`
--
ALTER TABLE `aptitude_answers`
  MODIFY `answer_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `aptitude_attempts`
--
ALTER TABLE `aptitude_attempts`
  MODIFY `attempt_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `aptitude_categories`
--
ALTER TABLE `aptitude_categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `aptitude_questions`
--
ALTER TABLE `aptitude_questions`
  MODIFY `question_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `aptitude_tests`
--
ALTER TABLE `aptitude_tests`
  MODIFY `test_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `coding_problems`
--
ALTER TABLE `coding_problems`
  MODIFY `problem_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `coding_submissions`
--
ALTER TABLE `coding_submissions`
  MODIFY `submission_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `comments`
--
ALTER TABLE `comments`
  MODIFY `comment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `community_answers`
--
ALTER TABLE `community_answers`
  MODIFY `answer_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `community_comments`
--
ALTER TABLE `community_comments`
  MODIFY `comment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `community_questions`
--
ALTER TABLE `community_questions`
  MODIFY `question_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `community_tags`
--
ALTER TABLE `community_tags`
  MODIFY `tag_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `community_votes`
--
ALTER TABLE `community_votes`
  MODIFY `vote_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `companies`
--
ALTER TABLE `companies`
  MODIFY `company_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `company_questions`
--
ALTER TABLE `company_questions`
  MODIFY `cq_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `company_resources`
--
ALTER TABLE `company_resources`
  MODIFY `resource_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `company_rounds`
--
ALTER TABLE `company_rounds`
  MODIFY `round_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `contests`
--
ALTER TABLE `contests`
  MODIFY `contest_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `contest_participants`
--
ALTER TABLE `contest_participants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `contest_problems`
--
ALTER TABLE `contest_problems`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `daily_challenges`
--
ALTER TABLE `daily_challenges`
  MODIFY `challenge_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `discussion_replies`
--
ALTER TABLE `discussion_replies`
  MODIFY `reply_id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `documents`
--
ALTER TABLE `documents`
  MODIFY `document_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `document_categories`
--
ALTER TABLE `document_categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `focus_analytics`
--
ALTER TABLE `focus_analytics`
  MODIFY `analytics_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `focus_sessions`
--
ALTER TABLE `focus_sessions`
  MODIFY `session_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `focus_violations`
--
ALTER TABLE `focus_violations`
  MODIFY `violation_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `problem_discussions`
--
ALTER TABLE `problem_discussions`
  MODIFY `discussion_id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `problem_tags`
--
ALTER TABLE `problem_tags`
  MODIFY `tag_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

--
-- AUTO_INCREMENT for table `questions`
--
ALTER TABLE `questions`
  MODIFY `question_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `question_tags`
--
ALTER TABLE `question_tags`
  MODIFY `tag_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `resumes`
--
ALTER TABLE `resumes`
  MODIFY `resume_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `resume_templates`
--
ALTER TABLE `resume_templates`
  MODIFY `template_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `role_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `submissions`
--
ALTER TABLE `submissions`
  MODIFY `submission_id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `supported_languages`
--
ALTER TABLE `supported_languages`
  MODIFY `language_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `support_tickets`
--
ALTER TABLE `support_tickets`
  MODIFY `ticket_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `setting_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `test_answers`
--
ALTER TABLE `test_answers`
  MODIFY `answer_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `test_attempts`
--
ALTER TABLE `test_attempts`
  MODIFY `attempt_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `test_cases`
--
ALTER TABLE `test_cases`
  MODIFY `testcase_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `test_cases_hidden`
--
ALTER TABLE `test_cases_hidden`
  MODIFY `test_case_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `test_questions`
--
ALTER TABLE `test_questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ticket_replies`
--
ALTER TABLE `ticket_replies`
  MODIFY `reply_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `user_activity`
--
ALTER TABLE `user_activity`
  MODIFY `activity_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `user_bookmarks`
--
ALTER TABLE `user_bookmarks`
  MODIFY `bookmark_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_documents`
--
ALTER TABLE `user_documents`
  MODIFY `document_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_profiles`
--
ALTER TABLE `user_profiles`
  MODIFY `profile_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `user_reputation`
--
ALTER TABLE `user_reputation`
  MODIFY `reputation_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `votes`
--
ALTER TABLE `votes`
  MODIFY `vote_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin_logs`
--
ALTER TABLE `admin_logs`
  ADD CONSTRAINT `admin_logs_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `answers`
--
ALTER TABLE `answers`
  ADD CONSTRAINT `answers_ibfk_1` FOREIGN KEY (`question_id`) REFERENCES `questions` (`question_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `answers_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `answers_ibfk_3` FOREIGN KEY (`verified_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `aptitude_answers`
--
ALTER TABLE `aptitude_answers`
  ADD CONSTRAINT `aptitude_answers_ibfk_1` FOREIGN KEY (`attempt_id`) REFERENCES `aptitude_attempts` (`attempt_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `aptitude_answers_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `aptitude_questions` (`question_id`) ON DELETE CASCADE;

--
-- Constraints for table `aptitude_attempts`
--
ALTER TABLE `aptitude_attempts`
  ADD CONSTRAINT `aptitude_attempts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `aptitude_attempts_ibfk_2` FOREIGN KEY (`test_id`) REFERENCES `aptitude_tests` (`test_id`) ON DELETE CASCADE;

--
-- Constraints for table `aptitude_questions`
--
ALTER TABLE `aptitude_questions`
  ADD CONSTRAINT `aptitude_questions_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `aptitude_categories` (`category_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `aptitude_questions_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `aptitude_tests`
--
ALTER TABLE `aptitude_tests`
  ADD CONSTRAINT `aptitude_tests_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `aptitude_categories` (`category_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `aptitude_tests_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `coding_problems`
--
ALTER TABLE `coding_problems`
  ADD CONSTRAINT `coding_problems_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `coding_submissions`
--
ALTER TABLE `coding_submissions`
  ADD CONSTRAINT `coding_submissions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `coding_submissions_ibfk_2` FOREIGN KEY (`problem_id`) REFERENCES `coding_problems` (`problem_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `coding_submissions_ibfk_3` FOREIGN KEY (`language_id`) REFERENCES `supported_languages` (`language_id`) ON DELETE CASCADE;

--
-- Constraints for table `comments`
--
ALTER TABLE `comments`
  ADD CONSTRAINT `comments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `community_answers`
--
ALTER TABLE `community_answers`
  ADD CONSTRAINT `community_answers_ibfk_1` FOREIGN KEY (`question_id`) REFERENCES `community_questions` (`question_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `community_answers_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `community_answers_ibfk_3` FOREIGN KEY (`verified_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `community_comments`
--
ALTER TABLE `community_comments`
  ADD CONSTRAINT `community_comments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `community_questions`
--
ALTER TABLE `community_questions`
  ADD CONSTRAINT `community_questions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `community_votes`
--
ALTER TABLE `community_votes`
  ADD CONSTRAINT `community_votes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `company_questions`
--
ALTER TABLE `company_questions`
  ADD CONSTRAINT `company_questions_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`company_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `company_questions_ibfk_2` FOREIGN KEY (`round_id`) REFERENCES `company_rounds` (`round_id`) ON DELETE SET NULL;

--
-- Constraints for table `company_resources`
--
ALTER TABLE `company_resources`
  ADD CONSTRAINT `company_resources_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`company_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `company_resources_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `company_rounds`
--
ALTER TABLE `company_rounds`
  ADD CONSTRAINT `company_rounds_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`company_id`) ON DELETE CASCADE;

--
-- Constraints for table `contest_participants`
--
ALTER TABLE `contest_participants`
  ADD CONSTRAINT `contest_participants_ibfk_1` FOREIGN KEY (`contest_id`) REFERENCES `contests` (`contest_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `contest_participants_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `contest_problems`
--
ALTER TABLE `contest_problems`
  ADD CONSTRAINT `contest_problems_ibfk_1` FOREIGN KEY (`contest_id`) REFERENCES `contests` (`contest_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `contest_problems_ibfk_2` FOREIGN KEY (`problem_id`) REFERENCES `coding_problems` (`problem_id`) ON DELETE CASCADE;

--
-- Constraints for table `daily_challenges`
--
ALTER TABLE `daily_challenges`
  ADD CONSTRAINT `daily_challenges_ibfk_1` FOREIGN KEY (`problem_id`) REFERENCES `coding_problems` (`problem_id`) ON DELETE CASCADE;

--
-- Constraints for table `discussion_replies`
--
ALTER TABLE `discussion_replies`
  ADD CONSTRAINT `discussion_replies_ibfk_1` FOREIGN KEY (`discussion_id`) REFERENCES `problem_discussions` (`discussion_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `discussion_replies_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `documents`
--
ALTER TABLE `documents`
  ADD CONSTRAINT `documents_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `documents_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `document_categories` (`category_id`) ON DELETE SET NULL;

--
-- Constraints for table `focus_analytics`
--
ALTER TABLE `focus_analytics`
  ADD CONSTRAINT `focus_analytics_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `focus_sessions`
--
ALTER TABLE `focus_sessions`
  ADD CONSTRAINT `focus_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `focus_violations`
--
ALTER TABLE `focus_violations`
  ADD CONSTRAINT `focus_violations_ibfk_1` FOREIGN KEY (`session_id`) REFERENCES `focus_sessions` (`session_id`) ON DELETE CASCADE;

--
-- Constraints for table `leaderboard`
--
ALTER TABLE `leaderboard`
  ADD CONSTRAINT `leaderboard_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `problem_discussions`
--
ALTER TABLE `problem_discussions`
  ADD CONSTRAINT `problem_discussions_ibfk_1` FOREIGN KEY (`problem_id`) REFERENCES `coding_problems` (`problem_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `problem_discussions_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `problem_tag_mapping`
--
ALTER TABLE `problem_tag_mapping`
  ADD CONSTRAINT `problem_tag_mapping_ibfk_1` FOREIGN KEY (`problem_id`) REFERENCES `coding_problems` (`problem_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `problem_tag_mapping_ibfk_2` FOREIGN KEY (`tag_id`) REFERENCES `problem_tags` (`tag_id`) ON DELETE CASCADE;

--
-- Constraints for table `questions`
--
ALTER TABLE `questions`
  ADD CONSTRAINT `questions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `question_tag_mapping`
--
ALTER TABLE `question_tag_mapping`
  ADD CONSTRAINT `question_tag_mapping_ibfk_1` FOREIGN KEY (`question_id`) REFERENCES `questions` (`question_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `question_tag_mapping_ibfk_2` FOREIGN KEY (`tag_id`) REFERENCES `question_tags` (`tag_id`) ON DELETE CASCADE;

--
-- Constraints for table `resumes`
--
ALTER TABLE `resumes`
  ADD CONSTRAINT `resumes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `resumes_ibfk_2` FOREIGN KEY (`template_id`) REFERENCES `resume_templates` (`template_id`) ON DELETE SET NULL;

--
-- Constraints for table `submissions`
--
ALTER TABLE `submissions`
  ADD CONSTRAINT `submissions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `submissions_ibfk_2` FOREIGN KEY (`problem_id`) REFERENCES `coding_problems` (`problem_id`) ON DELETE CASCADE;

--
-- Constraints for table `support_tickets`
--
ALTER TABLE `support_tickets`
  ADD CONSTRAINT `support_tickets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `support_tickets_ibfk_2` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `test_answers`
--
ALTER TABLE `test_answers`
  ADD CONSTRAINT `test_answers_ibfk_1` FOREIGN KEY (`attempt_id`) REFERENCES `test_attempts` (`attempt_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `test_answers_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `aptitude_questions` (`question_id`) ON DELETE CASCADE;

--
-- Constraints for table `test_attempts`
--
ALTER TABLE `test_attempts`
  ADD CONSTRAINT `test_attempts_ibfk_1` FOREIGN KEY (`test_id`) REFERENCES `aptitude_tests` (`test_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `test_attempts_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `test_cases`
--
ALTER TABLE `test_cases`
  ADD CONSTRAINT `test_cases_ibfk_1` FOREIGN KEY (`problem_id`) REFERENCES `coding_problems` (`problem_id`) ON DELETE CASCADE;

--
-- Constraints for table `test_cases_hidden`
--
ALTER TABLE `test_cases_hidden`
  ADD CONSTRAINT `test_cases_hidden_ibfk_1` FOREIGN KEY (`problem_id`) REFERENCES `coding_problems` (`problem_id`) ON DELETE CASCADE;

--
-- Constraints for table `test_questions`
--
ALTER TABLE `test_questions`
  ADD CONSTRAINT `test_questions_ibfk_1` FOREIGN KEY (`test_id`) REFERENCES `aptitude_tests` (`test_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `test_questions_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `aptitude_questions` (`question_id`) ON DELETE CASCADE;

--
-- Constraints for table `ticket_replies`
--
ALTER TABLE `ticket_replies`
  ADD CONSTRAINT `ticket_replies_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `support_tickets` (`ticket_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ticket_replies_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`) ON DELETE SET NULL;

--
-- Constraints for table `user_achievements`
--
ALTER TABLE `user_achievements`
  ADD CONSTRAINT `user_achievements_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_achievements_ibfk_2` FOREIGN KEY (`achievement_id`) REFERENCES `achievements` (`achievement_id`) ON DELETE CASCADE;

--
-- Constraints for table `user_activity`
--
ALTER TABLE `user_activity`
  ADD CONSTRAINT `user_activity_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `user_bookmarks`
--
ALTER TABLE `user_bookmarks`
  ADD CONSTRAINT `user_bookmarks_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_bookmarks_ibfk_2` FOREIGN KEY (`problem_id`) REFERENCES `coding_problems` (`problem_id`) ON DELETE CASCADE;

--
-- Constraints for table `user_documents`
--
ALTER TABLE `user_documents`
  ADD CONSTRAINT `user_documents_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `user_profiles`
--
ALTER TABLE `user_profiles`
  ADD CONSTRAINT `user_profiles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `user_reputation`
--
ALTER TABLE `user_reputation`
  ADD CONSTRAINT `user_reputation_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `user_streaks`
--
ALTER TABLE `user_streaks`
  ADD CONSTRAINT `user_streaks_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `votes`
--
ALTER TABLE `votes`
  ADD CONSTRAINT `votes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
