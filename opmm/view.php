<?php
// view.php - View Program Details + List of Projects
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
        SELECT id, title, location, duration_start, duration_end,
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

    $stmt = $pdo->prepare("
        SELECT id, project_title, implementation_start, implementation_end, status
        FROM project_entries
        WHERE program_id = ?
        ORDER BY implementation_start ASC
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
    <title>View Program - <?= htmlspecialchars($program['title'] ?? 'Program') ?></title>
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
                <p><strong>Location:</strong> <?= htmlspecialchars($program['location'] ?? 'N/A') ?></p>
                <p><strong>Duration:</strong> 
                    <?= htmlspecialchars(date('M d, Y', strtotime($program['duration_start']))) ?> – 
                    <?= htmlspecialchars(date('M d, Y', strtotime($program['duration_end']))) ?>
                </p>
                <p><strong>Type:</strong> <?= htmlspecialchars($program['type_of_extension_service_agenda'] ?? 'N/A') ?></p>
                <p><strong>SDG Goals:</strong> <?= htmlspecialchars($program['sdg_goals'] ?? 'N/A') ?></p>
                <p><strong>Offices Involved:</strong> <?= htmlspecialchars($program['offices_involved'] ?? 'N/A') ?></p>
                <p><strong>Programs Involved:</strong> <?= htmlspecialchars($program['programs_involved'] ?? 'N/A') ?></p>
                <p><strong>Partner Agencies:</strong> <?= htmlspecialchars($program['partner_agencies'] ?: 'None') ?></p>
                <p><strong>Beneficiaries:</strong> <span id="view-beneficiaries"></span></p>
                <p><strong>Total Cost:</strong> ₱<?= number_format($program['total_cost'] ?? 0, 2) ?></p>
                <p><strong>Source of Fund:</strong> <?= htmlspecialchars($program['source_of_fund'] ?: 'N/A') ?></p>
                <p><strong>Status:</strong> 
                    <?php
                    $status = strtolower($program['status'] ?? 'active');
                    if ($status !== 'active') {
                        echo '<span style="color: #10b981; font-weight: 600;">Completed</span>';
                    } else {
                        echo '<span style="color: #c8102e; font-weight: 600;">Active</span>';
                    }
                    ?>
                </p>
            </div>

            <div style="margin-top: 32px;">
                <h2>Projects under this Program</h2>

                <?php if (empty($projects)): ?>
                    <p>No projects added yet.</p>
                <?php else: ?>
                    <div class="quarter-scroll-container">
                        <div class="quarter-buttons">
                            <?php foreach ($projects as $project): ?>
                                <div class="quarter-item <?= ($project['status'] !== 'active') ? 'completed' : '' ?>">
                                    <button class="quarter-btn <?= ($project['status'] !== 'active') ? 'completed-project' : '' ?>" 
                                            onclick="window.location.href='view_project.php?id=<?= $project['id'] ?>'">
                                        <span class="quarter-btn-title"><?= htmlspecialchars($project['project_title']) ?></span>
                                        <span class="quarter-btn-subtitle">
                                            <?= htmlspecialchars($project['implementation_start'] ? date('M d, Y', strtotime($project['implementation_start'])) : 'N/A') ?>
                                            <?php if ($project['implementation_end']): ?>
                                                – <?= htmlspecialchars(date('M d, Y', strtotime($project['implementation_end']))) ?>
                                            <?php endif; ?>
                                        </span>
                                    </button>

                                    <button class="action-icon edit-icon-btn" 
                                            onclick="window.location.href='edit_project.php?id=<?= $project['id'] ?>'"
                                            title="Edit project">
                                        <span class="material-icons">edit</span>
                                    </button>

                                    <?php if ($project['status'] === 'active'): ?>
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

                <a href="add_project.php?program_id=<?= htmlspecialchars($program['id']) ?>" 
                   class="action-btn add">Add New Project</a>
            </div>
        <?php endif; ?>
    </main>

    <script>
    // Beneficiaries summary
    const beneficiariesJson = <?= json_encode(json_decode($program['beneficiaries_json'] ?? '[]', true)) ?>;
    const beneficiariesSpan = document.getElementById('view-beneficiaries');
    if (beneficiariesSpan) {
        let summary = '';
        let total = 0;
        if (Array.isArray(beneficiariesJson)) {
            beneficiariesJson.forEach(b => {
                const typeText = b.type?.trim() || '';
                const male = parseInt(b.male ?? 0);
                const female = parseInt(b.female ?? 0);
                if (typeText) {
                    if (male > 0 || female > 0) {
                        summary += `${typeText}: ${male} male, ${female} female | `;
                    } else {
                        summary += `${typeText} | `;
                    }
                    total += male + female;
                }
            });
            summary += total > 0 ? `Total: ${total}` : '';
            beneficiariesSpan.textContent = summary.trim() || 'None added';
        }
    } else {
        console.warn('Beneficiaries span not found');
    }

    // Complete button handler
    document.querySelectorAll('.complete-icon-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();

            const mode = this.dataset.mode || 'project';
            const entity = mode === 'project' ? 'project' : 'activity';

            if (confirm(`Mark this ${entity} as completed?`)) {
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