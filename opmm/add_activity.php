<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php");
    exit;
}

require_once '../config/db.php';

$project_id = $_GET['project_id'] ?? null;
if (!$project_id || !is_numeric($project_id)) {
    header("Location: list.php");
    exit;
}

$stmt = $pdo->prepare("
    SELECT project_title, implementation_start, implementation_end,
           type_of_extension_service_agenda, sdg_goals,
           offices_involved, programs_involved, beneficiaries_json
    FROM project_entries WHERE id = ?
");
$stmt->execute([$project_id]);
$parent = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$parent) {
    die("Parent project not found.");
}

$parentStart = $parent['implementation_start'];
$parentEnd   = $parent['implementation_end'];
$parentMonth = date('F', strtotime($parentStart));
$parentYear  = date('Y', strtotime($parentStart));
$isSingleMonth = (date('Y-m', strtotime($parentStart)) === date('Y-m', strtotime($parentEnd)));

$daysInMonth = cal_days_in_month(CAL_GREGORIAN, date('n', strtotime($parentStart)), $parentYear);

// Get parent end month/day for Annually auto-fill
$parentEndMonth = date('F', strtotime($parentEnd));
$parentEndDay   = date('j', strtotime($parentEnd));

$error = '';
$show_confirmation = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['confirm']) && isset($_SESSION['pending_activity'])) {
        $d = $_SESSION['pending_activity'];

        try {
            $stmt = $pdo->prepare("
                INSERT INTO activity_entries (
                    project_id, activity_name, implementation_start, implementation_end,
                    type_of_extension_service_agenda, sdg_goals, frequency_monitoring,
                    offices_involved, programs_involved, beneficiaries_json
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $d['project_id'], $d['activity_name'], $d['implementation_start'], $d['implementation_end'],
                $d['type_of_extension_service_agenda'], $d['sdg_goals'], $d['frequency_monitoring'],
                $d['offices_involved'], $d['programs_involved'], $d['beneficiaries_json']
            ]);

            unset($_SESSION['pending_activity']);
            header("Location: view_project.php?id={$d['project_id']}&success=added");
            exit;
        } catch (PDOException $e) {
            $error = 'Failed to save activity: ' . $e->getMessage();
        }
    } else {
        $activity_name = trim($_POST['activity_name'] ?? '');
        $type_agenda   = trim($_POST['type_agenda'] ?? '');
        $sdg_goals     = trim($_POST['sdg_goals'] ?? '');
        $frequency_monitoring = trim($_POST['frequency_monitoring'] ?? '');
        $offices       = trim($_POST['offices'] ?? '');
        $programs      = trim($_POST['programs'] ?? '');
        $beneficiaries = trim($_POST['beneficiaries'] ?? '[]');

        $start_month = $isSingleMonth ? $parentMonth : trim($_POST['start_month'] ?? '');
        $start_day   = (int)($_POST['start_day'] ?? 0);

        $end_month   = $isSingleMonth ? $parentMonth : trim($_POST['end_month'] ?? '');
        $end_day     = (int)($_POST['end_day'] ?? 0);

        if (empty($activity_name) || $start_day < 1 || $start_day > $daysInMonth ||
            empty($type_agenda) || empty($sdg_goals) || empty($frequency_monitoring) ||
            empty($offices) || empty($programs) || $beneficiaries === '[]') {
            $error = 'Please fill all required fields. Start day must be between 1 and ' . $daysInMonth . ' for ' . $parentMonth . '.';
        } elseif (!$isSingleMonth && (empty($start_month) || empty($end_month))) {
            $error = 'Please select start and end months.';
        } else {
            // NEW: Calculate days in the SELECTED end month
            $endMonthNum = date('n', strtotime("$end_month 1 $parentYear"));
            $daysInEndMonth = cal_days_in_month(CAL_GREGORIAN, $endMonthNum, $parentYear);

            if (!$isSingleMonth && ($end_day < 1 || $end_day > $daysInEndMonth)) {
                $error = "End day must be between 1 and $daysInEndMonth for $end_month.";
            } elseif (!$isSingleMonth && $end_day < $start_day && $end_month === $start_month) {
                $error = 'End day cannot be before start day in the same month.';
            } else {
                $startDate = date('Y-m-d', strtotime("$start_month $start_day $parentYear"));

                if ($isSingleMonth && $end_day === 0) {
                    $endDate = $startDate;
                } else {
                    $endDate = date('Y-m-d', strtotime("$end_month $end_day $parentYear"));
                }

                $_SESSION['pending_activity'] = [
                    'project_id' => $project_id,
                    'activity_name' => $activity_name,
                    'implementation_start' => $startDate,
                    'implementation_end' => $endDate,
                    'type_of_extension_service_agenda' => $type_agenda,
                    'sdg_goals' => $sdg_goals,
                    'frequency_monitoring' => $frequency_monitoring,
                    'offices_involved' => $offices,
                    'programs_involved' => $programs,
                    'beneficiaries_json' => $beneficiaries
                ];
                $show_confirmation = true;
            }
        }
    }
}

