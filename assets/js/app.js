// LOFO - JavaScript Application Logic

document.addEventListener('DOMContentLoaded', function() {
    // 1. Auto-dismiss alerts after 6 seconds
    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            if (bsAlert) {
                bsAlert.close();
            }
        }, 6000);
    });

    // 2. Image File Upload Preview
    const imageInputs = document.querySelectorAll('input[type="file"][data-preview-target]');
    imageInputs.forEach(input => {
        input.addEventListener('change', function() {
            const targetSelector = this.getAttribute('data-preview-target');
            const previewContainer = document.querySelector(targetSelector);
            if (!previewContainer) return;

            const file = this.files[0];
            if (file) {
                // Check file size (max 5MB)
                if (file.size > 5 * 1024 * 1024) {
                    alert('Selected image exceeds the 5MB maximum limit. Please choose a smaller file.');
                    this.value = '';
                    return;
                }

                const reader = new FileReader();
                reader.onload = function(e) {
                    previewContainer.innerHTML = `
                        <div class="position-relative d-inline-block mt-2">
                            <img src="${e.target.result}" class="img-thumbnail rounded shadow-sm" style="max-height: 200px; max-width: 100%; object-fit: contain;">
                            <button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 m-1 rounded-circle p-1" style="line-height:1; width:24px; height:24px;" onclick="clearImagePreview('${input.id}', '${targetSelector}')">
                                &times;
                            </button>
                        </div>
                    `;
                };
                reader.readAsDataURL(file);
            }
        });
    });

    // 3. Instant Search & Filter on Catalog (index.php)
    const searchInput = document.getElementById('catalogSearchInput');
    const categoryFilter = document.getElementById('catalogCategoryFilter');
    const statusFilter = document.getElementById('catalogStatusFilter');
    const itemCards = document.querySelectorAll('.catalog-item-card');
    const noResultsMsg = document.getElementById('noCatalogResults');

    function filterCatalog() {
        if (!itemCards.length) return;

        const query = searchInput ? searchInput.value.toLowerCase().trim() : '';
        const selectedCat = categoryFilter ? categoryFilter.value : 'all';
        const selectedStatus = statusFilter ? statusFilter.value : 'all';

        let visibleCount = 0;

        itemCards.forEach(card => {
            const itemName = (card.getAttribute('data-name') || '').toLowerCase();
            const itemDesc = (card.getAttribute('data-desc') || '').toLowerCase();
            const itemLocation = (card.getAttribute('data-location') || '').toLowerCase();
            const itemCat = card.getAttribute('data-category') || '';
            const itemStatus = card.getAttribute('data-status') || '';
            const itemType = card.getAttribute('data-type') || ''; // 'lost' or 'found'

            const matchesQuery = !query || 
                itemName.includes(query) || 
                itemDesc.includes(query) || 
                itemLocation.includes(query);

            const matchesCategory = (selectedCat === 'all' || itemCat === selectedCat);

            let matchesStatus = true;
            if (selectedStatus === 'lost') {
                matchesStatus = (itemType === 'lost');
            } else if (selectedStatus === 'found') {
                matchesStatus = (itemType === 'found');
            } else if (selectedStatus === 'available') {
                matchesStatus = (itemStatus === 'Available');
            } else if (selectedStatus === 'claimed') {
                matchesStatus = (itemStatus === 'Claimed' || itemStatus === 'Returned');
            }

            if (matchesQuery && matchesCategory && matchesStatus) {
                card.style.display = '';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });

        if (noResultsMsg) {
            noResultsMsg.classList.toggle('d-none', visibleCount > 0);
        }

        const countBadge = document.getElementById('catalogVisibleCount');
        if (countBadge) {
            countBadge.textContent = visibleCount;
        }
    }

    if (searchInput) searchInput.addEventListener('input', filterCatalog);
    if (categoryFilter) categoryFilter.addEventListener('change', filterCatalog);
    if (statusFilter) statusFilter.addEventListener('change', filterCatalog);

    // Filter pill buttons
    const pillButtons = document.querySelectorAll('.catalog-pill-btn');
    pillButtons.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            pillButtons.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            if (statusFilter) {
                statusFilter.value = this.getAttribute('data-filter-val');
                filterCatalog();
            }
        });
    });
});

function clearImagePreview(inputId, containerSelector) {
    const input = document.getElementById(inputId);
    const container = document.querySelector(containerSelector);
    if (input) input.value = '';
    if (container) container.innerHTML = '';
}
