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
    SELECT project_id, activity_name, implementation_start, implementation_end,
           type_of_extension_service_agenda, sdg_goals,
           offices_involved, programs_involved, beneficiaries_json
    FROM activity_entries WHERE id = ?
");
$stmt->execute([$id]);
$entry = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$entry) {
    die("Activity not found.");
}

$project_id = $entry['project_id'];

$pStmt = $pdo->prepare("
    SELECT project_title, implementation_start, implementation_end,
           type_of_extension_service_agenda, sdg_goals,
           offices_involved, programs_involved, beneficiaries_json
    FROM project_entries WHERE id = ?
");
$pStmt->execute([$project_id]);
$parent = $pStmt->fetch(PDO::FETCH_ASSOC);

if (!$parent) {
    die("Parent project not found.");
}

$parentStart = $parent['implementation_start'];
$parentEnd   = $parent['implementation_end'];

// Hardcoded full lists (same as add_activity.php)
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
    "No Poverty",
    "Zero Hunger",
    "Good Health and Well-Being",
    "Quality Education",
    "Gender Equality",
    "Clean Water and Sanitation",
    "Affordable and Clean Energy",
    "Decent Work and Economic Growth",
    "Industry, Innovation and Infrastructure",
    "Reduced Inequalities",
    "Sustainable Cities and Communities",
    "Responsible Consumption and Production",
    "Climate Action",
    "Life Below Water",
    "Life on Land",
    "Peace, Justice and Strong Institutions",
    "Partnerships for the Goals"
];

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $activity_name = trim($_POST['activity_name'] ?? '');
    $type_agenda   = trim($_POST['type_agenda'] ?? '');
    $sdg_goals     = trim($_POST['sdg_goals'] ?? '');
    $offices       = trim($_POST['offices'] ?? '');
    $programs      = trim($_POST['programs'] ?? '');
    $beneficiaries = trim($_POST['beneficiaries'] ?? '[]');

    $impl_start    = $_POST['implementation_start'] ?? '';
    $impl_end      = $_POST['implementation_end'] ?? '';

    $benefData = json_decode($beneficiaries, true) ?? [];

    $totalBenef = 0;
    foreach ($benefData as $b) {
        $totalBenef += ($b['male'] ?? 0) + ($b['female'] ?? 0);
    }

    if (empty($activity_name) || empty($impl_start) || empty($impl_end) ||
        empty($type_agenda) || empty($sdg_goals) ||
        empty($offices) || empty($programs) || $beneficiaries === '[]' || $totalBenef === 0) {
        $error = 'Please fill all required fields (at least one beneficiary with total > 0).';
    } elseif (strtotime($impl_end) < strtotime($impl_start)) {
        $error = 'End date cannot be before start date.';
    } elseif (strtotime($impl_start) < strtotime($parentStart) || strtotime($impl_end) > strtotime($parentEnd)) {
        $error = 'Implementation period must be within parent project duration.';
    } else {
        try {
            $stmt = $pdo->prepare("
                UPDATE activity_entries 
                SET activity_name = ?, implementation_start = ?, implementation_end = ?,
                    type_of_extension_service_agenda = ?, sdg_goals = ?,
                    offices_involved = ?, programs_involved = ?, beneficiaries_json = ?,
                    status = 'active', updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $activity_name, $impl_start, $impl_end,
                $type_agenda, $sdg_goals,
                $offices, $programs, $beneficiaries, $id
            ]);

            $revertProj = $pdo->prepare("
                UPDATE project_entries 
                SET status = 'active', updated_at = NOW() 
                WHERE id = ?
            ");
            $revertProj->execute([$project_id]);

            if ($stmt->rowCount() > 0) {
                header("Location: view_activity.php?id={$id}&success=updated");
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
    ['url' => '/opmm/view_project.php?id=' . $project_id, 'label' => 'Project', 'active' => false],
];
?>

<?php include '../includes/header.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Activity</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        /* Only keep modal-specific red buttons if not moved to style.css */
        .modal-actions button:first-child {
            background: #c8102e;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 10px 20px;
            cursor: pointer;
            font-weight: 500;
            transition: background 0.2s;
        }
        .modal-actions button:first-child:hover {
            background: #a50d24;
        }
        .modal-actions button:last-child {
            background: #6b7280;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 10px 20px;
            cursor: pointer;
            font-weight: 500;
        }

        /* Save Changes button - normal state */
        #save-btn {
            background: #c8102e;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: background 0.2s;
        }
        #save-btn:hover:not(:disabled) {
            background: #a50d24;
        }

        /* Disabled state: gray background + gray text to clearly show it's inactive */
        #save-btn:disabled {
            background: #d1d5db;
            color: #6b7280 !important;   /* gray text when disabled */
            cursor: not-allowed;
        }
    </style>
