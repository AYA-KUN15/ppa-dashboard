<?php
// index.php (Home / Monitoring Dashboard)
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

require_once 'config/db.php';

$today = date('Y-m-d');
$currentDayOfWeek = date('N'); // 1 = Monday, 7 = Sunday

try {
    // Get all active entries with frequency
    $stmt = $pdo->query("
        SELECT id, title, quarter, fiscal_year, frequency_monitoring, date_duration
        FROM ppa_entries
        WHERE status = 'active'
        ORDER BY created_at DESC
    ");
    $allEntries = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Filter entries that need monitoring today/this week
    $dueToday = [];
    $dueThisWeek = [];

    foreach ($allEntries as $entry) {
        $freq = $entry['frequency_monitoring'] ?? '';

        if (empty($freq)) continue;

        $needsAttention = false;

        switch (strtolower($freq)) {
            case 'daily':
                $needsAttention = true; // every day
                break;

            case 'weekly':
                // Assume weekly means every week on the same day of week as date_duration (simple heuristic)
                // You can refine this later
                $needsAttention = true; // for now show all weekly every day
                break;

            case 'bi-weekly':
                // Every two weeks — requires tracking last monitored date (future feature)
                $needsAttention = true; // placeholder
                break;

            case 'monthly':
                // Show on the same date every month (e.g., if date_duration is 6th, show on 6th)
                $dayOfMonth = (int)date('j', strtotime($today));
                if (strpos($entry['date_duration'], date('j')) !== false) {
                    $needsAttention = true;
                }
                break;

            case 'quarterly':
                // Show at the start of each quarter
                $currentQuarter = ceil(date('n') / 3);
                if ($currentQuarter != ceil(date('n', strtotime($entry['date_duration'])) / 3)) {
                    $needsAttention = true;
                }
                break;

            case 'annually':
                // Show on anniversary date
                if (date('m-d', strtotime($today)) === date('m-d', strtotime($entry['date_duration']))) {
                    $needsAttention = true;
                }
                break;

            case 'as needed':
            case 'event-based':
                // Show always or based on custom logic
                $needsAttention = true;
                break;
        }

        if ($needsAttention) {
            $dueToday[] = $entry; // for now put all in "due today" – refine later
        }
    }
} catch (PDOException $e) {
    $dueToday = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PPA Dashboard - Home</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

    <header class="top-bar">
        <div class="logo-container">
            <img src="assets/bsu-logo.jpg" alt="BSU Logo" class="logo">
            <span class="logo-text">PPA Dashboard</span>
        </div>
        <nav class="main-nav">
            <a href="index.php" class="nav-button active">Home</a>
            <a href="opmm/list.php" class="nav-button">PPA</a>
            <a href="logout.php" class="nav-button logout">Logout</a>
        </nav>
    </header>

    <main class="dashboard-content">
        <h1>PPA Monitoring Dashboard</h1>
        <p>Items that may need attention today / this week based on monitoring frequency.</p>

        <?php if (empty($dueToday)): ?>
            <p class="info">No PPAs require monitoring today.</p>
        <?php else: ?>
            <div class="summary-grid">
                <div class="summary-card">
                    <h3>Due Today / This Week</h3>
                    <p class="placeholder"><?= count($dueToday) ?></p>
                </div>
            </div>

            <div class="due-list" style="margin-top: 32px;">
                <h3>Activities to Monitor</h3>
                <ul style="list-style: none; padding: 0;">
                    <?php foreach ($dueToday as $entry): ?>
                        <li style="padding: 12px; border-bottom: 1px solid #e5e7eb;">
                            <a href="opmm/view.php?id=<?= $entry['id'] ?>" style="text-decoration: none; color: #374151;">
                                <strong><?= htmlspecialchars($entry['title']) ?></strong><br>
                                <small>
                                    <?= htmlspecialchars($entry['quarter']) ?> Quarter, FY <?= htmlspecialchars($entry['fiscal_year']) ?>
                                    · <?= htmlspecialchars($entry['frequency_monitoring'] ?? 'Not specified') ?>
                                </small>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div style="text-align: center; margin-top: 48px;">
            <a href="opmm/list.php" class="action-btn add" style="font-size: 1.3rem; padding: 18px 48px;">
                View All PPAs
            </a>
        </div>
    </main>
</body>
</html>