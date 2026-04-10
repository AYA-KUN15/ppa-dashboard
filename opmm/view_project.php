<?php
session_start();
// Start session: used for authentication and temporarily storing form data (e.g., pending activity before confirmation)

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header("Location: ../login.php");
    exit;
}

require_once '../config/db.php';
// Load database connection (PDO instance)

$id = $_GET['id'] ?? null;

if (!$id || !is_numeric($id)) {
    header("Location: list.php");
    exit;
}

try {
    // Fetch project
    // Prepare SQL query safely (prevents SQL injection)
$stmt = $pdo->prepare("
        SELECT program_id, project_title, implementation_start, implementation_end,
               type_of_extension_service_agenda, sdg_goals,
               offices_involved, programs_involved, beneficiaries_json, status
        FROM project_entries
        WHERE id = ?
    ");
    $stmt->execute([$id]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);
// Fetch parent project details (used to restrict activity inputs like dates and selectable options)

    if (!$project) {
        header("Location: list.php");
        exit;
    }

    // Fetch parent program title
    $progStmt = $pdo->prepare("SELECT title FROM program_entries WHERE id = ?");
    $progStmt->execute([$project['program_id']]);
    $program = $progStmt->fetch(PDO::FETCH_ASSOC);

    // Fetch all activities
    $actStmt = $pdo->prepare("
        SELECT id, activity_name, implementation_start, implementation_end, status
        FROM activity_entries
        WHERE project_id = ?
        ORDER BY implementation_start ASC
    ");
    $actStmt->execute([$id]);
    $activities = $actStmt->fetchAll(PDO::FETCH_ASSOC);

    // Count incomplete (active) activities
    $incompleteCount = 0;
    foreach ($activities as $act) {
        if ($act['status'] === 'active') {
            $incompleteCount++;
        }
    }

    // Handle POST: complete an activity
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_activity'])) {
        $activity_id = (int)($_POST['activity_id'] ?? 0);

        if ($activity_id <= 0) {
            $error = 'Invalid activity ID.';
        } else {
            try {
                $pdo->beginTransaction();

                // 1. Mark the activity as completed
                // Prepare SQL query safely (prevents SQL injection)
$stmt = $pdo->prepare("
                    UPDATE activity_entries 
                    SET status = 'completed', 
                        updated_at = NOW() 
                    WHERE id = ? AND project_id = ?
                ");
                $stmt->execute([$activity_id, $id]);

                // 2. Re-count remaining active activities
                $recheck = $pdo->prepare("
                    SELECT COUNT(*) 
                    FROM activity_entries 
                    WHERE project_id = ? AND status = 'active'
                ");
                $recheck->execute([$id]);
                $newIncomplete = $recheck->fetchColumn();

                // 3. If zero left → auto-complete the project
                if ($newIncomplete === 0) {
                    $projStmt = $pdo->prepare("
                        UPDATE project_entries 
                        SET status = 'completed', 
                            updated_at = NOW() 
                        WHERE id = ?
                    ");
                    $projStmt->execute([$id]);
                }

                $pdo->commit();

                header("Location: view_project.php?id=$id&success=activity_completed");
                exit;
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = 'Failed to complete activity: ' . $e->getMessage();
            }
        }
    }

} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

$nav_links = [
    ['url' => 'index.php', 'label' => 'Home', 'active' => false],
    ['url' => '/opmm/view.php?id=' . $project['program_id'], 'label' => 'Program', 'active' => false],
    ['url' => '/opmm/list_proposals.php', 'label' => 'Proposals', 'active' => false],
];
?>

<?php include '../includes/header.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Project - <?= htmlspecialchars($project['project_title'] ?? 'Project') ?></title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">

    <style>
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

        .edit-icon-btn,
        .complete-icon-btn {
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

        .edit-icon-btn .material-icons,
        .complete-icon-btn .material-icons {
            font-size: 1.6rem;
            color: #6b7280;
        }

        .edit-icon-btn:hover,
        .complete-icon-btn:hover {
            color: #c8102e;
            background: rgba(200, 16, 46, 0.1);
        }

        .quarter-btn.completed-project {
            background: #ecfdf5 !important;
            border: 2px solid #10b981 !important;
            color: #065f46 !important;
        }

        .quarter-btn.completed-project:hover {
            background: #d1fae5 !important;
            border-color: #059669 !important;
        }

        .status-text {
            font-weight: 600;
            font-size: 1rem;
        }

        .status-active    { color: #3b82f6; }
        .status-completed { color: #10b981; }
    </style>
</head>

<body>

    <main class="dashboard-content">
        <?php if (isset($error)): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php else: ?>
            <h1><?= htmlspecialchars($project['project_title']) ?></h1>

            <div class="program-details">
                <p><strong>Parent Program:</strong> <?= htmlspecialchars($program['title'] ?? 'Unknown') ?></p>
                <p><strong>Project Title:</strong> <?= htmlspecialchars($project['project_title']) ?></p>
                <p><strong>Implementation Duration:</strong> 
                    <?php
                    $start = $project['implementation_start'] ? date('M d, Y', strtotime($project['implementation_start'])) : 'N/A';
                    $end   = $project['implementation_end']   ? date('M d, Y', strtotime($project['implementation_end']))   : 'N/A';
                    echo htmlspecialchars($start . ($end !== 'N/A' ? ' – ' . $end : ''));
                    ?>
                </p>
                <p><strong>Type of Extension Service Agenda:</strong> <?= htmlspecialchars($project['type_of_extension_service_agenda'] ?? 'N/A') ?></p>
                <p><strong>SDG Goals:</strong> <?= htmlspecialchars($project['sdg_goals'] ?? 'N/A') ?></p>
                <p><strong>Offices Involved:</strong> <?= htmlspecialchars($project['offices_involved'] ?? 'N/A') ?></p>
                <p><strong>Programs Involved:</strong> <?= htmlspecialchars($project['programs_involved'] ?? 'N/A') ?></p>
                <p><strong>Beneficiaries:</strong> 
                    <?php
                    $benefs = json_decode($project['beneficiaries_json'] ?? '[]', true);
                    if (is_array($benefs) && !empty($benefs)) {
                        $parts = [];
                        $total = 0;
                        foreach ($benefs as $b) {
                            $type = htmlspecialchars($b['type'] ?? 'Unnamed');
                            $m = (int)($b['male'] ?? 0);
                            $f = (int)($b['female'] ?? 0);
                            $line = $type;
                            if ($m > 0 || $f > 0) $line .= ": $m male, $f female";
                            $parts[] = $line;
                            $total += $m + $f;
                        }
                        $summary = implode(' | ', $parts);
                        if ($total > 0) $summary .= " | Total: $total";
                        echo $summary;
                    } else {
                        echo 'None added';
                    }
                    ?>
                </p>
                <p><strong>Status:</strong> 
                    <?php
                    $statusClass = ($project['status'] === 'completed') ? 'status-completed' : 'status-active';
                    $statusText = ($project['status'] === 'completed') ? 'Completed' : 'Active';
                    ?>
                    <span class="status-text <?= $statusClass ?>"><?= $statusText ?></span>
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
                                <div class="quarter-item">
                                    <button class="quarter-btn <?= ($activity['status'] !== 'active') ? 'completed-project' : '' ?>" 
                                            onclick="window.location.href='view_activity.php?id=<?= $activity['id'] ?>'">
                                        <span class="quarter-btn-title"><?= htmlspecialchars($activity['activity_name']) ?></span>
                                        <span class="quarter-btn-subtitle">
                                            <?php
                                            $actStart = $activity['implementation_start'] ? date('M d, Y', strtotime($activity['implementation_start'])) : 'N/A';
                                            $actEnd   = $activity['implementation_end']   ? date('M d, Y', strtotime($activity['implementation_end']))   : '';
                                            echo htmlspecialchars($actStart . ($actEnd ? ' – ' . $actEnd : ''));
                                            ?>
                                        </span>
                                    </button>

                                    <button class="action-icon edit-icon-btn" 
                                            onclick="window.location.href='edit_activity.php?id=<?= $activity['id'] ?>'"
                                            title="Edit activity">
                                        <span class="material-icons">edit</span>
                                    </button>

                                    <?php if ($activity['status'] === 'active'): ?>
                                        <button class="action-icon complete-icon-btn" 
                                                onclick="confirmCompleteActivity(<?= $activity['id'] ?>, <?= $incompleteCount ?>)"
                                                title="Mark as Completed">
                                            <span class="material-icons">check_circle</span>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div style="margin-top: 16px;">
                    <a href="add_activity.php?project_id=<?= $id ?>" class="action-btn add">Add New Activity</a>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <!-- Hidden form for completing activity -->
    <form id="complete-activity-form" method="POST" style="display:none;">
        <input type="hidden" name="complete_activity" value="1">
        <input type="hidden" name="activity_id" id="activity_id_field" value="">
    </form>

    <script>
    function confirmCompleteActivity(activityId, incompleteCount) {
        let message = "Mark this activity as completed?";

        if (incompleteCount === 1) {
            message = "This is the last incomplete activity. Completing it will also mark the entire Project as Completed. Are you sure?";
        }

        if (confirm(message)) {
            document.getElementById('activity_id_field').value = activityId;
            document.getElementById('complete-activity-form').submit();
        }
    }
    </script>

</body>
</html>