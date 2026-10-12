<?php

session_start();

include 'includes/db.php';

if (!isset($_SESSION['username'])) {

    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];

if (!isset($_GET['id'])) {

    die("Invalid file.");
}

$id = intval($_GET['id']);

# =========================================
# GET FILE
# =========================================

$stmt = $conn->prepare(
    "SELECT * FROM files
     WHERE id = ?
     AND username = ?
     LIMIT 1"
);

$stmt->bind_param(
    "is",
    $id,
    $username
);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows == 0) {

    die("File not found.");
}

$file = $result->fetch_assoc();

# =========================================
# DELETE PHYSICAL FILE
# =========================================

if (file_exists($file['filepath'])) {

    unlink($file['filepath']);
}

# =========================================
# DELETE DATABASE RECORD
# =========================================

$deleteStmt = $conn->prepare(
    "DELETE FROM files
     WHERE id = ?"
);

$deleteStmt->bind_param(
    "i",
    $id
);

$deleteStmt->execute();

header("Location: trash.php");

exit();

?>