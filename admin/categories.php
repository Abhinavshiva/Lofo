<?php
// LOFO - Categories Management
$pageTitle = "Categories Management";
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_guard.php';

requireAdmin();
$pdo = getDBConnection();

$stmt = $pdo->query("
    SELECT c.*, 
           (SELECT COUNT(*) FROM lost_items l WHERE l.category_id = c.category_id) as lost_count,
           (SELECT COUNT(*) FROM found_items f WHERE f.category_id = c.category_id) as found_count
    FROM categories c 
    ORDER BY c.name ASC
");
$categories = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h3 class="fw-bold text-dark mb-1">Categories Management</h3>
            <p class="text-muted fs-sm mb-0">Define and manage item classifications across the UCC LOFO repository.</p>
        </div>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#categoryModal" onclick="openNewCategoryModal()">
            <i class="fa-solid fa-plus me-1"></i> Add New Category
        </button>
    </div>

    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 fs-sm">
                <thead class="table-light">
                    <tr>
                        <th>Icon</th>
                        <th>Category Name</th>
                        <th>Description</th>
                        <th>Associated Items</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $cat): ?>
                        <tr>
                            <td>
                                <div class="stat-icon stat-icon-blue" style="width:36px;height:36px;font-size:1rem;">
                                    <i class="fa-solid <?= htmlspecialchars($cat['icon'] ?: 'fa-box') ?>"></i>
                                </div>
                            </td>
                            <td class="fw-bold text-dark"><?= htmlspecialchars($cat['name']) ?></td>
                            <td class="text-muted fs-xs"><?= htmlspecialchars($cat['description']) ?></td>
                            <td>
                                <span class="badge bg-danger-subtle text-danger fs-xxs me-1"><?= $cat['lost_count'] ?> Lost</span>
                                <span class="badge bg-success-subtle text-success fs-xxs"><?= $cat['found_count'] ?> Found</span>
                            </td>
                            <td class="text-end">
                                <button class="btn btn-outline-primary btn-xs py-1 px-2 fs-xxs me-1" 
                                        onclick="editCategory(<?= $cat['category_id'] ?>, '<?= addslashes(htmlspecialchars($cat['name'])) ?>', '<?= addslashes(htmlspecialchars($cat['icon'])) ?>', '<?= addslashes(htmlspecialchars($cat['description'])) ?>')">
                                    <i class="fa-solid fa-pen"></i> Edit
                                </button>
                                <?php if ($cat['lost_count'] == 0 && $cat['found_count'] == 0): ?>
                                    <form action="<?= url('actions/admin_actions.php') ?>" method="POST" class="d-inline" onsubmit="return confirm('Delete this category?');">
                                        <input type="hidden" name="action" value="delete_category">
                                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                        <input type="hidden" name="category_id" value="<?= $cat['category_id'] ?>">
                                        <button type="submit" class="btn btn-outline-danger btn-xs py-1 px-2 fs-xxs">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add/Edit Category Modal -->
<div class="modal fade" id="categoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="<?= url('actions/admin_actions.php') ?>" method="POST">
                <input type="hidden" name="action" value="save_category">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="category_id" id="modalCatId" value="0">

                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="modalCatTitle">Add Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="modalCatName" class="form-label fs-sm fw-medium">Category Name</label>
                        <input type="text" class="form-control" id="modalCatName" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="modalCatIcon" class="form-label fs-sm fw-medium">FontAwesome Icon Class</label>
                        <input type="text" class="form-control" id="modalCatIcon" name="icon" placeholder="e.g. fa-laptop, fa-key, fa-wallet" required>
                    </div>
                    <div class="mb-3">
                        <label for="modalCatDesc" class="form-label fs-sm fw-medium">Description</label>
                        <textarea class="form-control" id="modalCatDesc" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">Save Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openNewCategoryModal() {
    document.getElementById('modalCatId').value = '0';
    document.getElementById('modalCatTitle').textContent = 'Add Category';
    document.getElementById('modalCatName').value = '';
    document.getElementById('modalCatIcon').value = 'fa-box';
    document.getElementById('modalCatDesc').value = '';
}

function editCategory(id, name, icon, desc) {
    document.getElementById('modalCatId').value = id;
    document.getElementById('modalCatTitle').textContent = 'Edit Category';
    document.getElementById('modalCatName').value = name;
    document.getElementById('modalCatIcon').value = icon;
    document.getElementById('modalCatDesc').value = desc;
    new bootstrap.Modal(document.getElementById('categoryModal')).show();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
