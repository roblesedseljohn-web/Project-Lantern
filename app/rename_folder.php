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

// GET FOLDER
$stmt = $conn->prepare("SELECT * FROM folders WHERE id = ? AND username = ? AND deleted = 0 LIMIT 1");
$stmt->bind_param("is", $folderId, $username);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows == 0) {
    header("Location: dashboard.php?error=folder_not_found");
    exit();
}
$folder = $result->fetch_assoc();

// CSRF check
if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
    header("Location: dashboard.php?error=invalid_token");
    exit();
}

$newName = trim($_POST['folder_name'] ?? '');
if ($newName === '') {
    header("Location: dashboard.php?error=empty_folder");
    exit();
}

$updateStmt = $conn->prepare("UPDATE folders SET folder_name = ? WHERE id = ?");
$updateStmt->bind_param("si", $newName, $folderId);
if ($updateStmt->execute()) {
    header("Location: dashboard.php?renamed=1");
} else {
    header("Location: dashboard.php?error=rename_failed");
}
exit();