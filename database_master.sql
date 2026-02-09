-- ========================================
-- PLACEMENT PORTAL - COMPLETE DATABASE SCHEMA
-- Version: 1.0
-- Last Updated: 2026-01-15
-- ========================================

-- Drop existing database if needed (CAUTION: This will delete all data)
-- DROP DATABASE IF EXISTS placement_portal;
-- CREATE DATABASE placement_portal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE placement_portal;

-- ========================================
-- CORE USER & AUTHENTICATION TABLES
-- ========================================

CREATE TABLE IF NOT EXISTS roles (
    role_id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO roles (role_name, description) VALUES
('student', 'Regular student user'),
('admin', 'Administrator with full access')
ON DUPLICATE KEY UPDATE role_name = role_name;

CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role_id INT DEFAULT 1,
    is_active BOOLEAN DEFAULT TRUE,
    email_verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    FOREIGN KEY (role_id) REFERENCES roles(role_id),
    INDEX idx_email (email),
    INDEX idx_role (role_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_profiles (
    profile_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    full_name VARCHAR(255),
    phone VARCHAR(20),
    college VARCHAR(255),
    branch VARCHAR(100),
    graduation_year INT,
    avatar_url VARCHAR(255),
    bio TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- CODING MODULE TABLES
-- ========================================

CREATE TABLE IF NOT EXISTS coding_problems (
    problem_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    difficulty ENUM('Easy', 'Medium', 'Hard') NOT NULL,
    description TEXT NOT NULL,
    input_format TEXT,
    output_format TEXT,
    constraints TEXT,
    sample_input TEXT,
    sample_output TEXT,
    explanation TEXT,
    tags JSON,
    time_limit INT DEFAULT 2,
    memory_limit INT DEFAULT 256,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_difficulty (difficulty),
    INDEX idx_slug (slug),
    FULLTEXT idx_title (title)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS test_cases (
    test_case_id INT AUTO_INCREMENT PRIMARY KEY,
    problem_id INT NOT NULL,
    input TEXT NOT NULL,
    expected_output TEXT NOT NULL,
    is_sample BOOLEAN DEFAULT FALSE,
    points INT DEFAULT 10,
    FOREIGN KEY (problem_id) REFERENCES coding_problems(problem_id) ON DELETE CASCADE,
    INDEX idx_problem (problem_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS submissions (
    submission_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    problem_id INT NOT NULL,
    language ENUM('c', 'cpp', 'java', 'python') NOT NULL,
    code TEXT NOT NULL,
    status ENUM('accepted', 'wrong_answer', 'time_limit_exceeded', 'runtime_error', 'compilation_error') NOT NULL,
    runtime INT,
    memory INT,
    test_cases_passed INT DEFAULT 0,
    total_test_cases INT,
    error_message TEXT,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (problem_id) REFERENCES coding_problems(problem_id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_problem (problem_id),
    INDEX idx_status (status),
    INDEX idx_submitted (submitted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- APTITUDE MODULE TABLES
-- ========================================

CREATE TABLE IF NOT EXISTS aptitude_tests (
    test_id INT AUTO_INCREMENT PRIMARY KEY,
    test_name VARCHAR(255) NOT NULL,
    category ENUM('Quantitative', 'Logical', 'Verbal') NOT NULL,
    difficulty ENUM('Easy', 'Medium', 'Hard') DEFAULT 'Medium',
    duration_minutes INT NOT NULL,
    total_questions INT NOT NULL,
    passing_score INT,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category (category),
    INDEX idx_difficulty (difficulty)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS aptitude_questions (
    question_id INT AUTO_INCREMENT PRIMARY KEY,
    test_id INT NOT NULL,
    question_text TEXT NOT NULL,
    option_a VARCHAR(500),
    option_b VARCHAR(500),
    option_c VARCHAR(500),
    option_d VARCHAR(500),
    correct_answer ENUM('A', 'B', 'C', 'D') NOT NULL,
    explanation TEXT,
    difficulty ENUM('Easy', 'Medium', 'Hard') DEFAULT 'Medium',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (test_id) REFERENCES aptitude_tests(test_id) ON DELETE CASCADE,
    INDEX idx_test (test_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS test_attempts (
    attempt_id INT AUTO_INCREMENT PRIMARY KEY,
    test_id INT NOT NULL,
    user_id INT NOT NULL,
    score INT NOT NULL,
    total_questions INT NOT NULL,
    time_taken INT,
    percentage DECIMAL(5,2),
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (test_id) REFERENCES aptitude_tests(test_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_test (test_id),
    INDEX idx_attempted (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS test_answers (
    answer_id INT AUTO_INCREMENT PRIMARY KEY,
    attempt_id INT NOT NULL,
    question_id INT NOT NULL,
    selected_answer ENUM('A', 'B', 'C', 'D'),
    is_correct BOOLEAN DEFAULT FALSE,
    time_spent INT,
    FOREIGN KEY (attempt_id) REFERENCES test_attempts(attempt_id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES aptitude_questions(question_id) ON DELETE CASCADE,
    INDEX idx_attempt (attempt_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- FOCUS MODE TABLES
-- ========================================

CREATE TABLE IF NOT EXISTS focus_sessions (
    session_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    problem_id INT,
    test_id INT,
    session_type ENUM('coding', 'aptitude', 'general') NOT NULL,
    start_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    end_time TIMESTAMP NULL,
    focus_score INT DEFAULT 100,
    violations_count INT DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (problem_id) REFERENCES coding_problems(problem_id) ON DELETE SET NULL,
    FOREIGN KEY (test_id) REFERENCES aptitude_tests(test_id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_start_time (start_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS focus_violations (
    violation_id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    violation_type ENUM('tab_switch', 'window_blur', 'copy_paste', 'fullscreen_exit') NOT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    details TEXT,
    FOREIGN KEY (session_id) REFERENCES focus_sessions(session_id) ON DELETE CASCADE,
    INDEX idx_session (session_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- COMMUNITY Q&A TABLES
-- ========================================

CREATE TABLE IF NOT EXISTS community_questions (
    question_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    tags JSON,
    views INT DEFAULT 0,
    votes INT DEFAULT 0,
    answer_count INT DEFAULT 0,
    is_solved BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_created (created_at),
    INDEX idx_votes (votes),
    FULLTEXT idx_search (title, content)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS community_answers (
    answer_id INT AUTO_INCREMENT PRIMARY KEY,
    question_id INT NOT NULL,
    user_id INT NOT NULL,
    content TEXT NOT NULL,
    votes INT DEFAULT 0,
    is_accepted BOOLEAN DEFAULT FALSE,
    is_verified BOOLEAN DEFAULT FALSE,
    verified_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (question_id) REFERENCES community_questions(question_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_question (question_id),
    INDEX idx_user (user_id),
    INDEX idx_votes (votes)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS community_comments (
    comment_id INT AUTO_INCREMENT PRIMARY KEY,
    parent_type ENUM('question', 'answer') NOT NULL,
    parent_id INT NOT NULL,
    user_id INT NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_parent (parent_type, parent_id),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS community_votes (
    vote_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    target_type ENUM('question', 'answer') NOT NULL,
    target_id INT NOT NULL,
    vote_value TINYINT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    UNIQUE KEY unique_vote (user_id, target_type, target_id),
    INDEX idx_target (target_type, target_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_reputation (
    reputation_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    points INT DEFAULT 0,
    questions_asked INT DEFAULT 0,
    answers_given INT DEFAULT 0,
    solutions_accepted INT DEFAULT 0,
    helpful_votes INT DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_points (points)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- CAREER TOOLKIT TABLES
-- ========================================

CREATE TABLE IF NOT EXISTS resumes (
    resume_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    personal_info JSON,
    education JSON,
    experience JSON,
    skills JSON,
    projects JSON,
    certifications JSON,
    template_id INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_documents (
    document_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_size BIGINT NOT NULL,
    category ENUM('resume', 'certificate', 'other') DEFAULT 'other',
    description TEXT,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- SUPPORT SYSTEM TABLES
-- ========================================

CREATE TABLE IF NOT EXISTS support_tickets (
    ticket_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    subject VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    category ENUM('general', 'technical', 'account', 'feedback') DEFAULT 'general',
    status ENUM('open', 'in_progress', 'resolved', 'closed') DEFAULT 'open',
    admin_response TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_status (status),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- SAMPLE DATA (Optional - for testing)
-- ========================================

-- Insert sample admin user (password: admin123)
INSERT INTO users (email, password_hash, role_id) VALUES
('admin@placementcode.com', '$argon2id$v=19$m=65536$t=4$p=3$somehashedpassword', 2)
ON DUPLICATE KEY UPDATE email = email;

-- Insert sample problems
INSERT INTO aptitude_tests (test_name, category, difficulty, duration_minutes, total_questions, description) VALUES
('Basic Quantitative Test', 'Quantitative', 'Easy', 30, 20, 'Covers basic arithmetic and algebra'),
('Logical Reasoning Test', 'Logical', 'Medium', 45, 25, 'Pattern recognition and logical thinking'),
('Verbal Ability Test', 'Verbal', 'Medium', 40, 20, 'Reading comprehension and grammar')
ON DUPLICATE KEY UPDATE test_name = test_name;

-- ========================================
-- INDEXES FOR PERFORMANCE
-- ========================================

-- Additional indexes for common queries
ALTER TABLE submissions ADD INDEX idx_user_problem (user_id, problem_id);
ALTER TABLE test_attempts ADD INDEX idx_user_test (user_id, test_id);
ALTER TABLE focus_sessions ADD INDEX idx_user_type (user_id, session_type);

-- ========================================
-- END OF SCHEMA
-- ========================================
