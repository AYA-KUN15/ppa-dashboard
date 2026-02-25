<?php
// list.php - Program List
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php");
    exit;
}

require_once '../config/db.php';

try {
    $stmt = $pdo->query("
        SELECT id, title, location, duration_start, duration_end,
               type_of_extension_service_agenda, sdg_goals, year_of_implementation
        FROM program_entries
        WHERE status = 'active'
        ORDER BY year_of_implementation DESC, title ASC
    ");
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
            <div class="action-buttons">
                <a href="add.php?mode=program" class="action-btn add">Add New Program</a>
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
                                    <?= htmlspecialchars($program['type_of_extension_service_agenda']) ?> · 
                                    FY <?= htmlspecialchars($program['year_of_implementation']) ?>
                                </span>
                            </button>

                            <button class="action-icon edit-icon-btn" 
                                    onclick="window.location.href='edit.php?mode=program&id=<?= $program['id'] ?>'"
                                    title="Edit program">
                                <span class="material-icons">edit</span>
                            </button>

                            <button class="action-icon delete-icon-btn" 
                                    data-id="<?= $program['id'] ?>"
                                    data-mode="program"
                                    title="Delete program">
                                <span class="material-icons">delete</span>
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

            <form id="filter-form">
                <label for="filter-type">Type of Extension Service Agenda</label>
                <select id="filter-type" name="type">
                    <option value="">All Types</option>
                    <option value="BatStateU Inclusive Social Innovation for Regional Growth (BISIG) Program">BISIG Program</option>
                    <option value="Livelihood and other Entrepreneurship related on Agri-Fisheries (LEAF)">LEAF</option>
                    <option value="Environment and Natural resources Conservation, Protection and Rehabilitation Program">Environment and Natural resources Program</option>
                    <option value="Smart Analytics and Engineering Innovation">Smart Analytics and Engineering Innovation</option>
                    <option value="Adopt-a Municipality/Barangay/School/Social Development Thru BIDANI Implementation">Adopt-a Municipality/Barangay/School</option>
                    <option value="Community Outreach">Community Outreach</option>
                    <option value="Technical-Vocational Education and Training (TVET) Program">TVET Program</option>
                    <option value="Technology Transfer and Adoption/Utilization Program">Technology Transfer and Adoption Program</option>
                    <option value="Technical Assistance and Advisory Services Program">Technical Assistance and Advisory Services Program</option>
                    <option value="Parents' Empowerment through Social Development (PESODEV)">PESODEV</option>
                    <option value="Gender and Development">Gender and Development</option>
                    <option value="Disaster Risk Reduction and Management and Disaster Preparedness and Response/Climate Change Adaptation (DRMM and DPR/CCA)">DRMM and DPR/CCA</option>
                </select>

                <label for="filter-sdg">Sustainable Development Goals</label>
                <select id="filter-sdg" name="sdg">
                    <option value="">All SDGs</option>
                    <option value="No Poverty">No Poverty</option>
                    <option value="Zero Hunger">Zero Hunger</option>
                    <option value="Good Health and Well-Being">Good Health and Well-Being</option>
                    <option value="Quality Education">Quality Education</option>
                    <option value="Gender Equality">Gender Equality</option>
                    <option value="Clean Water and Sanitation">Clean Water and Sanitation</option>
                    <option value="Affordable and Clean Energy">Affordable and Clean Energy</option>
                    <option value="Decent Work and Economic Growth">Decent Work and Economic Growth</option>
                    <option value="Industry, Innovation, and Infrastructure">Industry, Innovation, and Infrastructure</option>
                    <option value="Reduced Inequalities">Reduced Inequalities</option>
                    <option value="Sustainable Cities and Communities">Sustainable Cities and Communities</option>
                    <option value="Responsible Consumption and Production">Responsible Consumption and Production</option>
                    <option value="Climate Action">Climate Action</option>
                    <option value="Life Below Water">Life Below Water</option>
                    <option value="Life on Land">Life on Land</option>
                    <option value="Peace, Justice and Strong Institutions">Peace, Justice and Strong Institutions</option>
                    <option value="Partnerships for the Goals">Partnerships for the Goals</option>
                </select>

                <label for="filter-year">Year of Implementation</label>
                <select id="filter-year" name="year">
                    <option value="">All Years</option>
                    <?php for ($y = date('Y'); $y >= 2021; $y--): ?>
                        <option value="<?= $y ?>"><?= $y ?></option>
                    <?php endfor; ?>
                </select>

                <div class="modal-actions">
                    <button type="submit">Apply Filter</button>
                    <button type="button" onclick="closeModal('filter-modal')">Cancel</button>
                    <button type="button" id="clear-filter">Clear Filter</button>
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
</script>
    
</body>
</html>