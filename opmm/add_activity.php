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
    SELECT project_title, date_of_implementation,
           type_of_extension_service_agenda, sdg_goals,
           offices_involved, programs_involved, beneficiaries_json
    FROM project_entries WHERE id = ?
");
$stmt->execute([$project_id]);
$parent = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$parent) {
    die("Parent project not found.");
}

$error = '';
$show_confirmation = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['confirm']) && isset($_SESSION['pending_activity'])) {
        $d = $_SESSION['pending_activity'];

        try {
            $stmt = $pdo->prepare("
                INSERT INTO activity_entries (
                    project_id, activity_name, date_of_implementation,
                    type_of_extension_service_agenda, sdg_goals,
                    offices_involved, programs_involved, beneficiaries_json
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $d['project_id'], $d['activity_name'], $d['date_of_implementation'],
                $d['type_of_extension_service_agenda'], $d['sdg_goals'],
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
        $impl_month = trim($_POST['impl_month'] ?? '');
        $impl_year = trim($_POST['impl_year'] ?? '');
        $type_agenda = !empty($_POST['type_agenda']) ? implode(', ', $_POST['type_agenda']) : '';
        $sdg_goals = !empty($_POST['sdg_goals']) ? implode(', ', $_POST['sdg_goals']) : '';
        $offices = !empty($_POST['offices']) ? implode(', ', $_POST['offices']) : '';
        $programs = !empty($_POST['programs']) ? implode(', ', $_POST['programs']) : '';
        $beneficiaries = !empty($_POST['beneficiaries']) ? json_encode($_POST['beneficiaries']) : '[]';

        if (empty($activity_name) || empty($impl_month) || empty($impl_year) ||
            empty($type_agenda) || empty($sdg_goals) || empty($offices) ||
            empty($programs) || $beneficiaries === '[]') {
            $error = 'Please fill all required fields.';
        } else {
            $date_of_implementation = "$impl_month $impl_year";

            $_SESSION['pending_activity'] = [
                'project_id' => $project_id,
                'activity_name' => $activity_name,
                'date_of_implementation' => $date_of_implementation,
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Activity</title>
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
        <h1>Add New Activity</h1>

        <?php if ($error): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <?php if ($show_confirmation): $d = $_SESSION['pending_activity']; ?>
            <div class="confirmation-box">
                <h2>Confirm Activity Details</h2>
                <p><strong>Activity Name:</strong> <?= htmlspecialchars($d['activity_name']) ?></p>
                <p><strong>Date of Implementation:</strong> <?= htmlspecialchars($d['date_of_implementation']) ?></p>
                <p><strong>Type of Extension Agenda:</strong> <?= htmlspecialchars($d['type_of_extension_service_agenda'] ?: 'None') ?></p>
                <p><strong>SDG Goals:</strong> <?= htmlspecialchars($d['sdg_goals'] ?: 'None') ?></p>
                <p><strong>Offices Involved:</strong> <?= htmlspecialchars($d['offices_involved'] ?: 'None') ?></p>
                <p><strong>Programs Involved:</strong> <?= htmlspecialchars($d['programs_involved'] ?: 'None') ?></p>
                <p><strong>Beneficiaries:</strong> <?= htmlspecialchars($d['beneficiaries_json'] !== '[]' ? $d['beneficiaries_json'] : 'None') ?></p>

                <form method="POST">
                    <button type="submit" name="confirm">Confirm & Save</button>
                    <a href="add_activity.php?project_id=<?= htmlspecialchars($project_id) ?>" class="cancel-link">Cancel</a>
                </form>
            </div>
        <?php else: ?>
            <form method="POST">
                <input type="hidden" name="project_id" value="<?= htmlspecialchars($project_id) ?>">

                <label for="activity_name">Activity Name *</label>
                <input type="text" id="activity_name" name="activity_name" placeholder="Enter activity name" required>

                <!-- Type -->
                <label>Type of Extension Service Agenda *</label>
                <button type="button" onclick="openModal('type-modal')">Select Types</button>
                <div id="selected-types" style="margin: 8px 0; min-height: 40px; border: 1px solid #ccc; padding: 8px; border-radius: 4px;"></div>
                <input type="hidden" name="type_agenda" id="type-hidden">

                <!-- SDG -->
                <label>Sustainable Development Goals *</label>
                <button type="button" onclick="openModal('sdg-modal')">Select SDGs</button>
                <div id="selected-sdgs" style="margin: 8px 0; min-height: 40px; border: 1px solid #ccc; padding: 8px; border-radius: 4px;"></div>
                <input type="hidden" name="sdg_goals" id="sdg-hidden">

                <!-- Offices -->
                <label>Offices Involved *</label>
                <button type="button" onclick="openModal('offices-modal')">Select Offices</button>
                <div id="selected-offices" style="margin: 8px 0; min-height: 40px; border: 1px solid #ccc; padding: 8px; border-radius: 4px;"></div>
                <input type="hidden" name="offices" id="offices-hidden">

                <!-- Programs -->
                <label>Programs Involved *</label>
                <button type="button" onclick="openModal('programs-modal')">Select Programs</button>
                <div id="selected-programs" style="margin: 8px 0; min-height: 40px; border: 1px solid #ccc; padding: 8px; border-radius: 4px;"></div>
                <input type="hidden" name="programs" id="programs-hidden">

                <!-- Beneficiaries -->
                <label>Beneficiaries *</label>
                <button type="button" onclick="openModal('beneficiaries-modal')">Select Beneficiaries</button>
                <div id="selected-beneficiaries" style="margin: 8px 0; min-height: 40px; border: 1px solid #ccc; padding: 8px; border-radius: 4px;"></div>
                <input type="hidden" name="beneficiaries" id="beneficiaries-hidden">

                <label>Date of Implementation *</label>
                <div style="display: flex; gap: 16px; align-items: center; flex-wrap: wrap;">
                    <select name="impl_month" required style="flex: 1; min-width: 160px;">
                        <option value="">Select Month</option>
                        <?php
                        $months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
                        $parentMonth = explode(' ', $parent['date_of_implementation'])[0] ?? '';
                        foreach ($months as $m) {
                            $selected = ($m === $parentMonth) ? 'selected' : '';
                            echo "<option value=\"$m\" $selected>$m</option>";
                        }
                        ?>
                    </select>

                    <select name="impl_year" required style="flex: 1; min-width: 120px;">
                        <option value="">Select Year</option>
                        <?php
                        $year = (int) date('Y', strtotime($parent['date_of_implementation']));
                        for ($y = $year; $y <= $year + 5; $y++) {
                            echo "<option value=\"$y\">$y</option>";
                        }
                        ?>
                    </select>
                </div>

                <button type="submit">Review & Add</button>
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
                if ($parent['type_of_extension_service_agenda']) {
                    $types = explode(', ', $parent['type_of_extension_service_agenda']);
                    foreach ($types as $t) {
                        $t = trim($t);
                        echo '
                        <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px; border-radius: 6px; transition: background 0.2s;">
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
                        echo '
                        <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px; border-radius: 6px; transition: background 0.2s;">
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
                        echo '
                        <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px; border-radius: 6px; transition: background 0.2s;">
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
                        echo '
                        <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px; border-radius: 6px; transition: background 0.2s;">
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
                if ($parent['beneficiaries_json']) {
                    $benefs = json_decode($parent['beneficiaries_json'], true);
                    if (is_array($benefs)) {
                        foreach ($benefs as $b) {
                            if (!empty($b['type'])) {
                                echo '
                                <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px; border-radius: 6px; transition: background 0.2s;">
                                    ' . htmlspecialchars($b['type']) . '
                                    <input type="checkbox" value="' . htmlspecialchars($b['type']) . '">
                                </label>';
                            }
                        }
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
        const values = Array.from(checkboxes).map(cb => cb.value);
        const hidden = document.getElementById(type + '-hidden');
        const display = document.getElementById('selected-' + type + 's');

        hidden.value = values.join(', ');
        display.textContent = values.length > 0 ? values.join(', ') : 'None selected';
        closeModal(type + '-modal');
    }
    </script>
</body>
</html>