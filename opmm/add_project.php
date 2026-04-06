<?php
session_start();
// Start session: used for authentication and temporarily storing form data (e.g., pending activity before confirmation)

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php");
    exit;
}

require_once '../config/db.php';
// Load database connection (PDO instance)

$program_id = $_GET['program_id'] ?? null;
if (!$program_id || !is_numeric($program_id)) {
    header("Location: list.php");
    exit;
}

// Prepare SQL query safely (prevents SQL injection)
$stmt = $pdo->prepare("
    SELECT title, duration_start, duration_end,
           type_of_extension_service_agenda, sdg_goals,
           offices_involved, programs_involved, beneficiaries_json
    FROM program_entries WHERE id = ?
");
$stmt->execute([$program_id]);
$parent = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$parent || !$parent['duration_start']) {
    die("Parent program not found or missing start date.");
}

// Calculate latest allowed project end date: Dec 31 of (start year + 2)
$programStart = $parent['duration_start'];
$startYear = date('Y', strtotime($programStart));
$threeYearEnd = ($startYear + 2) . '-12-31';

// Hardcoded full lists (reference/whitelist)
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
$show_confirmation = false;

// Determine which data to use for repopulation
$formData = $_POST; // default: fresh POST

// If user cancelled from confirmation → load from session
if (isset($_GET['cancel']) && $_GET['cancel'] === '1' && isset($_SESSION['pending_project'])) {
    $formData = $_SESSION['pending_project'];
    $formData['project_title']       = $formData['project_title'] ?? '';
    $formData['type_agenda']         = $formData['type_of_extension_service_agenda'] ?? '';
    $formData['sdg_goals']           = $formData['sdg_goals'] ?? '';
    $formData['offices']             = $formData['offices_involved'] ?? '';
    $formData['programs']            = $formData['programs_involved'] ?? '';
    $formData['beneficiaries']       = $formData['beneficiaries_json'] ?? '[]';
    $formData['implementation_start'] = $formData['implementation_start'] ?? '';
    $formData['implementation_end']   = $formData['implementation_end'] ?? '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Final save: user confirmed activity, now insert into database
if (isset($_POST['confirm']) && isset($_SESSION['pending_project'])) {
        $d = $_SESSION['pending_project'];

        try {
            // Prepare SQL query safely (prevents SQL injection)
$stmt = $pdo->prepare("
                INSERT INTO project_entries (
                    program_id, project_title, implementation_start, implementation_end,
                    type_of_extension_service_agenda, sdg_goals,
                    offices_involved, programs_involved, beneficiaries_json
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $d['program_id'], $d['project_title'], $d['implementation_start'], $d['implementation_end'],
                $d['type_of_extension_service_agenda'], $d['sdg_goals'],
                $d['offices_involved'], $d['programs_involved'], $d['beneficiaries_json']
            ]);

            unset($_SESSION['pending_project']);
            header("Location: view.php?id={$d['program_id']}&success=added");
            exit;
        } catch (PDOException $e) {
            $error = 'Failed to save project: ' . $e->getMessage();
        }
    } else {
        $project_title = trim($_POST['project_title'] ?? '');
        $impl_start    = $_POST['implementation_start'] ?? '';
        $impl_end      = $_POST['implementation_end'] ?? '';
        $type_agenda   = trim($_POST['type_agenda'] ?? '');
        $sdg_goals     = trim($_POST['sdg_goals'] ?? '');
        $offices       = trim($_POST['offices'] ?? '');
        $programs      = trim($_POST['programs'] ?? '');
        $beneficiaries = trim($_POST['beneficiaries'] ?? '[]');

        $benefData = json_decode($beneficiaries, true) ?? [];
// Decode beneficiaries JSON into array for validation and counting

        $totalBenef = 0;
// Calculate total beneficiaries (male + female) to ensure at least one valid entry
        foreach ($benefData as $b) {
            $totalBenef += ($b['male'] ?? 0) + ($b['female'] ?? 0);
        }

        if (empty($project_title) || empty($impl_start) || empty($impl_end) ||
            empty($type_agenda) || empty($sdg_goals) || empty($offices) ||
            empty($programs) || $beneficiaries === '[]' || $totalBenef === 0) {
            $error = 'Please fill all required fields (at least one beneficiary with total > 0).';
        } elseif (strtotime($impl_end) < strtotime($impl_start)) {
            $error = 'End date cannot be before start date.';
        } elseif (strtotime($impl_start) < strtotime($programStart) || strtotime($impl_end) > strtotime($threeYearEnd)) {
            $error = "Project implementation must be within the first 3 full calendar years of the program (up to December 31, " . ($startYear + 2) . ").";
        } else {
            $_SESSION['pending_project'] = [
                'program_id' => $program_id,
                'project_title' => $project_title,
                'implementation_start' => $impl_start,
                'implementation_end' => $impl_end,
                'type_of_extension_service_agenda' => $type_agenda,
                'sdg_goals' => $sdg_goals,
                'offices_involved' => $offices,
                'programs_involved' => $programs,
                'beneficiaries_json' => $beneficiaries
            ];
            $show_confirmation = true;
        }
    }
}

