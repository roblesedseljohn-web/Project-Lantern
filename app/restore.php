<?php
session_start();

include 'includes/db.php';

if (isset($_GET['id'])) {

    $id = $_GET['id'];

    $stmt = $conn->prepare(
        "UPDATE files
         SET deleted = 0
         WHERE id = ?"
    );

    $stmt->bind_param("i", $id);

    $stmt->execute();
}

header("Location: trash.php");
exit();
?>