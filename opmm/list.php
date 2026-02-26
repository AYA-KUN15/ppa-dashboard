<?php
// list.php - Program List
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php");
    exit;
}

require_once '../config/db.php';

try {
    $where = "WHERE status = 'active'";
    $params = [];
    $orderBy = "ORDER BY duration_start DESC, title ASC";

    // Type filter (partial match)
    if (!empty($_GET['type'])) {
        $where .= " AND type_of_extension_service_agenda LIKE ?";
        $params[] = '%' . trim($_GET['type']) . '%';
    }

    // SDG filter (partial match)
    if (!empty($_GET['sdg'])) {
        $where .= " AND sdg_goals LIKE ?";
        $params[] = '%' . trim($_GET['sdg']) . '%';
    }

    // Duration range filter (by year)
    if (!empty($_GET['duration_start_year'])) {
        $startYear = (int)$_GET['duration_start_year'];
        if ($startYear >= 2021) {
            $where .= " AND YEAR(duration_start) >= ?";
            $params[] = $startYear;
        }
    }

    if (!empty($_GET['duration_end_year'])) {
        $endYear = (int)$_GET['duration_end_year'];
        if ($endYear >= 2021) {
            $where .= " AND YEAR(duration_end) <= ?";
            $params[] = $endYear;
        }
    }

    $query = "
        SELECT id, title, location, duration_start, duration_end,
               type_of_extension_service_agenda, sdg_goals
        FROM program_entries
        $where
        $orderBy
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $programs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
    $programs = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PPA Dashboard - Programs</title>
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
    <div class="action-buttons" style="display: flex; gap: 12px; justify-content: flex-end; align-items: center;">
    <a href="add.php?mode=program" class="action-btn add">Add New Program</a>
    <a href="archive.php" class="action-btn archive" style="text-decoration: none !important; min-width: 140px; text-align: center;">
        M&E Phase
    </a>
</div>
</div>

        <div class="quarter-scroll-container">
            <div class="quarter-buttons">
                <?php if (!empty($error)): ?>
                    <p class="error"><?= htmlspecialchars($error) ?></p>
                <?php elseif (empty($programs)): ?>
                    <p>No programs found yet.</p>
                <?php else: ?>
                    <?php foreach ($programs as $program): ?>
                        <div class="quarter-item">
                            <button class="quarter-btn" 
                                    onclick="window.location.href='view.php?mode=program&id=<?= $program['id'] ?>'">
                                <span class="quarter-btn-title"><?= htmlspecialchars($program['title']) ?></span>
                                <span class="quarter-btn-subtitle">
    <?= htmlspecialchars(date('M d, Y', strtotime($program['duration_start']))) ?> – 
    <?= htmlspecialchars(date('M d, Y', strtotime($program['duration_end']))) ?>
</span>
                            </button>

                            <button class="action-icon edit-icon-btn" 
                                    onclick="window.location.href='edit.php?mode=program&id=<?= $program['id'] ?>'"
                                    title="Edit program">
                                <span class="material-icons">edit</span>
                            </button>

                            <button class="action-icon complete-icon-btn" 
        data-id="<?= $program['id'] ?>"
        data-mode="program"
        title="Mark as Completed">
    <span class="material-icons">check_circle</span>
</button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="content-placeholder">
            <p>Select a program to view its projects.</p>
        </div>
    </main>

        <!-- Filter Modal -->
<div id="filter-modal" class="modal-overlay">
    <div class="modal-box">
        <span class="close-modal" onclick="closeModal('filter-modal')">×</span>
        <h2>Filter Programs</h2>

        <form id="filter-form" method="GET" action="list.php">
            <label for="filter-type">Type of Extension Service Agenda</label>
            <select id="filter-type" name="type">
                <option value="">All Types</option>
                <option value="BatStateU Inclusive Social Innovation for Regional Growth (BISIG) Program" <?= ($_GET['type'] ?? '') === 'BatStateU Inclusive Social Innovation for Regional Growth (BISIG) Program' ? 'selected' : '' ?>>BISIG Program</option>
                <option value="Livelihood and other Entrepreneurship related on Agri-Fisheries (LEAF)" <?= ($_GET['type'] ?? '') === 'Livelihood and other Entrepreneurship related on Agri-Fisheries (LEAF)' ? 'selected' : '' ?>>LEAF</option>
                <option value="Environment and Natural resources Conservation, Protection and Rehabilitation Program" <?= ($_GET['type'] ?? '') === 'Environment and Natural resources Conservation, Protection and Rehabilitation Program' ? 'selected' : '' ?>>Environment and Natural resources Program</option>
                <option value="Smart Analytics and Engineering Innovation" <?= ($_GET['type'] ?? '') === 'Smart Analytics and Engineering Innovation' ? 'selected' : '' ?>>Smart Analytics and Engineering Innovation</option>
                <option value="Adopt-a Municipality/Barangay/School/Social Development Thru BIDANI Implementation" <?= ($_GET['type'] ?? '') === 'Adopt-a Municipality/Barangay/School/Social Development Thru BIDANI Implementation' ? 'selected' : '' ?>>Adopt-a Municipality/Barangay/School</option>
                <option value="Community Outreach" <?= ($_GET['type'] ?? '') === 'Community Outreach' ? 'selected' : '' ?>>Community Outreach</option>
                <option value="Technical-Vocational Education and Training (TVET) Program" <?= ($_GET['type'] ?? '') === 'Technical-Vocational Education and Training (TVET) Program' ? 'selected' : '' ?>>TVET Program</option>
                <option value="Technology Transfer and Adoption/Utilization Program" <?= ($_GET['type'] ?? '') === 'Technology Transfer and Adoption/Utilization Program' ? 'selected' : '' ?>>Technology Transfer and Adoption Program</option>
                <option value="Technical Assistance and Advisory Services Program" <?= ($_GET['type'] ?? '') === 'Technical Assistance and Advisory Services Program' ? 'selected' : '' ?>>Technical Assistance and Advisory Services Program</option>
                <option value="Parents' Empowerment through Social Development (PESODEV)" <?= ($_GET['type'] ?? '') === "Parents' Empowerment through Social Development (PESODEV)" ? 'selected' : '' ?>>PESODEV</option>
                <option value="Gender and Development" <?= ($_GET['type'] ?? '') === 'Gender and Development' ? 'selected' : '' ?>>Gender and Development</option>
                <option value="Disaster Risk Reduction and Management and Disaster Preparedness and Response/Climate Change Adaptation (DRMM and DPR/CCA)" <?= ($_GET['type'] ?? '') === 'Disaster Risk Reduction and Management and Disaster Preparedness and Response/Climate Change Adaptation (DRMM and DPR/CCA)' ? 'selected' : '' ?>>DRMM and DPR/CCA</option>
            </select>

            <label for="filter-sdg">Sustainable Development Goals</label>
            <select id="filter-sdg" name="sdg">
                <option value="">All SDGs</option>
                <option value="No Poverty" <?= ($_GET['sdg'] ?? '') === 'No Poverty' ? 'selected' : '' ?>>No Poverty</option>
                <option value="Zero Hunger" <?= ($_GET['sdg'] ?? '') === 'Zero Hunger' ? 'selected' : '' ?>>Zero Hunger</option>
                <option value="Good Health and Well-Being" <?= ($_GET['sdg'] ?? '') === 'Good Health and Well-Being' ? 'selected' : '' ?>>Good Health and Well-Being</option>
                <option value="Quality Education" <?= ($_GET['sdg'] ?? '') === 'Quality Education' ? 'selected' : '' ?>>Quality Education</option>
                <option value="Gender Equality" <?= ($_GET['sdg'] ?? '') === 'Gender Equality' ? 'selected' : '' ?>>Gender Equality</option>
                <option value="Clean Water and Sanitation" <?= ($_GET['sdg'] ?? '') === 'Clean Water and Sanitation' ? 'selected' : '' ?>>Clean Water and Sanitation</option>
                <option value="Affordable and Clean Energy" <?= ($_GET['sdg'] ?? '') === 'Affordable and Clean Energy' ? 'selected' : '' ?>>Affordable and Clean Energy</option>
                <option value="Decent Work and Economic Growth" <?= ($_GET['sdg'] ?? '') === 'Decent Work and Economic Growth' ? 'selected' : '' ?>>Decent Work and Economic Growth</option>
                <option value="Industry, Innovation, and Infrastructure" <?= ($_GET['sdg'] ?? '') === 'Industry, Innovation, and Infrastructure' ? 'selected' : '' ?>>Industry, Innovation, and Infrastructure</option>
                <option value="Reduced Inequalities" <?= ($_GET['sdg'] ?? '') === 'Reduced Inequalities' ? 'selected' : '' ?>>Reduced Inequalities</option>
                <option value="Sustainable Cities and Communities" <?= ($_GET['sdg'] ?? '') === 'Sustainable Cities and Communities' ? 'selected' : '' ?>>Sustainable Cities and Communities</option>
                <option value="Responsible Consumption and Production" <?= ($_GET['sdg'] ?? '') === 'Responsible Consumption and Production' ? 'selected' : '' ?>>Responsible Consumption and Production</option>
                <option value="Climate Action" <?= ($_GET['sdg'] ?? '') === 'Climate Action' ? 'selected' : '' ?>>Climate Action</option>
                <option value="Life Below Water" <?= ($_GET['sdg'] ?? '') === 'Life Below Water' ? 'selected' : '' ?>>Life Below Water</option>
                <option value="Life on Land" <?= ($_GET['sdg'] ?? '') === 'Life on Land' ? 'selected' : '' ?>>Life on Land</option>
                <option value="Peace, Justice and Strong Institutions" <?= ($_GET['sdg'] ?? '') === 'Peace, Justice and Strong Institutions' ? 'selected' : '' ?>>Peace, Justice and Strong Institutions</option>
                <option value="Partnerships for the Goals" <?= ($_GET['sdg'] ?? '') === 'Partnerships for the Goals' ? 'selected' : '' ?>>Partnerships for the Goals</option>
            </select>

            <label>Duration Range (Year)</label>
            <div style="display: flex; gap: 16px; align-items: center;">
                <input type="number" name="duration_start_year" placeholder="From Year" min="2021" 
                       value="<?= htmlspecialchars($_GET['duration_start_year'] ?? '') ?>" style="width: 120px;">
                <span>to</span>
                <input type="number" name="duration_end_year" placeholder="To Year" min="2021" 
                       value="<?= htmlspecialchars($_GET['duration_end_year'] ?? '') ?>" style="width: 120px;">
            </div>

            <div class="modal-actions" style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 24px;">
    <button type="submit" 
            style="flex: 1; padding: 14px 24px; background: #c8102e; color: white; 
                   border: none; border-radius: 8px; cursor: pointer; font-size: 1rem; 
                   font-weight: 600; height: 52px; box-sizing: border-box;">
        Apply
    </button>
    <button type="button" onclick="closeModal('filter-modal')"
            style="flex: 1; padding: 14px 24px; background: #c8102e; color: white; 
                   border: none; border-radius: 8px; cursor: pointer; font-size: 1rem; 
                   font-weight: 600; height: 52px; box-sizing: border-box;">
        Cancel
    </button>
    <button type="button" onclick="window.location.href='list.php'"
            style="flex: 1; padding: 14px 24px; background: #c8102e; color: white; 
                   border: none; border-radius: 8px; cursor: pointer; font-size: 1rem; 
                   font-weight: 600; height: 52px; box-sizing: border-box;">
        Clear
    </button>
</div>
        </form>
    </div>
</div>
</form>
    </div>
</div>

    <!-- Delete Confirmation Modal -->
    <div id="delete-modal" class="modal-overlay">
        <div class="modal-box">
            <span class="close-modal" onclick="closeModal('delete-modal')">×</span>
            <h2>Confirm Delete</h2>
            <p>Are you sure you want to delete this entry?</p>
            <p>This action cannot be undone.</p>
            <form id="delete-form" method="POST" action="delete.php">
                <input type="hidden" name="id" id="delete-id">
                <input type="hidden" name="mode" id="delete-mode">
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
    document.getElementById(modalId).classList.add('active');
    document.body.classList.add('modal-open');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
    document.body.classList.remove('modal-open');
}

// Delete modal trigger
document.querySelectorAll('.delete-icon-btn').forEach(btn => {
    btn.addEventListener('click', function () {
        document.getElementById('delete-id').value = this.dataset.id;
        document.getElementById('delete-mode').value = this.dataset.mode;
        openModal('delete-modal');
    });
});

// Close modal on outside click
window.onclick = function(event) {
    if (event.target.classList.contains('modal-overlay')) {
        closeModal(event.target.id);
    }
};

document.querySelectorAll('.complete-icon-btn').forEach(btn => {
    btn.addEventListener('click', function () {
        if (confirm("Mark this program as completed? It will no longer appear in the active list.")) {
            fetch('complete.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `id=${this.dataset.id}&mode=${this.dataset.mode}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert("Program marked as completed.");
                    location.reload();
                } else {
                    alert("Error: " + (data.message || "Unknown error"));
                }
            })
            .catch(err => alert("Network error: " + err.message));
        }
    });
});
</script>
    
</body>
</html>