$nav_links = [
    ['url' => 'index.php',          'label' => 'Home',    'active' => false],
    ['url' => '/opmm/view.php?id=' . $program_id, 'label' => 'Program', 'active' => false],
];
?>

<?php include '../includes/header.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Project</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>

    <main class="dashboard-content add-program-page">
        <h1>Add New Project</h1>

        <?php if ($error): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <?php if ($show_confirmation): $d = $_SESSION['pending_project']; ?>
            <div class="confirmation-box">
                <h2>Confirm Project Details</h2>

                <div class="confirmation-grid">
                    <div class="confirm-item">
                        <strong>Project Title</strong>
                        <p><?= htmlspecialchars($d['project_title']) ?></p>
                    </div>

                    <div class="confirm-item">
                        <strong>Implementation Start</strong>
                        <p><?= htmlspecialchars(date('M d, Y', strtotime($d['implementation_start']))) ?></p>
                    </div>

                    <div class="confirm-item">
                        <strong>Implementation End</strong>
                        <p><?= htmlspecialchars(date('M d, Y', strtotime($d['implementation_end']))) ?></p>
                    </div>

                    <div class="confirm-item">
                        <strong>Type of Extension Service Agenda</strong>
                        <p><?= htmlspecialchars($d['type_of_extension_service_agenda']) ?: 'None' ?></p>
                    </div>

                    <div class="confirm-item">
                        <strong>SDG Goals</strong>
                        <p><?= htmlspecialchars($d['sdg_goals']) ?: 'None' ?></p>
                    </div>

                    <div class="confirm-item">
                        <strong>Offices Involved</strong>
                        <p><?= htmlspecialchars($d['offices_involved']) ?: 'None' ?></p>
                    </div>

                    <div class="confirm-item">
                        <strong>Programs Involved</strong>
                        <p><?= htmlspecialchars($d['programs_involved']) ?: 'None' ?></p>
                    </div>

                    <div class="confirm-item full-span">
                        <strong>Beneficiaries</strong>
                        <p>
                            <?php
                            $benefs = json_decode($d['beneficiaries_json'] ?? '[]', true);
                            if (is_array($benefs) && !empty($benefs)) {
                                $parts = [];
                                $total = 0;
                                foreach ($benefs as $b) {
                                    $type = htmlspecialchars($b['type'] ?? 'Unnamed');
                                    $m = (int)($b['male'] ?? 0);
                                    $f = (int)($b['female'] ?? 0);
                                    $line = $type;
                                    if ($m > 0 || $f > 0) $line .= ": $m M, $f F";
                                    $parts[] = $line;
                                    $total += $m + $f;
                                }
                                echo implode(' | ', $parts);
                                if ($total > 0) echo " | Total: $total";
                            } else {
                                echo 'None';
                            }
                            ?>
                        </p>
                    </div>
                </div>

                <div class="confirm-actions">
                    <form method="POST" style="display:inline;">
                        <button type="submit" name="confirm">Confirm & Save</button>
                    </form>
                    <a href="add_project.php?program_id=<?= $program_id ?>&cancel=1" class="cancel-link">Cancel</a>
                </div>
            </div>
        <?php else: ?>
            <form method="POST" class="program-form">
                <input type="hidden" name="program_id" value="<?= htmlspecialchars($program_id) ?>">

                <div class="form-group">
                    <label for="project_title">Project Title *</label>
                    <input type="text" id="project_title" name="project_title" 
                           value="<?= htmlspecialchars($formData['project_title'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label>Type of Extension Service Agenda *</label>
                    <button type="button" onclick="openModal('type-modal')">Select Types</button>
                    <div id="selected-types" class="compact-preview">
                        <?= htmlspecialchars($formData['type_agenda'] ?? 'None') ?>
                    </div>
                    <input type="hidden" name="type_agenda" id="type-hidden" 
                           value="<?= htmlspecialchars($formData['type_agenda'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label>Sustainable Development Goals *</label>
                    <button type="button" onclick="openModal('sdg-modal')">Select SDGs</button>
                    <div id="selected-sdgs" class="compact-preview">
                        <?= htmlspecialchars($formData['sdg_goals'] ?? 'None') ?>
                    </div>
                    <input type="hidden" name="sdg_goals" id="sdg-hidden" 
                           value="<?= htmlspecialchars($formData['sdg_goals'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label>Offices Involved *</label>
                    <button type="button" onclick="openModal('offices-modal')">Select Offices</button>
                    <div id="selected-offices" class="compact-preview">
                        <?= htmlspecialchars($formData['offices'] ?? 'None') ?>
                    </div>
                    <input type="hidden" name="offices" id="offices-hidden" 
                           value="<?= htmlspecialchars($formData['offices'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label>Programs Involved *</label>
                    <button type="button" onclick="openModal('programs-modal')">Select Programs</button>
                    <div id="selected-programs" class="compact-preview">
                        <?= htmlspecialchars($formData['programs'] ?? 'None') ?>
                    </div>
                    <input type="hidden" name="programs" id="programs-hidden" 
                           value="<?= htmlspecialchars($formData['programs'] ?? '') ?>">
                </div>

                <div class="form-group full-span">
                    <label>Beneficiaries *</label>
                    <button type="button" onclick="openModal('beneficiaries-modal')">Select Beneficiaries</button>
                    <div id="selected-beneficiaries" class="compact-preview">
                        <?= htmlspecialchars($formData['beneficiaries_preview'] ?? 'None') ?>
                    </div>
                    <input type="hidden" name="beneficiaries" id="beneficiaries-hidden" 
                           value="<?= htmlspecialchars($formData['beneficiaries'] ?? '[]') ?>">
                </div>

                <div class="form-group full-span">
                    <label>Implementation Period * (first 3 full calendar years of program)</label>
                    <div class="date-group">
                        <input type="date" name="implementation_start"
                               value="<?= htmlspecialchars($formData['implementation_start'] ?? '') ?>"
                               min="<?= htmlspecialchars($programStart) ?>"
                               max="<?= htmlspecialchars($threeYearEnd) ?>"
                               required>
                        <span>to</span>
                        <input type="date" name="implementation_end"
                               value="<?= htmlspecialchars($formData['implementation_end'] ?? '') ?>"
                               min="<?= htmlspecialchars($programStart) ?>"
                               max="<?= htmlspecialchars($threeYearEnd) ?>"
                               required>
                    </div>
                    <small class="hint">
                        Must be between <?= htmlspecialchars(date('M d, Y', strtotime($programStart))) ?> 
                        and December 31, <?= $startYear + 2 ?>
                    </small>
                </div>

                <div class="full-span" style="text-align: center; margin-top: 16px;">
                    <button type="submit">Review & Add</button>
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
                <?php
                $parentTypes = array_map('trim', explode(',', $parent['type_of_extension_service_agenda'] ?? ''));
                foreach ($fullTypeOptions as $opt):
                    if (in_array($opt, $parentTypes)):
                ?>
                    <label class="modal-checkbox-label">
                        <span><?= htmlspecialchars($opt) ?></span>
                        <input type="checkbox" value="<?= htmlspecialchars($opt) ?>">
                    </label>
                <?php
                    endif;
                endforeach;
                if (empty($parentTypes) || empty(array_intersect($fullTypeOptions, $parentTypes))):
                ?>
                    <p>No types available from parent program.</p>
                <?php endif; ?>
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
                <?php
                $parentSdgStr = $parent['sdg_goals'] ?? '';
                $parentSdgsLower = strtolower($parentSdgStr);

                $anyShown = false;
                foreach ($fullSdgOptions as $opt):
                    if (stripos($parentSdgStr, $opt) !== false):
                        $anyShown = true;
                ?>
                    <label class="modal-checkbox-label">
                        <span><?= htmlspecialchars($opt) ?></span>
                        <input type="checkbox" value="<?= htmlspecialchars($opt) ?>">
                    </label>
                <?php
                    endif;
                endforeach;

                if (!$anyShown):
                ?>
                    <p>No SDGs available from parent program.</p>
                <?php endif; ?>
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

    <!-- Offices Modal -->
    <div id="offices-modal" class="modal-overlay">
        <div class="modal-box">
            <span class="close-modal" onclick="closeModal('offices-modal')">×</span>
            <h2>Select Offices Involved</h2>
            <div style="max-height: 400px; overflow-y: auto; padding: 12px;">
                <?php
                $parentOffices = array_map('trim', explode(',', $parent['offices_involved'] ?? ''));
                foreach ($parentOffices as $opt):
                    if ($opt):
                ?>
                    <label class="modal-checkbox-label">
                        <span><?= htmlspecialchars($opt) ?></span>
                        <input type="checkbox" value="<?= htmlspecialchars($opt) ?>">
                    </label>
                <?php
                    endif;
                endforeach;
                if (empty($parentOffices)):
                ?>
                    <p>No offices available from parent program.</p>
                <?php endif; ?>
            </div>
            <div class="modal-actions">
                <button onclick="saveModalSelections('offices')" 
                        style="padding: 10px 20px; background: #c8102e; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 500;">
                    Save
                </button>
                <button onclick="closeModal('offices-modal')" 
                        style="padding: 10px 20px; background: #6b7280; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 500;">
                    Cancel
                </button>
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
                $parentPrograms = array_map('trim', explode(',', $parent['programs_involved'] ?? ''));
                foreach ($parentPrograms as $opt):
                    if ($opt):
                ?>
                    <label class="modal-checkbox-label">
                        <span><?= htmlspecialchars($opt) ?></span>
                        <input type="checkbox" value="<?= htmlspecialchars($opt) ?>">
                    </label>
                <?php
                    endif;
                endforeach;
                if (empty($parentPrograms)):
                ?>
                    <p>No programs available from parent program.</p>
                <?php endif; ?>
            </div>
            <div class="modal-actions">
                <button onclick="saveModalSelections('programs')" 
                        style="padding: 10px 20px; background: #c8102e; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 500;">
                    Save
                </button>
                <button onclick="closeModal('programs-modal')" 
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
            <h2>Select Beneficiaries from Program</h2>
            <div id="beneficiary-rows" style="margin-bottom: 16px;"></div>
            <div class="modal-actions" style="margin-top: 20px; display: flex; gap: 12px; justify-content: flex-end;">
                <button onclick="saveBeneficiaries()" style="background: #c8102e">Save</button>
                <button onclick="closeModal('beneficiaries-modal')">Cancel</button>
            </div>
        </div>
    </div>

    <script>
