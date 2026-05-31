<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/admin_login.php");
    exit();
}
require '../php/db.php';
require '../php/csrf.php';

$success = '';
$error = '';

$employee_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$employee_id) {
    header("Location: salary.php");
    exit();
}

// Fetch employee details
$stmt = $pdo->prepare("SELECT first_name, last_name, department FROM employees WHERE id = ?");
$stmt->execute([$employee_id]);
$employee = $stmt->fetch();

if (!$employee) {
    header("Location: salary.php?error=not_found");
    exit();
}

// Fetch existing salary details if any
$stmt_sal = $pdo->prepare("SELECT * FROM salary WHERE employee_id = ? ORDER BY year DESC, id DESC LIMIT 1");
$stmt_sal->execute([$employee_id]);
$current_salary = $stmt_sal->fetch();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!csrf_verify()) {
        $error = 'Invalid form submission. Please try again.';
    } else {
    $base_salary = $_POST['base_salary'];
    $bonus = $_POST['bonus'] ?? 0;
    $deductions = $_POST['deductions'] ?? 0;
    $month = $_POST['month'];
    $year = $_POST['year'];
    
    $net_salary = $base_salary + $bonus - $deductions;

    try {
        // Check if salary for this month and year already exists
        $check_stmt = $pdo->prepare("SELECT id FROM salary WHERE employee_id = ? AND month = ? AND year = ?");
        $check_stmt->execute([$employee_id, $month, $year]);
        $existing = $check_stmt->fetch();

        if ($existing) {
            $stmt = $pdo->prepare("UPDATE salary SET base_salary = ?, bonus = ?, deductions = ?, net_salary = ? WHERE id = ?");
            $stmt->execute([$base_salary, $bonus, $deductions, $net_salary, $existing['id']]);
            $success = "Salary record updated successfully!";
        } else {
            $stmt = $pdo->prepare("INSERT INTO salary (employee_id, base_salary, bonus, deductions, net_salary, month, year) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$employee_id, $base_salary, $bonus, $deductions, $net_salary, $month, $year]);
            $success = "Salary record added successfully!";
        }
        // Refresh the current salary details
        $current_salary = [
            'base_salary' => $base_salary,
            'bonus' => $bonus,
            'deductions' => $deductions,
            'net_salary' => $net_salary,
            'month' => $month,
            'year' => $year
        ];
    } catch (PDOException $e) {
        $error = "Error adding salary: " . $e->getMessage();
    }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Salary - AI System</title>
    <link rel="stylesheet" href="../css/style.css">
</head>

<body>
    <div class="app-container">
        <?php include '../php/sidebar.php'; ?>

        <main class="main-content">
            <header>
                <h1 class="page-title">Add/Update Salary for <?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?> (<?= htmlspecialchars($employee['department']) ?>)</h1>
            </header>

            <div class="glass-panel form-container animate-fade-in">
                <?php if ($success): ?>
                    <div class="alert alert--success">
                        <?= $success ?>
                    </div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert--danger">
                        <?= $error ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <?= csrf_field() ?>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label>Base Salary</label>
                            <input type="number" step="0.01" name="base_salary" class="form-control" value="<?= htmlspecialchars($current_salary['base_salary'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Bonus</label>
                            <input type="number" step="0.01" name="bonus" class="form-control" value="<?= htmlspecialchars($current_salary['bonus'] ?? '0') ?>">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label>Deductions</label>
                            <input type="number" step="0.01" name="deductions" class="form-control" value="<?= htmlspecialchars($current_salary['deductions'] ?? '0') ?>">
                        </div>
                        <div class="form-group">
                            <label>Month</label>
                            <select name="month" class="form-control" required>
                                <?php
                                $months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
                                $currentMonth = $current_salary['month'] ?? date('F');
                                foreach ($months as $m) {
                                    $selected = ($m === $currentMonth) ? 'selected' : '';
                                    echo "<option value=\"$m\" $selected>$m</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Year</label>
                        <input type="number" name="year" class="form-control" value="<?= htmlspecialchars($current_salary['year'] ?? date('Y')) ?>" required>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%;">Add/Update Salary</button>
                    <a href="salary.php" class="btn" style="display: block; text-align: center; margin-top: 1rem; color: #a0aec0; text-decoration: none;">Back to Salary List</a>
                </form>
            </div>
        </main>
    </div>
</body>

</html>
