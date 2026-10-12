// ============================================================
// PROJECT LANTERN – DASHBOARD JAVASCRIPT
// (Multi‑select + Bulk Context Menu + Drop Zones)
// ============================================================

"use strict";

// ---------- GLOBALS ----------
const currentFolderId = String(window.currentFolderId ?? "0");
const csrfToken      = String(window.csrf_token ?? "");

// ---------- ELEMENTS ----------
const folderPicker      = document.getElementById("folderPicker");
const folderPlusBtn     = document.getElementById("folderPlusBtn");
const folderDropdown    = document.getElementById("folderDropdown");
const uploadFolderButton = document.getElementById("uploadFolderButton");
const fileInput         = document.getElementById("fileInput");
const selectedFileName  = document.getElementById("selectedFileName");
const uploadBtn         = document.getElementById("uploadBtn");
const uploadOverlay     = document.getElementById("uploadLoadingOverlay");
const uploadFill        = document.getElementById("uploadProgressFill");
const uploadText        = document.getElementById("uploadProgressText");

// Drop zone elements (used by existing setupDropZone)
const filesZone         = document.getElementById("filesDropZone");
const foldersZone       = document.getElementById("foldersDropZone");

// ---------- MULTI‑SELECT SET ----------
const selectedCards = new Set();

// ============================================================
// MODALS (Create folder / Upload file)
// ============================================================
function openFolderModal() { const m = document.getElementById("folderModal"); if (m) m.style.display = "flex"; }
function closeFolderModal() { const m = document.getElementById("folderModal"); if (m) m.style.display = "none"; }
function openUploadModal() { const m = document.getElementById("uploadModal"); if (m) m.style.display = "flex"; }
function closeUploadModal() { const m = document.getElementById("uploadModal"); if (m) m.style.display = "none"; }
window.openFolderModal = openFolderModal;
window.closeFolderModal = closeFolderModal;
window.openUploadModal = openUploadModal;
window.closeUploadModal = closeUploadModal;

window.addEventListener("click", function (e) {
    const fm = document.getElementById("folderModal");
    const um = document.getElementById("uploadModal");
    if (fm && e.target === fm) closeFolderModal();
    if (um && e.target === um) closeUploadModal();
});

// ============================================================
// CLOSE MODALS WHEN CLICKING OUTSIDE (generic)
// ============================================================
window.addEventListener("click", function (event) {
    const folderModal = document.getElementById("folderModal");
    const uploadModal = document.getElementById("uploadModal");
    if (folderModal && event.target === folderModal) closeFolderModal();
    if (uploadModal && event.target === uploadModal) closeUploadModal();
});

// ============================================================
// MOVE FOLDER MODAL (unchanged)
// ============================================================
function openMoveFolderModal(folderId) {
    const modal = document.getElementById('moveFolderModal');
    const select = document.getElementById('moveTargetSelect');
    const form = document.getElementById('moveFolderForm');
    if (!modal || !select || !form) return;
    document.getElementById('moveFolderId').value = folderId;
    select.innerHTML = '<option value="">Loading folders…</option>';
    modal.style.display = 'flex';
    fetch(`get_folders_for_move.php?exclude_id=${folderId}`)
        .then(response => response.text())
        .then(html => { select.innerHTML = html; })
        .catch(() => { select.innerHTML = '<option value="">Error loading folders</option>'; });
}
function closeMoveFolderModal() { const m = document.getElementById('moveFolderModal'); if (m) m.style.display = 'none'; }
async function submitMoveFolder() {
    const form = document.getElementById('moveFolderForm');
    const formData = new FormData(form);
    const folderId = document.getElementById('moveFolderId').value;
    formData.delete('folder_id');
    const parentId = formData.get('parent_id');
    if (!parentId && parentId !== '0') { alert('Please select a destination.'); return; }
    if (parentId == folderId) { alert('Cannot move a folder into itself.'); return; }
    try {
        const response = await fetch('move_folder.php?id=' + encodeURIComponent(folderId), { method: 'POST', body: formData });
        if (response.redirected || response.ok) { closeMoveFolderModal(); location.reload(); }
        else { const text = await response.text(); alert('Move failed: ' + text); }
    } catch (error) { alert('Network error: ' + error.message); }
}
window.addEventListener('click', (e) => { const modal = document.getElementById('moveFolderModal'); if (e.target === modal) closeMoveFolderModal(); });

// ============================================================
// MOVE FILE MODAL (unchanged)
// ============================================================
function openMoveFileModal(fileId) {
    const modal = document.getElementById('moveFileModal');
    const select = document.getElementById('moveFileTargetSelect');
    if (!modal || !select) return;
    document.getElementById('moveFileId').value = fileId;
    document.getElementById('moveFileCsrfToken').value = csrfToken;
    select.innerHTML = '<option value="">Loading folders…</option>';
    modal.style.display = 'flex';
    fetch('get_folders_for_move.php')
        .then(response => response.text())
        .then(html => { select.innerHTML = html; })
        .catch(() => { select.innerHTML = '<option value="">Error loading folders</option>'; });
}
function closeMoveFileModal() { const m = document.getElementById('moveFileModal'); if (m) m.style.display = 'none'; }
async function submitMoveFile() {
    const form = document.getElementById('moveFileForm');
    const formData = new FormData(form);
    const fileId = document.getElementById('moveFileId').value;
    const folderId = formData.get('folder_id');
    if (!folderId && folderId !== '0') { alert('Please select a destination.'); return; }
    try {
        const response = await fetch('move.php?id=' + encodeURIComponent(fileId), { method: 'POST', body: formData });
        if (response.redirected || response.ok) { closeMoveFileModal(); location.reload(); }
        else { const text = await response.text(); alert('Move failed: ' + text); }
    } catch (error) { alert('Network error: ' + error.message); }
}
window.addEventListener('click', (e) => { const modal = document.getElementById('moveFileModal'); if (e.target === modal) closeMoveFileModal(); });

