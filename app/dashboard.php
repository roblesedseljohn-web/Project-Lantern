<?php
session_start();
include 'includes/db.php';

if (!isset($_SESSION['username'])) {
    header("Location: auth.php");
    exit();
}

$username = $_SESSION['username'];

$currentFolderId = 0;
if (isset($_GET['folder_id'])) {
    $currentFolderId = intval($_GET['folder_id']);
}

$currentVirtual = "";
if (isset($_GET['virtual'])) {
    $currentVirtual = trim($_GET['virtual']);
}

$search = "";
if (isset($_GET['search'])) {
    $search = trim($_GET['search']);
}

$errorMessage = "";

if (isset($_GET['error'])) {
    if ($_GET['error'] == "folder_exists") {
        $errorMessage = "Folder already exists 📁";
    } elseif ($_GET['error'] == "empty_folder") {
        $errorMessage = "Folder name cannot be empty 📂";
    } elseif ($_GET['error'] == "file_exists") {
        $errorMessage = "A file with that name already exists in the destination 📄";
    }
}

function buildBreadcrumb($conn, $folderId, $username) {
    $parts = [];

    while ($folderId != 0) {
        $stmt = $conn->prepare(
            "SELECT * FROM folders WHERE id = ? AND username = ? LIMIT 1"
        );

        $stmt->bind_param("is", $folderId, $username);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows == 0) {
            break;
        }

        $folder = $result->fetch_assoc();

        array_unshift(
            $parts,
            "<a href='dashboard.php?folder_id=" . intval($folder['id']) . "' class='breadcrumb-link'>" .
            htmlspecialchars($folder['folder_name'], ENT_QUOTES, 'UTF-8') .
            "</a>"
        );

        $folderId = intval($folder['parent_id']);
    }

    return implode(" / ", $parts);
}

$virtualSQL = "";

if ($currentVirtual == "photos") {
    $virtualSQL = " AND filetype IN ('jpg','jpeg','png','gif','webp')";
} elseif ($currentVirtual == "documents") {
    $virtualSQL = " AND filetype IN ('pdf','doc','docx','xls','xlsx','ppt','pptx','txt')";
} elseif ($currentVirtual == "audios") {
    $virtualSQL = " AND filetype IN ('mp3','wav','flac')";
} elseif ($currentVirtual == "videos") {
    $virtualSQL = " AND filetype IN ('mp4','mkv','avi','mov')";
}

/*
|--------------------------------------------------------------------------
| FILE QUERY
|--------------------------------------------------------------------------
*/

if ($search != "") {
    if ($currentFolderId == 0 || $currentVirtual != "") {
        $query = "
            SELECT *
            FROM files
            WHERE username = ?
              AND deleted = 0
              AND filename LIKE ?
              $virtualSQL
            ORDER BY created_at DESC
        ";
        $stmt = $conn->prepare($query);
        $searchLike = "%" . $search . "%";
        $stmt->bind_param("ss", $username, $searchLike);
    } else {
        $query = "
            SELECT *
            FROM files
            WHERE username = ?
              AND deleted = 0
              AND folder_id = ?
              AND filename LIKE ?
              $virtualSQL
            ORDER BY created_at DESC
        ";
        $stmt = $conn->prepare($query);
        $searchLike = "%" . $search . "%";
        $stmt->bind_param("sis", $username, $currentFolderId, $searchLike);
    }
} else {
    if ($currentVirtual != "") {
        $query = "
            SELECT *
            FROM files
            WHERE username = ?
              AND deleted = 0
              $virtualSQL
            ORDER BY created_at DESC
        ";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $username);
    } else {
        $query = "
            SELECT *
            FROM files
            WHERE username = ?
              AND deleted = 0
              AND folder_id = ?
            ORDER BY created_at DESC
        ";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("si", $username, $currentFolderId);
    }
}

$stmt->execute();
$result = $stmt->get_result();

$fileCards = "";

