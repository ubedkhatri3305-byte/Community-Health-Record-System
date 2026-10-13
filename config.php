<?php
// Ensure output buffering so cookies, headers, and redirects succeed cleanly
if (ob_get_level() === 0) {
    ob_start();
}

/* ================= SERVERLESS SESSION HANDLER ================= */
// On Vercel / Serverless, local filesystem sessions in /tmp are ephemeral and lost across invocations.
// We implement an HMAC-SHA256 signed stateless session handler when running in serverless environments.
$is_serverless = getenv('VERCEL') || getenv('AWS_LAMBDA_FUNCTION_NAME') || getenv('SERVERLESS') || !@is_writable(session_save_path());

if (!class_exists('ServerlessCookieSessionHandler')) {
    class ServerlessCookieSessionHandler implements SessionHandlerInterface {
        private string $secret;
        private string $cookieName;

        public function __construct(string $secret = 'chr_secure_vault_sign_key_9831', string $cookieName = 'chr_sess') {
            $this->secret = $secret;
            $this->cookieName = $cookieName;
        }

        public function open(string $path, string $name): bool {
            return true;
        }

        public function close(): bool {
            return true;
        }

        public function read(string $id): string|false {
            if (!isset($_COOKIE[$this->cookieName])) {
                return '';
            }
            $raw = $_COOKIE[$this->cookieName];
            $parts = explode('.', $raw, 2);
            if (count($parts) !== 2) {
                return '';
            }
            [$dataB64, $signature] = $parts;
            $expected = hash_hmac('sha256', $dataB64, $this->secret);
            if (!hash_equals($expected, $signature)) {
                return '';
            }
            $decoded = base64_decode($dataB64);
            return $decoded !== false ? $decoded : '';
        }

        public function write(string $id, string $data): bool {
            if (headers_sent()) {
                return true;
            }
            $dataB64 = base64_encode($data);
            $signature = hash_hmac('sha256', $dataB64, $this->secret);
            $val = $dataB64 . '.' . $signature;
            $params = session_get_cookie_params();
            @setcookie(
                $this->cookieName,
                $val,
                [
                    'expires'  => time() + ($params['lifetime'] > 0 ? $params['lifetime'] : 604800),
                    'path'     => '/',
                    'domain'   => '',
                    'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]
            );
            return true;
        }

        public function destroy(string $id): bool {
            if (!headers_sent()) {
                @setcookie($this->cookieName, '', time() - 3600, '/');
            }
            unset($_COOKIE[$this->cookieName]);
            return true;
        }

        public function gc(int $max_lifetime): int|false {
            return 1;
        }
    }
}

if (session_status() === PHP_SESSION_NONE) {
    if ($is_serverless) {
        $secret = getenv('SESSION_SECRET') ?: 'chr_secure_vault_sign_key_9831';
        session_set_save_handler(new ServerlessCookieSessionHandler($secret), true);
    }
    session_start();
}

/* ================= DATABASE CONFIG ================= */
// Check for unified connection string (DATABASE_URL / MYSQL_URL / JAWSDB_URL)
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

$conn_error = '';

// Helper function to safely attempt real_connect without crashing on PHP 8.1+ exceptions
$attempt_connect = function($host, $user, $pass, $name, $port, $ssl) use ($mysqli, &$conn_error) {
    try {
        if ($ssl) {
            $mysqli->ssl_set(NULL, NULL, NULL, NULL, NULL);
            $res = @$mysqli->real_connect($host, $user, $pass, $name, $port, NULL, MYSQLI_CLIENT_SSL);
        } else {
            $res = @$mysqli->real_connect($host, $user, $pass, $name, $port);
        }
        if (!$res) {
            $conn_error = $mysqli->connect_error ?: "Unknown connection failure (errno " . $mysqli->connect_errno . ")";
        }
        return $res;
    } catch (Throwable $e) {
        $conn_error = $e->getMessage();
        return false;
    }
};

$connected = $attempt_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT, $DB_SSL);

