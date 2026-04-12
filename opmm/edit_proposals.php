<?php
// edit_proposals.php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php");
    exit;
}

require_once '../config/db.php';

$id = $_GET['id'] ?? null;
if (!$id || !is_numeric($id)) {
    header("Location: list_proposals.php");
    exit;
}

// Fetch current proposal data
$stmt = $pdo->prepare("
    SELECT title, description, start_date, end_date, status,
           type_of_extension_service_agenda, sdg_goals,
           offices_involved, programs_involved, beneficiaries_json,
           partner_agencies, source_of_fund, total_cost
    FROM research_proposals WHERE id = ?
");
$stmt->execute([$id]);
$entry = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$entry) {
    header("Location: list_proposals.php");
    exit;
}

// Hardcoded options
$fullTypeOptions = [
    "BatStateU Inclusive Social Innovation for Regional Growth (BISIG) Program",
    "Livelihood and other Entrepreneurship related on Agri-Fisheries (LEAF)",
    "Environment and Natural resources Conservation, Protection and Rehabilitation Program",
    "Smart Analytics and Engineering Innovation",
    "Adopt-a Municipality/Barangay/School/Social Development Thru BIDANI Implementation",
    "Community Outreach",
    "Technical-Vocational Education and Training (TVET) Program",
    "Technology Transfer and Adoption/Utilization Program",
    "Technical Assistance and Advisory Services Program",
    "Parents' Empowerment through Social Development (PESODEV)",
    "Gender and Development",
    "Disaster Risk Reduction and Management and Disaster Preparedness and Response/Climate Change Adaptation (DRMM and DPR/CCA)"
];

$fullSdgOptions = [
    "No Poverty", "Zero Hunger", "Good Health and Well-Being", "Quality Education",
    "Gender Equality", "Clean Water and Sanitation", "Affordable and Clean Energy",
    "Decent Work and Economic Growth", "Industry, Innovation and Infrastructure",
    "Reduced Inequalities", "Sustainable Cities and Communities",
    "Responsible Consumption and Production", "Climate Action", "Life Below Water",
    "Life on Land", "Peace, Justice and Strong Institutions", "Partnerships for the Goals"
];

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title          = trim($_POST['title'] ?? '');
    $description    = trim($_POST['description'] ?? '');
    $start_date     = $_POST['start_date'] ?? '';
    $end_date       = $_POST['end_date'] ?? '';
    $type           = trim($_POST['type_of_extension_service_agenda'] ?? '');
    $sdg            = trim($_POST['sdg_goals'] ?? '');
    $offices        = trim($_POST['offices_involved'] ?? '');
    $programs       = trim($_POST['programs_involved'] ?? '');
    $beneficiaries  = trim($_POST['beneficiaries_json'] ?? '[]');
    $partner_agencies = trim($_POST['partner_agencies'] ?? '');
    $source_of_fund = trim($_POST['source_of_fund'] ?? '');
    $total_cost     = (float)($_POST['total_cost'] ?? 0);

    $benefData = json_decode($beneficiaries, true) ?? [];
    $totalBenef = 0;
    foreach ($benefData as $b) {
        $totalBenef += ($b['male'] ?? 0) + ($b['female'] ?? 0);
    }

    if (empty($title) || empty($start_date) || empty($end_date) ||
        empty($type) || empty($sdg) || empty($offices) || empty($programs) ||
        $beneficiaries === '[]' || $totalBenef === 0 ||
        empty($partner_agencies) || empty($source_of_fund) || $total_cost <= 0) {
        $error = 'Please fill all required fields.';
    } elseif (strtotime($end_date) < strtotime($start_date)) {
        $error = 'End date cannot be before start date.';
    } else {
        try {
            $stmt = $pdo->prepare("
                UPDATE research_proposals 
                SET title = ?, description = ?, start_date = ?, end_date = ?,
                    type_of_extension_service_agenda = ?, sdg_goals = ?,
                    offices_involved = ?, programs_involved = ?, beneficiaries_json = ?,
                    partner_agencies = ?, source_of_fund = ?, total_cost = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $title, $description, $start_date, $end_date,
                $type, $sdg, $offices, $programs, $beneficiaries,
                $partner_agencies, $source_of_fund, $total_cost, $id
            ]);

            header("Location: view_proposals.php?id=$id&success=updated");
            exit;
        } catch (PDOException $e) {
            $error = 'Update failed: ' . $e->getMessage();
        }
    }
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
    <title>Edit Research Proposal</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        #description {
            width: 100%;
            min-height: 78px;
            max-height: 150px;
            resize: vertical;
            overflow-y: auto;
            padding: 12px 14px;
            font-family: inherit;
            font-size: 1rem;
            line-height: 1.5;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            box-sizing: border-box;
        }

        #description:focus {
            border-color: #c8102e;
            outline: none;
        }

        .save-btn {
            padding: 14px 32px;
            font-size: 1.1rem;
            font-weight: 600;
        }
    </style>
