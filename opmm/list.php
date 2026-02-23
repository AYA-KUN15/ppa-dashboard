<?php
// list.php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php");
    exit;
}

require_once '../config/db.php';

try {
    $stmt = $pdo->query("
        SELECT id, fiscal_year, quarter, title, date_duration,
               beneficiaries_male, beneficiaries_female, beneficiaries_department,
               location, extensionists, partner_agencies, budget_allocation, source_of_fund,
               frequency_monitoring, created_at
        FROM ppa_entries
        WHERE status = 'active'
        ORDER BY created_at DESC
    ");
    $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
    $entries = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PPA Dashboard</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
</head>

<body>

    <header class="top-bar">
        <div class="logo-container">
            <img src="../assets/bsu-logo.jpg" alt="BatStateU Logo" class="logo">
            <span class="logo-text">PPA Dashboard</span>
        </div>
        <nav class="main-nav">
            <a href="../index.php" class="nav-button">Home</a>
            <a href="list.php" class="nav-button active">PPA</a>
            <a href="../logout.php" class="nav-button logout">Logout</a>
        </nav>
    </header>

    <main class="dashboard-content">
        <div class="filter-actions">
            <button class="filter-button" onclick="openModal('filter-modal')">Filter</button>
            <div class="action-buttons">
                <a href="add.php" class="action-btn add">Add New PPA</a>
            </div>
        </div>

        <div class="quarter-scroll-container">
            <div class="quarter-buttons edit-mode-container" id="ppa-list">
                <?php if (!empty($error)): ?>
                    <p class="error"><?= htmlspecialchars($error) ?></p>
                <?php elseif (empty($entries)): ?>
                    <p>No PPA entries found yet.</p>
                <?php else: ?>
                    <?php foreach ($entries as $entry): ?>
                        <div class="quarter-item"
                             data-frequency="<?= htmlspecialchars($entry['frequency_monitoring'] ?? '') ?>">
                            <button class="quarter-btn" 
                                    onclick="window.location.href='view.php?id=<?= $entry['id'] ?>'"
                                    title="<?= htmlspecialchars($entry['title']) ?>">
                                <span class="quarter-btn-title"><?= htmlspecialchars($entry['title']) ?></span>
                                <span class="quarter-btn-subtitle">
                                    <?= htmlspecialchars($entry['quarter']) ?> Quarter, FY <?= htmlspecialchars($entry['fiscal_year']) ?>
                                    <?= $entry['frequency_monitoring'] ? ' · ' . htmlspecialchars($entry['frequency_monitoring']) : '' ?>
                                </span>
                            </button>

                            <button class="action-icon edit-icon-btn" 
                                    data-id="<?= $entry['id'] ?>"
                                    data-quarter="<?= htmlspecialchars($entry['quarter']) ?>"
                                    data-fiscal="<?= htmlspecialchars($entry['fiscal_year']) ?>"
                                    data-title="<?= htmlspecialchars($entry['title'] ?? '') ?>"
                                    data-date-duration="<?= htmlspecialchars($entry['date_duration'] ?? '') ?>"
                                    data-male="<?= $entry['beneficiaries_male'] ?? 0 ?>"
                                    data-female="<?= $entry['beneficiaries_female'] ?? 0 ?>"
                                    data-dept="<?= htmlspecialchars($entry['beneficiaries_department'] ?? '') ?>"
                                    data-location="<?= htmlspecialchars($entry['location'] ?? '') ?>"
                                    data-extensionists="<?= htmlspecialchars($entry['extensionists'] ?? '') ?>"
                                    data-partners="<?= htmlspecialchars($entry['partner_agencies'] ?? '') ?>"
                                    data-budget="<?= $entry['budget_allocation'] ?? 0 ?>"
                                    data-fund="<?= htmlspecialchars($entry['source_of_fund'] ?? '') ?>"
                                    data-frequency="<?= htmlspecialchars($entry['frequency_monitoring'] ?? '') ?>"
                                    title="Edit entry">
                                <span class="material-icons">edit</span>
                            </button>

                            <button class="action-icon delete-icon-btn" 
                                    data-id="<?= $entry['id'] ?>"
                                    title="Delete entry">
                                <span class="material-icons">delete</span>
                            </button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="content-placeholder">
            <p>Select a PPA entry to view details.</p>
        </div>
    </main>

    <!-- Filter Modal -->
    <div id="filter-modal" class="modal-overlay">
        <div class="modal-box">
            <span class="close-modal" onclick="closeModal('filter-modal')">×</span>
            <h2>Filter PPA Entries</h2>

            <form id="filter-form">
                <label for="filter-frequency">Frequency of Monitoring</label>
                <select id="filter-frequency" name="frequency">
                    <option value="">All Frequencies</option>
                    <option value="Monthly">Monthly</option>
                    <option value="Quarterly">Quarterly</option>
                    <option value="Semi-annual">Semi-annual</option>
                    <option value="Annual">Annual</option>
                </select>

                <div class="modal-actions">
                    <button type="submit">Apply Filter</button>
                    <button type="button" onclick="closeModal('filter-modal')">Cancel</button>
                    <button type="button" id="clear-filter">Clear Filter</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="edit-modal" class="modal-overlay">
        <div class="modal-box">
            <span class="close-modal" onclick="closeModal('edit-modal')">×</span>
            <h2>Edit PPA Entry</h2>
            <form id="edit-form" method="POST" action="edit.php">
                <input type="hidden" name="id" id="edit-id">

                <label for="edit-quarter">Quarter</label>
                <select name="quarter" id="edit-quarter" required>
                    <option value="1st">1st Quarter</option>
                    <option value="2nd">2nd Quarter</option>
                    <option value="3rd">3rd Quarter</option>
                    <option value="4th">4th Quarter</option>
                </select>

                <label for="edit-fiscal">Fiscal Year (YYYY)</label>
                <input type="text" name="fiscal_year" id="edit-fiscal" required pattern="\d{4}" maxlength="4">

                <label for="edit-title">Title of Project/Program/Activity</label>
                <input type="text" name="title" id="edit-title" required>

                <label for="edit-date-duration">Date / Duration</label>
                <input type="text" name="date_duration" id="edit-date-duration" required placeholder="e.g., July 6, 2025 / 8 hrs">

                <label for="edit-male">No. of Beneficiaries (Male)</label>
                <input type="number" name="beneficiaries_male" id="edit-male" min="0" required>

                <label for="edit-female">No. of Beneficiaries (Female)</label>
                <input type="number" name="beneficiaries_female" id="edit-female" min="0" required>

                <label for="edit-department">Beneficiary Department / Program</label>
                <input type="text" name="beneficiaries_department" id="edit-department">

                <label for="edit-location">Location</label>
                <input type="text" name="location" id="edit-location" required>

                <label for="edit-extensionists">Extensionists</label>
                <input type="text" name="extensionists" id="edit-extensionists" required>

                <label for="edit-partners">Partner Agencies</label>
                <input type="text" name="partner_agencies" id="edit-partners" placeholder="e.g., LGU Lipa City, DSWD">

                <label for="edit-frequency">Frequency of Monitoring</label>
                <select name="frequency_monitoring" id="edit-frequency" required>
                    <option value="">Select frequency</option>
                    <option value="Monthly">Monthly</option>
                    <option value="Quarterly">Quarterly</option>
                    <option value="Semi-annual">Semi-annual</option>
                    <option value="Annual">Annual</option>
                </select>

                <label for="edit-budget">Budget Allocation (₱)</label>
                <input type="number" name="budget_allocation" id="edit-budget" step="0.01" min="0" required>

                <label for="edit-fund">Source of Fund</label>
                <input type="text" name="source_of_fund" id="edit-fund">

                <div class="modal-actions">
                    <button type="submit">Save Changes</button>
                    <button type="button" onclick="closeModal('edit-modal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="delete-modal" class="modal-overlay">
        <div class="modal-box">
            <span class="close-modal" onclick="closeModal('delete-modal')">×</span>
            <h2>Confirm Delete</h2>
            <p>Are you sure you want to delete this PPA entry?</p>
            <p>This action cannot be undone.</p>
            <form id="delete-form" method="POST" action="delete.php">
                <input type="hidden" name="id" id="delete-id">
                <div class="modal-actions">
                    <button type="submit">Yes, Delete</button>
                    <button type="button" onclick="closeModal('delete-modal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../js/dashboard.js"></script>
    <script>
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    modal.classList.add('active');
    document.body.classList.add('modal-open');
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    modal.classList.remove('active');
    document.body.classList.remove('modal-open');
}

function toggleFiscalDropdown() {
    const dropdown = document.getElementById('fiscal-dropdown');
    dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
}

function filterByYear(year) {
    const items = document.querySelectorAll('.quarter-item');
    items.forEach(item => {
        const btn = item.querySelector('.quarter-btn');
        if (!btn) {
            item.style.display = 'none';
            return;
        }

        const text = btn.textContent.trim();
        const match = text.match(/(\d{4})(?:-(\d{4}))?$/i);
        let displayedYear = '';
        if (match) {
            displayedYear = match[2] || match[1];
        }

        const shouldShow = !year || displayedYear === year;
        item.style.display = shouldShow ? 'flex' : 'none';
    });

    document.getElementById('fiscal-dropdown').style.display = 'none';
}

// Edit modal population
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
        document.getElementById('edit-frequency').value = this.dataset.frequency;

        openModal('edit-modal');
    });
});

// Close when clicking outside modal
window.onclick = function(event) {
    if (event.target.classList.contains('modal-overlay')) {
        closeModal(event.target.id);
    }
};
</script>
    
</body>
</html>