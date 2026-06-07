# 🎓 College Attendance Email Notification System

A premium web-based portal for teachers to manage student attendance and automate daily reporting. Built with a focus on ease of use, aesthetics, and handling records at scale.

## ✨ Core Features

- **Premium Teacher Portal**: 
    - 💎 Glassmorphism design for a modern, professional look.
    - 📊 Real-time Dashboard with attendance insights.
    - 🔍 Advanced filtering by Year, Branch, and Semester.
- **Smart Management**:
    - 👥 Student Management with bulk CSV upload.
    - 📚 Subject Management with easy CSV import.
- **Automation & Logs**:
    - 📨 **Automated Reports**: Sends personalized emails to both present and absent students.
    - 🖱️ **One-Click Trigger**: Send all daily reports directly from the web portal.
    - 📜 **System Logs**: View execution history and delivery status directly in the browser.
    - 🪟 **Windows Utility**: One-click `.bat` script for manual automation triggers.

## 🛠️ System Requirements

- **PHP**: 7.4 or higher
- **MySQL/MariaDB**: 5.7+ (Recommended for XAMPP)
- **Composer**: For dependency management
- **Web Server**: Apache (XAMPP) or PHP built-in server

## ⚙️ Setup & Installation

### 1. Database Configuration
1. Open **phpMyAdmin** (`http://localhost/phpmyadmin`).
2. Create a database named `attendance_db`.
3. Import `database/schema_mysql.sql` into the new database.
4. *Note: If using XAMPP on port 3307, ensure your `.env` reflects this.*

### 2. Environment Setup
Copy `.env.example` to `.env` and configure:
- `DB_HOST`, `DB_PORT` (3306 or 3307), `DB_NAME`, `DB_USER`, `DB_PASS`.
- `SMTP_HOST`, `SMTP_USERNAME`, `SMTP_PASSWORD` (Use Gmail App Password).
- `COLLEGE_NAME` to your institution's name.

### 3. Install Dependencies
```bash
composer install
```

### 4. Run the Project
- **Option A (XAMPP)**: Move the folder to `C:\xampp\htdocs\` and visit `http://localhost/attendance-email-system/public`.
- **Option B (PHP Server)**: Run `php -S localhost:8000 -t public` in the project root.

## 🚀 Usage

1. **Login**: Use your default administrator credentials.
2. **Mark Attendance**: Navigate to the "Attendance" page, select filters, and mark students.
3. **Send Emails**: 
    - Go to the **Logs** page and click **"Send Daily Reports Now"**.
    - Or run the **`Run_Attendance_Mailer.bat`** file from the root folder.

## 📂 Project Structure
- `public/`: Web root (UI, API endpoints).
- `src/`: Backend logic (Database, Attendance Processor, Email Sender).
- `cron/`: Core automation script logic.
- `logs/`: Daily execution logs.
- `database/`: SQL schemas and seed data.

---
*Created for JD College Engineering & Management*
