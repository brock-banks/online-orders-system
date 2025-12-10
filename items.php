<?php
require_once 'config.php';
require_once 'functions.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

// Restrict this page to admins only

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Manage Items</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .code-input { flex: 1; }
        .qty-input { width: 100px; }
        .item-row-actions { white-space: nowrap; }
        .small-input { max-width: 220px; }
    </style>
</head>
<body>
<?php include 'header.php'; ?>

<div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="text-primary">Items Management</h2>
        <a href="dashboard.php" class="btn btn-outline-secondary">Back</a>
    </div>

    <div class="row gy-4">
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Add New Item</h5>
                    <form id="addItemForm" class="row g-2">
                        <div class="col-5">
                            <label for="item_code" class="form-label">Item Code</label>
                            <input type="text" id="item_code" name="itemcode" class="form-control" required maxlength="191" placeholder="e.g. ABC123">
                        </div>
                        <div class="col-5">
                            <label for="item_name" class="form-label">Item Name</label>
                            <input type="text" id="item_name" name="itemname" class="form-control" required maxlength="255" placeholder="Item name">
                        </div>
                        <div class="col-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">Add</button>
                        </div>
                    </form>
                    <div id="addItemMessage" class="mt-2"></div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Import / Export</h5>

                    <form id="importForm" class="mb-3">
                        <div class="mb-2">
                            <label for="importFile" class="form-label">Import from CSV (columns: itemcode, itemname)</label>
                            <input type="file" id="importFile" name="file" accept=".csv,text/csv" class="form-control">
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" id="importBtn" class="btn btn-outline-success">Import CSV</button>
                            <a href="export_items_csv.php" class="btn btn-outline-primary">Export CSV</a>
                        </div>
                    </form>

                    <div id="importMessage"></div>
                    <div class="form-text mt-2">
                        CSV rules: the first row may be a header (itemcode,itemname). If present it will be skipped. Duplicates (existing itemcode) are skipped.
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-3">
                        <h5 class="card-title">Items List</h5>
                        <input id="searchItems" class="form-control w-50 small-input" placeholder="Search item code or name">
                    </div>

                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="itemsTable">
                            <thead class="table-primary">
                                <tr>
                                    <th>ID</th>
                                    <th>Item Code</th>
                                    <th>Item Name</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="itemsTableBody">
                                <!-- Filled by fetch_items.php -->
                            </tbody>
                        </table>
                    </div>

                    <div id="itemsMessage" class="mt-2"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Item Modal -->
