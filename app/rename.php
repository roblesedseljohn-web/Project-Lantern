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
$fileId = intval($_GET['id']);

// GET FILE
$stmt = $conn->prepare("SELECT * FROM files WHERE id = ? AND username = ? AND deleted = 0 LIMIT 1");
$stmt->bind_param("is", $fileId, $username);
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

$newName = trim($_POST['new_name'] ?? '');
if ($newName === '') {
    header("Location: dashboard.php?error=empty_filename");
    exit();
}

// Duplicate‑safe rename logic (unchanged)
$extension = pathinfo($file['filename'], PATHINFO_EXTENSION);
$filenameOnly = pathinfo($newName, PATHINFO_FILENAME);
$safeName = preg_replace("/[^A-Za-z0-9._ -]/", "_", $filenameOnly);
$finalName = !empty($extension) ? $safeName . "." . $extension : $safeName;

$directory = dirname($file['filepath']);
$counter = 1;
$testName = $finalName;
while (file_exists($directory . "/" . $testName) && $testName != $file['filename']) {
    if (!empty($extension)) {
        $testName = $safeName . " (" . $counter . ")." . $extension;
    } else {
        $testName = $safeName . " (" . $counter . ")";
    }
    $counter++;
}
$finalName = $testName;

$oldPath = $file['filepath'];
$newPath = $directory . "/" . $finalName;

if (rename($oldPath, $newPath)) {
    $updateStmt = $conn->prepare("UPDATE files SET filename = ?, filepath = ? WHERE id = ?");
    $updateStmt->bind_param("ssi", $finalName, $newPath, $fileId);
    $updateStmt->execute();
    header("Location: dashboard.php?renamed=1");
} else {
    header("Location: dashboard.php?error=rename_failed");
}
exit();