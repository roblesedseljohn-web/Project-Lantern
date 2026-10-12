<?php
session_start();
require 'includes/db.php';
if (!isset($_SESSION['username'])) { http_response_code(401); exit('Unauthorized'); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrfToken($_POST['csrf_token'] ?? '')) { http_response_code(403); exit('Invalid request'); }
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) { http_response_code(400); exit('Invalid file'); }
$username = $_SESSION['username'];
$stmt = $conn->prepare("SELECT filepath FROM files WHERE id = ? AND username = ? AND deleted = 1 LIMIT 1");
$stmt->bind_param("is", $id, $username);
$stmt->execute();
$file = $stmt->get_result()->fetch_assoc();
if (!$file) { http_response_code(404); exit('File not found in trash'); }
$path = $file['filepath'];
if (is_file($path) && !unlink($path)) { http_response_code(500); exit('Could not remove file'); }
$stmt = $conn->prepare("DELETE FROM files WHERE id = ? AND username = ? AND deleted = 1");
$stmt->bind_param("is", $id, $username);
$stmt->execute();
header("Location: trash.php", true, 303);
exit;
