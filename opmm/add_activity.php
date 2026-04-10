<?php
session_start();
// Start session: used for authentication and temporarily storing form data (e.g., pending activity before confirmation)

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php");
    exit;
}

require_once '../config/db.php';
// Load database connection (PDO instance)

$project_id = $_GET['project_id'] ?? null;
// Get project ID from URL to link this activity to its parent project
if (!$project_id || !is_numeric($project_id)) {
    header("Location: list.php");
    exit;
}

// Fetch parent project
// Prepare SQL query safely (prevents SQL injection)
$stmt = $pdo->prepare("
    SELECT project_title, implementation_start, implementation_end,
           type_of_extension_service_agenda, sdg_goals,
           offices_involved, programs_involved, beneficiaries_json
    FROM project_entries WHERE id = ?
");
$stmt->execute([$project_id]);
$project = $stmt->fetch(PDO::FETCH_ASSOC);
// Fetch parent project details (used to restrict activity inputs like dates and selectable options)

if (!$project) {
    die("Parent project not found.");
}

$projectStart = $project['implementation_start'];
$projectEnd   = $project['implementation_end'];

// Fetch grandparent program to enforce 3-year limit
$progStmt = $pdo->prepare("
    SELECT duration_start
    FROM program_entries 
    WHERE id = (SELECT program_id FROM project_entries WHERE id = ?)
");
$progStmt->execute([$project_id]);
$program = $progStmt->fetch(PDO::FETCH_ASSOC);

if (!$program || !$program['duration_start']) {
    die("Grandparent program not found or missing start date.");
}

$programStart = $program['duration_start'];
$startYear = date('Y', strtotime($programStart));
// Compute program 3-year limit (activities must fall within first 3 years of program)
$program3YearEnd = ($startYear + 2) . '-12-31';

// Effective max end date = earlier of project end and program 3-year end
// Determine maximum allowed activity end date (cannot exceed project end OR program 3-year limit)
$effectiveMaxEnd = min(strtotime($projectEnd), strtotime($program3YearEnd));
// Determine maximum allowed activity end date (cannot exceed project end OR program 3-year limit)
$effectiveMaxEnd = date('Y-m-d', $effectiveMaxEnd);

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
if (isset($_GET['cancel']) && $_GET['cancel'] === '1' && isset($_SESSION['pending_activity'])) {
    $formData = $_SESSION['pending_activity'];
    $formData['activity_name']       = $formData['activity_name'] ?? '';
    $formData['type_agenda']         = $formData['type_of_extension_service_agenda'] ?? '';
    $formData['sdg_goals']           = $formData['sdg_goals'] ?? '';
    $formData['offices']             = $formData['offices_involved'] ?? '';
    $formData['programs']            = $formData['programs_involved'] ?? '';
    $formData['beneficiaries']       = $formData['beneficiaries_json'] ?? '[]';
    $formData['implementation_start'] = $formData['implementation_start'] ?? '';
    $formData['implementation_end']   = $formData['implementation_end'] ?? '';
}

// Handle form submission (before confirmation step)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['confirm'])) {
    $activity_name   = trim($_POST['activity_name'] ?? '');
    $type_agenda     = trim($_POST['type_agenda'] ?? '');
    $sdg_goals       = trim($_POST['sdg_goals'] ?? '');
    $offices         = trim($_POST['offices'] ?? '');
    $programs        = trim($_POST['programs'] ?? '');
    $beneficiaries   = trim($_POST['beneficiaries'] ?? '[]');
    $impl_start      = $_POST['implementation_start'] ?? '';
    $impl_end        = $_POST['implementation_end'] ?? '';

    $benefData = json_decode($beneficiaries, true) ?? [];
// Decode beneficiaries JSON into array for validation and counting

    $totalBenef = 0;
// Calculate total beneficiaries (male + female) to ensure at least one valid entry
    foreach ($benefData as $b) {
        $totalBenef += ($b['male'] ?? 0) + ($b['female'] ?? 0);
    }

    if (empty($activity_name) || empty($impl_start) || empty($impl_end) ||
        empty($type_agenda) || empty($sdg_goals) ||
        empty($offices) || empty($programs) || $beneficiaries === '[]' || $totalBenef === 0) {
        $error = 'Please fill all required fields (at least one beneficiary with total > 0).';
    } elseif (strtotime($impl_end) < strtotime($impl_start)) {
        $error = 'End date cannot be before start date.';
    } elseif (strtotime($impl_start) < strtotime($projectStart) || 
              strtotime($impl_end) > strtotime($effectiveMaxEnd)) {
        $error = "Activity must be completed within both the parent project's duration (" .
                 date('M d, Y', strtotime($projectStart)) . " – " .
                 date('M d, Y', strtotime($projectEnd)) . ") " .
                 "and the first 3 full calendar years of the program (up to " .
                 date('M d, Y', strtotime($effectiveMaxEnd)) . ").";
    } else {
        // Store validated activity temporarily in session for confirmation page
$_SESSION['pending_activity'] = [
            'project_id'                  => $project_id,
            'activity_name'               => $activity_name,
            'implementation_start'        => $impl_start,
            'implementation_end'          => $impl_end,
            'type_of_extension_service_agenda' => $type_agenda,
            'sdg_goals'                   => $sdg_goals,
            'offices_involved'            => $offices,
            'programs_involved'           => $programs,
            'beneficiaries_json'          => $beneficiaries
        ];
        $show_confirmation = true;
    }
}

// Final save: user confirmed activity, now insert into database
if (isset($_POST['confirm']) && isset($_SESSION['pending_activity'])) {
    $d = $_SESSION['pending_activity'];

    try {
        // Prepare SQL query safely (prevents SQL injection)
$stmt = $pdo->prepare("
            INSERT INTO activity_entries (
                project_id, activity_name, implementation_start, implementation_end,
                type_of_extension_service_agenda, sdg_goals,
                offices_involved, programs_involved, beneficiaries_json
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $d['project_id'], $d['activity_name'], $d['implementation_start'], $d['implementation_end'],
            $d['type_of_extension_service_agenda'], $d['sdg_goals'],
            $d['offices_involved'], $d['programs_involved'], $d['beneficiaries_json']
        ]);

        unset($_SESSION['pending_activity']);
        header("Location: view_project.php?id={$d['project_id']}&success=added");
        exit;
    } catch (PDOException $e) {
        $error = 'Failed to save activity: ' . $e->getMessage();
    }
}

$nav_links = [
    ['url' => 'index.php', 'label' => 'Home', 'active' => false],
    ['url' => '/opmm/view_project.php?id=' . $project_id, 'label' => 'Project', 'active' => false],
    ['url' => '/opmm/list_proposals.php', 'label' => 'Proposals', 'active' => false],
];
?>

<?php include '../includes/header.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Activity</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        /* Review & Add button - exact match to add_project */
        #add-btn {
            background: #c8102e;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: background 0.2s;
        }
        #add-btn:hover {
            background: #a50d24;
        }

        .hint {
            color: #6b7280;
            font-size: 0.85rem;
            margin-top: 4px;
            display: block;
        }

        /* Confirmation modal - matches add_project exactly */
        .confirmation-box {
            max-width: 1200px;
            margin: 32px auto;
            padding: 24px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.1);
        }

        .confirmation-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .confirm-item {
            background: #f9fafb;
            padding: 16px;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
        }

        .confirm-item.full-span {
            grid-column: 1 / -1;
        }

        .confirm-item strong {
            display: block;
            margin-bottom: 8px;
            color: #1f2937;
        }

        .confirm-actions {
            text-align: center;
            margin-top: 24px;
        }

        .confirm-actions button,
        .confirm-actions .cancel-link {
            padding: 12px 24px;
            font-size: 1rem;
            margin: 0 12px;
            border-radius: 6px;
        }

        .confirm-actions button {
            background: #c8102e;
            color: white;
            border: none;
            cursor: pointer;
        }

        .confirm-actions button:hover {
            background: #a50d24;
        }

        .cancel-link {
            background: #6b7280;
            color: white;
            text-decoration: none;
            display: inline-block;
        }

        .cancel-link:hover {
            background: #4b5563;
        }

        /* Modal Save buttons - red */
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
    </style>