<div class="modal fade" id="editItemModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="editItemForm" class="needs-validation" novalidate>
        <div class="modal-header">
          <h5 class="modal-title">Edit Item</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="edit_itemid" name="itemid" value="">
            <div class="mb-3">
                <label for="edit_itemcode" class="form-label">Item Code</label>
                <input type="text" id="edit_itemcode" name="itemcode" class="form-control" required maxlength="191">
            </div>
            <div class="mb-3">
                <label for="edit_itemname" class="form-label">Item Name</label>
                <input type="text" id="edit_itemname" name="itemname" class="form-control" required maxlength="255">
            </div>
            <div id="editItemMessage"></div>
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
        Are you sure you want to delete this item?
      </div>
      <div class="modal-footer">
        <input type="hidden" id="deleteItemId" value="">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" id="confirmDeleteBtn" class="btn btn-danger">Delete</button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const itemsBody = document.getElementById('itemsTableBody');
    const addForm = document.getElementById('addItemForm');
    const addMsg = document.getElementById('addItemMessage');
    const itemsMsg = document.getElementById('itemsMessage');
    const searchInput = document.getElementById('searchItems');
    const confirmDeleteModal = new bootstrap.Modal(document.getElementById('confirmDeleteModal'));
    const deleteItemIdInput = document.getElementById('deleteItemId');
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
    const importFileInput = document.getElementById('importFile');
    const importBtn = document.getElementById('importBtn');
    const importMsg = document.getElementById('importMessage');
    const editItemModalEl = document.getElementById('editItemModal');
    const editItemModal = new bootstrap.Modal(editItemModalEl);
    const editForm = document.getElementById('editItemForm');
    const editMsg = document.getElementById('editItemMessage');

    function showMessage(element, message, type = 'success') {
        element.innerHTML = `<div class="alert alert-${type} py-2">${message}</div>`;
        setTimeout(() => { element.innerHTML = ''; }, 6000);
    }

    async function loadItems(search = '') {
        try {
            const res = await fetch('fetch_items.php?search=' + encodeURIComponent(search), { credentials: 'same-origin' });
            if (!res.ok) throw new Error('Network error');
            const html = await res.text();
            itemsBody.innerHTML = html;
        } catch (err) {
            console.error(err);
            showMessage(itemsMsg, 'Failed to load items.', 'danger');
        }
    }

    // initial load
    loadItems();

    // search
    let searchTimeout = null;
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => loadItems(this.value.trim()), 300);
    });

    // add item
    addForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        const formData = new FormData(addForm);
        formData.append('action', 'add');

        try {
            const res = await fetch('items_action.php', {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            });
            const json = await res.json();
            if (json.success) {
                showMessage(addMsg, json.message, 'success');
                addForm.reset();
                loadItems(searchInput.value.trim());
            } else {
                showMessage(addMsg, json.message || 'Failed to add item', 'danger');
            }
        } catch (err) {
            console.error(err);
            showMessage(addMsg, 'Server error while adding item.', 'danger');
        }
    });

    // import CSV
    importBtn.addEventListener('click', async function() {
        const file = importFileInput.files[0];
        if (!file) {
            showMessage(importMsg, 'Please select a CSV file to import.', 'danger');
            return;
        }

        const fd = new FormData();
        fd.append('file', file);

        try {
            const res = await fetch('items_import.php', {
                method: 'POST',
                credentials: 'same-origin',
                body: fd
            });
            const json = await res.json();
            if (json.success) {
                showMessage(importMsg, json.message, 'success');
                importFileInput.value = '';
                loadItems(searchInput.value.trim());
            } else {
                showMessage(importMsg, json.message || 'Failed to import file', 'danger');
            }
        } catch (err) {
            console.error(err);
            showMessage(importMsg, 'Server error during import.', 'danger');
        }
    });

    // delegate clicks for Edit and Delete
    itemsBody.addEventListener('click', function(e) {
        const editBtn = e.target.closest('.edit-item-btn');
        if (editBtn) {
            // populate modal
            const id = editBtn.dataset.itemid;
            const code = editBtn.dataset.itemcode || '';
            const name = editBtn.dataset.itemname || '';
            document.getElementById('edit_itemid').value = id;
            document.getElementById('edit_itemcode').value = code;
            document.getElementById('edit_itemname').value = name;
            editMsg.innerHTML = '';
            editItemModal.show();
            return;
        }

        const deleteBtn = e.target.closest('.delete-item-btn');
        if (deleteBtn) {
            const itemId = deleteBtn.dataset.itemid;
            deleteItemIdInput.value = itemId;
            confirmDeleteModal.show();
            return;
        }
    });

    // handle edit form submit
    editForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        const formData = new FormData(editForm);
        formData.append('action', 'edit');

        try {
            const res = await fetch('items_action.php', {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            });
            const json = await res.json();
            if (json.success) {
                showMessage(itemsMsg, json.message, 'success');
                editItemModal.hide();
                loadItems(searchInput.value.trim());
            } else {
                showMessage(editMsg, json.message || 'Failed to update item', 'danger');
            }
        } catch (err) {
            console.error(err);
            showMessage(editMsg, 'Server error while updating item.', 'danger');
        }
    });

    // confirm delete
    confirmDeleteBtn.addEventListener('click', async function() {
        const id = deleteItemIdInput.value;
        if (!id) return;
        const data = new FormData();
        data.append('action', 'delete');
        data.append('itemid', id);

        try {
            const res = await fetch('items_action.php', {
                method: 'POST',
                credentials: 'same-origin',
                body: data
            });
            const json = await res.json();
            if (json.success) {
                showMessage(itemsMsg, json.message, 'success');
                loadItems(searchInput.value.trim());
            } else {
                showMessage(itemsMsg, json.message || 'Failed to delete item', 'danger');
            }
        } catch (err) {
            console.error(err);
            showMessage(itemsMsg, 'Server error while deleting.', 'danger');
        } finally {
            confirmDeleteModal.hide();
            deleteItemIdInput.value = '';
        }
    });
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<?php include 'footer.php'; ?>
</body>
</html>