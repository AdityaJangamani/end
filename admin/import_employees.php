<?php
session_start();
header('Content-Type: application/json');

// ── Auth guard ───────────────────────────────────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid method.']);
    exit();
}

require '../php/db.php';
require '../php/config.php';
require '../php/csrf.php';

if (!csrf_verify()) {
    echo json_encode(['success' => false, 'message' => 'CSRF verification failed.']);
    exit();
}

// ── Salary defaults by department ────────────────────────────────────────────
// ── Salary defaults by department (from php/config.php) ──────────────────────

$valid_depts = array_keys($DEPT_SALARY_DEFAULTS);

// ── Read & sanitize fields ───────────────────────────────────────────────────
$employee_id = trim($_POST['employee_id'] ?? '');
$first_name  = trim($_POST['first_name']  ?? '');
$last_name   = trim($_POST['last_name']   ?? '');
$email       = trim($_POST['email']       ?? '');
$department  = trim($_POST['department']  ?? '');
$job_role    = trim($_POST['job_role']    ?? 'Staff');
$date_joined = trim($_POST['date_joined'] ?? '');
$age_raw     = trim($_POST['age']         ?? '');
$plain_pw    = trim($_POST['password']    ?? '');
$age         = ($age_raw !== '' && is_numeric($age_raw)) ? (int)$age_raw : null;

// ── Server-side validation ───────────────────────────────────────────────────
if (!$employee_id) {
    echo json_encode(['success' => false, 'message' => 'Missing employee_id.']);
    exit();
}
if (!$first_name || !preg_match('/^[a-zA-Z\s]+$/', $first_name)) {
    echo json_encode(['success' => false, 'message' => 'Invalid first_name.']);
    exit();
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
    exit();
}
if (!in_array($department, $valid_depts)) {
    echo json_encode(['success' => false, 'message' => "Unknown department: $department."]);
    exit();
}
if (!$date_joined) {
    echo json_encode(['success' => false, 'message' => 'Missing date_joined.']);
    exit();
}

// ── Auto-generate password if blank ─────────────────────────────────────────
if (!$plain_pw) {
    $plain_pw = ucfirst(strtolower($first_name)) . '@123';
}
$hashed_pw = password_hash($plain_pw, PASSWORD_BCRYPT);

// ── Calculate years_at_company ───────────────────────────────────────────────
try {
    $joined_dt = new DateTime($date_joined);
    $now_dt    = new DateTime();
    $diff      = $joined_dt->diff($now_dt);
    $years_at_company = round($diff->y + ($diff->m / 12), 2);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Invalid date_joined format (use YYYY-MM-DD).']);
    exit();
}

// ── Salary calc ──────────────────────────────────────────────────────────────
$annual_salary = $DEPT_SALARY_DEFAULTS[$department] ?? 50000;
$monthly_base  = round($annual_salary / 12, 2);

// ── Insert into DB ───────────────────────────────────────────────────────────
try {
    // Check for duplicate employee_id or email
    $check = $pdo->prepare("SELECT id FROM employees WHERE employee_id = ? OR email = ?");
    $check->execute([$employee_id, $email]);
    if ($check->fetch()) {
        echo json_encode(['success' => false, 'message' => "Duplicate: employee_id '$employee_id' or email '$email' already exists."]);
        exit();
    }

    // Insert employee
    $stmt = $pdo->prepare("
        INSERT INTO employees
            (employee_id, first_name, last_name, email, password,
             department, job_role, age, date_joined,
             years_at_company, job_satisfaction, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 3, 'Active')
    ");
    $stmt->execute([
        $employee_id,
        $first_name,
        $last_name,
        $email,
        $hashed_pw,
        $department,
        $job_role,
        $age,
        $date_joined,
        $years_at_company,
    ]);
    $emp_id = $pdo->lastInsertId();

    // Insert default performance record
    $pdo->prepare("
        INSERT INTO performance
            (employee_id, productivity_score, manager_rating, projects_completed, evaluation_date, hours_worked_per_week)
        VALUES (?, 75.00, 3.00, 5, CURDATE(), 40)
    ")->execute([$emp_id]);

    // Insert default salary record
    $pdo->prepare("
        INSERT INTO salary (employee_id, base_salary, bonus, deductions, net_salary, month, year)
        VALUES (?, ?, 0, 0, ?, ?, ?)
    ")->execute([$emp_id, $monthly_base, $monthly_base, date('F'), date('Y')]);

    echo json_encode([
        'success' => true,
        'message' => "$first_name $last_name imported successfully."
    ]);

} catch (PDOException $e) {
    error_log("import_employees.php error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'DB error: ' . $e->getMessage()]);
}