</head>
<body>

    <main class="dashboard-content">
        <h1>Edit Activity</h1>

        <?php if ($error): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <form method="POST" id="edit-form" class="program-form">
            <div class="form-group">
                <label for="activity_name">Activity Name *</label>
                <input type="text" id="activity_name" name="activity_name" value="<?= htmlspecialchars($entry['activity_name'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label>Type of Extension Service Agenda *</label>
                <button type="button" onclick="openModal('type-modal')">Select Types</button>
                <div id="selected-types" class="compact-preview"></div>
                <input type="hidden" name="type_agenda" id="type-hidden" value="<?= htmlspecialchars($entry['type_of_extension_service_agenda'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label>Sustainable Development Goals *</label>
                <button type="button" onclick="openModal('sdg-modal')">Select SDGs</button>
                <div id="selected-sdgs" class="compact-preview"></div>
                <input type="hidden" name="sdg_goals" id="sdg-hidden" value="<?= htmlspecialchars($entry['sdg_goals'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label>Offices Involved *</label>
                <button type="button" onclick="openModal('offices-modal')">Select Offices</button>
                <div id="selected-offices" class="compact-preview"></div>
                <input type="hidden" name="offices" id="offices-hidden" value="<?= htmlspecialchars($entry['offices_involved'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label>Programs Involved *</label>
                <button type="button" onclick="openModal('programs-modal')">Select Programs</button>
                <div id="selected-programs" class="compact-preview"></div>
                <input type="hidden" name="programs" id="programs-hidden" value="<?= htmlspecialchars($entry['programs_involved'] ?? '') ?>">
            </div>

            <div class="form-group full-span">
                <label>Beneficiaries *</label>
                <button type="button" onclick="openBeneficiariesModal()">Manage Beneficiaries</button>
                <div id="selected-beneficiaries" class="compact-preview"></div>
                <input type="hidden" name="beneficiaries" id="beneficiaries-hidden" value="<?= htmlspecialchars($entry['beneficiaries_json'] ?? '[]') ?>">
            </div>

            <div class="form-group full-span">
                <label>Implementation Duration * (within parent project)</label>
                <div class="date-group">
                    <input type="date" name="implementation_start"
                           value="<?= htmlspecialchars($entry['implementation_start'] ?? '') ?>"
                           min="<?= htmlspecialchars($parentStart) ?>"
                           max="<?= htmlspecialchars($parentEnd) ?>"
                           required>
                    <span>to</span>
                    <input type="date" name="implementation_end"
                           value="<?= htmlspecialchars($entry['implementation_end'] ?? '') ?>"
                           min="<?= htmlspecialchars($parentStart) ?>"
                           max="<?= htmlspecialchars($parentEnd) ?>"
                           required>
                </div>
                <small class="hint">
                    Must be between <?= htmlspecialchars(date('M d, Y', strtotime($parentStart))) ?> 
                    and <?= htmlspecialchars(date('M d, Y', strtotime($parentEnd))) ?>
                </small>
            </div>

            <div class="form-footer">
                <button type="submit" id="save-btn" disabled><b>Save Changes</b></button>
            </div>
        </form>
    </main>

    <!-- Type Modal -->
    <div id="type-modal" class="modal-overlay">
        <div class="modal-box">
            <span class="close-modal" onclick="closeModal('type-modal')">×</span>
            <h2>Select Type of Extension Service Agenda</h2>
            <div style="max-height: 400px; overflow-y: auto; padding: 12px;">
                <?php
                $parentTypeStr = $parent['type_of_extension_service_agenda'] ?? '';
                $shown = false;
                foreach ($fullTypeOptions as $opt):
                    if (stripos($parentTypeStr, $opt) !== false):
                        $shown = true;
                ?>
                    <label class="modal-checkbox-label">
                        <span><?= htmlspecialchars($opt) ?></span>
                        <input type="checkbox" value="<?= htmlspecialchars($opt) ?>">
                    </label>
                <?php
                    endif;
                endforeach;
                if (!$shown):
                ?>
                    <p>No types available from parent project.</p>
                <?php endif; ?>
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
                <?php
                $parentSdgStr = $parent['sdg_goals'] ?? '';
                $shown = false;
                foreach ($fullSdgOptions as $opt):
                    if (stripos($parentSdgStr, $opt) !== false):
                        $shown = true;
                ?>
                    <label class="modal-checkbox-label">
                        <span><?= htmlspecialchars($opt) ?></span>
                        <input type="checkbox" value="<?= htmlspecialchars($opt) ?>">
                    </label>
                <?php
                    endif;
                endforeach;
                if (!$shown):
                ?>
                    <p>No SDGs available from parent project.</p>
                <?php endif; ?>
            </div>
            <div class="modal-actions">
                <button onclick="saveModalSelections('sdg')">Save</button>
                <button onclick="closeModal('sdg-modal')">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Offices Modal -->
    <div id="offices-modal" class="modal-overlay">
        <div class="modal-box">
            <span class="close-modal" onclick="closeModal('offices-modal')">×</span>
            <h2>Select Offices Involved</h2>
            <div style="max-height: 400px; overflow-y: auto; padding: 12px;">
                <?php
                if ($parent['offices_involved']) {
                    $offices = explode(', ', $parent['offices_involved']);
                    foreach ($offices as $o) {
                        $o = trim($o);
                        if ($o !== '') {
                            echo '
                            <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px; border-radius: 6px;">
                                ' . htmlspecialchars($o) . '
                                <input type="checkbox" value="' . htmlspecialchars($o) . '">
                            </label>';
                        }
                    }
                } else {
                    echo '<p>No offices available from parent project.</p>';
                }
                ?>
            </div>
            <div class="modal-actions">
                <button onclick="saveModalSelections('offices')">Save</button>
                <button onclick="closeModal('offices-modal')">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Programs Modal -->
    <div id="programs-modal" class="modal-overlay">
        <div class="modal-box">
            <span class="close-modal" onclick="closeModal('programs-modal')">×</span>
            <h2>Select Programs Involved</h2>
            <div style="max-height: 400px; overflow-y: auto; padding: 12px;">
                <?php
                if ($parent['programs_involved']) {
                    $programs = explode(', ', $parent['programs_involved']);
                    foreach ($programs as $p) {
                        $p = trim($p);
                        if ($p !== '') {
                            echo '
                            <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px; border-radius: 6px;">
                                ' . htmlspecialchars($p) . '
                                <input type="checkbox" value="' . htmlspecialchars($p) . '">
                            </label>';
                        }
                    }
                } else {
                    echo '<p>No programs available from parent project.</p>';
                }
                ?>
            </div>
            <div class="modal-actions">
                <button onclick="saveModalSelections('programs')">Save</button>
                <button onclick="closeModal('programs-modal')">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Beneficiaries Modal -->
    <div id="beneficiaries-modal" class="modal-overlay">
        <div class="modal-box" style="max-width: 800px;">
            <span class="close-modal" onclick="closeModal('beneficiaries-modal')">×</span>
            <h2>Manage Beneficiaries</h2>
            <div id="beneficiary-rows" style="margin-bottom: 16px;"></div>
            <div class="modal-actions" style="margin-top: 20px; display: flex; gap: 12px; justify-content: flex-end;">
                <button onclick="saveBeneficiaries()">Save</button>
                <button onclick="closeModal('beneficiaries-modal')">Cancel</button>
            </div>
        </div>
    </div>

    <script>
