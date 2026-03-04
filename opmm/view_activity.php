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
        SELECT project_id, activity_name, implementation_start, implementation_end,
               type_of_extension_service_agenda, sdg_goals,
               offices_involved, programs_involved, beneficiaries_json,
               status
        FROM activity_entries
        WHERE id = ?
    ");
    $stmt->execute([$id]);
    $activity = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$activity) {
        header("Location: list.php");
        exit;
    }

    // Parent project
    $projStmt = $pdo->prepare("SELECT project_title FROM project_entries WHERE id = ?");
    $projStmt->execute([$activity['project_id']]);
    $project = $projStmt->fetch(PDO::FETCH_ASSOC);

    // Uploaded images
    $imgStmt = $pdo->prepare("
        SELECT id, image_path 
        FROM activity_documents 
        WHERE activity_id = ? 
        ORDER BY uploaded_at DESC
    ");
    $imgStmt->execute([$id]);
    $images = $imgStmt->fetchAll(PDO::FETCH_ASSOC);

    // Count for button disable
    $count = count($images);
    $maxPhotos = 2;
    $canAdd = $count < $maxPhotos;
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Activity - <?= htmlspecialchars($activity['activity_name'] ?? 'Activity') ?></title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .photo-item {
            position: relative;
            display: inline-block;
        }
        .delete-photo-btn {
            position: absolute;
            top: 6px;
            right: 6px;
            background: rgba(220, 38, 38, 0.9);
            color: white;
            border: none;
            border-radius: 50%;
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 1.1rem;
            opacity: 0.9;
            transition: opacity 0.2s;
        }
        .delete-photo-btn:hover {
            opacity: 1;
            background: #dc2626;
        }
        .add-disabled {
            background: #9ca3af !important;
            cursor: not-allowed !important;
            pointer-events: none !important;
            opacity: 0.7;
        }
    </style>
</head>
<body>

    <header class="top-bar">
        <div class="logo-container">
            <img src="../assets/bsu-logo.jpg" alt="BSU Logo" class="logo">
            <span class="logo-text">PPA Dashboard</span>
        </div>
        <nav class="main-nav">
            <a href="../index.php" class="nav-button">Home</a>
            <a href="view_project.php?id=<?= htmlspecialchars($activity['project_id']) ?>" class="nav-button">Project</a>
            <a href="../logout.php" class="nav-button logout">Logout</a>
        </nav>
    </header>

    <main class="dashboard-content">
        <?php if (isset($error)): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php else: ?>
            <h1><?= htmlspecialchars($activity['activity_name']) ?></h1>

            <div class="program-details">
                <p><strong>Parent Project:</strong> <?= htmlspecialchars($project['project_title'] ?? 'Unknown') ?></p>
                <p><strong>Activity Name:</strong> <?= htmlspecialchars($activity['activity_name']) ?></p>
                <p><strong>Implementation Start:</strong> <?= htmlspecialchars($activity['implementation_start'] ?? 'N/A') ?></p>
                <p><strong>Implementation End:</strong> <?= htmlspecialchars($activity['implementation_end'] ?? 'N/A') ?></p>
                <p><strong>Type of Extension Service Agenda:</strong> <?= htmlspecialchars($activity['type_of_extension_service_agenda'] ?? 'N/A') ?></p>
                <p><strong>SDG Goals:</strong> <?= htmlspecialchars($activity['sdg_goals'] ?? 'N/A') ?></p>
                <p><strong>Offices Involved:</strong> <?= htmlspecialchars($activity['offices_involved'] ?? 'N/A') ?></p>
                <p><strong>Programs Involved:</strong> <?= htmlspecialchars($activity['programs_involved'] ?? 'N/A') ?></p>
                <p><strong>Beneficiaries:</strong> <span id="view-beneficiaries"></span></p>
                <p><strong>Status:</strong> 
                    <?php
                    $status = strtolower($activity['status'] ?? 'active');
                    if ($status !== 'active') {
                        echo '<span style="color: #10b981; font-weight: 600;">Completed</span>';
                    } else {
                        echo '<span style="color: #c8102e; font-weight: 600;">Active</span>';
                    }
                    ?>
                </p>
            </div>

            <!-- Documentation / Photos -->
            <div style="margin-top: 32px;">
                <h2>Documentation / Photos (<?= $count ?>/<?= $maxPhotos ?>)</h2>

                <?php if (empty($images)): ?>
                    <p style="color: #6b7280;">No photos or documents uploaded yet.</p>
                <?php else: ?>
                    <div style="display: flex; flex-wrap: wrap; gap: 16px; margin-top: 16px;">
                        <?php foreach ($images as $img): ?>
                            <div class="photo-item">
                                <img src="../<?= htmlspecialchars($img['image_path']) ?>" 
                                     alt="Documentation image" 
                                     style="max-width: 220px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                                <button class="delete-photo-btn" 
                                        data-doc-id="<?= $img['id'] ?>"
                                        data-activity-id="<?= $id ?>"
                                        title="Delete this photo">
                                    ×
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($canAdd): ?>
                    <a href="add_documentation.php?id=<?= $id ?>" 
                       class="action-btn add" 
                       style="margin-top: 24px; background: #c8102e; text-decoration: none; color: white; display: inline-block;">
                        Add Documentation
                    </a>
                <?php else: ?>
                    <button class="action-btn add add-disabled" 
                            style="margin-top: 24px; background: #9ca3af; color: white; display: inline-block;"
                            title="Maximum 2 photos reached">
                        Add Documentation
                    </button>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>

    <script>
    // Beneficiaries summary – from current activity
    const rawJson = <?= json_encode($activity['beneficiaries_json'] ?? '[]') ?>;
    let beneficiariesJson = [];

    try {
        beneficiariesJson = JSON.parse(rawJson);
    } catch (e) {
        console.error('Failed to parse beneficiaries_json in view_activity:', e);
        console.log('Raw value was:', rawJson);
        beneficiariesJson = [];
    }

    const beneficiariesSpan = document.getElementById('view-beneficiaries');

    if (beneficiariesSpan) {
        let summary = 'None added';
        let total = 0;

        if (Array.isArray(beneficiariesJson) && beneficiariesJson.length > 0) {
            let parts = [];

            beneficiariesJson.forEach(b => {
                const typeText = (b.type || '').trim();
                const male   = Number(b.male   || 0);
                const female = Number(b.female || 0);

                if (typeText) {
                    if (male > 0 || female > 0) {
                        parts.push(`${typeText}: ${male} male, ${female} female`);
                    } else {
                        parts.push(typeText);
                    }
                    total += male + female;
                }
            });

            if (parts.length > 0) {
                summary = parts.join(' | ');
                if (total > 0) {
                    summary += ` | Total: ${total}`;
                }
            }
        }

        beneficiariesSpan.textContent = summary;
    } else {
        console.warn('Beneficiaries span element not found in view_activity');
    }

    // Delete photo handler (unchanged)
    document.querySelectorAll('.delete-photo-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            if (confirm('Delete this photo? This cannot be undone.')) {
                const docId = this.dataset.docId;
                const activityId = this.dataset.activityId;

                fetch('delete_documentation.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `doc_id=${encodeURIComponent(docId)}&activity_id=${encodeURIComponent(activityId)}`
                })
                .then(response => {
                    if (!response.ok) throw new Error('Network response was not ok');
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        this.closest('.photo-item').remove();
                        alert('Photo deleted successfully.');
                        location.reload();
                    } else {
                        alert('Failed: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(err => {
                    console.error('Delete error:', err);
                    alert('Network error while deleting.');
                });
            }
        });
    });
    </script>

</body>
</html>