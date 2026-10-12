<?php
session_start();
include 'includes/db.php';

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];
$currentFolderId = 0;
if (isset($_GET['folder_id'])) {
    $currentFolderId = intval($_GET['folder_id']);
}

$csrf_token = generateCsrfToken();

$showSuccessModal = isset($_GET['emptied']) && $_GET['emptied'] == '1';

$breadcrumb = "Trash";
$pathParts = [];
if ($currentFolderId != 0) {
    $folderId = $currentFolderId;
    while ($folderId != 0) {
        $stmt = $conn->prepare("SELECT * FROM folders WHERE id = ? AND username = ?");
        $stmt->bind_param("is", $folderId, $username);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows == 0) break;
        $folder = $result->fetch_assoc();
        array_unshift($pathParts, "<a href='trash.php?folder_id=".$folder['id']."' class='breadcrumb-link'>" . htmlspecialchars($folder['folder_name'], ENT_QUOTES, 'UTF-8') . "</a>");
        $folderId = $folder['parent_id'];
    }
    $breadcrumb .= " / " . implode(" / ", $pathParts);
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Trash - Lantern</title>
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{background:#0f172a;color:white;font-family:Arial,sans-serif;min-height:100vh;padding:30px;}
.topbar{display:flex;justify-content:space-between;align-items:center;margin-bottom:30px;}
.title{font-size:30px;font-weight:700;letter-spacing:0.5px;}
.buttons{display:flex;align-items:center;gap:10px;}
.buttons a{width:42px;height:42px;display:flex;align-items:center;justify-content:center;text-decoration:none;border-radius:14px;background:rgba(255,255,255,0.05);backdrop-filter:blur(10px);border:1px solid rgba(255,255,255,0.06);color:#cbd5e1;font-size:17px;transition:transform 0.2s ease,background 0.2s ease,color 0.2s ease,box-shadow 0.2s ease;}
.buttons a:hover{transform:translateY(-2px);background:rgba(59,130,246,0.15);color:white;box-shadow:0 0 20px rgba(59,130,246,0.2);}
.path{margin-bottom:30px;font-size:28px;font-weight:700;}
.breadcrumb-link{color:white;text-decoration:none;transition:opacity 0.2s ease;}
.breadcrumb-link:hover{opacity:0.7;}
.section-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:22px;}
.section-header h2{font-size:22px;font-weight:700;}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:22px;}
.card{position:relative;background:#1e293b;border-radius:24px;padding:24px;transition:transform 0.2s ease,box-shadow 0.2s ease,border 0.2s ease;border:1px solid transparent;cursor:pointer;overflow:hidden;}
.card:hover{transform:translateY(-3px);box-shadow:0 0 25px rgba(59,130,246,0.12);}
.card-menu-wrapper{position:absolute;top:14px;right:14px;}
.menu-btn{width:32px;height:32px;border:none;border-radius:10px;background:transparent;color:#94a3b8;font-size:18px;cursor:pointer;transition:background 0.2s ease,color 0.2s ease;}
.menu-btn:hover{background:rgba(255,255,255,0.06);color:white;}
.card-menu{display:none;position:absolute;top:40px;right:0;width:190px;background:#0f172a;border:1px solid rgba(255,255,255,0.06);border-radius:18px;overflow:hidden;z-index:999;box-shadow:0 0 25px rgba(0,0,0,0.35);}
.card-menu a{display:block;padding:13px 16px;text-decoration:none;color:#e2e8f0;font-size:14px;transition:background 0.2s ease;}
.card-menu a:hover{background:rgba(255,255,255,0.04);}
.folder-icon{font-size:80px;margin-top:15px;margin-bottom:12px;text-align:center;}
.file-icon{width:100%;height:170px;display:flex;align-items:center;justify-content:center;font-size:70px;background:#0f172a;border-radius:18px;margin-top:15px;margin-bottom:15px;}
.filename{text-align:center;font-size:20px;font-weight:600;color:white;word-break:break-word;margin-bottom:8px;}
.empty{background:#1e293b;border-radius:24px;padding:40px;text-align:center;color:#94a3b8;font-size:18px;max-width:320px;}

.empty-trash-btn {
    background: #dc2626;
    color: white;
    border: none;
    border-radius: 12px;
    padding: 10px 20px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.2s;
}
.empty-trash-btn:hover {
    background: #b91c1c;
}

/* Centered modal overlays (shared) */
.modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.65);
    backdrop-filter: blur(6px);
    z-index: 999999;
    justify-content: center;
    align-items: center;
}
.modal-overlay.show {
    display: flex;
}

/* Generic modal box */
.modal-box {
    background: #1e293b;
    padding: 40px;
    border-radius: 24px;
    text-align: center;
    max-width: 420px;
    box-shadow: 0 0 30px rgba(0,0,0,0.5);
    color: white;
}
.modal-box h2 {
    margin-bottom: 15px;
    font-size: 22px;
}
.modal-box p {
    margin-bottom: 25px;
    color: #cbd5e1;
    line-height: 1.5;
}

/* Confirmation modal buttons */
.confirm-buttons {
    display: flex;
    gap: 12px;
    justify-content: center;
}
.btn-danger {
    background: #dc2626;
    color: white;
    border: none;
    border-radius: 10px;
    padding: 10px 24px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.2s;
}
.btn-danger:hover {
    background: #b91c1c;
}
.btn-secondary {
    background: #475569;
    color: white;
    border: none;
    border-radius: 10px;
    padding: 10px 24px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.2s;
}
.btn-secondary:hover {
    background: #334155;
}

/* Success modal specific */
.success-modal h2 {
    color: #4ade80;
}
.btn-success {
    background: #16a34a;
    color: white;
    border: none;
    border-radius: 10px;
    padding: 10px 24px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.2s;
    margin-top: 20px;
}
.btn-success:hover {
    background: #15803d;
}
</style>
</head>
<body>

<!-- CONFIRMATION MODAL (appears when Empty Trash is clicked) -->
<div id="confirmModal" class="modal-overlay">
    <div class="modal-box">
        <h2>⚠️ Empty Trash?</h2>
        <p>Are you sure you want to permanently delete <strong>ALL</strong> items in the trash?<br>This action cannot be undone.</p>
        <div class="confirm-buttons">
            <button class="btn-danger" id="confirmYes">Yes, Delete All</button>
            <button class="btn-secondary" id="confirmNo">Cancel</button>
        </div>
    </div>
</div>

<!-- SUCCESS MODAL (shown after emptying) -->
<div id="successModal" class="modal-overlay">
    <div class="modal-box success-modal">
        <h2>✅ Trash emptied successfully</h2>
        <p>All items have been permanently deleted.</p>
        <button class="btn-success" onclick="closeSuccessModal()">OK</button>
    </div>
</div>

<div class="topbar">
    <div class="title">Lantern</div>
    <div class="buttons">
        <a href="dashboard.php" title="Home">⌂</a>
        <a href="trash.php" title="Trash">🗑</a>
        <a href="logout.php" title="Logout">⎋</a>
    </div>
</div>
<div class="path"><?php echo $breadcrumb; ?></div>

<div class="section-header">
    <h2>Deleted Items</h2>
    <!-- Form for emptying trash -->
    <form id="emptyTrashForm" method="POST" action="empty_trash.php">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        <button type="button" class="empty-trash-btn" id="emptyTrashBtn">🗑️ Empty Trash</button>
    </form>
</div>

<div class="grid">
<?php
$hasItems = false;

// Deleted folders
$folderStmt = $conn->prepare("SELECT * FROM folders WHERE username = ? AND deleted = 1 AND parent_id = ? ORDER BY created_at DESC");
$folderStmt->bind_param("si", $username, $currentFolderId);
$folderStmt->execute();
$folderResult = $folderStmt->get_result();
while ($folder = $folderResult->fetch_assoc()) {
    $hasItems = true;
    $safeName = htmlspecialchars($folder['folder_name'], ENT_QUOTES, 'UTF-8');
    echo "
    <div class='card'>
        <div class='card-menu-wrapper'>
            <button class='menu-btn'>⋮</button>
            <div class='card-menu'>
                <a href='trash.php?folder_id=".$folder['id']."'>Open</a>
                <a href='restore_folder.php?id=".$folder['id']."'>Restore</a>
                <form method='POST' action='permanent_delete_folder.php' onsubmit='return confirm(&quot;Permanently delete this folder and its contents?&quot;)' style='display:inline'><input type='hidden' name='id' value='".$folder['id']."'><input type='hidden' name='csrf_token' value='".$csrf_token."'><button type='submit'>Delete Forever</button></form>
            </div>
        </div>
        <div class='folder-icon'>📁</div>
        <div class='filename'>$safeName</div>
    </div>";
}

// Deleted files
$fileStmt = $conn->prepare("SELECT * FROM files WHERE username = ? AND deleted = 1 AND folder_id = ? ORDER BY created_at DESC");
$fileStmt->bind_param("si", $username, $currentFolderId);
$fileStmt->execute();
$fileResult = $fileStmt->get_result();
while ($file = $fileResult->fetch_assoc()) {
    $hasItems = true;
    $safeFileName = htmlspecialchars($file['filename'], ENT_QUOTES, 'UTF-8');
    echo "
    <div class='card'>
        <div class='card-menu-wrapper'>
            <button class='menu-btn'>⋮</button>
            <div class='card-menu'>
                <a href='restore.php?id=".$file['id']."'>Restore</a>
                <form method='POST' action='permanent_delete.php' onsubmit='return confirm(&quot;Permanently delete this file?&quot;)' style='display:inline'><input type='hidden' name='id' value='".$file['id']."'><input type='hidden' name='csrf_token' value='".$csrf_token."'><button type='submit'>Delete Forever</button></form>
            </div>
        </div>
        <div class='file-icon'>📄</div>
        <div class='filename'>$safeFileName</div>
    </div>";
}

if (!$hasItems) {
    echo "<div class='empty'>Trash is empty 🗑️</div>";
}
?>
</div>

<script>
// --- Confirmation Modal Logic ---
const emptyBtn = document.getElementById('emptyTrashBtn');
const confirmModal = document.getElementById('confirmModal');
const confirmYes = document.getElementById('confirmYes');
const confirmNo = document.getElementById('confirmNo');
const emptyForm = document.getElementById('emptyTrashForm');

emptyBtn.addEventListener('click', () => {
    confirmModal.classList.add('show');
});

confirmYes.addEventListener('click', () => {
    confirmModal.classList.remove('show');
    emptyForm.submit();   // Submit the form to empty_trash.php
});

confirmNo.addEventListener('click', () => {
    confirmModal.classList.remove('show');
});

// Close confirmation modal when clicking outside the box
confirmModal.addEventListener('click', function(e) {
    if (e.target === confirmModal) {
        confirmModal.classList.remove('show');
    }
});

// --- Success Modal Logic ---
<?php if ($showSuccessModal): ?>
document.getElementById('successModal').classList.add('show');
// Auto-close after 3 seconds (optional)
setTimeout(() => {
    document.getElementById('successModal').classList.remove('show');
}, 3000);
<?php endif; ?>

function closeSuccessModal() {
    document.getElementById('successModal').classList.remove('show');
}
document.getElementById('successModal').addEventListener('click', function(e) {
    if (e.target === this) closeSuccessModal();
});

// --- Card menu logic (unchanged) ---
const menus = document.querySelectorAll('.card-menu');
function closeAllMenus() { menus.forEach(menu => { menu.style.display = 'none'; }); }
document.querySelectorAll('.menu-btn').forEach(btn => {
    btn.addEventListener('click', (e) => {
        e.preventDefault(); e.stopPropagation();
        const menu = btn.parentElement.querySelector('.card-menu');
        const isVisible = menu.style.display === 'block';
        closeAllMenus();
        if (!isVisible) menu.style.display = 'block';
    });
});
document.querySelectorAll('.card-menu').forEach(menu => {
    menu.addEventListener('click', (e) => e.stopPropagation());
});
document.addEventListener('click', () => closeAllMenus());
</script>
</body>
</html>