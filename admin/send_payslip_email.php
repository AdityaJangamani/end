<?php
session_start();
header('Content-Type: application/json');

// ── Auth guard ───────────────────────────────────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit();
}

// ── Only accept POST ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

require '../php/db.php';
require '../php/email_config.php';
require '../php/SimpleMailer.php';
require '../php/csrf.php';

if (!csrf_verify()) {
    echo json_encode(['success' => false, 'message' => 'CSRF verification failed.']);
    exit();
}

// ── Validate salary ID ───────────────────────────────────────────────────────
$salary_id = filter_input(INPUT_POST, 'salary_id', FILTER_VALIDATE_INT);
if (!$salary_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid payslip ID.']);
    exit();
}

// ── Fetch payslip data ───────────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT e.first_name, e.last_name, e.email, e.department, e.job_role, e.profile_picture, s.*
    FROM salary s
    JOIN employees e ON s.employee_id = e.id
    WHERE s.id = ?
");
$stmt->execute([$salary_id]);
$data = $stmt->fetch();

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Payslip record not found.']);
    exit();
}

if (empty($data['email'])) {
    echo json_encode(['success' => false, 'message' => 'Employee has no email address on record.']);
    exit();
}

// ── Build HTML email body ────────────────────────────────────────────────────
$fullName   = htmlspecialchars($data['first_name'] . ' ' . $data['last_name']);
$department = htmlspecialchars($data['department']);
$jobRole    = htmlspecialchars($data['job_role']);
$period     = htmlspecialchars($data['month'] . ' ' . $data['year']);
$base       = number_format((float)$data['base_salary'],   2);
$bonus      = number_format((float)$data['bonus'],         2);
$deductions = number_format((float)$data['deductions'],    2);
$net        = number_format((float)$data['net_salary'],    2);
$generatedOn = date('d M Y, h:i A');

// Prepare base64 profile picture
$picBase64 = '';
$picHtml = '';
if (!empty($data['profile_picture'])) {
    $absPath = dirname(__DIR__) . '/' . $data['profile_picture'];
    if (file_exists($absPath)) {
        $mime = mime_content_type($absPath);
        $dataUri = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($absPath));
        $picHtml = '
        <tr>
          <td style="text-align:center;padding:0 40px 20px;">
            <img src="' . $dataUri . '" alt="Profile Picture" style="width:80px;height:80px;border-radius:50%;object-fit:cover;border:3px solid #4F46E5;">
          </td>
        </tr>';
    }
}