$nav_links = [
    ['url' => '../index.php', 'label' => 'Home', 'active' => false],
    ['url' => 'view_project.php?id=' . $project_id, 'label' => 'Project', 'active' => false],
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
    </style>
</head>
<body>

    <main class="dashboard-content">
        <h1>Add New Activity</h1>

        <?php if ($error): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <?php if ($show_confirmation): $d = $_SESSION['pending_activity']; ?>
            <div id="confirmation-box" class="confirmation-box">
                <h2>Confirm Activity Details</h2>
                <p><strong>Activity Name:</strong> <?= htmlspecialchars($d['activity_name']) ?></p>
                <p><strong>Implementation Start:</strong> <?= htmlspecialchars($d['implementation_start']) ?></p>
                <p><strong>Implementation End:</strong> <?= htmlspecialchars($d['implementation_end']) ?></p>
                <p><strong>Type of Extension Service Agenda:</strong> <?= htmlspecialchars($d['type_of_extension_service_agenda'] ?: 'None') ?></p>
                <p><strong>SDG Goals:</strong> <?= htmlspecialchars($d['sdg_goals'] ?: 'None') ?></p>
                <p><strong>Frequency of Monitoring:</strong> <?= htmlspecialchars($d['frequency_monitoring']) ?></p>
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
                    <button type="button" onclick="document.getElementById('confirmation-box').style.display='none'; document.getElementById('add-form').style.display='block';" class="cancel-link">Cancel</button>
                </div>
            </div>
        <?php endif; ?>

        <div id="add-form" style="<?= $show_confirmation ? 'display:none;' : '' ?>">
            <form method="POST" id="add-activity-form">
                <input type="hidden" name="project_id" value="<?= htmlspecialchars($project_id) ?>">

                <label for="activity_name">Activity Name *</label>
                <input type="text" id="activity_name" name="activity_name" value="<?= htmlspecialchars($_POST['activity_name'] ?? '') ?>" placeholder="Enter activity name" required>

                <label>Type of Extension Service Agenda *</label>
                <button type="button" onclick="openModal('type-modal')">Select Types</button>
                <div id="selected-types" style="margin: 8px 0; min-height: 40px; border: 1px solid #ccc; padding: 8px; border-radius: 4px;"></div>
                <input type="hidden" name="type_agenda" id="type-hidden" value="<?= htmlspecialchars($_POST['type_agenda'] ?? '') ?>">

                <label>Sustainable Development Goals *</label>
                <button type="button" onclick="openModal('sdg-modal')">Select SDGs</button>
                <div id="selected-sdgs" style="margin: 8px 0; min-height: 40px; border: 1px solid #ccc; padding: 8px; border-radius: 4px;"></div>
                <input type="hidden" name="sdg_goals" id="sdg-hidden" value="<?= htmlspecialchars($_POST['sdg_goals'] ?? '') ?>">

                <label>Frequency of Monitoring *</label>
                <select name="frequency_monitoring" id="frequency_monitoring" required style="padding: 10px; border: 1px solid #ccc; border-radius: 4px; width: 100%; max-width: 400px;">
                    <option value="">Select Frequency</option>
                    <option value="Monthly"    <?= ($_POST['frequency_monitoring'] ?? '') === 'Monthly'    ? 'selected' : '' ?>>Monthly</option>
                    <option value="Quarterly"  <?= ($_POST['frequency_monitoring'] ?? '') === 'Quarterly'  ? 'selected' : '' ?>>Quarterly</option>
                    <option value="Semi-Annually" <?= ($_POST['frequency_monitoring'] ?? '') === 'Semi-Annually' ? 'selected' : '' ?>>Semi-Annually</option>
                    <option value="Annually"   <?= ($_POST['frequency_monitoring'] ?? '') === 'Annually'   ? 'selected' : '' ?>>Annually</option>
                </select>

                <label>Offices Involved *</label>
                <button type="button" onclick="openModal('offices-modal')">Select Offices</button>
                <div id="selected-offices" style="margin: 8px 0; min-height: 40px; border: 1px solid #ccc; padding: 8px; border-radius: 4px;"></div>
                <input type="hidden" name="offices" id="offices-hidden" value="<?= htmlspecialchars($_POST['offices'] ?? '') ?>">

                <label>Programs Involved *</label>
                <button type="button" onclick="openModal('programs-modal')">Select Programs</button>
                <div id="selected-programs" style="margin: 8px 0; min-height: 40px; border: 1px solid #ccc; padding: 8px; border-radius: 4px;"></div>
                <input type="hidden" name="programs" id="programs-hidden" value="<?= htmlspecialchars($_POST['programs'] ?? '') ?>">

                <label>Beneficiaries *</label>
                <button type="button" onclick="openBeneficiariesModal()">Select Beneficiaries</button>
                <div id="selected-beneficiaries" style="margin: 8px 0; min-height: 40px; border: 1px solid #ccc; padding: 8px; border-radius: 4px;"></div>
                <input type="hidden" name="beneficiaries" id="beneficiaries-hidden" value="<?= htmlspecialchars($_POST['beneficiaries'] ?? '[]') ?>">

                <label>Implementation Duration *</label>
                <div style="display: flex; flex-direction: column; gap: 16px; max-width: 500px;">
                    <div style="display: flex; gap: 16px; align-items: center; flex-wrap: wrap;">
                        <span style="font-weight: 500; min-width: 60px;">Start:</span>

                        <?php if ($isSingleMonth): ?>
                            <input type="text" value="<?= htmlspecialchars($parentMonth) ?>" readonly 
                                   style="flex: 1; min-width: 140px; background: #f0f0f0; cursor: not-allowed; padding: 10px; border: 1px solid #ccc; border-radius: 4px;" />
                            <input type="number" name="start_day" id="start_day" min="1" max="<?= $daysInMonth ?>" 
                                   placeholder="Day" value="<?= htmlspecialchars($_POST['start_day'] ?? '') ?>" required 
                                   style="flex: 1; min-width: 100px; padding: 10px; border: 1px solid #ccc; border-radius: 4px;" />
                        <?php else: ?>
                            <select name="start_month" id="start_month" required style="flex: 1; min-width: 140px; padding: 10px; border: 1px solid #ccc; border-radius: 4px;">
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
                                        $selected = ($month === ($_POST['start_month'] ?? '')) ? 'selected' : '';
                                        echo "<option value=\"$month\" $selected>$month</option>";
                                    }
                                }
                                ?>
                            </select>
                            <input type="number" name="start_day" id="start_day" min="1" max="31" 
                                   placeholder="Day" value="<?= htmlspecialchars($_POST['start_day'] ?? '') ?>" required 
                                   style="flex: 1; min-width: 100px; padding: 10px; border: 1px solid #ccc; border-radius: 4px;" />
                        <?php endif; ?>
                    </div>

                    <div style="display: flex; gap: 16px; align-items: center; flex-wrap: wrap;">
                        <span style="font-weight: 500; min-width: 60px;">End:</span>

                        <?php if ($isSingleMonth): ?>
                            <input type="text" value="<?= htmlspecialchars($parentMonth) ?>" readonly 
                                   style="flex: 1; min-width: 140px; background: #f0f0f0; cursor: not-allowed; padding: 10px; border: 1px solid #ccc; border-radius: 4px;" />
                            <input type="number" name="end_day" id="end_day" min="1" max="<?= $daysInMonth ?>" 
                                   placeholder="Day (optional)" value="<?= htmlspecialchars($_POST['end_day'] ?? '') ?>" 
                                   style="flex: 1; min-width: 100px; padding: 10px; border: 1px solid #ccc; border-radius: 4px;" />
                            <small style="color: #6b7280; font-size: 0.85rem;">(leave blank to use same day as start)</small>
                        <?php else: ?>
                            <select name="end_month" id="end_month" required style="flex: 1; min-width: 140px; padding: 10px; border: 1px solid #ccc; border-radius: 4px;">
                                <option value="">Month</option>
                                <?php
                                $shownMonths = [];
                                foreach ($period as $dt) {
                                    $month = $dt->format('F');
                                    if (!in_array($month, $shownMonths)) {
                                        $shownMonths[] = $month;
                                        $selected = ($month === ($_POST['end_month'] ?? '')) ? 'selected' : '';
                                        echo "<option value=\"$month\" $selected>$month</option>";
                                    }
                                }
                                ?>
                            </select>
                            <input type="number" name="end_day" id="end_day" min="1" max="31" 
                                   placeholder="Day" value="<?= htmlspecialchars($_POST['end_day'] ?? '') ?>" required 
                                   style="flex: 1; min-width: 100px; padding: 10px; border: 1px solid #ccc; border-radius: 4px;" />
                        <?php endif; ?>
                    </div>
                </div>

                <button type="submit" id="add-btn"><b>Review & Add</b></button>
            </form>
        </div>
    </main>

    <!-- Modals (unchanged from your version) -->
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
        if (display) display.textContent = values.length > 0 ? values.join(', ') : '';

        closeModal(type + '-modal');
        syncPreviews();
    }

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
            preview.textContent = count > 0 ? `${count} type(s) selected` : '';
        }

        closeModal('beneficiaries-modal');
        syncPreviews();
    }

    function openBeneficiariesModal() {
        openModal('beneficiaries-modal');
        loadBeneficiaries();
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

    // Frequency + Duration restrictions (updated for Annually using parent end date)
    function updateDurationFields() {
        const freqSelect = document.querySelector('#frequency_monitoring');
        if (!freqSelect) return;

        const freq = freqSelect.value.trim().toLowerCase();

        const startMonthSelect = document.querySelector('#start_month');
        const startDayInput    = document.querySelector('#start_day');
        const endMonthSelect   = document.querySelector('#end_month');
        const endDayInput      = document.querySelector('#end_day');

        // Reset restrictions
        if (endMonthSelect) {
            endMonthSelect.disabled = false;
            Array.from(endMonthSelect.options).forEach(opt => {
                if (opt.value) {
                    opt.disabled = false;
                    opt.hidden = false;
                }
            });
        }
        if (endDayInput)   endDayInput.disabled = false;
        if (startDayInput) startDayInput.disabled = false;

        if (!startMonthSelect || !endMonthSelect) return;

        const monthNames = ["January","February","March","April","May","June",
                            "July","August","September","October","November","December"];
        const getMonthIndex = name => monthNames.indexOf(name);

        // Helper: last day of a month
        function getLastDayOfMonth(monthName) {
            const year = new Date().getFullYear();
            const monthNum = getMonthIndex(monthName) + 1;
            return new Date(year, monthNum, 0).getDate();
        }

        // Helper: reset end day to valid value
        function resetEndDay() {
            if (!endDayInput || !endMonthSelect.value) return;
            const lastDay = getLastDayOfMonth(endMonthSelect.value);
            const current = parseInt(endDayInput.value) || 1;
            endDayInput.value = Math.min(current, lastDay);
        }

        if (freq === 'monthly') {
            if (endMonthSelect) {
                endMonthSelect.disabled = true;
                if (startMonthSelect.value) {
                    endMonthSelect.value = startMonthSelect.value;
                }
            }
            if (endDayInput)    endDayInput.disabled = true;

            if (startMonthSelect.value && startDayInput?.value) {
                const lastDay = getLastDayOfMonth(startMonthSelect.value);
                endDayInput.value = lastDay;
            }
        } 
        else if (freq === 'annually') {
            // Use parent's end month/day
            if (endMonthSelect) {
                endMonthSelect.disabled = true;
                endMonthSelect.value = "<?= htmlspecialchars($parentEndMonth) ?>";
            }
            if (endDayInput) {
                endDayInput.disabled = true;
                endDayInput.value = "<?= htmlspecialchars($parentEndDay) ?>";
            }
        } 
        else if (freq === 'quarterly' || freq === 'semi-annually') {
            const startMonthName = startMonthSelect.value;
            if (!startMonthName) return;

            const startIdx = getMonthIndex(startMonthName);
            const range = freq === 'quarterly' ? 3 : 6;

            Array.from(endMonthSelect.options).forEach(opt => {
                if (!opt.value) return;
                const optIdx = getMonthIndex(opt.value);
                if (optIdx >= startIdx && optIdx < startIdx + range) {
                    opt.disabled = false;
                    opt.hidden = false;
                } else {
                    opt.disabled = true;
                    opt.hidden = true;
                }
            });

            if (endMonthSelect.value && endMonthSelect.options[endMonthSelect.selectedIndex].disabled) {
                endMonthSelect.value = startMonthName;
            }

            resetEndDay();
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        const freqSelect = document.querySelector('#frequency_monitoring');
        if (freqSelect) {
            freqSelect.addEventListener('change', updateDurationFields);
        }

        const startMonth = document.querySelector('#start_month');
        if (startMonth) {
            startMonth.addEventListener('change', updateDurationFields);
        }

        const startDay = document.querySelector('#start_day');
        if (startDay) {
            startDay.addEventListener('input', updateDurationFields);
        }

        const endMonth = document.querySelector('#end_month');
        if (endMonth) {
            endMonth.addEventListener('change', updateDurationFields);
        }

        // NEW: Dynamic max for end_day based on selected end_month
        if (endMonth && endDay) {
            endMonth.addEventListener('change', () => {
                const month = endMonth.value;
                if (!month) return;
                const year = new Date().getFullYear();
                const monthNum = new Date(`${month} 1, ${year}`).getMonth() + 1;
                const lastDay = new Date(year, monthNum, 0).getDate();
                endDay.max = lastDay;
                if (endDay.value > lastDay) endDay.value = lastDay;
            });
        }

        updateDurationFields();
        syncPreviews();
        setTimeout(syncPreviews, 100);
    });
    </script>

</body>
</html>