// ============================================================
// COPY FOLDER MODAL (unchanged)
// ============================================================
function openCopyFolderModal(folderId) {
    const modal = document.getElementById('copyFolderModal');
    const select = document.getElementById('copyTargetSelect');
    if (!modal || !select) return;
    document.getElementById('copyFolderId').value = folderId;
    document.getElementById('copyFolderCsrfToken').value = csrfToken;
    select.innerHTML = '<option value="">Loading folders…</option>';
    modal.style.display = 'flex';
    fetch(`get_folders_for_move.php?exclude_id=${folderId}`)
        .then(response => response.text())
        .then(html => { select.innerHTML = html; })
        .catch(() => { select.innerHTML = '<option value="">Error loading folders</option>'; });
}
function closeCopyFolderModal() { const m = document.getElementById('copyFolderModal'); if (m) m.style.display = 'none'; }
async function submitCopyFolder() {
    const form = document.getElementById('copyFolderForm');
    const formData = new FormData(form);
    const folderId = document.getElementById('copyFolderId').value;
    formData.delete('folder_id');
    const parentId = formData.get('parent_id');
    if (!parentId && parentId !== '0') { alert('Please select a destination.'); return; }
    if (parentId == folderId) { alert('Cannot copy a folder into itself.'); return; }
    try {
        const response = await fetch('copy_folder.php?id=' + encodeURIComponent(folderId), { method: 'POST', body: formData });
        if (response.redirected || response.ok) { closeCopyFolderModal(); location.reload(); }
        else { const text = await response.text(); alert('Copy failed: ' + text); }
    } catch (error) { alert('Network error: ' + error.message); }
}
window.addEventListener('click', (e) => { const modal = document.getElementById('copyFolderModal'); if (e.target === modal) closeCopyFolderModal(); });

// ============================================================
// COPY FILE MODAL (unchanged)
// ============================================================
function openCopyFileModal(fileId) {
    const modal = document.getElementById('copyFileModal');
    const select = document.getElementById('copyFileTargetSelect');
    if (!modal || !select) return;
    document.getElementById('copyFileId').value = fileId;
    document.getElementById('copyFileCsrfToken').value = csrfToken;
    select.innerHTML = '<option value="">Loading folders…</option>';
    modal.style.display = 'flex';
    fetch('get_folders_for_move.php')
        .then(response => response.text())
        .then(html => { select.innerHTML = html; })
        .catch(() => { select.innerHTML = '<option value="">Error loading folders</option>'; });
}
function closeCopyFileModal() { const m = document.getElementById('copyFileModal'); if (m) m.style.display = 'none'; }
async function submitCopyFile() {
    const form = document.getElementById('copyFileForm');
    const formData = new FormData(form);
    const fileId = document.getElementById('copyFileId').value;
    formData.delete('file_id');
    const folderId = formData.get('folder_id');
    if (!folderId && folderId !== '0') { alert('Please select a destination.'); return; }
    try {
        const response = await fetch('copy.php?id=' + encodeURIComponent(fileId), { method: 'POST', body: formData });
        if (response.redirected || response.ok) { closeCopyFileModal(); location.reload(); }
        else { const text = await response.text(); alert('Copy failed: ' + text); }
    } catch (error) { alert('Network error: ' + error.message); }
}
window.addEventListener('click', (e) => { const modal = document.getElementById('copyFileModal'); if (e.target === modal) closeCopyFileModal(); });

// ============================================================
// RENAME FOLDER MODAL (unchanged)
// ============================================================
function openRenameFolderModal(folderId, currentName) {
    const modal = document.getElementById('renameFolderModal');
    if (!modal) return;
    document.getElementById('renameFolderId').value = folderId;
    document.getElementById('renameFolderName').value = currentName;
    document.getElementById('renameFolderCsrfToken').value = csrfToken;
    modal.style.display = 'flex';
    setTimeout(() => { const input = document.getElementById('renameFolderName'); if (input) input.focus(); }, 100);
}
function closeRenameFolderModal() { const m = document.getElementById('renameFolderModal'); if (m) m.style.display = 'none'; }
async function submitRenameFolder() {
    const folderId = document.getElementById('renameFolderId').value;
    const newName = document.getElementById('renameFolderName').value.trim();
    const csrf = document.getElementById('renameFolderCsrfToken').value;
    if (!newName) { alert('Please enter a folder name.'); return; }
    const formData = new FormData();
    formData.append('folder_name', newName);
    formData.append('csrf_token', csrf);
    try {
        const response = await fetch('rename_folder.php?id=' + encodeURIComponent(folderId), { method: 'POST', body: formData });
        if (response.redirected || response.ok) { closeRenameFolderModal(); location.reload(); }
        else { const text = await response.text(); alert('Rename failed: ' + text); }
    } catch (error) { alert('Network error: ' + error.message); }
}
window.addEventListener('click', (e) => { const modal = document.getElementById('renameFolderModal'); if (e.target === modal) closeRenameFolderModal(); });