let beneficiariesData = [];

function openModal(modalId) {
    document.getElementById(modalId).classList.add('active');
    document.body.classList.add('modal-open');

    const type = modalId.replace('-modal', '');
    if (['type','sdg','offices','programs'].includes(type)) {
        setTimeout(() => {
            restoreCheckedState(type);
        }, 100);
    }
}

function openBeneficiariesModal() {
    openModal('beneficiaries-modal');
    loadBeneficiaries();
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
    let displayId = '';
    if (type === 'type') displayId = 'selected-types';
    if (type === 'sdg') displayId = 'selected-sdgs';
    if (type === 'offices') displayId = 'selected-offices';
    if (type === 'programs') displayId = 'selected-programs';

    const display = document.getElementById(displayId);

    if (hidden) {
        hidden.value = values.join(', ');
    }
    if (display) {
        display.textContent = values.length ? values.join(', ') : 'None';
    }

    closeModal(type + '-modal');
    checkFormChanges();
}

function restoreCheckedState(type) {
    const modal = document.getElementById(type + '-modal');
    const hidden = document.getElementById(type + '-hidden');
    const currentRaw = hidden?.value?.trim() || '';

    const norm1 = currentRaw.toLowerCase().replace(/\s+/g, '');
    const norm2 = currentRaw.toLowerCase().replace(/[\s,]+/g, '');
    const norm3 = currentRaw.toLowerCase().replace(/,/g, '').replace(/\s+/g, '');

    const checkboxes = modal.querySelectorAll('input[type="checkbox"]');
    checkboxes.forEach(cb => {
        const val = cb.value.trim();
        const n1 = val.toLowerCase().replace(/\s+/g, '');
        const n2 = val.toLowerCase().replace(/[\s,]+/g, '');
        const n3 = val.toLowerCase().replace(/,/g, '').replace(/\s+/g, '');

        cb.checked = (norm1.includes(n1) || norm2.includes(n2) || norm3.includes(n3));
    });
}

