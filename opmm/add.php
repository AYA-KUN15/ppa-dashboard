<?php
// add.php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php");
    exit;
}

require_once '../config/db.php';

$mode = $_GET['mode'] ?? '';
$valid_modes = ['program', 'project', 'activity'];

if (!in_array($mode, $valid_modes)) {
    header("Location: list.php");
    exit;
}

$error = '';
$show_confirmation = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['confirm']) && isset($_SESSION['pending_' . $mode])) {
        $d = $_SESSION['pending_' . $mode];

        if ($mode === 'program') {
            $stmt = $pdo->prepare("
                INSERT INTO program_entries (
                    title, location, duration_start, duration_end,
                    type_of_extension_service_agenda, sdg_goals, offices_involved,
                    programs_involved, partner_agencies, beneficiaries_json,
                    total_cost, source_of_fund
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $d['title'], $d['location'], $d['duration_start'], $d['duration_end'],
                $d['type'], $d['sdg'], $d['offices'], $d['programs'], $d['partners'],
                $d['beneficiaries_json'], $d['total_cost'], $d['source_fund']
            ]);
        } elseif ($mode === 'project') {
            $stmt = $pdo->prepare("
                INSERT INTO project_entries (
                    program_id, project_title, date_of_implementation
                ) VALUES (?, ?, ?)
            ");
            $stmt->execute([
                $d['program_id'], $d['project_title'], $d['date_of_implementation']
            ]);
        } elseif ($mode === 'activity') {
            $stmt = $pdo->prepare("
                INSERT INTO activity_entries (
                    project_id, activity_no, activity_name, date_of_implementation
                ) VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $d['project_id'], $d['activity_no'], $d['activity_name'], $d['date_of_implementation']
            ]);
        }

        unset($_SESSION['pending_' . $mode]);
        header("Location: list.php?success=added");
        exit;
    } else {
        // Collect and validate
        if ($mode === 'program') {
            $title = trim($_POST['title'] ?? '');
            $location = trim($_POST['location'] ?? '');
            $duration_start = $_POST['duration_start'] ?? '';
            $duration_end = $_POST['duration_end'] ?? '';
            $type = trim($_POST['type_of_extension_service_agenda'] ?? '');
            $sdg = trim($_POST['sdg_goals'] ?? '');
            $offices = trim($_POST['offices_involved'] ?? '');
            $programs = trim($_POST['programs_involved'] ?? '');
            $partners = trim($_POST['partner_agencies'] ?? '');
            $beneficiaries_json = trim($_POST['beneficiaries_json'] ?? '[]');
            $total_cost = (float)($_POST['total_cost'] ?? 0);
            $source_fund = trim($_POST['source_of_fund'] ?? '');

            if (empty($title) || empty($location) || empty($duration_start) || empty($duration_end) ||
                empty($type) || empty($sdg) || empty($offices) || empty($programs) ||
                $total_cost <= 0) {
                $error = 'Please fill all required fields correctly.';
            } else {
                $_SESSION['pending_program'] = [
                    'title' => $title,
                    'location' => $location,
                    'duration_start' => $duration_start,
                    'duration_end' => $duration_end,
                    'type' => $type,
                    'sdg' => $sdg,
                    'offices' => $offices,
                    'programs' => $programs,
                    'partners' => $partners,
                    'beneficiaries_json' => $beneficiaries_json,
                    'total_cost' => $total_cost,
                    'source_fund' => $source_fund
                ];
                $show_confirmation = true;
            }
        } elseif ($mode === 'project') {
            $program_id = $_POST['program_id'] ?? null;
            $project_title = trim($_POST['project_title'] ?? '');
            $impl_month = trim($_POST['impl_month'] ?? '');
            $impl_year = trim($_POST['impl_year'] ?? '');

            if (!$program_id || empty($project_title) || empty($impl_month) || empty($impl_year)) {
                $error = 'Please fill all required fields.';
            } else {
                // Convert selected month/year to date string for validation
                $monthNum = date('m', strtotime($impl_month . ' 1'));
                $impl_date_str = "$impl_year-$monthNum-01";
                $impl_date = strtotime($impl_date_str);

                // Get parent program duration
                $progStmt = $pdo->prepare("SELECT duration_start, duration_end FROM program_entries WHERE id = ?");
                $progStmt->execute([$program_id]);
                $program = $progStmt->fetch(PDO::FETCH_ASSOC);

                if ($program) {
                    $start = strtotime($program['duration_start']);
                    $end = strtotime($program['duration_end']);

                    if ($impl_date < $start || $impl_date > $end) {
                        $error = "Implementation date must be within program duration ({$program['duration_start']} to {$program['duration_end']}).";
                    } else {
                        $_SESSION['pending_project'] = [
                            'program_id' => $program_id,
                            'project_title' => $project_title,
                            'date_of_implementation' => "$impl_month $impl_year"
                        ];
                        $show_confirmation = true;
                    }
                } else {
                    $error = 'Parent program not found.';
                }
            }
        } elseif ($mode === 'activity') {
            $project_id = $_POST['project_id'] ?? null;
            $activity_no = trim($_POST['activity_no'] ?? '');
            $activity_name = trim($_POST['activity_name'] ?? '');
            $impl_month = trim($_POST['impl_month'] ?? '');
            $impl_year = trim($_POST['impl_year'] ?? '');

            if (!$project_id || empty($activity_no) || empty($activity_name) || empty($impl_month) || empty($impl_year)) {
                $error = 'Please fill all required fields.';
            } else {
                // Similar validation can be added later for activity within project
                $_SESSION['pending_activity'] = [
                    'project_id' => $project_id,
                    'activity_no' => $activity_no,
                    'activity_name' => $activity_name,
                    'date_of_implementation' => "$impl_month $impl_year"
                ];
                $show_confirmation = true;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New <?= ucfirst($mode) ?></title>
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
            <?php if ($mode === 'program'): ?>
                <a href="list.php" class="nav-button">PPA</a>
            <?php elseif ($mode === 'project'): ?>
                <a href="view.php?mode=program&id=<?= htmlspecialchars($_GET['program_id'] ?? '') ?>" class="nav-button">Programs</a>
            <?php elseif ($mode === 'activity'): ?>
                <a href="view.php?mode=project&id=<?= htmlspecialchars($_GET['project_id'] ?? '') ?>" class="nav-button">Projects</a>
            <?php endif; ?>
            <a href="../logout.php" class="nav-button logout">Logout</a>
        </nav>
    </header>

    <main class="dashboard-content">
        <h1>Add New <?= ucfirst($mode) ?></h1>

        <?php if ($error): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <?php if ($show_confirmation): $d = $_SESSION['pending_' . $mode]; ?>
            <div class="confirmation-box">
                <h2>Confirm <?= ucfirst($mode) ?> Details</h2>

                <?php if ($mode === 'program'): ?>
                    <p><strong>Title:</strong> <?= htmlspecialchars($d['title']) ?></p>
                    <p><strong>Location:</strong> <?= htmlspecialchars($d['location']) ?></p>
                    <p><strong>Duration Start:</strong> <?= htmlspecialchars($d['duration_start']) ?></p>
                    <p><strong>Duration End:</strong> <?= htmlspecialchars($d['duration_end']) ?></p>
                    <p><strong>Type of Extension Service Agenda:</strong> <?= htmlspecialchars($d['type']) ?></p>
                    <p><strong>SDG Goals:</strong> <?= htmlspecialchars($d['sdg']) ?></p>
                    <p><strong>Offices Involved:</strong> <?= htmlspecialchars($d['offices']) ?></p>
                    <p><strong>Programs Involved:</strong> <?= htmlspecialchars($d['programs']) ?></p>
                    <p><strong>Partner Agencies:</strong> <?= htmlspecialchars($d['partners'] ?: 'N/A') ?></p>
                    <p><strong>Beneficiaries:</strong> <span id="confirm-beneficiaries"></span></p>
                    <p><strong>Total Cost:</strong> ₱<?= number_format($d['total_cost'], 2) ?></p>
                    <p><strong>Source of Fund:</strong> <?= htmlspecialchars($d['source_fund'] ?: 'N/A') ?></p>
                <?php elseif ($mode === 'project'): ?>
                    <p><strong>Project Title:</strong> <?= htmlspecialchars($d['project_title']) ?></p>
                    <p><strong>Date of Implementation:</strong> <?= htmlspecialchars($d['date_of_implementation']) ?></p>
                <?php elseif ($mode === 'activity'): ?>
                    <p><strong>Activity No.:</strong> <?= htmlspecialchars($d['activity_no']) ?></p>
                    <p><strong>Activity Name:</strong> <?= htmlspecialchars($d['activity_name']) ?></p>
                    <p><strong>Date of Implementation:</strong> <?= htmlspecialchars($d['date_of_implementation']) ?></p>
                <?php endif; ?>

                <form method="POST">
                    <button type="submit" name="confirm">Confirm & Save</button>
                    <a href="add.php?mode=<?= $mode ?>" class="cancel-link">Cancel</a>
                </form>
            </div>
        <?php else: ?>
            <form method="POST">
                <?php if ($mode === 'program'): ?>
                    <label for="title">Title *</label>
                    <input type="text" id="title" name="title" required>

                    <label for="location">Location *</label>
                    <input type="text" id="location" name="location" required>

                    <label>Duration *</label>
                    <div style="display: flex; gap: 16px; align-items: center;">
                        <input type="date" name="duration_start" required>
                        <span>to</span>
                        <input type="date" name="duration_end" required>
                    </div>

                    <label>Type of Extension Service Agenda * (select all that apply)</label>
                    <button type="button" onclick="openModal('type-modal')">Select Types</button>
                    <div id="selected-types" style="margin: 8px 0; min-height: 40px; border: 1px solid #ccc; padding: 8px; border-radius: 4px;"></div>
                    <input type="hidden" name="type_of_extension_service_agenda" id="type-hidden">

                    <label>Sustainable Development Goals * (select all that apply)</label>
                    <button type="button" onclick="openModal('sdg-modal')">Select SDGs</button>
                    <div id="selected-sdgs" style="margin: 8px 0; min-height: 40px; border: 1px solid #ccc; padding: 8px; border-radius: 4px;"></div>
                    <input type="hidden" name="sdg_goals" id="sdg-hidden">

                    <label>Beneficiaries * (add types and counts)</label>
                    <button type="button" onclick="openModal('beneficiaries-modal')">Manage Beneficiaries</button>
                    <div id="selected-beneficiaries" style="margin: 8px 0; min-height: 40px; border: 1px solid #ccc; padding: 8px; border-radius: 4px;"></div>
                    <input type="hidden" name="beneficiaries_json" id="beneficiaries-json" value="[]">

                    <label for="offices_involved">Offices/Colleges/Organizations Involved *</label>
                    <input type="text" id="offices_involved" name="offices_involved" required>

                    <label for="programs_involved">Programs Involved *</label>
                    <input type="text" id="programs_involved" name="programs_involved" required>

                    <label for="partner_agencies">Partner Agencies</label>
                    <input type="text" id="partner_agencies" name="partner_agencies">

                    <label for="total_cost">Total Cost</label>
                    <input type="number" id="total_cost" name="total_cost" step="0.01" min="0" required>

                    <label for="source_of_fund">Source of Fund</label>
                    <input type="text" id="source_of_fund" name="source_of_fund">

                <?php elseif ($mode === 'project'): ?>
                    <input type="hidden" name="program_id" value="<?= htmlspecialchars($_GET['program_id'] ?? '') ?>">

                    <label for="project_title">Project Title *</label>
                    <input type="text" id="project_title" name="project_title" required>

                    <label>Date of Implementation *</label>
                    <div style="display: flex; gap: 16px; align-items: center; flex-wrap: wrap;">
                        <select name="impl_month" required style="flex: 1; min-width: 160px;">
                            <option value="">Select Month</option>
                            <option value="January">January</option>
                            <option value="February">February</option>
                            <option value="March">March</option>
                            <option value="April">April</option>
                            <option value="May">May</option>
                            <option value="June">June</option>
                            <option value="July">July</option>
                            <option value="August">August</option>
                            <option value="September">September</option>
                            <option value="October">October</option>
                            <option value="November">November</option>
                            <option value="December">December</option>
                        </select>

                        <select name="impl_year" required style="flex: 1; min-width: 120px;">
                            <option value="">Select Year</option>
                            <?php
                            $currentYear = date('Y');
                            for ($y = $currentYear - 1; $y <= $currentYear + 5; $y++) {
                                $selected = ($y == $currentYear) ? 'selected' : '';
                                echo "<option value=\"$y\" $selected>$y</option>";
                            }
                            ?>
                        </select>
                    </div>

                <?php elseif ($mode === 'activity'): ?>
                    <input type="hidden" name="project_id" value="<?= htmlspecialchars($_GET['project_id'] ?? '') ?>">

                    <label for="activity_no">Activity No. *</label>
                    <input type="text" id="activity_no" name="activity_no" required>

                    <label for="activity_name">Activity Name *</label>
                    <input type="text" id="activity_name" name="activity_name" required>

                    <label>Date of Implementation *</label>
                    <div style="display: flex; gap: 16px; align-items: center; flex-wrap: wrap;">
                        <select name="impl_month" required style="flex: 1; min-width: 160px;">
                            <option value="">Select Month</option>
                            <option value="January">January</option>
                            <option value="February">February</option>
                            <option value="March">March</option>
                            <option value="April">April</option>
                            <option value="May">May</option>
                            <option value="June">June</option>
                            <option value="July">July</option>
                            <option value="August">August</option>
                            <option value="September">September</option>
                            <option value="October">October</option>
                            <option value="November">November</option>
                            <option value="December">December</option>
                        </select>

                        <select name="impl_year" required style="flex: 1; min-width: 120px;">
                            <option value="">Select Year</option>
                            <?php
                            $currentYear = date('Y');
                            for ($y = $currentYear - 1; $y <= $currentYear + 5; $y++) {
                                $selected = ($y == $currentYear) ? 'selected' : '';
                                echo "<option value=\"$y\" $selected>$y</option>";
                            }
                            ?>
                        </select>
                    </div>
                <?php endif; ?>

                <button type="submit">Review & Add</button>
            </form>
        <?php endif; ?>
    </main>

    <!-- Type Modal -->
    <div id="type-modal" class="modal-overlay">
        <div class="modal-box">
            <span class="close-modal" onclick="closeModal('type-modal')">×</span>
            <h2>Select Type of Extension Service Agenda</h2>
            <div style="max-height: 400px; overflow-y: auto; padding: 12px;">
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px; border-radius: 6px; transition: background 0.2s;">
                        BatStateU Inclusive Social Innovation for Regional Growth (BISIG) Program
                        <input type="checkbox" value="BatStateU Inclusive Social Innovation for Regional Growth (BISIG) Program">
                    </label>
                    <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px; border-radius: 6px; transition: background 0.2s;">
                        Livelihood and other Entrepreneurship related on Agri-Fisheries (LEAF)
                        <input type="checkbox" value="Livelihood and other Entrepreneurship related on Agri-Fisheries (LEAF)">
                    </label>
                    <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px; border-radius: 6px; transition: background 0.2s;">
                        Environment and Natural resources Conservation, Protection and Rehabilitation Program
                        <input type="checkbox" value="Environment and Natural resources Conservation, Protection and Rehabilitation Program">
                    </label>
                    <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px; border-radius: 6px; transition: background 0.2s;">
                        Smart Analytics and Engineering Innovation
                        <input type="checkbox" value="Smart Analytics and Engineering Innovation">
                    </label>
                    <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px; border-radius: 6px; transition: background 0.2s;">
                        Adopt-a Municipality/Barangay/School/Social Development Thru BIDANI Implementation
                        <input type="checkbox" value="Adopt-a Municipality/Barangay/School/Social Development Thru BIDANI Implementation">
                    </label>
                    <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px; border-radius: 6px; transition: background 0.2s;">
                        Community Outreach
                        <input type="checkbox" value="Community Outreach">
                    </label>
                    <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px; border-radius: 6px; transition: background 0.2s;">
                        Technical-Vocational Education and Training (TVET) Program
                        <input type="checkbox" value="Technical-Vocational Education and Training (TVET) Program">
                    </label>
                    <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px; border-radius: 6px; transition: background 0.2s;">
                        Technology Transfer and Adoption/Utilization Program
                        <input type="checkbox" value="Technology Transfer and Adoption/Utilization Program">
                    </label>
                    <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px; border-radius: 6px; transition: background 0.2s;">
                        Technical Assistance and Advisory Services Program
                        <input type="checkbox" value="Technical Assistance and Advisory Services Program">
                    </label>
                    <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px; border-radius: 6px; transition: background 0.2s;">
                        Parents' Empowerment through Social Development (PESODEV)
                        <input type="checkbox" value="Parents' Empowerment through Social Development (PESODEV)">
                    </label>
                    <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px; border-radius: 6px; transition: background 0.2s;">
                        Gender and Development
                        <input type="checkbox" value="Gender and Development">
                    </label>
                    <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px; border-radius: 6px; transition: background 0.2s;">
                        Disaster Risk Reduction and Management and Disaster Preparedness and Response/Climate Change Adaptation (DRMM and DPR/CCA)
                        <input type="checkbox" value="Disaster Risk Reduction and Management and Disaster Preparedness and Response/Climate Change Adaptation (DRMM and DPR/CCA)">
                    </label>
                </div>
            </div>
            <div class="modal-actions">
                <button onclick="saveModalSelections('type')">Save</button>
                <button onclick="closeModal('type-modal')">Cancel</button>
            </div>
        </div>
    </div>

    <!-- SDG Modal -->
    <div id="sdg-modal" class="modal-overlay">
        <div class="modal-box">
            <span class="close-modal" onclick="closeModal('sdg-modal')">×</span>
            <h2>Select Sustainable Development Goals</h2>
            <div style="max-height: 400px; overflow-y: auto; padding: 12px;">
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px; border-radius: 6px; transition: background 0.2s;">
                        No Poverty
                        <input type="checkbox" value="No Poverty">
                    </label>
                    <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px; border-radius: 6px; transition: background 0.2s;">
                        Zero Hunger
                        <input type="checkbox" value="Zero Hunger">
                    </label>
                    <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px; border-radius: 6px; transition: background 0.2s;">
                        Good Health and Well-Being
                        <input type="checkbox" value="Good Health and Well-Being">
                    </label>
                    <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px; border-radius: 6px; transition: background 0.2s;">
                        Quality Education
                        <input type="checkbox" value="Quality Education">
                    </label>
                    <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px; border-radius: 6px; transition: background 0.2s;">
                        Gender Equality
                        <input type="checkbox" value="Gender Equality">
                    </label>
                    <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px; border-radius: 6px; transition: background 0.2s;">
                        Clean Water and Sanitation
                        <input type="checkbox" value="Clean Water and Sanitation">
                    </label>
                    <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px; border-radius: 6px; transition: background 0.2s;">
                        Affordable and Clean Energy
                        <input type="checkbox" value="Affordable and Clean Energy">
                    </label>
                    <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px; border-radius: 6px; transition: background 0.2s;">
                        Decent Work and Economic Growth
                        <input type="checkbox" value="Decent Work and Economic Growth">
                    </label>
                    <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px; border-radius: 6px; transition: background 0.2s;">
                        Industry, Innovation, and Infrastructure
                        <input type="checkbox" value="Industry, Innovation, and Infrastructure">
                    </label>
                    <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px; border-radius: 6px; transition: background 0.2s;">
                        Reduced Inequalities
                        <input type="checkbox" value="Reduced Inequalities">
                    </label>
                    <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px; border-radius: 6px; transition: background 0.2s;">
                        Sustainable Cities and Communities
                        <input type="checkbox" value="Sustainable Cities and Communities">
                    </label>
                    <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px; border-radius: 6px; transition: background 0.2s;">
                        Responsible Consumption and Production
                        <input type="checkbox" value="Responsible Consumption and Production">
                    </label>
                    <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px; border-radius: 6px; transition: background 0.2s;">
                        Climate Action
                        <input type="checkbox" value="Climate Action">
                    </label>
                    <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px; border-radius: 6px; transition: background 0.2s;">
                        Life Below Water
                        <input type="checkbox" value="Life Below Water">
                    </label>
                    <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px; border-radius: 6px; transition: background 0.2s;">
                        Life on Land
                        <input type="checkbox" value="Life on Land">
                    </label>
                    <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px; border-radius: 6px; transition: background 0.2s;">
                        Peace, Justice and Strong Institutions
                        <input type="checkbox" value="Peace, Justice and Strong Institutions">
                    </label>
                    <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px; border-radius: 6px; transition: background 0.2s;">
                        Partnerships for the Goals
                        <input type="checkbox" value="Partnerships for the Goals">
                    </label>
                </div>
            </div>
            <div class="modal-actions">
                <button onclick="saveModalSelections('sdg')">Save</button>
                <button onclick="closeModal('sdg-modal')">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Beneficiaries Modal -->
    <div id="beneficiaries-modal" class="modal-overlay">
        <div class="modal-box" style="max-width: 800px;">
            <span class="close-modal" onclick="closeModal('beneficiaries-modal')">×</span>
            <h2>Manage Beneficiaries</h2>
            <div id="beneficiary-rows" style="margin-bottom: 20px;">
                <!-- Rows added dynamically -->
            </div>
            <button type="button" onclick="addBeneficiaryRow()" 
                    style="margin-bottom: 16px; padding: 12px 20px; background: #c8102e; color: white; border: none; border-radius: 6px; cursor: pointer;">
                + Add Beneficiary Type
            </button>
            <div class="modal-actions" style="margin-top: 20px; display: flex; gap: 12px; justify-content: flex-end;">
                <button onclick="saveBeneficiaries()" 
                        style="padding: 12px 24px; background: #c8102e; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 500;">
                    Save
                </button>
                <button onclick="closeModal('beneficiaries-modal')" 
                        style="padding: 12px 24px; background: #c8102e; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 500;">
                    Cancel
                </button>
            </div>
        </div>
    </div>

    <script>
function openModal(modalId) {
    document.getElementById(modalId).classList.add('active');
    document.body.classList.add('modal-open');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
    document.body.classList.remove('modal-open');
}

function saveModalSelections(type) {
    const modal = document.getElementById(type + '-modal');
    const checkboxes = modal.querySelectorAll('input[type="checkbox"]:checked');
    const values = Array.from(checkboxes).map(cb => cb.value);
    const hidden = document.getElementById(type + '-hidden');
    const display = document.getElementById('selected-' + type + 's');

    hidden.value = values.join(', ');
    display.textContent = values.length > 0 ? values.join(', ') : 'None selected';
    closeModal(type + '-modal');
}

// Beneficiaries dynamic rows
function addBeneficiaryRow(type = '', male = 0, female = 0) {
    const container = document.getElementById('beneficiary-rows');
    const row = document.createElement('div');
    row.className = 'beneficiary-row';
    row.style.display = 'flex';
    row.style.alignItems = 'center';
    row.style.gap = '12px';
    row.style.marginBottom = '16px';
    row.style.flexWrap = 'wrap';

    row.innerHTML = `
        <input type="text" 
               placeholder="e.g., Farmers, Students, PWDs, Senior Citizens" 
               value="${type}" 
               class="beneficiary-type"
               required
               style="flex: 2; min-width: 220px;">

        <input type="number" 
               placeholder="Male" 
               value="${male}" 
               min="0" 
               class="beneficiary-male"
               required
               style="flex: 1; max-width: 100px;">

        <input type="number" 
               placeholder="Female" 
               value="${female}" 
               min="0" 
               class="beneficiary-female"
               required
               style="flex: 1; max-width: 100px;">

        <button type="button" 
                onclick="this.closest('.beneficiary-row').remove();"
                class="remove-btn">
            ×
        </button>
    `;

    container.appendChild(row);
}

function saveBeneficiaries() {
    const rows = document.querySelectorAll('#beneficiary-rows .beneficiary-row');
    const data = [];

    rows.forEach(row => {
        const inputs = row.querySelectorAll('input');
        const type = inputs[0].value.trim();
        const male = parseInt(inputs[1].value) || 0;
        const female = parseInt(inputs[2].value) || 0;

        if (type) {
            data.push({ type, male, female });
        }
    });

    const json = JSON.stringify(data);
    document.getElementById('beneficiaries-json').value = json;

    let summary = '';
    let total = 0;
    data.forEach(b => {
        summary += `${b.type}: ${b.male} male, ${b.female} female | `;
        total += b.male + b.female;
    });
    summary += `Total: ${total}`;
    document.getElementById('selected-beneficiaries').textContent = summary || 'None added';

    closeModal('beneficiaries-modal');
}

// Load existing beneficiaries in edit mode
window.addEventListener('load', () => {
    const json = document.getElementById('beneficiaries-json')?.value || '[]';
    const data = JSON.parse(json);
    data.forEach(b => addBeneficiaryRow(b.type, b.male, b.female));
    saveBeneficiaries(); // update summary
});
</script>
</body>
</html>