// ============================================================
// RENAME FILE MODAL (unchanged)
// ============================================================
function openRenameFileModal(fileId, currentFilename) {
    const modal = document.getElementById('renameFileModal');
    if (!modal) return;
    document.getElementById('renameFileId').value = fileId;
    document.getElementById('renameFileInput').value = currentFilename;
    document.getElementById('renameFileCurrent').textContent = 'Current: ' + currentFilename;
    document.getElementById('renameFileCsrfToken').value = csrfToken;
    modal.style.display = 'flex';
    setTimeout(() => { const input = document.getElementById('renameFileInput'); if (input) input.focus(); }, 100);
}
function closeRenameFileModal() { const m = document.getElementById('renameFileModal'); if (m) m.style.display = 'none'; }
async function submitRenameFile() {
    const fileId = document.getElementById('renameFileId').value;
    const newName = document.getElementById('renameFileInput').value.trim();
    const csrf = document.getElementById('renameFileCsrfToken').value;
    if (!newName) { alert('Please enter a filename.'); return; }
    const formData = new FormData();
    formData.append('new_name', newName);
    formData.append('csrf_token', csrf);
    try {
        const response = await fetch('rename.php?id=' + encodeURIComponent(fileId), { method: 'POST', body: formData });
        if (response.redirected || response.ok) { closeRenameFileModal(); location.reload(); }
        else { const text = await response.text(); alert('Rename failed: ' + text); }
    } catch (error) { alert('Network error: ' + error.message); }
}
window.addEventListener('click', (e) => { const modal = document.getElementById('renameFileModal'); if (e.target === modal) closeRenameFileModal(); });

// ============================================================
// DELETE CONFIRMATION MODAL (Promise‑based)
// ============================================================
function showDeleteModal(message) {
    const modal = document.getElementById('deleteConfirmModal');
    const msgEl = document.getElementById('deleteConfirmMessage');
    if (!modal || !msgEl) return Promise.reject('Modal missing');
    msgEl.textContent = message;
    modal.classList.add('show');
    return new Promise((resolve, reject) => {
        const yesBtn = document.getElementById('deleteConfirmYes');
        const noBtn = document.getElementById('deleteConfirmNo');
        function cleanup() {
            modal.classList.remove('show');
            yesBtn.removeEventListener('click', onYes);
            noBtn.removeEventListener('click', onNo);
            modal.removeEventListener('click', onClickOutside);
        }
        function onYes() { cleanup(); resolve(); }
        function onNo()  { cleanup(); reject(new Error('Cancelled')); }
        function onClickOutside(e) { if (e.target === modal) { cleanup(); reject(new Error('Cancelled')); } }
        yesBtn.addEventListener('click', onYes);
        noBtn.addEventListener('click', onNo);
        modal.addEventListener('click', onClickOutside);
    });
}

// ============================================================
// DELETE FILE (custom modal, then AJAX)
// ============================================================
async function deleteFile(fileId) {
    try { await showDeleteModal('Are you sure you want to delete this file? It will be moved to the trash.'); } catch (e) { return; }
    const formData = new FormData();
    formData.append('id', fileId);
    formData.append('csrf_token', csrfToken);
    try {
        const response = await fetch('delete.php', { method: 'POST', body: formData });
        const result = await response.json();
        if (result.success) {
            document.querySelectorAll('.selectable-card').forEach(c => {
                if (c.querySelector(`a[onclick*="deleteFile(${fileId})"]`)) c.remove();
            });
        } else alert('Delete failed: ' + (result.message || 'Unknown error'));
    } catch (error) { alert('Network error.'); }
}

// ============================================================
// DELETE FOLDER (custom modal, then AJAX)
// ============================================================
async function deleteFolder(folderId) {
    try { await showDeleteModal('Are you sure you want to delete this folder and all its contents? They will be moved to the trash.'); } catch (e) { return; }
    const formData = new FormData();
    formData.append('id', folderId);
    formData.append('csrf_token', csrfToken);
    try {
        const response = await fetch('delete_folder.php', { method: 'POST', body: formData });
        const result = await response.json();
        if (result.success) {
            document.querySelectorAll('.selectable-card').forEach(c => {
                if (c.querySelector(`a[onclick*="deleteFolder(${folderId})"]`)) c.remove();
            });
        } else alert('Delete failed: ' + (result.message || 'Unknown error'));
    } catch (error) { alert('Network error.'); }
}

