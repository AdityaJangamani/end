<?php
session_name('emp_sess');
session_start();
require '../php/db.php';
require '../php/csrf.php';
$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!csrf_verify()) {
        $error = "Invalid form submission. Please try again.";
    } else {
    $login_credential = trim($_POST['login_credential']);
    $password         = $_POST['password'];

    // Allow employee login with either email or employee ID (status doesn't block login — it's just shift status)
    $stmt = $pdo->prepare("SELECT * FROM employees WHERE (email = ? OR employee_id = ?)");
    $stmt->execute([$login_credential, $login_credential]);
    $employee = $stmt->fetch();

    if ($employee) {
        if (password_verify($password, $employee['password'])) {
            $_SESSION['employee_id']   = $employee['id'];
            $_SESSION['employee_name'] = $employee['first_name'] . ' ' . $employee['last_name'];
            $_SESSION['role']          = 'employee';
            header("Location: ../employee/employee_dashboard.php");
            exit();
        }
    }
    $error = "Invalid email or password!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Login — AI System</title>
    <meta name="description" content="Secure employee login for the AI Analytics System.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            background: #F1F5F9;
            color: #1E293B;
        }

        /* === Left Panel === */
        .login-left {
            flex: 1;
            background: linear-gradient(145deg, #10B981 0%, #059669 60%, #047857 100%); /* Green theme for employees */
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: flex-start;
            padding: 4rem;
            position: relative;
            overflow: hidden;
        }

        .login-left::before {
            content: '';
            position: absolute;
            width: 500px;
            height: 500px;
            background: rgba(255,255,255,0.06);
            border-radius: 50%;
            top: -100px;
            right: -150px;
        }

        .login-left::after {
            content: '';
            position: absolute;
            width: 300px;
            height: 300px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
            bottom: -80px;
            left: 30px;
        }

        .brand-icon {
            width: 56px;
            height: 56px;
            background: rgba(255,255,255,0.15);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 2rem;
            font-size: 1.6rem;
        }

        .brand-title {
            font-size: 2rem;
            font-weight: 700;
            color: #fff;
            line-height: 1.2;
            margin-bottom: 1rem;
            position: relative;
            z-index: 1;
        }

        .brand-subtitle {
            font-size: 1rem;
            color: rgba(255,255,255,0.75);
            max-width: 340px;
            line-height: 1.7;
            position: relative;
            z-index: 1;
        }

        .features-list {
            list-style: none;
            margin-top: 2.5rem;
            display: flex;
            flex-direction: column;
            gap: 0.85rem;
            position: relative;
            z-index: 1;
        }

        .features-list li {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            color: rgba(255,255,255,0.85);
            font-size: 0.9rem;
        }

        .features-list li span.icon {
            width: 22px;
            height: 22px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.65rem;
            font-weight: 700;
            color: #fff;
            flex-shrink: 0;
        }

        /* === Right Panel === */
        .login-right {
            width: 480px;
            background: #fff;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 3rem 3.5rem;
            box-shadow: -8px 0 30px rgba(0,0,0,0.06);
        }

        .login-form-wrapper {
            width: 100%;
            max-width: 360px;
        }

        .login-form-wrapper h1 {
            font-size: 1.75rem;
            font-weight: 700;
            color: #0F172A;
            margin-bottom: 0.4rem;
        }

        .login-form-wrapper p.subtitle {
            font-size: 0.9rem;
            color: #64748B;
            margin-bottom: 2rem;
        }

        .error-msg {
            background: #FEF2F2;
            border: 1px solid #FECACA;
            color: #DC2626;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            font-size: 0.875rem;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-group label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.45rem;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1.5px solid #E2E8F0;
            border-radius: 8px;
            font-size: 0.95rem;
            font-family: 'Inter', sans-serif;
            color: #0F172A;
            background: #F8FAFC;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .form-control::placeholder { color: #94A3B8; }

        .form-control:focus {
            outline: none;
            border-color: #10B981;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.12);
        }

        .btn-login {
            width: 100%;
            padding: 0.85rem 1.5rem;
            background: #10B981;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            transition: background 0.2s, transform 0.15s, box-shadow 0.2s;
            margin-top: 0.5rem;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .btn-login:hover {
            background: #059669;
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(16, 185, 129, 0.4);
        }

        .btn-login:active { transform: translateY(0); }

        .divider {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin: 1.5rem 0;
        }

        .divider hr {
            flex: 1;
            border: none;
            border-top: 1px solid #E2E8F0;
        }

        .divider span {
            font-size: 0.8rem;
            color: #94A3B8;
            white-space: nowrap;
        }

        .admin-link {
            display: block;
            text-align: center;
            padding: 0.75rem 1rem;
            border: 1.5px solid #E2E8F0;
            border-radius: 8px;
            color: #374151;
            font-size: 0.9rem;
            font-weight: 500;
            text-decoration: none;
            transition: border-color 0.2s, background 0.2s;
        }

        .admin-link:hover {
            border-color: #3B82F6;
            background: #EFF6FF;
            color: #1E40AF;
        }

        .footer-note {
            margin-top: 2rem;
            text-align: center;
            font-size: 0.78rem;
            color: #CBD5E1;
        }

        @media (max-width: 768px) {
            .login-left { display: none; }
            .login-right { width: 100%; padding: 2.5rem 1.5rem; }
        }
    </style>
</head>
<body>
    <!-- Left Branding Panel -->
    <div class="login-left">
        <div class="brand-icon">&#128100;</div>
        <h2 class="brand-title">Employee Staff<br>Portal</h2>
        <p class="brand-subtitle">Access your salary details, payslips, performance insights, and predictions securely.</p>
        <ul class="features-list">
            <li><span class="icon">&#10003;</span> Access Salary &amp; Digital Payslips</li>
            <li><span class="icon">&#10003;</span> View Important Announcements</li>
            <li><span class="icon">&#10003;</span> Track Your Productivity Metrics</li>
            <li><span class="icon">&#10003;</span> Update Personal Information</li>
        </ul>
    </div>

    <!-- Right Login Panel -->
    <div class="login-right">
        <div class="login-form-wrapper">
            <h1>Employee Sign In</h1>
            <p class="subtitle">Enter your email and password to access your dashboard.</p>

            <?php if (isset($_GET['reset']) && $_GET['reset'] === '1'): ?>
                <div class="alert alert--success" style="margin-bottom:1.25rem; display:flex; align-items:center; gap:.5rem;">
                    &#10003; Password reset successfully! You can now log in with your new password.
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="error-msg">
                    &#9888; <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="login_credential">Email Address or Employee ID</label>
                    <input type="text" id="login_credential" name="login_credential" class="form-control" required placeholder="e.g. emp01 or name@gmail.com" autocomplete="username">
                </div>
                <div class="form-group">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                        <label for="password" style="margin-bottom: 0;">Password</label>
                        <a href="../employee/forgot_password.php" style="font-size: 0.85rem; color: #10B981; text-decoration: none; font-weight: 500;">Forgot Password?</a>
                    </div>
                    <input type="password" id="password" name="password" class="form-control" required placeholder="Enter your password" autocomplete="current-password">
                </div>
                <button type="submit" class="btn-login">Sign In</button>
            </form>

            <div class="divider">
                <hr><span>Are you an administrator?</span><hr>
            </div>

            <a href="../index.php" class="admin-link">&#128274; Admin Login &rarr;</a>

            <p class="footer-note">AI System &copy; <?= date('Y') ?> &mdash; Team Member Portal</p>
        </div>
    </div>
</body>
</html>
