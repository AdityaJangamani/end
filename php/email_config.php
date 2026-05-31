<?php
// ─────────────────────────────────────────────
//  Gmail SMTP Configuration
//  Reads credentials from .env file in the project root.
//  If .env is missing, falls back to defaults (will fail).
// ─────────────────────────────────────────────

// Load .env file
$env_path = __DIR__ . '/../.env';
if (file_exists($env_path)) {
    $lines = file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') === false) continue;
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if (!array_key_exists($key, $_ENV)) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

define('SMTP_HOST',      getenv('SMTP_HOST')      ?: 'smtp.gmail.com');
define('SMTP_PORT',      (int)(getenv('SMTP_PORT') ?: 587));
define('SMTP_SECURE',    getenv('SMTP_SECURE')     ?: 'tls');
define('SMTP_USERNAME',  getenv('SMTP_USERNAME')    ?: '');
define('SMTP_PASSWORD',  getenv('SMTP_PASSWORD')    ?: '');
define('SMTP_FROM_NAME', getenv('SMTP_FROM_NAME')   ?: 'AI System');
