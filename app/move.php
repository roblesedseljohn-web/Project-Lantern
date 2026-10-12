<?php
session_start();
include 'includes/db.php';

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];

if (!isset($_GET['id'])) {
    header("Location: dashboard.php?error=missing_file");
    exit();
}
$id = intval($_GET['id']);

// GET FILE
$stmt = $conn->prepare("SELECT * FROM files WHERE id = ? AND username = ? AND deleted = 0 LIMIT 1");
$stmt->bind_param("is", $id, $username);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows == 0) {
    header("Location: dashboard.php?error=file_not_found");
    exit();
}
$file = $result->fetch_assoc();

// CSRF check
if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
    header("Location: dashboard.php?error=invalid_token");
    exit();
}

$folderId = intval($_POST['folder_id'] ?? 0);

// Check duplicate filename in destination
$checkStmt = $conn->prepare("SELECT id FROM files WHERE folder_id = ? AND filename = ? AND deleted = 0 AND username = ?");
$checkStmt->bind_param("iss", $folderId, $file['filename'], $username);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();
if ($checkResult->num_rows > 0) {
    header("Location: dashboard.php?error=file_exists");
    exit();
}

// Perform the move
$updateStmt = $conn->prepare("UPDATE files SET folder_id = ? WHERE id = ?");
$updateStmt->bind_param("ii", $folderId, $id);
if ($updateStmt->execute()) {
    header("Location: dashboard.php?moved=1");
} else {
    header("Location: dashboard.php?error=move_failed");
}
exit();