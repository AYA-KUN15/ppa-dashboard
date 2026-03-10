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
    SELECT program_id, project_title, implementation_start, implementation_end,
           type_of_extension_service_agenda, sdg_goals,
           offices_involved, programs_involved, beneficiaries_json
    FROM project_entries WHERE id = ?
");
$stmt->execute([$id]);
$entry = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$entry) {
    die("Project not found.");
}

$program_id = $entry['program_id'];

$pStmt = $pdo->prepare("
    SELECT duration_start, duration_end,
           type_of_extension_service_agenda, sdg_goals,
           offices_involved, programs_involved, beneficiaries_json
    FROM program_entries WHERE id = ?
");
$pStmt->execute([$program_id]);
$parent = $pStmt->fetch(PDO::FETCH_ASSOC);

if (!$parent) {
    die("Parent program not found.");
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        $error = 'Please fill all required fields (including at least one beneficiary).';
    } elseif (strtotime($impl_end) < strtotime($impl_start)) {
        $error = 'End date cannot be before start date.';
    } else {
        try {
            $stmt = $pdo->prepare("
                UPDATE project_entries 
                SET project_title = ?, implementation_start = ?, implementation_end = ?,
                    type_of_extension_service_agenda = ?, sdg_goals = ?,
                    offices_involved = ?, programs_involved = ?, beneficiaries_json = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $project_title, $impl_start, $impl_end,
                $type_agenda, $sdg_goals, $offices, $programs,
                $beneficiaries, $id
            ]);

            if ($stmt->rowCount() > 0) {
                // Force reset to active after successful edit
                $resetStmt = $pdo->prepare("UPDATE project_entries SET status = 'active', updated_at = NOW() WHERE id = ?");
                $resetStmt->execute([$id]);

                header("Location: view.php?id={$program_id}&success=updated");
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
    ['url' => 'index.php', 'label' => 'Home',    'active' => false],
    ['url' => '/opmm/view.php?id=' . $program_id, 'label' => 'Program', 'active' => false],
];
?>

<?php include '../includes/header.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Project</title>
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
        <h1>Edit Project</h1>

        <?php if ($error): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <form method="POST" id="edit-form">
            <label for="project_title">Project Title *</label>
            <input type="text" id="project_title" name="project_title" value="<?= htmlspecialchars($entry['project_title'] ?? '') ?>" required>

            <!-- Type -->
            <label>Type of Extension Service Agenda *</label>
            <button type="button" onclick="loadModalCheckboxes('type'); openModal('type-modal')">Select Types</button>
            <div id="selected-types" style="margin: 8px 0; min-height: 40px; border: 1px solid #ccc; padding: 8px; border-radius: 4px;"></div>
            <input type="hidden" name="type_agenda" id="type-hidden" value="<?= htmlspecialchars($entry['type_of_extension_service_agenda'] ?? '') ?>">

            <!-- SDG -->
            <label>Sustainable Development Goals *</label>
            <button type="button" onclick="loadModalCheckboxes('sdg'); openModal('sdg-modal')">Select SDGs</button>
            <div id="selected-sdgs" style="margin: 8px 0; min-height: 40px; border: 1px solid #ccc; padding: 8px; border-radius: 4px;"></div>
            <input type="hidden" name="sdg_goals" id="sdg-hidden" value="<?= htmlspecialchars($entry['sdg_goals'] ?? '') ?>">

            <!-- Offices -->
            <label>Offices Involved *</label>
            <button type="button" onclick="loadModalCheckboxes('offices'); openModal('offices-modal')">Select Offices</button>
            <div id="selected-offices" style="margin: 8px 0; min-height: 40px; border: 1px solid #ccc; padding: 8px; border-radius: 4px;"></div>
            <input type="hidden" name="offices" id="offices-hidden" value="<?= htmlspecialchars($entry['offices_involved'] ?? '') ?>">

            <!-- Programs -->
            <label>Programs Involved *</label>
            <button type="button" onclick="loadModalCheckboxes('programs'); openModal('programs-modal')">Select Programs</button>
            <div id="selected-programs" style="margin: 8px 0; min-height: 40px; border: 1px solid #ccc; padding: 8px; border-radius: 4px;"></div>
            <input type="hidden" name="programs" id="programs-hidden" value="<?= htmlspecialchars($entry['programs_involved'] ?? '') ?>">

            <!-- Beneficiaries -->
            <label>Beneficiaries *</label>
            <button type="button" onclick="openBeneficiariesModal()">Select Beneficiaries</button>
            <div id="selected-beneficiaries" style="margin: 8px 0; min-height: 40px; border: 1px solid #ccc; padding: 8px; border-radius: 4px;">
                <?php
                $json = $entry['beneficiaries_json'] ?? '[]';
                $decoded = json_decode($json, true);
                $count = is_array($decoded) ? count($decoded) : 0;
                echo htmlspecialchars($count > 0 ? "$count type(s) selected" : 'None selected');
                ?>
            </div>
            <input type="hidden" name="beneficiaries" id="beneficiaries-hidden" value="<?= htmlspecialchars($entry['beneficiaries_json'] ?? '[]') ?>">

            <!-- Duration Range -->
            <label>Implementation Duration *</label>
            <div style="display: flex; gap: 16px; align-items: center; flex-wrap: wrap;">
                <input type="date" 
                       name="implementation_start" 
                       value="<?= htmlspecialchars($entry['implementation_start'] ?? '') ?>" 
                       min="<?= htmlspecialchars($parent['duration_start']) ?>" 
                       max="<?= htmlspecialchars($parent['duration_end']) ?>" 
                       required 
                       style="flex: 1; min-width: 160px; padding: 10px; border: 1px solid #ccc; border-radius: 4px;" />
                <span>to</span>
                <input type="date" 
                       name="implementation_end" 
                       value="<?= htmlspecialchars($entry['implementation_end'] ?? '') ?>" 
                       min="<?= htmlspecialchars($parent['duration_start']) ?>" 
                       max="<?= htmlspecialchars($parent['duration_end']) ?>" 
                       required 
                       style="flex: 1; min-width: 160px; padding: 10px; border: 1px solid #ccc; border-radius: 4px;" />
            </div>

            <button type="submit" id="save-btn" disabled><b>Save Changes</b></button>
        </form>
    </main>

    <!-- Type Modal -->
    <div id="type-modal" class="modal-overlay">
        <div class="modal-box">
            <span class="close-modal" onclick="closeModal('type-modal')">×</span>
            <h2>Select Type of Extension Service Agenda</h2>
            <div id="type-checkboxes" style="max-height: 400px; overflow-y: auto; padding: 12px;">
                <!-- Dynamically populated -->
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
            <div id="sdg-checkboxes" style="max-height: 400px; overflow-y: auto; padding: 12px;">
                <!-- Dynamically populated -->
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
            <div id="offices-checkboxes" style="max-height: 400px; overflow-y: auto; padding: 12px;">
                <!-- Dynamically populated -->
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
            <div id="programs-checkboxes" style="max-height: 400px; overflow-y: auto; padding: 12px;">
                <!-- Dynamically populated -->
            </div>
            <div class="modal-actions">
                <button onclick="saveModalSelections('programs')">Save</button>
                <button onclick="closeModal('programs-modal')">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Beneficiaries Modal (unchanged - already working) -->
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
    // Open modal + load checkboxes for non-beneficiary modals
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
        const display = document.getElementById('selected-' + type);

        if (hidden) hidden.value = values.join(', ');
        if (display) display.textContent = values.length > 0 ? values.join(', ') : '';

        closeModal(type + '-modal');
        syncPreviews();
        checkFormChanges();
    }

    // Dynamic load for Type, SDG, Offices, Programs modals
    function loadModalCheckboxes(type) {
        const container = document.getElementById(type + '-checkboxes');
        const hidden = document.getElementById(type + '-hidden');
        if (!container || !hidden) return;

        const currentValues = hidden.value.split(', ').map(v => v.trim()).filter(v => v);

        // Parent values (from PHP)
        let parentValues = [];
        <?php
        if ($parent['type_of_extension_service_agenda']) {
            $types = explode(', ', $parent['type_of_extension_service_agenda']);
            echo "if (type === 'type') parentValues = " . json_encode(array_map('trim', $types)) . ";";
        }
        if ($parent['sdg_goals']) {
            $sdgs = explode(', ', $parent['sdg_goals']);
            echo "if (type === 'sdg') parentValues = " . json_encode(array_map('trim', $sdgs)) . ";";
        }
        if ($parent['offices_involved']) {
            $offices = explode(', ', $parent['offices_involved']);
            echo "if (type === 'offices') parentValues = " . json_encode(array_map('trim', $offices)) . ";";
        }
        if ($parent['programs_involved']) {
            $progs = explode(', ', $parent['programs_involved']);
            echo "if (type === 'programs') parentValues = " . json_encode(array_map('trim', $progs)) . ";";
        }
        ?>

        container.innerHTML = ''; // Clear old content

        parentValues.forEach(val => {
            if (!val) return;
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

    // Beneficiaries (unchanged - already working)
    let beneficiariesData = [];

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
            rowsDiv.innerHTML = '<p style="color:#6b7280; text-align:center;">No beneficiaries in parent program.</p>';
            return;
        }

        beneficiariesData = parentEntries.map((e, i) => {
            const isSelected = currentEntries.some(c => c.type === e.type);
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
            preview.textContent = count > 0 ? `${count} type(s) selected` : '';
        }

        closeModal('beneficiaries-modal');
        syncPreviews();
        checkFormChanges();
    }

    function openBeneficiariesModal() {
        openModal('beneficiaries-modal');
        loadBeneficiaries();
    }

    // Sync preview divs
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
                display.textContent = val;
            }
        });

        const benHidden = document.getElementById('beneficiaries-hidden');
        const benDisplay = document.getElementById('selected-beneficiaries');
        if (benHidden && benDisplay) {
            try {
                const data = JSON.parse(benHidden.value || '[]');
                const count = data.length;
                benDisplay.textContent = count > 0 ? `${count} type(s) selected` : '';
            } catch (e) {
                benDisplay.textContent = '';
            }
        }
    }

    let originalValues = {};

    function checkFormChanges() {
        const currentValues = {
            project_title: document.querySelector('[name="project_title"]').value.trim(),
            implementation_start: document.querySelector('[name="implementation_start"]').value.trim(),
            implementation_end: document.querySelector('[name="implementation_end"]').value.trim(),
            type_agenda: document.getElementById('type-hidden')?.value.trim() || '',
            sdg_goals: document.getElementById('sdg-hidden')?.value.trim() || '',
            offices: document.getElementById('offices-hidden')?.value.trim() || '',
            programs: document.getElementById('programs-hidden')?.value.trim() || '',
            beneficiaries: document.getElementById('beneficiaries-hidden')?.value.trim() || ''
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
            project_title: document.querySelector('[name="project_title"]').value.trim(),
            implementation_start: document.querySelector('[name="implementation_start"]').value.trim(),
            implementation_end: document.querySelector('[name="implementation_end"]').value.trim(),
            type_agenda: document.getElementById('type-hidden').value.trim(),
            sdg_goals: document.getElementById('sdg-hidden').value.trim(),
            offices: document.getElementById('offices-hidden').value.trim(),
            programs: document.getElementById('programs-hidden').value.trim(),
            beneficiaries: document.getElementById('beneficiaries-hidden').value.trim()
        };

        syncPreviews();
        setTimeout(syncPreviews, 100);
        setTimeout(syncPreviews, 500);

        document.querySelectorAll('input, select').forEach(el => {
            el.addEventListener('input', checkFormChanges);
            el.addEventListener('change', checkFormChanges);
        });

        checkFormChanges();
    });
    </script>
</body>
</html>