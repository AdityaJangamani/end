<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/admin_login.php");
    exit();
}

require '../php/db.php';
require '../php/predictions.php';

// 1. Validate and sanitize input immediately (cast to int)
$emp_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$emp_id) {
    header("Location: ../admin/view_employee.php?error=invalid_id");
    exit();
}

// 2. Authorization context
$current_user_role = $_SESSION['role'] ?? 'viewer';
$current_user_id   = $_SESSION['user_id'];

// 3. Fetch employee + performance + latest salary — simplified join
try {
    $stmt = $pdo->prepare("
        SELECT
            e.id,
            e.first_name,
            e.last_name,
            e.department,
            e.job_role,
            e.date_joined,
            e.age,
            e.years_at_company,
            e.job_satisfaction,
            p.productivity_score,
            p.manager_rating,
            p.projects_completed,
            p.hours_worked_per_week,
            s.base_salary
        FROM employees e
        LEFT JOIN performance p
            ON e.id = p.employee_id
        LEFT JOIN salary s
            ON s.employee_id = e.id
        WHERE e.id = ?
        ORDER BY s.id DESC
        LIMIT 1
    ");
    $stmt->execute([$emp_id]);
    $emp = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Prediction query failed: ' . $e->getMessage());
    die('<h2>Database Error</h2><p>' . htmlspecialchars($e->getMessage()) . '</p><p>You may need to run <a href="migrate_years.php">migrate_years.php</a> or re-import <code>database/schema.sql</code>.</p>');
}

if (!$emp) {
    header("Location: ../admin/view_employee.php?error=not_found");
    exit();
}

// 4. Role-based access: non-admins can only view their own data
if ($current_user_role !== 'admin' && (int)$emp['id'] !== (int)$current_user_id) {
    header("Location: ../admin/view_employee.php?error=unauthorized");
    exit();
}

// 5. Calculate years at company from date_joined and keep the stored value fresh
$years_at_company = 0;
if (!empty($emp['date_joined'])) {
    $date1 = new DateTime($emp['date_joined']);
    $date2 = new DateTime();
    $interval = $date1->diff($date2);
    $years_at_company = round($interval->y + ($interval->m / 12), 2);

    try {
        $pdo->prepare("UPDATE employees SET years_at_company = ? WHERE id = ?")->execute([$years_at_company, $emp_id]);
    } catch (PDOException $e) {
        // Column may not exist yet — run migrate_years.php first
    }
}

// 6. Build ML input — all values from the database, no hardcoded fakes
$ml_data = [
    'Age'                => $emp['age'] ?? null,
    'YearsAtCompany'     => $years_at_company,
    'BaseSalary'         => $emp['base_salary']           ?? null,
    'JobSatisfaction'    => $emp['job_satisfaction']       ?? null,
    'PerformanceRating'  => $emp['manager_rating']        ?? null,
    'ProjectsCompleted'  => $emp['projects_completed']    ?? null,
    'HoursWorkedPerWeek' => $emp['hours_worked_per_week'] ?? null,
];

// 7. Check for missing critical inputs before running models
$missing_fields = array_keys(array_filter($ml_data, fn($v) => $v === null));
$prediction_available = empty($missing_fields);

$salary_pred = null;
$attrition_pred = null;
$promotion_pred = null;

if ($prediction_available) {
    $salary_pred    = predict_salary($ml_data);
    $attrition_pred = predict_attrition($ml_data);
    $promotion_pred = predict_promotion($ml_data);

    // Validate returned values are numeric and in range
    $salary_val = $salary_pred['predicted_salary'] ?? null;
    if (!is_numeric($salary_val) || $salary_val < 0) {
        $salary_pred = null;
    }

    $attrition_val = $attrition_pred['attrition_risk'] ?? null;
    if (!is_numeric($attrition_val) || $attrition_val < 0 || $attrition_val > 100) {
        $attrition_pred = null;
    }

    $promo_val = $promotion_pred['promotion_probability'] ?? null;
    if (!is_numeric($promo_val) || $promo_val < 0 || $promo_val > 100) {
        $promotion_pred = null;
    }
}

// Determine attrition risk tier for CSS class
$attrition_risk = $attrition_pred['attrition_risk'] ?? null;
$risk_tier = match(true) {
    $attrition_risk === null => 'unknown',
    $attrition_risk > 70    => 'high',
    $attrition_risk > 40    => 'medium',
    default                 => 'low',
};
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Prediction Dashboard — <?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?></title>
    <link rel="stylesheet" href="../css/style.css">
</head>

<body>
    <div class="app-container">
        <?php include '../php/sidebar.php'; ?>

        <main class="main-content">
            <header class="page-header">
                <h1 class="page-title">AI Predictions for
                    <?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?>
                </h1>
                <a href="../admin/view_employee.php" class="btn btn-primary">← Back</a>
            </header>

            <?php if (!$prediction_available): ?>
                <div class="alert alert--warning animate-fade-in">
                    <strong>Incomplete Data</strong> — predictions require:
                    <strong><?= htmlspecialchars(implode(', ', $missing_fields)) ?></strong>.
                    Update the employee's performance record to enable scoring.
                </div>
            <?php endif; ?>

            <div class="dashboard-cards animate-fade-in delay-1">
                <!-- Salary Prediction -->
                <div class="stat-card glass-panel">
                    <div class="stat-title">Predicted Fair Salary</div>
                    <div class="stat-value text-success">
                        <?php if ($salary_pred): ?>
                            ₹<?= number_format($salary_pred['predicted_salary'], 2) ?>
                        <?php else: ?>
                            <span class="stat-unavailable">—</span>
                        <?php endif; ?>
                    </div>
                    <p class="stat-note">Linear Regression Model</p>
                </div>

                <!-- Attrition Prediction -->
                <div class="stat-card glass-panel <?= $risk_tier === 'high' ? 'card--danger' : '' ?>">
                    <div class="stat-title">Attrition Risk</div>
                    <div class="stat-value <?= $risk_tier === 'high' ? 'text-danger' : 'text-primary' ?>">
                        <?php if ($attrition_pred): ?>
                            <?= htmlspecialchars($attrition_pred['attrition_risk']) ?>%
                        <?php else: ?>
                            <span class="stat-unavailable">—</span>
                        <?php endif; ?>
                    </div>
                    <p class="stat-note">Logistic Regression Model</p>
                </div>

                <!-- Promotion Prediction -->
                <div class="stat-card glass-panel">
                    <div class="stat-title">Promotion Probability</div>
                    <div class="stat-value text-primary">
                        <?php if ($promotion_pred): ?>
                            <?= htmlspecialchars($promotion_pred['promotion_probability']) ?>%
                        <?php else: ?>
                            <span class="stat-unavailable">—</span>
                        <?php endif; ?>
                    </div>
                    <p class="stat-note">Random Forest Model</p>
                </div>
            </div>

            <div class="prediction-actions animate-fade-in delay-2">
                <a href="../prediction/attrition.php?id=<?= $emp['id'] ?>" class="btn btn-danger">Detailed Attrition Report</a>
                <a href="../prediction/promotion.php?id=<?= $emp['id'] ?>" class="btn btn-success">Detailed Promotion Report</a>
                <a href="../prediction/productivity.php?id=<?= $emp['id'] ?>" class="btn btn-primary">Advanced Productivity Matrix</a>
            </div>
        </main>
    </div>
</body>

</html>
