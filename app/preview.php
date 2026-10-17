<?php
session_start();
require 'includes/db.php';
if (empty($_SESSION['username'])) { http_response_code(401); exit; }
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) { http_response_code(400); exit; }
$username = $_SESSION['username'];
$stmt = $conn->prepare("SELECT filepath FROM files WHERE id = ? AND username = ? AND deleted = 0 LIMIT 1");
$stmt->bind_param('is', $id, $username);
$stmt->execute();
$file = $stmt->get_result()->fetch_assoc();
if (!$file) { http_response_code(404); exit; }
$base = realpath('/var/www/lantern-storage');
$path = realpath($file['filepath']);
if (!$base || !$path || !str_starts_with($path, $base . DIRECTORY_SEPARATOR) || !is_file($path)) { http_response_code(404); exit; }
$mime = mime_content_type($path);
if (!in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) { http_response_code(415); exit; }
header('Content-Type: ' . $mime);
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; sandbox");
header('Cache-Control: private, no-store');
readfile($path);
