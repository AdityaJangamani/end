<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/admin_login.php");
    exit();
}
require '../php/db.php';
require '../php/csrf.php';

// Handle posting a new announcement
$msg = '';
$msg_type = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_announcement'])) {
    if (!csrf_verify()) {
        $msg = "Invalid form submission. Please try again.";
        $msg_type = "danger";
    } else {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $priority = $_POST['priority'] ?? 'medium';
        $author = trim($_POST['author'] ?? 'Administration');
        
        if (empty($title) || empty($content)) {
            $msg = "Title and content are required fields.";
            $msg_type = "danger";
        } elseif (!in_array($priority, ['low', 'medium', 'high'])) {
            $msg = "Invalid priority level selected.";
            $msg_type = "danger";
        } else {
            try {
                $stmt_insert = $pdo->prepare("INSERT INTO announcements (title, content, priority, author) VALUES (?, ?, ?, ?)");
                $stmt_insert->execute([$title, $content, $priority, $author]);
                $msg = "Announcement successfully published!";
                $msg_type = "success";
            } catch (PDOException $e) {
                $msg = "Database error: " . $e->getMessage();
                $msg_type = "danger";
            }
        }
    }
}

// Handle deleting an announcement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_announcement'])) {
    if (!csrf_verify()) {
        $msg = "Invalid form submission. Please try again.";
        $msg_type = "danger";
    } else {
        $ann_id = (int)($_POST['announcement_id'] ?? 0);
        try {
            $stmt_delete = $pdo->prepare("DELETE FROM announcements WHERE id = ?");
            $stmt_delete->execute([$ann_id]);
            $msg = "Announcement successfully deleted.";
            $msg_type = "success";
        } catch (PDOException $e) {
            $msg = "Database error: " . $e->getMessage();
            $msg_type = "danger";
        }
    }
}