function loadBeneficiaries() {
    const rowsDiv = document.getElementById('beneficiary-rows');
    rowsDiv.innerHTML = '';

    const currentJson = document.getElementById('beneficiaries-hidden').value || '[]';
    let currentEntries = [];
    try { currentEntries = JSON.parse(currentJson); } catch (e) {}

    const parentJson = <?= json_encode($parent['beneficiaries_json'] ?? '[]') ?>;
    let parentEntries = [];
    try { parentEntries = JSON.parse(parentJson); } catch (e) {}

    if (parentEntries.length === 0) {
        rowsDiv.innerHTML = '<p style="color:#6b7280; text-align:center;">No beneficiaries in parent project.</p>';
        return;
    }

    beneficiariesData = parentEntries.map((e, i) => {
        const isSelected = currentEntries.some(c => (c.type || '').trim() === (e.type || '').trim());
        return {
            type: e.type || 'Unnamed Type',
            male: Number(e.male || 0),
            female: Number(e.female || 0),
            index: i,
            selected: isSelected
        };
    });

    beneficiariesData.forEach((entry, index) => {
        const row = document.createElement('div');
        row.style.display = 'flex';
        row.style.alignItems = 'center';
        row.style.gap = '16px';
        row.style.marginBottom = '12px';
        row.style.padding = '12px';
        row.style.border = '1px solid #d1d5db';
        row.style.borderRadius = '6px';

        row.innerHTML = `
            <input type="checkbox" ${entry.selected ? 'checked' : ''} 
                   onchange="toggleBeneficiary(${index}, this.checked)" style="width:24px; height:24px;">
            <div style="flex: 1;">
                <strong>${entry.type}</strong><br>
                <span style="color:#6b7280;">
                    Male: ${entry.male} | Female: ${entry.female}
                </span>
            </div>
        `;

        rowsDiv.appendChild(row);
    });
}