while ($row = $result->fetch_assoc()) {

    $file = htmlspecialchars($row['filename'], ENT_QUOTES, 'UTF-8');
    $filePath = "./" . $row['filepath'];
    $extension = strtolower(trim($row['filetype']));
    $fileId = intval($row['id']);
    $openUrl = "download.php?id=" . $fileId;

    // Escape filename for JS
    $fileNameJs = htmlspecialchars($row['filename'], ENT_QUOTES, 'UTF-8');
    $fileNameJs = addslashes($fileNameJs);

    $filePathText = "Home";

    if ($row['folder_id'] != 0) {
        $folderId = intval($row['folder_id']);
        $pathNames = [];

        while ($folderId != 0) {
            $pathStmt = $conn->prepare("SELECT * FROM folders WHERE id = ? LIMIT 1");
            $pathStmt->bind_param("i", $folderId);
            $pathStmt->execute();
            $pathResult = $pathStmt->get_result();

            if ($pathResult->num_rows == 0) {
                break;
            }

            $pathFolder = $pathResult->fetch_assoc();
            array_unshift($pathNames, htmlspecialchars($pathFolder['folder_name'], ENT_QUOTES, 'UTF-8'));
            $folderId = intval($pathFolder['parent_id']);
        }
        $filePathText .= "/" . implode("/", $pathNames);
    }

    $fileCards .= "
<div class='card selectable-card'
     tabindex='0'
     data-id='{$fileId}'
     data-type='file'
     data-file-id='{$fileId}'
     oncontextmenu=\"cardRightClick(event, this, 'file', {$fileId})\"
     ondblclick=\"window.location='{$openUrl}'\">


        <div class='card-menu-wrapper'>
            <button class='menu-btn'>⋮</button>

            <div class='card-menu'>
                <a href='download.php?id={$fileId}'>Download</a>
                <a href='#'
                   onclick='openRenameFileModal({$fileId}, \"{$fileNameJs}\"); return false;'>Rename</a>
                <a href='#'
                   onclick='openMoveFileModal({$fileId}); return false;'>Move</a>
                <a href='#'
                   onclick='openCopyFileModal({$fileId}); return false;'>Copy</a>
                <a href='#'
                   onclick='deleteFile({$fileId}); return false;'>Delete</a>
            </div>
        </div>";

    if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
        $fileCards .= "<img src='{$filePath}' alt='{$file}'>";
    } else {
        $icon = "📄";
        if (in_array($extension, ['mp4', 'mkv', 'avi', 'mov'])) {
            $icon = "🎥";
        } elseif (in_array($extension, ['mp3', 'wav', 'flac'])) {
            $icon = "🎵";
        }
        $fileCards .= "<div class='file-icon'>{$icon}</div>";
    }

    $fileCards .= "
        <div class='filename'>{$file}</div>
        <div class='filepath'>📁 {$filePathText}</div>
    </div>";
}

$csrf_token = generateCsrfToken();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Project Lantern</title>

    <link
        rel="stylesheet"
        href="./assets/css/dashboard.css?v=FINAL2"
    >
</head>

<body>

<div class="topbar">

    <div class="title">
        Lantern Beta: Version 2.1.3
    </div>

    <div class="buttons">

        <a
            href="dashboard.php"
            title="Home"
        >
            ⌂
        </a>

        <a
            href="trash.php"
            title="Trash"
        >
            🗑
        </a>

        <a
            href="logout.php"
            title="Logout"
        >
            ⎋
        </a>

    </div>

</div>

<?php if ($errorMessage != ""): ?>

<div class="error-box">
    <?php echo $errorMessage; ?>
</div>

<?php endif; ?>

<form
    method="GET"
    class="search-bar"
>

    <input
        type="hidden"
        name="folder_id"
        value="<?php echo $currentFolderId; ?>"
    >

    <input
        type="text"
        name="search"
        placeholder="Search files..."
        value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>"
    >

    <button
        type="submit"
        title="Search"
    >
        ⌕
    </button>

</form>

<div class="path">

<?php

if ($search != "") {

    echo "Search Results 🔍";

} else {

    echo '<a href="dashboard.php" class="breadcrumb-link">Home</a>';

    if ($currentFolderId != 0) {

        echo " / " .
            buildBreadcrumb(
                $conn,
                $currentFolderId,
                $username
            );
    }
}

?>

</div>

<?php if (
    $currentFolderId == 0 &&
    $currentVirtual == "" &&
    $search == ""
): ?>

<div class="categories">

    <a
        href="dashboard.php?virtual=photos"
        class="virtual-link"
    >
        <div class="card">
            <h2>📷</h2>
            <h3>Photos</h3>
        </div>
    </a>

    <a
        href="dashboard.php?virtual=documents"
        class="virtual-link"
    >
        <div class="card">
            <h2>📄</h2>
            <h3>Documents</h3>
        </div>
    </a>

    <a
        href="dashboard.php?virtual=audios"
        class="virtual-link"
    >
        <div class="card">
            <h2>🎵</h2>
            <h3>Audios</h3>
        </div>
    </a>

    <a
        href="dashboard.php?virtual=videos"
        class="virtual-link"
    >
        <div class="card">
            <h2>🎥</h2>
            <h3>Videos</h3>
        </div>
    </a>

