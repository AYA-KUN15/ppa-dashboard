// Global functions (outside DOMContentLoaded so they can be called from HTML if needed)
function toggleFiscalDropdown() {
    const dropdown = document.getElementById('fiscal-dropdown');
    if (dropdown) {
        dropdown.style.display = dropdown.style.display === 'none' || dropdown.style.display === '' ? 'block' : 'none';
    }
}

function filterByYear(year) {
    const items = document.querySelectorAll('.quarter-item');
    items.forEach(item => {
        const btn = item.querySelector('.quarter-btn');
        if (!btn) return;

        // Current subtitle is duration dates, e.g. "Mar 01, 2025 – Aug 31, 2025"
        // Extract year from end date (last 4 digits before closing quote)
        const subtitle = btn.querySelector('.quarter-btn-subtitle')?.textContent || '';
        const yearMatch = subtitle.match(/\d{4}/g); // find all 4-digit years
        const endYear = yearMatch && yearMatch.length > 0 ? yearMatch[yearMatch.length - 1] : '';

        // Show if no year filter or if end year matches
        item.style.display = (!year || endYear === year) ? 'flex' : 'none';
    });

    // Auto-close dropdown
    const dropdown = document.getElementById('fiscal-dropdown');
    if (dropdown) dropdown.style.display = 'none';
}

document.addEventListener('DOMContentLoaded', function () {
    /*
    // Edit icon click (opens edit modal)
    document.querySelectorAll('.edit-icon-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            // Populate form fields from data attributes
            document.getElementById('edit-id').value = this.dataset.id || '';
            document.getElementById('edit-quarter').value = this.dataset.quarter || '';
            document.getElementById('edit-fiscal').value = this.dataset.fiscal || '';
            document.getElementById('edit-title').value = this.dataset.title || '';
            document.getElementById('edit-date-duration').value = this.dataset.dateDuration || '';
            document.getElementById('edit-male').value = this.dataset.male || '';
            document.getElementById('edit-female').value = this.dataset.female || '';
            document.getElementById('edit-department').value = this.dataset.dept || '';
            document.getElementById('edit-location').value = this.dataset.location || '';
            document.getElementById('edit-extensionists').value = this.dataset.extensionists || '';
            document.getElementById('edit-partners').value = this.dataset.partners || '';
            document.getElementById('edit-budget').value = this.dataset.budget || '';
            document.getElementById('edit-fund').value = this.dataset.fund || '';

            openModal('edit-modal');
        });
    });
    */

    // Delete icon click (opens delete confirmation modal)
    document.querySelectorAll('.delete-icon-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            document.getElementById('delete-id').value = this.dataset.id || '';
            document.getElementById('delete-mode').value = this.dataset.mode || '';
            openModal('delete-modal');
        });
    });

    // Close any modal when clicking outside
    window.addEventListener('click', function(event) {
        if (event.target.classList.contains('modal-overlay')) {
            closeModal(event.target.id);
        }
    });
});