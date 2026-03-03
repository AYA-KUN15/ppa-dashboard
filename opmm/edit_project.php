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
    SELECT program_id, project_title, date_of_implementation,
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

// Parse date for dropdown pre-selection
$dateParts = explode(' ', trim($entry['date_of_implementation']));
$impl_month_val = $dateParts[0] ?? '';
$impl_year_val  = $dateParts[1] ?? '';

// Current values (POST on error, else DB)
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$project_title_val   = $isPost ? trim($_POST['project_title'] ?? '') : htmlspecialchars($entry['project_title'] ?? '');
$type_agenda_val     = $isPost ? trim($_POST['type_agenda'] ?? '') : htmlspecialchars($entry['type_of_extension_service_agenda'] ?? '');
$sdg_goals_val       = $isPost ? trim($_POST['sdg_goals'] ?? '') : htmlspecialchars($entry['sdg_goals'] ?? '');
$offices_val         = $isPost ? trim($_POST['offices'] ?? '') : htmlspecialchars($entry['offices_involved'] ?? '');
$programs_val        = $isPost ? trim($_POST['programs'] ?? '') : htmlspecialchars($entry['programs_involved'] ?? '');
$beneficiaries_val   = $isPost ? trim($_POST['beneficiaries'] ?? '') : htmlspecialchars($entry['beneficiaries_json'] ?? '');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $project_title = trim($_POST['project_title'] ?? '');
    $impl_month    = trim($_POST['impl_month'] ?? '');
    $impl_year     = trim($_POST['impl_year'] ?? '');
    $type_agenda   = trim($_POST['type_agenda'] ?? '');
    $sdg_goals     = trim($_POST['sdg_goals'] ?? '');
    $offices       = trim($_POST['offices'] ?? '');
    $programs      = trim($_POST['programs'] ?? '');
    $beneficiaries = trim($_POST['beneficiaries'] ?? '[]');

    if (empty($project_title) || empty($impl_month) || empty($impl_year) ||
        empty($type_agenda) || empty($sdg_goals) || empty($offices) ||
        empty($programs) || $beneficiaries === '[]') {
        $error = 'Please fill all required fields (including at least one beneficiary).';
    } else {
        $date_of_implementation = "$impl_month $impl_year";

        try {
            $stmt = $pdo->prepare("
                UPDATE project_entries 
                SET project_title = ?, date_of_implementation = ?,
                    type_of_extension_service_agenda = ?, sdg_goals = ?,
                    offices_involved = ?, programs_involved = ?,
                    beneficiaries_json = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $project_title, $date_of_implementation,
                $type_agenda, $sdg_goals, $offices, $programs,
                $beneficiaries, $id
            ]);

            if ($stmt->rowCount() > 0) {
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Project</title>
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
            <a href="view.php?id=<?= htmlspecialchars($program_id) ?>" class="nav-button">Program</a>
            <a href="../logout.php" class="nav-button logout">Logout</a>
        </nav>
    </header>

    <main class="dashboard-content">
        <h1>Edit Project</h1>

        <?php if ($error): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <form method="POST">
            <label for="project_title">Project Title *</label>
            <input type="text" id="project_title" name="project_title" value="<?= $project_title_val ?>" required>

            <!-- Type -->
            <label>Type of Extension Service Agenda *</label>
            <button type="button" onclick="openModal('type-modal')">Select Types</button>
            <div id="selected-types" style="margin: 8px 0; min-height: 40px; border: 1px solid #ccc; padding: 8px; border-radius: 4px;"><?= htmlspecialchars($type_agenda_val ?: 'None selected') ?></div>
            <input type="hidden" name="type_agenda" id="type-hidden" value="<?= htmlspecialchars($type_agenda_val) ?>">

            <!-- SDG -->
            <label>Sustainable Development Goals *</label>
            <button type="button" onclick="openModal('sdg-modal')">Select SDGs</button>
            <div id="selected-sdgs" style="margin: 8px 0; min-height: 40px; border: 1px solid #ccc; padding: 8px; border-radius: 4px;"><?= htmlspecialchars($sdg_goals_val ?: 'None selected') ?></div>
            <input type="hidden" name="sdg_goals" id="sdg-hidden" value="<?= htmlspecialchars($sdg_goals_val) ?>">

            <!-- Offices -->
            <label>Offices Involved *</label>
            <button type="button" onclick="openModal('offices-modal')">Select Offices</button>
            <div id="selected-offices" style="margin: 8px 0; min-height: 40px; border: 1px solid #ccc; padding: 8px; border-radius: 4px;"><?= htmlspecialchars($offices_val ?: 'None selected') ?></div>
            <input type="hidden" name="offices" id="offices-hidden" value="<?= htmlspecialchars($offices_val) ?>">

            <!-- Programs -->
            <label>Programs Involved *</label>
            <button type="button" onclick="openModal('programs-modal')">Select Programs</button>
            <div id="selected-programs" style="margin: 8px 0; min-height: 40px; border: 1px solid #ccc; padding: 8px; border-radius: 4px;"><?= htmlspecialchars($programs_val ?: 'None selected') ?></div>
            <input type="hidden" name="programs" id="programs-hidden" value="<?= htmlspecialchars($programs_val) ?>">

            <!-- Beneficiaries -->
            <label>Beneficiaries *</label>
            <button type="button" onclick="openBeneficiariesModal()">Select Beneficiaries</button>
            <div id="selected-beneficiaries" style="margin: 8px 0; min-height: 40px; border: 1px solid #ccc; padding: 8px; border-radius: 4px;">
                <?php
                $json = $beneficiaries_val ?: '[]';
                $decoded = json_decode($json, true);
                $count = is_array($decoded) ? count($decoded) : 0;
                echo htmlspecialchars($count > 0 ? "$count type(s) selected" : 'None selected');
                ?>
            </div>
            <input type="hidden" name="beneficiaries" id="beneficiaries-hidden" value="<?= htmlspecialchars($beneficiaries_val) ?>">

            <label>Date of Implementation *</label>
            <div style="display: flex; gap: 16px; align-items: center; flex-wrap: wrap;">
                <select name="impl_month" required style="flex: 1; min-width: 160px;">
                    <option value="">Select Month</option>
                    <?php
                    $start = new DateTime($parent['duration_start']);
                    $end = new DateTime($parent['duration_end']);
                    $interval = new DateInterval('P1M');
                    $period = new DatePeriod($start, $interval, $end->modify('+1 day'));
                    $shownMonths = [];
                    foreach ($period as $dt) {
                        $month = $dt->format('F');
                        if (!in_array($month, $shownMonths)) {
                            $shownMonths[] = $month;
                            $selected = ($month === $impl_month_val) ? 'selected' : '';
                            echo "<option value=\"$month\" $selected>$month</option>";
                        }
                    }
                    ?>
                </select>

                <select name="impl_year" required style="flex: 1; min-width: 120px;">
                    <option value="">Select Year</option>
                    <?php
                    $startYear = (int) date('Y', strtotime($parent['duration_start']));
                    $endYear = (int) date('Y', strtotime($parent['duration_end']));
                    for ($y = $startYear; $y <= $endYear; $y++) {
                        $selected = ($y == $impl_year_val) ? 'selected' : '';
                        echo "<option value=\"$y\" $selected>$y</option>";
                    }
                    ?>
                </select>
            </div>

            <button type="submit">Save Changes</button>
        </form>
    </main>

    <!-- Type Modal -->
    <div id="type-modal" class="modal-overlay">
        <div class="modal-box">
            <span class="close-modal" onclick="closeModal('type-modal')">×</span>
            <h2>Select Type of Extension Service Agenda</h2>
            <div style="max-height: 400px; overflow-y: auto; padding: 12px;">
                <?php
                if ($parent['type_of_extension_service_agenda']) {
                    $types = explode(', ', $parent['type_of_extension_service_agenda']);
                    foreach ($types as $t) {
                        $t = trim($t);
                        echo '<label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px; border-radius: 6px;">
                            ' . htmlspecialchars($t) . '
                            <input type="checkbox" value="' . htmlspecialchars($t) . '">
                        </label>';
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
                    $sdgs = explode(', ', $parent['sdg_goals']);
                    foreach ($sdgs as $s) {
                        $s = trim($s);
                        echo '<label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px; border-radius: 6px;">
                            ' . htmlspecialchars($s) . '
                            <input type="checkbox" value="' . htmlspecialchars($s) . '">
                        </label>';
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
                if ($parent['offices_involved']) {
                    $offices = explode(', ', $parent['offices_involved']);
                    foreach ($offices as $o) {
                        $o = trim($o);
                        echo '<label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px; border-radius: 6px;">
                            ' . htmlspecialchars($o) . '
                            <input type="checkbox" value="' . htmlspecialchars($o) . '">
                        </label>';
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
                if ($parent['programs_involved']) {
                    $progs = explode(', ', $parent['programs_involved']);
                    foreach ($progs as $p) {
                        $p = trim($p);
                        echo '<label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px; border-radius: 6px;">
                            ' . htmlspecialchars($p) . '
                            <input type="checkbox" value="' . htmlspecialchars($p) . '">
                        </label>';
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

    function saveModalSelections(type) {
        const modal = document.getElementById(type + '-modal');
        const checkboxes = modal.querySelectorAll('input[type="checkbox"]:checked');
        const values = Array.from(checkboxes).map(cb => cb.value.trim());

        const hidden = document.getElementById(type + '-hidden');
        const display = document.getElementById('selected-' + type);

        if (hidden) hidden.value = values.join(', ');
        if (display) display.textContent = values.length > 0 ? values.join(', ') : 'None selected';

        closeModal(type + '-modal');
    }

    let beneficiariesData = [];

    function loadBeneficiaries() {
        const rowsDiv = document.getElementById('beneficiary-rows');
        rowsDiv.innerHTML = '';

        const parentJson = <?= json_encode($parent['beneficiaries_json'] ?? '[]') ?>;
        const currentJson = <?= json_encode($beneficiaries_val) ?>;

        let parentEntries = [];
        let currentEntries = [];

        try { parentEntries = JSON.parse(parentJson); } catch (e) {}
        try { currentEntries = JSON.parse(currentJson); } catch (e) {}

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