// ============================================================
// CARD SYSTEM (with multi‑select)
// ============================================================
function initCardSystem() {
    const cards = document.querySelectorAll(".selectable-card");
    const cardMenus = document.querySelectorAll(".card-menu");

    function closeAllMenus() {
        cardMenus.forEach(m => m.style.display = "none");
        hideBulkContextMenu();
    }

    function clearSelection() {
        selectedCards.forEach(card => card.classList.remove("selected"));
        selectedCards.clear();
    }

    cards.forEach(card => {
        // ----- CLICK (Ctrl+Click multi‑select) -----
        card.addEventListener("click", function (e) {
            if (e.target.closest(".card-menu") || e.target.closest(".menu-btn")) return;
            if (e.ctrlKey || e.metaKey) {
                if (card.classList.contains("selected")) {
                    card.classList.remove("selected");
                    selectedCards.delete(card);
                } else {
                    card.classList.add("selected");
                    selectedCards.add(card);
                }
            } else {
                clearSelection();
                card.classList.add("selected");
                selectedCards.add(card);
            }
        });

        // ----- RIGHT CLICK (context menu) -----
        card.addEventListener("contextmenu", function (e) {
            e.preventDefault();
            e.stopPropagation();

            // If Ctrl is not held and this card isn't selected, clear and select it
            if (!e.ctrlKey && !e.metaKey && !card.classList.contains("selected")) {
                clearSelection();
                card.classList.add("selected");
                selectedCards.add(card);
            } else if ((e.ctrlKey || e.metaKey) && !card.classList.contains("selected")) {
                // Ctrl held, but this card not yet selected → add it
                card.classList.add("selected");
                selectedCards.add(card);
            }

            const count = selectedCards.size;
            closeAllMenus();

            if (count === 1) {
                // Show the card's own menu
                const menu = card.querySelector(".card-menu");
                if (menu) menu.style.display = "block";
            } else if (count > 1) {
                // Show bulk menu
                showBulkContextMenu(e.clientX, e.clientY, count);
            }
        });

        // Double‑click navigation
        card.addEventListener("dblclick", function (e) {
            if (e.target.closest(".card-menu") || e.target.closest(".menu-btn")) return;
            const folderId = card.getAttribute("data-folder-id");
            if (folderId) {
                window.location = "dashboard.php?folder_id=" + folderId;
                return;
            }
            const fileId = card.getAttribute("data-file-id");
            if (fileId) {
                window.location = "download.php?id=" + fileId;
            }
        });
    });

    // ⋮ button clicks (open the small menu for a single card)
    document.querySelectorAll(".menu-btn").forEach(btn => {
        btn.addEventListener("click", function (e) {
            e.preventDefault();
            e.stopPropagation();
            const menu = btn.parentElement.querySelector(".card-menu");
            if (!menu) return;
            const visible = menu.style.display === "block";
            closeAllMenus();
            if (!visible) menu.style.display = "block";
        });
    });

    // Global click – close menus and clear selection if clicking outside a card
    document.addEventListener("click", function (e) {
        closeAllMenus();
        const clickedCard = e.target.closest(".selectable-card");
        if (!clickedCard) {
            clearSelection();
        }
    });
}

// Run card system after DOM is ready
if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initCardSystem);
} else {
    initCardSystem();
}



// ============================================================
// FOLDER + DROPDOWN (unchanged)
// ============================================================
if (folderPlusBtn && folderDropdown) {
    folderPlusBtn.addEventListener("click", function (event) {
        event.preventDefault(); event.stopPropagation();
        folderDropdown.classList.toggle("show-dropdown");
    });
    folderDropdown.addEventListener("click", function (event) { event.stopPropagation(); });
    document.addEventListener("click", function () { folderDropdown.classList.remove("show-dropdown"); });
}

// ============================================================
// FOLDER PICKER (unchanged)
// ============================================================
function triggerFolderPicker() {
    if (!folderPicker) { alert("Folder picker is not available. Please refresh the page."); return; }
    folderPicker.value = "";
    folderPicker.click();
}
window.triggerFolderPicker = triggerFolderPicker;
if (uploadFolderButton) {
    uploadFolderButton.addEventListener("click", function (event) {
        event.preventDefault(); event.stopPropagation();
        if (folderDropdown) folderDropdown.classList.remove("show-dropdown");
        triggerFolderPicker();
    });
}

// ============================================================
// FILE INPUT (modal) – unchanged
// ============================================================
if (fileInput && selectedFileName) {
    fileInput.addEventListener("change", function () {
        const count = fileInput.files ? fileInput.files.length : 0;
        selectedFileName.textContent = count > 0 ? count + " file(s) selected" : "No file selected";
    });
}

// ============================================================
// UPLOAD OVERLAY / PROGRESS (unchanged)
// ============================================================
function showUploadOverlay(message) {
    if (uploadOverlay) {
        uploadOverlay.style.opacity = "1";
        uploadOverlay.style.display = "flex";
        if (uploadText && message) uploadText.textContent = message;
        if (uploadFill) uploadFill.style.width = "0%";
    }
}
function hideUploadOverlay() { if (uploadOverlay) { uploadOverlay.style.display = "none"; uploadOverlay.style.opacity = "1"; } }
function setUploadProgress(percentage, text) {
    const p = Math.max(0, Math.min(100, percentage));
    if (uploadFill) uploadFill.style.width = p + "%";
    if (uploadText && text) uploadText.textContent = text;
}
function showUploadComplete() {
    if (uploadFill) uploadFill.style.width = "100%";
    if (uploadText) uploadText.innerHTML = `<div style="display:flex;align-items:center;justify-content:center;gap:12px;font-size:18px;font-weight:700;color:#22c55e;animation:popIn 0.35s ease;"><span style="font-size:28px;">✅</span> Upload complete!</div>`;
}

