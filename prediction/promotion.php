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

// Fetch employee with performance data
$stmt = $pdo->prepare("
    SELECT e.id, e.first_name, e.last_name, e.department, e.date_joined,
           p.manager_rating, p.projects_completed
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


// Calculate tenure
$years_at_company = 0;
if (!empty($emp['date_joined'])) {
    $date1 = new DateTime($emp['date_joined']);
    $date2 = new DateTime();
    $years_at_company = $date1->diff($date2)->y;
}

$ml_data = [
    'PerformanceRating'  => $emp['manager_rating']      ?? null,
    'YearsAtCompany'     => $years_at_company,
    'ProjectsCompleted'  => $emp['projects_completed']   ?? null,
];

// Check for missing data
$missing = array_keys(array_filter($ml_data, fn($v) => $v === null));
$prediction_available = empty($missing);

$prob = null;
if ($prediction_available) {
    $prediction = predict_promotion($ml_data);
    $raw = $prediction['promotion_probability'] ?? null;
    $prob = (is_numeric($raw) && $raw >= 0 && $raw <= 100) ? (int) round($raw) : null;
}

$tier = match(true) {
    $prob === null => 'unknown',
    $prob > 70     => 'high',
    $prob > 40     => 'medium',
    default        => 'low',
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Promotion Report — <?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?></title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="app-container">
        <?php include '../php/sidebar.php'; ?>
        <main class="main-content">
            <header class="page-header">
                <h1 class="page-title">Promotion Readiness: <?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?></h1>
                <a href="../prediction/prediction.php?id=<?= $emp['id'] ?>" class="btn btn-primary">← Back</a>
            </header>

            <div class="glass-panel report-panel animate-fade-in">
                <?php if ($prob !== null): ?>
                    <div class="risk-score risk-score--<?= $tier === 'high' ? 'low' : ($tier === 'low' ? 'high' : 'medium') ?>">
                        <?= $prob ?>% Readiness
                    </div>
                    <p class="risk-subtitle">Random Forest Model — based on historical promotion patterns.</p>
                <?php else: ?>
                    <div class="risk-score risk-score--unknown">Score Unavailable</div>
                    <p class="risk-subtitle risk-subtitle--warning">
                        Missing data: <strong><?= htmlspecialchars(implode(', ', $missing)) ?></strong>.
                        Update the performance record to enable scoring.
                    </p>
                <?php endif; ?>

                <hr class="divider">

                <h3>Model Inputs</h3>
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
                            <td>Tenure</td>
                            <td><?= $years_at_company ?> year(s)</td>
                        </tr>
                        <tr>
                            <td>Projects Completed</td>
                            <td><?= $emp['projects_completed'] !== null ? htmlspecialchars($emp['projects_completed']) : '<span class="badge badge--missing">Not recorded</span>' ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>
</html>
