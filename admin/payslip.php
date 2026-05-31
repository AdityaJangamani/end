<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/admin_login.php");
    exit();
}
require '../php/db.php';
require '../php/csrf.php';

// Validate input — cast to integer immediately
$salary_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$salary_id) {
    header("Location: salary.php?error=invalid_id");
    exit();
}

$stmt = $pdo->prepare("
    SELECT e.first_name, e.last_name, e.email, e.department, e.job_role, e.profile_picture, s.*
    FROM salary s
    JOIN employees e ON s.employee_id = e.id
    WHERE s.id = ?
");
$stmt->execute([$salary_id]);
$data = $stmt->fetch();

// Resolve profile picture for display
$picPath = '';
if (!empty($data['profile_picture'])) {
    $abs = dirname(__DIR__) . '/' . $data['profile_picture'];
    if (file_exists($abs)) {
        $picPath = $data['profile_picture'] . '?v=' . filemtime($abs);
    }
}

if (!$data) {
    header("Location: salary.php?error=not_found");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payslip — <?= htmlspecialchars($data['first_name'] . ' ' . $data['last_name']) ?></title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .payslip-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            color: #333;
            padding: 3rem;
            border-radius: 8px;
        }

        .payslip-header {
            text-align: center;
            margin-bottom: 2rem;
            border-bottom: 2px solid #ccc;
            padding-bottom: 1rem;
        }

        .payslip-header h2 {
            color: #4F46E5;
        }

        .payslip-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1rem;
        }

        .payslip-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 2rem;
            color: #333;
        }

        .payslip-table th,
        .payslip-table td {
            padding: 10px;
            text-align: left;
            color: #333;
        }

        .payslip-table th {
            text-align: left;
            border-bottom: 1px solid #ccc;
        }

        .payslip-table .amount {
            text-align: right;
        }

        .payslip-table .deduction {
            text-align: right;
            color: red;
        }

        .payslip-table .total-row {
            border-top: 2px solid #ccc;
            font-weight: bold;
            font-size: 1.2rem;
        }

        /* ── Email modal ── */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,.55);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .modal-overlay.active { display: flex; }

        .modal-box {
            background: #1e1b4b;
            border: 1px solid rgba(99,102,241,.35);
            border-radius: 14px;
            padding: 2.2rem 2.5rem;
            width: 420px;
            max-width: 94vw;
            box-shadow: 0 20px 60px rgba(0,0,0,.5);
            animation: modal-in .25s ease;
        }
        @keyframes modal-in {
            from { opacity:0; transform:translateY(-18px) scale(.97); }
            to   { opacity:1; transform:none; }
        }
        .modal-box h3 {
            margin: 0 0 .6rem;
            font-size: 1.15rem;
            color: #e0e7ff;
        }
        .modal-box p  { margin: 0 0 1.2rem; color: #a5b4fc; font-size: .9rem; }
        .modal-email  {
            display: block;
            width: 100%;
            padding: .6rem .85rem;
            border-radius: 8px;
            border: 1px solid rgba(99,102,241,.5);
            background: rgba(255,255,255,.05);
            color: #e0e7ff;
            font-size: .95rem;
            margin-bottom: 1.4rem;
            box-sizing: border-box;
        }
        .modal-actions { display: flex; gap: .75rem; justify-content: flex-end; }
        .btn-send-email {
            background: linear-gradient(135deg,#4F46E5,#7C3AED);
            color: #fff;
            border: none;
            padding: .55rem 1.3rem;
            border-radius: 8px;
            cursor: pointer;
            font-size: .9rem;
            font-weight: 600;
            transition: opacity .2s;
        }
        .btn-send-email:disabled { opacity:.55; cursor:not-allowed; }
        .btn-cancel {
            background: rgba(255,255,255,.07);
            color: #a5b4fc;
            border: 1px solid rgba(99,102,241,.3);
            padding: .55rem 1.1rem;
            border-radius: 8px;
            cursor: pointer;
            font-size: .9rem;
            transition: background .2s;
        }
        .btn-cancel:hover { background: rgba(255,255,255,.12); }

        /* ── Toast ── */
        #toast {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            padding: .85rem 1.4rem;
            border-radius: 10px;
            font-size: .9rem;
            font-weight: 600;
            color: #fff;
            z-index: 2000;
            opacity: 0;
            transform: translateY(12px);
            transition: opacity .35s, transform .35s;
            pointer-events: none;
        }
        #toast.show { opacity:1; transform:none; }
        #toast.success { background: #059669; }
        #toast.error   { background: #dc2626; }
    </style>
</head>

<body>
    <div class="app-container">
        <?php include '../php/sidebar.php'; ?>

        <main class="main-content">
            <header class="page-header">
                <h1 class="page-title">Generate Payslip</h1>
                <div style="display:flex;gap:.6rem;">
                    <button onclick="window.print()" class="btn btn-primary">🖨️ Print Payslip</button>
                    <button onclick="openEmailModal()" class="btn btn-success" id="btn-open-email">📧 Send to Email</button>
                </div>
            </header>

            <div class="payslip-container animate-fade-in delay-1">
                <div class="payslip-header">
                    <h2>AI System Inc.</h2>
                    <p>Payslip for the month of
                        <?= htmlspecialchars($data['month'] . ' ' . $data['year']) ?>
                    </p>
                </div>

                <div class="payslip-row" style="align-items:center;gap:1.5rem;">
                    <!-- Employee photo -->
                    <?php if ($picPath): ?>
                        <img src="<?= htmlspecialchars($picPath) ?>" alt="Employee Photo"
                             style="width:72px;height:72px;border-radius:50%;object-fit:cover;
                                    border:2px solid #4F46E5;flex-shrink:0;">
                    <?php else: ?>
                        <div style="width:72px;height:72px;border-radius:50%;flex-shrink:0;
                                    background:linear-gradient(135deg,#4F46E5,#7C3AED);
                                    display:flex;align-items:center;justify-content:center;
                                    font-size:1.8rem;color:#fff;border:2px solid #4F46E5;">
                            <?= mb_strtoupper(mb_substr($data['first_name'], 0, 1)) ?>
                        </div>
                    <?php endif; ?>

                    <div>
                        <strong>Employee Name:</strong>
                        <?= htmlspecialchars($data['first_name'] . ' ' . $data['last_name']) ?><br>
                        <strong>Email:</strong>
                        <?= htmlspecialchars($data['email']) ?><br>
                        <strong>Department:</strong>
                        <?= htmlspecialchars($data['department']) ?><br>
                        <strong>Role:</strong>
                        <?= htmlspecialchars($data['job_role']) ?>
                    </div>
                </div>

                <table class="payslip-table">
                    <tr>
                        <th>Description</th>
                        <th class="amount">Amount (INR)</th>
                    </tr>
                    <tr>
                        <td>Base Salary</td>
                        <td class="amount">₹<?= number_format($data['base_salary'], 2) ?></td>
                    </tr>
                    <tr>
                        <td>Bonus</td>
                        <td class="amount">₹<?= number_format($data['bonus'], 2) ?></td>
                    </tr>
                    <tr>
                        <td>Deductions</td>
                        <td class="deduction">-₹<?= number_format($data['deductions'], 2) ?></td>
                    </tr>
                    <tr class="total-row">
                        <td>Net Salary</td>
                        <td class="amount">₹<?= number_format($data['net_salary'], 2) ?></td>
                    </tr>
                </table>
            </div>
        </main>
    </div>

    <!-- ── Email Confirmation Modal ── -->
    <div class="modal-overlay" id="emailModal">
        <div class="modal-box">
            <h3>📧 Send Payslip by Email</h3>
            <p>The payslip will be sent to the employee's registered email address.</p>
            <label style="color:#a5b4fc;font-size:.82rem;display:block;margin-bottom:.35rem;">Recipient email</label>
            <input class="modal-email" type="email" id="recipientEmail"
                   value="<?= htmlspecialchars($data['email']) ?>"
                   readonly>
            <div class="modal-actions">
                <button class="btn-cancel" onclick="closeEmailModal()">Cancel</button>
                <button class="btn-send-email" id="btnSend" onclick="sendPayslipEmail()">Send Payslip</button>
            </div>
        </div>
    </div>

    <!-- Toast notification -->
    <div id="toast"></div>

    <script>
        const SALARY_ID = <?= (int)$salary_id ?>;

        function openEmailModal()  { document.getElementById('emailModal').classList.add('active'); }
        function closeEmailModal() { document.getElementById('emailModal').classList.remove('active'); }

        // Close modal when clicking the backdrop
        document.getElementById('emailModal').addEventListener('click', function(e) {
            if (e.target === this) closeEmailModal();
        });

        function showToast(msg, type) {
            const t = document.getElementById('toast');
            t.textContent = msg;
            t.className = 'show ' + type;
            setTimeout(() => { t.className = ''; }, 4000);
        }

        function sendPayslipEmail() {
            const btn = document.getElementById('btnSend');
            btn.disabled = true;
            btn.textContent = 'Sending…';

            const formData = new FormData();
            formData.append('salary_id', SALARY_ID);
            formData.append('csrf_token', '<?php echo csrf_token(); ?>');

            fetch('send_payslip_email.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(res => {
                closeEmailModal();
                if (res.success) {
                    showToast('✅ ' + res.message, 'success');
                } else {
                    showToast('❌ ' + res.message, 'error');
                }
            })
            .catch(() => {
                closeEmailModal();
                showToast('❌ Network error. Please try again.', 'error');
            })
            .finally(() => {
                btn.disabled = false;
                btn.textContent = 'Send Payslip';
            });
        }
    </script>
</body>

</html>
