<?php
// LOFO - Campus Locations Management
$pageTitle = "Campus Locations";
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_guard.php';

requireAdmin();
$pdo = getDBConnection();

$stmt = $pdo->query("SELECT * FROM locations ORDER BY type ASC, name ASC");
$locations = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h3 class="fw-bold text-dark mb-1">Campus Locations</h3>
            <p class="text-muted fs-sm mb-0">Manage Union Christian College premises, buildings, and drop-off zones.</p>
        </div>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#locationModal" onclick="openNewLocationModal()">
            <i class="fa-solid fa-plus me-1"></i> Add Campus Location
        </button>
    </div>

    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 fs-sm">
                <thead class="table-light">
                    <tr>
                        <th>Location Name</th>
                        <th>Type / Zone</th>
                        <th>Date Registered</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($locations as $loc): ?>
                        <tr>
                            <td class="fw-semibold text-dark">
                                <i class="fa-solid fa-location-dot text-danger me-2"></i>
                                <?= htmlspecialchars($loc['name']) ?>
                            </td>
                            <td>
                                <span class="badge bg-secondary-subtle text-secondary border fs-xxs"><?= htmlspecialchars($loc['type']) ?></span>
                            </td>
                            <td class="text-muted fs-xs"><?= date('d M Y', strtotime($loc['created_at'])) ?></td>
                            <td class="text-end">
                                <button class="btn btn-outline-primary btn-xs py-1 px-2 fs-xxs me-1" 
                                        onclick="editLocation(<?= $loc['location_id'] ?>, '<?= addslashes(htmlspecialchars($loc['name'])) ?>', '<?= addslashes(htmlspecialchars($loc['type'])) ?>')">
                                    <i class="fa-solid fa-pen"></i> Edit
                                </button>
                                <form action="<?= url('actions/admin_actions.php') ?>" method="POST" class="d-inline" onsubmit="return confirm('Remove this campus location?');">
                                    <input type="hidden" name="action" value="delete_location">
                                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                    <input type="hidden" name="location_id" value="<?= $loc['location_id'] ?>">
                                    <button type="submit" class="btn btn-outline-danger btn-xs py-1 px-2 fs-xxs">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add/Edit Location Modal -->
<div class="modal fade" id="locationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="<?= url('actions/admin_actions.php') ?>" method="POST">
                <input type="hidden" name="action" value="save_location">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="location_id" id="modalLocId" value="0">

                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="modalLocTitle">Add Campus Location</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="modalLocName" class="form-label fs-sm fw-medium">Location / Building Name</label>
                        <input type="text" class="form-control" id="modalLocName" name="name" placeholder="e.g. MCA Block Lab, Central Library" required>
                    </div>
                    <div class="mb-3">
                        <label for="modalLocType" class="form-label fs-sm fw-medium">Location Type</label>
                        <select class="form-select" id="modalLocType" name="type" required>
                            <option value="Academic">Academic (Labs, Lecture Halls)</option>
                            <option value="Administrative">Administrative (Offices, Staff Rooms)</option>
                            <option value="Amenities">Amenities (Canteen, Common Rooms)</option>
                            <option value="Sports">Sports (Grounds, Gym)</option>
                            <option value="Security">Security & Gates</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">Save Location</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openNewLocationModal() {
    document.getElementById('modalLocId').value = '0';
    document.getElementById('modalLocTitle').textContent = 'Add Campus Location';
    document.getElementById('modalLocName').value = '';
    document.getElementById('modalLocType').value = 'Academic';
}

function editLocation(id, name, type) {
    document.getElementById('modalLocId').value = id;
    document.getElementById('modalLocTitle').textContent = 'Edit Location';
    document.getElementById('modalLocName').value = name;
    document.getElementById('modalLocType').value = type;
    new bootstrap.Modal(document.getElementById('locationModal')).show();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
