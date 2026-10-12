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
| REQUEST METHOD
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


/*
|--------------------------------------------------------------------------
| CSRF
|--------------------------------------------------------------------------
*/

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
| VERIFY DESTINATION FOLDER
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
| FILES
|--------------------------------------------------------------------------
*/

if (
    !isset($_FILES['files']) ||
    !is_array($_FILES['files']['name'])
) {

    echo json_encode([
        "success" => false,
        "message" => "No folder files received"
    ]);

    exit();
}


/*
|--------------------------------------------------------------------------
| RELATIVE PATHS
|--------------------------------------------------------------------------
*/

if (
    !isset($_POST['relative_paths']) ||
    !is_array($_POST['relative_paths'])
) {

    echo json_encode([
        "success" => false,
        "message" => "Missing folder paths"
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
| NORMALIZE PATH
|--------------------------------------------------------------------------
*/

function normalizeRelativePath(
    $path
) {

    $path =
        str_replace(
            "\\",
            "/",
            $path
        );


    $path =
        preg_replace(
            '#/+#',
            '/',
            $path
        );


    $path =
        trim(
            $path,
            "/"
        );


    $parts =
        explode(
            "/",
            $path
        );


    $cleanParts = [];


    foreach ($parts as $part) {

        $part =
            trim($part);


        if (
            $part === "" ||
            $part === "." ||
            $part === ".."
        ) {

            continue;
        }


        $cleanParts[] =
            $part;
    }


    return implode(
        "/",
        $cleanParts
    );
}


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
| LIMITS
|--------------------------------------------------------------------------
*/

$maxFileSize =
    100 * 1024 * 1024;


/*
|--------------------------------------------------------------------------
| FILE COUNT
|--------------------------------------------------------------------------
*/

$totalFiles =
    count(
        $_FILES['files']['name']
    );


$totalPaths =
    count(
        $_POST['relative_paths']
    );


if (
    $totalFiles === 0 ||
    $totalPaths === 0
) {

    echo json_encode([
        "success" => false,
        "message" => "The folder contains no files"
    ]);

    exit();
}


if (
    $totalFiles !== $totalPaths
) {

    echo json_encode([
        "success" => false,
        "message" => "File/path count mismatch"
    ]);

    exit();
}


/*
|--------------------------------------------------------------------------
| PROCESS FILES
|--------------------------------------------------------------------------
*/

$uploaded = 0;

$failed = 0;

$createdFolders = [];


for (
    $i = 0;
    $i < $totalFiles;
    $i++
) {

    $error =
        intval(
            $_FILES['files']['error'][$i]
        );


    if (
        $error !== UPLOAD_ERR_OK
    ) {

        $failed++;

        continue;
    }


    $tmpName =
        $_FILES['files']['tmp_name'][$i];


    $fileSize =
        intval(
            $_FILES['files']['size'][$i]
        );


    if (
        $fileSize > $maxFileSize
    ) {

        $failed++;

        continue;
    }


    if (
        !is_uploaded_file($tmpName)
    ) {

        $failed++;

        continue;
    }


    /*
     * Get and normalize relative path.
     */

    $relativePath =
        normalizeRelativePath(
            $_POST['relative_paths'][$i]
        );


    if ($relativePath === "") {

        $originalName =
            basename(
                $_FILES['files']['name'][$i]
            );

    } else {

        $originalName =
            basename(
                $relativePath
            );
    }


    if ($originalName === "") {

        $failed++;

        continue;
    }


    /*
     * Get directory portion.
     */

    $directoryPath =
        dirname(
            $relativePath
        );


    if (
        $directoryPath === "." ||
        $directoryPath === "/"
    ) {

        $directoryPath = "";
    }


    /*
     * Build destination folder.
     */

    $targetFolderId =
        $currentFolderId;


    if ($directoryPath !== "") {

        $folderParts =
            explode(
                "/",
                $directoryPath
            );


        foreach (
            $folderParts as $folderName
        ) {

            $folderName =
                trim($folderName);


            if (
                $folderName === "" ||
                $folderName === "." ||
                $folderName === ".."
            ) {

                continue;
            }


            $targetFolderId =
                getOrCreateFolder(
                    $conn,
                    $folderName,
                    $targetFolderId,
                    $username
                );


            $createdFolders[] =
                $folderName;
        }
    }


    /*
     * Original extension.
     */

    $extension =
        strtolower(
            pathinfo(
                $originalName,
                PATHINFO_EXTENSION
            )
        );


    /*
     * Generate unique physical filename.
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
     * Move physical file.
     */

    if (
        !move_uploaded_file(
            $tmpName,
            $targetPath
        )
    ) {

        $failed++;

        continue;
    }


    /*
     * Insert database record.
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
         * Database failure:
         * remove physical file.
         */

        if (
            is_file($targetPath)
        ) {

            @unlink(
                $targetPath
            );
        }


        $failed++;

        continue;
    }


    $uploaded++;
}


/*
|--------------------------------------------------------------------------
| RESULT
|--------------------------------------------------------------------------
*/

if ($uploaded > 0) {

    echo json_encode([
        "success" => true,
        "uploaded" => $uploaded,
        "failed" => $failed
    ]);

    exit();
}


echo json_encode([
    "success" => false,
    "message" => "No files were uploaded",
    "uploaded" => 0,
    "failed" => $failed
]);

exit();

?>