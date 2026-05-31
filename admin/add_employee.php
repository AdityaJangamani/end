<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/admin_login.php");
    exit();
}
require '../php/db.php';
require '../php/csrf.php';
require '../php/config.php';

$success = '';
$error = '';

// Using $DEPT_SALARY_DEFAULTS from php/config.php

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!csrf_verify()) {
        $error = 'Invalid form submission. Please try again.';
    } else {
    $employee_id = trim($_POST['employee_id']);
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $department = trim($_POST['department']);
    $job_role = trim($_POST['job_role']);
    $age = !empty($_POST['age']) ? (int) trim($_POST['age']) : null;          // FIX 3: read age from form
    $date_joined = trim($_POST['date_joined']);
    $job_sat = 3; // Default to Neutral — employee will set their own satisfaction from their dashboard
    $plain_pw = $_POST['password'];
    $password = password_hash($plain_pw, PASSWORD_BCRYPT);

    // Auto-calculate years_at_company
    $joined_dt = new DateTime($date_joined);
    $now_dt = new DateTime();
    $diff = $joined_dt->diff($now_dt);
    $years_at_company = round($diff->y + ($diff->m / 12), 2);

    // FIX 6: salary derived from department
    $annual_salary = $DEPT_SALARY_DEFAULTS[$department] ?? 50000;
    $monthly_base = round($annual_salary / 12, 2);

    if ($employee_id === '') {
        $error = 'Employee ID is required.';
    } elseif (!preg_match('/^[a-zA-Z\s]+$/', $first_name) || ($last_name !== '' && !preg_match('/^[a-zA-Z\s]+$/', $last_name))) {
        $error = 'Names must contain only alphabetic characters and spaces.';
    } elseif ($age !== null && ($age < 18 || $age > 80)) {
        $error = 'Age must be between 18 and 80 (or left blank).';
    } elseif (strlen($plain_pw) < 8 || !preg_match('/[A-Z]/', $plain_pw) || !preg_match('/[a-z]/', $plain_pw) || !preg_match('/[1-9]/', $plain_pw) || !preg_match('/[^a-zA-Z0-9]/', $plain_pw)) {
        $error = "Password must be at least 8 characters and include uppercase, lowercase, number (1-9), and special character.";
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO employees (
                    employee_id, first_name, last_name, email, password,
                    department, job_role, age, date_joined,
                    years_at_company, job_satisfaction, status
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active')
            ");
            $stmt->execute([
                $employee_id,
                $first_name,
                $last_name,
                $email,
                $password,
                $department,
                $job_role,
                $age,
                $date_joined,
                $years_at_company,
                $job_sat
            ]);

            $emp_id = $pdo->lastInsertId();

            // FIX 5: hours_worked_per_week explicitly set (40 = standard baseline)
            $stmt_perf = $pdo->prepare("
                INSERT INTO performance
                    (employee_id, productivity_score, manager_rating, projects_completed, evaluation_date, hours_worked_per_week)
                VALUES (?, 75.00, 3.00, 5, CURDATE(), 40)
            ");
            $stmt_perf->execute([$emp_id]);

            // FIX 6: salary based on department, not hardcoded 50000
            $stmt_sal = $pdo->prepare("
                INSERT INTO salary
                    (employee_id, base_salary, bonus, deductions, net_salary, month, year)
                VALUES (?, ?, 0, 0, ?, ?, ?)
            ");
            $stmt_sal->execute([$emp_id, $monthly_base, $monthly_base, date('F'), date('Y')]);

            $success = "Employee {$first_name} {$last_name} added successfully. "
                . "Default salary set to ₹" . number_format($monthly_base, 2) . "/month based on department.";

        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Employee - AI System</title>
    <link rel="stylesheet" href="../css/style.css">
</head>

<body>
    <div class="app-container">
        <?php include '../php/sidebar.php'; ?>
        <main class="main-content">
            <header>
                <h1 class="page-title">Add New Employee</h1>
            </header>
            <div class="glass-panel form-container animate-fade-in">

                <?php if ($success): ?>
                    <div class="alert alert--success">
                        <strong>✅ Success!</strong><br>
                        <?= htmlspecialchars($success) ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert--danger">
                        <strong>⚠️ Error!</strong><br>
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <?= csrf_field() ?>
                    <!-- Row 1: Name -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label>First Name*</label>
                            <input type="text" name="first_name" class="form-control" pattern="[A-Za-z\s]+"
                                title="Only alphabetic characters and spaces are allowed." required>
                        </div>
                        <div class="form-group">
                            <label>Last Name*</label>
                            <input type="text" name="last_name" class="form-control" pattern="[A-Za-z\s]+"
                                title="Only alphabetic characters and spaces are allowed.">
                        </div>
                    </div>

                    <!-- Row 2: Employee ID + Email -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label>Employee ID*</label>
                            <input type="text" name="employee_id" class="form-control" required
                                placeholder="e.g. EMP001">
                        </div>
                        <div class="form-group">
                            <label>Email Address*</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                    </div>

                    <!-- Row 3: Department + Job Role -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label>Department*</label>
                            <select name="department" class="form-control" required>
                                <option value="">-- Select Department --</option>
                                <option value="Engineering">Engineering</option>
                                <option value="Product">Product</option>
                                <option value="Finance">Finance</option>
                                <option value="Sales">Sales</option>
                                <option value="Marketing">Marketing</option>
                                <option value="HR">HR</option>
                                <option value="Operations">Operations</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Job Role</label>
                            <input type="text" name="job_role" class="form-control"
                                placeholder="e.g. Software Engineer">
                        </div>
                    </div>

                    <!-- Row 4: Age + Date Joined -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <!-- FIX 3: age input added -->
                        <div class="form-group">
                            <label>Age</label>
                            <input type="number" name="age" class="form-control" min="18" max="80"
                                placeholder="e.g. 28">
                        </div>
                        <div class="form-group">
                            <label>Date Joined</label>
                            <input type="date" name="date_joined" class="form-control" required>
                        </div>
                    </div>



                    <!-- Row 6: Portal Password -->
                    <div class="form-group">
                        <label>Portal Password*</label>
                        <input type="password" name="password" class="form-control"
                            placeholder="Create a password for this employee" required>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%;">Add Employee</button>

                </form>
            </div>
        </main>
    </div>
</body>

</html>