$html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Payslip — {$fullName}</title>
</head>
<body style="margin:0;padding:0;background:#f0f2f8;font-family:'Segoe UI',Arial,sans-serif;">

  <!-- Wrapper -->
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f2f8;padding:40px 0;">
    <tr>
      <td align="center">

        <!-- Card -->
        <table width="620" cellpadding="0" cellspacing="0"
               style="background:#ffffff;border-radius:12px;overflow:hidden;
                      box-shadow:0 4px 24px rgba(0,0,0,0.08);">

          <!-- Header banner -->
          <tr>
            <td style="background:linear-gradient(135deg,#4F46E5 0%,#7C3AED 100%);
                        padding:36px 40px;text-align:center;">
              <h1 style="margin:0;color:#fff;font-size:26px;letter-spacing:1px;">
                AI System Inc.
              </h1>
              <p style="margin:8px 0 0;color:#c7d2fe;font-size:14px;">
                Payslip for the period of &nbsp;<strong style="color:#fff;">{$period}</strong>
              </p>
            </td>
          </tr>
          
          <!-- Spacer / Pic -->
          <tr><td style="height:20px;"></td></tr>
          {$picHtml}

          <!-- Employee info -->
          <tr>
            <td style="padding:30px 40px 0;">
              <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                  <td style="background:#f5f3ff;border-radius:8px;padding:20px 24px;">
                    <table width="100%" cellpadding="4" cellspacing="0">
                      <tr>
                        <td style="color:#6b7280;font-size:13px;width:40%;">Employee Name</td>
                        <td style="color:#111827;font-weight:600;font-size:14px;">{$fullName}</td>
                      </tr>
                      <tr>
                        <td style="color:#6b7280;font-size:13px;">Department</td>
                        <td style="color:#111827;font-size:14px;">{$department}</td>
                      </tr>
                      <tr>
                        <td style="color:#6b7280;font-size:13px;">Job Role</td>
                        <td style="color:#111827;font-size:14px;">{$jobRole}</td>
                      </tr>
                    </table>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Salary breakdown -->
          <tr>
            <td style="padding:24px 40px 0;">
              <h3 style="margin:0 0 14px;color:#4F46E5;font-size:15px;
                          text-transform:uppercase;letter-spacing:0.5px;">
                Salary Breakdown
              </h3>
              <table width="100%" cellpadding="0" cellspacing="0"
                     style="border-collapse:collapse;">

                <!-- Head row -->
                <tr style="background:#f9fafb;">
                  <th style="text-align:left;padding:10px 14px;
                              font-size:12px;color:#6b7280;font-weight:600;
                              border-bottom:2px solid #e5e7eb;">Description</th>
                  <th style="text-align:right;padding:10px 14px;
                              font-size:12px;color:#6b7280;font-weight:600;
                              border-bottom:2px solid #e5e7eb;">Amount (INR)</th>
                </tr>

                <!-- Base Salary -->
                <tr>
                  <td style="padding:12px 14px;font-size:14px;color:#374151;
                              border-bottom:1px solid #f3f4f6;">Base Salary</td>
                  <td style="padding:12px 14px;font-size:14px;color:#059669;
                              text-align:right;border-bottom:1px solid #f3f4f6;">
                    &#8377; {$base}
                  </td>
                </tr>

                <!-- Bonus -->
                <tr>
                  <td style="padding:12px 14px;font-size:14px;color:#374151;
                              border-bottom:1px solid #f3f4f6;">Bonus</td>
                  <td style="padding:12px 14px;font-size:14px;color:#059669;
                              text-align:right;border-bottom:1px solid #f3f4f6;">
                    &#8377; {$bonus}
                  </td>
                </tr>

                <!-- Deductions -->
                <tr>
                  <td style="padding:12px 14px;font-size:14px;color:#374151;
                              border-bottom:1px solid #f3f4f6;">Deductions</td>
                  <td style="padding:12px 14px;font-size:14px;color:#dc2626;
                              text-align:right;border-bottom:1px solid #f3f4f6;">
                    - &#8377; {$deductions}
                  </td>
                </tr>

                <!-- Net Salary (total row) -->
                <tr style="background:#4F46E5;">
                  <td style="padding:14px 14px;font-size:15px;font-weight:700;
                              color:#fff;border-radius:0 0 0 8px;">
                    Net Salary
                  </td>
                  <td style="padding:14px 14px;font-size:16px;font-weight:700;
                              color:#fff;text-align:right;border-radius:0 0 8px 0;">
                    &#8377; {$net}
                  </td>
                </tr>

              </table>
            </td>
          </tr>

          <!-- Footer note -->
          <tr>
            <td style="padding:24px 40px 36px;text-align:center;">
              <p style="margin:0;font-size:12px;color:#9ca3af;">
                This is a system-generated payslip. Please do not reply to this email.<br>
                Generated on {$generatedOn}.
              </p>
            </td>
          </tr>

        </table>
        <!-- /Card -->

      </td>
    </tr>
  </table>

</body>
</html>
HTML;

// ── Send email ───────────────────────────────────────────────────────────────
try {
    $mailer = new SimpleMailer(SMTP_USERNAME, SMTP_PASSWORD, SMTP_FROM_NAME);
    $mailer->send(
        $data['email'],
        $data['first_name'] . ' ' . $data['last_name'],
        'Your Payslip for ' . $data['month'] . ' ' . $data['year'] . ' — AI System',
        $html
    );

    // Log the send event in a simple table (optional — won't crash if table missing)
    try {
        $pdo->prepare("
            INSERT INTO payslip_email_log (salary_id, sent_to, sent_at)
            VALUES (?, ?, NOW())
        ")->execute([$salary_id, $data['email']]);
    } catch (PDOException $ignored) {}

    echo json_encode([
        'success' => true,
        'message' => "Payslip successfully sent to {$data['email']}"
    ]);

} catch (Exception $e) {
    error_log("Payslip email error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to send email: ' . $e->getMessage()
    ]);
}
