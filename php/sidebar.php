<div class="sidebar">
    <div class="sidebar-logo">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
            stroke-linecap="round" stroke-linejoin="round">
            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
            <circle cx="9" cy="7" r="4"></circle>
            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
        </svg>
        ADMIN
    </div>
    <ul class="nav-links">
        <li><a href="../admin/dashboard.php"
                class="<?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?>">Dashboard</a></li>
        <li><a href="../admin/add_employee.php"
                class="<?= basename($_SERVER['PHP_SELF']) == 'add_employee.php' ? 'active' : '' ?>">Add Employee</a>
        </li>
        <li><a href="../admin/bulk_import.php"
                class="<?= basename($_SERVER['PHP_SELF']) == 'bulk_import.php' ? 'active' : '' ?>">📥 Bulk Import</a>
        </li>
        <li><a href="../admin/view_employee.php"
                class="<?= basename($_SERVER['PHP_SELF']) == 'view_employee.php' ? 'active' : '' ?>">View Employees</a>
        </li>
        <li><a href="../admin/salary.php" class="<?= basename($_SERVER['PHP_SELF']) == 'salary.php' ? 'active' : '' ?>">Salary &
                Payslip</a></li>
        <li><a href="../admin/manage_attendance.php"
                class="<?= basename($_SERVER['PHP_SELF']) == 'manage_attendance.php' ? 'active' : '' ?>">Attendance</a>
        </li>
        <li><a href="../admin/manage_performance.php"
                class="<?= basename($_SERVER['PHP_SELF']) == 'manage_performance.php' ? 'active' : '' ?>">Performance</a>
        </li>
        <li><a href="../prediction/prediction.php"
                class="<?= basename($_SERVER['PHP_SELF']) == 'prediction.php' ? 'active' : '' ?>">AI Predictions</a>
        </li>
        <li><a href="../admin/password_requests.php"
                class="<?= basename($_SERVER['PHP_SELF']) == 'password_requests.php' ? 'active' : '' ?>">Password Requests</a>
        </li>
        <li><a href="../auth/logout.php" style="color: var(--danger); margin-top: 2rem;">Logout</a></li>
    </ul>
</div>