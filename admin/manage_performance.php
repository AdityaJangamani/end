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

// Handle form submission — insert or update performance record
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['employee_id'])) {
    if (!csrf_verify()) {
        $error = 'Invalid form submission. Please try again.';
    } else {
    $emp_id           = (int) $_POST['employee_id'];
    $manager_rating   = floatval($_POST['manager_rating']);
    $projects_completed = (int) $_POST['projects_completed'];
    $productivity_score = floatval($_POST['productivity_score']);
    $hours_worked     = (int) $_POST['hours_worked_per_week'];

    // Validate inputs
    if ($manager_rating < 0 || $manager_rating > 5) {
        $error = "Manager rating must be between 0 and 5.";
    } elseif ($projects_completed < 0) {
        $error = "Projects completed cannot be negative.";
    } elseif ($productivity_score < 0 || $productivity_score > 100) {
        $error = "Productivity score must be between 0 and 100.";
    } elseif ($hours_worked < 0 || $hours_worked > 168) {
        $error = "Hours worked must be between 0 and 168.";
    } else {
        try {
            // Check if performance record already exists for this employee
            $check = $pdo->prepare("SELECT id FROM performance WHERE employee_id = ?");
            $check->execute([$emp_id]);
            $existing = $check->fetch();

            if ($existing) {
                $stmt = $pdo->prepare("UPDATE performance SET manager_rating = ?, projects_completed = ?, productivity_score = ?, hours_worked_per_week = ?, evaluation_date = CURDATE() WHERE id = ?");
                $stmt->execute([$manager_rating, $projects_completed, $productivity_score, $hours_worked, $existing['id']]);
                $success = "Performance record updated successfully!";
            } else {
                $stmt = $pdo->prepare("INSERT INTO performance (employee_id, manager_rating, projects_completed, productivity_score, hours_worked_per_week, evaluation_date) VALUES (?, ?, ?, ?, ?, CURDATE())");
                $stmt->execute([$emp_id, $manager_rating, $projects_completed, $productivity_score, $hours_worked]);
                $success = "Performance record added successfully!";
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
    }
}

// Fetch all employees with their performance data
$employees = $pdo->query("
    SELECT e.id, e.employee_id, e.first_name, e.last_name, e.department, e.job_role,
           p.manager_rating, p.projects_completed, p.productivity_score, p.hours_worked_per_week, p.evaluation_date
    FROM employees e
    LEFT JOIN performance p ON e.id = p.employee_id
    ORDER BY e.first_name ASC
")->fetchAll();

// If editing a specific employee, fetch their data
$edit_emp = null;
if (isset($_GET['edit'])) {
    $edit_id = (int) $_GET['edit'];
    foreach ($employees as $emp) {
        if ($emp['id'] == $edit_id) {
            $edit_emp = $emp;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Performance - AI System</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .perf-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            align-items: start;
        }
        @media (max-width: 900px) {
            .perf-grid { grid-template-columns: 1fr; }
        }
        .rating-stars {
            display: flex;
            gap: 4px;
            align-items: center;
        }
        .rating-stars .star {
            color: #FBBF24;
            font-size: 1.1rem;
        }
        .rating-stars .star.empty {
            color: var(--surface-light);
        }
        .perf-badge {
            display: inline-block;
            padding: 0.2rem 0.6rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .perf-badge.assigned {
            background: rgba(16, 185, 129, 0.15);
            color: #34D399;
        }
        .perf-badge.pending {
            background: rgba(245, 158, 11, 0.15);
            color: #FBBF24;
        }
        .range-display {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .range-display input[type="range"] {
            flex: 1;
            accent-color: var(--primary);
            height: 6px;
        }
        .range-display .range-value {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--primary);
            min-width: 50px;
            text-align: center;
        }
        .emp-row {
            transition: background 0.2s ease;
        }
        .emp-row:hover {
            background: rgba(79, 70, 229, 0.06) !important;
        }
        .emp-row.highlight {
            background: rgba(79, 70, 229, 0.1) !important;
        }

    </style>
</head>
<body>
    <div class="app-container">
        <?php include '../php/sidebar.php'; ?>

        <main class="main-content">
            <header>
                <h1 class="page-title">Manage Performance</h1>
                <a href="view_employee.php" class="btn btn-primary" style="font-size: 0.85rem;">View Employees</a>
            </header>

            <?php if ($success): ?>
                <div class="alert alert--success" style="display: flex; align-items: center; gap: 0.5rem;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert--danger" style="display: flex; align-items: center; gap: 0.5rem;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <div class="perf-grid animate-fade-in">
                <!-- Left: Form Panel -->
                <div class="glass-panel" style="padding: 1.75rem;">
                    <h2 style="margin-bottom: 0.25rem; font-size: 1.2rem;">
                        <?= $edit_emp ? 'Update Rating for ' . htmlspecialchars($edit_emp['first_name'] . ' ' . $edit_emp['last_name']) : 'Select an Employee to Rate' ?>
                    </h2>
                    <p style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 1.5rem;">
                        Assign manager rating, projects completed, and productivity score.
                    </p>

                    <form method="POST" action="manage_performance.php" id="perfForm">
                        <?= csrf_field() ?>
                        <div class="form-group">
                            <label>Employee</label>
                            <select name="employee_id" id="employeeSelect" class="form-control" required onchange="window.location='manage_performance.php?edit='+this.value">
                                <option value="">— Choose Employee —</option>
                                <?php foreach ($employees as $emp): ?>
                                    <option value="<?= $emp['id'] ?>" <?= ($edit_emp && $edit_emp['id'] == $emp['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name'] . ' (' . $emp['department'] . ')') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <?php if ($edit_emp): ?>
                            <div class="form-group">
                                <label>Manager Rating (0 – 5)</label>
                                <div class="range-display">
                                    <input type="range" name="manager_rating" id="ratingSlider" min="0" max="5" step="0.1"
                                           value="<?= htmlspecialchars($edit_emp['manager_rating'] ?? '3') ?>"
                                           oninput="document.getElementById('ratingVal').textContent = parseFloat(this.value).toFixed(1)">
                                    <span class="range-value" id="ratingVal"><?= number_format($edit_emp['manager_rating'] ?? 3, 1) ?></span>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Projects Completed</label>
                                <input type="number" name="projects_completed" class="form-control" min="0"
                                       value="<?= htmlspecialchars($edit_emp['projects_completed'] ?? '0') ?>" required>
                            </div>

                            <div class="form-group">
                                <label>Hours Worked / Week</label>
                                <input type="number" name="hours_worked_per_week" class="form-control" min="0" max="168"
                                       value="<?= htmlspecialchars($edit_emp['hours_worked_per_week'] ?? '40') ?>" required>
                            </div>

                            <div class="form-group">
                                <label>Productivity Score (0 – 100)</label>
                                <div class="range-display">
                                    <input type="range" name="productivity_score" id="prodSlider" min="0" max="100" step="1"
                                           value="<?= htmlspecialchars($edit_emp['productivity_score'] ?? '50') ?>"
                                           oninput="document.getElementById('prodVal').textContent = this.value">
                                    <span class="range-value" id="prodVal"><?= intval($edit_emp['productivity_score'] ?? 50) ?></span>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 0.5rem;">
                                <?= $edit_emp['manager_rating'] !== null ? 'Update Performance' : 'Assign Performance' ?>
                            </button>
                        <?php else: ?>
                            <div style="text-align: center; padding: 2rem 0; color: var(--text-muted);">
                                <svg width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="margin-bottom: 0.75rem; opacity: 0.5;">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                                </svg>
                                <p>Select an employee from the dropdown above<br>or click <strong>"Rate"</strong> in the table.</p>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Right: Employee Table -->
                <div class="glass-panel table-container" style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Department</th>
                                <th>Rating</th>
                                <th>Projects</th>
                                <th>Score</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($employees as $emp): ?>
                                <tr class="emp-row <?= ($edit_emp && $edit_emp['id'] == $emp['id']) ? 'highlight' : '' ?>">
                                    <td>
                                        <div style="font-weight: 600;"><?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?></div>
                                        <div style="font-size: 0.75rem; color: var(--text-muted);">#<?= htmlspecialchars($emp['employee_id']) ?></div>
                                    </td>
                                    <td><?= htmlspecialchars($emp['department']) ?></td>
                                    <td>
                                        <?php if ($emp['manager_rating'] !== null): ?>
                                            <div class="rating-stars">
                                                <?php
                                                $rating = floatval($emp['manager_rating']);
                                                for ($i = 1; $i <= 5; $i++):
                                                ?>
                                                    <span class="star <?= $i <= round($rating) ? '' : 'empty' ?>">★</span>
                                                <?php endfor; ?>
                                                <span style="font-size: 0.8rem; color: var(--text-muted); margin-left: 4px;"><?= number_format($rating, 1) ?></span>
                                            </div>
                                        <?php else: ?>
                                            <span style="color: var(--text-muted);">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($emp['projects_completed'] !== null): ?>
                                            <span style="font-weight: 600;"><?= $emp['projects_completed'] ?></span>
                                        <?php else: ?>
                                            <span style="color: var(--text-muted);">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($emp['productivity_score'] !== null): ?>
                                            <span style="font-weight: 600;"><?= number_format($emp['productivity_score'], 0) ?></span><span style="color: var(--text-muted); font-size: 0.8rem;">/100</span>
                                        <?php else: ?>
                                            <span style="color: var(--text-muted);">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($emp['manager_rating'] !== null): ?>
                                            <span class="perf-badge assigned">Assigned</span>
                                        <?php else: ?>
                                            <span class="perf-badge pending">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="manage_performance.php?edit=<?= $emp['id'] ?>" class="btn btn-primary"
                                           style="padding: 0.3rem 0.75rem; font-size: 0.75rem;">
                                            <?= $emp['manager_rating'] !== null ? 'Edit' : 'Rate' ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (count($employees) == 0): ?>
                                <tr>
                                    <td colspan="7" class="text-center" style="padding: 2rem;">No employees found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
