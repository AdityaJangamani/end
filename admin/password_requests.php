<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/admin_login.php");
    exit();
}
require '../php/db.php';
require '../php/csrf.php';

// Create table if it doesn't exist (safety fallback)
$pdo->exec("
    CREATE TABLE IF NOT EXISTS password_reset_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        requested_password VARCHAR(255) NOT NULL,
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
    )
");

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $msg = "<div class='badge badge-danger' style='padding: 1rem; margin-bottom: 1rem; font-size:1rem;'>⚠ Invalid form submission.</div>";
    } else {
    $req_id = (int)$_POST['request_id'];
    $action = $_POST['action']; // 'approve' or 'reject'

    if ($action === 'approve') {
        // Fetch request details
        $stmt = $pdo->prepare("SELECT r.employee_id, r.requested_password FROM password_reset_requests r WHERE r.id = ? AND r.status = 'pending'");
        $stmt->execute([$req_id]);
        $req = $stmt->fetch();

        if ($req) {
            $hashed = password_hash($req['requested_password'], PASSWORD_BCRYPT);
            
            // Update employee password
            $pdo->prepare("UPDATE employees SET password = ? WHERE id = ?")
                ->execute([$hashed, $req['employee_id']]);
            
            // Mark request approved
            $pdo->prepare("UPDATE password_reset_requests SET status = 'approved' WHERE id = ?")
                ->execute([$req_id]);
            
            $msg = "<div class='badge badge-success' style='padding: 1rem; margin-bottom: 1rem; font-size:1rem;'>✅ Request Approved. Password updated successfully.</div>";
        }
    } elseif ($action === 'reject') {
        $pdo->prepare("UPDATE password_reset_requests SET status = 'rejected' WHERE id = ?")
            ->execute([$req_id]);
        $msg = "<div class='badge badge-danger' style='padding: 1rem; margin-bottom: 1rem; font-size:1rem;'>❌ Request Rejected.</div>";
    }
    }
}

// Fetch Pending Requests
$requests = $pdo->query("
    SELECT r.id, r.requested_at, r.requested_password, e.employee_id, e.first_name, e.last_name, e.department
    FROM password_reset_requests r
    JOIN employees e ON e.id = r.employee_id
    WHERE r.status = 'pending'
    ORDER BY r.requested_at DESC
")->fetchAll();

// Fetch Recent History (Approved/Rejected)
$history = $pdo->query("
    SELECT r.id, r.status, r.requested_at, e.employee_id, e.first_name, e.last_name
    FROM password_reset_requests r
    JOIN employees e ON e.id = r.employee_id
    WHERE r.status != 'pending'
    ORDER BY r.requested_at DESC
    LIMIT 20
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Requests - AI System</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .layout-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            align-items: start;
        }
        @media (max-width: 960px) {
            .layout-grid { grid-template-columns: 1fr; }
        }
        .action-btns {
            display: flex;
            gap: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include '../php/sidebar.php'; ?>

        <main class="main-content">
            <header>
                <h1 class="page-title">Password Reset Requests</h1>
            </header>

            <?= $msg ?>

            <div class="layout-grid animate-fade-in">
                
                <!-- Pending Requests Table -->
                <div class="glass-panel table-container">
                    <div style="padding: 1.5rem 1.5rem 0;">
                        <h2 style="font-size: 1.2rem; font-weight: 700; margin-bottom: 1rem;">⏳ Pending Requests (<?= count($requests) ?>)</h2>
                    </div>
                    <?php if (count($requests) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>Employee</th>
                                <th>Department</th>
                                <th>Requested Password</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requests as $req): ?>
                            <tr>
                                <td style="font-size: 0.85rem; color: var(--text-muted);">
                                    <?= date('d M Y, h:i A', strtotime($req['requested_at'])) ?>
                                </td>
                                <td>
                                    <div style="font-weight: 600;"><?= htmlspecialchars($req['first_name'] . ' ' . $req['last_name']) ?></div>
                                    <div style="font-size: 0.75rem; color: var(--text-muted);">#<?= htmlspecialchars($req['employee_id']) ?></div>
                                </td>
                                <td><?= htmlspecialchars($req['department']) ?></td>
                                <td>
                                    <span style="font-family: monospace; background: rgba(255,255,255,0.05); padding: 3px 8px; border-radius: 4px; cursor: pointer;" 
                                          onclick="this.textContent = this.dataset.revealed === 'true' ? '••••••••' : this.dataset.pw; this.dataset.revealed = this.dataset.revealed === 'true' ? 'false' : 'true';" 
                                          data-pw="<?= htmlspecialchars($req['requested_password']) ?>" data-revealed="false">••••••••</span>
                                </td>
                                <td>
                                    <div class="action-btns">
                                        <form method="POST" action="" onsubmit="return confirm('Approve this password reset?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <button type="submit" class="btn btn-primary" style="padding: 0.35rem 0.75rem; font-size: 0.8rem; background: #10B981; border-color: #10B981;">Approve</button>
                                        </form>
                                        <form method="POST" action="" onsubmit="return confirm('Reject this password reset?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                                            <input type="hidden" name="action" value="reject">
                                            <button type="submit" class="btn btn-danger" style="padding: 0.35rem 0.75rem; font-size: 0.8rem;">Reject</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div style="padding: 3rem; text-align: center; color: var(--text-muted);">
                        <div style="font-size: 3rem; margin-bottom: 1rem;">🎉</div>
                        <p>No pending password reset requests.</p>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- History Table -->
                <div class="glass-panel" style="padding: 1.5rem;">
                    <h2 style="font-size: 1.2rem; font-weight: 700; margin-bottom: 1rem;">📜 Recent History</h2>
                    <?php if (count($history) > 0): ?>
                        <ul style="list-style: none; padding: 0;">
                            <?php foreach ($history as $h): ?>
                                <li style="padding: 0.75rem 0; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
                                    <div>
                                        <div style="font-size: 0.9rem; font-weight: 600;">
                                            <?= htmlspecialchars($h['first_name'] . ' ' . $h['last_name']) ?>
                                        </div>
                                        <div style="font-size: 0.75rem; color: var(--text-muted);">
                                            <?= date('d M, h:i A', strtotime($h['requested_at'])) ?>
                                        </div>
                                    </div>
                                    <span class="badge <?= $h['status'] === 'approved' ? 'badge-success' : 'badge-danger' ?>" style="font-size: 0.7rem;">
                                        <?= ucfirst($h['status']) ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p style="color: var(--text-muted); font-size: 0.9rem; text-align: center; padding: 2rem 0;">No history yet.</p>
                    <?php endif; ?>
                </div>

            </div>
        </main>
    </div>
</body>
</html>