let beneficiariesData = [];

// Opens modal UI and restores previously selected values
function openModal(modalId) {
    document.getElementById(modalId).classList.add('active');
    document.body.classList.add('modal-open');

    const type = modalId.replace('-modal', '');
    if (['type','sdg','offices','programs'].includes(type)) {
        setTimeout(() => restoreCheckedState(type), 100);
    } else if (type === 'beneficiaries') {
        setTimeout(loadBeneficiaries, 100);
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
    syncPreviews();
}

// Restore previously selected values when reopening modal (prevents losing selections)
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

// Load beneficiaries from parent project and allow user to select subset for this activity
function loadBeneficiaries() {
    const rowsDiv = document.getElementById('beneficiary-rows');
    rowsDiv.innerHTML = '';

    const currentJson = document.getElementById('beneficiaries-hidden').value || '[]';
    let currentEntries = [];
    try { currentEntries = JSON.parse(currentJson); } catch (e) {}

    const parentJson = <?= json_encode($parent['beneficiaries_json'] ?? '[]') ?>;
    let parentEntries = [];
    try { parentEntries = JSON.parse(parentJson); } catch (e) {}

    if (!Array.isArray(parentEntries) || parentEntries.length === 0) {
        rowsDiv.innerHTML = '<p style="color:#6b7280; text-align:center;">No beneficiaries defined in parent program.</p>';
        return;
    }

    beneficiariesData = parentEntries.map((e, i) => {
        const entryType = (e.type || 'Unnamed').trim();
        const isSelected = currentEntries.some(c => {
            return (c.type || '').trim().toLowerCase() === entryType.toLowerCase();
        });

        return {
            type: entryType,
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

// Save selected beneficiaries as JSON string into hidden input for backend processing
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
    syncPreviews();
}

// Sync UI preview labels with hidden input values (ensures user sees selected data)
function syncPreviews() {
    ['type', 'sdg', 'offices', 'programs'].forEach(type => {
        const hidden = document.getElementById(type + '-hidden');
        const display = document.getElementById('selected-' + type);
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

window.addEventListener('load', function() {
    syncPreviews();
    setTimeout(syncPreviews, 100);
});
    </script>

</body>
</html>