</div>

<?php endif; ?>


<?php if (
    $currentVirtual == "" &&
    $search == ""
): ?>

<div class="section-header">

    <h2>
        Folders 📁
    </h2>

    <div class="folder-actions">

        <button
            type="button"
            class="add-btn"
            id="folderPlusBtn"
            aria-label="Folder actions"
        >
            +
        </button>

        <div
            class="folder-dropdown"
            id="folderDropdown"
        >

            <button
                type="button"
                onclick="openFolderModal()"
            >
                📁 Create Folder
            </button>

            <button
                type="button"
                id="uploadFolderButton"
            >
                📂 Upload Folder
            </button>

        </div>

    </div>

</div>

<div
    class="drop-section-wrapper"
    id="foldersDropWrapper"
>

    <div
        class="grid drop-zone folders-drop-zone"
        id="foldersDropZone"
        data-drop-type="folders"
    >

<?php

$folderStmt = $conn->prepare(
    "SELECT *
     FROM folders
     WHERE username = ?
       AND deleted = 0
       AND parent_id = ?
     ORDER BY created_at DESC"
);

$folderStmt->bind_param(
    "si",
    $username,
    $currentFolderId
);

$folderStmt->execute();

$folderResult = $folderStmt->get_result();

if ($folderResult->num_rows == 0) {

    echo "
        <div class='card empty-card'>
            <h3>No folders yet 📂</h3>
        </div>
    ";

} else {

  while ($folder = $folderResult->fetch_assoc()) {

    $safeFolderName = htmlspecialchars(
        $folder['folder_name'],
        ENT_QUOTES,
        'UTF-8'
    );

    $folderId = intval($folder['id']);

    // Escape folder name for use inside JavaScript single‑quoted strings
    $folderNameJs = htmlspecialchars(
        $folder['folder_name'],
        ENT_QUOTES,
        'UTF-8'
    );
    $folderNameJs = addslashes($folderNameJs);   // escape backslashes and quotes

   echo "
<div class='card selectable-card'
     tabindex='0'
     data-id='{$folderId}'
     data-type='folder'
     data-folder-id='{$folderId}'
     oncontextmenu=\"cardRightClick(event, this, 'folder', {$folderId})\"
     ondblclick=\"window.location='dashboard.php?folder_id={$folderId}'\">

        <div class='card-menu-wrapper'>

            <button class='menu-btn'>
                ⋮
            </button>

            <div class='card-menu'>

                <a href='#'
                   onclick='openRenameFolderModal({$folderId}, \"{$folderNameJs}\"); return false;'>
                   Rename
                </a>

                <a href='#'
                   onclick='openMoveFolderModal({$folderId}); return false;'>
                   Move
                </a>

                <a href='#'
                   onclick='openCopyFolderModal({$folderId}); return false;'>
                   Copy
                </a>

                <a href='#'
                   onclick='deleteFolder({$folderId}); return false;'>
                   Delete
                </a>

            </div>

        </div>

        <div class='folder-icon'>
            📁
        </div>

        <div class='filename'>
            {$safeFolderName}
        </div>

    </div>";
}
}

?>

    </div>

</div>

<?php endif; ?>


<div class="section-header">

<h2>

<?php

if ($search != "") {

    echo "Search Results 🔍";

} elseif ($currentVirtual == "photos") {

    echo "Photos 📷";

} elseif ($currentVirtual == "documents") {

    echo "Documents 📄";

} elseif ($currentVirtual == "audios") {

    echo "Audios 🎵";

} elseif ($currentVirtual == "videos") {

    echo "Videos 🎥";

} else {

    echo "Files 📄";
}

?>

</h2>

<?php if ($search == ""): ?>

<button
    type="button"
    onclick="openUploadModal()"
    class="add-btn"
>
    +
</button>

<?php endif; ?>

</div>

<div
    class="drop-section-wrapper"
    id="filesDropWrapper"
>

    <div
        class="grid drop-zone files-drop-zone"
        id="filesDropZone"
        data-drop-type="files"
    >

<?php

if ($fileCards == "") {

    echo "
        <div class='card empty-card'>
            <h3>No files found 📂</h3>
        </div>
    ";

} else {

    echo $fileCards;
}

?>

    </div>

</div>


