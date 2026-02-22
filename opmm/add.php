<?php
// opmm/add.php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php");
    exit;
}

require_once '../config/db.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $quarter     = $_POST['quarter'] ?? '';
    $fiscal_year = trim($_POST['fiscal_year'] ?? '');
    $file        = $_FILES['opmm_file'] ?? null;

    // === Validation ===

    // Quarter must be one of 1st–4th
    $valid_quarters = ['1st', '2nd', '3rd', '4th'];
    if (!in_array($quarter, $valid_quarters)) {
        $error = 'Please select a valid quarter.';
    }

    // Fiscal year validation (single year, not future)
if (!preg_match('/^\d{4}$/', $fiscal_year)) {
    $error = 'Fiscal year must be exactly 4 digits (e.g., 2026).';
} else {
    $year = (int)$fiscal_year;
    $current_year = 2026;  // update this when time passes

    if ($year > $current_year) {
        $error = 'Cannot add future fiscal years beyond ' . $current_year . '.';
    } elseif ($year === $current_year) {
        // For 2026, only 1st quarter allowed right now (Feb)
        if ($quarter !== '1st') {
            $error = 'For Fiscal Year ' . $current_year . ', only 1st Quarter is allowed at this time.';
        }
    }
}

    // File upload validation
    if (!$error && $file && $file['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']; // .xlsx
        $max_size = 10 * 1024 * 1024; // 10 MB

        if (!in_array($file['type'], $allowed_types)) {
            $error = 'Only Excel files (.xlsx) are allowed at this time.';
        } elseif ($file['size'] > $max_size) {
            $error = 'File is too large. Maximum 10 MB.';
        }
    } else if (!$error) {
        $error = 'Please select a file to upload.';
    }

    // If no errors → show confirmation
    if (!$error) {
        // Store in session for confirmation step
        $_SESSION['pending_opmm'] = [
            'quarter'     => $quarter,
            'fiscal_year' => $fiscal_year,
            'file_tmp'    => $file['tmp_name'],
            'file_name'   => $file['name'],
            'file_size'   => $file['size'],
            'file_type'   => $file['type']
        ];

        // Show confirmation (we'll handle save in next POST)
        $show_confirmation = true;
    }
}

// Handle final confirmation & save
if (isset($_POST['confirm']) && isset($_SESSION['pending_opmm'])) {
    $data = $_SESSION['pending_opmm'];

    // Move file to permanent location
    $upload_dir = 'D:/JUSTINE/opmm-uploads/';
    $new_file_name = time() . '_' . basename($data['file_name']);
    $destination = $upload_dir . $new_file_name;

    if (move_uploaded_file($data['file_tmp'], $destination)) {
        // Save to database
        $stmt = $pdo->prepare("
            INSERT INTO opmm_entries 
            (fiscal_year, quarter, file_name, file_path, file_size, uploaded_by)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['fiscal_year'],
            $data['quarter'],
            $data['file_name'],
            $destination,
            $data['file_size'],
            $_SESSION['user_id'] ?? null
        ]);

        unset($_SESSION['pending_opmm']);
        $success = 'OPMM added successfully!';
        header("Location: list.php");
        exit;
    } else {
        $error = 'Failed to save the file.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New OPMM</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>

    <header class="top-bar">
        <div class="logo-container">
            <img src="../assets/bsu-logo.jpg" alt="BatStateU Logo" class="logo">
            <span class="logo-text">OPMM Dashboard</span>
        </div>
        <nav class="main-nav">
            <a href="../index.php" class="nav-button">Home</a>
            <a href="list.php" class="nav-button">OPMM</a>
            <a href="../logout.php" class="nav-button logout">Logout</a>
        </nav>
    </header>

    <main class="dashboard-content">
        <h1>Add New OPMM</h1>

        <?php if ($error): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <?php if (isset($success)): ?>
            <p class="success"><?= htmlspecialchars($success) ?></p>
        <?php endif; ?>

        <?php if (isset($show_confirmation)): $d = $_SESSION['pending_opmm']; ?>
            <div class="confirmation-box">
                <h2>Confirm OPMM Details</h2>
                <p><strong>Quarter:</strong> <?= htmlspecialchars($d['quarter']) ?></p>
                <p><strong>Fiscal Year:</strong> <?= htmlspecialchars($d['fiscal_year']) ?></p>
                <p><strong>File:</strong> <?= htmlspecialchars($d['file_name']) ?></p>
                <p><strong>Size:</strong> <?= number_format($d['file_size'] / 1024, 2) ?> KB</p>

                <form method="POST">
                    <button type="submit" name="confirm">Confirm & Save</button>
                    <a href="add.php" class="cancel-link">Cancel</a>
                </form>
            </div>
        <?php else: ?>
            <form method="POST" enctype="multipart/form-data">
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
                    placeholder="e.g., 2026" required pattern="\d{4}" maxlength="4" minlength="4">

                <label for="opmm_file">Upload File (.xlsx only)</label>
                <input type="file" id="opmm_file" name="opmm_file" accept=".xlsx" required>

                <button type="submit">Review & Add</button>
            </form>
        <?php endif; ?>
    </main>
</body>
</html>