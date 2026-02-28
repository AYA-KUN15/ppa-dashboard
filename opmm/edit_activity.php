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
    SELECT project_id, activity_name, date_of_implementation,
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
    SELECT project_title, date_of_implementation,
           type_of_extension_service_agenda, sdg_goals,
           offices_involved, programs_involved, beneficiaries_json
    FROM project_entries WHERE id = ?
");
$pStmt->execute([$project_id]);
$parent = $pStmt->fetch(PDO::FETCH_ASSOC);

if (!$parent) {
    die("Parent project not found.");
}

// Extract parent month and year
$parentDateParts = explode(' ', trim($parent['date_of_implementation']));
$parentMonth = $parentDateParts[0] ?? '';
$parentYear = (int)($parentDateParts[1] ?? date('Y'));

// Determine max days in month
$daysInMonth = 31;
if (in_array($parentMonth, ['April', 'June', 'September', 'November'])) {
    $daysInMonth = 30;
} elseif ($parentMonth === 'February') {
    if (($parentYear % 4 === 0 && $parentYear % 100 !== 0) || ($parentYear % 400 === 0)) {
        $daysInMonth = 29;
    } else {
        $daysInMonth = 28;
    }
}

// Current values: prefer POST on error, else DB
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$activity_name_val     = $isPost ? trim($_POST['activity_name'] ?? '') : htmlspecialchars($entry['activity_name'] ?? '');
$type_agenda_val       = $isPost ? trim($_POST['type_agenda'] ?? '') : htmlspecialchars($entry['type_of_extension_service_agenda'] ?? '');
$sdg_goals_val         = $isPost ? trim($_POST['sdg_goals'] ?? '') : htmlspecialchars($entry['sdg_goals'] ?? '');
$offices_val           = $isPost ? trim($_POST['offices'] ?? '') : htmlspecialchars($entry['offices_involved'] ?? '');
$programs_val          = $isPost ? trim($_POST['programs'] ?? '') : htmlspecialchars($entry['programs_involved'] ?? '');
$beneficiaries_val     = $isPost ? trim($_POST['beneficiaries'] ?? '') : htmlspecialchars($entry['beneficiaries_json'] ?? '');
$impl_month_val        = $isPost ? trim($_POST['impl_month'] ?? $parentMonth) : $parentMonth;
$impl_day_val          = $isPost ? (int)($_POST['impl_day'] ?? 1) : (int)(explode(' ', trim($entry['date_of_implementation']))[1] ?? 1);

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $activity_name   = trim($_POST['activity_name'] ?? '');
    $impl_month      = trim($_POST['impl_month'] ?? '');
    $impl_day        = (int)($_POST['impl_day'] ?? 1);
    $type_agenda     = trim($_POST['type_agenda'] ?? '');
    $sdg_goals       = trim($_POST['sdg_goals'] ?? '');
    $offices         = trim($_POST['offices'] ?? '');
    $programs        = trim($_POST['programs'] ?? '');
    $beneficiaries   = trim($_POST['beneficiaries'] ?? '');

    if (empty($activity_name) || 
        empty($impl_month) || $impl_day < 1 || $impl_day > $daysInMonth ||
        empty($type_agenda) || empty($sdg_goals) || empty($offices) ||
        empty($programs) || empty($beneficiaries)) {
        $error = 'Please fill all required fields (including at least one beneficiary).';
    } else {
        $date_of_implementation = "$impl_month $impl_day";

        try {
            $stmt = $pdo->prepare("
                UPDATE activity_entries 
                SET activity_name = ?, date_of_implementation = ?,
                    type_of_extension_service_agenda = ?, sdg_goals = ?,
                    offices_involved = ?, programs_involved = ?,
                    beneficiaries_json = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $activity_name, $date_of_implementation,
                $type_agenda, $sdg_goals, $offices, $programs,
                $beneficiaries, $id
            ]);

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
?>

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

    <header class="top-bar">
        <div class="logo-container">
            <img src="../assets/bsu-logo.jpg" alt="BatStateU Logo" class="logo">
            <span class="logo-text">PPA Dashboard</span>
        </div>
        <nav class="main-nav">
            <a href="../index.php" class="nav-button">Home</a>
            <a href="view_project.php?id=<?= htmlspecialchars($project_id) ?>" class="nav-button">Project</a>
            <a href="../logout.php" class="nav-button logout">Logout</a>
        </nav>
    </header>

    <main class="dashboard-content">
        <h1>Edit Activity</h1>

        <?php if ($error): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <form method="POST">
            <label for="activity_name">Activity Name *</label>
            <input type="text" id="activity_name" name="activity_name" value="<?= htmlspecialchars($activity_name_val) ?>" required>

            <!-- Type -->
            <label>Type of Extension Service Agenda *</label>
            <button type="button" onclick="openModal('type-modal')">Select Types</button>
            <div id="selected-types" style="margin: 8px 0; min-height: 40px; border: 1px solid #ccc; padding: 8px; border-radius: 4px;"></div>
            <input type="hidden" name="type_agenda" id="type-hidden" value="<?= htmlspecialchars($type_agenda_val) ?>">

            <!-- SDG -->
            <label>Sustainable Development Goals *</label>
            <button type="button" onclick="openModal('sdg-modal')">Select SDGs</button>
            <div id="selected-sdgs" style="margin: 8px 0; min-height: 40px; border: 1px solid #ccc; padding: 8px; border-radius: 4px;"></div>
            <input type="hidden" name="sdg_goals" id="sdg-hidden" value="<?= htmlspecialchars($sdg_goals_val) ?>">

            <!-- Offices -->
            <label>Offices Involved *</label>
            <button type="button" onclick="openModal('offices-modal')">Select Offices</button>
            <div id="selected-officess" style="margin: 8px 0; min-height: 40px; border: 1px solid #ccc; padding: 8px; border-radius: 4px;"></div>
            <input type="hidden" name="offices" id="offices-hidden" value="<?= htmlspecialchars($offices_val) ?>">

            <!-- Programs -->
            <label>Programs Involved *</label>
            <button type="button" onclick="openModal('programs-modal')">Select Programs</button>
            <div id="selected-programss" style="margin: 8px 0; min-height: 40px; border: 1px solid #ccc; padding: 8px; border-radius: 4px;"></div>
            <input type="hidden" name="programs" id="programs-hidden" value="<?= htmlspecialchars($programs_val) ?>">

            <!-- Beneficiaries -->
            <label>Beneficiaries *</label>
            <button type="button" onclick="openModal('beneficiaries-modal')">Select Beneficiaries</button>
            <div id="selected-beneficiariess" style="margin: 8px 0; min-height: 40px; border: 1px solid #ccc; padding: 8px; border-radius: 4px;"></div>
            <input type="hidden" name="beneficiaries" id="beneficiaries-hidden" value="<?= htmlspecialchars($beneficiaries_val) ?>">

            <label>Date of Implementation *</label>
            <div style="display: flex; gap: 16px; align-items: center; flex-wrap: wrap;">
                <select name="impl_month" required style="flex: 1; min-width: 160px; background: #f0f0f0; cursor: not-allowed;" readonly>
                    <option value="<?= htmlspecialchars($parentMonth) ?>" selected><?= htmlspecialchars($parentMonth) ?></option>
                </select>

                <input type="number" name="impl_day" min="1" max="<?= $daysInMonth ?>" placeholder="Day" 
                       value="<?= htmlspecialchars($impl_day_val) ?>" required 
                       style="flex: 1; max-width: 80px;">
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
        <div class="modal-box">
            <span class="close-modal" onclick="closeModal('beneficiaries-modal')">×</span>
            <h2>Select Beneficiaries</h2>
            <div style="max-height: 400px; overflow-y: auto; padding: 12px;">
                <?php
                $beneficiary_options = [];
                if ($parent['beneficiaries_json'] && $parent['beneficiaries_json'] !== '[]') {
                    $raw = $parent['beneficiaries_json'];
                    $benefs = json_decode($raw, true);

                    if (json_last_error() === JSON_ERROR_NONE && is_array($benefs)) {
                        if (!empty($benefs) && is_array($benefs[0]) && !empty($benefs[0]['type'])) {
                            foreach ($benefs as $b) {
                                if (!empty($b['type'])) {
                                    $type = trim($b['type']);
                                    if ($type) $beneficiary_options[] = $type;
                                }
                            }
                        } elseif (!empty($benefs) && is_string($benefs[0])) {
                            foreach ($benefs as $type) {
                                $type = trim($type);
                                if ($type) $beneficiary_options[] = $type;
                            }
                        }
                    } else {
                        $items = array_map('trim', explode(',', $raw));
                        foreach ($items as $type) {
                            if ($type) $beneficiary_options[] = $type;
                        }
                    }
                }

                if (!empty($beneficiary_options)) {
                    foreach ($beneficiary_options as $type) {
                        echo '<label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px; border-radius: 6px;">
                            ' . htmlspecialchars($type) . '
                            <input type="checkbox" value="' . htmlspecialchars($type) . '">
                        </label>';
                    }
                } else {
                    echo '<p>No beneficiaries available from parent project.</p>';
                }
                ?>
            </div>
            <div class="modal-actions">
                <button onclick="saveModalSelections('beneficiaries')">Save</button>
                <button onclick="closeModal('beneficiaries-modal')">Cancel</button>
            </div>
        </div>
    </div>

    <script>
    function openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('active');
            document.body.classList.add('modal-open');

            const type = modalId.replace('-modal', '');
            const hidden = document.getElementById(type + '-hidden');
            if (hidden && hidden.value.trim()) {
                const values = hidden.value.split(',').map(v => v.trim());
                const checkboxes = modal.querySelectorAll('input[type="checkbox"]');
                checkboxes.forEach(cb => {
                    cb.checked = values.includes(cb.value.trim());
                });
            }
        } else {
            console.error('Modal not found:', modalId);
        }
    }

    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) modal.classList.remove('active');
        document.body.classList.remove('modal-open');
    }

    function saveModalSelections(type) {
        const modal = document.getElementById(type + '-modal');
        if (!modal) return;

        const checkboxes = modal.querySelectorAll('input[type="checkbox"]:checked');
        const values = Array.from(checkboxes).map(cb => cb.value.trim());

        const hidden = document.getElementById(type + '-hidden');
        const display = document.getElementById('selected-' + type + 's');

        if (hidden) {
            hidden.value = values.join(', ');
        }

        if (display) {
            display.textContent = values.length > 0 ? values.join(', ') : '';
        }

        closeModal(type + '-modal');
    }

    window.addEventListener('load', () => {
        const fields = ['type', 'sdg', 'offices', 'programs', 'beneficiaries'];
        fields.forEach(type => {
            const hidden = document.getElementById(type + '-hidden');
            const display = document.getElementById('selected-' + type + 's');
            if (hidden && display) {
                let val = hidden.value.trim();
                display.textContent = val || '';
            }
        });
    });
    </script>
</body>
</html>