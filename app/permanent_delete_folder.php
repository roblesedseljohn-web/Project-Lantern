<?php
session_start();
include 'includes/db.php';

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];
$id = intval($_GET['id']);

function permanentDeleteFolderRecursive($conn, $username, $folderId) {
    // Delete files inside
    $fileStmt = $conn->prepare("SELECT * FROM files WHERE folder_id = ? AND username = ?");
    $fileStmt->bind_param("is", $folderId, $username);
    $fileStmt->execute();
    $fileResult = $fileStmt->get_result();
    while ($file = $fileResult->fetch_assoc()) {
        if (file_exists($file['filepath'])) {
            unlink($file['filepath']);
        }
    }

    // Delete file records
    $deleteFiles = $conn->prepare("DELETE FROM files WHERE folder_id = ?");
    $deleteFiles->bind_param("i", $folderId);
    $deleteFiles->execute();

    // Get child folders
    $childStmt = $conn->prepare("SELECT id FROM folders WHERE parent_id = ? AND username = ?");
    $childStmt->bind_param("is", $folderId, $username);
    $childStmt->execute();
    $childResult = $childStmt->get_result();
    while ($child = $childResult->fetch_assoc()) {
        permanentDeleteFolderRecursive($conn, $username, $child['id']);
    }

    // Delete the folder record itself
    $delFolder = $conn->prepare("DELETE FROM folders WHERE id = ?");
    $delFolder->bind_param("i", $folderId);
    $delFolder->execute();
}

permanentDeleteFolderRecursive($conn, $username, $id);
header("Location: trash.php");
exit();
?>