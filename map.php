<?php
require_once 'config.php';
require_once 'functions.php';

if (!isLoggedIn()) {
    redirect('index.php');
}
if (!isAdmin()) {
    die('Access denied. Admins only.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Manage Places (Map)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .small-input { max-width: 260px; }
        .row-actions { white-space: nowrap; }
        .import-preview { max-height: 200px; overflow:auto; }
    </style>
</head>
<body>
<?php include 'header.php'; ?>

<div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="text-primary">Places (Map) Management</h2>
        <div class="d-flex gap-2">
            <a id="exportCsvBtn" href="map_export.php" class="btn btn-success">Export CSV</a>
            <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#importModal">Import CSV</button>
            <a href="dashboard.php" class="btn btn-outline-secondary">Back</a>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title">Add New Place</h5>
            <form id="addPlaceForm" class="row g-2">
                <div class="col-md-8">
                    <label for="place_name" class="form-label">Place Name</label>
                    <input type="text" id="place_name" name="placename" class="form-control" required maxlength="255" placeholder="e.g. Shop1">
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Add Place</button>
                </div>
            </form>
            <div id="addPlaceMessage" class="mt-2"></div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="d-flex justify-content-between mb-3">
                <h5 class="card-title">Places List</h5>
                <input id="searchPlaces" class="form-control small-input" placeholder="Search place name">
            </div>

            <div class="table-responsive">
                <table class="table table-striped table-hover" id="placesTable">
                    <thead class="table-primary">
                        <tr>
                            <th>PlaceID</th>
                            <th>PlaceName</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="placesTableBody">
                        <!-- Filled by fetch_map.php -->
                    </tbody>
                </table>
            </div>

            <div id="placesMessage" class="mt-2"></div>
        </div>
    </div>
</div>

<!-- Import Modal -->
<div class="modal fade" id="importModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <form id="importForm" enctype="multipart/form-data" class="needs-validation" novalidate>
        <div class="modal-header">
          <h5 class="modal-title">Import Places from CSV</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
            <div id="importAlert"></div>

            <div class="mb-3">
                <label class="form-label">CSV file</label>
                <input type="file" name="file" id="csvFile" accept=".csv,text/csv" class="form-control" required>
                <div class="form-text">CSV expected: single column PlaceName (header optional). The importer will skip duplicates.</div>
            </div>

            <div class="mb-3">
                <label class="form-label">Delimiter (optional)</label>
                <select id="delimiter" class="form-select">
                    <option value="">Auto-detect</option>
                    <option value=",">Comma (,)</option>
                    <option value=";">Semicolon (;)</option>
                    <option value="\t">Tab</option>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Preview (first rows)</label>
                <div id="importPreview" class="import-preview border rounded p-2 small">No file selected.</div>
            </div>

            <div class="alert alert-info small">
                Tip: download a sample CSV <a href="map_export.php?sample=1" class="alert-link">here</a>.
            </div>
        </div>
        <div class="modal-footer">
          <button type="button" id="doImportBtn" class="btn btn-primary">Import</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Place Modal (kept from previous) -->
<div class="modal fade" id="editPlaceModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="editPlaceForm" class="needs-validation" novalidate>
        <div class="modal-header">
          <h5 class="modal-title">Edit Place</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="edit_placeid" name="placeid" value="">
            <div class="mb-3">
                <label for="edit_placename" class="form-label">Place Name</label>
                <input type="text" id="edit_placename" name="placename" class="form-control" required maxlength="255">
            </div>
            <div id="editPlaceMessage"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Delete confirmation modal -->
<div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Confirm Delete</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        Are you sure you want to delete this place?
      </div>
      <div class="modal-footer">
        <input type="hidden" id="deletePlaceId" value="">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" id="confirmDeleteBtn" class="btn btn-danger">Delete</button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const bodyEl = document.getElementById('placesTableBody');
    const addForm = document.getElementById('addPlaceForm');
    const addMsg = document.getElementById('addPlaceMessage');
    const placesMsg = document.getElementById('placesMessage');
    const searchInput = document.getElementById('searchPlaces');
    const editModalEl = document.getElementById('editPlaceModal');
    const editModal = new bootstrap.Modal(editModalEl);
    const editForm = document.getElementById('editPlaceForm');
    const editMsg = document.getElementById('editPlaceMessage');
    const confirmDeleteModal = new bootstrap.Modal(document.getElementById('confirmDeleteModal'));
    const deletePlaceIdInput = document.getElementById('deletePlaceId');
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');

    const importModalEl = document.getElementById('importModal');
    const importModal = new bootstrap.Modal(importModalEl);
    const importForm = document.getElementById('importForm');
    const csvFileInput = document.getElementById('csvFile');
    const importPreview = document.getElementById('importPreview');
    const importAlert = document.getElementById('importAlert');
    const doImportBtn = document.getElementById('doImportBtn');
    const delimiterSelect = document.getElementById('delimiter');

    function showMessage(el, msg, type='success') {
        el.innerHTML = `<div class="alert alert-${type} py-2">${msg}</div>`;
        setTimeout(()=> el.innerHTML = '', 5000);
    }

    async function loadPlaces(search='') {
        try {
            const res = await fetch('fetch_map.php?search=' + encodeURIComponent(search), { credentials: 'same-origin' });
            if (!res.ok) throw new Error('Network error');
            const html = await res.text();
            bodyEl.innerHTML = html;
        } catch (err) {
            console.error(err);
            showMessage(placesMsg, 'Failed to load places.', 'danger');
        }
    }

    loadPlaces();

    let searchTimeout = null;
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => loadPlaces(this.value.trim()), 250);
    });

    addForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        const fd = new FormData(addForm);
        fd.append('action', 'add');

        try {
            const res = await fetch('map_action.php', { method: 'POST', credentials: 'same-origin', body: fd });
            const json = await res.json();
            if (json.success) {
                showMessage(addMsg, json.message, 'success');
                addForm.reset();
                loadPlaces(searchInput.value.trim());
            } else {
                showMessage(addMsg, json.message || 'Failed to add place', 'danger');
            }
        } catch (err) {
            console.error(err);
            showMessage(addMsg, 'Server error while adding place.', 'danger');
        }
    });

    // Delegate edit/delete clicks
    bodyEl.addEventListener('click', function(e) {
        const editBtn = e.target.closest('.edit-place-btn');
        if (editBtn) {
            const id = editBtn.dataset.placeid;
            const name = editBtn.dataset.placename || '';
            document.getElementById('edit_placeid').value = id;
            document.getElementById('edit_placename').value = name;
            editMsg.innerHTML = '';
            editModal.show();
            return;
        }
        const delBtn = e.target.closest('.delete-place-btn');
        if (delBtn) {
            deletePlaceIdInput.value = delBtn.dataset.placeid;
            confirmDeleteModal.show();
            return;
        }
    });

    editForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        const fd = new FormData(editForm);
        fd.append('action', 'edit');

        try {
            const res = await fetch('map_action.php', { method: 'POST', credentials: 'same-origin', body: fd });
            const json = await res.json();
            if (json.success) {
                showMessage(placesMsg, json.message, 'success');
                editModal.hide();
                loadPlaces(searchInput.value.trim());
            } else {
                showMessage(editMsg, json.message || 'Failed to update place', 'danger');
            }
        } catch (err) {
            console.error(err);
            showMessage(editMsg, 'Server error while updating place.', 'danger');
        }
    });

    confirmDeleteBtn.addEventListener('click', async function() {
        const id = deletePlaceIdInput.value;
        if (!id) return;
        const fd = new FormData();
        fd.append('action', 'delete');
        fd.append('placeid', id);

        try {
            const res = await fetch('map_action.php', { method: 'POST', credentials: 'same-origin', body: fd });
            const json = await res.json();
            if (json.success) {
                showMessage(placesMsg, json.message, 'success');
                loadPlaces(searchInput.value.trim());
            } else {
                showMessage(placesMsg, json.message || 'Failed to delete place', 'danger');
            }
        } catch (err) {
            console.error(err);
            showMessage(placesMsg, 'Server error while deleting place.', 'danger');
        } finally {
            confirmDeleteModal.hide();
            deletePlaceIdInput.value = '';
        }
    });

    // --- Import CSV logic: preview + upload
    csvFileInput.addEventListener('change', function() {
        importPreview.textContent = 'Loading preview...';
        importAlert.innerHTML = '';
        const file = this.files[0];
        if (!file) {
            importPreview.textContent = 'No file selected.';
            return;
        }
        // Quick client-side preview: read first 10 lines
        const reader = new FileReader();
        reader.onload = function(e) {
            const text = e.target.result.replace(/\r/g, '');
            const lines = text.split('\n').slice(0, 20);
            const rows = lines.map(l => l.trim()).filter(l => l.length > 0).slice(0, 10);
            if (rows.length === 0) {
                importPreview.textContent = 'No data found in file.';
                return;
            }
            // show as simple table
            let html = '<table class="table table-sm mb-0"><thead><tr><th>#</th><th>Row</th></tr></thead><tbody>';
            rows.forEach((r, i) => {
                html += '<tr><td>' + (i+1) + '</td><td>' + r.replace(/</g,'&lt;') + '</td></tr>';
            });
            html += '</tbody></table>';
            importPreview.innerHTML = html;
        };
        reader.onerror = function() {
            importPreview.textContent = 'Unable to read file for preview.';
        };
        reader.readAsText(file, 'UTF-8');
    });

    doImportBtn.addEventListener('click', async function() {
        importAlert.innerHTML = '';
        const file = csvFileInput.files[0];
        if (!file) {
            importAlert.innerHTML = '<div class="alert alert-danger">Please select a CSV file to import.</div>';
            return;
        }
        const fd = new FormData();
        fd.append('file', file);
        const delim = delimiterSelect.value;
        if (delim) fd.append('delimiter', delim);

        doImportBtn.disabled = true;
        doImportBtn.textContent = 'Importing...';
        try {
            const res = await fetch('map_import.php', {
                method: 'POST',
                credentials: 'same-origin',
                body: fd
            });
            const json = await res.json();
            if (json.success) {
                importAlert.innerHTML = `<div class="alert alert-success">Inserted: ${json.inserted}, Skipped: ${json.skipped}</div>`;
                if (json.errors && json.errors.length) {
                    importAlert.innerHTML += `<div class="alert alert-warning small"><strong>Notes:</strong><br>${json.errors.map(e=>e.replace(/</g,'&lt;')).join('<br>')}</div>`;
                }
                // refresh list
                loadPlaces(searchInput.value.trim());
                // close modal after short delay
                setTimeout(()=>{ importModal.hide(); }, 900);
            } else {
                importAlert.innerHTML = `<div class="alert alert-danger">Import failed: ${json.message || 'Server error'}</div>`;
            }
        } catch (err) {
            console.error(err);
            importAlert.innerHTML = '<div class="alert alert-danger">Server error during import.</div>';
        } finally {
            doImportBtn.disabled = false;
            doImportBtn.textContent = 'Import';
        }
    });
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<?php include 'footer.php'; ?>
</body>
</html>