<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header("Location: ../login.php");
    exit;
}

require_once '../config/db.php';

$id = $_GET['id'] ?? null;
if (!$id || !is_numeric($id)) {
    header("Location: list.php");
    exit;
}

$stmt = $pdo->prepare("
    SELECT title, location, duration_start, duration_end,
           type_of_extension_service_agenda, sdg_goals, offices_involved,
           programs_involved, partner_agencies, beneficiaries_json,
           total_cost, source_of_fund
    FROM program_entries WHERE id = ?
");
$stmt->execute([$id]);
$entry = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$entry) {
    die("Program not found.");
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        try {
            $stmt = $pdo->prepare("
                UPDATE program_entries
                SET title = ?, location = ?, duration_start = ?, duration_end = ?,
                    type_of_extension_service_agenda = ?, sdg_goals = ?, offices_involved = ?,
                    programs_involved = ?, partner_agencies = ?, beneficiaries_json = ?,
                    total_cost = ?, source_of_fund = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $title, $location, $duration_start, $duration_end,
                $type, $sdg, $offices, $programs, $partners,
                $beneficiaries_json, $total_cost, $source_fund, $id
            ]);

            if ($stmt->rowCount() > 0) {
                // 1. Reset the program itself to active
                $resetProg = $pdo->prepare("
                    UPDATE program_entries 
                    SET status = 'active', updated_at = NOW() 
                    WHERE id = ?
                ");
                $resetProg->execute([$id]);

                // 2. Reset all projects under this program to active
                $resetProjects = $pdo->prepare("
                    UPDATE project_entries 
                    SET status = 'active', updated_at = NOW() 
                    WHERE program_id = ?
                ");
                $resetProjects->execute([$id]);

                // 3. Reset all activities under those projects to active
                $resetActivities = $pdo->prepare("
                    UPDATE activity_entries a
                    INNER JOIN project_entries p ON a.project_id = p.id
                    SET a.status = 'active', a.updated_at = NOW()
                    WHERE p.program_id = ?
                ");
                $resetActivities->execute([$id]);

                header("Location: list.php?success=updated");
                exit;
            } else {
                $error = 'No changes were made.';
            }
        } catch (PDOException $e) {
            $error = 'Update failed: ' . $e->getMessage();
        }
    }
}

$nav_links = [
    ['url' => 'index.php', 'label' => 'Home', 'active' => false],
    ['url' => '/opmm/list.php', 'label' => 'PPA', 'active' => false],
];
?>

<?php include '../includes/header.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Program</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        #save-btn {
            background: #c8102e;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: background 0.2s;
        }

        #save-btn:disabled {
            background: #d1d5db;
            color: #6b7280;
            cursor: not-allowed;
        }

        #save-btn:hover:not(:disabled) {
            background: #a50d24;
        }
    </style>
