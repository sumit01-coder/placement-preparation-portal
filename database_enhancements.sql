-- Additional Tables for LeetCode-Level Features

-- Submissions tracking table
CREATE TABLE IF NOT EXISTS submissions (
    submission_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    problem_id INT NOT NULL,
    language_id INT NOT NULL,
    source_code TEXT NOT NULL,
    status ENUM('accepted', 'wrong_answer', 'time_limit_exceeded', 'memory_limit_exceeded', 
                'runtime_error', 'compilation_error', 'pending', 'judging') DEFAULT 'pending',
    runtime DECIMAL(10,3),  -- in seconds
    memory_used INT,         -- in KB
    passed_tests INT DEFAULT 0,
    total_tests INT DEFAULT 0,
    time_complexity VARCHAR(50),
    space_complexity VARCHAR(50),
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_problem (user_id, problem_id),
    INDEX idx_status (status),
    INDEX idx_submitted_at (submitted_at),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (problem_id) REFERENCES coding_problems(problem_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Languages supported
CREATE TABLE IF NOT EXISTS languages (
    language_id INT PRIMARY KEY,
    language_name VARCHAR(50) NOT NULL,
    judge0_id INT NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert supported languages
INSERT INTO languages (language_id, language_name, judge0_id) VALUES
(50, 'C', 50),
(54, 'C++', 54),
(62, 'Java', 62),
(71, 'Python', 71),
(63, 'JavaScript', 63)
ON DUPLICATE KEY UPDATE language_name=VALUES(language_name);

-- Problem tags for categorization
CREATE TABLE IF NOT EXISTS problem_tags (
    tag_id INT PRIMARY KEY AUTO_INCREMENT,
    tag_name VARCHAR(50) NOT NULL UNIQUE,
    category ENUM('algorithm', 'data_structure', 'difficulty', 'company', 'topic') NOT NULL,
    color VARCHAR(7) DEFAULT '#667eea',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Problem-Tag relationship
CREATE TABLE IF NOT EXISTS problem_tag_mapping (
    problem_id INT NOT NULL,
    tag_id INT NOT NULL,
    PRIMARY KEY (problem_id, tag_id),
    FOREIGN KEY (problem_id) REFERENCES coding_problems(problem_id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES problem_tags(tag_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Hidden test cases (not visible to users)
CREATE TABLE IF NOT EXISTS test_cases_hidden (
    test_case_id INT PRIMARY KEY AUTO_INCREMENT,
    problem_id INT NOT NULL,
    input_data TEXT NOT NULL,
    expected_output TEXT NOT NULL,
    weight INT DEFAULT 10,  -- weightage for scoring
    is_sample BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (problem_id) REFERENCES coding_problems(problem_id) ON DELETE CASCADE,
    INDEX idx_problem_sample (problem_id, is_sample)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- User achievements system
CREATE TABLE IF NOT EXISTS achievements (
    achievement_id INT PRIMARY KEY AUTO_INCREMENT,
    achievement_name VARCHAR(100) NOT NULL,
    description TEXT,
    icon_url VARCHAR(255),
    criteria JSON,  -- {"problems_solved": 10, "difficulty": "easy"}
    points INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- User achievements earned
CREATE TABLE IF NOT EXISTS user_achievements (
    user_id INT NOT NULL,
    achievement_id INT NOT NULL,
    earned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, achievement_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (achievement_id) REFERENCES achievements(achievement_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Global leaderboard (cached rankings)
CREATE TABLE IF NOT EXISTS leaderboard (
    user_id INT PRIMARY KEY,
    total_solved INT DEFAULT 0,
    easy_solved INT DEFAULT 0,
    medium_solved INT DEFAULT 0,
    hard_solved INT DEFAULT 0,
    total_submissions INT DEFAULT 0,
    acceptance_rate DECIMAL(5,2) DEFAULT 0,
    rank_position INT,
    points INT DEFAULT 0,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_rank (rank_position),
    INDEX idx_points (points DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Discussion forum for problems
CREATE TABLE IF NOT EXISTS problem_discussions (
    discussion_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    problem_id INT NOT NULL,
    user_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    code_snippet TEXT,
    language_id INT,
    upvotes INT DEFAULT 0,
    downvotes INT DEFAULT 0,
    is_solution BOOLEAN DEFAULT FALSE,
    is_pinned BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (problem_id) REFERENCES coding_problems(problem_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_problem_created (problem_id, created_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Discussion replies
CREATE TABLE IF NOT EXISTS discussion_replies (
    reply_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    discussion_id BIGINT NOT NULL,
    user_id INT NOT NULL,
    content TEXT NOT NULL,
    code_snippet TEXT,
    upvotes INT DEFAULT 0,
    downvotes INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (discussion_id) REFERENCES problem_discussions(discussion_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- User study streaks
CREATE TABLE IF NOT EXISTS user_streaks (
    user_id INT PRIMARY KEY,
    current_streak INT DEFAULT 0,
    longest_streak INT DEFAULT 0,
    last_activity_date DATE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert sample problem tags
INSERT INTO problem_tags (tag_name, category, color) VALUES
('Arrays', 'data_structure', '#3b82f6'),
('Strings', 'data_structure', '#8b5cf6'),
('Linked List', 'data_structure', '#ec4899'),
('Trees', 'data_structure', '#10b981'),
('Dynamic Programming', 'algorithm', '#f59e0b'),
('Greedy', 'algorithm', '#14b8a6'),
('Backtracking', 'algorithm', '#ef4444'),
('Graphs', 'data_structure', '#6366f1'),
('Hash Table', 'data_structure', '#a855f7'),
('Two Pointers', 'algorithm', '#06b6d4'),
('Binary Search', 'algorithm', '#84cc16'),
('Sorting', 'algorithm', '#f97316'),
('Recursion', 'algorithm', '#ec4899'),
('Google', 'company', '#4285f4'),
('Amazon', 'company', '#ff9900'),
('Microsoft', 'company', '#00a4ef'),
('TCS', 'company', '#0066cc'),
('Infosys', 'company', '#007cc3'),
('Wipro', 'company', '#ea5b0c')
ON DUPLICATE KEY UPDATE tag_name=VALUES(tag_name);

-- Insert sample achievements
INSERT INTO achievements (achievement_name, description, criteria, points) VALUES
('First Blood', 'Solve your first problem', '{"problems_solved": 1}', 10),
('Problem Solver', 'Solve 10 problems', '{"problems_solved": 10}', 50),
('Century', 'Solve 100 problems', '{"problems_solved": 100}', 500),
('Easy Rider', 'Solve 25 easy problems', '{"easy_solved": 25}', 100),
('Medium Master', 'Solve 25 medium problems', '{"medium_solved": 25}', 250),
('Hard Core', 'Solve 10 hard problems', '{"hard_solved": 10}', 500),
('Speed Demon', 'Solve a problem in under 5 minutes', '{"solve_time": 300}', 75),
('Week Warrior', '7-day coding streak', '{"streak_days": 7}', 150)
ON DUPLICATE KEY UPDATE achievement_name=VALUES(achievement_name);

-- Update existing tables
ALTER TABLE users ADD COLUMN IF NOT EXISTS problems_solved INT DEFAULT 0;
ALTER TABLE users ADD COLUMN IF NOT EXISTS total_submissions INT DEFAULT 0;
ALTER TABLE users ADD COLUMN IF NOT EXISTS last_submission_at TIMESTAMP NULL;
ALTER TABLE coding_problems ADD COLUMN IF NOT EXISTS acceptance_rate DECIMAL(5,2) DEFAULT 0;
ALTER TABLE coding_problems ADD COLUMN IF NOT EXISTS total_submissions INT DEFAULT 0;
ALTER TABLE coding_problems ADD COLUMN IF NOT EXISTS total_accepted INT DEFAULT 0;

-- Create views for quick stats
CREATE OR REPLACE VIEW v_problem_stats AS
SELECT 
    p.problem_id,
    p.title,
    p.difficulty,
    COUNT(s.submission_id) as total_submissions,
    SUM(CASE WHEN s.status = 'accepted' THEN 1 ELSE 0 END) as accepted_submissions,
    COUNT(DISTINCT s.user_id) as unique_solvers,
    ROUND(SUM(CASE WHEN s.status = 'accepted' THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(s.submission_id), 0), 2) as acceptance_rate
FROM coding_problems p
LEFT JOIN submissions s ON p.problem_id = s.problem_id
GROUP BY p.problem_id, p.title, p.difficulty;

CREATE OR REPLACE VIEW v_user_stats AS
SELECT 
    u.user_id,
    u.username,
    u.email,
    COUNT(DISTINCT CASE WHEN s.status = 'accepted' THEN s.problem_id END) as problems_solved,
    COUNT(s.submission_id) as total_submissions,
    ROUND(COUNT(DISTINCT CASE WHEN s.status = 'accepted' THEN s.problem_id END) * 100.0 / NULLIF(COUNT(s.submission_id), 0), 2) as acceptance_rate,
    MAX(s.submitted_at) as last_submission
FROM users u
LEFT JOIN submissions s ON u.user_id = s.user_id
GROUP BY u.user_id, u.username, u.email;
