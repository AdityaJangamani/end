<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/admin_login.php");
    exit();
}

// Serve the CSV template as a download
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="employees_import_template.csv"');
header('Cache-Control: no-cache, no-store, must-revalidate');

// BOM for Excel UTF-8 compatibility
echo "\xEF\xBB\xBF";

$output = fopen('php://output', 'w');

// Header row
fputcsv($output, [
    'employee_id',
    'first_name',
    'last_name',
    'email',
    'department',
    'job_role',
    'age',
    'date_joined',
    'password'
]);

// Example rows
fputcsv($output, ['EMP001', 'Aanya',  'Sharma',  'aanya.sharma@example.com',  'Engineering', 'Software Engineer',  28, '2023-01-15', 'Aanya@123']);
fputcsv($output, ['EMP002', 'Rohan',  'Mehta',   'rohan.mehta@example.com',   'Finance',     'Financial Analyst',  34, '2022-06-01', 'Rohan@123']);
fputcsv($output, ['EMP003', 'Priya',  'Nair',    'priya.nair@example.com',    'HR',          'HR Executive',       29, '2021-09-10', '']);
fputcsv($output, ['EMP004', 'Vikram', 'Patel',   'vikram.patel@example.com',  'Sales',       'Sales Manager',      40, '2020-03-22', '']);
fputcsv($output, ['EMP005', 'Sneha',  'Iyer',    'sneha.iyer@example.com',    'Marketing',   'Marketing Lead',     31, '2023-07-05', 'Sneha@123']);

fclose($output);
exit();
