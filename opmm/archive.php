<?php
// archive.php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php");
    exit;
}

require_once '../config/db.php';

try {
    $stmt = $pdo->query("
        SELECT id, title, location, duration_start, duration_end,
               type_of_extension_service_agenda, sdg_goals, year_of_implementation
        FROM program_entries
        WHERE status = 'completed'
        ORDER BY updated_at DESC, title ASC
    ");
    $programs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
    $programs = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Completed Programs - PPA Dashboard</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>

    <header class="top-bar">
        <div class="logo-container">
            <img src="../assets/bsu-logo.jpg" alt="BatStateU Logo" class="logo">
            <span class="logo-text">PPA Dashboard</span>
        </div>
        <nav class="main-nav">
            <a href="../index.php" class="nav-button">Home</a>
            <a href="list.php" class="nav-button">Active PPA</a>
            <a href="../logout.php" class="nav-button logout">Logout</a>
        </nav>
    </header>

    <main class="dashboard-content">
        <h1>Completed Programs</h1>

        <?php if (!empty($error)): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php elseif (empty($programs)): ?>
            <p>No completed programs found.</p>
        <?php else: ?>
            <div class="quarter-scroll-container">
                <div class="quarter-buttons">
                    <?php foreach ($programs as $program): ?>
                        <div class="quarter-item">
                            <button class="quarter-btn" 
                                    onclick="window.location.href='view.php?mode=program&id=<?= $program['id'] ?>'">
                                <span class="quarter-btn-title"><?= htmlspecialchars($program['title']) ?></span>
                                <span class="quarter-btn-subtitle">
                                    <?= htmlspecialchars($program['type_of_extension_service_agenda']) ?> · 
                                    Completed
                                </span>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <script src="../js/dashboard.js"></script>
</body>
</html>