<!-- CREATE FOLDER MODAL -->

<div
    id="folderModal"
    class="modal-overlay"
>

    <div class="modal">

        <h2>
            Create Folder 📁
        </h2>

        <form
            action="create_folder.php"
            method="POST"
        >

            <input
                type="hidden"
                name="csrf_token"
                value="<?php echo $csrf_token; ?>"
            >

            <input
                type="hidden"
                name="parent_id"
                value="<?php echo $currentFolderId; ?>"
            >

            <input
                type="text"
                name="folder_name"
                placeholder="Folder Name"
                required
            >

            <button
                type="submit"
                class="modal-submit"
            >
                Create
            </button>

            <button
                type="button"
                onclick="closeFolderModal()"
                class="modal-close"
            >
                Close
            </button>

        </form>

    </div>

</div>


<!-- FILE UPLOAD MODAL -->

<div
    id="uploadModal"
    class="modal-overlay"
>

    <div class="modal">

        <h2>
            Upload File 📄
        </h2>

        <form
            id="uploadForm"
            onsubmit="return false;"
        >

            <input
                type="hidden"
                id="uploadFolderId"
                value="<?php echo $currentFolderId; ?>"
            >

            <div class="custom-file-input">

                <input
                    type="file"
                    id="fileInput"
                    multiple
                >

            </div>

            <div
                class="selected-file-name"
                id="selectedFileName"
            >
                No file selected
            </div>

            <div class="upload-buttons">

                <button
                    type="button"
                    class="modal-submit"
                    id="uploadBtn"
                >
                    Upload
                </button>

                <button
                    type="button"
                    onclick="closeUploadModal()"
                    class="modal-close"
                >
                    Close
                </button>

            </div>

        </form>

    </div>

</div>


<!-- UPLOAD LOADING OVERLAY -->

<div
    id="uploadLoadingOverlay"
    class="upload-loading-overlay"
>

    <div class="upload-loading-box">

        <div class="upload-spinner"></div>

        <h2>
            Uploading...
        </h2>

        <div class="upload-progress-bar">

            <div
                class="upload-progress-fill"
                id="uploadProgressFill"
            ></div>

        </div>

        <div
            class="upload-progress-text"
            id="uploadProgressText"
        >
            Preparing upload...
        </div>

    </div>

</div>


<script>

window.currentFolderId =
    <?php echo intval($currentFolderId); ?>;

window.csrf_token =
    <?php echo json_encode($csrf_token); ?>;

</script>

<input type="file" id="folderPicker" webkitdirectory directory multiple 
       style="position:absolute; left:-9999px; top:-9999px; width:1px; height:1px; opacity:0;">

<script src="./assets/js/dashboard.js?v=FINAL2"></script>
<!-- MOVE FOLDER MODAL -->
<div id="moveFolderModal" class="modal-overlay">
    <div class="modal">
        <h2>📁 Move Folder</h2>
        <form id="moveFolderForm" onsubmit="return false;">
            <input type="hidden" name="csrf_token" id="moveCsrfToken" value="<?php echo generateCsrfToken(); ?>">
            <input type="hidden" name="folder_id" id="moveFolderId">
            <label for="moveTargetSelect" style="display:block; margin-bottom:8px;">Select destination:</label>
            <select name="parent_id" id="moveTargetSelect" required>
                <!-- Options will be loaded dynamically -->
                <option value="">Loading folders…</option>
            </select>
            <div style="margin-top:20px; display:flex; gap:10px;">
                <button type="submit" class="modal-submit" onclick="submitMoveFolder()">Move</button>
                <button type="button" class="modal-close" onclick="closeMoveFolderModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- MOVE FILE MODAL -->
<div id="moveFileModal" class="modal-overlay">
    <div class="modal">
        <h2>📄 Move File</h2>
        <form id="moveFileForm" onsubmit="return false;">
            <input type="hidden" name="csrf_token" id="moveFileCsrfToken" value="<?php echo generateCsrfToken(); ?>">
            <input type="hidden" name="file_id" id="moveFileId">
            <label for="moveFileTargetSelect" style="display:block; margin-bottom:8px;">Select destination:</label>
            <select name="folder_id" id="moveFileTargetSelect" required>
                <!-- Options loaded dynamically -->
                <option value="">Loading folders…</option>
            </select>
            <div style="margin-top:20px; display:flex; gap:10px;">
                <button type="submit" class="modal-submit" onclick="submitMoveFile()">Move</button>
                <button type="button" class="modal-close" onclick="closeMoveFileModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- COPY FOLDER MODAL -->
