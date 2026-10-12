<?php
session_start();
require 'includes/db.php';
if (!isset($_SESSION['username'])) { http_response_code(401); exit('Unauthorized'); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrfToken($_POST['csrf_token'] ?? '')) { http_response_code(403); exit('Invalid request'); }
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) { http_response_code(400); exit('Invalid folder'); }
$username = $_SESSION['username'];
$check = $conn->prepare("SELECT id FROM folders WHERE id = ? AND username = ? AND deleted = 1 LIMIT 1");
$check->bind_param('is', $id, $username);
$check->execute();
if (!$check->get_result()->fetch_assoc()) { http_response_code(404); exit('Folder not found in trash'); }
function permanentDeleteFolderRecursive($conn, $username, $folderId) {
    $files = $conn->prepare("SELECT id, filepath FROM files WHERE folder_id = ? AND username = ?");
    $files->bind_param('is', $folderId, $username);
    $files->execute();
    $result = $files->get_result();
    while ($file = $result->fetch_assoc()) {
        if (is_file($file['filepath']) && !unlink($file['filepath'])) { throw new RuntimeException('Unable to delete file'); }
        $delFile = $conn->prepare("DELETE FROM files WHERE id = ? AND username = ?");
        $delFile->bind_param('is', $file['id'], $username);
        $delFile->execute();
    }
    $children = $conn->prepare("SELECT id FROM folders WHERE parent_id = ? AND username = ?");
    $children->bind_param('is', $folderId, $username);
    $children->execute();
    $childResult = $children->get_result();
    while ($child = $childResult->fetch_assoc()) { permanentDeleteFolderRecursive($conn, $username, (int)$child['id']); }
    $delete = $conn->prepare("DELETE FROM folders WHERE id = ? AND username = ?");
    $delete->bind_param('is', $folderId, $username);
    $delete->execute();
}
try { permanentDeleteFolderRecursive($conn, $username, $id); }
catch (Throwable $e) { error_log('Folder removal failed: ' . $e->getMessage()); http_response_code(500); exit('Deletion incomplete'); }
header('Location: trash.php', true, 303);
exit;
