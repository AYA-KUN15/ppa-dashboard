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

$error = '';
$entry = null;

try {
    $stmt = $pdo->prepare("
        SELECT title, location, duration_start, duration_end,
               type_of_extension_service_agenda, sdg_goals, offices_involved,
               programs_involved, partner_agencies, beneficiaries_json,
               total_cost, source_of_fund
        FROM program_entries WHERE id = ?
    ");
    $stmt->execute([$id]);
    $entry = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$entry) {
        $error = 'Program not found.';
    }
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    $title = trim($_POST['title'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $duration_start = $_POST['duration_start'] ?? '';
    $duration_end = $_POST['duration_end'] ?? '';
    $type = trim($_POST['type_of_extension_service_agenda'] ?? '');
    $sdg = trim($_POST['sdg_goals'] ?? '');
    $offices = trim($_POST['offices_involved'] ?? '');
    $programs = trim($_POST['programs_involved'] ?? '');
    $partners = trim($_POST['partner_agencies'] ?? '');
    $beneficiaries_json = trim($_POST['beneficiaries_json'] ?? '[]');
    $total_cost = (float)($_POST['total_cost'] ?? 0);
    $source_fund = trim($_POST['source_of_fund'] ?? '');

    if (empty($title) || empty($location) || empty($duration_start) || empty($duration_end) ||
        empty($type) || empty($sdg) || empty($offices) || empty($programs) ||
        empty($partners) || $total_cost <= 0 || empty($source_fund)) {
        $error = 'Please fill all required fields.';
    } else {
        try {
            $stmt = $pdo->prepare("
                UPDATE program_entries 
                SET title = ?, location = ?, duration_start = ?, duration_end = ?,
                    type_of_extension_service_agenda = ?, sdg_goals = ?, offices_involved = ?,
                    programs_involved = ?, partner_agencies = ?, beneficiaries_json = ?, 
                    total_cost = ?, source_of_fund = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $title, $location, $duration_start, $duration_end,
                $type, $sdg, $offices, $programs, $partners,
                $beneficiaries_json, $total_cost, $source_fund, $id
            ]);

            if ($stmt->rowCount() > 0) {
                header("Location: list.php?success=updated");
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
    <title>Edit Program</title>
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
            <a href="list.php" class="nav-button">PPA</a>
            <a href="../logout.php" class="nav-button logout">Logout</a>
        </nav>
    </header>

    <main class="dashboard-content">
        <h1>Edit Program</h1>

        <?php if ($error): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <?php if ($entry): ?>
            <form method="POST">
                <label for="title">Title *</label>
                <input type="text" id="title" name="title" value="<?= htmlspecialchars($entry['title']) ?>" required>

                <label for="location">Location *</label>
                <input type="text" id="location" name="location" value="<?= htmlspecialchars($entry['location']) ?>" required>

                <label>Duration *</label>
                <div style="display: flex; gap: 16px; align-items: center;">
                    <input type="date" name="duration_start" value="<?= htmlspecialchars($entry['duration_start']) ?>" required>
                    <span>to</span>
                    <input type="date" name="duration_end" value="<?= htmlspecialchars($entry['duration_end']) ?>" required>
                </div>

                <!-- Type -->
                <label>Type of Extension Service Agenda *</label>
                <button type="button" onclick="openModal('type-modal')">Select Types</button>
                <div id="selected-types" style="margin: 8px 0; min-height: 40px; border: 1px solid #ccc; padding: 8px; border-radius: 4px;">
                    <?= htmlspecialchars($entry['type_of_extension_service_agenda'] ?? 'None') ?>
                </div>
                <input type="hidden" name="type_of_extension_service_agenda" id="type-hidden" value="<?= htmlspecialchars($entry['type_of_extension_service_agenda'] ?? '') ?>">

                <!-- SDG -->
                <label>Sustainable Development Goals *</label>
                <button type="button" onclick="openModal('sdg-modal')">Select SDGs</button>
                <div id="selected-sdgs" style="margin: 8px 0; min-height: 40px; border: 1px solid #ccc; padding: 8px; border-radius: 4px;">
                    <?= htmlspecialchars($entry['sdg_goals'] ?? 'None') ?>
                </div>
                <input type="hidden" name="sdg_goals" id="sdg-hidden" value="<?= htmlspecialchars($entry['sdg_goals'] ?? '') ?>">

                <!-- Beneficiaries -->
                <label>Beneficiaries *</label>
                <button type="button" onclick="openModal('beneficiaries-modal')">Manage Beneficiaries</button>
                <div id="selected-beneficiaries" style="margin: 8px 0; min-height: 40px; border: 1px solid #ccc; padding: 8px; border-radius: 4px;"></div>
                <input type="hidden" name="beneficiaries_json" id="beneficiaries-json" value="<?= htmlspecialchars($entry['beneficiaries_json'] ?? '[]') ?>">

                <label for="offices_involved">Offices/Colleges/Organizations Involved *</label>
                <input type="text" id="offices_involved" name="offices_involved" value="<?= htmlspecialchars($entry['offices_involved']) ?>" required>

                <label for="programs_involved">Programs Involved *</label>
                <input type="text" id="programs_involved" name="programs_involved" value="<?= htmlspecialchars($entry['programs_involved']) ?>" required>

                <label for="partner_agencies">Partner Agencies *</label>
                <input type="text" id="partner_agencies" name="partner_agencies" value="<?= htmlspecialchars($entry['partner_agencies']) ?>" required>

                <label for="total_cost">Total Cost *</label>
                <input type="number" id="total_cost" name="total_cost" step="0.01" min="0" value="<?= htmlspecialchars($entry['total_cost']) ?>" required>

                <label for="source_of_fund">Source of Fund *</label>
                <select id="source_of_fund" name="source_of_fund" required>
                    <option value="">Select Source</option>
                    <option value="MDS" <?= $entry['source_of_fund'] === 'MDS' ? 'selected' : '' ?>>MDS</option>
                    <option value="STF" <?= $entry['source_of_fund'] === 'STF' ? 'selected' : '' ?>>STF</option>
                    <option value="Others" <?= $entry['source_of_fund'] === 'Others' ? 'selected' : '' ?>>Others</option>
                </select>

                <button type="submit">Save Changes</button>
            </form>
        <?php endif; ?>
    </main>

    <!-- Type Modal -->
    <div id="type-modal" class="modal-overlay">
        <div class="modal-box">
            <span class="close-modal" onclick="closeModal('type-modal')">×</span>
            <h2>Select Type of Extension Service Agenda</h2>
            <div style="max-height: 400px; overflow-y: auto; padding: 12px;">
                <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px; border-radius: 6px;">
                    BatStateU Inclusive Social Innovation for Regional Growth (BISIG) Program
                    <input type="checkbox" value="BatStateU Inclusive Social Innovation for Regional Growth (BISIG) Program">
                </label>
                <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px; border-radius: 6px;">
                    Livelihood and other Entrepreneurship related on Agri-Fisheries (LEAF)
                    <input type="checkbox" value="Livelihood and other Entrepreneurship related on Agri-Fisheries (LEAF)">
                </label>
                <!-- Add all other types here, same as your add.php -->
                <!-- ... -->
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
                <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px; border-radius: 6px;">
                    No Poverty
                    <input type="checkbox" value="No Poverty">
                </label>
                <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px; border-radius: 6px;">
                    Zero Hunger
                    <input type="checkbox" value="Zero Hunger">
                </label>
                <!-- Add all 17 SDGs here -->
                <!-- ... -->
            </div>
            <div class="modal-actions">
                <button onclick="saveModalSelections('sdg')">Save</button>
                <button onclick="closeModal('sdg-modal')">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Beneficiaries Modal -->
    <div id="beneficiaries-modal" class="modal-overlay">
        <div class="modal-box" style="max-width: 800px;">
            <span class="close-modal" onclick="closeModal('beneficiaries-modal')">×</span>
            <h2>Manage Beneficiaries</h2>
            <div id="beneficiary-rows" style="margin-bottom: 20px;"></div>
            <button type="button" onclick="addBeneficiaryRow()" 
                    style="margin-bottom: 16px; padding: 12px 20px; background: #c8102e; color: white; border: none; border-radius: 6px; cursor: pointer;">
                + Add Beneficiary Type
            </button>
            <div class="modal-actions" style="margin-top: 20px; display: flex; gap: 12px; justify-content: flex-end;">
                <button onclick="saveBeneficiaries()" 
                        style="padding: 12px 24px; background: #c8102e; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 500;">
                    Save
                </button>
                <button onclick="closeModal('beneficiaries-modal')" 
                        style="padding: 12px 24px; background: #c8102e; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 500;">
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
        const values = Array.from(checkboxes).map(cb => cb.value);
        const hidden = document.getElementById(type + '-hidden');
        const display = document.getElementById('selected-' + type + 's');

        hidden.value = values.join(', ');
        display.textContent = values.length > 0 ? values.join(', ') : 'None selected';
        closeModal(type + '-modal');
    }

    function addBeneficiaryRow(type = '', male = 0, female = 0) {
        const container = document.getElementById('beneficiary-rows');
        const row = document.createElement('div');
        row.className = 'beneficiary-row';
        row.style.display = 'flex';
        row.style.alignItems = 'center';
        row.style.gap = '12px';
        row.style.marginBottom = '16px';
        row.style.flexWrap = 'wrap';

        row.innerHTML = `
            <input type="text" 
                   placeholder="e.g., Farmers, Students, PWDs, Senior Citizens" 
                   value="${type}" 
                   class="beneficiary-type"
                   required
                   style="flex: 2; min-width: 220px;">

            <input type="number" 
                   placeholder="Male" 
                   value="${male}" 
                   min="0" 
                   class="beneficiary-male"
                   required
                   style="flex: 1; max-width: 100px;">

            <input type="number" 
                   placeholder="Female" 
                   value="${female}" 
                   min="0" 
                   class="beneficiary-female"
                   required
                   style="flex: 1; max-width: 100px;">

            <button type="button" 
                    onclick="this.closest('.beneficiary-row').remove();"
                    class="remove-btn">
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
        document.getElementById('beneficiaries-json').value = json;

        let summary = '';
        let total = 0;
        data.forEach(b => {
            summary += `${b.type}: ${b.male} male, ${b.female} female | `;
            total += b.male + b.female;
        });
        summary += `Total: ${total}`;
        document.getElementById('selected-beneficiaries').textContent = summary || 'None added';

        closeModal('beneficiaries-modal');
    }

    window.addEventListener('load', () => {
        // Pre-check existing values for Type, SDG
        ['type', 'sdg'].forEach(type => {
            const hidden = document.getElementById(type + '-hidden');
            if (hidden && hidden.value) {
                const values = hidden.value.split(', ');
                const modal = document.getElementById(type + '-modal');
                if (modal) {
                    const checkboxes = modal.querySelectorAll('input[type="checkbox"]');
                    checkboxes.forEach(cb => {
                        if (values.includes(cb.value.trim())) {
                            cb.checked = true;
                        }
                    });
                }
                document.getElementById('selected-' + type + 's').textContent = hidden.value || 'None selected';
            }
        });

        // Load beneficiaries
        const json = document.getElementById('beneficiaries-json')?.value || '[]';
        const data = JSON.parse(json);
        data.forEach(b => addBeneficiaryRow(b.type, b.male, b.female));
        saveBeneficiaries();
    });
    </script>
</body>
</html>