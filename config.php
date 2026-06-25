<?php
// Start secure session
if (session_status() === PHP_SESSION_NONE) {
    // Session cookie settings
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Strict');

    // Check if HTTPS is used
    $is_secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['SERVER_PORT'] ?? 80) == 443;
    if ($is_secure) {
        ini_set('session.cookie_secure', 1);
    }

    session_start();
}

// Database Credentials
define('DB_HOST', 'localhost');
define('DB_USER', 'avinanda_filemanager');
define('DB_PASS', 'avinandan_filemanager');
define('DB_NAME', 'avinanda_filemanager');

// Upload directory settings
define('UPLOAD_DIR', __DIR__ . '/secure_data/uploads/');

// Establish PDO connection & initialize database
try {
    // First connect to MySQL without selecting a database
    $pdo = new PDO("mysql:host=" . DB_HOST . ";charset=utf8mb4", DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);

    // Create DB if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    // Connect to the specific database
    $pdo->exec("USE `" . DB_NAME . "`");

    // Create Tables
    // 1. Admins Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `admins` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `username` VARCHAR(50) UNIQUE NOT NULL,
        `password_hash` VARCHAR(255) NOT NULL,
        `full_name` VARCHAR(100) NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;");

    // 2. Customers Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `customers` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `customer_name` VARCHAR(150) NOT NULL,
        `customer_email` VARCHAR(100) DEFAULT NULL,
        `customer_phone` VARCHAR(20) DEFAULT NULL,
        `sex` VARCHAR(10) DEFAULT NULL,
        `dob` DATE DEFAULT NULL,
        `profile_photo` VARCHAR(255) DEFAULT NULL,
        `address` TEXT DEFAULT NULL,
        `notes` TEXT DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;");

    // Dynamic database auto-migration check for existing tables
    $columns_to_add = [
        'sex' => "VARCHAR(10) DEFAULT NULL",
        'dob' => "DATE DEFAULT NULL",
        'profile_photo' => "VARCHAR(255) DEFAULT NULL",
        'address' => "TEXT DEFAULT NULL",
        'avatar_encrypted' => "TINYINT DEFAULT 0"
    ];
    foreach ($columns_to_add as $column_name => $column_definition) {
        $check_col = $pdo->query("SHOW COLUMNS FROM `customers` LIKE '$column_name'")->fetch();
        if (!$check_col) {
            $pdo->exec("ALTER TABLE `customers` ADD COLUMN `$column_name` $column_definition");
        }
    }

    // 3. Files Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `files` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `customer_id` INT NOT NULL,
        `original_name` VARCHAR(255) NOT NULL,
        `stored_name` VARCHAR(255) NOT NULL,
        `file_type` VARCHAR(50) NOT NULL,
        `mime_type` VARCHAR(100) NOT NULL,
        `file_size` INT NOT NULL,
        `notes` VARCHAR(255) DEFAULT NULL,
        `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB;");

    // Dynamic database auto-migration check for files table encryption column
    $check_enc = $pdo->query("SHOW COLUMNS FROM `files` LIKE 'is_encrypted'")->fetch();
    if (!$check_enc) {
        $pdo->exec("ALTER TABLE `files` ADD COLUMN `is_encrypted` TINYINT DEFAULT 0");
    }

    // 4. Audit Logs Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `audit_logs` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `admin_username` VARCHAR(50) NOT NULL,
        `action` VARCHAR(100) NOT NULL,
        `details` TEXT,
        `ip_address` VARCHAR(45) NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;");

    // Seed default admin if no administrators exist
    $stmt = $pdo->query("SELECT COUNT(*) FROM `admins`");
    if ($stmt->fetchColumn() == 0) {
        $default_username = 'admin';
        $default_password = 'admin123'; // Recommend to change after login
        $default_hash = password_hash($default_password, PASSWORD_BCRYPT);
        $default_name = 'Administrator';

        $insert = $pdo->prepare("INSERT INTO `admins` (username, password_hash, full_name) VALUES (?, ?, ?)");
        $insert->execute([$default_username, $default_hash, $default_name]);
    }

} catch (PDOException $e) {
    die("Database connection/initialization failed: " . $e->getMessage());
}

// Ensure Upload Directory Exists
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

// SECURITY FUNCTIONS

// Log admin action to security audit logs
function log_action($action, $details = null)
{
    global $pdo;
    $admin_username = $_SESSION['admin_username'] ?? 'System / Anonymous';
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    try {
        $stmt = $pdo->prepare("INSERT INTO `audit_logs` (admin_username, action, details, ip_address) VALUES (?, ?, ?, ?)");
        $stmt->execute([$admin_username, $action, $details, $ip_address]);
    } catch (PDOException $e) {
        // Fail silently to avoid breaking execution if logs table fails
    }
}

// Encrypt binary data using AES-256-CBC
function encrypt_file_content($rawData)
{
    $key = hash('sha256', DB_PASS, true); // derive a 256-bit key from HMAC secret
    $iv = openssl_random_pseudo_bytes(16);
    $ciphertext = openssl_encrypt($rawData, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
    return $iv . $ciphertext; // Prepend 16-byte IV to the ciphertext
}

// Decrypt binary data using AES-256-CBC
function decrypt_file_content($encryptedData)
{
    if (strlen($encryptedData) < 16) {
        return $encryptedData; // Not enough data to extract IV
    }
    $key = hash('sha256', DB_PASS, true);
    $iv = substr($encryptedData, 0, 16);
    $ciphertext = substr($encryptedData, 16);
    return openssl_decrypt($ciphertext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
}

// Generate CSRF Token
function get_csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Verify CSRF Token
function verify_csrf_token($token)
{
    if (!isset($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// Regenerate CSRF Token (e.g. after login/action)
function regenerate_csrf_token()
{
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check if user is logged in
function is_logged_in()
{
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_username']);
}

// Enforce login redirect
function require_login()
{
    if (!is_logged_in()) {
        header("Location: login.php");
        exit;
    }
}

// Return JSON Response and exit
function json_response($success, $message, $data = [])
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge([
        'success' => (bool) $success,
        'message' => $message
    ], $data));
    exit;
}

// Sanitize output strings
function h($string)
{
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

// Format bytes into readable format
function format_size($bytes)
{
    if ($bytes <= 0)
        return '0 B';
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = floor(log($bytes, 1024));
    return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
}
