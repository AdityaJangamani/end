<?php
session_name('emp_sess');
session_start();
session_destroy();
header("Location: employee_login.php"); // same auth/ directory
exit();
