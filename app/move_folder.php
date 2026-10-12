<?php
session_start();
include 'includes/db.php';

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];

if (!isset($_GET['id'])) {
    header("Location: dashboard.php?error=missing_folder");
    exit();
}
$folderId = intval($_GET['id']);

// GET CURRENT FOLDER
$stmt = $conn->prepare("SELECT * FROM folders WHERE id = ? AND username = ? AND deleted = 0 LIMIT 1");
$stmt->bind_param("is", $folderId, $username);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows == 0) {
    header("Location: dashboard.php?error=missing_folder");
    exit();
}
$currentFolder = $result->fetch_assoc();

// CSRF check
if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
    header("Location: dashboard.php?error=invalid_token");
    exit();
}

$newParentId = intval($_POST['parent_id'] ?? 0);

if ($newParentId == $folderId) {
    header("Location: dashboard.php?error=invalid_move");
    exit();
}

// Check duplicate folder name in destination
$checkStmt = $conn->prepare("SELECT id FROM folders WHERE parent_id = ? AND folder_name = ? AND deleted = 0 AND username = ?");
$checkStmt->bind_param("iss", $newParentId, $currentFolder['folder_name'], $username);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();
if ($checkResult->num_rows > 0) {
    header("Location: dashboard.php?error=folder_exists");
    exit();
}

// Perform the move
$updateStmt = $conn->prepare("UPDATE folders SET parent_id = ? WHERE id = ?");
$updateStmt->bind_param("ii", $newParentId, $folderId);
if ($updateStmt->execute()) {
    header("Location: dashboard.php?moved=1");
} else {
    header("Location: dashboard.php?error=move_failed");
}
exit();