<?php
// list.php - Program List (derived status only)
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php");
    exit;
}

require_once '../config/db.php';

try {
    $stmt = $pdo->prepare("
        SELECT id, title, location, duration_start, duration_end,
               type_of_extension_service_agenda, sdg_goals
        FROM program_entries
        ORDER BY duration_start DESC, title ASC
    ");
    $stmt->execute();
    $programs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($programs as &$program) {
        $projStmt = $pdo->prepare("SELECT id FROM project_entries WHERE program_id = ?");
        $projStmt->execute([$program['id']]);
        $projects = $projStmt->fetchAll(PDO::FETCH_ASSOC);

        $programStatus = 'active';
        if (!empty($projects)) {
            $allProjCompleted = true;

            foreach ($projects as $proj) {
                $actStmt = $pdo->prepare("
                    SELECT COUNT(*) AS total,
                           SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) AS completed
                    FROM activity_entries WHERE project_id = ?
                ");
                $actStmt->execute([$proj['id']]);
                $counts = $actStmt->fetch(PDO::FETCH_ASSOC);

                if ($counts['total'] > 0 && $counts['completed'] != $counts['total']) {
                    $allProjCompleted = false;
                    break;
                }
            }

            if ($allProjCompleted) {
                $programStatus = 'completed';
            }
        }

        $program['status'] = $programStatus;
    }

    unset($program);

} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
    $programs = [];
}

$nav_links = [
    ['url' => '../index.php', 'label' => 'Home', 'active' => false],
    ['url' => 'list.php', 'label' => 'PPA', 'active' => true],
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
/* Keep your flex layout (prevents overlap) + your width fix */
.quarter-item {
    display: flex;
    align-items: center;
    gap: 12px;                /* restored original gap */
    width: 100%;
}

/* Restore original quarter button style from view_project.php */
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

/* Edit icon - keep your width fix, remove gray background */
.edit-icon-btn {
    flex: 0 0 auto;           /* don't stretch */
    width: 42px;              /* ← your working width fix */
    height: 42px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: none;         /* ← removed #e8e8e8 */
    border: none;
    border-radius: 50%;
    cursor: pointer;
    transition: all 0.2s;
}

.edit-icon-btn .material-icons {
    font-size: 1.6rem;        /* restored original size */
    color: #6b7280;
}

.edit-icon-btn:hover {
    color: #c8102e;
    background: rgba(200, 16, 46, 0.1);
}

/* Completed state (same as view_project.php) */
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

<?php if ($program['status'] !== 'active') continue; ?>

<div class="quarter-item">

<button class="quarter-btn"
onclick="window.location.href='view.php?mode=program&id=<?= $program['id'] ?>'">

<span class="quarter-btn-title">
<?= htmlspecialchars($program['title']) ?>
</span>

<span class="quarter-btn-subtitle">
<?= date('M d, Y', strtotime($program['duration_start'])) ?>
–
<?= date('M d, Y', strtotime($program['duration_end'])) ?>
</span>

</button>

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

<script src="../js/dashboard.js"></script>

<script>

function openModal(modalId){
document.getElementById(modalId).classList.add('active');
document.body.classList.add('modal-open');
}

function closeModal(modalId){
document.getElementById(modalId).classList.remove('active');
document.body.classList.remove('modal-open');
}

</script>

</body>
</html>