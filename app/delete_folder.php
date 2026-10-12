<?php
session_start();
include 'includes/db.php';

if (!isset($_SESSION['username'])) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Unauthorized"]);
    exit();
}

$username = $_SESSION['username'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method not allowed"]);
    exit();
}

if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Invalid CSRF token"]);
    exit();
}

if (!isset($_POST['id'])) {
    echo json_encode(["success" => false, "message" => "Missing folder ID"]);
    exit();
}

$folderId = intval($_POST['id']);

// Recursive soft-delete
function softDeleteFolderRecursive($conn, $username, $folderId) {
    // Mark folder as deleted
    $stmt = $conn->prepare("UPDATE folders SET deleted = 1 WHERE id = ? AND username = ?");
    $stmt->bind_param("is", $folderId, $username);
    $stmt->execute();

    // Mark all files inside as deleted
    $fileStmt = $conn->prepare("UPDATE files SET deleted = 1 WHERE folder_id = ? AND username = ?");
    $fileStmt->bind_param("is", $folderId, $username);
    $fileStmt->execute();

    // Recursively process child folders
    $childStmt = $conn->prepare("SELECT id FROM folders WHERE parent_id = ? AND username = ? AND deleted = 0");
    $childStmt->bind_param("is", $folderId, $username);
    $childStmt->execute();
    $childResult = $childStmt->get_result();
    while ($child = $childResult->fetch_assoc()) {
        softDeleteFolderRecursive($conn, $username, $child['id']);
    }
}

softDeleteFolderRecursive($conn, $username, $folderId);
echo json_encode(["success" => true]);
exit();