<div id="copyFolderModal" class="modal-overlay">
    <div class="modal">
        <h2>📁 Copy Folder</h2>
        <form id="copyFolderForm" onsubmit="return false;">
            <input type="hidden" name="csrf_token" id="copyFolderCsrfToken" value="<?php echo generateCsrfToken(); ?>">
            <input type="hidden" name="folder_id" id="copyFolderId">
            <label for="copyTargetSelect" style="display:block; margin-bottom:8px;">Copy to:</label>
            <select name="parent_id" id="copyTargetSelect" required>
                <option value="">Loading folders…</option>
            </select>
            <div style="margin-top:20px; display:flex; gap:10px;">
                <button type="submit" class="modal-submit" onclick="submitCopyFolder()">Copy</button>
                <button type="button" class="modal-close" onclick="closeCopyFolderModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- COPY FILE MODAL -->
<div id="copyFileModal" class="modal-overlay">
    <div class="modal">
        <h2>📄 Copy File</h2>
        <form id="copyFileForm" onsubmit="return false;">
            <input type="hidden" name="csrf_token" id="copyFileCsrfToken" value="<?php echo generateCsrfToken(); ?>">
            <input type="hidden" name="file_id" id="copyFileId">
            <label for="copyFileTargetSelect" style="display:block; margin-bottom:8px;">Copy to:</label>
            <select name="folder_id" id="copyFileTargetSelect" required>
                <option value="">Loading folders…</option>
            </select>
            <div style="margin-top:20px; display:flex; gap:10px;">
                <button type="submit" class="modal-submit" onclick="submitCopyFile()">Copy</button>
                <button type="button" class="modal-close" onclick="closeCopyFileModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- RENAME FOLDER MODAL -->
<div id="renameFolderModal" class="modal-overlay">
    <div class="modal">
        <h2>✏️ Rename Folder</h2>
        <form id="renameFolderForm" onsubmit="return false;">
            <input type="hidden" name="csrf_token" id="renameFolderCsrfToken" value="<?php echo generateCsrfToken(); ?>">
            <input type="hidden" name="folder_id" id="renameFolderId">
            <input type="text" name="folder_name" id="renameFolderName" placeholder="New folder name" required>
            <div style="margin-top:20px; display:flex; gap:10px;">
                <button type="submit" class="modal-submit" onclick="submitRenameFolder()">Rename</button>
                <button type="button" class="modal-close" onclick="closeRenameFolderModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- RENAME FILE MODAL -->
<div id="renameFileModal" class="modal-overlay">
    <div class="modal">
        <h2>✏️ Rename File</h2>
        <p id="renameFileCurrent" style="color:#94a3b8; margin-bottom:15px;"></p>
        <form id="renameFileForm" onsubmit="return false;">
            <input type="hidden" name="csrf_token" id="renameFileCsrfToken" value="<?php echo generateCsrfToken(); ?>">
            <input type="hidden" name="file_id" id="renameFileId">
            <input type="text" name="new_name" id="renameFileInput" placeholder="Enter new filename" required>
            <div style="margin-top:20px; display:flex; gap:10px;">
                <button type="submit" class="modal-submit" onclick="submitRenameFile()">Rename</button>
                <button type="button" class="modal-close" onclick="closeRenameFileModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- DELETE CONFIRMATION MODAL -->
<div id="deleteConfirmModal" class="modal-overlay">
    <div class="modal-box">
        <h2>⚠️ Delete Confirmation</h2>
        <p id="deleteConfirmMessage">Are you sure you want to delete this item?</p>
        <div class="confirm-buttons">
            <button class="btn-danger" id="deleteConfirmYes">Yes, Delete</button>
            <button class="btn-secondary" id="deleteConfirmNo">Cancel</button>
        </div>
    </div>
</div>

<!-- BULK CONTEXT MENU -->
<div id="bulkContextMenu" class="bulk-context-menu" style="display:none;">
    <div class="bulk-menu-header"><span id="bulkCount">0</span> items selected</div>
    <a href="#" onclick="bulkDelete(); return false;">🗑️ Delete Selected</a>
    <a href="#" onclick="bulkMove(); return false;">📁 Move Selected</a>
    <a href="#" onclick="bulkCopy(); return false;">📄 Copy Selected</a>
    <a href="#" onclick="bulkDownload(); return false;">⬇️ Download Selected</a>
</div>

</body>
</html>