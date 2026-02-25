<?php
// edit.php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header("Location: ../login.php");
    exit;
}

require_once '../config/db.php';

$mode = $_GET['mode'] ?? '';
$id = $_GET['id'] ?? null;

$valid_modes = ['program', 'project', 'activity'];

if (!in_array($mode, $valid_modes) || !$id || !is_numeric($id)) {
    header("Location: list.php");
    exit;
}

$error = '';
$entry = null;

try {
    if ($mode === 'program') {
        $stmt = $pdo->prepare("
            SELECT title, location, duration_start, duration_end,
                   type_of_extension_service_agenda, sdg_goals, offices_involved,
                   programs_involved, partner_agencies, beneficiaries_json,
                   total_cost, source_of_fund
            FROM program_entries
            WHERE id = ? AND status = 'active'
        ");
        $stmt->execute([$id]);
        $entry = $stmt->fetch(PDO::FETCH_ASSOC);
    } elseif ($mode === 'project') {
        $stmt = $pdo->prepare("
            SELECT program_id, project_title, activities, month_of_implementation
            FROM project_entries
            WHERE id = ? AND status = 'active'
        ");
        $stmt->execute([$id]);
        $entry = $stmt->fetch(PDO::FETCH_ASSOC);
    } elseif ($mode === 'activity') {
        $stmt = $pdo->prepare("
            SELECT project_id, activity_no, activity_name, month_of_implementation
            FROM activity_entries
            WHERE id = ? AND status = 'active'
        ");
        $stmt->execute([$id]);
        $entry = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$entry) {
        $error = 'Entry not found or already deleted.';
    }
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
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
            $stmt = $pdo->prepare("
                UPDATE program_entries 
                SET title = ?, location = ?, duration_start = ?, duration_end = ?,
                    type_of_extension_service_agenda = ?, sdg_goals = ?, offices_involved = ?,
                    programs_involved = ?, partner_agencies = ?, beneficiaries_json = ?, total_cost = ?,
                    source_of_fund = ?, updated_at = NOW()
                WHERE id = ? AND status = 'active'
            ");
            $stmt->execute([
                $title, $location, $duration_start, $duration_end,
                $type, $sdg, $offices, $programs, $partners,
                $beneficiaries_json, $total_cost, $source_fund, $id
            ]);
            header("Location: list.php?success=updated");
            exit;
        }
    } elseif ($mode === 'project') {
        $project_title = trim($_POST['project_title'] ?? '');
        $activities = trim($_POST['activities'] ?? '');
        $month = trim($_POST['month_of_implementation'] ?? '');

        if (empty($project_title) || empty($activities) || empty($month)) {
            $error = 'Please fill all required fields.';
        } else {
            $stmt = $pdo->prepare("
                UPDATE project_entries 
                SET project_title = ?, activities = ?, month_of_implementation = ?, updated_at = NOW()
                WHERE id = ? AND status = 'active'
            ");
            $stmt->execute([$project_title, $activities, $month, $id]);
            header("Location: list.php?success=updated");
            exit;
        }
    } elseif ($mode === 'activity') {
        $activity_no = trim($_POST['activity_no'] ?? '');
        $activity_name = trim($_POST['activity_name'] ?? '');
        $month = trim($_POST['month_of_implementation'] ?? '');

        if (empty($activity_no) || empty($activity_name) || empty($month)) {
            $error = 'Please fill all required fields.';
        } else {
            $stmt = $pdo->prepare("
                UPDATE activity_entries 
                SET activity_no = ?, activity_name = ?, month_of_implementation = ?, updated_at = NOW()
                WHERE id = ? AND status = 'active'
            ");
            $stmt->execute([$activity_no, $activity_name, $month, $id]);
            header("Location: list.php?success=updated");
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit <?= ucfirst($mode) ?></title>
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
            <a href="list.php" class="nav-button">PPA</a>
            <a href="../logout.php" class="nav-button logout">Logout</a>
        </nav>
    </header>

    <main class="dashboard-content">
        <h1>Edit <?= ucfirst($mode) ?></h1>

        <?php if ($error): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <?php if ($entry): ?>
            <form method="POST">
                <?php if ($mode === 'program'): ?>
                    <label for="title">Title *</label>
                    <input type="text" id="title" name="title" value="<?= htmlspecialchars($entry['title']) ?>" required>

                    <label for="location">Location *</label>
                    <input type="text" id="location" name="location" value="<?= htmlspecialchars($entry['location']) ?>" required>

                    <label>Duration *</label>
                    <div style="display: flex; gap: 16px; align-items: center;">
                        <input type="date" name="duration_start" value="<?= htmlspecialchars($entry['duration_start']) ?>" required>
                        <span>to</span>
                        <input type="date" name="duration_end" value="<?= htmlspecialchars($entry['duration_end']) ?>" required>
                    </div>

                    <label>Type of Extension Service Agenda * (select all that apply)</label>
                    <button type="button" onclick="openModal('type-modal')">Select Types</button>
                    <div id="selected-types" style="margin: 8px 0; min-height: 40px; border: 1px solid #ccc; padding: 8px; border-radius: 4px;">
                        <?= htmlspecialchars($entry['type_of_extension_service_agenda']) ?>
                    </div>
                    <input type="hidden" name="type_of_extension_service_agenda" id="type-hidden" value="<?= htmlspecialchars($entry['type_of_extension_service_agenda']) ?>">

                    <label>Sustainable Development Goals * (select all that apply)</label>
                    <button type="button" onclick="openModal('sdg-modal')">Select SDGs</button>
                    <div id="selected-sdgs" style="margin: 8px 0; min-height: 40px; border: 1px solid #ccc; padding: 8px; border-radius: 4px;">
                        <?= htmlspecialchars($entry['sdg_goals']) ?>
                    </div>
                    <input type="hidden" name="sdg_goals" id="sdg-hidden" value="<?= htmlspecialchars($entry['sdg_goals']) ?>">

                    <label>Beneficiaries * (add types and counts)</label>
                    <button type="button" onclick="openModal('beneficiaries-modal')">Manage Beneficiaries</button>
                    <div id="selected-beneficiaries" style="margin: 8px 0; min-height: 40px; border: 1px solid #ccc; padding: 8px; border-radius: 4px;"></div>
                    <input type="hidden" name="beneficiaries_json" id="beneficiaries-json" value="<?= htmlspecialchars($entry['beneficiaries_json'] ?? '[]') ?>">

                    <label for="offices_involved">Offices/Colleges/Organizations Involved *</label>
                    <input type="text" id="offices_involved" name="offices_involved" value="<?= htmlspecialchars($entry['offices_involved']) ?>" required>

                    <label for="programs_involved">Programs Involved *</label>
                    <input type="text" id="programs_involved" name="programs_involved" value="<?= htmlspecialchars($entry['programs_involved']) ?>" required>

                    <label for="partner_agencies">Partner Agencies</label>
                    <input type="text" id="partner_agencies" name="partner_agencies" value="<?= htmlspecialchars($entry['partner_agencies']) ?>">

                    <label for="total_cost">Total Cost</label>
                    <input type="number" id="total_cost" name="total_cost" step="0.01" min="0" value="<?= $entry['total_cost'] ?>" required>

                    <label for="source_of_fund">Source of Fund</label>
                    <input type="text" id="source_of_fund" name="source_of_fund" value="<?= htmlspecialchars($entry['source_of_fund']) ?>">

                <?php elseif ($mode === 'project'): ?>
                    <label for="project_title">Project Title *</label>
                    <input type="text" id="project_title" name="project_title" value="<?= htmlspecialchars($entry['project_title']) ?>" required>

                    <label for="activities">Activities *</label>
                    <input type="text" id="activities" name="activities" value="<?= htmlspecialchars($entry['activities']) ?>" required>

                    <label for="month_of_implementation">Month of Implementation *</label>
                    <input type="text" id="month_of_implementation" name="month_of_implementation" value="<?= htmlspecialchars($entry['month_of_implementation']) ?>" required>

                <?php elseif ($mode === 'activity'): ?>
                    <label for="activity_no">Activity No. *</label>
                    <input type="text" id="activity_no" name="activity_no" value="<?= htmlspecialchars($entry['activity_no']) ?>" required>

                    <label for="activity_name">Activity Name *</label>
                    <input type="text" id="activity_name" name="activity_name" value="<?= htmlspecialchars($entry['activity_name']) ?>" required>

                    <label for="month_of_implementation">Month of Implementation *</label>
                    <input type="text" id="month_of_implementation" name="month_of_implementation" value="<?= htmlspecialchars($entry['month_of_implementation']) ?>" required>
                <?php endif; ?>

                <div class="modal-actions" style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 24px;">
    <button type="submit" 
            style="padding: 12px 24px; background: #c8102e; color: white; border: none; border-radius: 6px; 
                   cursor: pointer; font-size: 16px; font-weight: 500; height: 48px; line-height: 1; min-width: 140px;">
        Save Changes
    </button>
</div>
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
let beneficiaryIndex = 0;

function addBeneficiaryRow(type = '', male = 0, female = 0) {
    const container = document.getElementById('beneficiary-rows');
    const row = document.createElement('div');
    row.className = 'beneficiary-row';
    row.style.display = 'flex';
    row.style.alignItems = 'center';
    row.style.gap = '12px';
    row.style.marginBottom = '16px';
    row.style.flexWrap = 'wrap'; // good for small screens

    row.innerHTML = `
        <div style="flex: 2; min-width: 220px; display: flex; flex-direction: column; gap: 4px;">
            <label style="font-size: 14px; font-weight: 500; color: #444;">Beneficiary Type</label>
            <input type="text" 
                   placeholder="e.g., Farmers, Students, PWDs, Senior Citizens" 
                   value="${type}" 
                   required
                   style="width: 100%; font-size: 16px; padding: 10px 14px; height: 44px; 
                          border: 1px solid #ccc; border-radius: 6px; box-sizing: border-box;">
        </div>

        <div style="flex: 1; min-width: 100px; display: flex; flex-direction: column; gap: 4px;">
            <label style="font-size: 14px; font-weight: 500; color: #444;">Male</label>
            <input type="number" 
                   placeholder="0" 
                   value="${male}" 
                   min="0" 
                   required
                   style="width: 100%; font-size: 16px; padding: 10px; height: 44px; 
                          text-align: center; border: 1px solid #ccc; border-radius: 6px;">
        </div>

        <div style="flex: 1; min-width: 100px; display: flex; flex-direction: column; gap: 4px;">
            <label style="font-size: 14px; font-weight: 500; color: #444;">Female</label>
            <input type="number" 
                   placeholder="0" 
                   value="${female}" 
                   min="0" 
                   required
                   style="width: 100%; font-size: 16px; padding: 10px; height: 44px; 
                          text-align: center; border: 1px solid #ccc; border-radius: 6px;">
        </div>

        <button type="button" 
                onclick="this.closest('.beneficiary-row').remove();"
                style="padding: 10px 16px; background: #c8102e; color: white; 
                       border: none; border-radius: 6px; cursor: pointer; font-size: 14px; 
                       white-space: nowrap; align-self: flex-end; margin-top: 20px;">
            Remove
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