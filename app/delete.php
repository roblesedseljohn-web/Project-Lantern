<?php
session_start();
include 'includes/db.php';

if (!isset($_SESSION['username'])) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Unauthorized"]);
    exit();
}

$username = $_SESSION['username'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method not allowed"]);
    exit();
}

if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Invalid CSRF token"]);
    exit();
}

if (!isset($_POST['id'])) {
    echo json_encode(["success" => false, "message" => "Missing file ID"]);
    exit();
}

$id = intval($_POST['id']);

$stmt = $conn->prepare("UPDATE files SET deleted = 1 WHERE id = ? AND username = ?");
$stmt->bind_param("is", $id, $username);
if ($stmt->execute() && $stmt->affected_rows > 0) {
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false, "message" => "File not found or already deleted"]);
}
exit();