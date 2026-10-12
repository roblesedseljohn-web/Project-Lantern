<?php

session_start();

include 'includes/db.php';

header('Content-Type: application/json');


/*
|--------------------------------------------------------------------------
| AUTHENTICATION
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['username'])) {

    http_response_code(401);

    echo json_encode([
        "success" => false,
        "message" => "Unauthorized"
    ]);

    exit();
}

$username = $_SESSION['username'];


/*
|--------------------------------------------------------------------------
| CSRF
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    http_response_code(405);

    echo json_encode([
        "success" => false,
        "message" => "Method not allowed"
    ]);

    exit();
}


if (
    !isset($_POST['csrf_token']) ||
    !verifyCsrfToken($_POST['csrf_token'])
) {

    http_response_code(403);

    echo json_encode([
        "success" => false,
        "message" => "Invalid CSRF token"
    ]);

    exit();
}


/*
|--------------------------------------------------------------------------
| CURRENT FOLDER
|--------------------------------------------------------------------------
*/

$currentFolderId = 0;

if (isset($_POST['folder_id'])) {

    $currentFolderId =
        intval($_POST['folder_id']);
}


/*
|--------------------------------------------------------------------------
| VERIFY CURRENT FOLDER BELONGS TO USER
|--------------------------------------------------------------------------
*/

if ($currentFolderId > 0) {

    $folderCheck =
        $conn->prepare(
            "SELECT id
             FROM folders
             WHERE id = ?
               AND username = ?
               AND deleted = 0
             LIMIT 1"
        );

    $folderCheck->bind_param(
        "is",
        $currentFolderId,
        $username
    );

    $folderCheck->execute();

    $folderResult =
        $folderCheck->get_result();

    if ($folderResult->num_rows === 0) {

        echo json_encode([
            "success" => false,
            "message" => "Invalid destination folder"
        ]);

        exit();
    }
}


/*
|--------------------------------------------------------------------------
| RELATIVE PATH
|--------------------------------------------------------------------------
*/

$relativePath = "";

if (isset($_POST['relative_path'])) {

    $relativePath =
        trim(
            $_POST['relative_path']
        );
}


/*
|--------------------------------------------------------------------------
| FILE
|--------------------------------------------------------------------------
*/

if (!isset($_FILES['file'])) {

    echo json_encode([
        "success" => false,
        "message" => "No file uploaded"
    ]);

    exit();
}


$file = $_FILES['file'];


if ($file['error'] !== UPLOAD_ERR_OK) {

    echo json_encode([
        "success" => false,
        "message" => "Upload failed with error code " .
            intval($file['error'])
    ]);

    exit();
}


/*
|--------------------------------------------------------------------------
| SIZE LIMIT
|--------------------------------------------------------------------------
*/

$maxFileSize = 10 * 1024 * 1024 * 1024; // 10 GB

if ($file['size'] > $maxFileSize) {

    echo json_encode([
        "success" => false,
        "message" => "File too large (max 10 GB)"
    ]);

    exit();
}


/*
|--------------------------------------------------------------------------
| CREATE / FIND FOLDER
|--------------------------------------------------------------------------
*/

function getOrCreateFolder(
    $conn,
    $folderName,
    $parentId,
    $username
) {

    $folderName =
        trim($folderName);


    if ($folderName === "") {
        return $parentId;
    }


    /*
     * Prevent path traversal.
     */

    if (
        $folderName === "." ||
        $folderName === ".." ||
        strpos($folderName, "/") !== false ||
        strpos($folderName, "\\") !== false
    ) {

        return $parentId;
    }


    $checkStmt =
        $conn->prepare(
            "SELECT id
             FROM folders
             WHERE folder_name = ?
               AND parent_id = ?
               AND username = ?
               AND deleted = 0
             LIMIT 1"
        );

    $checkStmt->bind_param(
        "sis",
        $folderName,
        $parentId,
        $username
    );

    $checkStmt->execute();

    $result =
        $checkStmt->get_result();


    if ($result->num_rows > 0) {

        $existing =
            $result->fetch_assoc();

        return intval(
            $existing['id']
        );
    }


    $insertStmt =
        $conn->prepare(
            "INSERT INTO folders
            (
                folder_name,
                parent_id,
                username,
                deleted
            )
            VALUES (?, ?, ?, 0)"
        );

    $insertStmt->bind_param(
        "sis",
        $folderName,
        $parentId,
        $username
    );

    $insertStmt->execute();


    return $conn->insert_id;
}


