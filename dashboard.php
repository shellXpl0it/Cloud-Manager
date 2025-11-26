<?php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: index.php');
    exit;
}

define('DATABASE_DIR', 'database');
define('DB_FILE', DATABASE_DIR . '/files.json');

if (!is_dir(DATABASE_DIR)) {
    mkdir(DATABASE_DIR, 0777, true);
}
if (!file_exists(DB_FILE)) {
    file_put_contents(DB_FILE, json_encode([]));
}

function get_files_from_db() {
    $data = file_get_contents(DB_FILE);
    return json_decode($data, true) ?: [];
}

function save_files_to_db($files) {
    usort($files, function($a, $b) {
        return strcmp($a['name'], $b['name']);
    });
    file_put_contents(DB_FILE, json_encode($files, JSON_PRETTY_PRINT));
}


$config = json_decode(file_get_contents('config.json'), true);
$storagePath = $config['storage_path']; 
$allowedExtensions = $config['allowed_extensions'];
$maxUploadMB = $config['max_upload_mb'] ?? 128;

if (isset($_POST['save_settings'])) {
    $current_config = json_decode(file_get_contents('config.json'), true);
    $current_config['max_storage_gb'] = (int)$_POST['max_storage_gb'];
    if (!empty($_POST['new_password'])) {
        $current_config['password'] = $_POST['new_password'];
    }
    file_put_contents('config.json', json_encode($current_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    header("Location: dashboard.php");
    exit;
}

$subDirs = ['documents', 'images', 'audio', 'videos', 'archives'];
$paths = [];

if (!is_dir($storagePath)) {
    mkdir($storagePath, 0777, true);
}

foreach ($subDirs as $subDir) {
    $fullPath = $storagePath . DIRECTORY_SEPARATOR . $subDir;
    if (!is_dir($fullPath)) {
        mkdir($fullPath, 0777, true);
    }
    $paths[$subDir] = $fullPath;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $db_files = get_files_from_db();
    $files_added = false;

    foreach ($_FILES['file']['name'] as $key => $name) {
        $file = [
            'name' => $name,
            'tmp_name' => $_FILES['file']['tmp_name'][$key],
            'size' => $_FILES['file']['size'][$key],
            'error' => $_FILES['file']['error'][$key]
        ];
        
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowedExtensions)) {
            $_SESSION['errors'][] = "File type '." . $ext . "' is not allowed for the file '" . htmlspecialchars($name) . "'.";
            continue;
        }

        $targetDir = "";
        if (in_array($ext, ["txt", "pdf", "docx", "pptx", "xlsx", "csv", "odt", "lua", "js", "php", "css", "html", "json"])) {
            $targetDir = $paths['documents'];
        } elseif (in_array($ext, ["png", "jpg", "jpeg", "gif", "bmp", "svg", "webp"])) {
            $targetDir = $paths['images'];
        } elseif (in_array($ext, ["mp3", "wav", "ogg", "flac", "m4a"])) {
            $targetDir = $paths['audio'];
        } elseif (in_array($ext, ["mp4", "mov", "avi", "mkv", "webm", "wmv"])) {
            $targetDir = $paths['videos'];
        } elseif (in_array($ext, ["zip", "rar", "7z"])) {
            $targetDir = $paths['archives'];
        }

        if (!empty($targetDir)) {
            $fileName = basename($file['name']);
            $filePath = $targetDir . DIRECTORY_SEPARATOR . $fileName;

            if (file_exists($filePath)) {
                $_SESSION['errors'][] = "A file named '" . htmlspecialchars($fileName) . "' already exists. Upload skipped.";
                continue;
            }
            move_uploaded_file($file['tmp_name'], $filePath);
            $files_added = true;

            $db_files[] = [
                "path" => $filePath,
                "name" => basename($file['name']),
                "type" => array_search($targetDir, $paths),
                "date" => date("d.m.Y H:i", filemtime($filePath))
            ];
        }
    }
    if ($files_added) {
        save_files_to_db($db_files);
    }
    header("Location: dashboard.php");
    exit;
}

if (isset($_POST['delete'])) {
    $filePath = $_POST['delete'];
    if (file_exists($filePath)) {
        unlink($filePath);

        $db_files = get_files_from_db();
        $db_files = array_filter($db_files, function($file) use ($filePath) {
            return $file['path'] !== $filePath;
        });
        save_files_to_db($db_files);
    }
    header("Location: dashboard.php");
    exit;
}

