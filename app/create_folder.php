<?php
session_start();
include 'includes/db.php';

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}
$username = $_SESSION['username'];

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    header("Location: dashboard.php");
    exit();
}

// CSRF verification
if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
    die("CSRF token validation failed.");
}

$folderName = trim($_POST['folder_name'] ?? '');
$parentId = intval($_POST['parent_id'] ?? 0);

if ($folderName == '') {
    header("Location: dashboard.php?error=empty_folder");
    exit();
}

// Check duplicates
$checkStmt = $conn->prepare("SELECT id FROM folders WHERE username = ? AND folder_name = ? AND parent_id = ? AND deleted = 0 LIMIT 1");
$checkStmt->bind_param("ssi", $username, $folderName, $parentId);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();
if ($checkResult->num_rows > 0) {
    header("Location: dashboard.php?folder_id=".$parentId."&error=folder_exists");
    exit();
}

// Create folder
$stmt = $conn->prepare("INSERT INTO folders (username, folder_name, parent_id) VALUES (?, ?, ?)");
$stmt->bind_param("ssi", $username, $folderName, $parentId);
$stmt->execute();

header("Location: dashboard.php?folder_id=".$parentId);
exit();
?>