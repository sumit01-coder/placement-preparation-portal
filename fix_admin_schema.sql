-- Fix for Admin Dashboard Error: Table 'user_activity' doesn't exist

CREATE TABLE IF NOT EXISTS user_activity (
    activity_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    activity_type VARCHAR(50) NOT NULL,
    details TEXT,
    activity_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_time (activity_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert some sample data to populate the dashboard immediately
INSERT INTO user_activity (user_id, activity_type, details) 
SELECT user_id, 'login', 'User logged in' FROM users LIMIT 5;
