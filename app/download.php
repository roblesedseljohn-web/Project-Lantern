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

if (!file_exists($filePath)) {

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
header('Content-Type: ' . $mimeType);
header('Content-Disposition: attachment; filename="' . basename($fileName) . '"');
header('Content-Length: ' . $fileSize);
header('Cache-Control: no-cache');
header('Pragma: public');

readfile($filePath);

exit();
?>