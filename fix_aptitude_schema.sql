-- Safe fix for aptitude_tests table
-- Run each ALTER TABLE statement individually
-- Skip any that give "Duplicate column" errors - that means it already exists

-- Try to add difficulty column (skip if exists)
ALTER TABLE aptitude_tests 
ADD COLUMN difficulty ENUM('Easy', 'Medium', 'Hard') DEFAULT 'Medium';

-- Try to add description column (skip if exists)
ALTER TABLE aptitude_tests 
ADD COLUMN description TEXT;

-- Try to add passing_score column (skip if exists)
ALTER TABLE aptitude_tests 
ADD COLUMN passing_score INT;

-- Try to add updated_at column (skip if exists)
ALTER TABLE aptitude_tests 
ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Update existing tests with categories (safe to run multiple times)
UPDATE aptitude_tests SET category = 'Quantitative' WHERE category IS NULL OR (test_name LIKE '%quant%' OR test_name LIKE '%math%');
UPDATE aptitude_tests SET category = 'Logical' WHERE category IS NULL OR (test_name LIKE '%logic%' OR test_name LIKE '%reason%');
UPDATE aptitude_tests SET category = 'Verbal' WHERE category IS NULL OR (test_name LIKE '%verbal%' OR test_name LIKE '%english%');