</head>
<body>

    <main class="dashboard-content add-program-page">
        <h1>Edit Research Proposal</h1>

        <?php if ($error): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <form method="POST" class="program-form" id="edit-proposal-form">
            <div class="form-group">
                <label for="title">Proposal Title *</label>
                <input type="text" id="title" name="title" 
                       value="<?= htmlspecialchars($entry['title'] ?? '') ?>" required>
            </div>

            <div class="form-group full-span">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="3" 
                          placeholder="Enter detailed description..."><?= htmlspecialchars($entry['description'] ?? '') ?></textarea>
            </div>

            <div class="form-group full-span">
                <label>Duration *</label>
                <div class="date-group">
                    <input type="date" name="start_date" 
                           value="<?= htmlspecialchars($entry['start_date'] ?? '') ?>" required>
                    <span>to</span>
                    <input type="date" name="end_date" 
                           value="<?= htmlspecialchars($entry['end_date'] ?? '') ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label>Type of Extension Service Agenda *</label>
                <button type="button" onclick="openModal('type-modal')">Select Types</button>
                <div id="selected-types" class="compact-preview"><?= htmlspecialchars($entry['type_of_extension_service_agenda'] ?? 'None') ?></div>
                <input type="hidden" name="type_of_extension_service_agenda" id="type-hidden" 
                       value="<?= htmlspecialchars($entry['type_of_extension_service_agenda'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label>Sustainable Development Goals *</label>
                <button type="button" onclick="openModal('sdg-modal')">Select SDGs</button>
                <div id="selected-sdgs" class="compact-preview"><?= htmlspecialchars($entry['sdg_goals'] ?? 'None') ?></div>
                <input type="hidden" name="sdg_goals" id="sdg-hidden" 
                       value="<?= htmlspecialchars($entry['sdg_goals'] ?? '') ?>">
            </div>

            <div class="form-group full-span">
                <label>Beneficiaries *</label>
                <button type="button" onclick="openModal('beneficiaries-modal')">Manage Beneficiaries</button>
                <div id="selected-beneficiaries" class="compact-preview">None</div>
                <input type="hidden" name="beneficiaries_json" id="beneficiaries-json" 
                       value="<?= htmlspecialchars($entry['beneficiaries_json'] ?? '[]') ?>">
            </div>

            <div class="form-group">
                <label for="offices_involved">Offices Involved *</label>
                <input type="text" id="offices_involved" name="offices_involved" 
                       value="<?= htmlspecialchars($entry['offices_involved'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label for="programs_involved">Programs Involved *</label>
                <input type="text" id="programs_involved" name="programs_involved" 
                       value="<?= htmlspecialchars($entry['programs_involved'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label for="partner_agencies">Partner Agencies *</label>
                <input type="text" id="partner_agencies" name="partner_agencies" 
                       value="<?= htmlspecialchars($entry['partner_agencies'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label for="source_of_fund">Source of Fund *</label>
                <select id="source_of_fund" name="source_of_fund" required>
                    <option value="">Select Source</option>
                    <option value="MDS" <?= ($entry['source_of_fund'] ?? '') === 'MDS' ? 'selected' : '' ?>>MDS</option>
                    <option value="STF" <?= ($entry['source_of_fund'] ?? '') === 'STF' ? 'selected' : '' ?>>STF</option>
                    <option value="Others" <?= ($entry['source_of_fund'] ?? '') === 'Others' ? 'selected' : '' ?>>Others</option>
                </select>
            </div>

            <div class="form-group">
                <label for="total_cost">Total Cost *</label>
                <input type="number" id="total_cost" name="total_cost" step="0.01" min="0" 
                       value="<?= htmlspecialchars($entry['total_cost'] ?? '') ?>" required>
            </div>

            <div class="full-span" style="text-align: center; margin-top: 32px;">
                <button type="submit" id="save-btn" class="save-btn" disabled>Save Changes</button>
            </div>
        </form>
    </main>

    <!-- Type Modal -->
    <div id="type-modal" class="modal-overlay">
        <div class="modal-box">
            <span class="close-modal" onclick="closeModal('type-modal')">×</span>
            <h2>Select Type of Extension Service Agenda</h2>
            <div style="max-height: 400px; overflow-y: auto; padding: 12px;">
                <?php foreach ($fullTypeOptions as $opt): ?>
                    <label class="modal-checkbox-label">
                        <span><?= htmlspecialchars($opt) ?></span>
                        <input type="checkbox" value="<?= htmlspecialchars($opt) ?>">
                    </label>
                <?php endforeach; ?>
            </div>
            <div class="modal-actions">
                <button onclick="saveModalSelections('type')" style="background:#c8102e;color:white;">Save</button>
                <button onclick="closeModal('type-modal')" style="background:#6b7280;color:white;">Cancel</button>
            </div>
        </div>
    </div>

    <!-- SDG Modal -->
    <div id="sdg-modal" class="modal-overlay">
        <div class="modal-box">
            <span class="close-modal" onclick="closeModal('sdg-modal')">×</span>
            <h2>Select Sustainable Development Goals</h2>
            <div style="max-height: 400px; overflow-y: auto; padding: 12px;">
                <?php foreach ($fullSdgOptions as $opt): ?>
                    <label class="modal-checkbox-label">
                        <span><?= htmlspecialchars($opt) ?></span>
                        <input type="checkbox" value="<?= htmlspecialchars($opt) ?>">
                    </label>
                <?php endforeach; ?>
            </div>
            <div class="modal-actions">
                <button onclick="saveModalSelections('sdg')" style="background:#c8102e;color:white;">Save</button>
                <button onclick="closeModal('sdg-modal')" style="background:#6b7280;color:white;">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Beneficiaries Modal -->
    <div id="beneficiaries-modal" class="modal-overlay">
        <div class="modal-box" style="max-width: 800px;">
            <span class="close-modal" onclick="closeModal('beneficiaries-modal')">×</span>
            <h2>Manage Beneficiaries</h2>
            <div id="beneficiary-rows" style="margin-bottom: 16px;"></div>
            <button type="button" onclick="addBeneficiaryRow()" 
                    style="margin-bottom: 12px; padding: 10px 16px; background: #c8102e; color: white; border: none; border-radius: 4px; cursor: pointer;">
                + Add Beneficiary Type
            </button>
            <div class="modal-actions" style="margin-top: 12px; display: flex; gap: 12px; justify-content: flex-end;">
                <button onclick="saveBeneficiaries()" style="background:#c8102e;color:white;">Save</button>
                <button onclick="closeModal('beneficiaries-modal')" style="background:#6b7280;color:white;">Cancel</button>
            </div>
        </div>
    </div>

    <script>
    function openModal(modalId) {
        document.getElementById(modalId).classList.add('active');
        document.body.classList.add('modal-open');

        if (modalId === 'type-modal') restoreCheckedState('type');
        if (modalId === 'sdg-modal') restoreCheckedState('sdg');
        if (modalId === 'beneficiaries-modal') loadBeneficiaries();
    }

    function closeModal(modalId) {
        document.getElementById(modalId).classList.remove('active');
        document.body.classList.remove('modal-open');
    }

    function saveModalSelections(type) {
        const modal = document.getElementById(type + '-modal');
        const checkboxes = modal.querySelectorAll('input[type="checkbox"]:checked');
        const values = Array.from(checkboxes).map(cb => cb.value.trim());

        const hidden = document.getElementById(type + '-hidden');
        let displayId = type === 'type' ? 'selected-types' : 'selected-sdgs';

        const display = document.getElementById(displayId);

        if (hidden) hidden.value = values.join(', ');
        if (display) display.textContent = values.length ? values.join(', ') : 'None';

        closeModal(type + '-modal');
        checkFormChanges();
    }

    function restoreCheckedState(type) {
        const modal = document.getElementById(type + '-modal');
        const hidden = document.getElementById(type + '-hidden');
        if (!hidden) return;

        const selected = hidden.value.split(',').map(v => v.trim().toLowerCase());

        const checkboxes = modal.querySelectorAll('input[type="checkbox"]');
        checkboxes.forEach(cb => {
            cb.checked = selected.includes(cb.value.trim().toLowerCase());
        });
    }

    function addBeneficiaryRow(type = '', male = 0, female = 0) {
        const container = document.getElementById('beneficiary-rows');
        const row = document.createElement('div');
        row.className = 'beneficiary-row';
        row.style.display = 'flex';
        row.style.alignItems = 'center';
        row.style.gap = '10px';
        row.style.marginBottom = '10px';
        row.style.flexWrap = 'wrap';

        row.innerHTML = `
            <input type="text" placeholder="e.g., Farmers, Students, PWDs" 
                   value="${type}" class="beneficiary-type" required style="flex: 2; min-width: 180px;">
            <input type="number" placeholder="Male" value="${male}" min="0" 
                   class="beneficiary-male" required style="flex: 1; max-width: 80px;">
            <input type="number" placeholder="Female" value="${female}" min="0" 
                   class="beneficiary-female" required style="flex: 1; max-width: 80px;">
            <button type="button" onclick="this.closest('.beneficiary-row').remove(); saveBeneficiaries(false);" 
                    style="padding: 6px 10px; background: #c8102e; color: white; border: none; border-radius: 4px; cursor: pointer;">
                ×
            </button>
        `;
        container.appendChild(row);
    }

    function saveBeneficiaries(closeAfter = true) {
        const rows = document.querySelectorAll('#beneficiary-rows .beneficiary-row');
        const data = [];

        rows.forEach(row => {
            const inputs = row.querySelectorAll('input');
            const type = inputs[0] ? inputs[0].value.trim() : '';
            const male = inputs[1] ? parseInt(inputs[1].value) || 0 : 0;
            const female = inputs[2] ? parseInt(inputs[2].value) || 0 : 0;
            if (type) data.push({ type, male, female });
        });

        document.getElementById('beneficiaries-json').value = JSON.stringify(data);

        let summary = '';
        let total = 0;
        data.forEach(b => {
            summary += `${b.type}: ${b.male} M, ${b.female} F | `;
            total += b.male + b.female;
        });
        summary = summary.trim().replace(/ \| $/, '');
        if (total > 0) summary += ` | Total: ${total}`;
        document.getElementById('selected-beneficiaries').textContent = summary || 'None';

        if (closeAfter) closeModal('beneficiaries-modal');
        checkFormChanges();
    }

    function loadBeneficiaries() {
        const container = document.getElementById('beneficiary-rows');
        container.innerHTML = '';

        const json = document.getElementById('beneficiaries-json').value || '[]';
        try {
            const data = JSON.parse(json);
            data.forEach(b => addBeneficiaryRow(b.type || '', b.male || 0, b.female || 0));
        } catch (e) {
            console.error('Invalid beneficiaries JSON:', e);
        }
    }

    // Form change detection
    let originalValues = {};

    function checkFormChanges() {
        const currentValues = {
            title: document.getElementById('title').value.trim(),
            description: document.getElementById('description').value.trim(),
            start_date: document.querySelector('[name="start_date"]').value,
            end_date: document.querySelector('[name="end_date"]').value,
            type: document.getElementById('type-hidden').value.trim(),
            sdg: document.getElementById('sdg-hidden').value.trim(),
            offices: document.getElementById('offices_involved').value.trim(),
            programs: document.getElementById('programs_involved').value.trim(),
            beneficiaries: document.getElementById('beneficiaries-json').value.trim(),
            partner_agencies: document.getElementById('partner_agencies').value.trim(),
            source_of_fund: document.getElementById('source_of_fund').value.trim(),
            total_cost: document.getElementById('total_cost').value.trim()
        };

        let hasChanged = false;
        for (let key in originalValues) {
            if (currentValues[key] !== originalValues[key]) {
                hasChanged = true;
                break;
            }
        }

        const saveBtn = document.getElementById('save-btn');
        saveBtn.disabled = !hasChanged;
        saveBtn.style.background = hasChanged ? '#c8102e' : '#d1d5db';
        saveBtn.style.color = hasChanged ? 'white' : '#6b7280';
        saveBtn.style.cursor = hasChanged ? 'pointer' : 'not-allowed';
    }

    // Initialize
    window.addEventListener('load', function() {
        originalValues = {
            title: document.getElementById('title').value.trim(),
            description: document.getElementById('description').value.trim(),
            start_date: document.querySelector('[name="start_date"]').value,
            end_date: document.querySelector('[name="end_date"]').value,
            type: document.getElementById('type-hidden').value.trim(),
            sdg: document.getElementById('sdg-hidden').value.trim(),
            offices: document.getElementById('offices_involved').value.trim(),
            programs: document.getElementById('programs_involved').value.trim(),
            beneficiaries: document.getElementById('beneficiaries-json').value.trim(),
            partner_agencies: document.getElementById('partner_agencies').value.trim(),
            source_of_fund: document.getElementById('source_of_fund').value.trim(),
            total_cost: document.getElementById('total_cost').value.trim()
        };

        // Load beneficiaries
        loadBeneficiaries();
        saveBeneficiaries(false);   // Force preview update

        // Initial button state
        document.getElementById('save-btn').disabled = true;

        // Listen for changes
        document.querySelectorAll('input, textarea, select').forEach(el => {
            el.addEventListener('input', checkFormChanges);
            el.addEventListener('change', checkFormChanges);
        });

        checkFormChanges();
    });
    </script>

</body>
</html>