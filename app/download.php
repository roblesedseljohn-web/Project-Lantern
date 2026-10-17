<?php

session_start();

include 'includes/db.php';

# =========================================
# REQUIRE LOGIN
# =========================================

if (!isset($_SESSION['username'])) {

    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];

# =========================================
# VALIDATE FILE ID
# =========================================

if (!isset($_GET['id'])) {

    die("Invalid file.");
}

$fileId = intval($_GET['id']);

# =========================================
# GET FILE FROM DATABASE
# =========================================

$stmt = $conn->prepare(
    "SELECT * FROM files
     WHERE id = ?
     AND username = ?
     AND deleted = 0
     LIMIT 1"
);

$stmt->bind_param(
    "is",
    $fileId,
    $username
);

$stmt->execute();

$result = $stmt->get_result();

# =========================================
# FILE NOT FOUND
# =========================================

if ($result->num_rows == 0) {

    die("File not found.");
}

$file = $result->fetch_assoc();

$filePath = $file['filepath'];

# =========================================
# VERIFY FILE EXISTS
# =========================================

$storageRoot = realpath('/var/www/lantern-storage');
$resolvedPath = realpath($filePath);
if (!$storageRoot || !$resolvedPath || !str_starts_with($resolvedPath, $storageRoot . DIRECTORY_SEPARATOR)) { http_response_code(404); exit('File not found'); }
$filePath = $resolvedPath;
if (!is_file($filePath)) {

    die("Physical file missing.");
}

# =========================================
# GET FILE INFO
# =========================================

$fileName = $file['filename'];

$fileSize = filesize($filePath);

$mimeType = mime_content_type($filePath);

# =========================================
# FORCE DOWNLOAD
# =========================================

header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: attachment; filename="' . basename($fileName) . '"');
header('Content-Length: ' . $fileSize);
header('Cache-Control: no-cache');
header('Pragma: public');

readfile($filePath);

exit();
?>