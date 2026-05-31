CREATE DATABASE IF NOT EXISTS hr_ai_system;
USE hr_ai_system;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'hr', 'employee') DEFAULT 'admin'
);

CREATE TABLE IF NOT EXISTS employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id VARCHAR(10) NOT NULL UNIQUE,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    department VARCHAR(50) NOT NULL,
    job_role VARCHAR(50) NOT NULL,
    date_joined DATE NOT NULL,
    status VARCHAR(255) NOT NULL,
    age INT,
    years_at_company DECIMAL(5, 2),
    job_satisfaction INT,
    has_left VARCHAR(10) NOT NULL,
    left_date DATE
);

CREATE TABLE IF NOT EXISTS attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    month VARCHAR(20) NOT NULL,
    year INT NOT NULL,
    total_days INT NOT NULL,
    days_present INT NOT NULL,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS salary (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    base_salary DECIMAL(10, 2) NOT NULL,
    bonus DECIMAL(10, 2) DEFAULT 0,
    deductions DECIMAL(10, 2) DEFAULT 0,
    net_salary DECIMAL(10, 2) NOT NULL,
    month VARCHAR(20) NOT NULL,
    year INT NOT NULL,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS performance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    productivity_score DECIMAL(5, 2) NOT NULL,
    manager_rating DECIMAL(3, 2) NOT NULL,
    projects_completed INT NOT NULL,
    evaluation_date DATE NOT NULL,
    hours_worked_per_week INT DEFAULT 40,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
);


CREATE TABLE IF NOT EXISTS training_data_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    total_records INT,
    records_used INT,
    training_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_version VARCHAR(50),
    notes TEXT
);

INSERT INTO users (username, password, role) VALUES ('admin', '$2y$10$UEhQ6kBEiJCj2tcFqR4mPucap44K42b2FbTA4n1pRNjQNrUcmVhTa', 'admin') ON DUPLICATE KEY UPDATE id=id;

CREATE INDEX idx_employee_status ON employees(status);
CREATE INDEX idx_employee_department ON employees(department);
CREATE INDEX idx_performance_employee ON performance(employee_id);
CREATE INDEX idx_salary_employee ON salary(employee_id);
CREATE INDEX idx_attendance_employee ON attendance(employee_id);

CREATE TABLE IF NOT EXISTS announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    author VARCHAR(100) DEFAULT 'Administration'
);

CREATE INDEX idx_announcements_created ON announcements(created_at);

INSERT INTO announcements (title, content, priority, author) VALUES 
('AI Model Retraining Complete', 'The AI Remuneration & Attrition prediction model has been retrained with the latest employee datasets. Prediction accuracy is now at 92.4%.', 'medium', 'AI System Administrator'),
('Quarterly Performance Appraisals', 'All managers are requested to complete and submit their team evaluations by the end of this week. Please update performance scores in the portal.', 'high', 'Director'),
('System Maintenance Notice', 'The Portal will undergo scheduled database optimization on Sunday, May 24, from 02:00 AM to 04:00 AM. The system will be temporarily offline.', 'low', 'IT Support Team')
ON DUPLICATE KEY UPDATE id=id;
