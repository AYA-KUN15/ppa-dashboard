<?php
// index.php
session_start();

// Redirect to login if not authenticated
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OPMM Dashboard - Home</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

    <!-- Top navigation bar -->
    <header class="top-bar">
        <div class="logo-container">
            <!-- Placeholder logo – replace src later with real image -->
            <img src="assets/bsu-logo.jpg" alt="OPMM Logo" class="logo">
            <span class="logo-text">OPMM Dashboard</span>
        </div>

        <nav class="main-nav">
            <a href="index.php" class="nav-button active">Home</a>
            <a href="opmm/list.php" class="nav-button">OPMM</a>
            <a href="logout.php" class="nav-button logout">Logout</a>
        </nav>
    </header>

    <!-- Main content area -->
    <main class="dashboard-content">

        <!-- Filter section -->
        <div class="filter-section">
            <button class="filter-button">Filter by Fiscal Year ▼</button>
            <!-- Hidden dropdown – can be shown with JS later -->
            <div class="filter-dropdown" style="display: none;">
                <select name="fiscal_year">
                    <option value="">All Fiscal Years</option>
                    <option value="2023-2024">2023-2024</option>
                    <option value="2024-2025">2024-2025</option>
                    <option value="2025-2026">2025-2026</option>
                </select>
            </div>
        </div>

        <!-- Placeholder for graph summaries / cards -->
        <div class="summary-grid">
            <div class="summary-card">
                <h3>Total Budget</h3>
                <p class="placeholder">₱ —</p>
            </div>
            <div class="summary-card">
                <h3>Total Spent</h3>
                <p class="placeholder">₱ —</p>
            </div>
            <div class="summary-card">
                <h3>Overall Accomplishment</h3>
                <p class="placeholder">— %</p>
            </div>
            <div class="summary-card">
                <h3>Activities Completed</h3>
                <p class="placeholder">— / —</p>
            </div>
        </div>

        <!-- Space for future charts -->
        <div class="chart-area">
            <p class="coming-soon">Graph summaries will appear here (e.g., budget utilization, progress by quarter)</p>
        </div>

    </main>

    <script src="js/dashboard.js"></script>
</body>
</html>