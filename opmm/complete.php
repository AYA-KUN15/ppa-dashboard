<?php
// complete.php - Fully Completed Programs (ended duration)
session_start();
// Start session: used for authentication and temporarily storing form data (e.g., pending activity before confirmation)

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php");
    exit;
}

require_once '../config/db.php';
// Load database connection (PDO instance)

try {
    // Prepare SQL query safely (prevents SQL injection)
$stmt = $pdo->prepare("
        SELECT id, title
        FROM program_entries
        WHERE status = 'completed' 
          AND duration_end < CURDATE()
        ORDER BY duration_end DESC, title ASC
    ");
    $stmt->execute();
    $completed = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
    $completed = [];
}

$nav_links = [
    ['url' => 'index.php', 'label' => 'Home', 'active' => false],
    ['url' => '/opmm/list.php', 'label' => 'PPA', 'active' => false],
];
?>

<?php include '../includes/header.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Completed Programs - PPA Dashboard</title>
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
            min-height: 1.2em; /* keeps spacing even when empty */
        }

        /* Green styling for Completed programs */
        .quarter-btn.completed {
            background: #ecfdf5 !important;
            border: 2px solid #10b981 !important;
            color: #065f46 !important;
        }

        .quarter-btn.completed:hover {
            background: #d1fae5 !important;
            border-color: #059669 !important;
        }

        /* Text wrap */
        .quarter-btn-title {
            white-space: normal;
            word-break: break-word;
        }
    </style>
</head>
<body>

    <main class="dashboard-content">
        <h1>Completed Programs</h1>

        <?php if (!empty($error)): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php elseif (empty($completed)): ?>
            <p>No fully completed programs yet.</p>
        <?php else: ?>
            <div class="quarter-scroll-container">
                <div class="quarter-buttons">
                    <?php foreach ($completed as $program): ?>
                        <div class="quarter-item">
                            <button class="quarter-btn completed"
                                    onclick="window.location.href='../opmm/view.php?mode=program&id=<?= $program['id'] ?>'">
                                <span class="quarter-btn-title"><?= htmlspecialchars($program['title']) ?></span>
                                <span class="quarter-btn-subtitle"></span> <!-- blank as requested -->
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <div style="margin-top: 24px;">
            <a href="archive.php" class="action-btn">Back to M&E Phase</a>
        </div>
    </main>

</body>
</html>