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

// GET SOURCE FOLDER
$stmt = $conn->prepare("SELECT * FROM folders WHERE id = ? AND username = ? AND deleted = 0 LIMIT 1");
$stmt->bind_param("is", $folderId, $username);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows == 0) {
    header("Location: dashboard.php?error=folder_not_found");
    exit();
}
$sourceFolder = $result->fetch_assoc();

// CSRF check
if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
    header("Location: dashboard.php?error=invalid_token");
    exit();
}

$destinationParentId = intval($_POST['parent_id'] ?? 0);

// Prevent copying into itself (if destination is the same folder)
if ($destinationParentId == $folderId) {
    header("Location: dashboard.php?error=invalid_copy");
    exit();
}

// Recursive copy function (unchanged)
function copyFolderRecursive($conn, $username, $sourceFolderId, $newParentId) {
    $stmt = $conn->prepare("SELECT * FROM folders WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $sourceFolderId);
    $stmt->execute();
    $result = $stmt->get_result();
    $folder = $result->fetch_assoc();

    $baseFolderName = preg_replace('/ - Copy( \d+)?$/', '', $folder['folder_name']);
    $newFolderName = $baseFolderName . " - Copy";
    $counter = 1;
    while (true) {
        $checkStmt = $conn->prepare("SELECT id FROM folders WHERE username = ? AND folder_name = ? AND parent_id = ? AND deleted = 0");
        $checkStmt->bind_param("ssi", $username, $newFolderName, $newParentId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        if ($checkResult->num_rows == 0) break;
        $newFolderName = $baseFolderName . " - Copy " . $counter;
        $counter++;
    }

    $insertStmt = $conn->prepare("INSERT INTO folders (username, folder_name, parent_id) VALUES (?, ?, ?)");
    $insertStmt->bind_param("ssi", $username, $newFolderName, $newParentId);
    $insertStmt->execute();
    $newFolderId = $conn->insert_id;

    // Copy files
    $fileStmt = $conn->prepare("SELECT * FROM files WHERE folder_id = ? AND username = ? AND deleted = 0");
    $fileStmt->bind_param("is", $sourceFolderId, $username);
    $fileStmt->execute();
    $fileResult = $fileStmt->get_result();
    while ($file = $fileResult->fetch_assoc()) {
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
        copy($originalPath, $newPath);
        $filesize = filesize($newPath);
        $insertFileStmt = $conn->prepare("INSERT INTO files (username, filename, filepath, filesize, filetype, folder_id) VALUES (?, ?, ?, ?, ?, ?)");
        $insertFileStmt->bind_param("sssdsi", $username, $newFileName, $newPath, $filesize, $file['filetype'], $newFolderId);
        $insertFileStmt->execute();
    }

    // Copy child folders
    $childStmt = $conn->prepare("SELECT * FROM folders WHERE parent_id = ? AND username = ? AND deleted = 0");
    $childStmt->bind_param("is", $sourceFolderId, $username);
    $childStmt->execute();
    $childResult = $childStmt->get_result();
    while ($child = $childResult->fetch_assoc()) {
        copyFolderRecursive($conn, $username, $child['id'], $newFolderId);
    }
}

// Perform the copy
try {
    copyFolderRecursive($conn, $username, $folderId, $destinationParentId);
    header("Location: dashboard.php?copied=1");
} catch (Exception $e) {
    header("Location: dashboard.php?error=copy_failed");
}
exit();