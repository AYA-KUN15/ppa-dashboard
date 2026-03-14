<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php");
    exit;
}

require_once '../config/db.php';

$program_id = $_GET['program_id'] ?? null;
if (!$program_id || !is_numeric($program_id)) {
    header("Location: list.php");
    exit;
}

$stmt = $pdo->prepare("
    SELECT title, duration_start, duration_end,
           type_of_extension_service_agenda, sdg_goals,
           offices_involved, programs_involved, beneficiaries_json
    FROM program_entries WHERE id = ?
");
$stmt->execute([$program_id]);
$parent = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$parent) {
    die("Parent program not found.");
}

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['confirm']) && isset($_SESSION['pending_project'])) {
        $d = $_SESSION['pending_project'];

        try {
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

        if (empty($project_title) || empty($impl_start) || empty($impl_end) ||
            empty($type_agenda) || empty($sdg_goals) || empty($offices) ||
            empty($programs) || $beneficiaries === '[]') {
            $error = 'Please fill all required fields.';
        } elseif (strtotime($impl_end) < strtotime($impl_start)) {
            $error = 'End date cannot be before start date.';
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
                        <p><?= htmlspecialchars($d['implementation_start']) ?></p>
                    </div>

                    <div class="confirm-item">
                        <strong>Implementation End</strong>
                        <p><?= htmlspecialchars($d['implementation_end']) ?></p>
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
                                if ($total > 0) echo " | <strong>Total:</strong> $total";
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
                    <a href="add_project.php?program_id=<?= $program_id ?>" class="cancel-link">Cancel</a>
                </div>
            </div>
        <?php else: ?>
            <form method="POST" class="program-form">
                <input type="hidden" name="program_id" value="<?= htmlspecialchars($program_id) ?>">

                <div class="form-group">
    <label for="project_title">Project Title *</label>
    <input type="text" id="project_title" name="project_title" required>
</div>

                <div class="form-group">
                    <label>Type of Extension Service Agenda *</label>
                    <button type="button" onclick="openModal('type-modal')">Select Types</button>
                    <div id="selected-types" class="compact-preview">None</div>
                    <input type="hidden" name="type_agenda" id="type-hidden">
                </div>

                <div class="form-group">
                    <label>Sustainable Development Goals *</label>
                    <button type="button" onclick="openModal('sdg-modal')">Select SDGs</button>
                    <div id="selected-sdgs" class="compact-preview">None</div>
                    <input type="hidden" name="sdg_goals" id="sdg-hidden">
                </div>

                <div class="form-group">
                    <label>Offices Involved *</label>
                    <button type="button" onclick="openModal('offices-modal')">Select Offices</button>
                    <div id="selected-offices" class="compact-preview">None</div>
                    <input type="hidden" name="offices" id="offices-hidden">
                </div>

                <div class="form-group">
                    <label>Programs Involved *</label>
                    <button type="button" onclick="openModal('programs-modal')">Select Programs</button>
                    <div id="selected-programs" class="compact-preview">None</div>
                    <input type="hidden" name="programs" id="programs-hidden">
                </div>

                <div class="form-group full-span">
                    <label>Beneficiaries *</label>
                    <button type="button" onclick="openModal('beneficiaries-modal')">Manage Beneficiaries</button>
                    <div id="selected-beneficiaries" class="compact-preview">None</div>
                    <input type="hidden" name="beneficiaries" id="beneficiaries-hidden" value="[]">
                </div>

                <div class="form-group full-span">
                    <label>Implementation Period * (within program duration)</label>
                    <div class="date-group">
                        <input type="date" name="implementation_start"
                               min="<?= htmlspecialchars($parent['duration_start']) ?>"
                               max="<?= htmlspecialchars($parent['duration_end']) ?>"
                               required>
                        <span>to</span>
                        <input type="date" name="implementation_end"
                               min="<?= htmlspecialchars($parent['duration_start']) ?>"
                               max="<?= htmlspecialchars($parent['duration_end']) ?>"
                               required>
                    </div>
                    <small class="hint">
                        Must be within parent program: <?= htmlspecialchars(date('M d, Y', strtotime($parent['duration_start']))) ?> – 
                        <?= htmlspecialchars(date('M d, Y', strtotime($parent['duration_end']))) ?>
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
                // Check if the full goal name (with commas) appears in parent string
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
        <input type="text" 
               placeholder="e.g., Farmers, Students, PWDs" 
               value="${type}" 
               class="beneficiary-type"
               required
               style="flex: 2; min-width: 180px;">

        <input type="number" 
               placeholder="Male" 
               value="${male}" 
               min="0" 
               class="beneficiary-male"
               required
               style="flex: 1; max-width: 80px;">

        <input type="number" 
               placeholder="Female" 
               value="${female}" 
               min="0" 
               class="beneficiary-female"
               required
               style="flex: 1; max-width: 80px;">

        <button type="button" 
                onclick="this.closest('.beneficiary-row').remove();"
                style="padding: 6px 10px; background: #c8102e; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 0.9rem;">
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
    document.getElementById('beneficiaries-hidden').value = json;

    let summary = '';
    let total = 0;

    data.forEach(b => {
        summary += `${b.type}: ${b.male} M, ${b.female} F | `;
        total += b.male + b.female;
    });

    summary += total > 0 ? `Total: ${total}` : 'None';

    document.getElementById('selected-beneficiaries').textContent = summary.trim();

    closeModal('beneficiaries-modal');
}

window.addEventListener('load', function() {
    ['type', 'sdg', 'offices', 'programs'].forEach(type => {
        const hidden = document.getElementById(type + '-hidden');
        const display = document.getElementById('selected-' + type);
        if (hidden && display) {
            display.textContent = hidden.value.trim() ? hidden.value : 'None';
        }
    });

    const json = document.getElementById('beneficiaries-hidden')?.value || '[]';
    try {
        const data = JSON.parse(json);
        if (data.length === 0) {
            document.getElementById('selected-beneficiaries').textContent = 'None';
        } else {
            data.forEach(b => addBeneficiaryRow(b.type, b.male, b.female));
            saveBeneficiaries(); // refresh preview
        }
    } catch (e) {
        document.getElementById('selected-beneficiaries').textContent = 'None';
    }
});
    </script>
</body>
</html>