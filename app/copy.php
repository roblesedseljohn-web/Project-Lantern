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

// Duplicate naming logic (same as original)
$originalPath = $file['filepath'];
$directory = dirname($originalPath);
$extension = pathinfo($file['filename'], PATHINFO_EXTENSION);
$filenameOnly = preg_replace('/ - Copy( \d+)?$/', '', pathinfo($file['filename'], PATHINFO_FILENAME));
$newFileName = $filenameOnly . " - Copy";
if (!empty($extension)) $newFileName .= "." . $extension;
$counter = 1;
$testName = $newFileName;
while (file_exists($directory . "/" . $testName)) {
    $testName = $filenameOnly . " - Copy " . $counter;
    if (!empty($extension)) $testName .= "." . $extension;
    $counter++;
}
$newFileName = $testName;
$newPath = $directory . "/" . $newFileName;

if (!copy($originalPath, $newPath)) {
    header("Location: dashboard.php?error=copy_failed");
    exit();
}

$filesize = filesize($newPath);
$insertStmt = $conn->prepare("INSERT INTO files (username, filename, filepath, filesize, filetype, folder_id) VALUES (?, ?, ?, ?, ?, ?)");
$insertStmt->bind_param("sssdsi", $username, $newFileName, $newPath, $filesize, $file['filetype'], $folderId);
if ($insertStmt->execute()) {
    header("Location: dashboard.php?copied=1");
} else {
    header("Location: dashboard.php?error=copy_failed");
}
exit();