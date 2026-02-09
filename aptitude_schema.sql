-- Aptitude Module Database Schema

-- Aptitude Tests Table
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

-- Aptitude Questions Table
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
    INDEX idx_test_id (test_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Test Attempts Table
CREATE TABLE IF NOT EXISTS test_attempts (
    attempt_id INT AUTO_INCREMENT PRIMARY KEY,
    test_id INT NOT NULL,
    user_id INT NOT NULL,
    score INT NOT NULL,
    total_questions INT NOT NULL,
    time_taken INT, -- in minutes
    percentage DECIMAL(5,2),
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (test_id) REFERENCES aptitude_tests(test_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_test_id (test_id),
    INDEX idx_attempted_at (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Test Answers Table (stores individual question answers)
CREATE TABLE IF NOT EXISTS test_answers (
    answer_id INT AUTO_INCREMENT PRIMARY KEY,
    attempt_id INT NOT NULL,
    question_id INT NOT NULL,
    selected_answer ENUM('A', 'B', 'C', 'D'),
    is_correct BOOLEAN DEFAULT FALSE,
    time_spent INT, -- in seconds
    FOREIGN KEY (attempt_id) REFERENCES test_attempts(attempt_id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES aptitude_questions(question_id) ON DELETE CASCADE,
    INDEX idx_attempt_id (attempt_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert Sample Tests
INSERT INTO aptitude_tests (test_name, category, difficulty, duration_minutes, total_questions, description) VALUES
('Quantitative Aptitude - Basic', 'Quantitative', 'Easy', 30, 20, 'Basic quantitative aptitude covering numbers, percentages, and ratios'),
('Logical Reasoning - Pattern Recognition', 'Logical', 'Medium', 45, 25, 'Test your pattern recognition and logical thinking skills'),
('Verbal Ability - Reading Comprehension', 'Verbal', 'Medium', 40, 20, 'Assess your reading comprehension and grammar skills'),
('Advanced Quantitative', 'Quantitative', 'Hard', 60, 30, 'Advanced problems on algebra, geometry, and data interpretation'),
('Logical Puzzles & Brain Teasers', 'Logical', 'Hard', 50, 25, 'Challenging puzzles and logical problems');
