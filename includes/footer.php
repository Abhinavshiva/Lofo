<?php
// LOFO - Footer Component
?>
<footer class="bg-dark text-white-50 mt-auto pt-5 pb-4 border-top border-secondary">
    <div class="container">
        <div class="row g-4 mb-4">
            <div class="col-lg-4 col-md-6">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <div class="brand-icon-footer">
                        <i class="fa-solid fa-compass-drafting text-primary fs-4"></i>
                    </div>
                    <div>
                        <h5 class="text-white mb-0 fw-bold">LOFO</h5>
                        <small class="text-white-50 fs-xs">Lost and Found Management System</small>
                    </div>
                </div>
                <p class="fs-sm text-white-50 mb-3">
                    A centralized, secure digital platform designed to streamline lost and found recovery for students, faculty, and administration at Union Christian College, Aluva.
                </p>
                <div class="d-flex gap-2 text-white-50 fs-sm">
                    <i class="fa-solid fa-building-columns mt-1 text-primary"></i>
                    <span>Union Christian College, Aluva, Kerala</span>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                
            </div>

            <div class="col-lg-5 col-md-12">
                    <h6 class="text-white fw-semibold mb-3"><i class="fa-solid fa-link me-1 text-primary"></i> Quick Navigation</h6>
                <ul class="list-unstyled fs-sm space-y-2 mb-0">
                    <li class="mb-2"><a href="<?= url('index.php') ?>" class="text-decoration-none text-white-50 hover-light"><i class="fa-solid fa-angle-right me-1 fs-xs"></i> Browse All Items</a></li>
                    <li class="mb-2"><a href="<?= url('user/report-lost.php') ?>" class="text-decoration-none text-white-50 hover-light"><i class="fa-solid fa-angle-right me-1 fs-xs"></i> Report Lost Item</a></li>
                    <li class="mb-2"><a href="<?= url('user/report-found.php') ?>" class="text-decoration-none text-white-50 hover-light"><i class="fa-solid fa-angle-right me-1 fs-xs"></i> Report Found Item</a></li>
                    <li class="mb-2"><a href="<?= url('user/my-claims.php') ?>" class="text-decoration-none text-white-50 hover-light"><i class="fa-solid fa-angle-right me-1 fs-xs"></i> Claim Tracking & Status</a></li>
                </ul>
                
            </div>
        </div>

        <hr class="border-secondary my-3">

        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-2 fs-xs text-white-50">
            <div>&copy; <?= date('Y') ?> LOFO - Union Christian College, Aluva. All rights reserved.</div>
            <div class="d-flex gap-3">
                <span>Fast & Secure</span> &bull;
                <span>Automated Matching</span> &bull;
                <span>Zero Data Loss</span>
            </div>
        </div>
    </div>
</footer>

<!-- Bootstrap 5.3 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- Custom JavaScript (with auto cache-busting) -->
<script src="<?= asset('js/app.js') ?>?v=<?= file_exists(__DIR__ . '/../assets/js/app.js') ? filemtime(__DIR__ . '/../assets/js/app.js') : time() ?>"></script>
</body>
</html>
