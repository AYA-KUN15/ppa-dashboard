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
        SELECT program_id, project_title, date_of_implementation,
               type_of_extension_service_agenda, sdg_goals,
               offices_involved, programs_involved, beneficiaries_json,
               status
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

$beneficiaries = [];
if (!empty($project['beneficiaries_json'])) {
    $decoded = json_decode($project['beneficiaries_json'], true);
    if (is_array($decoded)) {
        $beneficiaries = $decoded;
    }
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
            <a href="view.php?id=<?= htmlspecialchars($project['program_id']) ?>" class="nav-button">Program</a>
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
                <p><strong>Project Title:</strong> <?= htmlspecialchars($project['project_title']) ?></p>
                <p><strong>Date of Implementation:</strong> <?= htmlspecialchars($project['date_of_implementation']) ?></p>
                <p><strong>Type of Extension Service Agenda:</strong> <?= htmlspecialchars($project['type_of_extension_service_agenda'] ?? 'N/A') ?></p>
                <p><strong>SDG Goals:</strong> <?= htmlspecialchars($project['sdg_goals'] ?? 'N/A') ?></p>
                <p><strong>Offices Involved:</strong> <?= htmlspecialchars($project['offices_involved'] ?? 'N/A') ?></p>
                <p><strong>Programs Involved:</strong> <?= htmlspecialchars($project['programs_involved'] ?? 'N/A') ?></p>
                <p><strong>Beneficiaries:</strong> <span id="view-beneficiaries"></span></p>
                <p><strong>Status:</strong> 
                    <?php
                    $status = strtolower($project['status'] ?? 'active');
                    $displayStatus = ucfirst($status);
                    $color = ($status === 'completed' || $status === 'archived') ? '#10b981' : '#c8102e';
                    ?>
                    <span style="color: <?= $color ?>; font-weight: 600;">
                        <?= htmlspecialchars($displayStatus) ?>
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
                                <div class="quarter-item <?= ($activity['status'] !== 'active') ? 'completed' : '' ?>">
                                    <button class="quarter-btn <?= ($activity['status'] !== 'active') ? 'completed-project' : '' ?>" 
                                            onclick="window.location.href='view_activity.php?id=<?= $activity['id'] ?>'">
                                        <span class="quarter-btn-title"><?= htmlspecialchars($activity['activity_name']) ?></span>
                                        <span class="quarter-btn-subtitle">
                                            <?= htmlspecialchars($activity['date_of_implementation']) ?>
                                            <?php if ($activity['status'] !== 'active'): ?>
                                                <span style="color: #10b981; font-weight: 600;"> (<?= ucfirst($activity['status']) ?>)</span>
                                            <?php endif; ?>
                                        </span>
                                    </button>

                                    <button class="action-icon edit-icon-btn" 
                                            onclick="window.location.href='edit_activity.php?id=<?= $activity['id'] ?>'"
                                            title="Edit activity">
                                        <span class="material-icons">edit</span>
                                    </button>

                                    <?php if ($activity['status'] === 'active'): ?>
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
    // Beneficiaries summary
    const beneficiariesJson = <?= json_encode($beneficiaries) ?>;
    const beneficiariesSpan = document.getElementById('view-beneficiaries');
    if (beneficiariesSpan) {
        let summary = '';
        let total = 0;
        if (Array.isArray(beneficiariesJson)) {
    beneficiariesJson.forEach(b => {
            const typeText = b.type?.trim() || '';
            const male = parseInt(b.male) || 0;
            const female = parseInt(b.female) || 0;
            if (typeText) {
                if (male > 0 || female > 0) {
                    summary += `${typeText}: ${male} male, ${female} female | `;
                } else {
                    summary += `${typeText} | `;
                }
                total += male + female;
            }
                })
}
        };
        summary += total > 0 ? `Total: ${total}` : '';
        beneficiariesSpan.textContent = summary.trim() || 'None added';
    } else {
        console.warn('Beneficiaries span not found');
    }

    // Complete button handler
    document.querySelectorAll('.complete-icon-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();

            const mode = this.dataset.mode || 'project';
            const entity = mode === 'project' ? 'project' : 'activity';

            if (confirm(`Mark this ${entity} as completed? It will move to the archive view.`)) {
                btn.disabled = true;
                const originalIcon = btn.innerHTML;
                btn.innerHTML = '<span class="material-icons">hourglass_empty</span>';

                fetch('complete.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `id=${encodeURIComponent(this.dataset.id)}&mode=${encodeURIComponent(mode)}`
                })
                .then(response => {
                    if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        const card = this.closest('.quarter-item');
                        if (card) {
                            card.querySelector('.quarter-btn').classList.add('completed-project');
                            card.classList.add('completed');
                            this.remove();
                        }
                        location.reload();
                    } else {
                        alert('Failed: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(err => {
                    console.error('Complete request failed:', err);
                    alert('Network or server error: ' + err.message);
                })
                .finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = originalIcon;
                });
            }
        });
    });
    </script>

</body>
</html>