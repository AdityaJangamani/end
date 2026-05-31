<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/admin_login.php");
    exit();
}

require '../php/db.php';
require '../php/predictions.php';

// 1. Validate and sanitize input immediately
$emp_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$emp_id) {
    header("Location: ../admin/view_employee.php?error=invalid_id");
    exit();
}

// 2. Authorization: restrict access based on role
// Managers can only view their own team; admins see all
$current_user_role = $_SESSION['role'] ?? 'viewer';
$current_user_id   = $_SESSION['user_id'];

// 3. Fetch employee + performance + latest salary + satisfaction + hours
$stmt = $pdo->prepare("
    SELECT
        e.id,
        e.first_name,
        e.last_name,
        e.department,
        p.productivity_score,
        p.manager_rating,
        p.projects_completed,
        e.job_satisfaction,
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

if (!$emp) {
    header("Location: ../admin/view_employee.php?error=not_found");
    exit();
}

// 4. Role-based access: only admins can view reports
if ($current_user_role !== 'admin') {
    header("Location: ../admin/view_employee.php?error=unauthorized");
    exit();
}


// 5. Build ML input — no hardcoded values
$ml_data = [
    'JobSatisfaction'    => $emp['job_satisfaction']     ?? null,
    'HoursWorkedPerWeek' => $emp['hours_worked_per_week'] ?? null,
    'BaseSalary'         => $emp['base_salary']           ?? null,
    'PerformanceRating'  => $emp['manager_rating']        ?? null,
    'ProjectsCompleted'  => $emp['projects_completed']    ?? null,
];

// 6. Check for missing critical inputs before running the model
$missing_fields = array_keys(array_filter($ml_data, fn($v) => $v === null));
$prediction_available = empty($missing_fields);

$risk = null;
if ($prediction_available) {
    $prediction = predict_attrition($ml_data);
    // Validate the returned risk is actually a number in range
    $raw_risk = $prediction['attrition_risk'] ?? null;
    $risk = (is_numeric($raw_risk) && $raw_risk >= 0 && $raw_risk <= 100)
        ? (int) round($raw_risk)
        : null;
}

// 7. Determine risk tier for UI logic — single source of truth
$risk_tier = match(true) {
    $risk === null      => 'unknown',
    $risk > 70          => 'high',
    $risk > 40          => 'medium',
    default             => 'low',
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Attrition Report — <?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?></title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="app-container">
        <?php include '../php/sidebar.php'; ?>
        <main class="main-content">
            <header class="page-header">
                <h1 class="page-title">
                    Attrition Report: <?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?>
                </h1>
                <a href="../prediction/prediction.php?id=<?= $emp['id'] ?>" class="btn btn-primary">← Back</a>
            </header>

            <div class="glass-panel report-panel">

                <!-- Risk Score -->
                <?php if ($risk !== null): ?>
                    <div class="risk-score risk-score--<?= $risk_tier ?>">
                        <?= $risk ?>% Risk
                    </div>
                    <p class="risk-subtitle">
                        Logistic Regression model — based on live employee data.
                    </p>
                <?php else: ?>
                    <div class="risk-score risk-score--unknown">
                        Score Unavailable
                    </div>
                    <p class="risk-subtitle risk-subtitle--warning">
                        Prediction could not run. Missing data:
                        <strong><?= htmlspecialchars(implode(', ', $missing_fields)) ?></strong>.
                        Update the employee's performance record to enable scoring.
                    </p>
                <?php endif; ?>

                <hr class="divider">

                <!-- Input Factors -->
                <h3>Model Inputs</h3>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Factor</th>
                            <th>Value</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $labels = [
                            'JobSatisfaction'    => 'Job Satisfaction',
                            'HoursWorkedPerWeek' => 'Hours Worked / Week',
                            'BaseSalary'         => 'Base Salary',
                            'PerformanceRating'  => 'Manager Rating',
                            'ProjectsCompleted'  => 'Projects Completed',
                        ];
                        foreach ($ml_data as $key => $value):
                            $is_missing = $value === null;
                        ?>
                        <tr class="<?= $is_missing ? 'row--missing' : '' ?>">
                            <td><?= $labels[$key] ?></td>
                            <td>
                                <?php if ($is_missing): ?>
                                    <span class="badge badge--missing">Not recorded</span>
                                <?php elseif ($key === 'BaseSalary'): ?>
                                    ₹<?= number_format($value, 2) ?>
                                <?php elseif ($key === 'PerformanceRating'): ?>
                                    <?= htmlspecialchars($value) ?> / 5
                                <?php else: ?>
                                    <?= htmlspecialchars($value) ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge--<?= $is_missing ? 'missing' : 'ok' ?>">
                                    <?= $is_missing ? 'Missing' : 'OK' ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Recommended Action -->
                <?php if ($risk !== null): ?>
                <div class="recommendation recommendation--<?= $risk_tier ?>">
                    <h4>Recommended Action</h4>
                    <p>
                        <?php match($risk_tier) {
                            'high'   => print('High risk. Schedule a 1-on-1 retention meeting immediately. Review compensation and workload.'),
                            'medium' => print('Moderate risk. Flag for manager review next quarter. Monitor satisfaction trends.'),
                            'low'    => print('Low risk. Continue standard quarterly check-ins.'),
                            default  => print('No action — risk score unavailable.'),
                        }; ?>
                    </p>
                </div>
                <?php endif; ?>

            </div>
        </main>
    </div>
</body>
</html>
