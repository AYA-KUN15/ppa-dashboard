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
        SELECT project_id, activity_name, date_of_implementation, status
        FROM activity_entries
        WHERE id = ?
    ");
    $stmt->execute([$id]);
    $activity = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$activity) {
        header("Location: list.php");
        exit;
    }

    $projStmt = $pdo->prepare("SELECT project_title FROM project_entries WHERE id = ?");
    $projStmt->execute([$activity['project_id']]);
    $project = $projStmt->fetch(PDO::FETCH_ASSOC);
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
</head>
<body>

    <header class="top-bar">
        <div class="logo-container">
            <img src="../assets/bsu-logo.jpg" alt="BSU Logo" class="logo">
            <span class="logo-text">PPA Dashboard</span>
        </div>
        <nav class="main-nav">
            <a href="../index.php" class="nav-button">Home</a>
            <a href="view_project.php?id=<?= $activity['project_id'] ?>" class="nav-button">Back to Project</a>
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
                <p><strong>Date of Implementation:</strong> <?= htmlspecialchars($activity['date_of_implementation']) ?></p>
                <p><strong>Status:</strong> 
                    <span style="color: <?= $activity['status'] === 'completed' ? '#10b981' : '#c8102e' ?>; font-weight: 600;">
                        <?= htmlspecialchars(ucfirst($activity['status'] ?? 'Active')) ?>
                    </span>
                </p>
            </div>

            <div style="margin-top: 32px;">
                <a href="edit_activity.php?id=<?= $id ?>" class="action-btn edit">Edit This Activity</a>
            </div>

        <?php endif; ?>
    </main>

</body>
</html>