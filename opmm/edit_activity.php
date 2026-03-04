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
           type_of_extension_service_agenda, sdg_goals, frequency_monitoring,
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

// Determine parent month/year and single-month status
$parentStart = $parent['implementation_start'];
$parentEnd   = $parent['implementation_end'];
$parentMonth = date('F', strtotime($parentStart));
$parentYear  = date('Y', strtotime($parentStart));
$isSingleMonth = (date('Y-m', strtotime($parentStart)) === date('Y-m', strtotime($parentEnd)));

// Days in parent month
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, date('n', strtotime($parentStart)), $parentYear);

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $activity_name = trim($_POST['activity_name'] ?? '');
    $type_agenda   = trim($_POST['type_agenda'] ?? '');
    $sdg_goals     = trim($_POST['sdg_goals'] ?? '');
    $frequency_monitoring = trim($_POST['frequency_monitoring'] ?? '');
    $offices       = trim($_POST['offices'] ?? '');
    $programs      = trim($_POST['programs'] ?? '');
    $beneficiaries = trim($_POST['beneficiaries'] ?? '[]');

    // Start
    $start_day = (int)($_POST['start_day'] ?? 0);

    // End (optional in single-month)
    $end_day = (int)($_POST['end_day'] ?? 0);

    if (empty($activity_name) || $start_day < 1 || $start_day > $daysInMonth ||
        empty($type_agenda) || empty($sdg_goals) || empty($frequency_monitoring) ||
        empty($offices) || empty($programs) || $beneficiaries === '[]') {
        $error = 'Please fill all required fields. Start day must be between 1 and ' . $daysInMonth . ' for ' . $parentMonth . '.';
    } elseif (!$isSingleMonth && ($end_day < 1 || $end_day > $daysInMonth)) {
        $error = 'End day must be between 1 and ' . $daysInMonth . '.';
    } elseif (!$isSingleMonth && $end_day < $start_day) {
        $error = 'End day cannot be before start day in the same month.';
    } else {
        $startDate = date('Y-m-d', strtotime("$parentMonth $start_day $parentYear"));

        if ($isSingleMonth && $end_day === 0) {
            $endDate = $startDate;
        } else {
            $endDate = date('Y-m-d', strtotime("$parentMonth $end_day $parentYear"));
        }

        try {
            $stmt = $pdo->prepare("
                UPDATE activity_entries 
                SET activity_name = ?, implementation_start = ?, implementation_end = ?,
                    type_of_extension_service_agenda = ?, sdg_goals = ?, frequency_monitoring = ?,
                    offices_involved = ?, programs_involved = ?, beneficiaries_json = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $activity_name, $startDate, $endDate,
                $type_agenda, $sdg_goals, $frequency_monitoring,
                $offices, $programs, $beneficiaries, $id
            ]);

            if ($stmt->rowCount() > 0) {
                // Force reset to active after successful edit
                $resetStmt = $pdo->prepare("UPDATE activity_entries SET status = 'active', updated_at = NOW() WHERE id = ?");
                $resetStmt->execute([$id]);

                header("Location: view_project.php?id={$project_id}&success=updated");
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
    ['url' => '../index.php',                    'label' => 'Home',    'active' => false],
    ['url' => 'view_project.php?id=' . $project_id, 'label' => 'Project', 'active' => false],
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
</head>
<body>

    <main class="dashboard-content">
        <h1>Edit Activity</h1>

        <?php if ($error): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <form method="POST">
            <label for="activity_name">Activity Name *</label>
            <input type="text" id="activity_name" name="activity_name" value="<?= htmlspecialchars($entry['activity_name'] ?? '') ?>" required>

            <!-- Type -->
            <label>Type of Extension Service Agenda *</label>
            <button type="button" onclick="openModal('type-modal')">Select Types</button>
            <div id="selected-types" style="margin: 8px 0; min-height: 40px; border: 1px solid #ccc; padding: 8px; border-radius: 4px;">
                <?= htmlspecialchars($entry['type_of_extension_service_agenda'] ?? 'None') ?>
            </div>
            <input type="hidden" name="type_agenda" id="type-hidden" value="<?= htmlspecialchars($entry['type_of_extension_service_agenda'] ?? '') ?>">

            <!-- SDG -->
            <label>Sustainable Development Goals *</label>
            <button type="button" onclick="openModal('sdg-modal')">Select SDGs</button>
            <div id="selected-sdgs" style="margin: 8px 0; min-height: 40px; border: 1px solid #ccc; padding: 8px; border-radius: 4px;">
                <?= htmlspecialchars($entry['sdg_goals'] ?? 'None') ?>
            </div>
            <input type="hidden" name="sdg_goals" id="sdg-hidden" value="<?= htmlspecialchars($entry['sdg_goals'] ?? '') ?>">

            <!-- Frequency of Monitoring -->
            <label>Frequency of Monitoring *</label>
            <select name="frequency_monitoring" required style="padding: 10px; border: 1px solid #ccc; border-radius: 4px; width: 100%; max-width: 400px;">
                <option value="">Select Frequency</option>
                <option value="Monthly"    <?= ($entry['frequency_monitoring'] ?? '') === 'Monthly'    ? 'selected' : '' ?>>Monthly</option>
                <option value="Quarterly"  <?= ($entry['frequency_monitoring'] ?? '') === 'Quarterly'  ? 'selected' : '' ?>>Quarterly</option>
                <option value="Semi-Annually" <?= ($entry['frequency_monitoring'] ?? '') === 'Semi-Annually' ? 'selected' : '' ?>>Semi-Annually</option>
                <option value="Annually"   <?= ($entry['frequency_monitoring'] ?? '') === 'Annually'   ? 'selected' : '' ?>>Annually</option>
            </select>

            <!-- Offices -->
            <label>Offices Involved *</label>
            <button type="button" onclick="openModal('offices-modal')">Select Offices</button>
            <div id="selected-offices" style="margin: 8px 0; min-height: 40px; border: 1px solid #ccc; padding: 8px; border-radius: 4px;">
                <?= htmlspecialchars($entry['offices_involved'] ?? 'None') ?>
            </div>
            <input type="hidden" name="offices" id="offices-hidden" value="<?= htmlspecialchars($entry['offices_involved'] ?? '') ?>">

            <!-- Programs -->
            <label>Programs Involved *</label>
            <button type="button" onclick="openModal('programs-modal')">Select Programs</button>
            <div id="selected-programs" style="margin: 8px 0; min-height: 40px; border: 1px solid #ccc; padding: 8px; border-radius: 4px;">
                <?= htmlspecialchars($entry['programs_involved'] ?? 'None') ?>
            </div>
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

            <!-- Implementation Duration -->
            <label>Implementation Duration *</label>
            <div style="display: flex; flex-direction: column; gap: 16px; max-width: 500px;">

                <!-- Start -->
                <div style="display: flex; gap: 16px; align-items: center; flex-wrap: wrap;">
                    <span style="font-weight: 500; min-width: 60px;">Start:</span>

                    <?php if ($isSingleMonth): ?>
                        <input type="text" value="<?= htmlspecialchars($parentMonth) ?>" readonly 
                               style="flex: 1; min-width: 140px; background: #f0f0f0; cursor: not-allowed; padding: 10px; border: 1px solid #ccc; border-radius: 4px;" />
                        <input type="number" name="start_day" min="1" max="<?= $daysInMonth ?>" 
                               placeholder="Day" value="<?= htmlspecialchars(date('j', strtotime($entry['implementation_start']))) ?>" required 
                               style="flex: 1; min-width: 100px; padding: 10px; border: 1px solid #ccc; border-radius: 4px;" />
                    <?php else: ?>
                        <select name="start_month" required style="flex: 1; min-width: 140px; padding: 10px; border: 1px solid #ccc; border-radius: 4px;">
                            <option value="">Month</option>
                            <?php
                            $shownMonths = [];
                            $pStart = new DateTime($parent['implementation_start']);
                            $pEnd   = new DateTime($parent['implementation_end']);
                            $interval = new DateInterval('P1M');
                            $period = new DatePeriod($pStart, $interval, $pEnd->modify('+1 day'));
                            foreach ($period as $dt) {
                                $month = $dt->format('F');
                                if (!in_array($month, $shownMonths)) {
                                    $shownMonths[] = $month;
                                    $selected = ($month === date('F', strtotime($entry['implementation_start']))) ? 'selected' : '';
                                    echo "<option value=\"$month\" $selected>$month</option>";
                                }
                            }
                            ?>
                        </select>
                        <input type="number" name="start_day" min="1" max="31" 
                               placeholder="Day" value="<?= htmlspecialchars(date('j', strtotime($entry['implementation_start']))) ?>" required 
                               style="flex: 1; min-width: 100px; padding: 10px; border: 1px solid #ccc; border-radius: 4px;" />
                    <?php endif; ?>
                </div>

                <!-- End -->
                <div style="display: flex; gap: 16px; align-items: center; flex-wrap: wrap;">
                    <span style="font-weight: 500; min-width: 60px;">End:</span>

                    <?php if ($isSingleMonth): ?>
                        <input type="text" value="<?= htmlspecialchars($parentMonth) ?>" readonly 
                               style="flex: 1; min-width: 140px; background: #f0f0f0; cursor: not-allowed; padding: 10px; border: 1px solid #ccc; border-radius: 4px;" />
                        <input type="number" name="end_day" min="1" max="<?= $daysInMonth ?>" 
                               placeholder="Day (optional)" value="<?= htmlspecialchars(date('j', strtotime($entry['implementation_end']))) ?>" 
                               style="flex: 1; min-width: 100px; padding: 10px; border: 1px solid #ccc; border-radius: 4px;" />
                        <small style="color: #6b7280; font-size: 0.85rem;">(leave blank to use same day as start)</small>
                    <?php else: ?>
                        <select name="end_month" required style="flex: 1; min-width: 140px; padding: 10px; border: 1px solid #ccc; border-radius: 4px;">
                            <option value="">Month</option>
                            <?php
                            $shownMonths = [];
                            foreach ($period as $dt) {
                                $month = $dt->format('F');
                                if (!in_array($month, $shownMonths)) {
                                    $shownMonths[] = $month;
                                    $selected = ($month === date('F', strtotime($entry['implementation_end']))) ? 'selected' : '';
                                    echo "<option value=\"$month\" $selected>$month</option>";
                                }
                            }
                            ?>
                        </select>
                        <input type="number" name="end_day" min="1" max="31" 
                               placeholder="Day" value="<?= htmlspecialchars(date('j', strtotime($entry['implementation_end']))) ?>" required 
                               style="flex: 1; min-width: 100px; padding: 10px; border: 1px solid #ccc; border-radius: 4px;" />
                    <?php endif; ?>
                </div>
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
                    echo '<p>No types available from parent project.</p>';
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
                    echo '<p>No SDGs available from parent project.</p>';
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
                    $progs = explode(', ', $parent['programs_involved']);
                    foreach ($progs as $p) {
                        $p = trim($p);
                        echo '<label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px; border-radius: 6px;">
                            ' . htmlspecialchars($p) . '
                            <input type="checkbox" value="' . htmlspecialchars($p) . '">
                        </label>';
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
            <h2>Select Beneficiaries from Project</h2>
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
        const currentJson = <?= json_encode($entry['beneficiaries_json'] ?? '[]') ?>;

        let parentEntries = [];
        let currentEntries = [];

        try { parentEntries = JSON.parse(parentJson); } catch (e) {}
        try { currentEntries = JSON.parse(currentJson); } catch (e) {}

        if (parentEntries.length === 0) {
            rowsDiv.innerHTML = '<p style="color:#6b7280; text-align:center;">No beneficiaries in parent project.</p>';
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