// ============================================================
// FILE UPLOAD ENGINE (unchanged)
// ============================================================
const fileUploadQueue = [];
let isUploadingFiles = false, uploadedBytes = 0, totalBytes = 0;
function resetFileUploadQueue() { fileUploadQueue.length = 0; uploadedBytes = 0; totalBytes = 0; }
function queueFileUpload(file, relativePath = "") {
    if (!(file instanceof File)) return;
    fileUploadQueue.push({ file, relativePath });
    totalBytes += file.size;
}
function uploadSingleFile(file, relativePath = "") {
    return new Promise(function (resolve, reject) {
        const formData = new FormData();
        formData.append("file", file, file.name);
        formData.append("folder_id", currentFolderId);
        formData.append("relative_path", relativePath);
        formData.append("csrf_token", csrfToken);
        const xhr = new XMLHttpRequest();
        xhr.open("POST", "upload.php", true);
        let lastLoaded = 0;
        xhr.upload.onprogress = function (event) {
            if (!event.lengthComputable) return;
            const difference = event.loaded - lastLoaded;
            lastLoaded = event.loaded;
            uploadedBytes += difference;
            const percentage = totalBytes > 0 ? (uploadedBytes / totalBytes) * 100 : 0;
            setUploadProgress(percentage, formatBytes(uploadedBytes) + " / " + formatBytes(totalBytes));
        };
        xhr.onload = function () {
            if (xhr.status !== 200) { reject(new Error("HTTP " + xhr.status)); return; }
            try {
                const response = JSON.parse(xhr.responseText);
                if (response.success) resolve(response);
                else reject(new Error(response.message || "Upload failed"));
            } catch (error) { reject(new Error("Invalid server response")); }
        };
        xhr.onerror = function () { reject(new Error("Network error")); };
        xhr.send(formData);
    });
}
async function processUploadQueue() {
    if (isUploadingFiles) return;
    if (fileUploadQueue.length === 0) return;
    isUploadingFiles = true;
    uploadedBytes = 0;
    showUploadOverlay("Preparing upload...");
    try {
        while (fileUploadQueue.length > 0) {
            const item = fileUploadQueue.shift();
            await uploadSingleFile(item.file, item.relativePath);
        }
        showUploadComplete();
        setTimeout(function () { location.reload(); }, 1200);
    } catch (error) {
        console.error("File upload error:", error);
        hideUploadOverlay();
        alert("Upload failed: " + error.message);
    } finally { isUploadingFiles = false; }
}
if (uploadBtn && fileInput) {
    uploadBtn.addEventListener("click", async function () {
        const files = fileInput.files;
        if (!files || files.length === 0) { alert("Select files first."); return; }
        resetFileUploadQueue();
        for (let i = 0; i < files.length; i++) queueFileUpload(files[i]);
        closeUploadModal();
        await processUploadQueue();
    });
}

// ============================================================
// DIRECTORY PICKER UPLOAD (unchanged)
// ============================================================
if (folderPicker) {
    folderPicker.addEventListener("change", async function () {
        const files = Array.from(folderPicker.files || []);
        if (files.length === 0) return;
        try {
            await uploadFolderFiles(files, "picker");
        } catch (error) {
            console.error("Folder picker upload error:", error);
            hideUploadOverlay();
            alert("Folder upload failed: " + error.message);
        }
    });
}

// ============================================================
// FOLDER UPLOAD (unchanged)
// ============================================================
async function uploadFolderFiles(files, source) {
    if (!files || files.length === 0) { alert("The selected folder contains no files."); return; }
    showUploadOverlay("Preparing folder upload...");
    const formData = new FormData();
    formData.append("folder_id", currentFolderId);
    formData.append("csrf_token", csrfToken);
    let totalBytesFolder = 0;
    for (let i = 0; i < files.length; i++) {
        const file = files[i];
        let relativePath = getFileRelativePath(file);
        formData.append("files[]", file, file.name);
        formData.append("relative_paths[]", relativePath);
        totalBytesFolder += file.size;
    }
    setUploadProgress(0, "Uploading folder...");
    const xhr = new XMLHttpRequest();
    await new Promise(function (resolve, reject) {
        xhr.open("POST", "upload_folder.php", true);
        xhr.upload.onprogress = function (event) {
            if (!event.lengthComputable) return;
            const percentage = event.total > 0 ? (event.loaded / event.total) * 100 : 0;
            setUploadProgress(percentage, formatBytes(event.loaded) + " / " + formatBytes(event.total));
        };
        xhr.onload = function () {
            if (xhr.status < 200 || xhr.status >= 300) { reject(new Error("HTTP " + xhr.status)); return; }
            let response;
            try { response = JSON.parse(xhr.responseText); } catch (error) {
                console.error("Server response:", xhr.responseText);
                reject(new Error("Invalid server response"));
                return;
            }
            if (response.success) {
                setUploadProgress(100, "Folder upload complete");
                showUploadComplete();
                setTimeout(function () { location.reload(); }, 1200);
                resolve(response);
            } else {
                reject(new Error(response.message || "Folder upload failed"));
            }
        };
        xhr.onerror = function () { reject(new Error("Network error")); };
        xhr.send(formData);
    });
}

