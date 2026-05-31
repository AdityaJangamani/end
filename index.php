<?php
session_start();
require 'php/db.php';

// Fetch dynamic system metrics
$totalEmployees = 0;
$activeToday = 0;
$avgProductivity = 0.0;
$modelAccuracy = 'N/A';

try {
    // Total Employees count
    $totalEmployees = $pdo->query("SELECT COUNT(*) FROM employees")->fetchColumn();
    
    // Active staff today count (signed in)
    $activeToday = $pdo->query("SELECT COUNT(DISTINCT employee_id) FROM attendance_daily WHERE date = CURDATE() AND status = 'present'")->fetchColumn();
    
    // Avg Productivity score
    $avgProductivityVal = $pdo->query("SELECT AVG(productivity_score) FROM performance")->fetchColumn();
    if ($avgProductivityVal !== null) {
        $avgProductivity = round($avgProductivityVal, 1);
    }
    
    // Model Status accuracy from latest run log
    $latestLog = $pdo->query("SELECT * FROM training_data_log ORDER BY id DESC LIMIT 1")->fetch();
    if ($latestLog && !empty($latestLog['notes'])) {
        $metrics = json_decode($latestLog['notes'], true);
        if ($metrics && isset($metrics['attrition_accuracy'])) {
            $modelAccuracy = number_format($metrics['attrition_accuracy'] * 100, 1) . '%';
        }
    }
} catch (PDOException $e) {
    // Fallback if queries fail or table structure is not fully set up
}

