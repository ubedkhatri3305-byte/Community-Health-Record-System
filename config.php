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
    if ($is_serverless) {
        // Helpful setup UI when deployed to Vercel without configured cloud database
        http_response_code(500);
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>CHR - Database Setup Required</title>
            <link rel="stylesheet" href="assets/css/styles.css">
        </head>
        <body class="center" style="min-height:100vh;background:linear-gradient(135deg, #0f172a 0%, #1e293b 100%);color:#f8fafc;">
            <div class="card" style="max-width:560px;width:90%;margin:20px;padding:32px;background:#ffffff;color:#1e293b;border-radius:18px;box-shadow:0 20px 40px rgba(0,0,0,0.3);">
                <div class="logo" style="background:#eff6ff;color:#2563eb;font-size:32px;">🏥</div>
                <h2 style="color:#1e3a8a;margin-bottom:8px;">Cloud Database Required on Vercel</h2>
                <p style="color:#64748b;font-size:14px;line-height:1.6;margin-bottom:20px;">
                    Your CHR system is live on Vercel! To connect your database, please configure your Cloud MySQL credentials in your <strong>Vercel Dashboard &rarr; Project Settings &rarr; Environment Variables</strong>:
                </p>
                <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:14px;font-family:monospace;font-size:13px;line-height:1.8;margin-bottom:20px;">
                    <div><strong>DB_HOST</strong>: your-mysql-host.cloud</div>
                    <div><strong>DB_USER</strong>: your-username</div>
                    <div><strong>DB_PASS</strong>: your-password</div>
                    <div><strong>DB_NAME</strong>: chr_db</div>
                    <div><strong>DB_PORT</strong>: 3306 (or 4000)</div>
                    <div><strong>DB_SSL</strong>: true (if using TiDB / Aiven)</div>
                </div>
                <p style="font-size:12px;color:#94a3b8;margin-bottom:20px;">
                    Free Cloud MySQL providers supported: <strong>TiDB Cloud (Free Serverless)</strong>, <strong>Aiven MySQL</strong>, or <strong>Railway</strong>. Import your <code>database.sql</code> file into the cloud database.
                </p>
                <button class="btn" onclick="location.reload()">🔄 Retry Connection</button>
            </div>
        </body>
        </html>
        <?php
        exit;
    } else {
        die("Database connection failed: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error . 
            "<br><small>Host: " . htmlspecialchars($DB_HOST) . " | Port: " . htmlspecialchars($DB_PORT) . " | Database: " . htmlspecialchars($DB_NAME) . "</small>");
    }
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