</head>
<body>

    <main class="dashboard-content">
        <h1>Edit Program</h1>

        <?php if ($error): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <form method="POST" id="edit-form">
            <label for="title">Title *</label>
            <input type="text" id="title" name="title" value="<?= htmlspecialchars($entry['title'] ?? '') ?>" required>

            <label for="location">Location *</label>
            <input type="text" id="location" name="location" value="<?= htmlspecialchars($entry['location'] ?? '') ?>" required>

            <label>Duration *</label>
            <div style="display: flex; gap: 16px; align-items: center;">
                <input type="date" name="duration_start" value="<?= htmlspecialchars($entry['duration_start'] ?? '') ?>" required>
                <span>to</span>
                <input type="date" name="duration_end" value="<?= htmlspecialchars($entry['duration_end'] ?? '') ?>" required>
            </div>

            <label>Type of Extension Service Agenda * (select all that apply)</label>
            <button type="button" onclick="openModal('type-modal')">Select Types</button>
            <div id="selected-types" style="margin: 8px 0; min-height: 40px; border: 1px solid #ccc; padding: 8px; border-radius: 4px;"></div>
            <input type="hidden" name="type_of_extension_service_agenda" id="type-hidden" value="<?= htmlspecialchars($entry['type_of_extension_service_agenda'] ?? '') ?>">

            <label>Sustainable Development Goals * (select all that apply)</label>
            <button type="button" onclick="openModal('sdg-modal')">Select SDGs</button>
            <div id="selected-sdgs" style="margin: 8px 0; min-height: 40px; border: 1px solid #ccc; padding: 8px; border-radius: 4px;"></div>
            <input type="hidden" name="sdg_goals" id="sdg-hidden" value="<?= htmlspecialchars($entry['sdg_goals'] ?? '') ?>">

            <label>Beneficiaries * (add types and counts)</label>
            <button type="button" onclick="openModal('beneficiaries-modal')">Manage Beneficiaries</button>
            <div id="selected-beneficiaries" style="margin: 8px 0; min-height: 40px; border: 1px solid #ccc; padding: 8px; border-radius: 4px;"></div>
            <input type="hidden" name="beneficiaries_json" id="beneficiaries-json" value="<?= htmlspecialchars($entry['beneficiaries_json'] ?? '[]') ?>">

            <label for="offices_involved">Offices/Colleges/Organizations Involved *</label>
            <input type="text" id="offices_involved" name="offices_involved" value="<?= htmlspecialchars($entry['offices_involved'] ?? '') ?>" required>

            <label for="programs_involved">Programs Involved *</label>
            <input type="text" id="programs_involved" name="programs_involved" value="<?= htmlspecialchars($entry['programs_involved'] ?? '') ?>" required>

            <label for="partner_agencies">Partner Agencies *</label>
            <input type="text" id="partner_agencies" name="partner_agencies" value="<?= htmlspecialchars($entry['partner_agencies'] ?? '') ?>" required>

            <label>Source of Fund * (select all that apply)</label>
            <button type="button" onclick="openModal('source-modal')">Select Sources</button>
            <div id="selected-source" style="margin: 8px 0; min-height: 40px; border: 1px solid #ccc; padding: 8px; border-radius: 4px;"></div>
            <input type="hidden" name="source_of_fund" id="source-hidden" value="<?= htmlspecialchars($entry['source_of_fund'] ?? '') ?>">

            <label for="total_cost">Total Cost *</label>
            <input type="number" id="total_cost" name="total_cost" step="0.01" min="0" value="<?= htmlspecialchars($entry['total_cost'] ?? '') ?>" required>

            <button type="submit" id="save-btn" disabled><b>Save Changes</b></button>
        </form>
    </main>

    <!-- Type Modal -->
    <div id="type-modal" class="modal-overlay">
        <div class="modal-box">
            <span class="close-modal" onclick="closeModal('type-modal')">×</span>
            <h2>Select Type of Extension Service Agenda</h2>
            <div id="type-checkboxes" style="max-height: 400px; overflow-y: auto; padding: 12px;"></div>
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
            <div id="sdg-checkboxes" style="max-height: 400px; overflow-y: auto; padding: 12px;"></div>
            <div class="modal-actions">
                <button onclick="saveModalSelections('sdg')">Save</button>
                <button onclick="closeModal('sdg-modal')">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Source Modal -->
    <div id="source-modal" class="modal-overlay">
        <div class="modal-box">
            <span class="close-modal" onclick="closeModal('source-modal')">×</span>
            <h2>Select Source of Fund</h2>
            <div id="source-checkboxes" style="max-height: 400px; overflow-y: auto; padding: 12px;"></div>
            <div class="modal-actions">
                <button onclick="saveModalSelections('source')">Save</button>
                <button onclick="closeModal('source-modal')">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Beneficiaries Modal -->
    <div id="beneficiaries-modal" class="modal-overlay">
        <div class="modal-box" style="max-width: 800px;">
            <span class="close-modal" onclick="closeModal('beneficiaries-modal')">×</span>
            <h2>Manage Beneficiaries</h2>
            <div id="beneficiary-rows" style="margin-bottom: 20px;"></div>
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
                        style="padding: 12px 24px; background: #6b7280; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 500;">
                    Cancel
                </button>
            </div>
        </div>
    </div>

    <script>
    // Hardcoded options (unchanged)
    const typeOptions = [
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

    const sdgOptions = [
        "No Poverty", "Zero Hunger", "Good Health and Well-Being", "Quality Education",
        "Gender Equality", "Clean Water and Sanitation", "Affordable and Clean Energy",
        "Decent Work and Economic Growth", "Industry, Innovation and Infrastructure",
        "Reduced Inequalities", "Sustainable Cities and Communities",
        "Responsible Consumption and Production", "Climate Action", "Life Below Water",
        "Life on Land", "Peace, Justice and Strong Institutions", "Partnerships for the Goals"
    ];

    const sourceOptions = ["MDS", "STF", "Others"];

    function openModal(modalId) {
        document.getElementById(modalId).classList.add('active');
        document.body.classList.add('modal-open');

        const type = modalId.replace('-modal', '');
        if (type !== 'beneficiaries') {
            setTimeout(() => loadModalCheckboxes(type), 0);
        } else {
            loadBeneficiaries();
        }
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
        const display = document.getElementById('selected-' + type);

        if (hidden) hidden.value = values.join(', ');
        if (display) display.textContent = values.length > 0 ? values.join(', ') : '';

        closeModal(type + '-modal');
        checkFormChanges();
    }

    function loadModalCheckboxes(type) {
        const container = document.getElementById(type + '-checkboxes');
        if (!container) return;

        const hidden = document.getElementById(type + '-hidden');
        const currentRaw = hidden?.value?.trim() || '';
        const currentValues = currentRaw 
            ? currentRaw.split(',').map(v => v.trim()).filter(v => v)
            : [];

        let options = [];
        if (type === 'type') options = typeOptions;
        if (type === 'sdg') options = sdgOptions;
        if (type === 'source') options = sourceOptions;

        container.innerHTML = '';

        options.forEach(val => {
            const label = document.createElement('label');
            label.style.display = 'flex';
            label.style.alignItems = 'center';
            label.style.justifyContent = 'space-between';
            label.style.cursor = 'pointer';
            label.style.padding = '8px';
            label.style.borderRadius = '6px';

            const checked = currentValues.includes(val) ? 'checked' : '';

            label.innerHTML = `
                ${val}
                <input type="checkbox" value="${val}" ${checked}>
            `;

            container.appendChild(label);
        });
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
            <input type="text" placeholder="e.g., Farmers, Students, PWDs" value="${type}" class="beneficiary-type" required style="flex: 2; min-width: 220px;">
            <input type="number" placeholder="Male" value="${male}" min="0" class="beneficiary-male" required style="flex: 1; max-width: 100px;">
            <input type="number" placeholder="Female" value="${female}" min="0" class="beneficiary-female" required style="flex: 1; max-width: 100px;">
            <button type="button" onclick="this.closest('.beneficiary-row').remove(); saveBeneficiaries();" class="remove-btn">×</button>
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
            if (type) data.push({ type, male, female });
        });
        const json = JSON.stringify(data);
        document.getElementById('beneficiaries-json').value = json;

        let summary = '';
        let total = 0;
        data.forEach(b => {
            summary += `${b.type}: ${b.male} male, ${b.female} female | `;
            total += b.male + b.female;
        });
        summary = summary.trim().replace(/ \| $/, '');
        if (total > 0) summary += ` | Total: ${total}`;
        document.getElementById('selected-beneficiaries').textContent = summary || '';

        checkFormChanges();
    }

    function loadBeneficiaries() {
        const rowsDiv = document.getElementById('beneficiary-rows');
        rowsDiv.innerHTML = '';

        const json = document.getElementById('beneficiaries-json').value.trim() || '[]';
        let data = [];
        try {
            data = JSON.parse(json);
        } catch (e) {
            console.error('Invalid beneficiaries JSON:', e);
        }

        if (data.length === 0) {
            rowsDiv.innerHTML = '<p style="color:#6b7280; text-align:center;">No beneficiaries added yet.</p>';
        } else {
            data.forEach(b => addBeneficiaryRow(b.type || '', b.male || 0, b.female || 0));
        }

        saveBeneficiaries();
    }

    let originalValues = {};

    function checkFormChanges() {
        const currentValues = {
            title: document.querySelector('[name="title"]').value.trim(),
            location: document.querySelector('[name="location"]').value.trim(),
            duration_start: document.querySelector('[name="duration_start"]').value.trim(),
            duration_end: document.querySelector('[name="duration_end"]').value.trim(),
            type_of_extension_service_agenda: document.getElementById('type-hidden')?.value.trim() || '',
            sdg_goals: document.getElementById('sdg-hidden')?.value.trim() || '',
            offices_involved: document.querySelector('[name="offices_involved"]').value.trim(),
            programs_involved: document.querySelector('[name="programs_involved"]').value.trim(),
            partner_agencies: document.querySelector('[name="partner_agencies"]').value.trim(),
            beneficiaries_json: document.getElementById('beneficiaries-json')?.value.trim() || '[]',
            total_cost: document.querySelector('[name="total_cost"]').value.trim(),
            source_of_fund: document.getElementById('source-hidden')?.value.trim() || ''
        };

        let changed = false;
        for (let key in originalValues) {
            if (currentValues[key] !== originalValues[key]) {
                changed = true;
                break;
            }
        }

        document.getElementById('save-btn').disabled = !changed;
    }

    document.addEventListener('DOMContentLoaded', function() {
        originalValues = {
            title: document.querySelector('[name="title"]').value.trim(),
            location: document.querySelector('[name="location"]').value.trim(),
            duration_start: document.querySelector('[name="duration_start"]').value.trim(),
            duration_end: document.querySelector('[name="duration_end"]').value.trim(),
            type_of_extension_service_agenda: document.getElementById('type-hidden').value.trim(),
            sdg_goals: document.getElementById('sdg-hidden').value.trim(),
            offices_involved: document.querySelector('[name="offices_involved"]').value.trim(),
            programs_involved: document.querySelector('[name="programs_involved"]').value.trim(),
            partner_agencies: document.querySelector('[name="partner_agencies"]').value.trim(),
            beneficiaries_json: document.getElementById('beneficiaries-json').value.trim(),
            total_cost: document.querySelector('[name="total_cost"]').value.trim(),
            source_of_fund: document.getElementById('source-hidden').value.trim()
        };

        const syncPreviews = () => {
            const fields = [
                { h: 'type-hidden', d: 'selected-types' },
                { h: 'sdg-hidden', d: 'selected-sdgs' },
                { h: 'source-hidden', d: 'selected-source' }
            ];

            fields.forEach(f => {
                const hidden = document.getElementById(f.h);
                const display = document.getElementById(f.d);
                if (hidden && display) {
                    const val = hidden.value.trim();
                    display.textContent = val || '';
                }
            });

            const bHidden = document.getElementById('beneficiaries-json');
            const bDisplay = document.getElementById('selected-beneficiaries');
            if (bHidden && bDisplay) {
                const json = bHidden.value.trim() || '[]';
                let data = [];
                try { data = JSON.parse(json); } catch (e) {}
                let summary = '';
                let total = 0;
                data.forEach(b => {
                    summary += `${b.type}: ${b.male} male, ${b.female} female | `;
                    total += (b.male || 0) + (b.female || 0);
                });
                summary = summary.trim().replace(/ \| $/, '');
                if (total > 0) summary += ` | Total: ${total}`;
                bDisplay.textContent = summary || '';
            }
        };

        syncPreviews();
        loadBeneficiaries();

        document.querySelectorAll('input, select').forEach(el => {
            el.addEventListener('input', () => { checkFormChanges(); syncPreviews(); });
            el.addEventListener('change', () => { checkFormChanges(); syncPreviews(); });
        });

        checkFormChanges();
    });
    </script>

</body>
</html>