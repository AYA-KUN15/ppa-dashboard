document.addEventListener('DOMContentLoaded', function () {
    const editToggle = document.getElementById('edit-toggle');
    const deleteToggle = document.getElementById('delete-toggle');
    const container = document.querySelector('.quarter-buttons');


    // Edit icon click
    document.querySelectorAll('.edit-icon-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            document.getElementById('edit-id').value = btn.dataset.id;
            document.getElementById('edit-quarter').value = btn.dataset.quarter;
            document.getElementById('edit-fiscal').value = btn.dataset.fiscal;
            document.getElementById('edit-modal').classList.add('active');
            document.body.classList.add('modal-open');
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

    // Close modal on outside click or close button
    window.onclick = function(event) {
        if (event.target.classList.contains('modal-overlay')) {
            event.target.classList.remove('active');
            document.body.classList.remove('modal-open');
        }
    };

    function toggleFiscalDropdown() {
    const dropdown = document.getElementById('fiscal-dropdown');
    dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
}

function filterByYear(year) {
    const items = document.querySelectorAll('.quarter-item');
    items.forEach(item => {
        const btn = item.querySelector('.quarter-btn');
        const fiscal = btn.textContent.match(/Fiscal Year (\d{4})/)?.[1];
        item.style.display = (!year || fiscal === year) ? 'flex' : 'none';
    });

    // Optional: close dropdown after selection
    document.getElementById('fiscal-dropdown').style.display = 'none';
}
});