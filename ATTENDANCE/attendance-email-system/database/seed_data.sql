-- Seed Data for Attendance System

-- Clear existing data (optional, but good for a fresh start)
-- DELETE FROM attendance;
-- DELETE FROM students;
-- DELETE FROM subjects;
-- DELETE FROM faculty;

-- 1. Insert Faculty
INSERT INTO faculty (faculty_name, email, department) VALUES 
('Imran Sheikh', 'gosavivarad6905@gmail.com', 'CSE'),
('Varad Gosavi', 'gosavivarad6905@gmail.com', 'CSE'),
('Priya Sharma', 'gosavivarad6905@gmail.com', 'ME'),
('Rajesh Kumar', 'gosavivarad6905@gmail.com', 'EE');

-- 2. Insert Subjects
INSERT INTO subjects (subject_name, subject_code, department, semester) VALUES 
('Computer Networks', 'CS401', 'CSE', 4),
('Operating Systems', 'CS402', 'CSE', 4),
('Database Management', 'CS403', 'CSE', 4),
('Data Structures', 'CS201', 'CSE', 2),
('Machine Design', 'ME601', 'ME', 6),
('Power Electronics', 'EE401', 'EE', 4);

-- 3. Insert Students (Sample Batch 1: CSE, Semester 4)
INSERT INTO students (roll_number, student_name, email, department, semester) VALUES 
('CSE22001', 'Ayush Gajbhiye', 'ayush@student.edu', 'CSE', 4),
('CSE22002', 'Rahul Verma', 'rahul@student.edu', 'CSE', 4),
('CSE22003', 'Sneha Patil', 'sneha@student.edu', 'CSE', 4),
('CSE22004', 'Amit Singh', 'amit@student.edu', 'CSE', 4),
('CSE22005', 'Nisha Gupta', 'nisha@student.edu', 'CSE', 4);

-- Sample Batch 2: CSE, Semester 2
INSERT INTO students (roll_number, student_name, email, department, semester) VALUES 
('CSE23001', 'Karan Johar', 'karan@student.edu', 'CSE', 2),
('CSE23002', 'Juhi Chawla', 'juhi@student.edu', 'CSE', 2);

-- Sample Batch 3: ME, Semester 6
INSERT INTO students (roll_number, student_name, email, department, semester) VALUES 
('ME21001', 'Vijay Mallya', 'vijay@student.edu', 'ME', 6),
('ME21002', 'Harshad Mehta', 'harshad@student.edu', 'ME', 6);

-- Sample Batch 4: EE, Semester 4
INSERT INTO students (roll_number, student_name, email, department, semester) VALUES
('EE22001', 'Nikola Tesla', 'tesla@student.edu', 'EE', 4),
('EE22002', 'Thomas Edison', 'edison@student.edu', 'EE', 4);

-- 5. Link admin user to faculty_id=1 (Imran Sheikh) — must run after faculty rows are inserted
UPDATE users SET faculty_id = 1 WHERE username = 'admin';
