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
    SELECT title, location, duration_start, duration_end,
           type_of_extension_service_agenda, sdg_goals, offices_involved,
           programs_involved, partner_agencies, beneficiaries_json,
           total_cost, source_of_fund
    FROM program_entries WHERE id = ?
");
$stmt->execute([$id]);
$entry = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$entry) {
    die("Program not found.");
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
                // Reset status to 'active' after any successful edit
                $resetStmt = $pdo->prepare("UPDATE program_entries SET status = 'active', updated_at = NOW() WHERE id = ?");
                $resetStmt->execute([$id]);

                header("Location: list.php?success=updated");  // ← Changed from view.php to list.php
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
    ['url' => '../index.php', 'label' => 'Home',    'active' => false],
    ['url' => 'list.php',     'label' => 'PPA',     'active' => false],
];

?>

<?php include '../includes/header.php'; ?>

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

    <main class="dashboard-content">
        <h1>Edit Program</h1>

        <?php if ($error): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <form method="POST">
            <label for="title">Title *</label>
            <input type="text" id="title" name="title" value="<?= htmlspecialchars($entry['title'] ?? '') ?>" required>

            <label for="location">Location *</label>
            <input type="text" id="location" name="location" value="<?= htmlspecialchars($entry['location'] ?? '') ?>" required>

            <label>Duration *</label>
            <div style="display: flex; gap: 16px; align-items: center;">
                <input type="date" name="duration_start" value="<?= htmlspecialchars($entry['duration_start'] ?? '') ?>" required>
                <span>to</span>
                <input type="date" name="duration_end" value="<?= htmlspecialchars($entry['duration_end'] ?? '') ?>" required>
            </div>

            <label>Type of Extension Service Agenda * (select all that apply)</label>
            <button type="button" onclick="openModal('type-modal')">Select Types</button>
            <div id="selected-types" style="margin: 8px 0; min-height: 40px; border: 1px solid #ccc; padding: 8px; border-radius: 4px;">
                <?= htmlspecialchars($entry['type_of_extension_service_agenda'] ?? '') ?>
            </div>
            <input type="hidden" name="type_of_extension_service_agenda" id="type-hidden" value="<?= htmlspecialchars($entry['type_of_extension_service_agenda'] ?? '') ?>">

            <label>Sustainable Development Goals * (select all that apply)</label>
            <button type="button" onclick="openModal('sdg-modal')">Select SDGs</button>
            <div id="selected-sdgs" style="margin: 8px 0; min-height: 40px; border: 1px solid #ccc; padding: 8px; border-radius: 4px;">
                <?= htmlspecialchars($entry['sdg_goals'] ?? '') ?>
            </div>
            <input type="hidden" name="sdg_goals" id="sdg-hidden" value="<?= htmlspecialchars($entry['sdg_goals'] ?? '') ?>">

            <label>Beneficiaries * (add types and counts)</label>
            <button type="button" onclick="openModal('beneficiaries-modal')">Manage Beneficiaries</button>
            <div id="selected-beneficiaries" style="margin: 8px 0; min-height: 40px; border: 1px solid #ccc; padding: 8px; border-radius: 4px;">
                <?php
                $json = $entry['beneficiaries_json'] ?? '[]';
                $decoded = json_decode($json, true);
                $count = is_array($decoded) ? count($decoded) : 0;
                echo htmlspecialchars($count > 0 ? "$count type(s) selected" : '');
                ?>
            </div>
            <input type="hidden" name="beneficiaries_json" id="beneficiaries-json" value="<?= htmlspecialchars($entry['beneficiaries_json'] ?? '[]') ?>">

            <label for="offices_involved">Offices/Colleges/Organizations Involved *</label>
            <input type="text" id="offices_involved" name="offices_involved" value="<?= htmlspecialchars($entry['offices_involved'] ?? '') ?>" required>

            <label for="programs_involved">Programs Involved *</label>
            <input type="text" id="programs_involved" name="programs_involved" value="<?= htmlspecialchars($entry['programs_involved'] ?? '') ?>" required>

            <label for="partner_agencies">Partner Agencies *</label>
            <input type="text" id="partner_agencies" name="partner_agencies" value="<?= htmlspecialchars($entry['partner_agencies'] ?? '') ?>" required>

            <label>Source of Fund * (select all that apply)</label>
            <button type="button" onclick="openModal('source-modal')">Select Sources</button>
            <div id="selected-source" style="margin: 8px 0; min-height: 40px; border: 1px solid #ccc; padding: 8px; border-radius: 4px;">
                <?= htmlspecialchars($entry['source_of_fund'] ?? '') ?>
            </div>
            <input type="hidden" name="source_of_fund" id="source-hidden" value="<?= htmlspecialchars($entry['source_of_fund'] ?? '') ?>">

            <label for="total_cost">Total Cost *</label>
            <input type="number" id="total_cost" name="total_cost" step="0.01" min="0" value="<?= htmlspecialchars($entry['total_cost'] ?? '') ?>" required>

            <button type="submit">Save Changes</button>
        </form>
    </main>

    <!-- Type Modal -->
    <div id="type-modal" class="modal-overlay">
        <div class="modal-box">
            <span class="close-modal" onclick="closeModal('type-modal')">×</span>
            <h2>Select Type of Extension Service Agenda</h2>
            <div style="max-height: 400px; overflow-y: auto; padding: 12px;">
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px; border-radius: 6px;">
                        BatStateU Inclusive Social Innovation for Regional Growth (BISIG) Program
                        <input type="checkbox" value="BatStateU Inclusive Social Innovation for Regional Growth (BISIG) Program" <?= strpos($entry['type_of_extension_service_agenda'] ?? '', 'BISIG') !== false ? 'checked' : '' ?>>
                    </label>
                    <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px; border-radius: 6px;">
                        Livelihood and other Entrepreneurship related on Agri-Fisheries (LEAF)
                        <input type="checkbox" value="Livelihood and other Entrepreneurship related on Agri-Fisheries (LEAF)" <?= strpos($entry['type_of_extension_service_agenda'] ?? '', 'LEAF') !== false ? 'checked' : '' ?>>
                    </label>
                    <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px; border-radius: 6px;">
                        Environment and Natural resources Conservation, Protection and Rehabilitation Program
                        <input type="checkbox" value="Environment and Natural resources Conservation, Protection and Rehabilitation Program" <?= strpos($entry['type_of_extension_service_agenda'] ?? '', 'Environment') !== false ? 'checked' : '' ?>>
                    </label>
                    <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px; border-radius: 6px;">
                        Smart Analytics and Engineering Innovation
                        <input type="checkbox" value="Smart Analytics and Engineering Innovation" <?= strpos($entry['type_of_extension_service_agenda'] ?? '', 'Smart Analytics') !== false ? 'checked' : '' ?>>
                    </label>
                    <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px; border-radius: 6px;">
                        Adopt-a Municipality/Barangay/School/Social Development Thru BIDANI Implementation
                        <input type="checkbox" value="Adopt-a Municipality/Barangay/School/Social Development Thru BIDANI Implementation" <?= strpos($entry['type_of_extension_service_agenda'] ?? '', 'Adopt-a') !== false ? 'checked' : '' ?>>
                    </label>
                    <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px; border-radius: 6px;">
                        Community Outreach
                        <input type="checkbox" value="Community Outreach" <?= strpos($entry['type_of_extension_service_agenda'] ?? '', 'Community Outreach') !== false ? 'checked' : '' ?>>
                    </label>
                    <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px; border-radius: 6px;">
                        Technical-Vocational Education and Training (TVET) Program
                        <input type="checkbox" value="Technical-Vocational Education and Training (TVET) Program" <?= strpos($entry['type_of_extension_service_agenda'] ?? '', 'TVET') !== false ? 'checked' : '' ?>>
                    </label>
                    <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px; border-radius: 6px;">
                        Technology Transfer and Adoption/Utilization Program
                        <input type="checkbox" value="Technology Transfer and Adoption/Utilization Program" <?= strpos($entry['type_of_extension_service_agenda'] ?? '', 'Technology Transfer') !== false ? 'checked' : '' ?>>
                    </label>
                    <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px; border-radius: 6px;">
                        Technical Assistance and Advisory Services Program
                        <input type="checkbox" value="Technical Assistance and Advisory Services Program" <?= strpos($entry['type_of_extension_service_agenda'] ?? '', 'Technical Assistance') !== false ? 'checked' : '' ?>>
                    </label>
                    <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px; border-radius: 6px;">
                        Parents' Empowerment through Social Development (PESODEV)
                        <input type="checkbox" value="Parents' Empowerment through Social Development (PESODEV)" <?= strpos($entry['type_of_extension_service_agenda'] ?? '', 'PESODEV') !== false ? 'checked' : '' ?>>
                    </label>
                    <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px; border-radius: 6px;">
                        Gender and Development
                        <input type="checkbox" value="Gender and Development" <?= strpos($entry['type_of_extension_service_agenda'] ?? '', 'Gender and Development') !== false ? 'checked' : '' ?>>
                    </label>
                    <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px; border-radius: 6px;">
                        Disaster Risk Reduction and Management and Disaster Preparedness and Response/Climate Change Adaptation (DRMM and DPR/CCA)
                        <input type="checkbox" value="Disaster Risk Reduction and Management and Disaster Preparedness and Response/Climate Change Adaptation (DRMM and DPR/CCA)" <?= strpos($entry['type_of_extension_service_agenda'] ?? '', 'DRMM') !== false ? 'checked' : '' ?>>
                    </label>
                </div>
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
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px; border-radius: 6px;">
                        No Poverty
                        <input type="checkbox" value="No Poverty" <?= strpos($entry['sdg_goals'] ?? '', 'No Poverty') !== false ? 'checked' : '' ?>>
                    </label>
                    <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px; border-radius: 6px;">
                        Zero Hunger
                        <input type="checkbox" value="Zero Hunger" <?= strpos($entry['sdg_goals'] ?? '', 'Zero Hunger') !== false ? 'checked' : '' ?>>
                    </label>
                    <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px; border-radius: 6px;">
                        Good Health and Well-Being
                        <input type="checkbox" value="Good Health and Well-Being" <?= strpos($entry['sdg_goals'] ?? '', 'Good Health') !== false ? 'checked' : '' ?>>
                    </label>
                    <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px; border-radius: 6px;">
                        Quality Education
                        <input type="checkbox" value="Quality Education" <?= strpos($entry['sdg_goals'] ?? '', 'Quality Education') !== false ? 'checked' : '' ?>>
                    </label>
                    <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px; border-radius: 6px;">
                        Gender Equality
                        <input type="checkbox" value="Gender Equality" <?= strpos($entry['sdg_goals'] ?? '', 'Gender Equality') !== false ? 'checked' : '' ?>>
                    </label>
                    <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px; border-radius: 6px;">
                        Clean Water and Sanitation
                        <input type="checkbox" value="Clean Water and Sanitation" <?= strpos($entry['sdg_goals'] ?? '', 'Clean Water') !== false ? 'checked' : '' ?>>
                    </label>
                    <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px; border-radius: 6px;">
                        Affordable and Clean Energy
                        <input type="checkbox" value="Affordable and Clean Energy" <?= strpos($entry['sdg_goals'] ?? '', 'Affordable and Clean Energy') !== false ? 'checked' : '' ?>>
                    </label>
                    <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px; border-radius: 6px;">
                        Decent Work and Economic Growth
                        <input type="checkbox" value="Decent Work and Economic Growth" <?= strpos($entry['sdg_goals'] ?? '', 'Decent Work') !== false ? 'checked' : '' ?>>
                    </label>
                    <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px; border-radius: 6px;">
                        Industry, Innovation, and Infrastructure
                        <input type="checkbox" value="Industry, Innovation, and Infrastructure" <?= strpos($entry['sdg_goals'] ?? '', 'Industry') !== false ? 'checked' : '' ?>>
                    </label>
                    <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px; border-radius: 6px;">
                        Reduced Inequalities
                        <input type="checkbox" value="Reduced Inequalities" <?= strpos($entry['sdg_goals'] ?? '', 'Reduced Inequalities') !== false ? 'checked' : '' ?>>
                    </label>
                    <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px; border-radius: 6px;">
                        Sustainable Cities and Communities
                        <input type="checkbox" value="Sustainable Cities and Communities" <?= strpos($entry['sdg_goals'] ?? '', 'Sustainable Cities') !== false ? 'checked' : '' ?>>
                    </label>
                    <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px; border-radius: 6px;">
                        Responsible Consumption and Production
                        <input type="checkbox" value="Responsible Consumption and Production" <?= strpos($entry['sdg_goals'] ?? '', 'Responsible Consumption') !== false ? 'checked' : '' ?>>
                    </label>
                    <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px; border-radius: 6px;">
                        Climate Action
                        <input type="checkbox" value="Climate Action" <?= strpos($entry['sdg_goals'] ?? '', 'Climate Action') !== false ? 'checked' : '' ?>>
                    </label>
                    <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px; border-radius: 6px;">
                        Life Below Water
                        <input type="checkbox" value="Life Below Water" <?= strpos($entry['sdg_goals'] ?? '', 'Life Below Water') !== false ? 'checked' : '' ?>>
                    </label>
                    <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px; border-radius: 6px;">
                        Life on Land
                        <input type="checkbox" value="Life on Land" <?= strpos($entry['sdg_goals'] ?? '', 'Life on Land') !== false ? 'checked' : '' ?>>
                    </label>
                    <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px; border-radius: 6px;">
                        Peace, Justice and Strong Institutions
                        <input type="checkbox" value="Peace, Justice and Strong Institutions" <?= strpos($entry['sdg_goals'] ?? '', 'Peace, Justice') !== false ? 'checked' : '' ?>>
                    </label>
                    <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px; border-radius: 6px;">
                        Partnerships for the Goals
                        <input type="checkbox" value="Partnerships for the Goals" <?= strpos($entry['sdg_goals'] ?? '', 'Partnerships') !== false ? 'checked' : '' ?>>
                    </label>
                </div>
            </div>
            <div class="modal-actions">
                <button onclick="saveModalSelections('sdg')">Save</button>
                <button onclick="closeModal('sdg-modal')">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Source of Fund Modal -->
    <div id="source-modal" class="modal-overlay">
        <div class="modal-box">
            <span class="close-modal" onclick="closeModal('source-modal')">×</span>
            <h2>Select Source of Fund</h2>
            <div style="max-height: 400px; overflow-y: auto; padding: 12px;">
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px; border-radius: 6px;">
                        MDS
                        <input type="checkbox" value="MDS" <?= strpos($entry['source_of_fund'] ?? '', 'MDS') !== false ? 'checked' : '' ?>>
                    </label>
                    <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px; border-radius: 6px;">
                        STF
                        <input type="checkbox" value="STF" <?= strpos($entry['source_of_fund'] ?? '', 'STF') !== false ? 'checked' : '' ?>>
                    </label>
                    <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px; border-radius: 6px;">
                        Others
                        <input type="checkbox" value="Others" <?= strpos($entry['source_of_fund'] ?? '', 'Others') !== false ? 'checked' : '' ?>>
                    </label>
                </div>
            </div>
            <div class="modal-actions">
                <button onclick="saveModalSelections('source')">Save</button>
                <button onclick="closeModal('source-modal')">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Beneficiaries Modal -->
    <div id="beneficiaries-modal" class="modal-overlay">
        <div class="modal-box" style="max-width: 800px;">
            <span class="close-modal" onclick="closeModal('beneficiaries-modal')">×</span>
            <h2>Manage Beneficiaries</h2>
            <div id="beneficiary-rows" style="margin-bottom: 20px;">
            </div>
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
    let displayId = '';
    if (type === 'type') displayId = 'selected-types';
    if (type === 'sdg') displayId = 'selected-sdgs';
    if (type === 'source') displayId = 'selected-source';

    const display = document.getElementById(displayId);

    if (hidden) {
        hidden.value = values.join(', ');
    }
    if (display) {
        display.textContent = values.length ? values.join(', ') : '';
    }

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
        <input type="text" placeholder="e.g., Farmers, Students, PWDs, Senior Citizens" value="${type}" class="beneficiary-type" required style="flex: 2; min-width: 220px;">
        <input type="number" placeholder="Male" value="${male}" min="0" class="beneficiary-male" required style="flex: 1; max-width: 100px;">
        <input type="number" placeholder="Female" value="${female}" min="0" class="beneficiary-female" required style="flex: 1; max-width: 100px;">
        <button type="button" onclick="this.closest('.beneficiary-row').remove();" class="remove-btn">×</button>
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

        if (type) data.push({ type, male, female });
    });

    const json = JSON.stringify(data);
    document.getElementById('beneficiaries-json').value = json;

    let summary = '';
    let total = 0;
    data.forEach(b => {
        summary += `${b.type}: ${b.male} male, ${b.female} female | `;
        total += b.male + b.female;
    });
    summary += total > 0 ? `Total: ${total}` : '';

    document.getElementById('selected-beneficiaries').textContent = summary.trim();

    closeModal('beneficiaries-modal');
}

window.addEventListener('load', function() {
    const typeHidden = document.getElementById('type-hidden');
    if (typeHidden && typeHidden.value && document.getElementById('selected-types')) {
        document.getElementById('selected-types').textContent = typeHidden.value;
    }

    const sdgHidden = document.getElementById('sdg-hidden');
    if (sdgHidden && sdgHidden.value && document.getElementById('selected-sdgs')) {
        document.getElementById('selected-sdgs').textContent = sdgHidden.value;
    }

    const sourceHidden = document.getElementById('source-hidden');
    if (sourceHidden && sourceHidden.value && document.getElementById('selected-source')) {
        document.getElementById('selected-source').textContent = sourceHidden.value;
    }

    const json = document.getElementById('beneficiaries-json')?.value || '[]';
    try {
        const data = JSON.parse(json);
        data.forEach(b => addBeneficiaryRow(b.type, b.male, b.female));
        saveBeneficiaries();
    } catch (e) {
        console.error('Invalid beneficiaries JSON on load:', e);
    }
});
</script>
</body>
</html>