// ============================================================
// GET RELATIVE PATH FROM PICKER FILE (unchanged)
// ============================================================
function getFileRelativePath(file) {
    if (file && typeof file.webkitRelativePath === "string" && file.webkitRelativePath.length > 0) {
        return normalizeRelativePath(file.webkitRelativePath);
    }
    return file.name;
}

// Disable native drag on cards (prevent duplication)
document.querySelectorAll('.selectable-card').forEach(card => {
    card.addEventListener('dragstart', e => e.preventDefault());
});

// ============================================================
// DRAG & DROP DIRECTORY ENTRY HELPERS (unchanged – used by drop zones)
// ============================================================
function getDataTransferItems(dataTransfer) {
    if (!dataTransfer || !dataTransfer.items) return [];
    return Array.from(dataTransfer.items).filter(function (item) { return item.kind === "file"; });
}
function getEntryFromItem(item) {
    if (!item) return null;
    try { if (typeof item.webkitGetAsEntry === "function") return item.webkitGetAsEntry(); } catch (error) { console.warn("webkitGetAsEntry failed:", error); }
    try { if (typeof item.getAsEntry === "function") return item.getAsEntry(); } catch (error) { console.warn("getAsEntry failed:", error); }
    return null;
}
function inspectDropItems(dataTransfer) {
    const items = getDataTransferItems(dataTransfer);
    const entries = items.map(getEntryFromItem).filter(Boolean);
    const hasDirectory = entries.some(function (entry) { return entry.isDirectory === true; });
    const hasFile = entries.some(function (entry) { return entry.isFile === true; });
    return { items, entries, hasDirectory, hasFile, empty: entries.length === 0 };
}
function getDropType(dataTransfer) {
    const inspection = inspectDropItems(dataTransfer);
    if (inspection.hasDirectory) return "folder";
    if (inspection.hasFile) return "file";
    if (dataTransfer && dataTransfer.files && dataTransfer.files.length > 0) {
        const files = Array.from(dataTransfer.files);
        const hasRelativePath = files.some(function (file) {
            return typeof file.webkitRelativePath === "string" && file.webkitRelativePath.length > 0;
        });
        if (hasRelativePath) return "folder";
        return "file";
    }
    return "unknown";
}

// ============================================================
// SIMPLIFIED DROP ZONE FEEDBACK (unchanged)
// ============================================================
const DROP_HINT_MAP = { files: '📄 Drop files here', folders: '📁 Drop folder here' };
function getDropHintElement(zone) {
    let hint = zone.querySelector('.drop-hint');
    if (!hint) { hint = document.createElement('div'); hint.className = 'drop-hint'; zone.appendChild(hint); }
    return hint;
}
function showNeutralDropHint(zone, zoneType) {
    const hint = getDropHintElement(zone);
    hint.textContent = DROP_HINT_MAP[zoneType] || '';
    hint.className = 'drop-hint';
    zone.classList.add('drop-hover');
    zone.classList.remove('drop-valid', 'drop-invalid');
}
function clearDropHint(zone) {
    zone.classList.remove('drop-hover', 'drop-valid', 'drop-invalid');
    const hint = zone.querySelector('.drop-hint');
    if (hint) hint.remove();
}
const dragCounters = { folders: 0, files: 0 };
function resetDragCounter(type) { dragCounters[type] = 0; }

function setupDropZone(zone, type) {
    if (!zone) return;
    const zoneType = type === 'folders' ? 'folders' : 'files';
    zone.addEventListener('dragenter', (event) => {
        event.preventDefault(); event.stopPropagation();
        dragCounters[zoneType]++;
        showNeutralDropHint(zone, zoneType);
    });
    zone.addEventListener('dragover', (event) => {
        event.preventDefault(); event.stopPropagation();
        showNeutralDropHint(zone, zoneType);
    });
    zone.addEventListener('dragleave', (event) => {
        event.stopPropagation();
        dragCounters[zoneType]--;
        if (dragCounters[zoneType] <= 0) { resetDragCounter(zoneType); clearDropHint(zone); }
    });
    zone.addEventListener('drop', async (event) => {
        event.preventDefault(); event.stopPropagation();
        resetDragCounter(zoneType); clearDropHint(zone);
        const dataTransfer = event.dataTransfer;
        const detectedType = getDropType(dataTransfer);
        if (zoneType === 'folders') {
            if (detectedType !== 'folder') { alert('Only folders can be uploaded here.'); return; }
            showUploadOverlay('Reading folder...');
            try {
                const droppedFiles = await extractDroppedFolder(dataTransfer);
                if (droppedFiles.length === 0) { hideUploadOverlay(); alert('The selected folder contains no files.'); return; }
                const files = droppedFiles.map(item => item.file);
                const paths = droppedFiles.map(item => item.relativePath);
                await uploadFolderWithPaths(files, paths);
            } catch (error) { console.error(error); hideUploadOverlay(); alert('Could not read the dropped folder: ' + error.message); }
            return;
        }
        if (detectedType === 'folder') { alert('Folders cannot be uploaded here. Use the Folders section.'); return; }
        if (detectedType !== 'file') return;
        const files = extractDroppedFiles(dataTransfer);
        if (files.length === 0) return;
        resetFileUploadQueue();
        files.forEach(file => queueFileUpload(file));
        await processUploadQueue();
    });
}

