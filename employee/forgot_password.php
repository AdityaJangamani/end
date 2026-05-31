<?php
session_name('emp_sess');
session_start();
require '../php/db.php';
require '../php/email_config.php';
require '../php/SimpleMailer.php';
require '../php/csrf.php';

// ── Helpers ──────────────────────────────────────────────────────────────────
function clearFPSession(): void {
    unset(
        $_SESSION['fp_email'],
        $_SESSION['fp_db_id'],
        $_SESSION['fp_otp'],
        $_SESSION['fp_otp_expiry'],
        $_SESSION['fp_verified']
    );
}

function currentStep(): int {
    if (!empty($_SESSION['fp_verified']))                 return 3;
    if (!empty($_SESSION['fp_otp']))                      return 2;
    return 1;
}

$error   = '';
$success = '';

// ════════════════════════════════════════════════════════
//  POST HANDLERS
// ════════════════════════════════════════════════════════

// ── STEP 1: Validate email & send OTP ───────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_verify()) {
    $error = 'Invalid form submission. Please try again.';
} elseif (isset($_POST['action']) && $_POST['action'] === 'send_otp') {
    clearFPSession();
    $email = trim($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        global $pdo;
        $stmt = $pdo->prepare("SELECT id, first_name FROM employees WHERE email = ?");
        $stmt->execute([$email]);
        $emp = $stmt->fetch();

        if (!$emp) {
            $error = 'No employee account found with that email address.';
        } else {
            // Generate 6-digit OTP
            $otp    = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $expiry = time() + 600; // 10 minutes

            $_SESSION['fp_email']      = $email;
            $_SESSION['fp_db_id']      = $emp['id'];
            $_SESSION['fp_otp']        = $otp;
            $_SESSION['fp_otp_expiry'] = $expiry;

            // Build OTP email
            $name    = htmlspecialchars($emp['first_name']);
            $expires = date('h:i A', $expiry);
            $html = <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f0f2f8;font-family:'Segoe UI',Arial,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="padding:40px 0;">
    <tr><td align="center">
      <table width="520" cellpadding="0" cellspacing="0"
             style="background:#fff;border-radius:12px;overflow:hidden;
                    box-shadow:0 4px 20px rgba(0,0,0,0.08);">
        <!-- Header -->
        <tr>
          <td style="background:linear-gradient(135deg,#10B981,#059669);
                      padding:32px 40px;text-align:center;">
            <h1 style="margin:0;color:#fff;font-size:22px;">AI System</h1>
            <p style="margin:6px 0 0;color:rgba(255,255,255,.8);font-size:13px;">Password Reset Request</p>
          </td>
        </tr>
        <!-- Body -->
        <tr>
          <td style="padding:36px 40px 28px;">
            <p style="margin:0 0 12px;font-size:15px;color:#374151;">Hi <strong>{$name}</strong>,</p>
            <p style="margin:0 0 24px;font-size:14px;color:#6b7280;line-height:1.7;">
              We received a request to reset your Portal password. Use the verification code below:
            </p>
            <!-- OTP Box -->
            <div style="text-align:center;margin:0 0 28px;">
              <div style="display:inline-block;background:#f0fdf4;border:2px dashed #10B981;
                          border-radius:12px;padding:20px 48px;">
                <p style="margin:0;font-size:38px;font-weight:700;
                           letter-spacing:14px;color:#059669;font-family:monospace;">
                  {$otp}
                </p>
              </div>
            </div>
            <p style="margin:0 0 8px;font-size:13px;color:#9ca3af;text-align:center;">
              ⏱ This code expires at <strong>{$expires}</strong> (10 minutes)
            </p>
            <p style="margin:24px 0 0;font-size:12px;color:#d1d5db;">
              If you did not request a password reset, please ignore this email. Your password will remain unchanged.
            </p>
          </td>
        </tr>
        <!-- Footer -->
        <tr>
          <td style="background:#f9fafb;padding:16px 40px;text-align:center;">
            <p style="margin:0;font-size:11px;color:#9ca3af;">AI System Inc. · Automated Message — Do not reply</p>
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;

            try {
                $mailer = new SimpleMailer(SMTP_USERNAME, SMTP_PASSWORD, SMTP_FROM_NAME);
                $mailer->send(
                    $email,
                    $emp['first_name'],
                    'Your Password Reset Code',
                    $html
                );
                $success = "A 6-digit verification code has been sent to <strong>{$email}</strong>. Check your inbox (and spam folder).";
            } catch (Exception $e) {
                clearFPSession();
                $error = 'Could not send email: ' . $e->getMessage();
            }
        }
    }
}