// If failed due to database not existing yet (e.g. fresh TiDB cluster), try connecting to server root and creating database
if (!$connected && $DB_HOST !== 'localhost' && $DB_HOST !== '127.0.0.1') {
    if ($attempt_connect($DB_HOST, $DB_USER, $DB_PASS, null, $DB_PORT, $DB_SSL)) {
        @$mysqli->query("CREATE DATABASE IF NOT EXISTS `" . $mysqli->real_escape_string($DB_NAME) . "` DEFAULT CHARACTER SET utf8mb4");
        if (@$mysqli->select_db($DB_NAME)) {
            $connected = true;
            $conn_error = '';
        }
    }
}

// If failed and host was localhost or 127.0.0.1, try alternative local binding
if (!$connected && ($DB_HOST === 'localhost' || $DB_HOST === '127.0.0.1')) {
    $alt_host = ($DB_HOST === 'localhost') ? '127.0.0.1' : 'localhost';
    $connected = $attempt_connect($alt_host, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT, false);
    if ($connected) {
        $DB_HOST = $alt_host;
    }
}

if (!$connected || $mysqli->connect_errno) {
    $has_custom_host = ($DB_HOST !== 'localhost' && $DB_HOST !== '127.0.0.1');
    $has_pass = !empty($DB_PASS);
    
    // Don't emit 500 on health check requests so initial cloud deployments can stabilize
    $is_health = (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], 'health') !== false) ||
                 (isset($_SERVER['HTTP_USER_AGENT']) && stripos($_SERVER['HTTP_USER_AGENT'], 'health') !== false);

    http_response_code($is_health ? 200 : 503);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>CHR - Database Status & Setup</title>
        <link rel="stylesheet" href="assets/css/styles.css">
    </head>
    <body class="center" style="min-height:100vh;background:linear-gradient(135deg, #0f172a 0%, #1e293b 100%);color:#f8fafc;padding:20px 0;">
        <div class="card" style="max-width:620px;width:92%;margin:auto;padding:32px;background:#ffffff;color:#1e293b;border-radius:18px;box-shadow:0 20px 40px rgba(0,0,0,0.3);">
            <div class="logo" style="background:#eff6ff;color:#2563eb;font-size:32px;">🏥</div>
            <h2 style="color:#1e3a8a;margin-bottom:6px;">Database Connection Status</h2>
            
            <?php if (!$has_custom_host): ?>
            <div style="background:#fffbeb;border:1.5px solid #fde68a;border-radius:12px;padding:16px;margin:16px 0;color:#92400e;">
                <strong>⚠️ Local Database Initializing or Connecting...</strong><br>
                <p style="font-size:13px;margin:6px 0 0 0;line-height:1.5;">
                    The database server is starting up or external credentials have not been configured. If you are hosting on Render or Vercel, please check your Environment Variables.
                </p>
            </div>
            <?php else: ?>
            <div style="background:#fef2f2;border:1.5px solid #fecaca;border-radius:12px;padding:16px;margin:16px 0;color:#991b1b;">
                <strong>❌ Database Connection Error:</strong><br>
                <code style="display:block;margin-top:6px;font-size:13px;background:rgba(255,255,255,0.7);padding:8px;border-radius:6px;word-break:break-all;">
                    <?= htmlspecialchars($conn_error ?: ($mysqli->connect_error ?? 'Connection timed out or access denied')) ?>
                </code>
            </div>
            <?php endif; ?>

            <p style="color:#64748b;font-size:13px;margin-bottom:12px;font-weight:600;">Current Environment Configuration:</p>
            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:14px;font-family:monospace;font-size:13px;line-height:1.9;margin-bottom:20px;">
                <div><strong>DB_HOST</strong>: <?= htmlspecialchars($DB_HOST) ?> <?= $has_custom_host ? '✅' : 'ℹ️ (Default: local)' ?></div>
                <div><strong>DB_USER</strong>: <?= htmlspecialchars($DB_USER) ?></div>
                <div><strong>DB_PASS</strong>: <?= $has_pass ? '•••••••• ✅' : '❌ (Empty / None)' ?></div>
                <div><strong>DB_NAME</strong>: <?= htmlspecialchars($DB_NAME) ?></div>
                <div><strong>DB_PORT</strong>: <?= htmlspecialchars($DB_PORT) ?></div>
                <div><strong>DB_SSL</strong>: <?= $DB_SSL ? 'true ✅' : 'false' ?></div>
            </div>

            <button class="btn" onclick="location.reload()">🔄 Refresh / Retry</button>
        </div>
    </body>
    </html>
    <?php
    exit;
}

