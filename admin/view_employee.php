<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/admin_login.php");
    exit();
}
require '../php/db.php';
require '../php/csrf.php';

// FIX: Use POST for delete to prevent accidental/malicious GET-triggered deletes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    if (csrf_verify()) {
        $del_id = (int) $_POST['delete_id'];
        $pdo->prepare("DELETE FROM employees WHERE id = ?")->execute([$del_id]);
    }
    header("Location: ../admin/view_employee.php");
    exit();
}


$employees = $pdo->query("SELECT * FROM employees ORDER BY employee_id DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Employees - AI System</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .search-wrap {
            position: relative;
            margin-bottom: 1.5rem;
            max-width: 400px;
        }

        .search-wrap input {
            width: 100%;
            padding: 0.65rem 1rem 0.65rem 2.5rem;
            border: 1.5px solid var(--border);
            border-radius: 8px;
            background: var(--surface-light);
            color: var(--text-primary);
            font-size: 0.9rem;
            font-family: inherit;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .search-wrap input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12);
        }

        .search-icon {
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 0.9rem;
        }
    </style>
</head>

<body>
    <div class="app-container">
        <?php include '../php/sidebar.php'; ?>
        <main class="main-content">
            <header>
                <h1 class="page-title">Employee Directory</h1>
                <a href="../admin/add_employee.php" class="btn btn-primary">+ New Employee</a>
            </header>


            <div class="search-wrap animate-fade-in delay-1">
                <span class="search-icon">🔍</span>
                <input type="text" id="empSearch" placeholder="Search by name, ID, email, or department..."
                    autocomplete="off">
            </div>

            <div class="glass-panel table-container animate-fade-in delay-1">
                <table>
                    <thead>
                        <tr>
                            <th>Employee ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Department</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="empTableBody">
                        <?php foreach ($employees as $emp): ?>
                            <tr>
                                <td>#<?= htmlspecialchars($emp['employee_id']) ?></td>
                                <td><?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?></td>
                                <td><?= htmlspecialchars($emp['email']) ?></td>
                                <td><?= htmlspecialchars($emp['department']) ?></td>
                                <td><?= htmlspecialchars($emp['job_role']) ?></td>
                                <td>
                                    <?php if ($emp['status'] == 'Active'): ?>
                                        <span class="badge badge-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="../prediction/prediction.php?id=<?= $emp['id'] ?>" class="btn btn-primary"
                                        style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">AI Insight</a>
                                    <!-- FIX: delete via POST form, not a bare GET link -->
                                    <form method="POST" action="" style="display:inline;"
                                        onsubmit="return confirm('Are you sure you want to delete this employee?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="delete_id" value="<?= $emp['id'] ?>">
                                        <button type="submit" class="btn btn-danger"
                                            style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">Delete</button>
                                    </form>

                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (count($employees) == 0): ?>
                            <tr class="no-data">
                                <td colspan="7" class="text-center" style="padding: 2rem;">No employees found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <script>
        const searchInput = document.getElementById('empSearch');
        const tableRows = document.querySelectorAll('#empTableBody tr:not(.no-data)');

        if (searchInput) {
            searchInput.addEventListener('input', function () {
                const query = this.value.toLowerCase().trim();

                tableRows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    if (text.includes(query)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });

            // Auto-focus search box on load
            searchInput.focus();
        }
    </script>
</body>

</html>
