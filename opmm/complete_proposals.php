<?php
// complete_proposals.php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php");
    exit;
}

require_once '../config/db.php';

try {
    $stmt = $pdo->prepare("
        SELECT id, title, start_date, end_date, status
        FROM research_proposals
        WHERE status = 'published'
        ORDER BY end_date DESC, title ASC
    ");
    $stmt->execute();
    $published = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
    $published = [];
}

$nav_links = [
    ['url' => 'index.php', 'label' => 'Home', 'active' => false],
    ['url' => '/opmm/list.php', 'label' => 'PPA', 'active' => false],
    ['url' => '/opmm/list_proposals.php', 'label' => 'Proposals', 'active' => false],
];
?>

<?php include '../includes/header.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Published Proposals</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>

    <main class="dashboard-content">
        <h1>Published Proposals</h1>

        <?php if (!empty($error)): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php elseif (empty($published)): ?>
            <p>No published proposals yet.</p>
        <?php else: ?>
            <div class="quarter-scroll-container">
                <div class="quarter-buttons">
                    <?php foreach ($published as $prop): ?>
                        <div class="quarter-item">
                            <button class="quarter-btn completed-project"
                                    onclick="window.location.href='view_proposals.php?id=<?= $prop['id'] ?>'">
                                <span class="quarter-btn-title"><?= htmlspecialchars($prop['title']) ?></span>
                                <span class="quarter-btn-subtitle">
                                    <?= date('M d, Y', strtotime($prop['start_date'])) ?> – 
                                    <?= date('M d, Y', strtotime($prop['end_date'])) ?>
                                </span>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </main>

</body>
</html>