if (isset($_POST['delete_selected']) && isset($_POST['selected_files'])) {
    $files_to_delete = $_POST['selected_files'];
    $db_files = get_files_from_db();

    $updated_db_files = array_filter($db_files, function($file) use ($files_to_delete) {
        return !in_array($file['path'], $files_to_delete);
    });

    foreach ($files_to_delete as $filePath) {
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }

    save_files_to_db($updated_db_files);
    header("Location: dashboard.php");
    exit;
}

if (isset($_POST['rename']) && isset($_POST['old_name']) && isset($_POST['new_name'])) {
    $oldPath = str_replace('\\', '/', $_POST['old_name']);
    $newName = $_POST['new_name'];
    
    $ext = pathinfo($oldPath, PATHINFO_EXTENSION);
    $newPath = dirname($oldPath) . '/' . $newName . '.' . $ext;

    if (file_exists($oldPath)) {
        rename($oldPath, $newPath);

        $db_files = get_files_from_db();
        foreach ($db_files as &$file_ref) {
            if (str_replace('\\', '/', $file_ref['path']) === $oldPath) {
                $file_ref['path'] = $newPath;
                $file_ref['name'] = basename($newPath);
                $file_ref['date'] = date("d.m.Y H:i", filemtime($newPath));
                break;
            }
        }
        save_files_to_db($db_files);
    }
    header("Location: dashboard.php");
    exit;
}

function sync_filesystem_to_db($paths) {
    $all_files = [];
    foreach ($paths as $type => $dir) {
        if (is_dir($dir)) {
            foreach (scandir($dir) as $file) {
                if ($file !== "." && $file !== "..") {
                    $filePath = $dir . DIRECTORY_SEPARATOR . $file;
                    if (is_file($filePath)) {
                        $all_files[] = [
                            "path" => $filePath,
                            "name" => $file,
                            "type" => $type,
                            "date" => date("d.m.Y H:i", filemtime($filePath))
                        ];
                    }
                }
            }
        }
    }
    save_files_to_db($all_files);
    return $all_files;
}

if (isset($_GET['sync'])) {
    $files = sync_filesystem_to_db($paths);
    header("Location: dashboard.php");
    exit;
} else {
    $files = get_files_from_db();
}

if (isset($_GET['file'])) {
    header('Location: download.php?file=' . urlencode($_GET['file']));
    exit;
}
?>

<?php
$used_bytes = 0;
foreach ($files as $file) {
    if (file_exists($file['path'])) {
        $used_bytes += filesize($file['path']);
    }
}

$max_storage_gb = $config['max_storage_gb'];
$max_storage_bytes = $max_storage_gb * 1024 * 1024 * 1024;