// ── STEP 2: Verify OTP ───────────────────────────────────
elseif (isset($_POST['action']) && $_POST['action'] === 'verify_otp') {
    $entered = trim(str_replace(' ', '', $_POST['otp'] ?? ''));

    if (empty($_SESSION['fp_otp'])) {
        $error = 'Session expired. Please start again.';
        clearFPSession();
    } elseif (time() > $_SESSION['fp_otp_expiry']) {
        $error = 'The verification code has expired (10-minute limit). Please request a new one.';
        clearFPSession();
    } elseif ($entered !== $_SESSION['fp_otp']) {
        $error = 'Incorrect code. Please try again.';
    } else {
        // OTP correct — advance to step 3
        $_SESSION['fp_verified'] = true;
        $success = 'Identity verified! Now set your new password.';
    }
}

// ── STEP 3: Reset password ───────────────────────────────
elseif (isset($_POST['action']) && $_POST['action'] === 'reset_password') {
    if (empty($_SESSION['fp_verified']) || empty($_SESSION['fp_db_id'])) {
        $error = 'Session invalid. Please start again.';
        clearFPSession();
    } else {
        $new_pw     = $_POST['new_password']     ?? '';
        $confirm_pw = $_POST['confirm_password'] ?? '';

        if ($new_pw !== $confirm_pw) {
            $error = 'Passwords do not match.';
        } elseif (
            strlen($new_pw) < 8 ||
            !preg_match('/[A-Z]/', $new_pw) ||
            !preg_match('/[a-z]/', $new_pw) ||
            !preg_match('/[1-9]/', $new_pw) ||
            !preg_match('/[^a-zA-Z0-9]/', $new_pw)
        ) {
            $error = 'Password must be at least 8 characters and include uppercase, lowercase, a number (1–9), and a special character.';
        } else {
            global $pdo;
            $hashed = password_hash($new_pw, PASSWORD_BCRYPT);
            $pdo->prepare("UPDATE employees SET password = ? WHERE id = ?")
                ->execute([$hashed, $_SESSION['fp_db_id']]);

            clearFPSession();
            $success = 'PASSWORD_RESET_OK'; // trigger redirect
        }
    }
}

$step = currentStep();

