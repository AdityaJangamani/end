<?php
session_name('emp_sess');
session_start();
if (!isset($_SESSION['employee_id'])) {
    header("Location: ../auth/employee_login.php");
    exit();
}
require '../php/db.php';
require '../php/csrf.php';
$emp_id = $_SESSION['employee_id'];

// Handle Sign In / Sign Off toggle
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['toggle_status'])) {
    if (csrf_verify()) {
    $current_status = $_POST['current_status'];
    $new_status = ($current_status === 'Active') ? 'Inactive' : 'Active';
    $stmt_update = $pdo->prepare("UPDATE employees SET status = ? WHERE id = ?");
    $stmt_update->execute([$new_status, $emp_id]);

    if ($new_status === 'Active') {
        $today = date('Y-m-d');
        // Insert today as present — IGNORE if already signed in today
        $pdo->prepare("
            INSERT IGNORE INTO attendance_daily (employee_id, date, status)
            VALUES (?, ?, 'present')
        ")->execute([$emp_id, $today]);
    }

    }
    header("Location: ../employee/employee_dashboard.php");
    exit();
}

// Handle Job Satisfaction update
$sat_msg = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_satisfaction'])) {
    if (!csrf_verify()) {
        $sat_msg = 'error';
    } else {
    $new_sat = (int) $_POST['job_satisfaction'];
    if ($new_sat >= 1 && $new_sat <= 5) {
        $stmt_sat = $pdo->prepare("UPDATE employees SET job_satisfaction = ? WHERE id = ?");
        $stmt_sat->execute([$new_sat, $emp_id]);
        $sat_msg = 'success';
        header("Location: ../employee/employee_dashboard.php?sat=updated");
        exit();
    } else {
        $sat_msg = 'error';
    }
    }
}
if (isset($_GET['sat']) && $_GET['sat'] === 'updated') {
    $sat_msg = 'success';
}

// Ensure profile_picture column exists
try { $pdo->exec("ALTER TABLE employees ADD COLUMN profile_picture VARCHAR(255) NULL DEFAULT NULL"); } catch (PDOException $ignored) {}

