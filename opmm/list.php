<?php
// list.php - Program List (only active programs; completed go to archive.php / M&E Phase)
session_start();
// Start session: used for authentication and temporarily storing form data (e.g., pending activity before confirmation)

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php");
    exit;
}

require_once '../config/db.php';
// Load database connection (PDO instance)

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
    // Source of Fund filter (exact match)
    if (!empty($_GET['fund'])) {
        $where .= " AND source_of_fund = ?";
        $params[] = trim($_GET['fund']);
    }

    $query = "
        SELECT id, title, location, duration_start, duration_end,
               type_of_extension_service_agenda, sdg_goals
        FROM program_entries
        $where
        $orderBy
    ";
    // Prepare SQL query safely (prevents SQL injection)
$stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $programs = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
    $programs = [];
}

$nav_links = [
    ['url' => 'index.php', 'label' => 'Home', 'active' => false],
    ['url' => '/opmm/list.php', 'label' => 'PPA', 'active' => true],
    ['url' => '/opmm/list_proposals.php', 'label' => 'Proposals', 'active' => false],
];
?>

<?php include '../includes/header.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>PPA Dashboard - Programs</title>

<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
<link rel="stylesheet" href="../css/style.css">

<style>
/* Exact quarter button styles from view_project.php */
.quarter-item {
    display: flex;
    align-items: center;
    gap: 8px;
    width: 100%;
}

.quarter-btn {
    flex: 1;
    padding: 14px 20px;
    background: #ffffff;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
    text-align: left;
    box-sizing: border-box;
}

.quarter-btn-title {
    font-weight: 600;
    font-size: 1.1rem;
    display: block;
    margin-bottom: 4px;
}

.quarter-btn-subtitle {
    font-size: 0.9rem;
    color: #6b7280;
}

.edit-icon-btn {
    flex: 0 0 42px;
    width: 42px;
    height: 42px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: none;
    border: none;
    border-radius: 50%;
    cursor: pointer;
    transition: all 0.2s;
}

.edit-icon-btn .material-icons {
    font-size: 1.6rem;
    color: #6b7280;
}

.edit-icon-btn:hover {
    color: #c8102e;
    background: rgba(200, 16, 46, 0.1);
}

/* Completed green style (for archive.php or future use) */
.quarter-btn.completed-project {
    background: #ecfdf5 !important;
    border: 2px solid #10b981 !important;
    color: #065f46 !important;
}

.quarter-btn.completed-project:hover {
    background: #d1fae5 !important;
    border-color: #059669 !important;
}
</style>

</head>

<body>

<main class="dashboard-content">

<div class="filter-actions">
    <button class="filter-button" onclick="openModal('filter-modal')">Filter</button>

    <div class="action-buttons" style="display:flex; gap:12px; justify-content:flex-end; align-items:center;">
        <a href="add.php?mode=program" class="action-btn add">Add New Program</a>

        <a href="archive.php" class="action-btn archive"
           style="text-decoration:none !important; min-width:140px; text-align:center;">
            M&E Phase
        </a>
    </div>
</div>

<div class="quarter-scroll-container">

<div class="quarter-buttons">

<?php if (!empty($error)): ?>

<p class="error"><?= htmlspecialchars($error) ?></p>

<?php elseif (empty($programs)): ?>

<p>No active programs found.</p>

<?php else: ?>

<?php foreach ($programs as $program): ?>

<div class="quarter-item">

<button class="quarter-btn"
onclick="window.location.href='view.php?mode=program&id=<?= $program['id'] ?>'">

<span class="quarter-btn-title">
<?= htmlspecialchars($program['title']) ?>
</span>

<span class="quarter-btn-subtitle">
<?= date('M d, Y', strtotime($program['duration_start'])) ?> –
<?= date('M d, Y', strtotime($program['duration_end'])) ?>
</span>

</button>

<!-- Fixed: points to edit.php for program (not activity) -->
<button class="action-icon edit-icon-btn"
onclick="window.location.href='edit.php?mode=program&id=<?= $program['id'] ?>'"
title="Edit program">

<span class="material-icons">edit</span>

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

<!-- Filter Modal (kept exactly as you provided) -->
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

            <label for="filter-fund">Source of Fund</label>
            <select id="filter-fund" name="fund">
                <option value="">All Sources</option>
                <option value="MDS" <?= ($_GET['fund'] ?? '') === 'MDS' ? 'selected' : '' ?>>MDS</option>
                <option value="STF" <?= ($_GET['fund'] ?? '') === 'STF' ? 'selected' : '' ?>>STF</option>
                <option value="Others" <?= ($_GET['fund'] ?? '') === 'Others' ? 'selected' : '' ?>>Others</option>
            </select>

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

<script src="../js/dashboard.js"></script>
<script>
// Opens modal UI and restores previously selected values
function openModal(modalId) {
    document.getElementById(modalId).classList.add('active');
    document.body.classList.add('modal-open');
}
function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
    document.body.classList.remove('modal-open');
}
</script>

</body>
</html>