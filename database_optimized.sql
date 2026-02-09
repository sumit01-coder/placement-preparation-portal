-- Database Optimization & New Features Script
-- Version: 2.0
-- Description: Consolidates legacy tables, adds advanced LeetCode features, and optimizes indexing

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- ==========================================
-- 1. CONSOLIDATION & CLEANUP
-- ==========================================

-- Migrate legacy coding_submissions to the new submissions table if needed
-- (Assuming we want to keep one single source of truth)
INSERT INTO submissions (user_id, problem_id, language_id, source_code, status, runtime, memory_used, passed_tests, total_tests, submitted_at)
SELECT 
    user_id, 
    problem_id, 
    language_id, 
    source_code, 
    CASE status 
        WHEN 'time_limit' THEN 'time_limit_exceeded'
        WHEN 'compile_error' THEN 'compilation_error'
        ELSE status 
    END,
    execution_time_ms / 1000.0, -- Convert ms to seconds
    memory_used_kb, 
    passed_testcases, 
    total_testcases, 
    submitted_at
FROM coding_submissions
WHERE submission_id NOT IN (SELECT submission_id FROM submissions);

-- Now we can drop the redundant table (Uncomment when ready to drop)
-- DROP TABLE coding_submissions;

-- Unified Test Cases Table
-- Merging test_cases and test_cases_hidden into a robust structure
ALTER TABLE test_cases 
ADD COLUMN is_hidden BOOLEAN DEFAULT FALSE,
ADD COLUMN weight INT DEFAULT 10,
ADD COLUMN time_limit_ms INT DEFAULT 2000,
ADD COLUMN memory_limit_mb INT DEFAULT 256;

-- Move hidden test cases to main table
INSERT INTO test_cases (problem_id, input_data, expected_output, is_sample, is_hidden, weight)
SELECT problem_id, input_data, expected_output, is_sample, TRUE, weight
FROM test_cases_hidden;

-- Drop redundant hidden table (Uncomment when ready)
-- DROP TABLE test_cases_hidden;


-- ==========================================
-- 2. NEW LEETCODE-STYLE FEATURES
-- ==========================================

-- Daily Challenges System
CREATE TABLE IF NOT EXISTS daily_challenges (
    challenge_id INT PRIMARY KEY AUTO_INCREMENT,
    problem_id INT NOT NULL,
    challenge_date DATE NOT NULL,
    coins_reward INT DEFAULT 10,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (problem_id) REFERENCES coding_problems(problem_id) ON DELETE CASCADE,
    UNIQUE KEY unique_date (challenge_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- User Bookmarks / Lists
CREATE TABLE IF NOT EXISTS user_bookmarks (
    bookmark_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    problem_id INT NOT NULL,
    list_name VARCHAR(50) DEFAULT 'My List',
    note TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (problem_id) REFERENCES coding_problems(problem_id) ON DELETE CASCADE,
    UNIQUE KEY unique_bookmark (user_id, problem_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Notifications System
CREATE TABLE IF NOT EXISTS notifications (
    notification_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    type ENUM('achievement', 'contest', 'reply', 'system', 'daily_challenge') NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    link_url VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Contest System Infrastructure
CREATE TABLE IF NOT EXISTS contests (
    contest_id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    status ENUM('upcoming', 'live', 'ended') DEFAULT 'upcoming',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS contest_problems (
    id INT PRIMARY KEY AUTO_INCREMENT,
    contest_id INT NOT NULL,
    problem_id INT NOT NULL,
    points INT DEFAULT 100,
    problem_order INT DEFAULT 1,
    FOREIGN KEY (contest_id) REFERENCES contests(contest_id) ON DELETE CASCADE,
    FOREIGN KEY (problem_id) REFERENCES coding_problems(problem_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS contest_participants (
    id INT PRIMARY KEY AUTO_INCREMENT,
    contest_id INT NOT NULL,
    user_id INT NOT NULL,
    score INT DEFAULT 0,
    finish_time DATETIME,
    rank_position INT,
    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (contest_id) REFERENCES contests(contest_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ==========================================
-- 3. PERFORMANCE OPTIMIZATIONS
-- ==========================================

-- FullText Search for Problems
ALTER TABLE coding_problems ADD FULLTEXT INDEX idx_problem_search (title, description);

-- Optimized User Stats View (Replacing old one with more optimized version)
CREATE OR REPLACE VIEW v_user_comprehensive_stats AS
SELECT 
    u.user_id,
    u.email,
    up.full_name,
    COUNT(DISTINCT s.submission_id) as total_submissions,
    COUNT(DISTINCT CASE WHEN s.status = 'accepted' THEN s.problem_id END) as problems_solved,
    SUM(CASE WHEN s.status = 'accepted' THEN 1 ELSE 0 END) as total_accepted_submissions,
    -- Calculate acceptance rate
    CASE 
        WHEN COUNT(s.submission_id) > 0 
        THEN (SUM(CASE WHEN s.status = 'accepted' THEN 1 ELSE 0 END) / COUNT(s.submission_id)) * 100 
        ELSE 0 
    END as submission_acceptance_rate,
    -- Difficulty breakdown
    COUNT(DISTINCT CASE WHEN s.status = 'accepted' AND p.difficulty = 'Easy' THEN p.problem_id END) as easy_solved,
    COUNT(DISTINCT CASE WHEN s.status = 'accepted' AND p.difficulty = 'Medium' THEN p.problem_id END) as medium_solved,
    COUNT(DISTINCT CASE WHEN s.status = 'accepted' AND p.difficulty = 'Hard' THEN p.problem_id END) as hard_solved,
    -- Rank
    COALESCE(l.rank_position, 0) as global_rank
FROM users u
LEFT JOIN user_profiles up ON u.user_id = up.user_id
LEFT JOIN submissions s ON u.user_id = s.user_id
LEFT JOIN coding_problems p ON s.problem_id = p.problem_id
LEFT JOIN leaderboard l ON u.user_id = l.user_id
GROUP BY u.user_id;

-- Optimized Problem Stats View
CREATE OR REPLACE VIEW v_problem_advanced_stats AS
SELECT 
    p.problem_id,
    p.title,
    p.difficulty,
    p.slug,
    COUNT(s.submission_id) as attempts,
    SUM(CASE WHEN s.status = 'accepted' THEN 1 ELSE 0 END) as accepted,
    CASE 
        WHEN COUNT(s.submission_id) > 0 
        THEN ROUND((SUM(CASE WHEN s.status = 'accepted' THEN 1 ELSE 0 END) * 100.0 / COUNT(s.submission_id)), 1)
        ELSE 0 
    END as acceptance_rate,
    AVG(CASE WHEN s.status = 'accepted' THEN s.runtime ELSE NULL END) as avg_runtime_sec,
    AVG(CASE WHEN s.status = 'accepted' THEN s.memory_used ELSE NULL END) as avg_memory_kb
FROM coding_problems p
LEFT JOIN submissions s ON p.problem_id = s.problem_id
GROUP BY p.problem_id;


COMMIT;
