-- Add Sample Coding Problems

-- 1. Reverse String
INSERT INTO coding_problems (title, slug, description, input_format, output_format, constraints, difficulty, tags, time_limit_ms, memory_limit_mb, created_by)
VALUES 
('Reverse String', 'reverse-string', 'Write a program that takes a string as input and prints its reverse.', 'A single line containing a string S.', 'Print the reversed string.', '1 <= |S| <= 1000', 'easy', 'Strings, Basic', 1000, 256, 1);

SET @problem_id_1 = LAST_INSERT_ID();

INSERT INTO test_cases (problem_id, input_data, expected_output, is_sample) VALUES 
(@problem_id_1, 'hello', 'olleh', 1),
(@problem_id_1, 'world', 'dlrow', 1),
(@problem_id_1, 'OpenAI', 'IAnepO', 0),
(@problem_id_1, '12345', '54321', 0);

-- 2. Factorial
INSERT INTO coding_problems (title, slug, description, input_format, output_format, constraints, difficulty, tags, time_limit_ms, memory_limit_mb, created_by)
VALUES 
('Find Factorial', 'find-factorial', 'Write a program to find the factorial of a given number N.', 'A single integer N.', 'Print the factorial of N.', '0 <= N <= 10', 'medium', 'Math, Recursion', 1000, 256, 1);

SET @problem_id_2 = LAST_INSERT_ID();

INSERT INTO test_cases (problem_id, input_data, expected_output, is_sample) VALUES 
(@problem_id_2, '5', '120', 1),
(@problem_id_2, '3', '6', 1),
(@problem_id_2, '0', '1', 0),
(@problem_id_2, '1', '1', 0),
(@problem_id_2, '10', '3628800', 0);
