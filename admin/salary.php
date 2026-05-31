<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/admin_login.php");
    exit();
}
require '../php/db.php';
// No API needed — salary data comes from the database

// Fetch all employees with their latest salary data
$query = "
    SELECT e.id, e.first_name, e.last_name, e.department, s.base_salary, s.net_salary, s.month, s.year, s.id as salary_id
    FROM employees e
    LEFT JOIN (
        SELECT s1.* FROM salary s1
        INNER JOIN (
            SELECT employee_id, MAX(id) as max_id
            FROM salary
            GROUP BY employee_id
        ) s2 ON s1.id = s2.max_id
    ) s ON e.id = s.employee_id
    ORDER BY e.date_joined DESC
";
$employees = $pdo->query($query)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salary Management - AI System</title>
    <link rel="stylesheet" href="../css/style.css">
</head>

<body>
    <div class="app-container">
        <?php include '../php/sidebar.php'; ?>

        <main class="main-content">
            <header>
                <h1 class="page-title">Salary Management</h1>
            </header>

            <div class="glass-panel table-container animate-fade-in delay-1">
                <table>
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Department</th>
                            <th>Base Salary</th>
                            <th>Net Salary</th>
                            <th>Last Run</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($employees as $emp): ?>
                            <tr>
                                <td>
                                    <?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($emp['department']) ?>
                                </td>
                                <td>₹
                                    <?= number_format($emp['base_salary'] ?? 0, 2) ?>
                                </td>
                                <td>₹
                                    <?= number_format($emp['net_salary'] ?? 0, 2) ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars(($emp['month'] ?? 'N/A') . ' ' . ($emp['year'] ?? '')) ?>
                                </td>
                                <td>
                                    <a href="../admin/add_salary.php?id=<?= $emp['id'] ?>" class="btn btn-primary"
                                        style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">Manage Salary</a>
                                    <a href="../prediction/prediction.php?id=<?= $emp['id'] ?>" class="btn btn-primary"
                                        style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">AI Salary Pred</a>
                                    <?php if ($emp['salary_id']): ?>
                                        <a href="../admin/payslip.php?id=<?= $emp['salary_id'] ?>" class="btn btn-success"
                                            style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">Generate Payslip</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>

</html>
