<?php
// view.php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php");
    exit;
}

require_once '../config/db.php';

$mode = $_GET['mode'] ?? '';
$id = $_GET['id'] ?? null;

if ($mode !== 'program' || !$id || !is_numeric($id)) {
    header("Location: list.php");
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT title, location, duration_start, duration_end,
               type_of_extension_service_agenda, sdg_goals, offices_involved,
               programs_involved, partner_agencies, beneficiaries_json,
               total_cost, source_of_fund, status
        FROM program_entries
        WHERE id = ?
    ");
    $stmt->execute([$id]);
    $program = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$program) {
        header("Location: list.php");
        exit;
    }

    // Get projects under this program
    $stmt = $pdo->prepare("
    SELECT id, project_title, activities, date_of_implementation, status
    FROM project_entries
    WHERE program_id = ? AND status = 'active'
    ORDER BY date_of_implementation ASC
");
    $stmt->execute([$id]);
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Program - <?= htmlspecialchars($program['title'] ?? 'Not Found') ?> 
        (<?= htmlspecialchars($program['status'] ?? 'Unknown') ?>)</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>

    <header class="top-bar">
        <div class="logo-container">
            <img src="../assets/bsu-logo.jpg" alt="BSU Logo" class="logo">
            <span class="logo-text">PPA Dashboard</span>
        </div>
        <nav class="main-nav">
            <a href="../index.php" class="nav-button">Home</a>
            <a href="list.php" class="nav-button">PPA</a>
            <a href="../logout.php" class="nav-button logout">Logout</a>
        </nav>
    </header>

    <main class="dashboard-content">
        <?php if (isset($error)): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php else: ?>
            <h1><?= htmlspecialchars($program['title']) ?></h1>

            <div class="program-details">
                <p><strong>Location:</strong> <?= htmlspecialchars($program['location']) ?></p>
                <p><strong>Duration:</strong> 
                    <?= htmlspecialchars(date('M d, Y', strtotime($program['duration_start']))) ?> – 
                    <?= htmlspecialchars(date('M d, Y', strtotime($program['duration_end']))) ?>
                </p>
                <p><strong>Type:</strong> <?= htmlspecialchars($program['type_of_extension_service_agenda']) ?></p>
                <p><strong>SDG Goals:</strong> <?= htmlspecialchars($program['sdg_goals']) ?></p>
                <p><strong>Offices Involved:</strong> <?= htmlspecialchars($program['offices_involved']) ?></p>
                <p><strong>Programs Involved:</strong> <?= htmlspecialchars($program['programs_involved']) ?></p>
                <p><strong>Partner Agencies:</strong> <?= htmlspecialchars($program['partner_agencies'] ?: 'None') ?></p>
                <p><strong>Beneficiaries:</strong> <span id="view-beneficiaries"></span></p>
                <p><strong>Total Cost:</strong> ₱<?= number_format($program['total_cost'], 2) ?></p>
                <p><strong>Source of Fund:</strong> <?= htmlspecialchars($program['source_of_fund'] ?: 'N/A') ?></p>
            </div>

            <div style="margin-top: 32px;">
                <h2>Projects under this Program</h2>
                <?php if (empty($projects)): ?>
                    <p>No projects added yet.</p>
                <?php else: ?>
                    <div class="quarter-scroll-container">
                        <div class="quarter-buttons">
                            <?php foreach ($projects as $project): ?>
                                <div class="quarter-item">
                                    <button class="quarter-btn <?= ($project['status'] ?? '') === 'completed' ? 'completed-project' : '' ?>" 
        onclick="window.location.href='view_project.php?id=<?= $project['id'] ?>'">
                                        <span class="quarter-btn-title"><?= htmlspecialchars($project['project_title']) ?></span>
                                        <span class="quarter-btn-subtitle">
                                            <?= htmlspecialchars($project['date_of_implementation']) ?>
                                            <?php if (($project['status'] ?? '') !== 'completed'): ?>
                                        </span>
                                    </button>

                                    <button class="action-icon edit-icon-btn" 
                                            onclick="window.location.href='edit.php?mode=project&id=<?= $project['id'] ?>'"
                                            title="Edit project">
                                        <span class="material-icons">edit</span>
                                    </button>

                                    <button class="action-icon complete-icon-btn" 
            data-id="<?= $project['id'] ?>"
            data-mode="project"
            title="Mark as Completed">
        <span class="material-icons">check_circle</span>
    </button>
<?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                <a href="add.php?mode=project&program_id=<?= $id ?>" class="action-btn add" style="margin-top: 16px;">Add New Project</a>
            </div>
        <?php endif; ?>
    </main>

    <script>
    // Show beneficiaries summary
    const beneficiariesJson = <?= json_encode($program['beneficiaries_json'] ?? '[]') ?>;
    let summary = '';
    let total = 0;
    beneficiariesJson.forEach(b => {
        summary += `${b.type}: ${b.male} male, ${b.female} female | `;
        total += b.male + b.female;
    });
    summary += `Total: ${total}`;
    document.getElementById('view-beneficiaries').textContent = summary || 'None added';
    </script>

</body>
</html>