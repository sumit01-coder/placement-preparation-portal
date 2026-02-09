-- ==================================================
-- PLACEMENT PORTAL - DATABASE MIGRATION SCRIPT
-- Safely adds missing columns to existing tables
-- ==================================================

-- Add missing columns to roles table
ALTER TABLE roles 
ADD COLUMN IF NOT EXISTS description VARCHAR(255) AFTER role_name;

-- Add missing columns to users table
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS is_active BOOLEAN DEFAULT TRUE AFTER role_id,
ADD COLUMN IF NOT EXISTS email_verified BOOLEAN DEFAULT FALSE AFTER is_active,
ADD COLUMN IF NOT EXISTS last_login TIMESTAMP NULL AFTER created_at;

-- Add missing columns to user_profiles table
ALTER TABLE user_profiles 
ADD COLUMN IF NOT EXISTS phone VARCHAR(20) AFTER full_name,
ADD COLUMN IF NOT EXISTS college VARCHAR(255) AFTER phone,
ADD COLUMN IF NOT EXISTS branch VARCHAR(100) AFTER college,
ADD COLUMN IF NOT EXISTS graduation_year INT AFTER branch,
ADD COLUMN IF NOT EXISTS avatar_url VARCHAR(255) AFTER graduation_year,
ADD COLUMN IF NOT EXISTS bio TEXT AFTER avatar_url;

-- Add missing columns to aptitude_tests table
ALTER TABLE aptitude_tests 
ADD COLUMN IF NOT EXISTS category ENUM('Quantitative', 'Logical', 'Verbal') NOT NULL DEFAULT 'Quantitative' AFTER test_name,
ADD COLUMN IF NOT EXISTS difficulty ENUM('Easy', 'Medium', 'Hard') DEFAULT 'Medium' AFTER category,
ADD COLUMN IF NOT EXISTS passing_score INT AFTER total_questions,
ADD COLUMN IF NOT EXISTS description TEXT AFTER passing_score,
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

-- Add missing columns to coding_problems table
ALTER TABLE coding_problems 
ADD COLUMN IF NOT EXISTS slug VARCHAR(255) AFTER title,
ADD COLUMN IF NOT EXISTS input_format TEXT AFTER description,
ADD COLUMN IF NOT EXISTS output_format TEXT AFTER input_format,
ADD COLUMN IF NOT EXISTS constraints TEXT AFTER output_format,
ADD COLUMN IF NOT EXISTS sample_input TEXT AFTER constraints,
ADD COLUMN IF NOT EXISTS sample_output TEXT AFTER sample_input,
ADD COLUMN IF NOT EXISTS explanation TEXT AFTER sample_output,
ADD COLUMN IF NOT EXISTS tags JSON AFTER explanation,
ADD COLUMN IF NOT EXISTS time_limit INT DEFAULT 2 AFTER tags,
ADD COLUMN IF NOT EXISTS memory_limit INT DEFAULT 256 AFTER time_limit,
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

-- Add unique constraint to slug if it doesn't exist
ALTER TABLE coding_problems 
ADD UNIQUE INDEX IF NOT EXISTS idx_slug (slug);

-- Add missing columns to submissions table
ALTER TABLE submissions 
ADD COLUMN IF NOT EXISTS runtime INT AFTER status,
ADD COLUMN IF NOT EXISTS memory INT AFTER runtime,
ADD COLUMN IF NOT EXISTS test_cases_passed INT DEFAULT 0 AFTER memory,
ADD COLUMN IF NOT EXISTS total_test_cases INT AFTER test_cases_passed,
ADD COLUMN IF NOT EXISTS error_message TEXT AFTER total_test_cases;

-- Now insert roles with description
INSERT INTO roles (role_name, description) VALUES
('student', 'Regular student user'),
('admin', 'Administrator with full access')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- ==================================================
-- Verification Queries (Run after migration)
-- ==================================================

-- Check tables structure
-- DESCRIBE roles;
-- DESCRIBE users;
-- DESCRIBE user_profiles;
-- DESCRIBE aptitude_tests;
-- DESCRIBE coding_problems;

-- ==================================================
-- SUCCESS!
-- ==================================================
