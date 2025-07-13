-- Electronic Voting System Database
-- Dynamic system - users register themselves, no pre-populated data

CREATE DATABASE IF NOT EXISTS voting_system;
USE voting_system;

-- 1. Users table (stores voter and admin info)
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'voter') DEFAULT 'voter',
    full_name VARCHAR(100) NOT NULL,
    voted BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Elections table (manages election instances)
CREATE TABLE elections (
    election_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(100) NOT NULL,
    description TEXT,
    start_date DATETIME NOT NULL,
    end_date DATETIME NOT NULL,
    status ENUM('active', 'inactive', 'completed') DEFAULT 'inactive',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 3. Candidates table (stores candidate details)
CREATE TABLE candidates (
    candidate_id INT AUTO_INCREMENT PRIMARY KEY,
    election_id INT NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    position VARCHAR(50) NOT NULL,
    photo VARCHAR(255),
    bio TEXT,
    vote_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (election_id) REFERENCES elections(election_id) ON DELETE CASCADE
);

-- 4. Votes table (records voting choices with enhanced security)
CREATE TABLE votes (
    vote_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    candidate_id INT NOT NULL,
    election_id INT NOT NULL,
    vote_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (candidate_id) REFERENCES candidates(candidate_id) ON DELETE CASCADE,
    FOREIGN KEY (election_id) REFERENCES elections(election_id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_election (user_id, election_id),
    INDEX idx_election_votes (election_id),
    INDEX idx_candidate_votes (candidate_id),
    INDEX idx_vote_time (vote_time)
);

-- Create additional indexes for better performance
CREATE INDEX idx_elections_status ON elections(status);
CREATE INDEX idx_elections_dates ON elections(start_date, end_date);
CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_candidates_election ON candidates(election_id);

-- Optional: Insert one sample election (can be removed if you want completely empty)
-- INSERT INTO elections (title, description, start_date, end_date, status) VALUES
-- ('Student Council Election 2025', 'Annual election for student representatives', '2025-07-15 08:00:00', '2025-07-17 18:00:00', 'active');

COMMIT;

-- Display setup completion message
SELECT 'Database created successfully!' as message;
SELECT 'System ready for user registration!' as status;
SELECT 'No pre-populated data - users must register accounts' as note;
