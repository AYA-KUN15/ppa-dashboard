<?php
// archive.php - Monitoring & Evaluation Phase (completed programs)
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php");
    exit;
}

require_once '../config/db.php';

try {
    $stmt = $pdo->prepare("
        SELECT id, title, location, duration_start, duration_end,
               type_of_extension_service_agenda, sdg_goals, status
        FROM program_entries
        WHERE status != 'active'
        ORDER BY updated_at DESC, title ASC
    ");
    $stmt->execute();
    $programs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
    $programs = [];
}

$nav_links = [
    ['url' => 'index.php', 'label' => 'Home', 'active' => false],
    ['url' => '/opmm/list.php',     'label' => 'PPA',  'active' => false],
];
?>

<?php include '../includes/header.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitoring & Evaluation Phase - PPA Dashboard</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">

    <style>
        /* Reuse same quarter button styles from list.php / view_project.php */
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

        /* Completed green style */
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
        <h1>Monitoring & Evaluation Phase</h1>

        <?php if (!empty($error)): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php elseif (empty($programs)): ?>
            <p>No programs in Monitoring & Evaluation phase yet.</p>
        <?php else: ?>
            <div class="quarter-scroll-container">
                <div class="quarter-buttons">
                    <?php foreach ($programs as $program): ?>
                        <div class="quarter-item">
                            <!-- Main card button goes to VIEW page -->
                            <button class="quarter-btn completed-project"
                                    onclick="window.location.href='../view.php?mode=program&id=<?= $program['id'] ?>'">
                                <span class="quarter-btn-title"><?= htmlspecialchars($program['title']) ?></span>
                                <span class="quarter-btn-subtitle">
                                    M&E Phase
                                </span>
                            </button>

                            <!-- Pencil icon goes to EDIT page (to allow reactivation/changes) -->
                            <button class="action-icon edit-icon-btn"
                                    onclick="window.location.href='../edit.php?mode=program&id=<?= $program['id'] ?>'"
                                    title="Edit program">
                                <span class="material-icons">edit</span>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <div style="margin-top: 16px;">
            <a href="../list.php" class="action-btn">Back to Active Programs</a>
        </div>
    </main>

</body>
</html>