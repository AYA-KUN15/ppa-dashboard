<?php
// opmm/list.php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php");
    exit;
}

require_once '../config/db.php';

try {
    $stmt = $pdo->query("
        SELECT id, fiscal_year, quarter
        FROM opmm_entries
        WHERE status = 'active'
        GROUP BY fiscal_year, quarter
        ORDER BY fiscal_year DESC, 
                 FIELD(quarter, '4th', '3rd', '2nd', '1st') DESC
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
    <title>OPMM Dashboard - List</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
</head>

<body>

    <header class="top-bar">
        <div class="logo-container">
            <img src="../assets/bsu-logo.jpg" alt="BatStateU Logo" class="logo">
            <span class="logo-text">OPMM Dashboard</span>
        </div>
        <nav class="main-nav">
            <a href="../index.php" class="nav-button">Home</a>
            <a href="list.php" class="nav-button active">OPMM</a>
            <a href="../logout.php" class="nav-button logout">Logout</a>
        </nav>
    </header>

    <main class="dashboard-content">
        <div class="filter-actions">
            <button class="filter-button">Filter by Fiscal Year ▼</button>
            <div class="action-buttons">
                <a href="add.php" class="action-btn add">+ Add New OPMM</a>
            </div>
        </div>

        <div class="quarter-scroll-container">
            <div class="quarter-buttons edit-mode-container">
                <?php if (!empty($error)): ?>
                    <p class="error"><?= htmlspecialchars($error) ?></p>
                <?php elseif (empty($entries)): ?>
                    <p>No OPMM entries found yet.</p>
                <?php else: ?>
                    <?php foreach ($entries as $entry): ?>
                        <div class="quarter-item">
    <button class="quarter-btn" 
            onclick="window.location.href='view.php?id=<?= $entry['id'] ?>'">
        <?= htmlspecialchars($entry['quarter']) ?> Quarter, Fiscal Year <?= htmlspecialchars($entry['fiscal_year']) ?>
    </button>

    <button class="action-icon edit-icon-btn" 
            data-id="<?= $entry['id'] ?>"
            data-quarter="<?= htmlspecialchars($entry['quarter']) ?>"
            data-fiscal="<?= htmlspecialchars($entry['fiscal_year']) ?>"
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
            <p>Select a quarter/year to view entries.</p>
        </div>
    </main>

    <!-- Edit Modal -->
    <div id="edit-modal" class="modal-overlay">
        <div class="modal-box">
            <span class="close-modal" onclick="closeModal('edit-modal')">×</span>
            <h2>Edit OPMM</h2>
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
            <p>Are you sure you want to delete this OPMM entry?</p>
            <p>This action cannot be undone.</p>
            <form id="delete-form" method="POST" action="delete.php">
                <input type="hidden" name="id" id="delete-id">
                <div class="modal-actions">
                    <button type="submit" class="danger-btn">Yes, Delete</button>
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

// Close when clicking outside modal
window.onclick = function(event) {
    if (event.target.classList.contains('modal-overlay')) {
        closeModal(event.target.id);
    }
};
</script>
    
</body>
</html>