function toggleBeneficiary(index, checked) {
    if (beneficiariesData[index]) beneficiariesData[index].selected = checked;
}

function saveBeneficiaries() {
    const selected = beneficiariesData.filter(b => b.selected).map(b => ({
        type: b.type,
        male: b.male,
        female: b.female
    }));

    const hidden = document.getElementById('beneficiaries-hidden');
    if (hidden) hidden.value = JSON.stringify(selected);

    const preview = document.getElementById('selected-beneficiaries');
    if (preview) {
        const count = selected.length;
        preview.textContent = count > 0 ? `${count} type(s) selected` : 'None';
    }

    closeModal('beneficiaries-modal');
    checkFormChanges();
}

function syncPreviews() {
    const pairs = [
        ['type-hidden', 'selected-types'],
        ['sdg-hidden', 'selected-sdgs'],
        ['offices-hidden', 'selected-offices'],
        ['programs-hidden', 'selected-programs']
    ];

    pairs.forEach(([hId, dId]) => {
        const hidden = document.getElementById(hId);
        const display = document.getElementById(dId);
        if (hidden && display) {
            const val = hidden.value.trim();
            display.textContent = val || 'None';
        }
    });

    const benHidden = document.getElementById('beneficiaries-hidden');
    const benDisplay = document.getElementById('selected-beneficiaries');
    if (benHidden && benDisplay) {
        try {
            const data = JSON.parse(benHidden.value || '[]');
            const count = data.length;
            benDisplay.textContent = count > 0 ? `${count} type(s) selected` : 'None';
        } catch (e) {
            benDisplay.textContent = 'None';
        }
    }
}

let originalValues = {};

function checkFormChanges() {
    const currentValues = {
        activity_name: document.querySelector('[name="activity_name"]').value.trim(),
        implementation_start: document.querySelector('[name="implementation_start"]').value.trim(),
        implementation_end: document.querySelector('[name="implementation_end"]').value.trim(),
        type_agenda: document.getElementById('type-hidden')?.value.trim() || '',
        sdg_goals: document.getElementById('sdg-hidden')?.value.trim() || '',
        offices: document.getElementById('offices-hidden')?.value.trim() || '',
        programs: document.getElementById('programs-hidden')?.value.trim() || '',
        beneficiaries: document.getElementById('beneficiaries-hidden')?.value.trim() || '[]'
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
        activity_name: document.querySelector('[name="activity_name"]').value.trim(),
        implementation_start: document.querySelector('[name="implementation_start"]').value.trim(),
        implementation_end: document.querySelector('[name="implementation_end"]').value.trim(),
        type_agenda: document.getElementById('type-hidden').value.trim(),
        sdg_goals: document.getElementById('sdg-hidden').value.trim(),
        offices: document.getElementById('offices-hidden').value.trim(),
        programs: document.getElementById('programs-hidden').value.trim(),
        beneficiaries: document.getElementById('beneficiaries-hidden').value.trim() || '[]'
    };

    syncPreviews();
    setTimeout(syncPreviews, 100);
    setTimeout(syncPreviews, 500);

    document.querySelectorAll('input, select').forEach(el => {
        ['input', 'change'].forEach(evt => el.addEventListener(evt, checkFormChanges));
    });

    checkFormChanges();
});
    </script>

</body>
</html>