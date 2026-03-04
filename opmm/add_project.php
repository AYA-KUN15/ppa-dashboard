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
    ['url' => '../index.php', 'label' => 'Home',    'active' => false],
    ['url' => 'view.php?id=' . $program_id, 'label' => 'Program', 'active' => false],
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

    <main class="dashboard-content">
        <h1>Add New Project</h1>

        <?php if ($error): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <?php if ($show_confirmation): $d = $_SESSION['pending_project']; ?>
            <div id="confirmation-box" class="confirmation-box">
                <h2>Confirm Project Details</h2>
                <p><strong>Project Title:</strong> <?= htmlspecialchars($d['project_title']) ?></p>
                <p><strong>Implementation Start:</strong> <?= htmlspecialchars($d['implementation_start']) ?></p>
                <p><strong>Implementation End:</strong> <?= htmlspecialchars($d['implementation_end']) ?></p>
                <p><strong>Type of Extension Agenda:</strong> <?= htmlspecialchars($d['type_of_extension_service_agenda'] ?: 'None') ?></p>
                <p><strong>SDG Goals:</strong> <?= htmlspecialchars($d['sdg_goals'] ?: 'None') ?></p>
                <p><strong>Offices Involved:</strong> <?= htmlspecialchars($d['offices_involved'] ?: 'None') ?></p>
                <p><strong>Programs Involved:</strong> <?= htmlspecialchars($d['programs_involved'] ?: 'None') ?></p>
                <p><strong>Beneficiaries:</strong> 
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
                            if ($m > 0 || $f > 0) $line .= ": $m male, $f female";
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

                <div style="margin-top: 24px;">
                    <form method="POST" style="display:inline;">
                        <button type="submit" name="confirm">Confirm & Save</button>
                    </form>
                    <button type="button" onclick="cancelConfirmation()" class="cancel-link">Cancel</button>
                </div>
            </div>
        <?php endif; ?>

        <div id="add-form" style="<?= $show_confirmation ? 'display:none;' : '' ?>">
            <form method="POST">
                <input type="hidden" name="program_id" value="<?= htmlspecialchars($program_id) ?>">

                <label for="project_title">Project Title *</label>
                <input type="text" id="project_title" name="project_title" value="<?= htmlspecialchars($_POST['project_title'] ?? '') ?>" placeholder="Enter project title" required>

                <!-- Type -->
                <label>Type of Extension Service Agenda *</label>
                <button type="button" onclick="openModal('type-modal')">Select Types</button>
                <div id="selected-types" style="margin: 8px 0; min-height: 40px; border: 1px solid #ccc; padding: 8px; border-radius: 4px;"><?= htmlspecialchars($_POST['type_agenda'] ?? 'None selected') ?></div>
                <input type="hidden" name="type_agenda" id="type-hidden" value="<?= htmlspecialchars($_POST['type_agenda'] ?? '') ?>">

                <!-- SDG -->
                <label>Sustainable Development Goals *</label>
                <button type="button" onclick="openModal('sdg-modal')">Select SDGs</button>
                <div id="selected-sdgs" style="margin: 8px 0; min-height: 40px; border: 1px solid #ccc; padding: 8px; border-radius: 4px;"><?= htmlspecialchars($_POST['sdg_goals'] ?? 'None selected') ?></div>
                <input type="hidden" name="sdg_goals" id="sdg-hidden" value="<?= htmlspecialchars($_POST['sdg_goals'] ?? '') ?>">

                <!-- Offices -->
                <label>Offices Involved *</label>
                <button type="button" onclick="openModal('offices-modal')">Select Offices</button>
                <div id="selected-offices" style="margin: 8px 0; min-height: 40px; border: 1px solid #ccc; padding: 8px; border-radius: 4px;"><?= htmlspecialchars($_POST['offices'] ?? 'None selected') ?></div>
                <input type="hidden" name="offices" id="offices-hidden" value="<?= htmlspecialchars($_POST['offices'] ?? '') ?>">

                <!-- Programs -->
                <label>Programs Involved *</label>
                <button type="button" onclick="openModal('programs-modal')">Select Programs</button>
                <div id="selected-programs" style="margin: 8px 0; min-height: 40px; border: 1px solid #ccc; padding: 8px; border-radius: 4px;"><?= htmlspecialchars($_POST['programs'] ?? 'None selected') ?></div>
                <input type="hidden" name="programs" id="programs-hidden" value="<?= htmlspecialchars($_POST['programs'] ?? '') ?>">

                <!-- Beneficiaries -->
                <label>Beneficiaries *</label>
                <button type="button" onclick="openBeneficiariesModal()">Select Beneficiaries</button>
                <div id="selected-beneficiaries" style="margin: 8px 0; min-height: 40px; border: 1px solid #ccc; padding: 8px; border-radius: 4px;">
                    <?= htmlspecialchars($_POST['beneficiaries'] ?? 'None selected') ?>
                </div>
                <input type="hidden" name="beneficiaries" id="beneficiaries-hidden" value="<?= htmlspecialchars($_POST['beneficiaries'] ?? '[]') ?>">

                <!-- Duration Range -->
                <label>Implementation Duration *</label>
