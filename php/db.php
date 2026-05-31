<?php
$host = 'localhost';
$user = 'root'; // default XAMPP user
$pass = '';     // default XAMPP password
$db   = 'hr_ai_system';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    // Set PDO error mode to exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Set default fetch mode to associative array
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Auto-create announcements table if not exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS announcements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        content TEXT NOT NULL,
        priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        author VARCHAR(100) DEFAULT 'Administration'
    )");
    
    // Seed announcements if empty
    $stmt_count = $pdo->query("SELECT COUNT(*) FROM announcements");
    if ($stmt_count->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO announcements (title, content, priority, author) VALUES 
        ('AI Model Retraining Complete', 'The AI Remuneration & Attrition prediction model has been retrained with the latest employee datasets. Prediction accuracy is now at 92.4%.', 'medium', 'AI System Administrator'),
        ('Quarterly Performance Appraisals', 'All managers are requested to complete and submit their team evaluations by the end of this week. Please update performance scores in the portal.', 'high', 'Director'),
        ('System Maintenance Notice', 'The Portal will undergo scheduled database optimization on Sunday, May 24, from 02:00 AM to 04:00 AM. The system will be temporarily offline.', 'low', 'IT Support Team')");
    }
} catch(PDOException $e) {
    // Log the real error server-side; never expose it to the browser
    error_log("Database Connection failed: " . $e->getMessage());
    http_response_code(503);
    echo "<!DOCTYPE html><html><head><title>Service Unavailable</title></head>
          <body style='font-family:sans-serif;text-align:center;padding:4rem;'>
          <h1>Service Temporarily Unavailable</h1>
          <p>We are unable to connect to the database. Please try again later.</p>
          </body></html>";
    exit();
}
?>