// Fetch announcements
$announcements = [];
try {
    $stmt = $pdo->query("SELECT * FROM announcements ORDER BY created_at DESC LIMIT 5");
    $announcements = $stmt->fetchAll();
} catch (PDOException $e) {
    // Fallback if table doesn't exist
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Employee Analytics System</title>
    <meta name="description" content="An intelligent portal for employee management, remuneration benchmarking, performance insights, and AI attrition predictions.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <style>
        :root {
            --font-display: 'Outfit', sans-serif;
            --font-sans: 'Plus Jakarta Sans', sans-serif;
        }

        body {
            font-family: var(--font-sans);
            background: radial-gradient(circle at 50% 0%, #151F32 0%, #0B0F19 80%);
            overflow-x: hidden;
        }

        h1, h2, h3, .brand-title {
            font-family: var(--font-display);
        }

        .landing-wrapper {
            max-width: 1200px;
            margin: 0 auto;
            padding: 3rem 2rem 5rem 2rem;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        /* === Navigation / Header === */
        .landing-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 4rem;
        }

        .logo-wrap {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }

        .logo-icon {
            width: 44px;
            height: 44px;
            background: linear-gradient(135deg, #4F46E5, #6366F1);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            box-shadow: 0 0 20px rgba(79, 70, 229, 0.4);
        }

        .logo-text {
            font-size: 1.5rem;
            font-weight: 800;
            color: #fff;
            letter-spacing: -0.02em;
        }

        .logo-text span {
            color: #818CF8;
        }

        /* === Hero Section === */
        .hero-section {
            text-align: center;
            max-width: 800px;
            margin: 0 auto 5rem auto;
            position: relative;
        }

        .badge-pill {
            background: rgba(79, 70, 229, 0.15);
            border: 1px solid rgba(79, 70, 229, 0.3);
            color: #818CF8;
            padding: 0.5rem 1.25rem;
            border-radius: 9999px;
            font-size: 0.85rem;
            font-weight: 600;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            display: inline-block;
            margin-bottom: 1.5rem;
            animation: pulse 2s infinite alternate;
        }

        @keyframes pulse {
            0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(79, 70, 229, 0.2); }
            100% { transform: scale(1.02); box-shadow: 0 0 15px 5px rgba(79, 70, 229, 0.1); }
        }

        .hero-title {
            font-size: 3.5rem;
            font-weight: 800;
            line-height: 1.15;
            letter-spacing: -0.03em;
            background: linear-gradient(135deg, #FFFFFF 30%, #A5B4FC 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 1.5rem;
        }

        .hero-subtitle {
            font-size: 1.2rem;
            color: #94A3B8;
            line-height: 1.7;
            max-width: 650px;
            margin: 0 auto;
        }

        /* === Portals Container === */
        .portals-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 2rem;
            margin-bottom: 5rem;
        }

        .portal-card {
            padding: 2.5rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 320px;
            cursor: pointer;
        }

        .portal-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(180deg, rgba(255,255,255,0.03) 0%, rgba(255,255,255,0) 100%);
            opacity: 0;
            transition: opacity 0.3s;
        }

        .portal-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
            border-color: rgba(99, 102, 241, 0.35);
        }

        .portal-card:hover::before {
            opacity: 1;
        }

        .portal-icon {
            font-size: 2.5rem;
            margin-bottom: 1.5rem;
            background: rgba(255, 255, 255, 0.05);
            width: 64px;
            height: 64px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 16px;
            color: #818CF8;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        .portal-title {
            font-size: 1.6rem;
            font-weight: 700;
            color: #fff;
            margin-bottom: 0.75rem;
        }

        .portal-desc {
            font-size: 0.95rem;
            color: #94A3B8;
            line-height: 1.6;
            margin-bottom: 2rem;
            flex-grow: 1;
        }

        .portal-action-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            font-size: 1rem;
            color: #fff;
            text-decoration: none;
            transition: gap 0.2s;
        }

        .portal-card:hover .portal-action-btn {
            color: #818CF8;
            gap: 12px;
        }

        .arrow-icon {
            transition: transform 0.2s;
        }

        .portal-card:hover .arrow-icon {
            transform: translateX(4px);
        }

        /* === Stats Dashboard === */
        .stats-dashboard {
            padding: 2.5rem;
            margin-bottom: 5rem;
            position: relative;
        }

        .stats-title-main {
            font-size: 1.4rem;
            font-weight: 700;
            color: #fff;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
        }

        .stat-box {
            background: rgba(255,255,255,0.02);
            border: 1px solid rgba(255,255,255,0.05);
            padding: 1.5rem;
            border-radius: 12px;
            text-align: left;
            transition: all 0.2s;
        }

        .stat-box:hover {
            background: rgba(255,255,255,0.04);
            border-color: rgba(255,255,255,0.1);
        }

        .stat-num {
            font-size: 2.2rem;
            font-weight: 800;
            color: #fff;
            line-height: 1;
            margin-bottom: 0.5rem;
            font-family: var(--font-display);
        }

        .stat-num.gradient-text-1 {
            background: linear-gradient(135deg, #818CF8, #C084FC);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .stat-num.gradient-text-2 {
            background: linear-gradient(135deg, #34D399, #6EE7B7);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .stat-num.gradient-text-3 {
            background: linear-gradient(135deg, #FBBF24, #FDE68A);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .stat-lbl {
            font-size: 0.85rem;
            color: #94A3B8;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        /* === Announcements bulletin === */
        .bulletin-panel {
            padding: 2.5rem;
            margin-bottom: 4rem;
        }

        .bulletin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.75rem;
        }

        .bulletin-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .announcements-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .announcement-item {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            padding: 1.5rem;
            transition: all 0.2s;
            position: relative;
        }

        .announcement-item:hover {
            border-color: rgba(255, 255, 255, 0.1);
            background: rgba(255, 255, 255, 0.04);
            transform: translateX(4px);
        }

        .announcement-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1rem;
            margin-bottom: 0.75rem;
            flex-wrap: wrap;
        }

        .announcement-meta {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.8rem;
            color: #64748B;
        }

        .announcement-title {
            font-size: 1.15rem;
            font-weight: 600;
            color: #fff;
        }

        .announcement-content {
            font-size: 0.95rem;
            color: #94A3B8;
            line-height: 1.6;
        }

        .priority-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
        }

        .priority-dot.high { background-color: var(--danger); box-shadow: 0 0 8px var(--danger); }
        .priority-dot.medium { background-color: var(--warning); box-shadow: 0 0 8px var(--warning); }
        .priority-dot.low { background-color: var(--secondary); box-shadow: 0 0 8px var(--secondary); }

        .announcement-badge {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            padding: 0.2rem 0.6rem;
            border-radius: 6px;
        }

        .announcement-badge.high { background: rgba(239, 68, 68, 0.15); color: #F87171; border: 1px solid rgba(239, 68, 68, 0.25); }
        .announcement-badge.medium { background: rgba(245, 158, 11, 0.15); color: #FBBF24; border: 1px solid rgba(245, 158, 11, 0.25); }
        .announcement-badge.low { background: rgba(16, 185, 129, 0.15); color: #34D399; border: 1px solid rgba(16, 185, 129, 0.25); }

        .empty-bulletin {
            text-align: center;
            padding: 2rem;
            color: #64748B;
            font-size: 0.95rem;
        }

        /* === Footer === */
        .landing-footer {
            border-top: 1px solid rgba(255, 255, 255, 0.05);
            padding-top: 2rem;
            text-align: center;
            color: #475569;
            font-size: 0.85rem;
        }

        /* === Media Queries === */
        @media (max-width: 768px) {
            .hero-title { font-size: 2.5rem; }
            .portals-grid { grid-template-columns: 1fr; }
            .landing-header { margin-bottom: 2.5rem; }
            .landing-wrapper { padding: 2rem 1rem 3rem 1rem; }
        }
    </style>
</head>
<body class="animate-fade-in">
    <div class="landing-wrapper">
        <!-- Header -->
        <header class="landing-header">
            <a href="index.php" class="logo-wrap">
                <div class="logo-icon">📊</div>
                <div class="logo-text">AI<span>.Portal</span></div>
            </a>
            <div class="header-status">
                <span style="font-size: 0.85rem; color: #64748B;">System Status: <span class="badge badge-success">Online</span></span>
            </div>
        </header>

        <!-- Hero Section -->
        <section class="hero-section">
            <span class="badge-pill">Intelligence-Driven Workforce Management</span>
            <h1 class="hero-title">Predict the Future of Your Workforce</h1>
            <p class="hero-subtitle">
                A premium, AI-powered system designed to analyze employee satisfaction, optimize compensation structures, predict attrition risks, and offer data-driven organizational insights.
            </p>
        </section>

        <!-- Portals Grid -->
        <section class="portals-grid">
            <!-- Admin Portal -->
            <div class="portal-card glass-panel" onclick="location.href='auth/admin_login.php'">
                <div>
                    <div class="portal-icon">🔑</div>
                    <h2 class="portal-title">Administrator</h2>
                    <p class="portal-desc">
                        Manage workforce databases, record daily attendance, design payroll structures, approve payslips, and build/run predictive AI models.
                    </p>
                </div>
                <a href="auth/admin_login.php" class="portal-action-btn">
                    Enter Admin Portal <span class="arrow-icon">&rarr;</span>
                </a>
            </div>

            <!-- Employee Portal -->
            <div class="portal-card glass-panel" onclick="location.href='auth/employee_login.php'">
                <div>
                    <div class="portal-icon">👤</div>
                    <h2 class="portal-title">Employee Self-Service</h2>
                    <p class="portal-desc">
                        Sign in/off daily shifts, verify hours worked, consult payslips, log job satisfaction feedback, and receive personalized AI career growth predictions.
                    </p>
                </div>
                <a href="auth/employee_login.php" class="portal-action-btn">
                    Enter Employee Portal <span class="arrow-icon">&rarr;</span>
                </a>
            </div>
        </section>

        <!-- Stats Overview -->
        <section class="stats-dashboard glass-panel animate-fade-in delay-1">
            <div class="stats-title-main">
                <span>📈</span> Live Platform Analytics
            </div>
            <div class="stats-grid">
                <div class="stat-box">
                    <div class="stat-num gradient-text-1"><?= $totalEmployees ?></div>
                    <div class="stat-lbl">Employees Active</div>
                </div>
                <div class="stat-box">
                    <div class="stat-num gradient-text-2"><?= $activeToday ?></div>
                    <div class="stat-lbl">Checked-in Today</div>
                </div>
                <div class="stat-box">
                    <div class="stat-num gradient-text-3"><?= $avgProductivity ?>/100</div>
                    <div class="stat-lbl">Average Productivity</div>
                </div>
                <div class="stat-box">
                    <div class="stat-num" style="color: #818CF8;"><?= $modelAccuracy ?></div>
                    <div class="stat-lbl">AI Predictor Accuracy</div>
                </div>
            </div>
        </section>

        <!-- Announcements Section -->
        <section class="bulletin-panel glass-panel animate-fade-in delay-2">
            <div class="bulletin-header">
                <h2 class="bulletin-title">
                    <span>📢</span> Important Announcements
                </h2>
                <span style="font-size: 0.85rem; color: #64748B;">Bulletin Board</span>
            </div>

            <div class="announcements-list">
                <?php if (!empty($announcements)): ?>
                    <?php foreach ($announcements as $ann): ?>
                        <div class="announcement-item">
                            <div class="announcement-top">
                                <h3 class="announcement-title">
                                    <span class="priority-dot <?= htmlspecialchars($ann['priority']) ?>"></span>
                                    <?= htmlspecialchars($ann['title']) ?>
                                </h3>
                                <div class="announcement-meta">
                                    <span class="announcement-badge <?= htmlspecialchars($ann['priority']) ?>"><?= htmlspecialchars($ann['priority']) ?> Priority</span>
                                    <span>&bull;</span>
                                    <span><?= date('M d, Y H:i', strtotime($ann['created_at'])) ?></span>
                                </div>
                            </div>
                            <p class="announcement-content">
                                <?= nl2br(htmlspecialchars($ann['content'])) ?>
                            </p>
                            <div style="margin-top: 0.75rem; font-size: 0.8rem; color: #475569; font-weight: 500;">
                                Posted by: <span style="color: #64748B;"><?= htmlspecialchars($ann['author']) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-bulletin">
                        No active announcements found at the moment.
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Footer -->
        <footer class="landing-footer">
            <p>AI Employee Analytics &amp; Remuneration System &copy; <?= date('Y') ?> &mdash; Built with Advanced ML Predictions</p>
        </footer>
    </div>
</body>
</html>
