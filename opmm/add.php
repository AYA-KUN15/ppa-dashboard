<?php
// add.php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php");
    exit;
}

require_once '../config/db.php';

$error = '';
$show_confirmation = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['confirm']) && isset($_SESSION['pending_ppa'])) {
        // Save after confirmation
        $d = $_SESSION['pending_ppa'];

        $stmt = $pdo->prepare("
            INSERT INTO ppa_entries (
                fiscal_year, quarter, title, date_duration,
                beneficiaries_male, beneficiaries_female, beneficiaries_department,
                location, extensionists, partner_agencies, frequency_monitoring, budget_allocation, source_of_fund
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $d['fiscal_year'], $d['quarter'], $d['title'], $d['date_duration'],
            $d['male'], $d['female'], $d['dept'] ?? null,
            $d['location'], $d['extensionists'], $d['partners'] ?? null, 'frequency' => $frequency,
            $d['budget'], $d['fund_source'] ?? null
        ]);

        unset($_SESSION['pending_ppa']);
        header("Location: list.php?success=1");
        exit;
    } else {
        // First submission - validate & collect
        $quarter     = $_POST['quarter'] ?? '';
        $fiscal_year = trim($_POST['fiscal_year'] ?? '');
        $title       = trim($_POST['title'] ?? '');
        $date_duration = trim($_POST['date_duration'] ?? '');
        $male        = (int)($_POST['beneficiaries_male'] ?? 0);
        $female      = (int)($_POST['beneficiaries_female'] ?? 0);
        $dept        = trim($_POST['beneficiaries_department'] ?? '');
        $location    = trim($_POST['location'] ?? '');
        $extensionists = trim($_POST['extensionists'] ?? '');
        $partners    = trim($_POST['partner_agencies'] ?? '');
        $frequency = trim($_POST['frequency_monitoring'] ?? '');
        $budget      = (float)($_POST['budget_allocation'] ?? 0);
        $fund_source = trim($_POST['source_of_fund'] ?? '');

        // Validation
        $valid_quarters = ['1st', '2nd', '3rd', '4th'];
        if (!in_array($quarter, $valid_quarters)) {
            $error = 'Please select a valid quarter.';
        } elseif (!preg_match('/^\d{4}$/', $fiscal_year)) {
            $error = 'Fiscal year must be exactly 4 digits (e.g., 2025).';
        } else {
            $year = (int)$fiscal_year;
            $current_year = 2026;

            if ($year > $current_year) {
                $error = 'Cannot add future fiscal years beyond ' . $current_year . '.';
            } elseif ($year < 2021) {
                $error = 'Fiscal year cannot be earlier than 2021.';
            } elseif ($year === $current_year) {
                if ($quarter !== '1st') {
                    $error = 'For Fiscal Year ' . $current_year . ', only 1st Quarter is allowed at this time.';
                }
            }
        }

        if (empty($title)) {
            $error = 'Title of Project/Program/Activity is required.';
        } elseif (empty($date_duration)) {
            $error = 'Date / Duration is required.';
        } elseif (empty($location)) {
            $error = 'Location is required.';
        } elseif (empty($extensionists)) {
            $error = 'Extensionists is required.';
        } elseif ($budget < 0) {
            $error = 'Budget Allocation cannot be negative.';
        }

        if (!$error) {
            $_SESSION['pending_ppa'] = [
                'quarter'     => $quarter,
                'fiscal_year' => $fiscal_year,
                'title'       => $title,
                'date_duration' => $date_duration,
                'male'        => $male,
                'female'      => $female,
                'dept'        => $dept,
                'location'    => $location,
                'extensionists' => $extensionists,
                'partners'    => $partners,
                'budget'      => $budget,
                'fund_source' => $fund_source
            ];
            $show_confirmation = true;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New PPA</title>
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
            <a href="list.php" class="nav-button">Home</a>
            <a href="../logout.php" class="nav-button logout">Logout</a>
        </nav>
    </header>

    <main class="dashboard-content">
        <h1>Add New PPA</h1>

        <?php if ($error): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <?php if ($show_confirmation): $d = $_SESSION['pending_ppa']; ?>
            <div class="confirmation-box">
                <h2>Review PPA Details</h2>
                <p><strong>Fiscal Year:</strong> <?= htmlspecialchars($d['fiscal_year']) ?></p>
                <p><strong>Quarter:</strong> <?= htmlspecialchars($d['quarter']) ?></p>
                <p><strong>Title:</strong> <?= htmlspecialchars($d['title']) ?></p>
                <p><strong>Date / Duration:</strong> <?= htmlspecialchars($d['date_duration']) ?></p>
                <p><strong>Beneficiaries (Male):</strong> <?= htmlspecialchars($d['male']) ?></p>
                <p><strong>Beneficiaries (Female):</strong> <?= htmlspecialchars($d['female']) ?></p>
                <p><strong>Beneficiary Department:</strong> <?= htmlspecialchars($d['dept'] ?: 'N/A') ?></p>
                <p><strong>Location:</strong> <?= htmlspecialchars($d['location']) ?></p>
                <p><strong>Extensionists:</strong> <?= htmlspecialchars($d['extensionists']) ?></p>
                <p><strong>Partner Agencies:</strong> <?= htmlspecialchars($d['partners'] ?: 'N/A') ?></p>
                <p><strong>Frequency of Monitoring:</strong> <?= htmlspecialchars($d['frequency'] ?: 'N/A') ?></p>
                <p><strong>Budget Allocation:</strong> ₱<?= number_format($d['budget'], 2) ?></p>
                <p><strong>Source of Fund:</strong> <?= htmlspecialchars($d['fund_source'] ?: 'N/A') ?></p>

                <form method="POST">
                    <button type="submit" name="confirm">Confirm & Save</button>
                    <a href="add.php" class="cancel-link">Cancel</a>
                </form>
            </div>
        <?php else: ?>
            <form method="POST">
                <label for="quarter">Quarter</label>
                <select id="quarter" name="quarter" required>
                    <option value="">Select quarter</option>
                    <option value="1st">1st Quarter</option>
                    <option value="2nd">2nd Quarter</option>
                    <option value="3rd">3rd Quarter</option>
                    <option value="4th">4th Quarter</option>
                </select>

                <label for="fiscal_year">Fiscal Year (YYYY)</label>
                <input type="text" id="fiscal_year" name="fiscal_year" 
                       placeholder="e.g., 2025" required pattern="\d{4}" maxlength="4">

                <label for="title">Title of Project/Program/Activity</label>
                <input type="text" id="title" name="title" required>

                <label for="date_duration">Date / Duration</label>
                <input type="text" id="date_duration" name="date_duration" required placeholder="e.g., July 6, 2025 / 8 hrs">

                <label for="beneficiaries_male">No. of Beneficiaries (Male)</label>
                <input type="number" id="beneficiaries_male" name="beneficiaries_male" min="0" required>

                <label for="beneficiaries_female">No. of Beneficiaries (Female)</label>
                <input type="number" id="beneficiaries_female" name="beneficiaries_female" min="0" required>

                <label for="beneficiaries_department">Beneficiary Department / Program</label>
                <input type="text" id="beneficiaries_department" name="beneficiaries_department">

                <label for="location">Location</label>
                <input type="text" id="location" name="location" required>

                <label for="extensionists">Extensionists</label>
                <input type="text" id="extensionists" name="extensionists" required>

                <label for="partner_agencies">Partner Agencies</label>
                <input type="text" id="partner_agencies" name="partner_agencies" placeholder="e.g., LGU Lipa City, DSWD">

                <label for="frequency_monitoring">Frequency of Monitoring</label>
<select id="frequency_monitoring" name="frequency_monitoring" required>
    <option value="">Select frequency</option>
    <option value="Monthly">Monthly</option>
    <option value="Quarterly">Quarterly</option>
    <option value="Semi-annual">Semi-annual</option>
    <option value="Annual">Annual</option>
</select>

                <label for="budget_allocation">Budget Allocation (₱)</label>
                <input type="number" id="budget_allocation" name="budget_allocation" step="0.01" min="0" required>

                <label for="source_of_fund">Source of Fund</label>
                <input type="text" id="source_of_fund" name="source_of_fund">

                <button type="submit">Review & Add</button>
            </form>
        <?php endif; ?>
    </main>
</body>
</html>