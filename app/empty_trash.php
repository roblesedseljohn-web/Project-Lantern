<?php
session_start();
include 'includes/db.php';

// -------- DEBUG (remove after testing) --------
error_reporting(E_ALL);
ini_set('display_errors', 1);
// ---------------------------------------------

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: trash.php");
    exit();
}

// CSRF verification
if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
    die("Invalid CSRF token.");
}

/**
 * Recursively delete a folder and ALL its contents (physical files + DB records).
 * Handles any nesting level, regardless of the `deleted` flag of children.
 */
function permanentDeleteFolderRecursive($conn, $username, $folderId) {
    // 1. Delete physical files and file records inside this folder
    $fileStmt = $conn->prepare(
        "SELECT * FROM files WHERE folder_id = ? AND username = ?"
    );
    $fileStmt->bind_param("is", $folderId, $username);
    $fileStmt->execute();
    $fileResult = $fileStmt->get_result();
    while ($file = $fileResult->fetch_assoc()) {
        if (file_exists($file['filepath'])) {
            unlink($file['filepath']);
        }
    }
    $conn->query("DELETE FROM files WHERE folder_id = $folderId AND username = '$username'");

    // 2. Recursively delete child folders
    $childStmt = $conn->prepare(
        "SELECT id FROM folders WHERE parent_id = ? AND username = ?"
    );
    $childStmt->bind_param("is", $folderId, $username);
    $childStmt->execute();
    $childResult = $childStmt->get_result();
    while ($child = $childResult->fetch_assoc()) {
        permanentDeleteFolderRecursive($conn, $username, $child['id']);
    }

    // 3. Delete the folder record itself
    $conn->query("DELETE FROM folders WHERE id = $folderId AND username = '$username'");
}

// ----------------------------------------------
// 1. Permanently remove all folders in the trash
// ----------------------------------------------
$folderStmt = $conn->prepare(
    "SELECT id FROM folders WHERE username = ? AND deleted = 1"
);
$folderStmt->bind_param("s", $username);
$folderStmt->execute();
$folderResult = $folderStmt->get_result();
while ($row = $folderResult->fetch_assoc()) {
    permanentDeleteFolderRecursive($conn, $username, $row['id']);
}

// ----------------------------------------------
// 2. Remove any remaining trashed files that are
//    not inside a folder (e.g., root-level files)
// ----------------------------------------------
$fileStmt = $conn->prepare(
    "SELECT * FROM files WHERE username = ? AND deleted = 1"
);
$fileStmt->bind_param("s", $username);
$fileStmt->execute();
$fileResult = $fileStmt->get_result();
while ($file = $fileResult->fetch_assoc()) {
    if (file_exists($file['filepath'])) {
        unlink($file['filepath']);
    }
}
$conn->query("DELETE FROM files WHERE username = '$username' AND deleted = 1");

// ----------------------------------------------
// Done – redirect back with success message
// ----------------------------------------------
header("Location: trash.php?emptied=1");
exit();