// ============================================================
// READ DIRECTORY ENTRY RECURSIVELY (unchanged)
// ============================================================
function readDirectoryEntries(directoryReader) {
    return new Promise(function (resolve, reject) {
        const allEntries = [];
        function readBatch() {
            directoryReader.readEntries(function (entries) {
                if (!entries || entries.length === 0) { resolve(allEntries); return; }
                allEntries.push(...entries);
                readBatch();
            }, function (error) { reject(error); });
        }
        readBatch();
    });
}
function readEntryFile(fileEntry) {
    return new Promise(function (resolve, reject) {
        fileEntry.file(function (file) { resolve(file); }, function (error) { reject(error); });
    });
}
async function readDirectoryEntry(directoryEntry, parentPath = "") {
    const directoryName = directoryEntry.name;
    const currentPath = parentPath ? parentPath + "/" + directoryName : directoryName;
    const reader = directoryEntry.createReader();
    const entries = await readDirectoryEntries(reader);
    const files = [];
    for (const entry of entries) {
        if (entry.isFile) {
            const file = await readEntryFile(entry);
            files.push({ file, relativePath: currentPath + "/" + entry.name });
        } else if (entry.isDirectory) {
            const childFiles = await readDirectoryEntry(entry, currentPath);
            files.push(...childFiles);
        }
    }
    return files;
}
async function extractDroppedFolder(dataTransfer) {
    const items = getDataTransferItems(dataTransfer);
    const entries = items.map(getEntryFromItem).filter(Boolean);
    const directoryEntries = entries.filter(function (entry) { return entry.isDirectory; });
    if (directoryEntries.length === 0) throw new Error("No folder was detected in the drop.");
    const allFiles = [];
    for (const directoryEntry of directoryEntries) {
        const files = await readDirectoryEntry(directoryEntry);
        allFiles.push(...files);
    }
    return allFiles;
}
function extractDroppedFiles(dataTransfer) {
    if (!dataTransfer || !dataTransfer.files) return [];
    return Array.from(dataTransfer.files);
}

// ============================================================
// UPLOAD FOLDER WITH EXPLICIT RELATIVE PATHS (unchanged)
// ============================================================
async function uploadFolderWithPaths(files, relativePaths) {
    if (!files || files.length === 0) { hideUploadOverlay(); alert("No files were found in the folder."); return; }
    if (!relativePaths || relativePaths.length !== files.length) { hideUploadOverlay(); throw new Error("Folder paths do not match uploaded files."); }
    const formData = new FormData();
    formData.append("folder_id", currentFolderId);
    formData.append("csrf_token", csrfToken);
    for (let i = 0; i < files.length; i++) {
        formData.append("files[]", files[i], files[i].name);
        formData.append("relative_paths[]", normalizeRelativePath(relativePaths[i]));
    }
    setUploadProgress(0, "Uploading folder...");
    await new Promise(function (resolve, reject) {
        const xhr = new XMLHttpRequest();
        xhr.open("POST", "upload_folder.php", true);
        xhr.upload.onprogress = function (event) {
            if (!event.lengthComputable) return;
            const percentage = (event.loaded / event.total) * 100;
            setUploadProgress(percentage, formatBytes(event.loaded) + " / " + formatBytes(event.total));
        };
        xhr.onload = function () {
            if (xhr.status < 200 || xhr.status >= 300) { reject(new Error("HTTP " + xhr.status)); return; }
            let response;
            try { response = JSON.parse(xhr.responseText); } catch (error) {
                console.error("upload_folder.php response:", xhr.responseText);
                reject(new Error("Invalid server response")); return;
            }
            if (response.success) {
                setUploadProgress(100, "Folder upload complete");
                showUploadComplete();
                setTimeout(function () { location.reload(); }, 1200);
                resolve(response);
            } else {
                reject(new Error(response.message || "Folder upload failed"));
            }
        };
        xhr.onerror = function () { reject(new Error("Network error")); };
        xhr.send(formData);
    });
}

// ============================================================
// INITIALIZE DROP ZONES
// ============================================================
setupDropZone(filesZone, "files");
setupDropZone(foldersZone, "folders");

// Global drag cleanup
document.addEventListener("dragend", function () {
    resetDragCounter("files"); resetDragCounter("folders");
    clearDropHint(filesZone); clearDropHint(foldersZone);
});
document.addEventListener("drop", function (event) {
    const target = event.target;
    if (!target.closest || (!target.closest("#filesDropZone") && !target.closest("#foldersDropZone"))) {
        clearDropHint(filesZone); clearDropHint(foldersZone);
    }
});

// ============================================================
// NORMALIZE RELATIVE PATH (unchanged)
// ============================================================
function normalizeRelativePath(path) {
    if (typeof path !== "string") return "";
    let normalized = path.replace(/\\/g, "/").replace(/^\/+/, "").replace(/\/+/g, "/");
    const parts = normalized.split("/").filter(function (part) { return part !== "" && part !== "." && part !== ".."; });
    return parts.join("/");
}