</head>
<body>

    <main class="dashboard-content">
        <h1>Add New Activity</h1>

        <?php if ($error): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <?php if ($show_confirmation): $d = $_SESSION['pending_activity']; ?>
            <div class="confirmation-box">
                <h2>Confirm Activity Details</h2>

                <div class="confirmation-grid">
                    <div class="confirm-item">
                        <strong>Activity Name</strong>
                        <p><?= htmlspecialchars($d['activity_name']) ?></p>
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
                        <p><?= htmlspecialchars($d['type_of_extension_service_agenda'] ?: 'None') ?></p>
                    </div>

                    <div class="confirm-item">
                        <strong>SDG Goals</strong>
                        <p><?= htmlspecialchars($d['sdg_goals'] ?: 'None') ?></p>
                    </div>

                    <div class="confirm-item">
                        <strong>Offices Involved</strong>
                        <p><?= htmlspecialchars($d['offices_involved'] ?: 'None') ?></p>
                    </div>

                    <div class="confirm-item">
                        <strong>Programs Involved</strong>
                        <p><?= htmlspecialchars($d['programs_involved'] ?: 'None') ?></p>
                    </div>

                    <div class="confirm-item">
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
                    <a href="add_activity.php?project_id=<?= $project_id ?>&cancel=1" class="cancel-link">Cancel</a>
                </div>
            </div>
        <?php else: ?>
            <form method="POST" class="program-form" id="add-activity-form">
                <input type="hidden" name="project_id" value="<?= htmlspecialchars($project_id) ?>">

                <div class="form-group">
                    <label for="activity_name">Activity Name *</label>
                    <input type="text" id="activity_name" name="activity_name" 
                           value="<?= htmlspecialchars($formData['activity_name'] ?? '') ?>" required>
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
                    <button type="button" onclick="openBeneficiariesModal()">Manage Beneficiaries</button>
                    <div id="selected-beneficiaries" class="compact-preview">
                        <?= htmlspecialchars($formData['beneficiaries_preview'] ?? 'None') ?>
                    </div>
                    <input type="hidden" name="beneficiaries" id="beneficiaries-hidden" 
                           value="<?= htmlspecialchars($formData['beneficiaries'] ?? '[]') ?>">
                </div>

                <div class="form-group full-span">
                    <label>Implementation Duration * (within project & first 3 years of program)</label>
                    <div class="date-group">
                        <input type="date" name="implementation_start"
                               value="<?= htmlspecialchars($formData['implementation_start'] ?? '') ?>"
                               min="<?= htmlspecialchars($projectStart) ?>"
                               max="<?= htmlspecialchars($effectiveMaxEnd) ?>"
                               required>
                        <span>to</span>
                        <input type="date" name="implementation_end"
                               value="<?= htmlspecialchars($formData['implementation_end'] ?? '') ?>"
                               min="<?= htmlspecialchars($projectStart) ?>"
                               max="<?= htmlspecialchars($effectiveMaxEnd) ?>"
                               required>
                    </div>
                    <small class="hint">
                        Must be within parent project (<?= htmlspecialchars(date('M d, Y', strtotime($projectStart))) ?> – 
                        <?= htmlspecialchars(date('M d, Y', strtotime($projectEnd))) ?>) 
                        and first 3 full calendar years of program (up to <?= htmlspecialchars(date('M d, Y', strtotime($effectiveMaxEnd))) ?>).
                    </small>
                </div>

                <div class="full-span" style="text-align: center; margin-top: 16px;">
                    <button type="submit" id="add-btn"><b>Review & Add</b></button>
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
                $parentTypeStr = $project['type_of_extension_service_agenda'] ?? '';
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
                $parentSdgStr = $project['sdg_goals'] ?? '';
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
                if ($project['offices_involved']) {
                    $offices = explode(', ', $project['offices_involved']);
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
                if ($project['programs_involved']) {
                    $programs = explode(', ', $project['programs_involved']);
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

function openBeneficiariesModal() {
    openModal('beneficiaries-modal');
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

    if (hidden) hidden.value = values.join(', ');
    if (display) display.textContent = values.length ? values.join(', ') : 'None';

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
    try { currentEntries = JSON.parse(currentJson); } catch (e) { console.error("JSON parse error:", e); }

    const parentJson = <?= json_encode($project['beneficiaries_json'] ?? '[]') ?>;
    let parentEntries = [];
    try { parentEntries = JSON.parse(parentJson); } catch (e) { console.error("Parent JSON parse error:", e); }

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

// Save selected beneficiaries as JSON string into hidden input for backend processing
function saveBeneficiaries() {
    const selected = beneficiariesData.filter(b => b.selected).map(b => ({
        type: b.type,
        male: b.male,
        female: b.female
    }));

    document.getElementById('beneficiaries-hidden').value = JSON.stringify(selected);

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
            benDisplay.textContent = data.length > 0 ? `${data.length} type(s) selected` : 'None';
        } catch (e) {
            benDisplay.textContent = 'None';
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    syncPreviews();
    setTimeout(syncPreviews, 100);
    setTimeout(syncPreviews, 500);
});
    </script>

</body>
</html>