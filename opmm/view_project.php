<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php");
    exit;
}

require_once '../config/db.php';

$id = $_GET['id'] ?? null;

if (!$id || !is_numeric($id)) {
    header("Location: list.php");
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT program_id, project_title, date_of_implementation, status
        FROM project_entries
        WHERE id = ?
    ");
    $stmt->execute([$id]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$project) {
        header("Location: list.php");
        exit;
    }

    $progStmt = $pdo->prepare("SELECT title FROM program_entries WHERE id = ?");
    $progStmt->execute([$project['program_id']]);
    $program = $progStmt->fetch(PDO::FETCH_ASSOC);

    $actStmt = $pdo->prepare("
        SELECT id, activity_name, date_of_implementation, status
        FROM activity_entries
        WHERE project_id = ?
        ORDER BY date_of_implementation ASC
    ");
    $actStmt->execute([$id]);
    $activities = $actStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Project - <?= htmlspecialchars($project['project_title'] ?? 'Project') ?></title>
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
            <a href="view.php?id=<?= $project['program_id'] ?>" class="nav-button">Back to Program</a>
            <a href="../logout.php" class="nav-button logout">Logout</a>
        </nav>
    </header>

    <main class="dashboard-content">
        <?php if (isset($error)): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php else: ?>
            <h1><?= htmlspecialchars($project['project_title']) ?></h1>

            <div class="program-details">
                <p><strong>Parent Program:</strong> <?= htmlspecialchars($program['title'] ?? 'Unknown') ?></p>
                <p><strong>Date of Implementation:</strong> <?= htmlspecialchars($project['date_of_implementation']) ?></p>
                <p><strong>Status:</strong> 
                    <span style="color: <?= $project['status'] === 'completed' ? '#10b981' : '#c8102e' ?>; font-weight: 600;">
                        <?= htmlspecialchars(ucfirst($project['status'] ?? 'Active')) ?>
                    </span>
                </p>
            </div>

            <div style="margin-top: 32px;">
                <h2>Activities under this Project</h2>

                <?php if (empty($activities)): ?>
                    <p>No activities added yet.</p>
                <?php else: ?>
                    <div class="quarter-scroll-container">
                        <div class="quarter-buttons">
                            <?php foreach ($activities as $activity): ?>
                                <div class="quarter-item <?= $activity['status'] === 'completed' ? 'completed' : '' ?>">
                                    <button class="quarter-btn <?= $activity['status'] === 'completed' ? 'completed-project' : '' ?>" 
                                            onclick="window.location.href='view_activity.php?id=<?= $activity['id'] ?>'">
                                        <span class="quarter-btn-title"><?= htmlspecialchars($activity['activity_name']) ?></span>
                                        <span class="quarter-btn-subtitle">
                                            <?= htmlspecialchars($activity['date_of_implementation']) ?>
                                            <?php if ($activity['status'] === 'completed'): ?>
                                                <span style="color: #10b981; font-weight: 600;"> (Completed)</span>
                                            <?php endif; ?>
                                        </span>
                                    </button>

                                    <button class="action-icon edit-icon-btn" 
                                            onclick="window.location.href='edit_activity.php?id=<?= $activity['id'] ?>'"
                                            title="Edit activity">
                                        <span class="material-icons">edit</span>
                                    </button>

                                    <?php if ($activity['status'] !== 'completed'): ?>
                                        <button class="action-icon complete-icon-btn" 
                                                data-id="<?= $activity['id'] ?>"
                                                data-mode="activity"
                                                title="Mark as Completed">
                                            <span class="material-icons">check_circle</span>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <a href="add_activity.php?project_id=<?= $id ?>" class="action-btn add" style="margin-top: 16px;">Add New Activity</a>
            </div>
        <?php endif; ?>
    </main>

    <script>
document.querySelectorAll('.complete-icon-btn').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.stopPropagation();

        if (confirm(`Mark this activity as completed? It will move to the archive view.`)) {
            fetch('complete.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `id=${encodeURIComponent(this.dataset.id)}&mode=${encodeURIComponent(this.dataset.mode)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const card = this.closest('.quarter-item');
                    if (card) {
                        card.querySelector('.quarter-btn').classList.add('completed-project');
                        card.classList.add('completed');
                        this.remove(); // hide complete button after success
                    }
                    location.reload();
                } else {
                    alert('Failed: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(err => {
                console.error(err);
                alert('Network error.');
            });
        }
    });
});
</script>

</body>
</html>