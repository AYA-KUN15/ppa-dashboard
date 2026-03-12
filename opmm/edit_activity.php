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

$parentStart = $parent['implementation_start'];
$parentEnd   = $parent['implementation_end'];
$parentMonth = date('F', strtotime($parentStart));
$parentYear  = date('Y', strtotime($parentStart));
$isSingleMonth = (date('Y-m', strtotime($parentStart)) === date('Y-m', strtotime($parentEnd)));

$daysInMonth = cal_days_in_month(CAL_GREGORIAN, date('n', strtotime($parentStart)), $parentYear);

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
    "Industry, Innovation and Infrastructure",   // with comma - correct
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
    $frequency_monitoring = trim($_POST['frequency_monitoring'] ?? '');
    $offices       = trim($_POST['offices'] ?? '');
    $programs      = trim($_POST['programs'] ?? '');
    $beneficiaries = trim($_POST['beneficiaries'] ?? '[]');

    $start_month = $isSingleMonth ? $parentMonth : trim($_POST['start_month'] ?? $parentMonth);
    $start_day   = (int)($_POST['start_day'] ?? date('j', strtotime($entry['implementation_start'])));

    $end_month   = $isSingleMonth ? $parentMonth : trim($_POST['end_month'] ?? $parentMonth);
    $end_day     = (int)($_POST['end_day'] ?? date('j', strtotime($entry['implementation_end'])));

    if (empty($activity_name) || $start_day < 1 || $start_day > $daysInMonth ||
        empty($type_agenda) || empty($sdg_goals) || empty($frequency_monitoring) ||
        empty($offices) || empty($programs) || $beneficiaries === '[]') {
        $error = 'Please fill all required fields. Start day must be between 1 and ' . $daysInMonth . ' for ' . $parentMonth . '.';
    } elseif (!$isSingleMonth && ($end_day < 1 || $end_day > $daysInMonth)) {
        $error = 'End day must be between 1 and ' . $daysInMonth . '.';
    } elseif (!$isSingleMonth && $end_day < $start_day && $end_month === $start_month) {
        $error = 'End day cannot be before start day in the same month.';
    } else {
        $startDate = date('Y-m-d', strtotime("$start_month $start_day $parentYear"));

        if ($isSingleMonth && $end_day === 0) {
            $endDate = $startDate;
        } else {
            $endDate = date('Y-m-d', strtotime("$end_month $end_day $parentYear"));
        }

        try {
            $stmt = $pdo->prepare("
                UPDATE activity_entries 
                SET activity_name = ?, implementation_start = ?, implementation_end = ?,
                    type_of_extension_service_agenda = ?, sdg_goals = ?, frequency_monitoring = ?,
                    offices_involved = ?, programs_involved = ?, beneficiaries_json = ?,
                    status = 'active', updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $activity_name, $startDate, $endDate,
                $type_agenda, $sdg_goals, $frequency_monitoring,
                $offices, $programs, $beneficiaries, $id
            ]);

            // After successful edit, force parent project back to 'active'
            $revertProj = $pdo->prepare("
                UPDATE project_entries 
                SET status = 'active', 
                    updated_at = NOW() 
                WHERE id = ?
            ");
            $revertProj->execute([$project_id]);

            if ($stmt->rowCount() > 0) {
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
        <h1>Edit Activity</h1>

        <?php if ($error): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <form method="POST" id="edit-form">
            <label for="activity_name">Activity Name *</label>
            <input type="text" id="activity_name" name="activity_name" value="<?= htmlspecialchars($entry['activity_name'] ?? '') ?>" required>

            <label>Type of Extension Service Agenda *</label>
            <button type="button" onclick="openModal('type-modal')">Select Types</button>
            <div id="selected-types" style="margin: 8px 0; min-height: 40px; border: 1px solid #ccc; padding: 8px; border-radius: 4px;"></div>
            <input type="hidden" name="type_agenda" id="type-hidden" value="<?= htmlspecialchars($entry['type_of_extension_service_agenda'] ?? '') ?>">

            <label>Sustainable Development Goals *</label>
            <button type="button" onclick="openModal('sdg-modal')">Select SDGs</button>
            <div id="selected-sdgs" style="margin: 8px 0; min-height: 40px; border: 1px solid #ccc; padding: 8px; border-radius: 4px;"></div>
            <input type="hidden" name="sdg_goals" id="sdg-hidden" value="<?= htmlspecialchars($entry['sdg_goals'] ?? '') ?>">

            <label>Frequency of Monitoring *</label>
            <select name="frequency_monitoring" required style="padding: 10px; border: 1px solid #ccc; border-radius: 4px; width: 100%; max-width: 400px;">
                <option value="">Select Frequency</option>
                <option value="Monthly"    <?= ($entry['frequency_monitoring'] ?? '') === 'Monthly'    ? 'selected' : '' ?>>Monthly</option>
                <option value="Quarterly"  <?= ($entry['frequency_monitoring'] ?? '') === 'Quarterly'  ? 'selected' : '' ?>>Quarterly</option>
                <option value="Semi-Annually" <?= ($entry['frequency_monitoring'] ?? '') === 'Semi-Annually' ? 'selected' : '' ?>>Semi-Annually</option>
                <option value="Annually"   <?= ($entry['frequency_monitoring'] ?? '') === 'Annually'   ? 'selected' : '' ?>>Annually</option>
            </select>

            <label>Offices Involved *</label>
            <button type="button" onclick="openModal('offices-modal')">Select Offices</button>
            <div id="selected-offices" style="margin: 8px 0; min-height: 40px; border: 1px solid #ccc; padding: 8px; border-radius: 4px;"></div>
            <input type="hidden" name="offices" id="offices-hidden" value="<?= htmlspecialchars($entry['offices_involved'] ?? '') ?>">

            <label>Programs Involved *</label>
            <button type="button" onclick="openModal('programs-modal')">Select Programs</button>
            <div id="selected-programs" style="margin: 8px 0; min-height: 40px; border: 1px solid #ccc; padding: 8px; border-radius: 4px;"></div>
            <input type="hidden" name="programs" id="programs-hidden" value="<?= htmlspecialchars($entry['programs_involved'] ?? '') ?>">

            <label>Beneficiaries *</label>
            <button type="button" onclick="openBeneficiariesModal()">Select Beneficiaries</button>
            <div id="selected-beneficiaries" style="margin: 8px 0; min-height: 40px; border: 1px solid #ccc; padding: 8px; border-radius: 4px;"></div>
            <input type="hidden" name="beneficiaries" id="beneficiaries-hidden" value="<?= htmlspecialchars($entry['beneficiaries_json'] ?? '[]') ?>">

            <label>Implementation Duration *</label>
            <div style="display: flex; flex-direction: column; gap: 16px; max-width: 500px;">
                <div style="display: flex; gap: 16px; align-items: center; flex-wrap: wrap;">
                    <span style="font-weight: 500; min-width: 60px;">Start:</span>

                    <?php if ($isSingleMonth): ?>
                        <input type="text" value="<?= htmlspecialchars($parentMonth) ?>" readonly 
                               style="flex: 1; min-width: 140px; background: #f0f0f0; cursor: not-allowed; padding: 10px; border: 1px solid #ccc; border-radius: 4px;" />
                        <input type="number" name="start_day" min="1" max="<?= $daysInMonth ?>" 
                               placeholder="Day" value="<?= htmlspecialchars(date('j', strtotime($entry['implementation_start']))) ?>" required 
                               style="flex: 1; min-width: 100px; padding: 10px; border: 1px solid #ccc; border-radius: 4px;" />
                    <?php else: ?>
                        <select name="start_month" class="duration-month" required style="flex: 1; min-width: 140px; padding: 10px; border: 1px solid #ccc; border-radius: 4px;">
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
                        <select name="end_month" class="duration-month" required style="flex: 1; min-width: 140px; padding: 10px; border: 1px solid #ccc; border-radius: 4px;">
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

            <button type="submit" id="save-btn" disabled><b>Save Changes</b></button>
        </form>
    </main>

    <!-- Type Modal - STATIC + robust matching -->
    <div id="type-modal" class="modal-overlay">
        <div class="modal-box">
            <span class="close-modal" onclick="closeModal('type-modal')">×</span>
            <h2>Select Type of Extension Service Agenda</h2>
            <div style="max-height: 400px; overflow-y: auto; padding: 12px;">
                <?php
                $parentTypeStr = $parent['type_of_extension_service_agenda'] ?? '';
                $norm1 = strtolower(str_replace(' ', '', $parentTypeStr));
                $norm2 = strtolower(str_replace([',', ' '], '', $parentTypeStr));
                $norm3 = strtolower(str_replace(',', '', str_replace(' ', '', $parentTypeStr)));

                if (empty($parentTypeStr)) {
                    echo '<p>No types selected in parent project.</p>';
                } else {
                    foreach ($fullTypeOptions as $opt) {
                        $n1 = strtolower(str_replace(' ', '', $opt));
                        $n2 = strtolower(str_replace([',', ' '], '', $opt));
                        $n3 = strtolower(str_replace(',', '', str_replace(' ', '', $opt)));

                        if (strpos($norm1, $n1) !== false ||
                            strpos($norm2, $n2) !== false ||
                            strpos($norm3, $n3) !== false) {
                            echo '
                            <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px; border-radius: 6px;">
                                ' . htmlspecialchars($opt) . '
                                <input type="checkbox" value="' . htmlspecialchars($opt) . '">
                            </label>';
                        }
                    }
                }
                ?>
            </div>
            <div class="modal-actions">
                <button onclick="saveModalSelections('type')">Save</button>
                <button onclick="closeModal('type-modal')">Cancel</button>
            </div>
        </div>
    </div>

    <!-- SDG Modal - STATIC + robust matching -->
    <div id="sdg-modal" class="modal-overlay">
        <div class="modal-box">
            <span class="close-modal" onclick="closeModal('sdg-modal')">×</span>
            <h2>Select Sustainable Development Goals</h2>
            <div style="max-height: 400px; overflow-y: auto; padding: 12px;">
                <?php
                $parentSdgStr = $parent['sdg_goals'] ?? '';
                $norm1 = strtolower(str_replace(' ', '', $parentSdgStr));
                $norm2 = strtolower(str_replace([',', ' '], '', $parentSdgStr));
                $norm3 = strtolower(str_replace(',', '', str_replace(' ', '', $parentSdgStr)));

                if (empty($parentSdgStr)) {
                    echo '<p>No SDGs selected in parent project.</p>';
                } else {
                    foreach ($fullSdgOptions as $opt) {
                        $n1 = strtolower(str_replace(' ', '', $opt));
                        $n2 = strtolower(str_replace([',', ' '], '', $opt));
                        $n3 = strtolower(str_replace(',', '', str_replace(' ', '', $opt)));

                        if (strpos($norm1, $n1) !== false ||
                            strpos($norm2, $n2) !== false ||
                            strpos($norm3, $n3) !== false) {
                            echo '
                            <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px; border-radius: 6px;">
                                ' . htmlspecialchars($opt) . '
                                <input type="checkbox" value="' . htmlspecialchars($opt) . '">
                            </label>';
                        }
                    }
                }
                ?>
            </div>
            <div class="modal-actions">
                <button onclick="saveModalSelections('sdg')">Save</button>
                <button onclick="closeModal('sdg-modal')">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Offices Modal - STATIC -->
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

    <!-- Programs Modal - STATIC -->
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

    function saveModalSelections(type) {
        const modal = document.getElementById(type + '-modal');
        const checkboxes = modal.querySelectorAll('input[type="checkbox"]:checked');
        const values = Array.from(checkboxes).map(cb => cb.value.trim());

        const hidden = document.getElementById(type + '-hidden');
        const display = document.getElementById('selected-' + type);

        if (hidden) {
            hidden.value = values.join(', ');
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

    function restoreCheckedState(type) {
        const modal = document.getElementById(type + '-modal');
        const hidden = document.getElementById(type + '-hidden');
        const currentRaw = hidden?.value?.trim() || '';

        // Multiple normalization strategies
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
            preview.textContent = count > 0 ? `${count} type(s) selected` : 'None selected';
        }

        closeModal('beneficiaries-modal');
        syncPreviews();
        checkFormChanges();
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
                display.textContent = val || 'None';
            }
        });

        const benHidden = document.getElementById('beneficiaries-hidden');
        const benDisplay = document.getElementById('selected-beneficiaries');
        if (benHidden && benDisplay) {
            try {
                const data = JSON.parse(benHidden.value || '[]');
                const count = data.length;
                benDisplay.textContent = count > 0 ? `${count} type(s) selected` : 'None selected';
            } catch (e) {
                benDisplay.textContent = 'None selected';
            }
        }
    }

    let originalValues = {};

    function checkFormChanges() {
        const currentValues = {
            activity_name: document.querySelector('[name="activity_name"]').value.trim(),
            type_agenda: document.getElementById('type-hidden')?.value.trim() || '',
            sdg_goals: document.getElementById('sdg-hidden')?.value.trim() || '',
            frequency_monitoring: document.querySelector('[name="frequency_monitoring"]')?.value.trim() || '',
            offices: document.getElementById('offices-hidden')?.value.trim() || '',
            programs: document.getElementById('programs-hidden')?.value.trim() || '',
            beneficiaries: document.getElementById('beneficiaries-hidden')?.value.trim() || '',
            start_day: document.querySelector('[name="start_day"]')?.value.trim() || '',
            start_month: document.querySelector('[name="start_month"]')?.value.trim() || '',
            end_day: document.querySelector('[name="end_day"]')?.value.trim() || '',
            end_month: document.querySelector('[name="end_month"]')?.value.trim() || ''
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
            activity_name: document.querySelector('[name="activity_name"]').value.trim(),
            type_agenda: document.getElementById('type-hidden').value.trim(),
            sdg_goals: document.getElementById('sdg-hidden').value.trim(),
            frequency_monitoring: document.querySelector('[name="frequency_monitoring"]').value.trim(),
            offices: document.getElementById('offices-hidden').value.trim(),
            programs: document.getElementById('programs-hidden').value.trim(),
            beneficiaries: document.getElementById('beneficiaries-hidden').value.trim(),
            start_day: document.querySelector('[name="start_day"]').value.trim(),
            start_month: document.querySelector('[name="start_month"]')?.value.trim() || '',
            end_day: document.querySelector('[name="end_day"]').value.trim(),
            end_month: document.querySelector('[name="end_month"]')?.value.trim() || ''
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

    // Duration + Frequency restrictions (unchanged from your original)
    function updateDurationFields() {
        const freqSelect = document.querySelector('select[name="frequency_monitoring"]');
        if (!freqSelect) return;

        const freq = freqSelect.value.trim().toLowerCase();

        const startMonthSelect = document.querySelector('select[name="start_month"]');
        const startDayInput   = document.querySelector('input[name="start_day"]');
        const endMonthSelect  = document.querySelector('select[name="end_month"]');
        const endDayInput     = document.querySelector('input[name="end_day"]');

        // Reset all restrictions first
        if (endMonthSelect) {
            endMonthSelect.disabled = false;
            Array.from(endMonthSelect.options).forEach(opt => {
                opt.disabled = false;
                opt.hidden = false;
            });
        }
        if (endDayInput)   endDayInput.disabled = false;
        if (startDayInput) startDayInput.disabled = false;

        if (!startMonthSelect || !endMonthSelect) return;

        const monthNames = ["January","February","March","April","May","June",
                            "July","August","September","October","November","December"];

        const getMonthIndex = (monthName) => monthNames.indexOf(monthName);

        function setEndDayToLastValid() {
            if (!endMonthSelect.value || !endDayInput) return;
            const year = new Date().getFullYear();
            const monthNum = getMonthIndex(endMonthSelect.value) + 1;
            const lastDay = new Date(year, monthNum, 0).getDate();
            endDayInput.value = Math.min(parseInt(startDayInput?.value || lastDay), lastDay);
        }

        if (freq === 'monthly') {
            endMonthSelect.disabled = true;
            endDayInput.disabled = true;

            if (startMonthSelect.value && startDayInput) {
                const startMonthIdx = getMonthIndex(startMonthSelect.value);
                const year = new Date().getFullYear();
                const daysInMonth = new Date(year, startMonthIdx + 1, 0).getDate();

                startDayInput.addEventListener('input', () => {
                    if (startDayInput.value) {
                        endDayInput.value = daysInMonth;
                    }
                });

                if (startDayInput.value) {
                    endDayInput.value = daysInMonth;
                }
            }
        } 
        else if (freq === 'annually') {
            endMonthSelect.disabled = true;
            endDayInput.disabled = true;

            endMonthSelect.value = "December";
            endDayInput.value = "31";
        } 
        else if (freq === 'quarterly' || freq === 'semi-annually') {
            const startMonthName = startMonthSelect.value;
            if (!startMonthName) return;

            const startIdx = getMonthIndex(startMonthName);
            const rangeMonths = freq === 'quarterly' ? 3 : 6;

            Array.from(endMonthSelect.options).forEach(opt => {
                if (!opt.value) return;
                const optIdx = getMonthIndex(opt.value);
                if (optIdx >= startIdx && optIdx < startIdx + rangeMonths) {
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

            endMonthSelect.addEventListener('change', setEndDayToLastValid);
            setEndDayToLastValid();
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        const freqSelect = document.querySelector('select[name="frequency_monitoring"]');
        if (freqSelect) {
            freqSelect.addEventListener('change', updateDurationFields);
        }

        updateDurationFields();

        const startMonth = document.querySelector('select[name="start_month"]');
        if (startMonth) {
            startMonth.addEventListener('change', updateDurationFields);
        }

        const startDay = document.querySelector('input[name="start_day"]');
        if (startDay) {
            startDay.addEventListener('input', updateDurationFields);
        }
    });
    </script>

</body>
</html>