/*
|--------------------------------------------------------------------------
| BUILD DESTINATION FOLDER
|--------------------------------------------------------------------------
*/

$targetFolderId =
    $currentFolderId;


if ($relativePath !== "") {

    $relativePath =
        str_replace(
            "\\",
            "/",
            $relativePath
        );


    $parts =
        explode(
            "/",
            trim(
                $relativePath,
                "/"
            )
        );


    /*
     * The last element is the filename.
     */

    array_pop($parts);


    foreach ($parts as $part) {

        $part =
            trim($part);


        if ($part === "") {
            continue;
        }


        if (
            $part === "." ||
            $part === ".."
        ) {
            continue;
        }


        $targetFolderId =
            getOrCreateFolder(
                $conn,
                $part,
                $targetFolderId,
                $username
            );
    }
}


/*
|--------------------------------------------------------------------------
| ORIGINAL FILE NAME
|--------------------------------------------------------------------------
*/

$originalName =
    basename(
        $file['name']
    );


if ($originalName === "") {

    echo json_encode([
        "success" => false,
        "message" => "Invalid filename"
    ]);

    exit();
}


/*
|--------------------------------------------------------------------------
| FILE EXTENSION
|--------------------------------------------------------------------------
*/

$extension =
    strtolower(
        pathinfo(
            $originalName,
            PATHINFO_EXTENSION
        )
    );


/*
|--------------------------------------------------------------------------
| UPLOAD DIRECTORY
|--------------------------------------------------------------------------
*/

$uploadDir =
    "uploads/";


if (!is_dir($uploadDir)) {

    if (
        !mkdir(
            $uploadDir,
            0777,
            true
        )
    ) {

        echo json_encode([
            "success" => false,
            "message" => "Could not create upload directory"
        ]);

        exit();
    }
}


/*
|--------------------------------------------------------------------------
| UNIQUE SERVER FILENAME
|--------------------------------------------------------------------------
*/

$newFilename =
    time() .
    "_" .
    uniqid(
        "",
        true
    ) .
    "_" .
    $originalName;


$targetPath =
    $uploadDir .
    $newFilename;


/*
|--------------------------------------------------------------------------
| MOVE FILE
|--------------------------------------------------------------------------
*/

if (
    !move_uploaded_file(
        $file['tmp_name'],
        $targetPath
    )
) {

    echo json_encode([
        "success" => false,
        "message" => "Failed to move uploaded file"
    ]);

    exit();
}


/*
|--------------------------------------------------------------------------
| DATABASE RECORD
|--------------------------------------------------------------------------
*/

$stmt =
    $conn->prepare(
        "INSERT INTO files
        (
            username,
            filename,
            filepath,
            filetype,
            folder_id,
            deleted
        )
        VALUES (?, ?, ?, ?, ?, 0)"
    );


$stmt->bind_param(
    "ssssi",
    $username,
    $originalName,
    $targetPath,
    $extension,
    $targetFolderId
);


if (!$stmt->execute()) {

    /*
     * Remove uploaded physical file
     * if database insertion fails.
     */

    if (is_file($targetPath)) {
        @unlink($targetPath);
    }


    echo json_encode([
        "success" => false,
        "message" => "Failed to save file metadata"
    ]);

    exit();
}


/*
|--------------------------------------------------------------------------
| SUCCESS
|--------------------------------------------------------------------------
*/

echo json_encode([
    "success" => true,
    "filename" => $originalName,
    "relative_path" => $relativePath,
    "folder_id" => $targetFolderId
]);

exit();

?>