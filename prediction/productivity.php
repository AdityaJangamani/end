<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/admin_login.php");
    exit();
}
require '../php/db.php';
require '../php/predictions.php';

// Validate input — cast to integer immediately
$emp_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$emp_id) {
    header("Location: ../admin/view_employee.php?error=invalid_id");
    exit();
}

// Fetch employee with performance data (including hours_worked_per_week)
$stmt = $pdo->prepare("
    SELECT e.id, e.first_name, e.last_name, e.department,
           p.productivity_score, p.manager_rating, p.projects_completed, p.hours_worked_per_week
    FROM employees e
    LEFT JOIN performance p ON e.id = p.employee_id
    WHERE e.id = ?
");
$stmt->execute([$emp_id]);
$emp = $stmt->fetch();

if (!$emp) {
    header("Location: ../admin/view_employee.php?error=not_found");
    exit();
}

// Authorization — only admins can view reports
$current_user_role = $_SESSION['role'] ?? 'viewer';
if ($current_user_role !== 'admin') {
    header("Location: ../admin/view_employee.php?error=unauthorized");
    exit();
}


// Build ML input — no hardcoded values
$ml_data = [
    'PerformanceRating'  => $emp['manager_rating']        ?? null,
    'ProjectsCompleted'  => $emp['projects_completed']    ?? null,
    'HoursWorkedPerWeek' => $emp['hours_worked_per_week'] ?? null,
];

$missing = array_keys(array_filter($ml_data, fn($v) => $v === null));
$prediction_available = empty($missing);

$score = null;
if ($prediction_available) {
    $prediction = analyze_productivity($ml_data);
    $raw = $prediction['productivity_score'] ?? null;
    $score = (is_numeric($raw) && $raw >= 0 && $raw <= 100) ? round($raw, 1) : null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Productivity Matrix — <?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?></title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="app-container">
        <?php include '../php/sidebar.php'; ?>
        <main class="main-content">
            <header class="page-header">
                <h1 class="page-title">Productivity Matrix: <?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?></h1>
                <a href="../prediction/prediction.php?id=<?= $emp['id'] ?>" class="btn btn-primary">← Back</a>
            </header>

            <div class="glass-panel report-panel animate-fade-in">
                <?php if ($score !== null): ?>
                    <div class="risk-score risk-score--<?= $score >= 70 ? 'low' : ($score >= 40 ? 'medium' : 'high') ?>">
                        <?= $score ?> / 100
                    </div>
                    <p class="risk-subtitle">Performance Score Model — output vs. expected hours.</p>
                <?php else: ?>
                    <div class="risk-score risk-score--unknown">Score Unavailable</div>
                    <p class="risk-subtitle risk-subtitle--warning">
                        Missing data: <strong><?= htmlspecialchars(implode(', ', $missing)) ?></strong>.
                        Update the performance record to enable scoring.
                    </p>
                <?php endif; ?>

                <hr class="divider">

                <h3>Breakdown</h3>
                <table class="data-table">
                    <thead>
                        <tr><th>Factor</th><th>Value</th></tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Manager Rating</td>
                            <td><?= $emp['manager_rating'] !== null ? htmlspecialchars($emp['manager_rating']) . ' / 5' : '<span class="badge badge--missing">Not recorded</span>' ?></td>
                        </tr>
                        <tr>
                            <td>Projects Completed</td>
                            <td><?= $emp['projects_completed'] !== null ? htmlspecialchars($emp['projects_completed']) : '<span class="badge badge--missing">Not recorded</span>' ?></td>
                        </tr>
                        <tr>
                            <td>Hours Worked / Week</td>
                            <td><?= $emp['hours_worked_per_week'] !== null ? htmlspecialchars($emp['hours_worked_per_week']) : '<span class="badge badge--missing">Not recorded</span>' ?></td>
                        </tr>
                        <tr>
                            <td>DB Productivity Score</td>
                            <td><?= $emp['productivity_score'] !== null ? htmlspecialchars($emp['productivity_score']) . ' / 100' : '<span class="badge badge--missing">Not recorded</span>' ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>
</html>
