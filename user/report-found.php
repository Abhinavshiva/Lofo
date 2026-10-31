<?php
// LOFO - Report Found Item Page
$pageTitle = "Report Found Item";

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_guard.php';

$pdo = getDBConnection();

$categories = $pdo->query("
    SELECT *
    FROM categories
    ORDER BY name ASC
")->fetchAll();

$locations = $pdo->query("
    SELECT *
    FROM locations
    ORDER BY name ASC
")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<style>
    /* LOFO - Report Found Item Background */
    .report-page {
        min-height: 100vh;
        background-image:
            linear-gradient(
                135deg,
                rgba(15, 23, 42, 0.82),
                rgba(22, 95, 163, 0.6)
            ),
            url("../assets/images/report-bg.jpg");
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
        padding-top: 1px;
        padding-bottom: 30px;
    }

    /* Keep the form readable over the background */
    .report-page .card {
        background-color: rgba(255, 255, 255, 0.97);
    }

    /* Keep the header card dark */
    .report-page .bg-dark-primary {
        background-color: #0f172a !important;
    }
</style>

<div class="report-page">
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">

                <!-- Header Card -->
                <div class="card shadow-sm border-0 mb-4 bg-dark-primary text-white">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center gap-3">
                            <div
                                class="stat-icon stat-icon-green"
                                style="width:48px;height:48px;font-size:1.3rem;"
                            >
                                <i class="fa-solid fa-hand-holding-heart"></i>
                            </div>

                            <div>
                                <h3 class="fw-bold mb-1">
                                    Report a Found Item
                                </h3>

                                <p class="text-white-50 fs-sm mb-0">
                                    Help reunite a lost possession with its
                                    rightful owner at Union Christian College.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Form Card -->
                <div class="card shadow-sm border-0">
                    <div class="card-body p-4 p-md-5">

                        <form
                            action="<?= url('actions/report_found.php') ?>"
                            method="POST"
                            enctype="multipart/form-data"
                        >
                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= csrfToken() ?>"
                            >

                            <!-- Section 1: Item Details -->
                            <h6 class="fw-bold text-dark mb-3 d-flex align-items-center gap-2">
                                <span
                                    class="badge bg-success rounded-circle"
                                    style="width:22px;height:22px;display:inline-flex;align-items:center;justify-content:center;"
                                >
                                    1
                                </span>

                                Found Item Details
                            </h6>

                            <div class="row g-3 mb-4">
                                <div class="col-md-8">
                                    <label
                                        for="item_name"
                                        class="form-label fs-sm fw-medium"
                                    >
                                        Item Name
                                        <span class="text-danger">*</span>
                                    </label>

                                    <input
                                        type="text"
                                        class="form-control"
                                        id="item_name"
                                        name="item_name"
                                        placeholder="e.g. Set of Honda Keys, Student ID Card, Blue Umbrella..."
                                        required
                                    >
                                </div>

                                <div class="col-md-4">
                                    <label
                                        for="category_id"
                                        class="form-label fs-sm fw-medium"
                                    >
                                        Category
                                        <span class="text-danger">*</span>
                                    </label>

                                    <select
                                        class="form-select"
                                        id="category_id"
                                        name="category_id"
                                        required
                                    >
                                        <option value="">
                                            -- Select Category --
                                        </option>

                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?= $cat['category_id'] ?>">
                                                <?= htmlspecialchars($cat['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-12">
                                    <label
                                        for="description"
                                        class="form-label fs-sm fw-medium"
                                    >
                                        General Description
                                        <span class="text-danger">*</span>
                                    </label>

                                    <textarea
                                        class="form-control"
                                        id="description"
                                        name="description"
                                        rows="3"
                                        placeholder="Describe general appearance, brand, color, condition..."
                                        required
                                    ></textarea>

                                    <div class="form-text fs-xxs text-primary">
                                        <i class="fa-solid fa-lightbulb me-1"></i>
                                        Tip: Keep ultra-specific secret identifiers
                                        such as exact serial numbers or wallet
                                        cash amount private so claimants can
                                        verify them in Stage 1.
                                    </div>
                                </div>
                            </div>

                            <!-- Section 2: Location & Date -->
                            <h6 class="fw-bold text-dark mb-3 d-flex align-items-center gap-2">
                                <span
                                    class="badge bg-success rounded-circle"
                                    style="width:22px;height:22px;display:inline-flex;align-items:center;justify-content:center;"
                                >
                                    2
                                </span>

                                Recovery & Campus Location
                            </h6>

                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label
                                        for="location_found"
                                        class="form-label fs-sm fw-medium"
                                    >
                                        Campus Location Where Found
                                        <span class="text-danger">*</span>
                                    </label>

                                    <input
                                        type="text"
                                        class="form-control"
                                        id="location_found"
                                        name="location_found"
                                        list="locationOptions"
                                        placeholder="Select or type location..."
                                        required
                                    >

                                    <datalist id="locationOptions">
                                        <?php foreach ($locations as $loc): ?>
                                            <option value="<?= htmlspecialchars($loc['name']) ?>">
                                            </option>
                                        <?php endforeach; ?>
                                    </datalist>
                                </div>

                                <div class="col-md-6">
                                    <label
                                        for="date_found"
                                        class="form-label fs-sm fw-medium"
                                    >
                                        Date Found
                                        <span class="text-danger">*</span>
                                    </label>

                                    <input
                                        type="date"
                                        class="form-control"
                                        id="date_found"
                                        name="date_found"
                                        max="<?= date('Y-m-d') ?>"
                                        value="<?= date('Y-m-d') ?>"
                                        required
                                    >
                                </div>
                            </div>

                            <!-- Section 3: Photo Upload -->
<h6 class="fw-bold text-dark mb-3 d-flex align-items-center gap-2">
    <span
        class="badge bg-success rounded-circle"
        style="width:22px;height:22px;display:inline-flex;align-items:center;justify-content:center;"
    >
        3
    </span>

    Item Photograph
</h6>

<div class="mb-4">

    <label
        for="item_image"
        class="form-label fs-sm fw-medium"
    >
        Upload Image
        <span class="text-danger">*</span>
    </label>

    <input
        class="form-control"
        type="file"
        id="item_image"
        name="item_image"
        accept="image/*"
        data-preview-target="#foundImagePreview"
        required
    >

    <div class="form-text fs-xxs text-muted">
        A clear photograph of the found item is required.
    </div>

    <div id="foundImagePreview"></div>

</div>
                            <!-- Form Buttons -->
                            <div class="pt-3 border-top d-flex gap-2">
                                <button
                                    type="submit"
                                    class="btn btn-success fw-semibold px-4 py-2 shadow-sm"
                                >
                                    <i class="fa-solid fa-check-circle me-1"></i>
                                    Register Found Item
                                </button>

                                <a
                                    href="<?= url('user/dashboard.php') ?>"
                                    class="btn btn-outline-secondary px-4 py-2"
                                >
                                    Cancel
                                </a>
                            </div>
                        </form>

                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>