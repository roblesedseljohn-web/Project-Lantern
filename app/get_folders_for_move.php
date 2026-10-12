<?php
session_start();
include 'includes/db.php';

if (!isset($_SESSION['username'])) {
    http_response_code(401);
    exit();
}

$username = $_SESSION['username'];
$excludeId = isset($_GET['exclude_id']) ? intval($_GET['exclude_id']) : -1;

$stmt = $conn->prepare(
    "SELECT id, folder_name FROM folders
     WHERE username = ?
     AND deleted = 0
     AND id != ?
     ORDER BY folder_name ASC"
);
$stmt->bind_param("si", $username, $excludeId);
$stmt->execute();
$result = $stmt->get_result();

echo '<option value="0">📂 Home / Root</option>';
while ($row = $result->fetch_assoc()) {
    $safeName = htmlspecialchars($row['folder_name'], ENT_QUOTES, 'UTF-8');
    echo '<option value="' . intval($row['id']) . '">📁 ' . $safeName . '</option>';
}