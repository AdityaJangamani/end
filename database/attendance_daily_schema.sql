-- Daily attendance log — one row per employee per date
-- Run this once in phpMyAdmin to add the new table

USE hr_ai_system;

CREATE TABLE IF NOT EXISTS attendance_daily (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    employee_id    INT NOT NULL,
    date           DATE NOT NULL,
    status         ENUM('present', 'absent') DEFAULT 'present',
    signed_in_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_emp_date (employee_id, date),
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
);
