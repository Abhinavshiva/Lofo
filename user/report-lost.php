<?php
// LOFO - Report Lost Item Page
$pageTitle = "Report Lost Item";

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/notification_helper.php';

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
    /* LOFO - Report Lost Item Background */
    .report-page {
        min-height: 100vh;
        background-image:
            linear-gradient(
                135deg,
                rgba(15, 23, 42, 0.82),
                rgba(37, 99, 235, 0.60)
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
                                class="stat-icon stat-icon-amber"
                                style="width:48px;height:48px;font-size:1.3rem;"
                            >
                                <i class="fa-solid fa-circle-question"></i>
                            </div>

                            <div>
                                <h3 class="fw-bold mb-1">
                                    Report a Lost Item
                                </h3>

                                <p class="text-white-50 fs-sm mb-0">
                                    Fill in accurate details to help our smart
                                    algorithm find your item on campus.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Form Card -->
                <div class="card shadow-sm border-0">
                    <div class="card-body p-4 p-md-5">

                        <form
                            action="<?= url('actions/report_lost.php') ?>"
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
                                    class="badge bg-primary rounded-circle"
                                    style="width:22px;height:22px;display:inline-flex;align-items:center;justify-content:center;"
                                >
                                    1
                                </span>

                                Item Information
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
                                        placeholder="e.g. Casio Scientific Calculator fx-991EX, Fastrack Watch..."
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
                                        Detailed Description & Identifying Marks
                                        <span class="text-danger">*</span>
                                    </label>

                                    <textarea
                                        class="form-control"
                                        id="description"
                                        name="description"
                                        rows="4"
                                        placeholder="Describe color, brand, model, scratches, stickers, covers, contents, unique marks..."
                                        required
                                    ></textarea>

                                    <div class="form-text fs-xxs">
                                        Detailed descriptions significantly increase
                                        the confidence score of automated matching!
                                    </div>
                                </div>
                            </div>

                            <!-- Section 2: Location & Date -->
                            <h6 class="fw-bold text-dark mb-3 d-flex align-items-center gap-2">
                                <span
                                    class="badge bg-primary rounded-circle"
                                    style="width:22px;height:22px;display:inline-flex;align-items:center;justify-content:center;"
                                >
                                    2
                                </span>

                                Incident Location & Date
                            </h6>

                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label
                                        for="location_lost"
                                        class="form-label fs-sm fw-medium"
                                    >
                                        Campus Location Where Lost
                                        <span class="text-danger">*</span>
                                    </label>

                                    <input
                                        type="text"
                                        class="form-control"
                                        id="location_lost"
                                        name="location_lost"
                                        list="locationOptions"
                                        placeholder="Select or type campus location..."
                                        required
                                    >

                                    <datalist id="locationOptions">
                                        <?php foreach ($locations as $loc): ?>
                                            <option value="<?= htmlspecialchars($loc['name']) ?>">
                                        <?php endforeach; ?>
                                    </datalist>

                                    <div class="form-text fs-xxs">
                                        e.g. MCA Block Lab, Central Library,
                                        Canteen, Auditorium
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label
                                        for="date_lost"
                                        class="form-label fs-sm fw-medium"
                                    >
                                        Estimated Date Lost
                                        <span class="text-danger">*</span>
                                    </label>

                                    <input
                                        type="date"
                                        class="form-control"
                                        id="date_lost"
                                        name="date_lost"
                                        max="<?= date('Y-m-d') ?>"
                                        value="<?= date('Y-m-d') ?>"
                                        required
                                    >
                                </div>
                            </div>

                            <!-- Section 3: Photo Upload -->
                            <h6 class="fw-bold text-dark mb-3 d-flex align-items-center gap-2">
                                <span
                                    class="badge bg-primary rounded-circle"
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
                                    <span class="text-muted">
                                        (JPG, PNG, WebP - max 5MB) (Optional)
                                    </span>
                                </label>

                                <input
                                    class="form-control"
                                    type="file"
                                    id="item_image"
                                    name="item_image"
                                    accept="image/*"
                                    data-preview-target="#lostImagePreview"
                                >

                                <div id="lostImagePreview"></div>
                            </div>

                            <!-- Form Buttons -->
                            <div class="pt-3 border-top d-flex gap-2">
                                <button
                                    type="submit"
                                    class="btn btn-warning fw-semibold px-4 py-2 shadow-sm text-dark"
                                >
                                    <i class="fa-solid fa-paper-plane me-1"></i>
                                    Submit Lost Report
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