// Get some basic stats
$empCount = $pdo->query("SELECT COUNT(*) FROM employees WHERE status='Active'")->fetchColumn();
$recentHires = $pdo->query("SELECT COUNT(*) FROM employees WHERE date_joined >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();

// Get the latest AI training log from DB
$latestLog = $pdo->query("SELECT * FROM training_data_log ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$metrics = null;
if ($latestLog && !empty($latestLog['notes'])) {
    $metrics = json_decode($latestLog['notes'], true);
}

// Fetch recent announcements
$announcements = [];
try {
    $announcements = $pdo->query("SELECT * FROM announcements ORDER BY created_at DESC LIMIT 5")->fetchAll();
} catch (PDOException $e) {
    // Fail silently
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - AI System</title>
    <link rel="stylesheet" href="../css/style.css">
</head>

<body>
    <div class="app-container">
        <?php include '../php/sidebar.php'; ?>

        <main class="main-content">
            <header>
                <h1 class="page-title">Dashboard Overview</h1>
                <div class="user-info">
                    <span class="badge badge-success">Admin</span>
                </div>
            </header>

            <div class="dashboard-cards animate-fade-in delay-1">
                <div class="stat-card glass-panel">
                    <div class="stat-title">Active Employees</div>
                    <div class="stat-value text-primary">
                        <?= htmlspecialchars($empCount) ?>
                    </div>
                </div>
                <div class="stat-card glass-panel">
                    <div class="stat-title">New Hires (30 Days)</div>
                    <div class="stat-value text-success">
                        <?= htmlspecialchars($recentHires) ?>
                    </div>
                </div>
                <!-- Static placeholders for UI display -->
                <div class="stat-card glass-panel">
                    <div class="stat-title">High Attrition Risk Employees</div>
                    <div class="stat-value" style="color: var(--warning);">View AI</div>
                </div>
                <div class="stat-card glass-panel">
                    <div class="stat-title">Top Performers</div>
                    <div class="stat-value text-primary">View AI</div>
                </div>
            </div>

            <div class="glass-panel animate-fade-in delay-2" style="padding: 1.5rem; margin-top: 2rem;">
                <h2 style="margin-bottom: 1rem;">System Overview</h2>
                <p style="color: var(--text-muted); line-height: 1.6;">
                    Welcome to the AI-Driven Intelligent Employee Remuneration & Attrition Prediction System.
                    Use the sidebar to navigate through employee management, track attendance and salary,
                    and leverage the Flask Machine Learning API to predict salaries, attrition risks, and intelligent
                    categorizations.
                </p>
                <div style="margin-top: 1.5rem;">
                    <a href="../prediction/prediction.php" class="btn btn-primary">Run AI Predictions</a>
                </div>
            </div>

            <?php if ($latestLog): ?>
            <div class="glass-panel animate-fade-in delay-3" style="padding: 1.75rem; margin-top: 2rem;">
                <h2 style="margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                    🤖 AI Model Status & Accuracy
                </h2>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.25rem; margin-top: 1rem; margin-bottom: 1.5rem;">
                    <div style="background: rgba(255, 255, 255, 0.03); padding: 1rem; border-radius: 8px; border: 1px solid var(--border);">
                        <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">Last Retrained</div>
                        <div style="font-size: 1.1rem; font-weight: 600; margin-top: 0.25rem;">
                            <?= date('d M Y H:i', strtotime($latestLog['training_date'])) ?>
                        </div>
                    </div>
                    <div style="background: rgba(255, 255, 255, 0.03); padding: 1rem; border-radius: 8px; border: 1px solid var(--border);">
                        <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">Records Used</div>
                        <div style="font-size: 1.1rem; font-weight: 600; margin-top: 0.25rem;">
                            <?= htmlspecialchars($latestLog['records_used']) ?> / <?= htmlspecialchars($latestLog['total_records']) ?>
                        </div>
                    </div>
                    <div style="background: rgba(255, 255, 255, 0.03); padding: 1rem; border-radius: 8px; border: 1px solid var(--border);">
                        <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">Data Version</div>
                        <div style="font-size: 1.1rem; font-weight: 600; margin-top: 0.25rem;">
                            <span class="badge badge-success"><?= htmlspecialchars($latestLog['data_version']) ?></span>
                        </div>
                    </div>
                </div>

                <?php if ($metrics): ?>
                <h3 style="margin-top: 1.5rem; margin-bottom: 0.75rem; font-size: 1.1rem; color: var(--text-muted);">Model Accuracy Performance</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem;">
                    <div style="background: rgba(79, 70, 229, 0.05); padding: 1rem; border-radius: 8px; border: 1px solid rgba(79, 70, 229, 0.2);">
                        <div style="font-weight: 600; font-size: 0.9rem;">Salary Regressor (R²)</div>
                        <div style="font-size: 1.75rem; font-weight: 700; color: var(--primary); margin-top: 0.25rem;">
                            <?= number_format($metrics['salary_r2'] * 100, 1) ?>%
                        </div>
                        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">
                            MAE: ₹<?= number_format($metrics['salary_mae'], 0) ?>
                        </div>
                    </div>
                    <div style="background: rgba(16, 185, 129, 0.05); padding: 1rem; border-radius: 8px; border: 1px solid rgba(16, 185, 129, 0.2);">
                        <div style="font-weight: 600; font-size: 0.9rem;">Attrition Risk Accuracy</div>
                        <div style="font-size: 1.75rem; font-weight: 700; color: var(--secondary); margin-top: 0.25rem;">
                            <?= number_format($metrics['attrition_accuracy'] * 100, 1) ?>%
                        </div>
                    </div>
                    <div style="background: rgba(245, 158, 11, 0.05); padding: 1rem; border-radius: 8px; border: 1px solid rgba(245, 158, 11, 0.2);">
                        <div style="font-weight: 600; font-size: 0.9rem;">Promotion Classifier</div>
                        <div style="font-size: 1.75rem; font-weight: 700; color: var(--warning); margin-top: 0.25rem;">
                            <?= number_format($metrics['promotion_accuracy'] * 100, 1) ?>%
                        </div>
                    </div>
                    <div style="background: rgba(99, 102, 241, 0.05); padding: 1rem; border-radius: 8px; border: 1px solid rgba(99, 102, 241, 0.2);">
                        <div style="font-weight: 600; font-size: 0.9rem;">Role Categorizer</div>
                        <div style="font-size: 1.75rem; font-weight: 700; color: #818CF8; margin-top: 0.25rem;">
                            <?= number_format($metrics['category_accuracy'] * 100, 1) ?>%
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div style="margin-top: 1rem; font-size: 0.85rem; color: var(--text-muted); background: rgba(255, 255, 255, 0.02); padding: 0.75rem 1rem; border-radius: 6px;">
                    <strong>Details:</strong> <?= htmlspecialchars($latestLog['notes']) ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Messages / Alerts -->
            <?php if ($msg): ?>
                <div class="alert alert--<?= $msg_type ?> animate-fade-in" style="margin-top: 2rem;">
                    <?= $msg_type === 'success' ? '✅' : '❌' ?> <?= htmlspecialchars($msg) ?>
                </div>
            <?php endif; ?>

            <!-- Announcements Management -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 2rem; margin-top: 2rem;">
                <!-- Post Announcement -->
                <div class="glass-panel animate-fade-in delay-1" style="padding: 1.75rem;">
                    <h2 style="margin-bottom: 1.25rem; font-size: 1.4rem; display: flex; align-items: center; gap: 0.5rem; color: #fff;">
                        📢 Publish Announcement
                    </h2>
                    <form method="POST" action="">
                        <?= csrf_field() ?>
                        <input type="hidden" name="post_announcement" value="1">
                        
                        <div class="form-group">
                            <label for="title">Title</label>
                            <input type="text" id="title" name="title" class="form-control" placeholder="e.g. System Maintenance Notice" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="content">Content</label>
                            <textarea id="content" name="content" class="form-control" rows="4" placeholder="Enter announcement details..." required style="resize: vertical; font-family: inherit;"></textarea>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div class="form-group">
                                <label for="priority">Priority Level</label>
                                <select id="priority" name="priority" class="form-control" required style="background-color: var(--surface-light); border: 1px solid var(--border); color: #fff;">
                                    <option value="low">Low</option>
                                    <option value="medium" selected>Medium</option>
                                    <option value="high">High</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="author">Author Profile</label>
                                <input type="text" id="author" name="author" class="form-control" value="Administration" required>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 0.5rem;">
                            Publish Announcement
                        </button>
                    </form>
                </div>

                <!-- Recent Announcements List -->
                <div class="glass-panel animate-fade-in delay-2" style="padding: 1.75rem;">
                    <h2 style="margin-bottom: 1.25rem; font-size: 1.4rem; display: flex; align-items: center; gap: 0.5rem; color: #fff;">
                        📋 Recent Announcements
                    </h2>
                    <div style="display: flex; flex-direction: column; gap: 1rem; max-height: 480px; overflow-y: auto; padding-right: 0.25rem;">
                        <?php if (!empty($announcements)): ?>
                            <?php foreach ($announcements as $ann): ?>
                                <div style="background: rgba(255, 255, 255, 0.02); border: 1px solid var(--border); border-radius: 8px; padding: 1.25rem; position: relative;">
                                    <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 0.75rem; flex-wrap: wrap;">
                                        <h3 style="font-size: 1.05rem; font-weight: 600; color: #fff; margin: 0 0 0.25rem 0; display: flex; align-items: center; gap: 6px;">
                                            <span style="width: 8px; height: 8px; border-radius: 50%; background: <?= $ann['priority'] === 'high' ? 'var(--danger)' : ($ann['priority'] === 'medium' ? 'var(--warning)' : 'var(--secondary)') ?>; display: inline-block;"></span>
                                            <?= htmlspecialchars($ann['title']) ?>
                                        </h3>
                                        <div style="display: flex; gap: 6px;">
                                            <?php
                                            $pClass = 'badge-warning';
                                            if ($ann['priority'] === 'high') $pClass = 'badge-danger';
                                            if ($ann['priority'] === 'low') $pClass = 'badge-success';
                                            ?>
                                            <span class="badge <?= $pClass ?>" style="font-size: 0.7rem; padding: 0.1rem 0.4rem;"><?= htmlspecialchars($ann['priority']) ?></span>
                                        </div>
                                    </div>
                                    <p style="font-size: 0.85rem; color: var(--text-muted); line-height: 1.5; margin: 0.5rem 0;">
                                        <?= nl2br(htmlspecialchars($ann['content'])) ?>
                                    </p>
                                    <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.75rem; color: rgba(255,255,255,0.25); margin-top: 0.5rem; border-top: 1px solid rgba(255,255,255,0.05); padding-top: 0.5rem;">
                                        <span>By: <?= htmlspecialchars($ann['author']) ?> &bull; <?= date('d M H:i', strtotime($ann['created_at'])) ?></span>
                                        
                                        <form method="POST" action="" onsubmit="return confirm('Are you sure you want to delete this announcement?');" style="margin: 0;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="delete_announcement" value="1">
                                            <input type="hidden" name="announcement_id" value="<?= $ann['id'] ?>">
                                            <button type="submit" style="background: none; border: none; color: var(--danger); cursor: pointer; font-size: 0.75rem; padding: 0; font-weight: 600;">
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="color: var(--text-muted); text-align: center; margin: 0; padding: 2rem;">No announcements published yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>

</html>