<div style="display: flex; gap: 16px; align-items: center; flex-wrap: wrap;">
    <input type="date" 
           name="implementation_start" 
           value="<?= htmlspecialchars($isPost ? $_POST['implementation_start'] : ($edit ? $entry['implementation_start'] : '')) ?>" 
           min="<?= htmlspecialchars($parent['duration_start']) ?>" 
           max="<?= htmlspecialchars($parent['duration_end']) ?>" 
           required 
           style="flex: 1; min-width: 160px; padding: 10px; border: 1px solid #ccc; border-radius: 4px;" />
    <span>to</span>
    <input type="date" 
           name="implementation_end" 
           value="<?= htmlspecialchars($isPost ? $_POST['implementation_end'] : ($edit ? $entry['implementation_end'] : '')) ?>" 
           min="<?= htmlspecialchars($parent['duration_start']) ?>" 
           max="<?= htmlspecialchars($parent['duration_end']) ?>" 
           required 
           style="flex: 1; min-width: 160px; padding: 10px; border: 1px solid #ccc; border-radius: 4px;" />
</div>

                <button type="submit">Review & Add</button>
            </form>
        </div>
    </main>

    <!-- Type Modal -->
    <div id="type-modal" class="modal-overlay">
        <div class="modal-box">
            <span class="close-modal" onclick="closeModal('type-modal')">×</span>
            <h2>Select Type of Extension Service Agenda</h2>
            <div style="max-height: 400px; overflow-y: auto; padding: 12px;">
                <?php
                if ($parent['type_of_extension_service_agenda']) {
                    $types = array_map('trim', explode(',', $parent['type_of_extension_service_agenda']));
                    foreach ($types as $t) {
                        if ($t !== '') {
                            echo '
                            <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px; border-radius: 6px;">
                                ' . htmlspecialchars($t) . '
                                <input type="checkbox" value="' . htmlspecialchars($t) . '">
                            </label>';
                        }
                    }
                } else {
                    echo '<p>No types available from parent program.</p>';
                }
                ?>
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
                if ($parent['sdg_goals']) {
                    $sdgs = array_map('trim', explode(',', $parent['sdg_goals']));
                    foreach ($sdgs as $s) {
                        if ($s !== '') {
                            echo '
                            <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px; border-radius: 6px;">
                                ' . htmlspecialchars($s) . '
                                <input type="checkbox" value="' . htmlspecialchars($s) . '">
                            </label>';
                        }
                    }
                } else {
                    echo '<p>No SDGs available from parent program.</p>';
                }
                ?>
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
                $offices = [];
                if ($parent['offices_involved']) {
                    $offices = array_map('trim', explode(',', $parent['offices_involved']));
                }
                if (!empty($offices)) {
                    foreach ($offices as $o) {
                        if ($o !== '') {
                            echo '
                            <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px; border-radius: 6px;">
                                ' . htmlspecialchars($o) . '
                                <input type="checkbox" value="' . htmlspecialchars($o) . '">
                            </label>';
                        }
                    }
                } else {
                    echo '<p>No offices available from parent program.</p>';
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
                $programs = [];
                if ($parent['programs_involved']) {
                    $programs = array_map('trim', explode(',', $parent['programs_involved']));
                }
                if (!empty($programs)) {
                    foreach ($programs as $p) {
                        if ($p !== '') {
                            echo '
                            <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px; border-radius: 6px;">
                                ' . htmlspecialchars($p) . '
                                <input type="checkbox" value="' . htmlspecialchars($p) . '">
                            </label>';
                        }
                    }
                } else {
                    echo '<p>No programs available from parent program.</p>';
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
            <h2>Select Beneficiaries from Program</h2>
            <div id="beneficiary-rows" style="margin-bottom: 20px;"></div>
            <div class="modal-actions" style="margin-top: 20px; display: flex; gap: 12px; justify-content: flex-end;">
                <button onclick="saveBeneficiaries()" 
                        style="padding: 12px 24px; background: #c8102e; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 500;">
                    Save Selected
                </button>
                <button onclick="closeModal('beneficiaries-modal')" 
                        style="padding: 12px 24px; background: #6b7280; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 500;">
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

    function cancelConfirmation() {
        document.getElementById('confirmation-box').style.display = 'none';
        document.getElementById('add-form').style.display = 'block';
    }

    function saveModalSelections(type) {
        const modal = document.getElementById(type + '-modal');
        const checkboxes = modal.querySelectorAll('input[type="checkbox"]:checked');
        const values = Array.from(checkboxes).map(cb => cb.value.trim());

        const hidden = document.getElementById(type + '-hidden');

        const displayMap = {
            'type':         'selected-types',
            'sdg':          'selected-sdgs',
            'offices':      'selected-offices',
            'programs':     'selected-programs',
            'beneficiaries': 'selected-beneficiaries'
        };
        const displayId = displayMap[type];
        const display = displayId ? document.getElementById(displayId) : null;

        if (hidden) {
            hidden.value = values.join(', ');
        }

        if (display) {
            display.textContent = values.length > 0 ? values.join(', ') : 'None selected';
        }

        closeModal(type + '-modal');
    }

    let beneficiariesData = [];

    function loadBeneficiaries() {
        const rowsDiv = document.getElementById('beneficiary-rows');
        rowsDiv.innerHTML = '';

        const parentJson = <?= json_encode($parent['beneficiaries_json'] ?? '[]') ?>;
        let entries = [];

        try {
            entries = JSON.parse(parentJson);
            entries = entries.map(e => ({
                type: e.type || 'Unnamed Type',
                male: Number(e.male || 0),
                female: Number(e.female || 0)
            }));
        } catch (e) {
            entries = parentJson.split(',').map(item => ({
                type: item.trim(),
                male: 0,
                female: 0
            })).filter(e => e.type !== '');
        }

        if (entries.length === 0) {
            rowsDiv.innerHTML = '<p style="color:#6b7280; text-align:center;">No beneficiaries defined in parent program.</p>';
            return;
        }

        beneficiariesData = entries.map((e, i) => ({ ...e, index: i, selected: true }));

        entries.forEach((entry, index) => {
            const row = document.createElement('div');
            row.style.display = 'flex';
            row.style.alignItems = 'center';
            row.style.gap = '16px';
            row.style.marginBottom = '12px';
            row.style.padding = '12px';
            row.style.border = '1px solid #d1d5db';
            row.style.borderRadius = '6px';

            row.innerHTML = `
                <input type="checkbox" checked onchange="toggleBeneficiary(${index}, this.checked)" style="width:24px; height:24px;">

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
        if (beneficiariesData[index]) {
            beneficiariesData[index].selected = checked;
        }
    }

    function saveBeneficiaries() {
        const selected = beneficiariesData.filter(b => b.selected).map(b => ({
            type: b.type,
            male: b.male,
            female: b.female
        }));

        const hidden = document.getElementById('beneficiaries-hidden');
        if (hidden) {
            hidden.value = JSON.stringify(selected);
        }

        const preview = document.getElementById('selected-beneficiaries');
        if (preview) {
            const count = selected.length;
            preview.textContent = count > 0 ? `${count} type(s) selected` : 'None selected';
        }

        closeModal('beneficiaries-modal');
    }

    function openBeneficiariesModal() {
        openModal('beneficiaries-modal');
        loadBeneficiaries();
    }
    </script>
</body>
</html>