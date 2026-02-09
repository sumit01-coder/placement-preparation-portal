-- ============================================
-- PLACEMENT PORTAL - DATABASE SCHEMA
-- ============================================
-- Created: 2026-01-15
-- Description: Complete database schema for placement preparation portal
-- ============================================

-- Drop existing database if exists and create new
DROP DATABASE IF EXISTS placement_portal;
CREATE DATABASE placement_portal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE placement_portal;

-- ============================================
-- CORE AUTHENTICATION & USER MANAGEMENT
-- ============================================

CREATE TABLE roles (
    role_id INT PRIMARY KEY AUTO_INCREMENT,
    role_name VARCHAR(50) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO roles (role_name) VALUES ('student'), ('admin'), ('moderator');

CREATE TABLE users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role_id INT DEFAULT 1,
    is_active BOOLEAN DEFAULT TRUE,
    email_verified BOOLEAN DEFAULT FALSE,
    verification_token VARCHAR(100),
    reset_token VARCHAR(100),
    reset_token_expiry DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    FOREIGN KEY (role_id) REFERENCES roles(role_id) ON DELETE SET NULL,
    INDEX idx_email (email),
    INDEX idx_role (role_id)
);

CREATE TABLE user_profiles (
    profile_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    college_name VARCHAR(255),
    branch VARCHAR(100),
    graduation_year INT,
    profile_picture VARCHAR(255),
    bio TEXT,
    linkedin_url VARCHAR(255),
    github_url VARCHAR(255),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- ============================================
-- APTITUDE ENGINE MODULE
-- ============================================

CREATE TABLE aptitude_categories (
    category_id INT PRIMARY KEY AUTO_INCREMENT,
    category_name VARCHAR(100) NOT NULL,
    category_slug VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    icon VARCHAR(50),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO aptitude_categories (category_name, category_slug, description, icon) VALUES
('Quantitative Aptitude', 'quantitative', 'Mathematical and numerical reasoning', 'calculator'),
('Logical Reasoning', 'logical', 'Pattern recognition and logical thinking', 'brain'),
('Verbal Ability', 'verbal', 'English comprehension and grammar', 'book');

CREATE TABLE aptitude_questions (
    question_id INT PRIMARY KEY AUTO_INCREMENT,
    category_id INT NOT NULL,
    question_text TEXT NOT NULL,
    option_a VARCHAR(500) NOT NULL,
    option_b VARCHAR(500) NOT NULL,
    option_c VARCHAR(500) NOT NULL,
    option_d VARCHAR(500) NOT NULL,
    correct_answer ENUM('a', 'b', 'c', 'd') NOT NULL,
    explanation TEXT,
    difficulty ENUM('easy', 'medium', 'hard') DEFAULT 'medium',
    marks INT DEFAULT 1,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES aptitude_categories(category_id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_category (category_id),
    INDEX idx_difficulty (difficulty)
);

CREATE TABLE aptitude_tests (
    test_id INT PRIMARY KEY AUTO_INCREMENT,
    test_name VARCHAR(255) NOT NULL,
    test_description TEXT,
    category_id INT,
    duration_minutes INT NOT NULL,
    total_marks INT NOT NULL,
    total_questions INT NOT NULL,
    difficulty ENUM('easy', 'medium', 'hard', 'mixed') DEFAULT 'mixed',
    is_active BOOLEAN DEFAULT TRUE,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES aptitude_categories(category_id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL
);

CREATE TABLE test_questions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    test_id INT NOT NULL,
    question_id INT NOT NULL,
    question_order INT,
    FOREIGN KEY (test_id) REFERENCES aptitude_tests(test_id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES aptitude_questions(question_id) ON DELETE CASCADE,
    UNIQUE KEY unique_test_question (test_id, question_id)
);

CREATE TABLE aptitude_attempts (
    attempt_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    test_id INT NOT NULL,
    start_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    end_time TIMESTAMP NULL,
    duration_seconds INT,
    score INT DEFAULT 0,
    total_marks INT,
    percentage DECIMAL(5,2),
    status ENUM('in_progress', 'completed', 'abandoned') DEFAULT 'in_progress',
    focus_score DECIMAL(5,2) DEFAULT 100.00,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (test_id) REFERENCES aptitude_tests(test_id) ON DELETE CASCADE,
    INDEX idx_user_test (user_id, test_id),
    INDEX idx_status (status)
);

CREATE TABLE aptitude_answers (
    answer_id INT PRIMARY KEY AUTO_INCREMENT,
    attempt_id INT NOT NULL,
    question_id INT NOT NULL,
    selected_answer ENUM('a', 'b', 'c', 'd'),
    is_correct BOOLEAN,
    time_taken_seconds INT,
    answered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (attempt_id) REFERENCES aptitude_attempts(attempt_id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES aptitude_questions(question_id) ON DELETE CASCADE,
    UNIQUE KEY unique_attempt_question (attempt_id, question_id)
);

-- ============================================
-- SMART CODE STUDIO MODULE
-- ============================================

CREATE TABLE supported_languages (
    language_id INT PRIMARY KEY AUTO_INCREMENT,
    language_name VARCHAR(50) NOT NULL,
    language_code VARCHAR(20) UNIQUE NOT NULL,
    judge0_id INT,
    is_active BOOLEAN DEFAULT TRUE
);

INSERT INTO supported_languages (language_name, language_code, judge0_id) VALUES
('C', 'c', 50),
('C++', 'cpp', 54),
('Java', 'java', 62),
('Python', 'python', 71),
('JavaScript', 'javascript', 63);

CREATE TABLE coding_problems (
    problem_id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    description TEXT NOT NULL,
    input_format TEXT,
    output_format TEXT,
    constraints TEXT,
    difficulty ENUM('easy', 'medium', 'hard') DEFAULT 'medium',
    tags VARCHAR(255),
    time_limit_ms INT DEFAULT 2000,
    memory_limit_mb INT DEFAULT 256,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_difficulty (difficulty),
    INDEX idx_slug (slug)
);

CREATE TABLE test_cases (
    testcase_id INT PRIMARY KEY AUTO_INCREMENT,
    problem_id INT NOT NULL,
    input_data TEXT NOT NULL,
    expected_output TEXT NOT NULL,
    is_sample BOOLEAN DEFAULT FALSE,
    testcase_order INT,
    FOREIGN KEY (problem_id) REFERENCES coding_problems(problem_id) ON DELETE CASCADE
);

CREATE TABLE coding_submissions (
    submission_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    problem_id INT NOT NULL,
    language_id INT NOT NULL,
    source_code TEXT NOT NULL,
    status ENUM('pending', 'accepted', 'wrong_answer', 'runtime_error', 'time_limit', 'compile_error') DEFAULT 'pending',
    execution_time_ms INT,
    memory_used_kb INT,
    time_complexity VARCHAR(50),
    space_complexity VARCHAR(50),
    passed_testcases INT DEFAULT 0,
    total_testcases INT DEFAULT 0,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (problem_id) REFERENCES coding_problems(problem_id) ON DELETE CASCADE,
    FOREIGN KEY (language_id) REFERENCES supported_languages(language_id) ON DELETE CASCADE,
    INDEX idx_user_problem (user_id, problem_id),
    INDEX idx_status (status)
);

-- ============================================
-- COMPANY SPECIFIC PREPARATION MODULE
-- ============================================

CREATE TABLE companies (
    company_id INT PRIMARY KEY AUTO_INCREMENT,
    company_name VARCHAR(255) NOT NULL,
    company_slug VARCHAR(255) UNIQUE NOT NULL,
    logo_url VARCHAR(255),
    description TEXT,
    website_url VARCHAR(255),
    package_range VARCHAR(100),
    eligibility_criteria TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_slug (company_slug)
);

INSERT INTO companies (company_name, company_slug, description, package_range) VALUES
('TCS', 'tcs', 'Tata Consultancy Services - Leading IT services company', '3.5 - 7 LPA'),
('Infosys', 'infosys', 'Global leader in consulting and technology services', '3.5 - 6 LPA'),
('Wipro', 'wipro', 'Information technology, consulting and business process services', '3.5 - 7.5 LPA'),
('Cognizant', 'cognizant', 'Multinational IT services and consulting company', '4 - 7 LPA'),
('Accenture', 'accenture', 'Global professional services company', '4.5 - 8 LPA');

CREATE TABLE company_rounds (
    round_id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT NOT NULL,
    round_name VARCHAR(255) NOT NULL,
    round_type ENUM('aptitude', 'technical', 'hr', 'coding', 'group_discussion') NOT NULL,
    round_order INT,
    description TEXT,
    duration_minutes INT,
    cutoff_percentage DECIMAL(5,2),
    tips TEXT,
    FOREIGN KEY (company_id) REFERENCES companies(company_id) ON DELETE CASCADE
);

CREATE TABLE company_questions (
    cq_id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT NOT NULL,
    round_id INT,
    question_text TEXT NOT NULL,
    question_type ENUM('aptitude', 'coding', 'technical', 'hr') NOT NULL,
    difficulty ENUM('easy', 'medium', 'hard') DEFAULT 'medium',
    year INT,
    answer TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(company_id) ON DELETE CASCADE,
    FOREIGN KEY (round_id) REFERENCES company_rounds(round_id) ON DELETE SET NULL
);

CREATE TABLE company_resources (
    resource_id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    resource_type ENUM('pdf', 'video', 'link', 'article') NOT NULL,
    resource_url VARCHAR(500),
    description TEXT,
    uploaded_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(company_id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(user_id) ON DELETE SET NULL
);

-- ============================================
-- COMMUNITY Q&A MODULE
-- ============================================

CREATE TABLE question_tags (
    tag_id INT PRIMARY KEY AUTO_INCREMENT,
    tag_name VARCHAR(50) UNIQUE NOT NULL,
    tag_slug VARCHAR(50) UNIQUE NOT NULL,
    usage_count INT DEFAULT 0
);

CREATE TABLE questions (
    question_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    title VARCHAR(500) NOT NULL,
    question_body TEXT NOT NULL,
    views INT DEFAULT 0,
    upvotes INT DEFAULT 0,
    downvotes INT DEFAULT 0,
    is_solved BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_created (created_at),
    INDEX idx_votes (upvotes),
    FULLTEXT idx_search (title, question_body)
);

CREATE TABLE question_tag_mapping (
    question_id INT NOT NULL,
    tag_id INT NOT NULL,
    PRIMARY KEY (question_id, tag_id),
    FOREIGN KEY (question_id) REFERENCES questions(question_id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES question_tags(tag_id) ON DELETE CASCADE
);

CREATE TABLE answers (
    answer_id INT PRIMARY KEY AUTO_INCREMENT,
    question_id INT NOT NULL,
    user_id INT NOT NULL,
    answer_body TEXT NOT NULL,
    upvotes INT DEFAULT 0,
    downvotes INT DEFAULT 0,
    is_verified BOOLEAN DEFAULT FALSE,
    verified_by INT,
    is_accepted BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (question_id) REFERENCES questions(question_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_question (question_id)
);

CREATE TABLE comments (
    comment_id INT PRIMARY KEY AUTO_INCREMENT,
    parent_type ENUM('question', 'answer') NOT NULL,
    parent_id INT NOT NULL,
    user_id INT NOT NULL,
    comment_text TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_parent (parent_type, parent_id)
);

CREATE TABLE votes (
    vote_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    vote_type ENUM('question', 'answer') NOT NULL,
    target_id INT NOT NULL,
    vote_value TINYINT CHECK (vote_value IN (-1, 1)),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    UNIQUE KEY unique_vote (user_id, vote_type, target_id)
);

CREATE TABLE leaderboard (
    user_id INT PRIMARY KEY,
    reputation_score INT DEFAULT 0,
    questions_asked INT DEFAULT 0,
    answers_posted INT DEFAULT 0,
    solutions_verified INT DEFAULT 0,
    total_upvotes INT DEFAULT 0,
    rank_position INT,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_reputation (reputation_score DESC)
);

-- ============================================
-- FOCUS MODE (USP) MODULE
-- ============================================

CREATE TABLE focus_sessions (
    session_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    session_type ENUM('test', 'coding', 'study') NOT NULL,
    reference_id INT,
    start_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    end_time TIMESTAMP NULL,
    duration_seconds INT,
    violation_count INT DEFAULT 0,
    focus_score DECIMAL(5,2) DEFAULT 100.00,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user (user_id)
);

CREATE TABLE focus_violations (
    violation_id INT PRIMARY KEY AUTO_INCREMENT,
    session_id INT NOT NULL,
    violation_type ENUM('tab_switch', 'window_blur', 'copy_paste', 'right_click', 'devtools') NOT NULL,
    violation_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    duration_seconds INT,
    FOREIGN KEY (session_id) REFERENCES focus_sessions(session_id) ON DELETE CASCADE
);

CREATE TABLE focus_analytics (
    analytics_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    date DATE NOT NULL,
    total_study_time INT DEFAULT 0,
    total_violations INT DEFAULT 0,
    average_focus_score DECIMAL(5,2) DEFAULT 100.00,
    sessions_count INT DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_date (user_id, date)
);

-- ============================================
-- CAREER TOOLKIT MODULE
-- ============================================

CREATE TABLE resume_templates (
    template_id INT PRIMARY KEY AUTO_INCREMENT,
    template_name VARCHAR(100) NOT NULL,
    template_slug VARCHAR(100) UNIQUE NOT NULL,
    preview_image VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE
);

INSERT INTO resume_templates (template_name, template_slug) VALUES
('Professional', 'professional'),
('Modern', 'modern'),
('Creative', 'creative'),
('Minimalist', 'minimalist');

CREATE TABLE resumes (
    resume_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    template_id INT,
    resume_title VARCHAR(255),
    personal_info JSON,
    education JSON,
    experience JSON,
    skills JSON,
    projects JSON,
    certifications JSON,
    achievements JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (template_id) REFERENCES resume_templates(template_id) ON DELETE SET NULL
);

CREATE TABLE document_categories (
    category_id INT PRIMARY KEY AUTO_INCREMENT,
    category_name VARCHAR(100) NOT NULL,
    category_slug VARCHAR(100) UNIQUE NOT NULL
);

INSERT INTO document_categories (category_name, category_slug) VALUES
('Certificates', 'certificates'),
('Marksheets', 'marksheets'),
('ID Proofs', 'id-proofs'),
('Other Documents', 'other');

CREATE TABLE documents (
    document_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    category_id INT,
    document_name VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255),
    file_path VARCHAR(500),
    file_size INT,
    file_type VARCHAR(50),
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES document_categories(category_id) ON DELETE SET NULL,
    INDEX idx_user (user_id)
);

-- ============================================
-- ADMIN PANEL MODULE
-- ============================================

CREATE TABLE admin_logs (
    log_id INT PRIMARY KEY AUTO_INCREMENT,
    admin_id INT NOT NULL,
    action_type VARCHAR(100) NOT NULL,
    table_name VARCHAR(100),
    record_id INT,
    action_description TEXT,
    ip_address VARCHAR(45),
    user_agent VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_admin (admin_id),
    INDEX idx_action (action_type)
);

CREATE TABLE support_tickets (
    ticket_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('open', 'in_progress', 'resolved', 'closed') DEFAULT 'open',
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    assigned_to INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_status (status)
);

CREATE TABLE ticket_replies (
    reply_id INT PRIMARY KEY AUTO_INCREMENT,
    ticket_id INT NOT NULL,
    user_id INT NOT NULL,
    reply_message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES support_tickets(ticket_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

CREATE TABLE system_settings (
    setting_id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type ENUM('string', 'integer', 'boolean', 'json') DEFAULT 'string',
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO system_settings (setting_key, setting_value, setting_type, description) VALUES
('site_name', 'Placement Portal', 'string', 'Website name'),
('site_email', 'admin@placementportal.com', 'string', 'Contact email'),
('maintenance_mode', 'false', 'boolean', 'Enable/disable maintenance mode'),
('max_file_upload_mb', '10', 'integer', 'Maximum file upload size in MB');

-- ============================================
-- VIEWS FOR REPORTS & ANALYTICS
-- ============================================

CREATE VIEW user_stats AS
SELECT 
    u.user_id,
    up.full_name,
    u.email,
    COUNT(DISTINCT aa.attempt_id) as total_tests_taken,
    AVG(aa.percentage) as avg_test_score,
    COUNT(DISTINCT cs.submission_id) as total_submissions,
    COUNT(DISTINCT CASE WHEN cs.status = 'accepted' THEN cs.submission_id END) as solved_problems,
    COALESCE(l.reputation_score, 0) as reputation,
    COALESCE(l.rank_position, 0) as community_rank
FROM users u
LEFT JOIN user_profiles up ON u.user_id = up.user_id
LEFT JOIN aptitude_attempts aa ON u.user_id = aa.user_id AND aa.status = 'completed'
LEFT JOIN coding_submissions cs ON u.user_id = cs.user_id
LEFT JOIN leaderboard l ON u.user_id = l.user_id
WHERE u.role_id = 1
GROUP BY u.user_id;

CREATE VIEW test_performance AS
SELECT 
    t.test_id,
    t.test_name,
    COUNT(DISTINCT aa.user_id) as total_attempts,
    AVG(aa.percentage) as average_score,
    MAX(aa.percentage) as highest_score,
    MIN(aa.percentage) as lowest_score,
    AVG(aa.duration_seconds/60) as avg_duration_minutes
FROM aptitude_tests t
LEFT JOIN aptitude_attempts aa ON t.test_id = aa.test_id AND aa.status = 'completed'
GROUP BY t.test_id;

-- ============================================
-- TRIGGERS FOR AUTOMATIC UPDATES
-- ============================================

DELIMITER $$

-- Update leaderboard on new question
CREATE TRIGGER after_question_insert
AFTER INSERT ON questions
FOR EACH ROW
BEGIN
    INSERT INTO leaderboard (user_id, questions_asked, reputation_score)
    VALUES (NEW.user_id, 1, 5)
    ON DUPLICATE KEY UPDATE 
        questions_asked = questions_asked + 1,
        reputation_score = reputation_score + 5;
END$$

-- Update leaderboard on new answer
CREATE TRIGGER after_answer_insert
AFTER INSERT ON answers
FOR EACH ROW
BEGIN
    INSERT INTO leaderboard (user_id, answers_posted, reputation_score)
    VALUES (NEW.user_id, 1, 10)
    ON DUPLICATE KEY UPDATE 
        answers_posted = answers_posted + 1,
        reputation_score = reputation_score + 10;
END$$

-- Update leaderboard on vote
CREATE TRIGGER after_vote_insert
AFTER INSERT ON votes
FOR EACH ROW
BEGIN
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
END$$

-- Update focus score after violations
CREATE TRIGGER after_violation_insert
AFTER INSERT ON focus_violations
FOR EACH ROW
BEGIN
    UPDATE focus_sessions 
    SET violation_count = violation_count + 1,
        focus_score = GREATEST(0, focus_score - 5)
    WHERE session_id = NEW.session_id;
END$$

DELIMITER ;

-- ============================================
-- INDEXES FOR PERFORMANCE OPTIMIZATION
-- ============================================

-- Additional composite indexes
CREATE INDEX idx_attempts_user_date ON aptitude_attempts(user_id, start_time);
CREATE INDEX idx_submissions_user_date ON coding_submissions(user_id, submitted_at);
CREATE INDEX idx_questions_user_date ON questions(user_id, created_at);

-- ============================================
-- DEFAULT ADMIN USER (Password: admin123)
-- ============================================

INSERT INTO users (email, password_hash, role_id, is_active, email_verified) VALUES
('admin@placementportal.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2, TRUE, TRUE);

INSERT INTO user_profiles (user_id, full_name, phone) VALUES
(1, 'System Administrator', '9999999999');

-- ============================================
-- SAMPLE DATA FOR TESTING
-- ============================================

-- Sample aptitude questions
INSERT INTO aptitude_questions (category_id, question_text, option_a, option_b, option_c, option_d, correct_answer, explanation, difficulty, created_by) VALUES
(1, 'What is 15% of 200?', '25', '30', '35', '40', 'b', '15% of 200 = (15/100) × 200 = 30', 'easy', 1),
(1, 'If a train travels 120 km in 2 hours, what is its speed?', '50 km/h', '60 km/h', '70 km/h', '80 km/h', 'b', 'Speed = Distance/Time = 120/2 = 60 km/h', 'easy', 1),
(2, 'Complete the series: 2, 6, 12, 20, ?', '28', '30', '32', '34', 'b', 'Differences are 4, 6, 8, so next is 10. 20 + 10 = 30', 'medium', 1),
(3, 'Choose the correctly spelled word:', 'Accommodate', 'Acommodate', 'Accomodate', 'Acomodate', 'a', 'Accommodate is the correct spelling', 'easy', 1);

-- Sample coding problem
INSERT INTO coding_problems (title, slug, description, difficulty, created_by) VALUES
('Two Sum', 'two-sum', 'Given an array of integers nums and an integer target, return indices of the two numbers such that they add up to target.', 'easy', 1);

INSERT INTO test_cases (problem_id, input_data, expected_output, is_sample) VALUES
(1, '[2,7,11,15]\n9', '[0,1]', TRUE),
(1, '[3,2,4]\n6', '[1,2]', TRUE);

-- ============================================
-- END OF DATABASE SCHEMA
-- ============================================
