<?php
session_name('emp_sess');
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['employee_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['profile_pic'])) {
    echo json_encode(['success' => false, 'message' => 'No file received.']);
    exit();
}

require '../php/db.php';
require '../php/csrf.php';

if (!csrf_verify()) {
    echo json_encode(['success' => false, 'message' => 'CSRF verification failed.']);
    exit();
}

$emp_id = $_SESSION['employee_id'];
$file   = $_FILES['profile_pic'];

// ── Validate ─────────────────────────────────────────────────────────────────
$allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$max_size      = 3 * 1024 * 1024; // 3 MB

if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Upload error. Please try again.']);
    exit();
}

// Use finfo for true MIME type detection (not just extension)
$finfo     = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mime_type, $allowed_types)) {
    echo json_encode(['success' => false, 'message' => 'Only JPG, PNG, GIF, or WebP images allowed.']);
    exit();
}

if ($file['size'] > $max_size) {
    echo json_encode(['success' => false, 'message' => 'Image must be under 3 MB.']);
    exit();
}

// ── Create upload directory ───────────────────────────────────────────────────
$upload_dir = __DIR__ . '/../uploads/profile_pics/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// ── Delete old picture if it exists ──────────────────────────────────────────
$stmt = $pdo->prepare("SELECT profile_picture FROM employees WHERE id = ?");
$stmt->execute([$emp_id]);
$old = $stmt->fetchColumn();
if ($old && file_exists(__DIR__ . '/../' . $old)) {
    @unlink(__DIR__ . '/../' . $old);
}

// ── Save new file with unique name ────────────────────────────────────────────
$ext      = match($mime_type) {
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp',
    default      => 'jpg'
};
$filename = 'emp_' . $emp_id . '_' . time() . '.' . $ext;
$dest     = $upload_dir . $filename;

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save file on server.']);
    exit();
}

// ── Ensure column exists (auto-migrate) ──────────────────────────────────────
try {
    $pdo->exec("ALTER TABLE employees ADD COLUMN profile_picture VARCHAR(255) NULL DEFAULT NULL");
} catch (PDOException $ignored) {}

// ── Save relative path to DB ──────────────────────────────────────────────────
$relative_path = 'uploads/profile_pics/' . $filename;
$pdo->prepare("UPDATE employees SET profile_picture = ? WHERE id = ?")
    ->execute([$relative_path, $emp_id]);

echo json_encode([
    'success'  => true,
    'message'  => 'Profile picture updated successfully!',
    'img_path' => $relative_path . '?v=' . time()
]);
