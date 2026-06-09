-- Migration: Link users to faculty records + create faculty_subjects mapping
-- Run this ONCE after the initial schema has been set up.

-- 1. Add faculty_id linkage to users table
ALTER TABLE users ADD COLUMN faculty_id INT NULL DEFAULT NULL AFTER role;
ALTER TABLE users ADD CONSTRAINT fk_user_faculty FOREIGN KEY (faculty_id) REFERENCES faculty(faculty_id) ON DELETE SET NULL;

-- 2. Create faculty_subjects assignment table (which faculty teaches which subject)
CREATE TABLE IF NOT EXISTS faculty_subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    faculty_id INT NOT NULL,
    subject_id INT NOT NULL,
    UNIQUE(faculty_id, subject_id),
    FOREIGN KEY (faculty_id) REFERENCES faculty(faculty_id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(subject_id) ON DELETE CASCADE
);

-- 3. Seed: Assign faculty to subjects
-- Imran Sheikh (faculty_id=1) -> CS401, CS402 (CSE Sem 4)
-- Varad Gosavi (faculty_id=2) -> CS403, CS201 (CSE)
-- Priya Sharma (faculty_id=3) -> ME601
-- Rajesh Kumar (faculty_id=4) -> EE401
INSERT IGNORE INTO faculty_subjects (faculty_id, subject_id) VALUES
(1, 1), -- Imran -> Computer Networks
(1, 2), -- Imran -> Operating Systems
(2, 3), -- Varad -> Database Management
(2, 4), -- Varad -> Data Structures
(3, 5), -- Priya -> Machine Design
(4, 6); -- Rajesh -> Power Electronics

-- 4. Link admin user to faculty_id=1 (Imran Sheikh)
-- NOTE: This UPDATE is intentionally in 05_seed.sql (runs after faculty rows are inserted)
