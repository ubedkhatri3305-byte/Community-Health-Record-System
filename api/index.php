<?php
/**
 * =========================================================================
 * Vercel Serverless Unified Entrypoint & Router
 * Community Health Record System (CHR)
 * =========================================================================
 *
 * This router dispatches requests on Vercel through a single Serverless
 * Function, staying well within Vercel's 12-function limit on the Hobby plan
 * while executing all application pages seamlessly.
 */

// Enable output buffering so headers, redirects, and session cookies always succeed
if (ob_get_level() === 0) {
    ob_start();
}

$rootDir = dirname(__DIR__);
chdir($rootDir);

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($requestUri, PHP_URL_PATH);
$path = trim($path, '/');

// Default home route
if ($path === '' || $path === 'index' || $path === 'index.html') {
    $script = 'index.php';
} else {
    // If clean URL without .php, check if matching .php exists
    if (!str_ends_with($path, '.php')) {
        if (file_exists($rootDir . '/' . $path . '.php')) {
            $path .= '.php';
        }
    }
    $script = basename($path);
}

$targetFile = $rootDir . '/' . $script;

// Prevent direct execution of internal config, dumps, or build files
$blockedFiles = [
    'config.php',
    'database.sql',
    'Dockerfile',
    'docker-entrypoint.sh',
    'render.yaml',
    'composer.json',
    'composer.lock',
    'package.json',
    'vercel.json',
    '.env',
    '.env.example'
];

if (in_array($script, $blockedFiles) || !file_exists($targetFile) || !str_ends_with($script, '.php')) {
    // If a static file or uploaded document was requested
    $staticFile = $rootDir . '/' . $path;
    if (!file_exists($staticFile) && str_starts_with($path, 'uploads/')) {
        $tempUpload = sys_get_temp_dir() . '/uploads/' . basename($path);
        if (file_exists($tempUpload) && !is_dir($tempUpload)) {
            $staticFile = $tempUpload;
        }
    }

    if (file_exists($staticFile) && !is_dir($staticFile)) {
        $ext = strtolower(pathinfo($staticFile, PATHINFO_EXTENSION));
        $mimes = [
            'css'   => 'text/css',
            'js'    => 'application/javascript',
            'png'   => 'image/png',
            'jpg'   => 'image/jpeg',
            'jpeg'  => 'image/jpeg',
            'gif'   => 'image/gif',
            'svg'   => 'image/svg+xml',
            'ico'   => 'image/x-icon',
            'pdf'   => 'application/pdf',
            'woff'  => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf'   => 'font/ttf'
        ];
        if (isset($mimes[$ext])) {
            header('Content-Type: ' . $mimes[$ext]);
        }
        readfile($staticFile);
        exit;
    }

    // 404 Response
    http_response_code(404);
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>404 - Page Not Found</title>';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<link rel="stylesheet" href="/assets/css/styles.css">';
    echo '</head><body><div class="center" style="min-height:100vh;">';
    echo '<div class="card" style="text-align:center;max-width:420px;padding:36px;">';
    echo '<div class="logo">404</div>';
    echo '<h2 style="margin:10px 0 8px 0;">Page Not Found</h2>';
    echo '<p style="color:var(--text-muted);font-size:14px;margin-bottom:24px;">The page you are looking for does not exist or has been moved.</p>';
    echo '<a class="btn" href="/index.php">Back to Home</a>';
    echo '</div></div></body></html>';
    exit;
}

// Emulate standard PHP server environment for the required script
$_SERVER['SCRIPT_NAME'] = '/' . $script;
$_SERVER['SCRIPT_FILENAME'] = $targetFile;
$_SERVER['PHP_SELF'] = '/' . $script;

// Execute target page
require $targetFile;