// Redirect after successful reset
if ($success === 'PASSWORD_RESET_OK') {
    header("Location: ../auth/employee_login.php?reset=1");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password — AI System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #ecfdf5 0%, #f0fdf4 40%, #f1f5f9 100%);
            padding: 2rem 1rem;
        }

        .card {
            background: #fff;
            width: 100%;
            max-width: 440px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.10);
            overflow: hidden;
        }

        /* ── Progress bar at top ── */
        .progress-bar {
            height: 4px;
            background: #e2e8f0;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #10B981, #059669);
            transition: width .4s ease;
        }

        /* ── Card inner ── */
        .card-inner { padding: 2.8rem 2.5rem 2.5rem; }

        .step-badge {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            background: #f0fdf4;
            color: #059669;
            border: 1px solid #bbf7d0;
            border-radius: 20px;
            padding: .28rem .8rem;
            font-size: .75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .5px;
            margin-bottom: 1rem;
        }

        h1 { font-size: 1.55rem; font-weight: 700; color: #0f172a; margin-bottom: .4rem; }
        .subtitle { font-size: .9rem; color: #64748b; line-height: 1.6; margin-bottom: 1.8rem; }

        /* ── Alerts ── */
        .alert {
            display: flex; align-items: flex-start; gap: .6rem;
            padding: .85rem 1rem; border-radius: 10px;
            font-size: .875rem; margin-bottom: 1.4rem; line-height: 1.5;
        }
        .alert-error   { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
        .alert-success { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }
        .alert-icon { font-size: 1rem; flex-shrink: 0; margin-top: .05rem; }

        /* ── Form ── */
        .form-group { margin-bottom: 1.2rem; }
        .form-group label {
            display: block; font-size: .85rem; font-weight: 600;
            color: #374151; margin-bottom: .45rem;
        }
        .form-control {
            width: 100%; padding: .78rem 1rem;
            border: 1.5px solid #e2e8f0; border-radius: 10px;
            font-size: .95rem; font-family: 'Inter', sans-serif;
            color: #0f172a; background: #f8fafc;
            transition: border-color .2s, box-shadow .2s;
        }
        .form-control:focus {
            outline: none; border-color: #10B981;
            background: #fff; box-shadow: 0 0 0 3px rgba(16,185,129,.12);
        }
        .form-control::placeholder { color: #94a3b8; }

        /* ── OTP input ── */
        .otp-input {
            text-align: center; letter-spacing: 10px;
            font-size: 1.6rem; font-weight: 700;
            font-family: monospace; padding: .9rem 1rem;
        }

        /* ── Buttons ── */
        .btn-primary {
            width: 100%; padding: .88rem 1.5rem;
            background: #10B981; color: #fff; border: none;
            border-radius: 10px; font-size: 1rem; font-weight: 600;
            font-family: 'Inter', sans-serif; cursor: pointer;
            transition: background .2s, transform .15s, box-shadow .2s;
            margin-top: .4rem;
            box-shadow: 0 4px 14px rgba(16,185,129,.30);
        }
        .btn-primary:hover {
            background: #059669; transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(16,185,129,.40);
        }
        .btn-primary:active { transform: translateY(0); }

        /* ── Timer ── */
        .timer-row {
            display: flex; justify-content: space-between;
            align-items: center; margin-top: .6rem;
            font-size: .82rem; color: #94a3b8;
        }
        #timer { font-weight: 600; color: #10B981; }
        #timer.expired { color: #dc2626; }

        .resend-link {
            color: #10B981; text-decoration: none; font-weight: 600;
            font-size: .82rem; display: none;
        }
        .resend-link.visible { display: inline; }
        .resend-link:hover { color: #059669; }

        /* ── Bottom links ── */
        .back-link {
            display: block; text-align: center; margin-top: 1.6rem;
            font-size: .85rem; color: #94a3b8; text-decoration: none;
            font-weight: 500; transition: color .2s;
        }
        .back-link:hover { color: #10B981; }

        /* ── Password strength ── */
        .strength-bar {
            height: 4px; border-radius: 2px;
            background: #e2e8f0; margin-top: .4rem; overflow: hidden;
        }
        .strength-fill { height: 100%; width: 0; transition: width .3s, background .3s; border-radius: 2px; }
        .strength-label { font-size: .75rem; margin-top: .3rem; color: #94a3b8; }
    </style>
</head>
<body>
<div class="card">

    <!-- Progress bar (step 1=33%, 2=66%, 3=100%) -->
    <div class="progress-bar">
        <div class="progress-fill" style="width:<?= $step === 1 ? '33' : ($step === 2 ? '66' : '100') ?>%;"></div>
    </div>

    <div class="card-inner">

        <?php if ($step === 1): ?>
        <!-- ════════ STEP 1: Enter Email ════════ -->
        <div class="step-badge">🔐 Step 1 of 3</div>
        <h1>Forgot Password?</h1>
        <p class="subtitle">Enter your registered email address and we'll send you a 6-digit verification code.</p>

        <?php if ($error): ?>
            <div class="alert alert--danger"><span class="alert-icon">⚠</span><span><?= htmlspecialchars($error) ?></span></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert--success"><span class="alert-icon">✓</span><span><?= $success ?></span></div>
        <?php endif; ?>

        <?php if (!$success): ?>
        <form method="POST" id="form1">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="send_otp">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" class="form-control"
                       required placeholder="name@example.com"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>
            <button type="submit" class="btn-primary" id="btn1">Send Verification Code</button>
        </form>
        <?php else: ?>
        <!-- Email sent — show proceed button -->
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="verify_otp">
            <p style="text-align:center;margin-bottom:1rem;">
                <a href="../employee/forgot_password.php" style="color:#10B981;font-size:.85rem;">← Use a different email</a>
            </p>
            <button type="submit" style="display:none;" id="goStep2"></button>
        </form>
        <script>
            // Auto-advance to step 2 UI by reloading — session is already set
            setTimeout(() => window.location.reload(), 1500);
        </script>
        <?php endif; ?>

        <?php elseif ($step === 2): ?>
        <!-- ════════ STEP 2: Enter OTP ════════ -->
        <div class="step-badge">📧 Step 2 of 3</div>
        <h1>Check Your Email</h1>
        <p class="subtitle">
            A 6-digit code was sent to <strong><?= htmlspecialchars($_SESSION['fp_email']) ?></strong>.
            It expires in 10 minutes.
        </p>

        <?php if ($error): ?>
            <div class="alert alert--danger"><span class="alert-icon">⚠</span><span><?= htmlspecialchars($error) ?></span></div>
        <?php endif; ?>

        <form method="POST" id="form2">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="verify_otp">
            <div class="form-group">
                <label for="otp">Verification Code</label>
                <input type="text" id="otp" name="otp" class="form-control otp-input"
                       required maxlength="6" placeholder="— — — — — —"
                       autocomplete="one-time-code" inputmode="numeric"
                       pattern="\d{6}">
            </div>

            <!-- Countdown timer -->
            <div class="timer-row">
                <span>⏱ Expires in <span id="timer">10:00</span></span>
                <a href="../employee/forgot_password.php" class="resend-link" id="resendLink">Resend code</a>
            </div>

            <button type="submit" class="btn-primary" style="margin-top:1.2rem;">Verify Code</button>
        </form>

        <script>
        // ── Countdown ──────────────────────────────────────────────
        const expiry = <?= (int)$_SESSION['fp_otp_expiry'] ?> * 1000;
        const timerEl  = document.getElementById('timer');
        const resendEl = document.getElementById('resendLink');

        function updateTimer() {
            const remain = Math.max(0, Math.floor((expiry - Date.now()) / 1000));
            const m = String(Math.floor(remain / 60)).padStart(2, '0');
            const s = String(remain % 60).padStart(2, '0');
            timerEl.textContent = m + ':' + s;
            if (remain === 0) {
                timerEl.classList.add('expired');
                timerEl.textContent = 'Expired';
                resendEl.classList.add('visible');
                clearInterval(iv);
            }
        }
        const iv = setInterval(updateTimer, 1000);
        updateTimer();

        // Auto-format OTP input
        document.getElementById('otp').addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '').slice(0, 6);
        });
        </script>

        <?php elseif ($step === 3): ?>
        <!-- ════════ STEP 3: New Password ════════ -->
        <div class="step-badge">✅ Step 3 of 3</div>
        <h1>Set New Password</h1>
        <p class="subtitle">Identity verified. Choose a strong new password for your account.</p>

        <?php if ($error): ?>
            <div class="alert alert--danger"><span class="alert-icon">⚠</span><span><?= htmlspecialchars($error) ?></span></div>
        <?php endif; ?>

        <form method="POST" id="form3">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="reset_password">

            <div class="form-group">
                <label for="new_password">New Password</label>
                <input type="password" id="new_password" name="new_password"
                       class="form-control" required
                       placeholder="Min 8 chars · Upper · Lower · Number · Symbol"
                       oninput="checkStrength(this.value)">
                <div class="strength-bar"><div class="strength-fill" id="strengthFill"></div></div>
                <div class="strength-label" id="strengthLabel">Enter a password</div>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password"
                       class="form-control" required placeholder="Re-enter new password"
                       oninput="checkMatch()">
                <div id="matchMsg" style="font-size:.75rem;margin-top:.3rem;color:#94a3b8;"></div>
            </div>

            <button type="submit" class="btn-primary">Reset Password</button>
        </form>

        <script>
        function checkStrength(pw) {
            let score = 0;
            if (pw.length >= 8)               score++;
            if (/[A-Z]/.test(pw))             score++;
            if (/[a-z]/.test(pw))             score++;
            if (/[1-9]/.test(pw))             score++;
            if (/[^a-zA-Z0-9]/.test(pw))      score++;

            const fill  = document.getElementById('strengthFill');
            const label = document.getElementById('strengthLabel');
            const pct   = (score / 5) * 100;
            fill.style.width = pct + '%';

            const map = {
                0: ['#e2e8f0',''],
                1: ['#f87171','Weak'],
                2: ['#fb923c','Fair'],
                3: ['#facc15','Moderate'],
                4: ['#4ade80','Strong'],
                5: ['#10B981','Very Strong']
            };
            fill.style.background = map[score][0];
            label.textContent     = map[score][1];
            label.style.color     = map[score][0];
        }

        function checkMatch() {
            const pw = document.getElementById('new_password').value;
            const cf = document.getElementById('confirm_password').value;
            const el = document.getElementById('matchMsg');
            if (!cf) { el.textContent = ''; return; }
            if (pw === cf) {
                el.textContent = '✔ Passwords match';
                el.style.color = '#10B981';
            } else {
                el.textContent = '✖ Passwords do not match';
                el.style.color = '#dc2626';
            }
        }
        </script>
        <?php endif; ?>

        <!-- Start over / back to login -->
        <?php if ($step === 1): ?>
            <a href="../auth/employee_login.php" class="back-link">← Back to Login</a>
        <?php elseif ($step === 2): ?>
            <a href="../employee/forgot_password.php" class="back-link">← Use a different email</a>
        <?php else: ?>
            <a href="../employee/forgot_password.php" class="back-link">← Start over</a>
        <?php endif; ?>

    </div><!-- /card-inner -->
</div><!-- /card -->

<script>
// Show loading on submit buttons
document.querySelectorAll('form').forEach(f => {
    f.addEventListener('submit', function() {
        const btn = this.querySelector('button[type="submit"]');
        if (btn) { btn.disabled = true; btn.textContent = 'Please wait…'; }
    });
});
</script>
</body>
</html>
