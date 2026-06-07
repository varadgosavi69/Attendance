-- Migration: Detention tracking table
-- Run this ONCE after the initial schema has been set up.

CREATE TABLE IF NOT EXISTS detention (
    detention_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    month DATE NOT NULL,                          -- First day of the month e.g. 2026-02-01
    total_classes INT NOT NULL DEFAULT 0,
    attended_classes INT NOT NULL DEFAULT 0,
    attendance_percentage DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    is_detained TINYINT(1) NOT NULL DEFAULT 0,    -- 1 = Detained, 0 = OK
    notified_at TIMESTAMP NULL DEFAULT NULL,      -- When detention email was sent
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE(student_id, month),
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE
);
