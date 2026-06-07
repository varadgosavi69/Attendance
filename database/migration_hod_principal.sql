-- Migration: HOD + Principal roles, HOD attendance summary table
-- Run ONCE after initial setup.

-- 1. Add department column to users (for HOD dept linkage)
ALTER TABLE users ADD COLUMN IF NOT EXISTS department VARCHAR(50) NULL DEFAULT NULL AFTER faculty_id;

-- 2. HOD attendance summary table
CREATE TABLE IF NOT EXISTS hod_attendance_summary (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    department      VARCHAR(50)    NOT NULL,
    semester        INT            NOT NULL,
    year            INT            NOT NULL,
    date            DATE           NOT NULL,
    total_students  INT            NOT NULL DEFAULT 0,
    present_count   INT            NOT NULL DEFAULT 0,
    attendance_percentage DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    uploaded_by     INT            NOT NULL,
    uploaded_at     TIMESTAMP      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_dept_sem_date (department, semester, date),
    FOREIGN KEY (uploaded_by) REFERENCES users(user_id) ON DELETE CASCADE
);

-- 3. Seed Principal user (password: principal123)
INSERT IGNORE INTO users (username, password_hash, email, full_name, role, department) VALUES
('principal', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'principal@jdcoem.ac.in', 'Dr. R.S. Pande', 'principal', NULL);

-- 4. Seed HOD users (password: hod123)
INSERT IGNORE INTO users (username, password_hash, email, full_name, role, department) VALUES
('hod_cse', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'hod.cse@jdcoem.ac.in', 'Dr. Imran Sheikh', 'hod', 'CSE'),
('hod_me',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'hod.me@jdcoem.ac.in',  'Dr. Priya Sharma',  'hod', 'ME'),
('hod_ee',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'hod.ee@jdcoem.ac.in',  'Dr. Rajesh Kumar',  'hod', 'EE');
-- NOTE: password hash above = 'password' via bcrypt cost 10 (Laravel default)
-- Actual passwords: principal123 and hod123 are set via PHP password_hash below.
-- The hash '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi' = 'password'
-- We will update with correct hashes via the setup script.
