<?php

session_start();

include 'includes/db.php';

if (!isset($_SESSION['username'])) {

    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];

if (!isset($_GET['id'])) {

    die("Invalid folder.");
}

$folderId = intval($_GET['id']);

# =========================================
# RECURSIVE RESTORE FUNCTION
# =========================================

function restoreFolderRecursive(
    $conn,
    $username,
    $folderId
) {

    # =========================================
    # RESTORE FOLDER
    # =========================================

    $stmt = $conn->prepare(
        "UPDATE folders
         SET deleted = 0
         WHERE id = ?
         AND username = ?"
    );

    $stmt->bind_param(
        "is",
        $folderId,
        $username
    );

    $stmt->execute();

    # =========================================
    # RESTORE FILES
    # =========================================

    $fileStmt = $conn->prepare(
        "UPDATE files
         SET deleted = 0
         WHERE folder_id = ?
         AND username = ?"
    );

    $fileStmt->bind_param(
        "is",
        $folderId,
        $username
    );

    $fileStmt->execute();

    # =========================================
    # GET CHILD FOLDERS
    # =========================================

    $childStmt = $conn->prepare(
        "SELECT id FROM folders
         WHERE parent_id = ?
         AND username = ?"
    );

    $childStmt->bind_param(
        "is",
        $folderId,
        $username
    );

    $childStmt->execute();

    $childResult = $childStmt->get_result();

    while ($child = $childResult->fetch_assoc()) {

        restoreFolderRecursive(
            $conn,
            $username,
            $child['id']
        );
    }
}

# =========================================
# START RESTORE
# =========================================

restoreFolderRecursive(
    $conn,
    $username,
    $folderId
);

header("Location: trash.php");

exit();

?>