$used_gb = $used_bytes > 0 ? $used_bytes / (1024 * 1024 * 1024) : 0;
$usage_percentage = 0;
if ($max_storage_bytes > 0) {
    $usage_percentage = ($used_bytes / $max_storage_bytes) * 100;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cloud Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .drop-area {
            border: 2px dashed #0d6efd;
            border-radius: .5rem;
            padding: 50px;
            text-align: center;
            cursor: pointer;
            background-color: #fff;
            transition: background-color 0.2s;
        }
        .drop-area:hover {
            background-color: #e9ecef;
        }
        .drop-area.drag-over {
            background-color: #d1e7fd;
        }
        .file-icon {
            font-size: 1.5rem;
        }
        .table th, .table td {
            vertical-align: middle;
        }
        .action-btns {
            display: flex;
            gap: 5px;
        }
        .modal-body img, .modal-body video, .modal-body audio {
            max-width: 100%;
        }
    </style>
</head>
<body>

<div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="d-flex align-items-center gap-3">
            <h1 class="m-0">Cloud Manager</h1>
            <button id="delete-selected-btn" class="btn btn-lg btn-danger" style="display: none;" title="Delete Selected"><i class="bi bi-trash"></i></button>
            <button class="btn btn-lg btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#settingsModal">
                <i class="bi bi-gear-fill"></i>
            </button>
            <a href="logout.php" class="btn btn-lg btn-outline-danger"><i class="bi bi-box-arrow-right"></i></a>
        </div>
    </div>
    
    <div class="mb-4">
        <div class="d-flex justify-content-between">
            <span>Storage Usage</span>
            <span><?= number_format($used_gb, 2) ?> GB / <?= $max_storage_gb ?> GB</span>
        </div>
        <div class="progress" role="progressbar" aria-label="Storage usage" aria-valuenow="<?= $usage_percentage ?>" aria-valuemin="0" aria-valuemax="100">
            <div class="progress-bar" style="width: <?= $usage_percentage ?>%">
                <?= number_format($usage_percentage, 1) ?>%
            </div>
        </div>
    </div>

    <div class="mb-5">
        <form method="POST" enctype="multipart/form-data">
            <input type="file" name="file[]" id="file-input" multiple onchange="this.form.submit()" style="display:none;">
            <div class="drop-area" id="drop-area">
                <p>Drag & Drop files here or click to select.</p>
            </div>
        </form>
    </div>


    <?php if (isset($_SESSION['errors']) && !empty($_SESSION['errors'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <strong>Error(s) during upload:</strong>
            <ul>
                <?php foreach ($_SESSION['errors'] as $error): ?>
                    <li><?= $error ?></li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['errors']); ?>
    <?php endif; ?>


    <div class="mb-4">
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="text" id="search-input" class="form-control" placeholder="Search for files by name...">
        </div>
    </div>


    <table class="table table-hover align-middle" id="file-table">
        <thead class="table-light">
            <tr>
                <th scope="col" style="width: 1%;"><input class="form-check-input" type="checkbox" id="select-all-checkbox"></th>
                <th scope="col" style="width: 5%;">Type</th>
                <th scope="col">Name</th>
                <th scope="col" style="width: 15%;">Last Modified</th>
                <th scope="col" style="width: 20%;">Actions</th>
            </tr>
        </thead>
        <tbody id="file-table-body">
            <?php foreach ($files as $file): ?>
                <tr>
                    <td><input class="form-check-input file-checkbox" type="checkbox" value="<?= htmlspecialchars($file['path']) ?>"></td>
                    <td>
                        <?php
                        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                        $icon = "bi-file-earmark";
                        if (in_array($ext, ["png", "jpg", "jpeg", "gif", "bmp", "svg", "webp"])) $icon = "bi-file-earmark-image";
                        elseif (in_array($ext, ["mp4", "mov", "avi", "mkv", "webm", "wmv"])) $icon = "bi-file-earmark-play";
                        elseif (in_array($ext, ["mp3", "wav", "ogg", "flac", "m4a"])) $icon = "bi-file-earmark-music";
                        elseif (in_array($ext, ["pdf"])) $icon = "bi-file-earmark-pdf";
                        elseif (in_array($ext, ["docx", "odt"])) $icon = "bi-file-earmark-word";
                        elseif (in_array($ext, ["pptx"])) $icon = "bi-file-earmark-slides";
                        elseif (in_array($ext, ["xlsx", "csv"])) $icon = "bi-file-earmark-excel";
                        elseif (in_array($ext, ["lua", "js", "php", "css", "html", "json"])) $icon = "bi-file-earmark-code";
                        elseif (in_array($ext, ["zip", "rar", "7z"])) $icon = "bi-file-earmark-zip";
                        ?>
                        <i class="bi <?= $icon ?> file-icon"></i>
                    </td>
                    <td><?= htmlspecialchars($file['name']) ?></td>
                    <td><?= $file['date'] ?></td>
                    <td>
                        <div class="action-btns">
                            <?php
                                $webPath = str_replace('\\', '/', $file['path']);
                            ?>
                            <button class="btn btn-sm btn-outline-secondary" onclick="openModal('<?= htmlspecialchars($webPath) ?>', '<?= htmlspecialchars($file['name']) ?>')">
                                <i class="bi bi-eye"></i>
                            </button>

                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="openRenameModal('<?= htmlspecialchars($file['path']) ?>', '<?= htmlspecialchars(pathinfo($file['name'], PATHINFO_FILENAME)) ?>')">
                                <i class="bi bi-pencil"></i>
                            </button>

                            <a href="download.php?file=<?= urlencode($file['path']) ?>" class="btn btn-sm btn-outline-success"><i class="bi bi-download"></i></a>

                            <?php $formId = 'delete-form-' . md5($file['path']); ?>
                            <form method="POST" class="d-inline" id="<?= $formId ?>">
                                <input type="hidden" name="delete" value="<?= htmlspecialchars($file['path']) ?>">
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="openDeleteModal('<?= $formId ?>', '<?= htmlspecialchars($file['name']) ?>')"><i class="bi bi-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="modal fade" id="previewModal" tabindex="-1" aria-labelledby="previewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="previewModalLabel">File Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
            </div>
        </div>
    </div>
</div>


<div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteConfirmModalLabel">Confirm Deletion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to delete the file <strong id="file-to-delete-name"></strong>? This action cannot be undone.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button> 
                <button type="button" class="btn btn-danger" id="confirm-delete-btn">Delete</button>
            </div>
        </div>
    </div>
</div>


<div class="modal fade" id="renameModal" tabindex="-1" aria-labelledby="renameModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="renameModalLabel">Rename File</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Enter a new name for the file.</p>
                    <input type="hidden" name="old_name" id="rename-old-path">
                    <input type="hidden" name="rename" value="1">
                    <div class="mb-3">
                        <label for="new-name-input" class="form-label">New Name (without extension)</label>
                        <input type="text" class="form-control" id="new-name-input" name="new_name" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Rename</button>
                </div>
            </form>
        </div>
    </div>
</div>


<div class="modal fade" id="settingsModal" tabindex="-1" aria-labelledby="settingsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="settingsModalLabel">Settings</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="save_settings" value="1">
                    <div class="mb-3">
                        <label for="max-storage-input" class="form-label">Max Storage (GB)</label>
                        <input type="number" class="form-control" id="max-storage-input" name="max_storage_gb" 
                               value="<?= htmlspecialchars($config['max_storage_gb'] ?? 5) ?>" 
                               min="1" required>
                    </div>
                    <hr>
                    <div class="mb-3">
                        <label for="new-password-input" class="form-label">New Password (leave empty to keep current)</label>
                        <input type="password" class="form-control" id="new-password-input" name="new_password" placeholder="Enter new password">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Settings</button>
                </div>
            </form>
        </div>
    </div>
</div>


<form id="bulk-delete-form" method="POST">
    <input type="hidden" name="delete_selected" value="1">
</form>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const dropArea = document.getElementById('drop-area');
    const fileInput = document.getElementById('file-input');
    const MAX_UPLOAD_BYTES = <?= $maxUploadMB ?> * 1024 * 1024;

    function handleFiles(files) {
        let totalSize = 0;
        let tooLargeFiles = [];

        for (let i = 0; i < files.length; i++) {
            if (files[i].size > MAX_UPLOAD_BYTES) {
                tooLargeFiles.push(files[i].name);
            }
            totalSize += files[i].size;
        }

        if (tooLargeFiles.length > 0) {
            alert(`Error: The following files are too large (max <?= $maxUploadMB ?> MB per file):\n- ${tooLargeFiles.join('\n- ')}`);
            return false;
        }

        fileInput.files = files;
        fileInput.form.submit();
    }

    dropArea.addEventListener('click', () => fileInput.click());

    dropArea.addEventListener('dragover', (event) => {
        event.preventDefault();
        dropArea.classList.add('drag-over');
    });

    dropArea.addEventListener('dragleave', () => {
        dropArea.classList.remove('drag-over');
    });

    dropArea.addEventListener('drop', (event) => {
        event.preventDefault();
        dropArea.classList.remove('drag-over');
        handleFiles(event.dataTransfer.files);
    });

    fileInput.addEventListener('change', () => {
        handleFiles(fileInput.files);
    });

    const searchInput = document.getElementById('search-input');
    const tableBody = document.getElementById('file-table-body');
    const tableRows = tableBody.getElementsByTagName('tr');

    searchInput.addEventListener('keyup', function() {
        const searchTerm = searchInput.value.toLowerCase();

        for (let i = 0; i < tableRows.length; i++) {
            const row = tableRows[i];
            const fileNameCell = row.getElementsByTagName('td')[1];
            if (fileNameCell) {
                const fileName = fileNameCell.textContent || fileNameCell.innerText;
                if (fileName.toLowerCase().indexOf(searchTerm) > -1) {
                    row.style.display = "";
                } else {
                    row.style.display = "none";
                }
            }
        }
    });

    // --- Bulk Selection & Deletion ---
    const selectAllCheckbox = document.getElementById('select-all-checkbox');
    const fileCheckboxes = document.querySelectorAll('.file-checkbox');
    const deleteSelectedBtn = document.getElementById('delete-selected-btn');

    function updateBulkActions() {
        const selectedCheckboxes = document.querySelectorAll('.file-checkbox:checked');
        const selectedCount = selectedCheckboxes.length;

        if (selectedCount > 0) {
            deleteSelectedBtn.style.display = 'inline-block';
        } else {
            deleteSelectedBtn.style.display = 'none';
        }
        selectAllCheckbox.checked = selectedCount > 0 && selectedCount === fileCheckboxes.length;
    }

    selectAllCheckbox.addEventListener('change', function() {
        fileCheckboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
        });
        updateBulkActions();
    });

    fileCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateBulkActions);
    });

    deleteSelectedBtn.addEventListener('click', function() {
        const selectedFiles = Array.from(document.querySelectorAll('.file-checkbox:checked')).map(cb => cb.value);
        const fileCount = selectedFiles.length;
        
        openDeleteModal(null, `${fileCount} files`);
    });


    function openModal(filePath, fileName) {
        const modalBody = document.querySelector('#previewModal .modal-body');
        const modalTitle = document.querySelector('#previewModal .modal-title');
        const fileExtension = filePath.split('.').pop().toLowerCase();

        modalTitle.textContent = fileName;

        if (['jpg', 'png', 'jpeg', 'gif', 'bmp', 'svg', 'webp'].includes(fileExtension)) {
            modalBody.innerHTML = `<img src="${filePath}" alt="${fileName}">`;
        } else if (['mp4', 'mov', 'webm'].includes(fileExtension)) {
            modalBody.innerHTML = `<video src="${filePath}" controls autoplay></video>`;
        } else if (['mp3', 'wav', 'm4a', 'ogg', 'flac'].includes(fileExtension)) {
            modalBody.innerHTML = `<audio src="${filePath}" controls autoplay></audio>`;
        } else if (['txt', 'csv', 'log', 'md', 'json', 'php', 'js', 'css', 'html', 'lua'].includes(fileExtension)) {
            modalBody.innerHTML = '<div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div>';
            fetch(`get_file_content.php?path=${encodeURIComponent(filePath)}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.text();
                })
                .then(text => modalBody.innerHTML = text)
                .catch(error => modalBody.innerHTML = '<p class="p-5 text-danger">Error loading file preview.</p>');
        } else {
            modalBody.innerHTML = `<p class="p-5">No preview available for this file type.</p><a href="download.php?file=${encodeURIComponent(filePath)}" class="btn btn-primary">Download File</a>`;
        }

        const previewModal = new bootstrap.Modal(document.getElementById('previewModal'));
        previewModal.show();
    }

    const renameModal = new bootstrap.Modal(document.getElementById('renameModal'));
    const renameOldPathInput = document.getElementById('rename-old-path');
    const newNameInput = document.getElementById('new-name-input');

    function openRenameModal(oldPath, currentName) {
        renameOldPathInput.value = oldPath;
        newNameInput.value = currentName;
        renameModal.show();
    }

    const deleteModal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
    const confirmDeleteBtn = document.getElementById('confirm-delete-btn');
    const fileToDeleteName = document.getElementById('file-to-delete-name');
    let formToSubmit = null;

    function openDeleteModal(formId, fileName) {
        if (formId) {
            formToSubmit = document.getElementById(formId);
            fileToDeleteName.innerHTML = `the file: <strong>${fileName}</strong>`;
        } else {
            formToSubmit = null;
            fileToDeleteName.innerHTML = `the selected <strong>${fileName}</strong>`;
        }
        deleteModal.show();
    }

    confirmDeleteBtn.addEventListener('click', function() {
        if (formToSubmit) {
            formToSubmit.submit();
        } else {
            const bulkForm = document.getElementById('bulk-delete-form');
            bulkForm.innerHTML = '<input type="hidden" name="delete_selected" value="1">';
            const selectedFiles = Array.from(document.querySelectorAll('.file-checkbox:checked')).map(cb => cb.value);
            selectedFiles.forEach(path => {
                bulkForm.insertAdjacentHTML('beforeend', `<input type="hidden" name="selected_files[]" value="${path}">`);
            });
            bulkForm.submit();
        }
    });
</script>

</body>
</html>
