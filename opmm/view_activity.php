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
    // Fetch activity + parent project + grandparent program (for display only)
    $stmt = $pdo->prepare("
        SELECT 
            a.id, a.activity_name, a.implementation_start, a.implementation_end,
            a.type_of_extension_service_agenda, a.sdg_goals,
            a.offices_involved, a.programs_involved, a.beneficiaries_json,
            a.status AS activity_status,
            p.id AS project_id, p.project_title,
            pr.id AS program_id, pr.title AS program_title
        FROM activity_entries a
        LEFT JOIN project_entries p ON a.project_id = p.id
        LEFT JOIN program_entries pr ON p.program_id = pr.id
        WHERE a.id = ?
    ");
    $stmt->execute([$id]);
    $activity = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$activity) {
        header("Location: list.php");
        exit;
    }

    // Use REAL activity status only
    $status = strtolower($activity['activity_status'] ?? 'active');

    // Uploaded images
    $imgStmt = $pdo->prepare("
        SELECT id, image_path 
        FROM activity_documents 
        WHERE activity_id = ? 
        ORDER BY uploaded_at DESC
    ");
    $imgStmt->execute([$id]);
    $images = $imgStmt->fetchAll(PDO::FETCH_ASSOC);

    $count = count($images);
    $maxPhotos = 2;
    $canAdd = $count < $maxPhotos;
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

$nav_links = [
    ['url' => 'index.php', 'label' => 'Home', 'active' => false],
    ['url' => '/opmm/view_project.php?id=' . $activity['project_id'], 'label' => 'Project', 'active' => false],
];
?>

<?php include '../includes/header.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Activity - <?= htmlspecialchars($activity['activity_name'] ?? 'Activity') ?></title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">

    <style>
        .photo-item { position: relative; display: inline-block; }
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
        .delete-photo-btn:hover { opacity: 1; background: #dc2626; }
        .add-disabled {
            background: #9ca3af !important;
            cursor: not-allowed !important;
            pointer-events: none !important;
            opacity: 0.7;
        }

        /* Status text colors only (no background/pill) - consistent with other views */
        .status-text {
            font-weight: 600;
            font-size: 1rem;
        }

        .status-active    { color: #3b82f6; } /* blue (now matches view.php / view_project.php) */
        .status-completed { color: #10b981; } /* green */
    </style>
</head>
<body>

    <main class="dashboard-content">
        <?php if (isset($error)): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php else: ?>
            <h1><?= htmlspecialchars($activity['activity_name']) ?></h1>

            <div class="program-details">
                <p><strong>Parent Project:</strong> <?= htmlspecialchars($activity['project_title'] ?? 'Unknown') ?></p>
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
                    $statusClass = ($status === 'completed') ? 'status-completed' : 'status-active';
                    $statusText  = ($status === 'completed') ? 'Completed' : 'Active';
                    ?>
                    <span class="status-text <?= $statusClass ?>"><?= $statusText ?></span>
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
    // Beneficiaries summary (unchanged)
    const rawJson = <?= json_encode($activity['beneficiaries_json'] ?? '[]') ?>;
    let beneficiariesJson = [];

    try {
        beneficiariesJson = JSON.parse(rawJson);
    } catch (e) {
        console.error('Failed to parse beneficiaries_json in view_activity:', e);
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