<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/admin_login.php");
    exit();
}
require '../php/db.php';
require '../php/csrf.php';

// Handle deletion of future-dated records
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_future') {
    if (csrf_verify()) {
        $del_emp_id = (int) $_POST['employee_id'];
        // Delete only future dates for this employee
        $pdo->prepare("DELETE FROM attendance_daily WHERE employee_id = ? AND date > CURDATE()")->execute([$del_emp_id]);
    }
    header("Location: ../admin/manage_attendance.php?deleted=1");
    exit();
}

// Fetch all employees for search
$employees = $pdo->query("SELECT id, employee_id, first_name, last_name, department FROM employees ORDER BY first_name")->fetchAll();

// ── Employees with FUTURE-dated attendance records ────────────────────────
$future_records = $pdo->query("
    SELECT
        e.id,
        e.employee_id,
        e.first_name,
        e.last_name,
        e.department,
        COUNT(ad.id)    AS future_count,
        MIN(ad.date)    AS earliest_future,
        MAX(ad.date)    AS latest_future
    FROM attendance_daily ad
    JOIN employees e ON e.id = ad.employee_id
    WHERE ad.date > CURDATE()
    GROUP BY e.id, e.employee_id, e.first_name, e.last_name, e.department
    ORDER BY future_count DESC
")->fetchAll();

$selected_emp    = null;
$daily_records   = [];
$monthly_summary = [];

if (isset($_GET['emp'])) {
    $sel_id = (int) $_GET['emp'];
    $sel_stmt = $pdo->prepare("SELECT id, employee_id, first_name, last_name, department FROM employees WHERE id = ?");
    $sel_stmt->execute([$sel_id]);
    $selected_emp = $sel_stmt->fetch();

    if ($selected_emp) {
        // Daily records — last 90 days
        $dr = $pdo->prepare("
            SELECT date, status, signed_in_at
            FROM attendance_daily
            WHERE employee_id = ?
            ORDER BY date DESC
            LIMIT 90
        ");
        $dr->execute([$sel_id]);
        $daily_records = $dr->fetchAll();

        // Monthly summary
        $ms = $pdo->prepare("
            SELECT month, year, days_present, total_days
            FROM attendance
            WHERE employee_id = ?
            ORDER BY year DESC,
                FIELD(month,'January','February','March','April','May','June',
                           'July','August','September','October','November','December') DESC
        ");
        $ms->execute([$sel_id]);
        $monthly_summary = $ms->fetchAll();
    }
}

$today = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Attendance — AI System</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .att-layout { display: grid; grid-template-columns: 320px 1fr; gap: 2rem; align-items: start; }
        @media (max-width: 960px) { .att-layout { grid-template-columns: 1fr; } }

        /* Search */
        .search-wrap { position: relative; margin-bottom: 1rem; }
        .search-wrap input {
            width: 100%; padding: 0.65rem 1rem 0.65rem 2.5rem;
            border: 1.5px solid var(--border); border-radius: 8px;
            background: var(--surface-light); color: var(--text-primary);
            font-size: 0.9rem; font-family: inherit;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .search-wrap input:focus {
            outline: none; border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99,102,241,0.12);
        }
        .search-icon {
            position: absolute; left: 0.75rem; top: 50%;
            transform: translateY(-50%); color: var(--text-muted); font-size: 0.9rem;
        }

        /* Employee list */
        .emp-list { max-height: 400px; overflow-y: auto; border: 1px solid var(--border); border-radius: 8px; }
        .emp-item {
            display: flex; flex-direction: column; padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--border); cursor: pointer;
            text-decoration: none; color: var(--text-primary);
            transition: background 0.15s;
        }
        .emp-item:last-child { border-bottom: none; }
        .emp-item:hover, .emp-item.active { background: rgba(99,102,241,0.08); }
        .emp-item.active { border-left: 3px solid var(--primary); }
        .emp-item .emp-name { font-weight: 600; font-size: 0.9rem; }
        .emp-item .emp-meta { font-size: 0.75rem; color: var(--text-muted); margin-top: 0.1rem; }
        .emp-item.hidden { display: none; }

        /* Badges */
        .badge-present { background: rgba(16,185,129,0.15); color: #34D399; padding: 0.2rem 0.65rem; border-radius: 6px; font-size: 0.78rem; font-weight: 700; }
        .badge-absent  { background: rgba(239,68,68,0.15);  color: #F87171; padding: 0.2rem 0.65rem; border-radius: 6px; font-size: 0.78rem; font-weight: 700; }
        .att-pct       { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 6px; font-size: 0.8rem; font-weight: 700; }
        .att-pct.excellent { background: rgba(16,185,129,0.15); color: #34D399; }
        .att-pct.good      { background: rgba(79,70,229,0.15);  color: #818CF8; }
        .att-pct.average   { background: rgba(245,158,11,0.15); color: #FBBF24; }
        .att-pct.poor      { background: rgba(239,68,68,0.15);  color: #F87171; }

        .section-title { font-size: 1rem; font-weight: 700; margin-bottom: 1rem; }
        .readonly-note {
            background: rgba(245,158,11,0.1); color: #FBBF24;
            padding: 0.65rem 1rem; border-radius: 8px;
            font-size: 0.82rem; margin-bottom: 1.25rem;
            border: 1px solid rgba(245,158,11,0.2);
        }
    </style>
</head>
<body>
<div class="app-container">
    <?php include '../php/sidebar.php'; ?>

    <main class="main-content">
        <header>
            <h1 class="page-title">Manage Attendance</h1>
        </header>

        <?php if (isset($_GET['deleted'])): ?>
            <div class="alert alert--success animate-fade-in" style="margin-bottom: 2rem;">
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <span style="font-size: 1.4rem;">✅</span>
                    <p style="font-weight: 700; font-size: 1rem; margin: 0;">Future-dated records deleted successfully.</p>
                </div>
            </div>
        <?php endif; ?>

        <?php if (count($future_records) > 0): ?>
        <div class="alert alert--danger animate-fade-in" style="margin-bottom: 2rem;">
            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;">
                <span style="font-size: 1.4rem;">⚠️</span>
                <div>
                    <p style="font-weight: 700; font-size: 1rem; margin: 0; color: inherit;">Future-Dated Attendance Detected</p>
                    <p style="font-size: 0.82rem; margin: 0; opacity: 0.9;">
                        <?= count($future_records) ?> employee(s) have attendance records dated <strong>after today (<?= date('d M Y') ?>)</strong>.
                        These records may have been incorrectly imported.
                    </p>
                </div>
            </div>
            <table style="width:100%; border-collapse:collapse;">
                <thead>
                    <tr>
                        <th style="padding:0.6rem 0.75rem; text-align:left; border-bottom:1px solid var(--border); font-size:0.8rem; color:var(--text-muted);">Employee</th>
                        <th style="padding:0.6rem 0.75rem; text-align:left; border-bottom:1px solid var(--border); font-size:0.8rem; color:var(--text-muted);">Department</th>
                        <th style="padding:0.6rem 0.75rem; text-align:left; border-bottom:1px solid var(--border); font-size:0.8rem; color:var(--text-muted);">Future Records</th>
                        <th style="padding:0.6rem 0.75rem; text-align:left; border-bottom:1px solid var(--border); font-size:0.8rem; color:var(--text-muted);">Date Range</th>
                        <th style="padding:0.6rem 0.75rem; text-align:left; border-bottom:1px solid var(--border); font-size:0.8rem; color:var(--text-muted);">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($future_records as $fr): ?>
                    <tr>
                        <td style="padding:0.65rem 0.75rem; border-bottom:1px solid var(--border);">
                            <div style="font-weight:600; font-size:0.9rem;"><?= htmlspecialchars($fr['first_name'] . ' ' . $fr['last_name']) ?></div>
                            <div style="font-size:0.75rem; color:var(--text-muted);">#<?= $fr['employee_id'] ?></div>
                        </td>
                        <td style="padding:0.65rem 0.75rem; border-bottom:1px solid var(--border); font-size:0.85rem;"><?= htmlspecialchars($fr['department']) ?></td>
                        <td style="padding:0.65rem 0.75rem; border-bottom:1px solid var(--border);">
                            <span style="background:rgba(239,68,68,0.15); color:#F87171; padding:0.2rem 0.6rem; border-radius:6px; font-size:0.8rem; font-weight:700;">
                                <?= $fr['future_count'] ?> record(s)
                            </span>
                        </td>
                        <td style="padding:0.65rem 0.75rem; border-bottom:1px solid var(--border); font-size:0.82rem; color:var(--text-muted);">
                            <?= date('d M Y', strtotime($fr['earliest_future'])) ?>
                            <?= $fr['earliest_future'] !== $fr['latest_future'] ? ' → ' . date('d M Y', strtotime($fr['latest_future'])) : '' ?>
                        </td>
                        <td style="padding:0.65rem 0.75rem; border-bottom:1px solid var(--border);">
                            <div style="display: flex; gap: 0.5rem;">
                                <a href="../admin/manage_attendance.php?emp=<?= $fr['id'] ?>" class="btn btn-primary"
                                   style="padding:0.3rem 0.75rem; font-size:0.75rem;">View</a>
                                <form method="POST" action="../admin/manage_attendance.php" style="margin:0;" onsubmit="return confirm('Are you sure you want to delete these future-dated records?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete_future">
                                    <input type="hidden" name="employee_id" value="<?= $fr['id'] ?>">
                                    <button type="submit" class="btn btn-danger" style="padding:0.3rem 0.75rem; font-size:0.75rem;">Delete Future Records</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <div class="att-layout animate-fade-in">

            <!-- ── Left: Employee Search + List ── -->
            <div class="glass-panel" style="padding: 1.5rem;">
                <p class="section-title">🔍 Search Employee</p>

                <div class="search-wrap">
                    <span class="search-icon">🔍</span>
                    <input type="text" id="empSearch" placeholder="Name, ID or Department…" autocomplete="off">
                </div>

                <div class="emp-list" id="empList">
                    <?php foreach ($employees as $e): ?>
                        <a href="../admin/manage_attendance.php?emp=<?= $e['id'] ?>"
                           class="emp-item <?= ($selected_emp && $selected_emp['id'] == $e['id']) ? 'active' : '' ?>"
                           data-search="<?= strtolower($e['first_name'] . ' ' . $e['last_name'] . ' ' . $e['employee_id'] . ' ' . $e['department']) ?>">
                            <span class="emp-name"><?= htmlspecialchars($e['first_name'] . ' ' . $e['last_name']) ?></span>
                            <span class="emp-meta">#<?= $e['employee_id'] ?> · <?= htmlspecialchars($e['department']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- ── Right: Attendance View ── -->
            <div>
                <?php if ($selected_emp): ?>

                <div class="readonly-note">
                    ℹ️ Attendance is recorded automatically when employees sign in via their dashboard. Admin view is <strong>read-only</strong>.
                </div>

                <!-- Stats row -->
                <?php
                    $cur_month_present = 0;
                    $cur_month_total   = 0;
                    foreach ($daily_records as $r) {
                        if (substr($r['date'], 0, 7) === date('Y-m')) {
                            $cur_month_total++;
                            if ($r['status'] === 'present') $cur_month_present++;
                        }
                    }
                    $cur_pct = $cur_month_total > 0 ? round($cur_month_present / $cur_month_total * 100, 1) : 0;
                ?>
                <div class="dashboard-cards" style="margin-bottom: 1.5rem;">
                    <div class="stat-card glass-panel">
                        <div class="stat-title">This Month Present</div>
                        <div class="stat-value text-success"><?= $cur_month_present ?> days</div>
                        <p class="stat-note"><?= date('F Y') ?></p>
                    </div>
                    <div class="stat-card glass-panel">
                        <div class="stat-title">This Month %</div>
                        <div class="stat-value text-primary"><?= $cur_pct ?>%</div>
                        <p class="stat-note">Attendance rate</p>
                    </div>
                    <div class="stat-card glass-panel">
                        <div class="stat-title">Total Days Logged</div>
                        <div class="stat-value"><?= count($daily_records) ?></div>
                        <p class="stat-note">Last 90 days</p>
                    </div>
                </div>

                <!-- Daily log (read-only) -->
                <div class="glass-panel table-container" style="margin-bottom: 1.5rem;">
                    <div style="padding: 1.25rem 1.25rem 0;">
                        <p class="section-title">
                            📋 Daily Attendance Log — <?= htmlspecialchars($selected_emp['first_name'] . ' ' . $selected_emp['last_name']) ?>
                        </p>
                    </div>
                    <?php if (count($daily_records) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Day</th>
                                <th>Status</th>
                                <th>Signed In At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($daily_records as $rec):
                                $is_present = $rec['status'] === 'present';
                            ?>
                            <tr>
                                <td style="font-weight:600;"><?= date('d M Y', strtotime($rec['date'])) ?></td>
                                <td style="color:var(--text-muted); font-size:0.85rem;"><?= date('l', strtotime($rec['date'])) ?></td>
                                <td>
                                    <span class="<?= $is_present ? 'badge-present' : 'badge-absent' ?>">
                                        <?= $is_present ? '✓ Present' : '✗ Absent' ?>
                                    </span>
                                </td>
                                <td style="font-size:0.82rem; color:var(--text-muted);">
                                    <?= $is_present ? date('h:i A', strtotime($rec['signed_in_at'])) : '—' ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                        <div style="padding: 2rem; text-align: center; color: var(--text-muted);">
                            No attendance records found for this employee.
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Monthly summary (read-only) -->
                <div class="glass-panel table-container">
                    <div style="padding: 1.25rem 1.25rem 0;">
                        <p class="section-title">📊 Monthly Summary</p>
                    </div>
                    <?php if (count($monthly_summary) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th>Year</th>
                                <th>Working Days</th>
                                <th>Present</th>
                                <th>Absent</th>
                                <th>Attendance %</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($monthly_summary as $ms):
                                $pct    = $ms['total_days'] > 0 ? round(($ms['days_present'] / $ms['total_days']) * 100, 1) : 0;
                                $absent = $ms['total_days'] - $ms['days_present'];
                                $cls    = $pct >= 95 ? 'excellent' : ($pct >= 90 ? 'good' : ($pct >= 80 ? 'average' : 'poor'));
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($ms['month']) ?></td>
                                <td><?= $ms['year'] ?></td>
                                <td><?= $ms['total_days'] ?></td>
                                <td style="font-weight:600; color:#34D399;"><?= $ms['days_present'] ?></td>
                                <td style="font-weight:600; color:#F87171;"><?= $absent ?></td>
                                <td><span class="att-pct <?= $cls ?>"><?= $pct ?>%</span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                        <div style="padding: 2rem; text-align: center; color: var(--text-muted);">
                            No monthly summary available yet.
                        </div>
                    <?php endif; ?>
                </div>

                <?php else: ?>
                <!-- No employee selected -->
                <div class="glass-panel" style="padding: 3rem; text-align: center; color: var(--text-muted);">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">📅</div>
                    <p style="font-size: 1rem; font-weight: 600; margin-bottom: 0.5rem;">Select an Employee</p>
                    <p style="font-size: 0.875rem;">Search or click an employee on the left to view their attendance history.</p>
                </div>
                <?php endif; ?>
            </div>

        </div>
    </main>
</div>

<script>
    // Live search filter
    const searchInput = document.getElementById('empSearch');
    const items       = document.querySelectorAll('.emp-item');

    searchInput.addEventListener('input', function () {
        const q = this.value.toLowerCase().trim();
        items.forEach(item => {
            const text = item.dataset.search || '';
            item.classList.toggle('hidden', q !== '' && !text.includes(q));
        });
    });

    // Auto-focus search on page load
    searchInput.focus();
</script>
</body>
</html>
