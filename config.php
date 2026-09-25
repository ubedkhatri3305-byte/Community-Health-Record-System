<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ================= DATABASE CONFIG ================= */
// Check for unified connection string (DATABASE_URL / MYSQL_URL)
$db_url = getenv('DATABASE_URL') ?: getenv('MYSQL_URL') ?: getenv('JAWSDB_URL');

if ($db_url) {
    $dbparts = parse_url($db_url);
    $DB_HOST = $dbparts['host'] ?? 'localhost';
    $DB_USER = $dbparts['user'] ?? 'root';
    $DB_PASS = $dbparts['pass'] ?? '';
    $DB_NAME = isset($dbparts['path']) ? ltrim($dbparts['path'], '/') : 'chr_db';
    $DB_PORT = isset($dbparts['port']) ? (int)$dbparts['port'] : 3306;
} else {
    // Check individual environment variables with fallbacks to local defaults
    $DB_HOST = getenv('DB_HOST') ?: getenv('MYSQLHOST') ?: getenv('MYSQL_HOST') ?: 'localhost';
    $DB_USER = getenv('DB_USER') ?: getenv('MYSQLUSER') ?: getenv('MYSQL_USER') ?: 'root';
    $DB_PASS = getenv('DB_PASS') !== false ? getenv('DB_PASS') : (getenv('MYSQLPASSWORD') !== false ? getenv('MYSQLPASSWORD') : (getenv('MYSQL_PASSWORD') !== false ? getenv('MYSQL_PASSWORD') : ''));
    $DB_NAME = getenv('DB_NAME') ?: getenv('MYSQLDATABASE') ?: getenv('MYSQL_DATABASE') ?: 'chr_db';
    $DB_PORT = (int)(getenv('DB_PORT') ?: getenv('MYSQLPORT') ?: getenv('MYSQL_PORT') ?: 3306);
}

$DB_SSL = getenv('DB_SSL') === 'true' || getenv('MYSQL_SSL') === 'true';

$mysqli = mysqli_init();
if (!$mysqli) {
    die("mysqli_init failed");
}

$mysqli->options(MYSQLI_OPT_CONNECT_TIMEOUT, 10);

// Helper function to safely attempt real_connect without crashing on PHP 8.1+ exceptions
$attempt_connect = function($host, $user, $pass, $name, $port, $ssl) use ($mysqli) {
    try {
        if ($ssl) {
            $mysqli->ssl_set(NULL, NULL, NULL, NULL, NULL);
            return @$mysqli->real_connect($host, $user, $pass, $name, $port, NULL, MYSQLI_CLIENT_SSL);
        } else {
            return @$mysqli->real_connect($host, $user, $pass, $name, $port);
        }
    } catch (Throwable $e) {
        return false;
    }
};

$connected = $attempt_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT, $DB_SSL);

// If failed and host was localhost or 127.0.0.1, try alternative local binding
if (!$connected && ($DB_HOST === 'localhost' || $DB_HOST === '127.0.0.1')) {
    $alt_host = ($DB_HOST === 'localhost') ? '127.0.0.1' : 'localhost';
    $connected = $attempt_connect($alt_host, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT, false);
    if ($connected) {
        $DB_HOST = $alt_host;
    }
}

if (!$connected || $mysqli->connect_errno) {
    die("Database connection failed: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error . 
        "<br><small>Host: " . htmlspecialchars($DB_HOST) . " | Port: " . htmlspecialchars($DB_PORT) . " | Database: " . htmlspecialchars($DB_NAME) . "</small>");
}

$mysqli->set_charset("utf8mb4");

/* ================= AUTH HELPERS ================= */

// Ensure user is logged in
function ensure_logged_in() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: index.php");
        exit;
    }
}

// Ensure role access
function ensure_role($role) {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== $role) {
        header("Location: index.php?msg=Unauthorized");
        exit;
    }
}

// Get logged-in user
function current_user($mysqli) {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        return null;
    }

    if ($_SESSION['role'] === 'hospital' || $_SESSION['role'] === 'laboratory') {
        $stmt = $mysqli->prepare(
            "SELECT id, name, email, type AS role, phone, address, open_time, close_time, latitude, longitude
             FROM hospitals
             WHERE id=? LIMIT 1"
        );
    } else {
        $stmt = $mysqli->prepare(
            "SELECT id, name, email, role, phone, address
             FROM users
             WHERE id=? LIMIT 1"
        );
    }

    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}