$stmt = $pdo->prepare("
    SELECT e.*, 
           p.productivity_score, p.manager_rating, p.projects_completed, p.hours_worked_per_week,
           s.base_salary, s.net_salary, s.bonus, s.deductions, s.month, s.year, s.id as salary_id
    FROM employees e
    LEFT JOIN performance p ON e.id = p.employee_id
    LEFT JOIN (
        SELECT s1.* FROM salary s1
        INNER JOIN (SELECT employee_id, MAX(id) as max_id FROM salary GROUP BY employee_id) s2
        ON s1.id = s2.max_id
    ) s ON e.id = s.employee_id
    WHERE e.id = ?
");
$stmt->execute([$emp_id]);
$emp = $stmt->fetch();

// Auto-compute years at company from date_joined and keep DB fresh
$joined_dt = new DateTime($emp['date_joined']);
$now_dt = new DateTime();
$diff = $joined_dt->diff($now_dt);
$years_at_company = round($diff->y + ($diff->m / 12), 2);
try {
    $pdo->prepare("UPDATE employees SET years_at_company = ? WHERE id = ?")->execute([$years_at_company, $emp_id]);
} catch (PDOException $e) {
    // Column may not exist yet — run migrate_years.php first
}

require '../php/predictions.php';

// Prepare data for ML model
$ml_data = [
    'Age'                => $emp['age'] ?? null,
    'YearsAtCompany'     => $years_at_company,
    'BaseSalary'         => $emp['base_salary']           ?? null,
    'JobSatisfaction'    => $emp['job_satisfaction']       ?? null,
    'PerformanceRating'  => $emp['manager_rating']        ?? null,
    'ProjectsCompleted'  => $emp['projects_completed']    ?? null,
    'HoursWorkedPerWeek' => $emp['hours_worked_per_week'] ?? 40,
];

// Check if prediction inputs are available
$missing_fields = array_keys(array_filter($ml_data, fn($v) => $v === null));
$prediction_available = empty($missing_fields);

$salary_pred = null;
$attrition_pred = null;
$promotion_pred = null;
$category_pred = null;

if ($prediction_available) {
    $salary_pred    = predict_salary($ml_data);
    $attrition_pred = predict_attrition($ml_data);
    $promotion_pred = predict_promotion($ml_data);
    $category_pred  = predict_category($ml_data);
}

// Fetch recent announcements
$announcements = [];
try {
    $stmt_ann = $pdo->query("SELECT * FROM announcements ORDER BY created_at DESC LIMIT 5");
    $announcements = $stmt_ann->fetchAll();
} catch (PDOException $e) {
    // Fail silently
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Dashboard - AI System</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="app-container" style="justify-content: center; padding: 2rem;">
        <main class="main-content" style="max-width: 900px; width: 100%;">
            <header style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2rem;">
                <h1 class="page-title">Welcome, <?= htmlspecialchars($emp['first_name']) ?></h1>
                <a href="../auth/employee_logout.php" class="btn btn-danger">Logout</a>
            </header>

            <!-- Profile -->
            <div class="glass-panel animate-fade-in" style="padding: 1.5rem; margin-bottom: 1.5rem;">
                <h2 style="margin-bottom: 1.2rem;">My Profile</h2>

                <div style="display:flex;gap:2rem;align-items:flex-start;flex-wrap:wrap;">

                    <!-- Avatar column -->
                    <div style="display:flex;flex-direction:column;align-items:center;gap:.75rem;min-width:130px;">
                        <div style="position:relative;">
                            <?php
                                $pic = !empty($emp['profile_picture']) ? $emp['profile_picture'] : null;
                                $avatarStyle = 'width:110px;height:110px;border-radius:50%;object-fit:cover;
                                    border:3px solid var(--primary);box-shadow:0 4px 16px rgba(99,102,241,.35);';
                            ?>
                            <?php if ($pic && file_exists(__DIR__ . '/' . $pic)): ?>
                                <img src="<?= htmlspecialchars($pic) ?>?v=<?= filemtime(__DIR__.'/'.$pic) ?>"
                                     id="avatarPreview" alt="Profile" style="<?= $avatarStyle ?>">
                            <?php else: ?>
                                <div id="avatarPreview"
                                     style="<?= $avatarStyle ?>display:flex;align-items:center;justify-content:center;
                                            background:linear-gradient(135deg,#4F46E5,#7C3AED);font-size:2.5rem;color:#fff;">
                                    <?= mb_strtoupper(mb_substr($emp['first_name'], 0, 1)) ?>
                                </div>
                            <?php endif; ?>
                            <!-- Camera overlay button -->
                            <label for="picInput"
                                   style="position:absolute;bottom:4px;right:4px;width:30px;height:30px;
                                          background:#4F46E5;border-radius:50%;cursor:pointer;
                                          display:flex;align-items:center;justify-content:center;
                                          box-shadow:0 2px 8px rgba(0,0,0,.4);transition:background .2s;"
                                   title="Change photo">
                                <span style="font-size:.9rem;">📷</span>
                            </label>
                            <input type="file" id="picInput" accept="image/*"
                                   style="display:none;" onchange="uploadPic(this)">
                        </div>
                        <p id="picMsg" style="font-size:.75rem;color:#34d399;text-align:center;display:none;"></p>
                    </div>

                    <!-- Info column -->
                    <div style="flex:1;display:flex;flex-direction:column;gap:.5rem;">
                        <p><strong>Name:</strong> <?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?></p>
                        <p><strong>Email:</strong> <?= htmlspecialchars($emp['email']) ?></p>
                        <p><strong>Department:</strong> <?= htmlspecialchars($emp['department']) ?></p>
                        <p><strong>Role:</strong> <?= htmlspecialchars($emp['job_role']) ?></p>
                        <p><strong>Date Joined:</strong> <?= htmlspecialchars($emp['date_joined']) ?></p>
                        <p><strong>Years at Company:</strong> <?= $years_at_company ?> years</p>
                        <div style="display:flex;align-items:center;gap:1rem;margin-top:.5rem;flex-wrap:wrap;">
                            <p style="margin:0;"><strong>Shift Status:</strong>
                                <span class="badge <?= $emp['status'] === 'Active' ? 'badge-success' : 'badge-danger' ?>">
                                    <?= htmlspecialchars($emp['status']) ?>
                                </span>
                            </p>
                            <form method="POST" action="" style="margin:0;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="current_status" value="<?= htmlspecialchars($emp['status']) ?>">
                                <button type="submit" name="toggle_status"
                                        class="btn <?= $emp['status'] === 'Active' ? 'btn-danger' : 'btn-success' ?>"
                                        style="padding:.4rem 1rem;font-size:.85rem;">
                                    <?= $emp['status'] === 'Active' ? 'Sign Off for the Day' : 'Sign In to Job' ?>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <script>
            function uploadPic(input) {
                if (!input.files || !input.files[0]) return;
                const file = input.files[0];
                if (file.size > 3 * 1024 * 1024) {
                    alert('Image must be under 3 MB.'); return;
                }
                // Live preview
                const reader = new FileReader();
                reader.onload = e => {
                    const prev = document.getElementById('avatarPreview');
                    if (prev.tagName === 'IMG') {
                        prev.src = e.target.result;
                    } else {
                        const img = document.createElement('img');
                        img.id = 'avatarPreview';
                        img.src = e.target.result;
                        img.alt = 'Profile';
                        img.style.cssText = prev.style.cssText;
                        prev.parentNode.replaceChild(img, prev);
                    }
                };
                reader.readAsDataURL(file);

                // Upload
                const fd = new FormData();
                fd.append('profile_pic', file);
                fd.append('csrf_token', '<?php echo csrf_token(); ?>');
                const msg = document.getElementById('picMsg');
                msg.style.display = 'block';
                msg.style.color   = '#a5b4fc';
                msg.textContent   = 'Uploading…';

                fetch('upload_profile_pic.php', { method: 'POST', body: fd })
                    .then(r => r.json())
                    .then(res => {
                        msg.style.color = res.success ? '#34d399' : '#f87171';
                        msg.textContent = res.success ? '✔ Photo saved!' : '✖ ' + res.message;
                        setTimeout(() => { msg.style.display = 'none'; }, 3000);
                    })
                    .catch(() => {
                        msg.style.color = '#f87171';
                        msg.textContent = '✖ Upload failed.';
                    });
            }
            </script>

            <!-- Announcements Section -->
            <div class="glass-panel animate-fade-in delay-1" style="padding: 1.5rem; margin-bottom: 1.5rem;">
                <h2 style="margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.5rem; font-size: 1.5rem;">
                    📢 Company Announcements
                </h2>
                <div style="display: flex; flex-direction: column; gap: 1rem;">
                    <?php if (!empty($announcements)): ?>
                        <?php foreach ($announcements as $ann): ?>
                            <div style="background: rgba(255, 255, 255, 0.02); border: 1px solid var(--border); border-radius: 8px; padding: 1.25rem; position: relative;">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; flex-wrap: wrap; margin-bottom: 0.5rem;">
                                    <h3 style="font-size: 1.1rem; font-weight: 600; color: #fff; margin: 0; display: flex; align-items: center; gap: 8px;">
                                        <span style="width: 8px; height: 8px; border-radius: 50%; background: <?= $ann['priority'] === 'high' ? 'var(--danger)' : ($ann['priority'] === 'medium' ? 'var(--warning)' : 'var(--secondary)') ?>; display: inline-block;"></span>
                                        <?= htmlspecialchars($ann['title']) ?>
                                    </h3>
                                    <div style="display: flex; align-items: center; gap: 8px; font-size: 0.75rem;">
                                        <?php
                                        $pClass = 'badge-warning';
                                        if ($ann['priority'] === 'high') $pClass = 'badge-danger';
                                        if ($ann['priority'] === 'low') $pClass = 'badge-success';
                                        ?>
                                        <span class="badge <?= $pClass ?>"><?= htmlspecialchars($ann['priority']) ?> Priority</span>
                                        <span style="color: var(--text-muted);">&bull;</span>
                                        <span style="color: var(--text-muted);"><?= date('d M Y H:i', strtotime($ann['created_at'])) ?></span>
                                    </div>
                                </div>
                                <p style="font-size: 0.9rem; color: var(--text-muted); line-height: 1.6; margin: 0 0 0.5rem 0;">
                                    <?= nl2br(htmlspecialchars($ann['content'])) ?>
                                </p>
                                <div style="font-size: 0.75rem; color: rgba(255, 255, 255, 0.3); font-weight: 500;">
                                    Posted by: <span style="color: var(--text-muted);"><?= htmlspecialchars($ann['author']) ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="color: var(--text-muted); text-align: center; margin: 0; padding: 1rem;">No announcements available.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Job Satisfaction (Employee Self-Service) -->
            <?php
                $sat_labels = [1 => 'Very Dissatisfied', 2 => 'Dissatisfied', 3 => 'Neutral', 4 => 'Satisfied', 5 => 'Very Satisfied'];
                $current_sat = (int)($emp['job_satisfaction'] ?? 3);
                $sat_colors  = [1 => '#F87171', 2 => '#FB923C', 3 => '#FBBF24', 4 => '#34D399', 5 => '#818CF8'];
                $sat_color   = $sat_colors[$current_sat] ?? '#FBBF24';
            ?>
            <div class="glass-panel animate-fade-in delay-1" style="padding: 1.5rem; margin-bottom: 1.5rem;">
                <h2 style="margin-bottom: 1rem;">My Job Satisfaction</h2>

                <?php if ($sat_msg === 'success'): ?>
                    <div class="alert alert--success">
                        ✅ Job satisfaction updated successfully!
                    </div>
                <?php elseif ($sat_msg === 'error'): ?>
                    <div class="alert alert--danger">
                        ❌ Invalid value. Please select a rating between 1 and 5.
                    </div>
                <?php endif; ?>

                <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                    <span style="font-size: 0.95rem;"><strong>Current Rating:</strong></span>
                    <span style="font-size: 1.5rem; font-weight: 700; color: <?= $sat_color ?>;">
                        <?= $current_sat ?> / 5
                    </span>
                    <span style="font-size: 0.85rem; color: var(--text-muted);">
                        — <?= $sat_labels[$current_sat] ?? 'Unknown' ?>
                    </span>
                </div>

                <!-- Visual bar -->
                <div style="width: 100%; height: 8px; background: var(--surface-light); border-radius: 4px; overflow: hidden; margin-bottom: 1.25rem;">
                    <div style="height: 100%; width: <?= ($current_sat / 5) * 100 ?>%; background: <?= $sat_color ?>; border-radius: 4px; transition: width 0.5s;"></div>
                </div>

                <form method="POST" action="" style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
                    <?= csrf_field() ?>
                    <label style="font-size: 0.9rem; font-weight: 500;">Update your satisfaction:</label>
                    <select name="job_satisfaction" class="form-control" style="width: auto; min-width: 220px;" required>
                        <option value="">-- Select --</option>
                        <option value="1" <?= $current_sat === 1 ? 'selected' : '' ?>>1 — Very Dissatisfied</option>
                        <option value="2" <?= $current_sat === 2 ? 'selected' : '' ?>>2 — Dissatisfied</option>
                        <option value="3" <?= $current_sat === 3 ? 'selected' : '' ?>>3 — Neutral</option>
                        <option value="4" <?= $current_sat === 4 ? 'selected' : '' ?>>4 — Satisfied</option>
                        <option value="5" <?= $current_sat === 5 ? 'selected' : '' ?>>5 — Very Satisfied</option>
                    </select>
                    <button type="submit" name="update_satisfaction" class="btn btn-primary" style="padding: 0.5rem 1.25rem; font-size: 0.85rem;">
                        Save
                    </button>
                </form>
            </div>

            <!-- Salary -->
            <div class="glass-panel animate-fade-in delay-1" style="padding: 1.5rem; margin-bottom: 1.5rem;">
                <h2 style="margin-bottom: 1rem;">My Salary — <?= htmlspecialchars(($emp['month'] ?? 'N/A') . ' ' . ($emp['year'] ?? '')) ?></h2>
                <div class="dashboard-cards">
                    <div class="stat-card glass-panel">
                        <div class="stat-title">Base Salary</div>
                        <div class="stat-value text-primary">₹<?= number_format($emp['base_salary'] ?? 0, 2) ?></div>
                    </div>
                    <div class="stat-card glass-panel">
                        <div class="stat-title">Bonus</div>
                        <div class="stat-value text-success">₹<?= number_format($emp['bonus'] ?? 0, 2) ?></div>
                    </div>
                    <div class="stat-card glass-panel">
                        <div class="stat-title">Deductions</div>
                        <div class="stat-value" style="color:var(--danger)">₹<?= number_format($emp['deductions'] ?? 0, 2) ?></div>
                    </div>
                    <div class="stat-card glass-panel">
                        <div class="stat-title">Net Salary</div>
                        <div class="stat-value text-success">₹<?= number_format($emp['net_salary'] ?? 0, 2) ?></div>
                    </div>
                </div>
                <?php if ($emp['salary_id']): ?>
                    <a href="../admin/payslip.php?id=<?= $emp['salary_id'] ?>" class="btn btn-primary" style="margin-top:1rem; display:inline-block;">View Payslip</a>
                <?php endif; ?>
            </div>

            <!-- Attendance History -->
            <?php
            $att_stmt = $pdo->prepare("
                SELECT date, status, signed_in_at
                FROM attendance_daily
                WHERE employee_id = ?
                ORDER BY date DESC
                LIMIT 30
            ");
            $att_stmt->execute([$emp_id]);
            $attendance_records = $att_stmt->fetchAll();
            ?>
            <div class="glass-panel animate-fade-in delay-2" style="padding: 1.5rem; margin-bottom: 1.5rem;">
                <h2 style="margin-bottom: 1rem;">My Attendance History</h2>
                    <?php if (count($attendance_records) > 0): ?>
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr>
                                <th style="padding: 0.75rem; text-align: left; border-bottom: 1px solid var(--border); color: var(--text-muted); font-size: 0.85rem;">Date</th>
                                <th style="padding: 0.75rem; text-align: left; border-bottom: 1px solid var(--border); color: var(--text-muted); font-size: 0.85rem;">Day</th>
                                <th style="padding: 0.75rem; text-align: left; border-bottom: 1px solid var(--border); color: var(--text-muted); font-size: 0.85rem;">Status</th>
                                <th style="padding: 0.75rem; text-align: left; border-bottom: 1px solid var(--border); color: var(--text-muted); font-size: 0.85rem;">Signed In At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attendance_records as $att): ?>
                                <tr>
                                    <td style="padding: 0.75rem; border-bottom: 1px solid var(--border); font-weight: 600;">
                                        <?= date('d M Y', strtotime($att['date'])) ?>
                                    </td>
                                    <td style="padding: 0.75rem; border-bottom: 1px solid var(--border); color: var(--text-muted); font-size: 0.85rem;">
                                        <?= date('l', strtotime($att['date'])) ?>
                                    </td>
                                    <td style="padding: 0.75rem; border-bottom: 1px solid var(--border);">
                                        <?php if ($att['status'] === 'present'): ?>
                                            <span style="background: rgba(16,185,129,0.15); color: #34D399; padding: 0.2rem 0.6rem; border-radius: 6px; font-size: 0.8rem; font-weight: 700;">✓ Present</span>
                                        <?php else: ?>
                                            <span style="background: rgba(239,68,68,0.15); color: #F87171; padding: 0.2rem 0.6rem; border-radius: 6px; font-size: 0.8rem; font-weight: 700;">✗ Absent</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 0.75rem; border-bottom: 1px solid var(--border); font-size: 0.85rem; color: var(--text-muted);">
                                        <?= date('h:i A', strtotime($att['signed_in_at'])) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="color: var(--text-muted); text-align: center; padding: 1rem;">No attendance records available yet.</p>
                <?php endif; ?>
            </div>

            <!-- Performance -->
            <div class="glass-panel animate-fade-in delay-3" style="padding: 1.5rem; margin-bottom: 1.5rem;">
                <h2 style="margin-bottom: 1rem;">My Performance</h2>
                <div class="dashboard-cards">
                    <div class="stat-card glass-panel">
                        <div class="stat-title">Productivity Score</div>
                        <div class="stat-value text-primary"><?= htmlspecialchars($emp['productivity_score'] ?? 'N/A') ?></div>
                    </div>
                    <div class="stat-card glass-panel">
                        <div class="stat-title">Manager Rating</div>
                        <div class="stat-value text-primary"><?= htmlspecialchars($emp['manager_rating'] ?? 'N/A') ?> / 5</div>
                    </div>
                    <div class="stat-card glass-panel">
                        <div class="stat-title">Projects Completed</div>
                        <div class="stat-value text-success"><?= htmlspecialchars($emp['projects_completed'] ?? 'N/A') ?></div>
                    </div>
                </div>
            </div>

            <!-- AI Career Insights -->
            <div class="glass-panel animate-fade-in delay-3" style="padding: 1.75rem; margin-bottom: 1.5rem;">
                <h2 style="margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                    🤖 My AI Career Insights
                </h2>
                
                <?php if (!$prediction_available): ?>
                    <div class="alert alert--warning animate-fade-in" style="margin: 0;">
                        <strong>AI Predictions Unavailable</strong> — Some critical performance or salary data is missing. Please contact Administrator to update your records.
                    </div>
                <?php else: ?>
                    <div class="dashboard-cards" style="margin-bottom: 1.5rem;">
                        <div class="stat-card glass-panel">
                            <div class="stat-title">Market Fair Salary</div>
                            <div class="stat-value text-success" style="font-size: 1.6rem;">
                                <?php if ($salary_pred && $salary_pred['predicted_salary'] > 0): ?>
                                    ₹<?= number_format($salary_pred['predicted_salary'], 2) ?>
                                <?php else: ?>
                                    <span class="stat-unavailable">—</span>
                                <?php endif; ?>
                            </div>
                            <p class="stat-note">Based on your experience & performance</p>
                        </div>
                        
                        <div class="stat-card glass-panel">
                            <div class="stat-title">Promotion Readiness</div>
                            <div class="stat-value text-primary">
                                <?php if ($promotion_pred): ?>
                                    <?= htmlspecialchars($promotion_pred['promotion_probability']) ?>%
                                <?php else: ?>
                                    <span class="stat-unavailable">—</span>
                                <?php endif; ?>
                            </div>
                            <p class="stat-note">Random Forest Classification</p>
                        </div>

                        <?php
                        $cat_val = $category_pred['category'] ?? 1;
                        $cat_map = [0 => 'Underperformer', 1 => 'Steady Performer', 2 => 'High Potential'];
                        $cat_badges = [0 => 'badge-danger', 1 => 'badge-warning', 2 => 'badge-success'];
                        $cat_text = $cat_map[$cat_val] ?? 'Steady Performer';
                        $cat_badge = $cat_badges[$cat_val] ?? 'badge-warning';
                        ?>
                        <div class="stat-card glass-panel">
                            <div class="stat-title">Talent Classification</div>
                            <div class="stat-value" style="font-size: 1.4rem; padding-top: 0.5rem;">
                                <span class="badge <?= $cat_badge ?>" style="font-size: 1.1rem; padding: 0.4rem 1rem;">
                                    <?= htmlspecialchars($cat_text) ?>
                                </span>
                            </div>
                            <p class="stat-note" style="margin-top: 1rem;">Decision Tree Categorizer</p>
                        </div>
                    </div>

                    <?php
                    $risk_val = $attrition_pred['attrition_risk'] ?? 0;
                    $risk_color = $risk_val > 70 ? 'var(--danger)' : ($risk_val > 40 ? 'var(--warning)' : 'var(--secondary)');
                    ?>
                    <div style="background: rgba(255, 255, 255, 0.02); padding: 1.25rem; border-radius: 8px; border: 1px solid var(--border);">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                            <span style="font-size: 0.9rem; font-weight: 600;">Workplace Retention Estimation (Risk)</span>
                            <span style="font-size: 1.1rem; font-weight: 700; color: <?= $risk_color ?>;"><?= $risk_val ?>%</span>
                        </div>
                        <div style="width: 100%; height: 8px; background: var(--surface-light); border-radius: 4px; overflow: hidden;">
                            <div style="height: 100%; width: <?= $risk_val ?>%; background: <?= $risk_color ?>; border-radius: 4px; transition: width 0.5s;"></div>
                        </div>
                        <p style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.5rem; line-height: 1.4;">
                            *This estimates retention indicators using standard prediction models. It helps estimate workplace engagement.
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>