// ============================================================
// FORMAT BYTES (unchanged)
// ============================================================
function formatBytes(bytes) {
    if (!isFinite(bytes) || bytes <= 0) return "0 B";
    const units = ["B", "KB", "MB", "GB", "TB"];
    const exponent = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
    return (bytes / Math.pow(1024, exponent)).toFixed(exponent === 0 ? 0 : 2) + " " + units[exponent];
}

// ============================================================
// FOLDER PICKER -> UPLOAD (unchanged, already handled above)
// ============================================================
if (folderPicker) {
    folderPicker.addEventListener("change", async function () {
        const files = Array.from(folderPicker.files || []);
        if (files.length === 0) return;
        try {
            const paths = files.map(function (file) { return getFileRelativePath(file); });
            await uploadFolderWithPaths(files, paths);
        } catch (error) {
            console.error("Folder picker error:", error);
            hideUploadOverlay();
            alert("Folder upload failed: " + error.message);
        }
    });
}

// ============================================================
// DEBUG INFORMATION (unchanged)
// ============================================================
console.log("[Lantern] Dashboard initialized.");
console.log("[Lantern] Current folder:", currentFolderId);
console.log("[Lantern] Folder drop zone:", foldersZone);
console.log("[Lantern] File drop zone:", filesZone);
console.log("[Lantern] Folder picker:", folderPicker);

// ============================================================
// ANIMATION (unchanged)
// ============================================================
const styleEl = document.createElement("style");
styleEl.innerHTML = `@keyframes popIn{0%{transform:scale(0.8);opacity:0}100%{transform:scale(1);opacity:1}}`;
document.head.appendChild(styleEl);

// ============================================================
// BULK CONTEXT MENU (must be defined before cardRightClick)
// ============================================================
const bulkMenu = document.getElementById("bulkContextMenu");
function showBulkContextMenu(x, y, count) {
    if (!bulkMenu) {
        console.error("bulkContextMenu element not found in DOM!");
        return;
    }
    bulkMenu.style.display = "block";
    bulkMenu.style.left = x + "px";
    bulkMenu.style.top = y + "px";
    const countSpan = document.getElementById("bulkCount");
    if (countSpan) countSpan.textContent = count;
    console.log("[BULK MENU] shown at", x, y, "items:", count);
}
function hideBulkContextMenu() {
    if (bulkMenu) bulkMenu.style.display = "none";
}
// Hide bulk menu on outside click, scroll, resize
document.addEventListener("click", function (e) {
    if (bulkMenu && !bulkMenu.contains(e.target)) {
        hideBulkContextMenu();
    }
});
window.addEventListener("scroll", hideBulkContextMenu);
window.addEventListener("resize", hideBulkContextMenu);

// ============================================================
// HELPER: clear all selections
// ============================================================
function clearSelection() {
    try {
        selectedCards.forEach(card => card.classList.remove("selected"));
        selectedCards.clear();
        console.log("[clearSelection] done");
    } catch(e) {
        console.error("[clearSelection] ERROR:", e);
    }
}

// ============================================================
// RIGHT‑CLICK HANDLER (called from HTML attribute)
// ============================================================
function cardRightClick(e, cardElement, type, id) {
    console.log("[cardRightClick] FIRED", type, id);   // LOG 1

    try {
        e.preventDefault();
        e.stopPropagation();
    } catch(err) {
        console.error("[cardRightClick] preventDefault error:", err);
    }

    // Ctrl / Meta handling
    try {
        if (!e.ctrlKey && !e.metaKey && !cardElement.classList.contains("selected")) {
            clearSelection();
            cardElement.classList.add("selected");
            selectedCards.add(cardElement);
            console.log("[cardRightClick] cleared & selected single card");
        } else if ((e.ctrlKey || e.metaKey) && !cardElement.classList.contains("selected")) {
            cardElement.classList.add("selected");
            selectedCards.add(cardElement);
            console.log("[cardRightClick] added to selection via Ctrl");
        }
    } catch(err) {
        console.error("[cardRightClick] selection logic error:", err);
    }

    const count = selectedCards.size;
    console.log("[cardRightClick] selected count:", count);   // LOG 2

    // Hide all menus
    try {
        document.querySelectorAll('.card-menu').forEach(m => m.style.display = 'none');
        hideBulkContextMenu();
        console.log("[cardRightClick] menus hidden");
    } catch(err) {
        console.error("[cardRightClick] hide menus error:", err);
    }

    if (count === 1) {
        const menu = cardElement.querySelector('.card-menu');
        console.log("[cardRightClick] single card menu element:", menu);   // LOG 3
        if (menu) {
            menu.style.display = 'block';
            console.log("[cardRightClick] single card menu shown");
        } else {
            console.error("[cardRightClick] single card menu NOT FOUND inside card");
        }
    } else if (count > 1) {
        console.log("[cardRightClick] calling showBulkContextMenu at", e.clientX, e.clientY);
        try {
            showBulkContextMenu(e.clientX, e.clientY, count);
        } catch(err) {
            console.error("[cardRightClick] showBulkContextMenu error:", err);
        }
    }
}