<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php");
    exit;
}

require_once '../config/db.php';

$error = '';
$show_confirmation = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['confirm']) && isset($_SESSION['pending_program'])) {
        $d = $_SESSION['pending_program'];

        try {
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

            unset($_SESSION['pending_program']);
            header("Location: list.php?success=added");
            exit;
        } catch (PDOException $e) {
            $error = 'Failed to save: ' . $e->getMessage();
        }
    } else {
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
            empty($partners) || $total_cost <= 0 || empty($source_fund)) {
            $error = 'Please fill all required fields.';
        } else {
            $_SESSION['pending_program'] = [
                'title' => $title, 'location' => $location, 'duration_start' => $duration_start,
                'duration_end' => $duration_end, 'type' => $type, 'sdg' => $sdg,
                'offices' => $offices, 'programs' => $programs, 'partners' => $partners,
                'beneficiaries_json' => $beneficiaries_json, 'total_cost' => $total_cost,
                'source_fund' => $source_fund
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
    <title>Add New Program</title>
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
        <h1>Add New Program</h1>

        <?php if ($error): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <?php if ($show_confirmation): $d = $_SESSION['pending_program']; ?>
            <div class="confirmation-box">
                <h2>Confirm Program Details</h2>
                <p><strong>Title:</strong> <?= htmlspecialchars($d['title']) ?></p>
                <p><strong>Location:</strong> <?= htmlspecialchars($d['location']) ?></p>
                <p><strong>Duration Start:</strong> <?= htmlspecialchars($d['duration_start']) ?></p>
                <p><strong>Duration End:</strong> <?= htmlspecialchars($d['duration_end']) ?></p>
                <p><strong>Type of Extension Service Agenda:</strong> <?= htmlspecialchars($d['type']) ?></p>
                <p><strong>SDG Goals:</strong> <?= htmlspecialchars($d['sdg']) ?></p>
                <p><strong>Offices Involved:</strong> <?= htmlspecialchars($d['offices']) ?></p>
                <p><strong>Programs Involved:</strong> <?= htmlspecialchars($d['programs']) ?></p>
                <p><strong>Partner Agencies:</strong> <?= htmlspecialchars($d['partners']) ?></p>
                <p><strong>Beneficiaries:</strong> <span id="confirm-beneficiaries"></span></p>
                <p><strong>Total Cost:</strong> ₱<?= number_format($d['total_cost'], 2) ?></p>
                <p><strong>Source of Fund:</strong> <?= htmlspecialchars($d['source_fund']) ?></p>

                <form method="POST">
                    <button type="submit" name="confirm">Confirm & Save</button>
                    <a href="add.php" class="cancel-link">Cancel</a>
                </form>
            </div>
        <?php else: ?>
            <form method="POST">
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

                <label for="partner_agencies">Partner Agencies *</label>
                <input type="text" id="partner_agencies" name="partner_agencies" required>

                <label for="total_cost">Total Cost *</label>
                <input type="number" id="total_cost" name="total_cost" step="0.01" min="0" required>

                <label for="source_of_fund">Source of Fund *</label>
                <select id="source_of_fund" name="source_of_fund" required>
                    <option value="">Select Source</option>
                    <option value="MDS">MDS</option>
                    <option value="STF">STF</option>
                    <option value="Others">Others</option>
                </select>

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

    <div id="beneficiaries-modal" class="modal-overlay">
        <div class="modal-box" style="max-width: 800px;">
            <span class="close-modal" onclick="closeModal('beneficiaries-modal')">×</span>
            <h2>Manage Beneficiaries</h2>
            <div id="beneficiary-rows" style="margin-bottom: 20px;">
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

window.addEventListener('load', () => {
    const json = document.getElementById('beneficiaries-json')?.value || '[]';
    const data = JSON.parse(json);
    data.forEach(b => addBeneficiaryRow(b.type, b.male, b.female));
    saveBeneficiaries();
});
</script>
</body>
</html>