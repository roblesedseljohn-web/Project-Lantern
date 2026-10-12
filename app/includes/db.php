<?php
$host = getenv('DB_HOST') ?: 'mariadb';
$dbname = getenv('DB_NAME') ?: 'lantern';
$user = getenv('DB_USER') ?: 'lanternuser';
$pass = getenv('DB_PASSWORD');
if ($pass === false || $pass === '') { error_log('DB_PASSWORD is not configured'); http_response_code(503); exit('Database is not configured'); }
$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) { error_log('Database connection failed: ' . $conn->connect_error); http_response_code(503); exit('Database unavailable'); }
$conn->set_charset('utf8mb4');
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
    return $_SESSION['csrf_token'];
}
function verifyCsrfToken($token) {
    return is_string($token) && isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
