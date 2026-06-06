<?php
session_start();
// Start session: used for authentication and temporarily storing form data (e.g., pending activity before confirmation)

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header("Location: ../login.php");
    exit;
}

require_once '../config/db.php';
// Load database connection (PDO instance)

$id = $_GET['id'] ?? null;
if (!$id || !is_numeric($id)) {
    header("Location: list.php");
    exit;
}

// Prepare SQL query safely (prevents SQL injection)
$stmt = $pdo->prepare("
    SELECT title, location, duration_start, duration_end,
           type_of_extension_service_agenda, sdg_goals, offices_involved,
           programs_involved, partner_agencies, beneficiaries_json,
           total_cost, source_of_fund, monitoring_frequency
    FROM program_entries WHERE id = ?
");
$stmt->execute([$id]);
$entry = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$entry) {
    die("Program not found.");
}

// Hardcoded options (same as add.php)
$typeOptions = [
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

$sdgOptions = [
    "No Poverty", "Zero Hunger", "Good Health and Well-Being", "Quality Education",
    "Gender Equality", "Clean Water and Sanitation", "Affordable and Clean Energy",
    "Decent Work and Economic Growth", "Industry, Innovation and Infrastructure",
    "Reduced Inequalities", "Sustainable Cities and Communities",
    "Responsible Consumption and Production", "Climate Action", "Life Below Water",
    "Life on Land", "Peace, Justice and Strong Institutions", "Partnerships for the Goals"
];

$sourceOptions = ["MDS", "STF", "Others"];

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
    $monitoring_frequency = trim($_POST['monitoring_frequency'] ?? '');

    // Validate end date is Dec 31 of (start year + 5)
    $duration_valid = false;
    if ($duration_start && $duration_end) {
        $start = new DateTime($duration_start);
        $expected_year = $start->format('Y') + 5;
        $expected_end = new DateTime("$expected_year-12-31");
        $submitted_end = new DateTime($duration_end);

        if ($submitted_end->format('Y-m-d') === $expected_end->format('Y-m-d')) {
            $duration_valid = true;
        }
    }

    if (empty($title) || empty($location) || empty($duration_start) || empty($duration_end) ||
        !$duration_valid || empty($type) || empty($sdg) || empty($offices) || empty($programs) ||
        empty($partners) || $total_cost <= 0 || empty($source_fund) || empty($monitoring_frequency)) {
        $error = 'Please fill all required fields. Duration end must be December 31 of the year +5 from start year.';
    } else {
        try {
            // Prepare SQL query safely (prevents SQL injection)
$stmt = $pdo->prepare("
                UPDATE program_entries
                SET title = ?, location = ?, duration_start = ?, duration_end = ?,
                    type_of_extension_service_agenda = ?, sdg_goals = ?, offices_involved = ?,
                    programs_involved = ?, partner_agencies = ?, beneficiaries_json = ?,
                    total_cost = ?, source_of_fund = ?, monitoring_frequency = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $title, $location, $duration_start, $duration_end,
                $type, $sdg, $offices, $programs, $partners,
                $beneficiaries_json, $total_cost, $source_fund,
                $monitoring_frequency, $id
            ]);

            if ($stmt->rowCount() > 0) {
                $pdo->prepare("UPDATE program_entries SET status = 'active', updated_at = NOW() WHERE id = ?")->execute([$id]);
                $pdo->prepare("UPDATE project_entries SET status = 'active', updated_at = NOW() WHERE program_id = ?")->execute([$id]);
                $pdo->prepare("
                    UPDATE activity_entries a
                    INNER JOIN project_entries p ON a.project_id = p.id
                    SET a.status = 'active', a.updated_at = NOW()
                    WHERE p.program_id = ?
                ")->execute([$id]);

                header("Location: view.php?id={$id}&success=updated");
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
    ['url' => '/opmm/list.php', 'label' => 'Dashboard', 'active' => false],
    ['url' => '/opmm/list_proposals.php', 'label' => 'Proposals', 'active' => false],
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
</head>
<body>

    <main class="dashboard-content add-program-page">
        <h1>Edit Program</h1>

        <?php if ($error): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <form method="POST" class="program-form" id="edit-form">
            <div class="form-group">
                <label for="title">Title *</label>
                <input type="text" id="title" name="title" value="<?= htmlspecialchars($entry['title'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label for="location">Location *</label>
                <input type="text" id="location" name="location" value="<?= htmlspecialchars($entry['location'] ?? '') ?>" required>
            </div>

            <div class="form-group full-span">
                <label>Duration & Frequency of Monitoring *</label>
                <div class="date-group">
                    <input type="date" name="duration_start" id="duration_start" value="<?= htmlspecialchars($entry['duration_start'] ?? '') ?>" required onchange="updateEndDate()">
                    <span>to</span>
                    <input type="date" name="duration_end" id="duration_end" value="<?= htmlspecialchars($entry['duration_end'] ?? '') ?>" readonly required>
                    <select name="monitoring_frequency" required>
                        <option value="">Frequency</option>
                        <option value="Monthly"    <?= ($entry['monitoring_frequency'] ?? '') === 'Monthly'    ? 'selected' : '' ?>>Monthly</option>
                        <option value="Quarterly"  <?= ($entry['monitoring_frequency'] ?? '') === 'Quarterly'  ? 'selected' : '' ?>>Quarterly</option>
                        <option value="Semi-Annually" <?= ($entry['monitoring_frequency'] ?? '') === 'Semi-Annually' ? 'selected' : '' ?>>Semi-Annually</option>
                        <option value="Annually"   <?= ($entry['monitoring_frequency'] ?? '') === 'Annually'   ? 'selected' : '' ?>>Annually</option>
                    </select>
                </div>
                <small class="hint">
                    Program always ends on December 31 of the year +5 from start year.<br>
                    Frequency applies during monitoring & evaluation (typically last 2 years).
                </small>
            </div>

            <div class="form-group">
                <label>Type of Extension Service Agenda * (select all that apply)</label>
                <button type="button" onclick="openModal('type-modal')">Select Types</button>
                <div id="selected-types" class="compact-preview"></div>
                <input type="hidden" name="type_of_extension_service_agenda" id="type-hidden" value="<?= htmlspecialchars($entry['type_of_extension_service_agenda'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label>Sustainable Development Goals * (select all that apply)</label>
                <button type="button" onclick="openModal('sdg-modal')">Select SDGs</button>
                <div id="selected-sdgs" class="compact-preview"></div>
                <input type="hidden" name="sdg_goals" id="sdg-hidden" value="<?= htmlspecialchars($entry['sdg_goals'] ?? '') ?>">
            </div>

            <div class="form-group full-span">
                <label>Beneficiaries * (add types and counts)</label>
                <button type="button" onclick="openModal('beneficiaries-modal')">Manage Beneficiaries</button>
                <div id="selected-beneficiaries" class="compact-preview"></div>
                <input type="hidden" name="beneficiaries_json" id="beneficiaries-json" value="<?= htmlspecialchars($entry['beneficiaries_json'] ?? '[]') ?>">
            </div>

            <div class="form-group">
                <label for="offices_involved">Offices/Colleges/Organizations Involved *</label>
                <input type="text" id="offices_involved" name="offices_involved" value="<?= htmlspecialchars($entry['offices_involved'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label for="programs_involved">Programs Involved *</label>
                <input type="text" id="programs_involved" name="programs_involved" value="<?= htmlspecialchars($entry['programs_involved'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label for="partner_agencies">Partner Agencies *</label>
                <input type="text" id="partner_agencies" name="partner_agencies" value="<?= htmlspecialchars($entry['partner_agencies'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label>Source of Fund * (select all that apply)</label>
                <button type="button" onclick="openModal('source-modal')">Select Sources</button>
                <div id="selected-source" class="compact-preview"></div>
                <input type="hidden" name="source_of_fund" id="source-hidden" value="<?= htmlspecialchars($entry['source_of_fund'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="total_cost">Total Cost *</label>
                <input type="number" id="total_cost" name="total_cost" step="0.01" min="0" value="<?= htmlspecialchars($entry['total_cost'] ?? '') ?>" required>
            </div>

            <div class="full-span" style="text-align: center; margin-top: 16px;">
                <button type="submit" id="save-btn" disabled>Save Changes</button>
            </div>
        </form>
    </main>

    <!-- Type Modal -->
    <div id="type-modal" class="modal-overlay">
        <div class="modal-box">
            <span class="close-modal" onclick="closeModal('type-modal')">×</span>
            <h2>Select Type of Extension Service Agenda</h2>
            <div style="max-height: 400px; overflow-y: auto; padding: 12px;">
                <?php foreach ($typeOptions as $opt): ?>
                    <label class="modal-checkbox-label">
                        <span><?= htmlspecialchars($opt) ?></span>
                        <input type="checkbox" value="<?= htmlspecialchars($opt) ?>">
                    </label>
                <?php endforeach; ?>
            </div>
            <div class="modal-actions">
                <button onclick="saveModalSelections('type')" 
                        style="padding: 10px 20px; background: #c8102e; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 500;">
                    Save
                </button>
                <button onclick="closeModal('type-modal')" 
                        style="padding: 10px 20px; background: #6b7280; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 500;">
                    Cancel
                </button>
            </div>
        </div>
    </div>

    <!-- SDG Modal -->
    <div id="sdg-modal" class="modal-overlay">
        <div class="modal-box">
            <span class="close-modal" onclick="closeModal('sdg-modal')">×</span>
            <h2>Select Sustainable Development Goals</h2>
            <div style="max-height: 400px; overflow-y: auto; padding: 12px;">
                <?php foreach ($sdgOptions as $opt): ?>
                    <label class="modal-checkbox-label">
                        <span><?= htmlspecialchars($opt) ?></span>
                        <input type="checkbox" value="<?= htmlspecialchars($opt) ?>">
                    </label>
                <?php endforeach; ?>
            </div>
            <div class="modal-actions">
                <button onclick="saveModalSelections('sdg')" 
                        style="padding: 10px 20px; background: #c8102e; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 500;">
                    Save
                </button>
                <button onclick="closeModal('sdg-modal')" 
                        style="padding: 10px 20px; background: #6b7280; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 500;">
                    Cancel
                </button>
            </div>
        </div>
    </div>

    <!-- Source Modal -->
    <div id="source-modal" class="modal-overlay">
        <div class="modal-box">
            <span class="close-modal" onclick="closeModal('source-modal')">×</span>
            <h2>Select Source of Fund</h2>
            <div style="max-height: 400px; overflow-y: auto; padding: 12px;">
                <?php foreach ($sourceOptions as $opt): ?>
                    <label class="modal-checkbox-label">
                        <span><?= htmlspecialchars($opt) ?></span>
                        <input type="checkbox" value="<?= htmlspecialchars($opt) ?>">
                    </label>
                <?php endforeach; ?>
            </div>
            <div class="modal-actions">
                <button onclick="saveModalSelections('source')" 
                        style="padding: 10px 20px; background: #c8102e; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 500;">
                    Save
                </button>
                <button onclick="closeModal('source-modal')" 
                        style="padding: 10px 20px; background: #6b7280; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 500;">
                    Cancel
                </button>
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
                <button onclick="saveBeneficiaries()"
                        style="padding: 10px 20px; background: #c8102e; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 500;">
                    Save
                </button>
                <button onclick="closeModal('beneficiaries-modal')"
                        style="padding: 10px 20px; background: #6b7280; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 500;">
                    Cancel
                </button>
            </div>
        </div>
    </div>

    <script>
    // Auto-set duration_end to Dec 31 of (start year + 5)
    function updateEndDate() {
        const startInput = document.getElementById('duration_start');
        const endInput = document.getElementById('duration_end');
        if (startInput.value) {
            const start = new Date(startInput.value);
            const year = start.getFullYear() + 5;
            endInput.value = `${year}-12-31`;
        } else {
            endInput.value = '';
        }
    }

    // Opens modal UI and restores previously selected values
function openModal(modalId) {
        document.getElementById(modalId).classList.add('active');
        document.body.classList.add('modal-open');

        const type = modalId.replace('-modal', '');
        if (type !== 'beneficiaries') {
            setTimeout(() => {
                restoreCheckedState(type);
            }, 100);
        } else {
            loadBeneficiaries();
        }
    }

    function closeModal(modalId) {
        document.getElementById(modalId).classList.remove('active');
        document.body.classList.remove('modal-open');
    }

    // Save selected checkbox values into hidden inputs (used for form submission)
function saveModalSelections(type) {
        const modal = document.getElementById(type + '-modal');
        const checkboxes = modal.querySelectorAll('input[type="checkbox"]:checked');
        const values = Array.from(checkboxes).map(cb => cb.value.trim());

        const hidden = document.getElementById(type + '-hidden');
        const display = document.getElementById('selected-' + type);

        if (hidden) {
            hidden.value = values.map(v => v.trim()).join(', ');
        }

        if (display) {
            display.textContent = values.length > 0 ? values.join(', ') : 'None';
        }

        closeModal(type + '-modal');

        setTimeout(() => {
            syncPreviews();
            checkFormChanges();
        }, 50);
    }

    // Restore previously selected values when reopening modal (prevents losing selections)
function restoreCheckedState(type) {
        const modal = document.getElementById(type + '-modal');
        const hidden = document.getElementById(type + '-hidden');
        const currentRaw = hidden?.value?.trim() || '';

        let normalized = currentRaw.replace(/\s*,\s*/g, ',').toLowerCase();

        const checkboxes = modal.querySelectorAll('input[type="checkbox"]');
        checkboxes.forEach(cb => {
            let valNormalized = cb.value.toLowerCase().replace(/\s*,\s*/g, ',');
            cb.checked = normalized.includes(valNormalized);
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
            <input type="text" placeholder="e.g., Farmers, Students, PWDs" value="${type}" class="beneficiary-type" required style="flex: 2; min-width: 180px;">
            <input type="number" placeholder="Male" value="${male}" min="0" class="beneficiary-male" required style="flex: 1; max-width: 80px;">
            <input type="number" placeholder="Female" value="${female}" min="0" class="beneficiary-female" required style="flex: 1; max-width: 80px;">
            <button type="button" onclick="this.closest('.beneficiary-row').remove(); saveBeneficiaries(false);" class="remove-btn">×</button>
        `;

        container.appendChild(row);
    }

    // Save selected beneficiaries as JSON string into hidden input for backend processing
function saveBeneficiaries(closeAfter = true) {
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
            total += (b.male || 0) + (b.female || 0);
        });
        summary = summary.trim().replace(/ \| $/, '');
        if (total > 0) summary += ` | Total: ${total}`;
        document.getElementById('selected-beneficiaries').textContent = summary || 'None';

        syncPreviews();
        checkFormChanges();

        if (closeAfter) {
            closeModal('beneficiaries-modal');
        }
    }

    // Load beneficiaries from parent project and allow user to select subset for this activity
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
    }

    // Sync UI preview labels with hidden input values (ensures user sees selected data)
function syncPreviews() {
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
                display.textContent = val || 'None';
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
            bDisplay.textContent = summary || 'None';
        }
    }

    let originalValues = {};

    function checkFormChanges() {
        const currentValues = {
            title: document.querySelector('[name="title"]').value.trim(),
            location: document.querySelector('[name="location"]').value.trim(),
            duration_start: document.querySelector('[name="duration_start"]').value.trim(),
            duration_end: document.querySelector('[name="duration_end"]').value.trim(),
            monitoring_frequency: document.querySelector('[name="monitoring_frequency"]').value.trim(),
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

        const saveBtn = document.getElementById('save-btn');
        if (changed) {
            saveBtn.disabled = false;
            saveBtn.style.background = '#c8102e';
            saveBtn.style.color = 'white';
            saveBtn.style.cursor = 'pointer';
        } else {
            saveBtn.disabled = true;
            saveBtn.style.background = '#d1d5db';
            saveBtn.style.color = '#6b7280';
            saveBtn.style.cursor = 'not-allowed';
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        originalValues = {
            title: document.querySelector('[name="title"]').value.trim(),
            location: document.querySelector('[name="location"]').value.trim(),
            duration_start: document.querySelector('[name="duration_start"]').value.trim(),
            duration_end: document.querySelector('[name="duration_end"]').value.trim(),
            monitoring_frequency: document.querySelector('[name="monitoring_frequency"]').value.trim(),
            type_of_extension_service_agenda: document.getElementById('type-hidden').value.trim(),
            sdg_goals: document.getElementById('sdg-hidden').value.trim(),
            offices_involved: document.querySelector('[name="offices_involved"]').value.trim(),
            programs_involved: document.querySelector('[name="programs_involved"]').value.trim(),
            partner_agencies: document.querySelector('[name="partner_agencies"]').value.trim(),
            beneficiaries_json: document.getElementById('beneficiaries-json').value.trim(),
            total_cost: document.querySelector('[name="total_cost"]').value.trim(),
            source_of_fund: document.getElementById('source-hidden').value.trim()
        };

        // Load previews and beneficiaries
        syncPreviews();
        loadBeneficiaries();

        // Auto-set end date on load
        updateEndDate();

        // Listen for changes
        document.querySelectorAll('input, select').forEach(el => {
            el.addEventListener('input', () => { checkFormChanges(); syncPreviews(); });
            el.addEventListener('change', () => { checkFormChanges(); syncPreviews(); });
        });

        // Initial button state
        const saveBtn = document.getElementById('save-btn');
        saveBtn.disabled = true;
        saveBtn.style.background = '#d1d5db';
        saveBtn.style.color = '#6b7280';
        saveBtn.style.cursor = 'not-allowed';

        checkFormChanges();
    });

    // Validate on submit: end must be Dec 31 of (start year + 5)
    document.getElementById('edit-form')?.addEventListener('submit', function(e) {
        const start = document.getElementById('duration_start').value;
        const end = document.getElementById('duration_end').value;

        if (start && end) {
            const s = new Date(start);
            const expectedEnd = `${s.getFullYear() + 5}-12-31`;
            if (end !== expectedEnd) {
                e.preventDefault();
                alert('Program must end on December 31 of the year +5 from the start year.');
            }
        }
    });
    </script>

</body>
</html>