function initialize_chr_tables($mysqli) {
    $tables = [
        "CREATE TABLE IF NOT EXISTS `users` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `name` varchar(100) DEFAULT NULL,
          `email` varchar(100) DEFAULT NULL,
          `password` varchar(255) DEFAULT NULL,
          `role` enum('patient','doctor','admin','laboratory') DEFAULT 'patient',
          `phone` varchar(20) DEFAULT NULL,
          `address` text DEFAULT NULL,
          `location` varchar(100) DEFAULT NULL,
          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
          `hospital` varchar(255) DEFAULT NULL,
          `hospital_id` int(11) DEFAULT NULL,
          `specialization` varchar(100) DEFAULT NULL,
          `qualification` varchar(100) DEFAULT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `email` (`email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS `hospitals` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `name` varchar(255) NOT NULL,
          `address` text DEFAULT NULL,
          `phone` varchar(20) DEFAULT NULL,
          `open_time` time NOT NULL DEFAULT '09:00:00',
          `close_time` time NOT NULL DEFAULT '18:00:00',
          `latitude` decimal(10,8) DEFAULT NULL,
          `longitude` decimal(11,8) DEFAULT NULL,
          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
          `type` enum('hospital','laboratory') NOT NULL DEFAULT 'hospital',
          `email` varchar(255) NOT NULL,
          `password` varchar(255) NOT NULL,
          `city` varchar(100) DEFAULT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS `appointments` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `patient_id` int(11) DEFAULT NULL,
          `doctor_id` int(11) DEFAULT NULL,
          `appt_date` date DEFAULT NULL,
          `appt_time` time DEFAULT NULL,
          `reason` text DEFAULT NULL,
          `status` enum('pending','accepted','rejected','rescheduled') DEFAULT 'pending',
          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
          `reschedule_note` text DEFAULT NULL,
          `rescheduled_date` date DEFAULT NULL,
          `rescheduled_time` time DEFAULT NULL,
          `reschedule_msg` text DEFAULT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS `doctor_leaves` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `doctor_id` int(11) NOT NULL,
          `leave_date` date NOT NULL,
          `reason` varchar(255) DEFAULT NULL,
          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS `doctor_profiles` (
          `doctor_id` int(11) NOT NULL,
          `qualification` varchar(100) DEFAULT NULL,
          `experience` int(11) DEFAULT NULL,
          `specialization` varchar(100) DEFAULT NULL,
          `address` text DEFAULT NULL,
          PRIMARY KEY (`doctor_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS `lab_appointments` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `patient_id` int(11) DEFAULT NULL,
          `lab_id` int(11) DEFAULT NULL,
          `test_name` varchar(100) DEFAULT NULL,
          `appointment_date` date DEFAULT NULL,
          `status` enum('pending','accepted','rejected') DEFAULT 'pending',
          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS `lab_reports` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `appointment_id` int(11) DEFAULT NULL,
          `patient_id` int(11) DEFAULT NULL,
          `lab_id` int(11) DEFAULT NULL,
          `report_file` varchar(255) DEFAULT NULL,
          `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS `notifications` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `user_id` int(11) DEFAULT NULL,
          `message` text DEFAULT NULL,
          `seen` tinyint(4) DEFAULT 0,
          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS `records` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `user_id` int(11) NOT NULL,
          `title` varchar(255) NOT NULL,
          `description` text DEFAULT NULL,
          `record_date` date DEFAULT NULL,
          `file_path` varchar(255) DEFAULT NULL,
          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS `user_locations` (
          `user_id` int(11) NOT NULL,
          `latitude` decimal(10,8) DEFAULT NULL,
          `longitude` decimal(11,8) DEFAULT NULL,
          `updated_at` timestamp NOT NULL DEFAULT current_timestamp(),
          PRIMARY KEY (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS `prescriptions` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `doctor_id` int(11) NOT NULL,
          `patient_id` int(11) NOT NULL,
          `appointment_id` int(11) DEFAULT NULL,
          `medicine_name` varchar(255) NOT NULL,
          `dosage` varchar(100) NOT NULL,
          `timing_morning` tinyint(1) NOT NULL DEFAULT 1,
          `morning_time` time DEFAULT '08:00:00',
          `timing_afternoon` tinyint(1) NOT NULL DEFAULT 1,
          `afternoon_time` time DEFAULT '13:00:00',
          `timing_night` tinyint(1) NOT NULL DEFAULT 1,
          `night_time` time DEFAULT '20:00:00',
          `every_hours` int(11) DEFAULT NULL,
          `intake_relation` enum('after_meal','before_meal','with_meal','anytime') NOT NULL DEFAULT 'after_meal',
          `instructions` text DEFAULT NULL,
          `start_date` date NOT NULL,
          `duration_days` int(11) NOT NULL DEFAULT 7,
          `end_date` date NOT NULL,
          `status` enum('active','completed','stopped') NOT NULL DEFAULT 'active',
          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    ];

    // Suppress MySQLi exceptions during schema initialization
    $driver = new mysqli_driver();
    $orig_report = $driver->report_mode;
    $driver->report_mode = MYSQLI_REPORT_OFF;

    try {
        // Run CREATE TABLE queries if users or prescriptions table is not present
        $checkUsers = $mysqli->query("SHOW TABLES LIKE 'users'");
        $checkPresc = $mysqli->query("SHOW TABLES LIKE 'prescriptions'");
        if (!$checkUsers || $checkUsers->num_rows === 0 || !$checkPresc || $checkPresc->num_rows === 0) {
            foreach ($tables as $sql) {
                try {
                    $mysqli->query($sql);
                } catch (Throwable $e) {}
            }
        }

        // Safe column migrations: check if column exists before ALTER TABLE
        $migrations = [
            "users" => [
                "qualification"  => "ALTER TABLE users ADD COLUMN qualification varchar(100) DEFAULT NULL",
                "specialization" => "ALTER TABLE users ADD COLUMN specialization varchar(100) DEFAULT NULL",
                "hospital_id"    => "ALTER TABLE users ADD COLUMN hospital_id int(11) DEFAULT NULL",
                "medicine_reminders_enabled" => "ALTER TABLE users ADD COLUMN medicine_reminders_enabled tinyint(1) DEFAULT 1"
            ],
            "appointments" => [
                "rescheduled_date" => "ALTER TABLE appointments ADD COLUMN rescheduled_date date DEFAULT NULL",
                "rescheduled_time" => "ALTER TABLE appointments ADD COLUMN rescheduled_time time DEFAULT NULL",
                "reschedule_msg"   => "ALTER TABLE appointments ADD COLUMN reschedule_msg text DEFAULT NULL",
                "reschedule_note"  => "ALTER TABLE appointments ADD COLUMN reschedule_note text DEFAULT NULL"
            ],
            "prescriptions" => [
                "every_hours" => "ALTER TABLE prescriptions ADD COLUMN every_hours int(11) DEFAULT NULL"
            ]
        ];

        foreach ($migrations as $table => $columns) {
            foreach ($columns as $col => $alterSql) {
                try {
                    $check = $mysqli->query("SHOW COLUMNS FROM `$table` LIKE '$col'");
                    if ($check && $check->num_rows === 0) {
                        $mysqli->query($alterSql);
                    }
                } catch (Throwable $e) {}
            }
        }
    } catch (Throwable $e) {
        // Silently handle any initialization issue
    } finally {
        $driver->report_mode = $orig_report;
    }
}

// Auto-initialize tables and ensure schema integrity on every connection
if ($connected) {
    initialize_chr_tables($mysqli);
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
