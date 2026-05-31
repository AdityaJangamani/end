<?php
session_start();
require '../php/db.php';
require '../php/csrf.php';
$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!csrf_verify()) {
        $error = "Invalid form submission. Please try again.";
    } else {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role']    = $user['role'];
        header("Location: ../admin/dashboard.php");
        exit();
    } else {
        $error = "Invalid username or password!";
    }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login — AI System</title>
    <meta name="description" content="Secure admin login for the AI Employee Analytics System.">
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
            background: linear-gradient(145deg, #1E40AF 0%, #3B82F6 60%, #60A5FA 100%);
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
            border-color: #3B82F6;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12);
        }

        .btn-login {
            width: 100%;
            padding: 0.85rem 1.5rem;
            background: #1E40AF;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            transition: background 0.2s, transform 0.15s, box-shadow 0.2s;
            margin-top: 0.5rem;
            box-shadow: 0 4px 12px rgba(30, 64, 175, 0.3);
        }

        .btn-login:hover {
            background: #1D4ED8;
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(30, 64, 175, 0.4);
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

        .employee-link {
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

        .employee-link:hover {
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
        <div class="brand-icon">&#128101;</div>
        <h2 class="brand-title">AI Analytics<br>System</h2>
        <p class="brand-subtitle">A smart, AI-powered platform for managing employee remuneration, attrition, and performance predictions.</p>
        <ul class="features-list">
            <li><span class="icon">&#10003;</span> Salary &amp; Payslip Management</li>
            <li><span class="icon">&#10003;</span> AI Attrition Prediction</li>
            <li><span class="icon">&#10003;</span> Promotion &amp; Productivity Analytics</li>
            <li><span class="icon">&#10003;</span> Real-time Employee Dashboard</li>
        </ul>
    </div>

    <!-- Right Login Panel -->
    <div class="login-right">
        <div class="login-form-wrapper">
            <h1>Admin Sign In</h1>
            <p class="subtitle">Enter your credentials to access the admin dashboard.</p>

            <?php if ($error): ?>
                <div class="error-msg">
                    &#9888; <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="loginForm">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" class="form-control" required placeholder="e.g. admin" autocomplete="username">
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control" required placeholder="Enter your password" autocomplete="current-password">
                </div>
                <button type="submit" class="btn-login">Sign In to Dashboard</button>
            </form>

            <div class="divider">
                <hr><span>Are you an employee?</span><hr>
            </div>

            <a href="employee_login.php" class="employee-link">&#128100; Employee Login &rarr;</a>

            <p class="footer-note">AI System &copy; <?= date('Y') ?> &mdash; Powered by AI Analytics</p>
        </div>
    </div>
</body>
</html>
