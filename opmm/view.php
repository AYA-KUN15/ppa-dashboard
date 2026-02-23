<?php
// view.php
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
        SELECT * FROM ppa_entries 
        WHERE id = ? AND status = 'active'
    ");
    $stmt->execute([$id]);
    $entry = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$entry) {
        header("Location: list.php");
        exit;
    }
} catch (PDOException $e) {
    // Handle error gracefully
    $error = "Database error: " . $e->getMessage();
    $entry = null;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PPA Dashboard - View Entry</title>
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
            <a href="../logout.php" class="nav-button logout">Logout</a>
        </nav>
    </header>

    <main class="dashboard-content">
    <h1>View PPA Entry</h1>

    <?php if ($entry): ?>
        <div class="view-table">
            <table>
                <tr><th>Fiscal Year</th><td><?= htmlspecialchars($entry['fiscal_year']) ?></td></tr>
                <tr><th>Quarter</th><td><?= htmlspecialchars($entry['quarter']) ?></td></tr>
                <tr><th>Title</th><td><?= htmlspecialchars($entry['title']) ?></td></tr>
                <tr><th>Date / Duration</th><td><?= htmlspecialchars($entry['date_duration']) ?></td></tr>
                <tr><th>Beneficiaries (Male)</th><td><?= htmlspecialchars($entry['beneficiaries_male']) ?></td></tr>
                <tr><th>Beneficiaries (Female)</th><td><?= htmlspecialchars($entry['beneficiaries_female']) ?></td></tr>
                <tr><th>Beneficiary Department</th><td><?= htmlspecialchars($entry['beneficiaries_department'] ?: 'N/A') ?></td></tr>
                <tr><th>Location</th><td><?= htmlspecialchars($entry['location']) ?></td></tr>
                <tr><th>Extensionists</th><td><?= htmlspecialchars($entry['extensionists']) ?></td></tr>
                <tr><th>Partner Agencies</th><td><?= htmlspecialchars($entry['partner_agencies'] ?: 'N/A') ?></td></tr>
                <tr><th>Budget Allocation</th><td>₱<?= number_format($entry['budget_allocation'], 2) ?></td></tr>
                <tr><th>Source of Fund</th><td><?= htmlspecialchars($entry['source_of_fund'] ?: 'N/A') ?></td></tr>
                <tr><th>Created</th><td><?= date('M d, Y h:i A', strtotime($entry['created_at'])) ?></td></tr>
                <tr><th>Last Updated</th><td><?= date('M d, Y h:i A', strtotime($entry['updated_at'])) ?></td></tr>
            </table>

            <div class="view-actions">
                <a href="list.php" class="back-btn">Back to List</a>
            </div>
        </div>
    <?php endif; ?>
</main>
</body>
</html>