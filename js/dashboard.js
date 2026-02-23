// Move these OUTSIDE the DOMContentLoaded
function toggleFiscalDropdown() {
    const dropdown = document.getElementById('fiscal-dropdown');
    dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
}

function filterByYear(year) {
    const items = document.querySelectorAll('.quarter-item');
    items.forEach(item => {
        const btn = item.querySelector('.quarter-btn');
        const fiscalMatch = btn.textContent.match(/Fiscal Year (\d{4})/);
        const fiscal = fiscalMatch ? fiscalMatch[1] : '';
        item.style.display = (!year || fiscal === year) ? 'flex' : 'none';
    });

    // Auto-close dropdown after selection
    document.getElementById('fiscal-dropdown').style.display = 'none';
}

document.addEventListener('DOMContentLoaded', function () {
    const editToggle = document.getElementById('edit-toggle');
    const deleteToggle = document.getElementById('delete-toggle');
    const container = document.querySelector('.quarter-buttons');

    // Edit icon click
    document.querySelectorAll('.edit-icon-btn').forEach(btn => {
    btn.addEventListener('click', function () {
        document.getElementById('edit-id').value = this.dataset.id;
        document.getElementById('edit-quarter').value = this.dataset.quarter;
        document.getElementById('edit-fiscal').value = this.dataset.fiscal;
        document.getElementById('edit-title').value = this.dataset.title;
        document.getElementById('edit-date-duration').value = this.dataset.dateDuration;
        document.getElementById('edit-male').value = this.dataset.male;
        document.getElementById('edit-female').value = this.dataset.female;
        document.getElementById('edit-department').value = this.dataset.dept;
        document.getElementById('edit-location').value = this.dataset.location;
        document.getElementById('edit-extensionists').value = this.dataset.extensionists;
        document.getElementById('edit-partners').value = this.dataset.partners;
        document.getElementById('edit-budget').value = this.dataset.budget;
        document.getElementById('edit-fund').value = this.dataset.fund;

        openModal('edit-modal');
    });
});

    // Delete icon click
    document.querySelectorAll('.delete-icon-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            document.getElementById('delete-id').value = btn.dataset.id;
            document.getElementById('delete-modal').classList.add('active');
            document.body.classList.add('modal-open');
        });
    });

    // Close modal on outside click
    window.onclick = function(event) {
        if (event.target.classList.contains('modal-overlay')) {
            event.target.classList.remove('active');
            document.body.classList.remove('modal-open');
        }
    };
});