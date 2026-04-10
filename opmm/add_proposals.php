<?php
// add_proposals.php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php");
    exit;
}

require_once '../config/db.php';

// ==================== HARDCODED OPTIONS FOR MODALS ====================
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
    "No Poverty", "Zero Hunger", "Good Health and Well-Being", "Quality Education",
    "Gender Equality", "Clean Water and Sanitation", "Affordable and Clean Energy",
    "Decent Work and Economic Growth", "Industry, Innovation and Infrastructure",
    "Reduced Inequalities", "Sustainable Cities and Communities",
    "Responsible Consumption and Production", "Climate Action", "Life Below Water",
    "Life on Land", "Peace, Justice and Strong Institutions", "Partnerships for the Goals"
];
// =====================================================================

$error = '';
$show_confirmation = false;

// Restore form data when user cancels from confirmation
$formData = $_POST;
if (isset($_GET['cancel']) && $_GET['cancel'] === '1' && isset($_SESSION['pending_proposal'])) {
    $formData = $_SESSION['pending_proposal'];
    $formData['title']          = $formData['title'] ?? '';
    $formData['description']    = $formData['description'] ?? '';
    $formData['start_date']     = $formData['start_date'] ?? '';
    $formData['end_date']       = $formData['end_date'] ?? '';
    $formData['type']           = $formData['type'] ?? '';
    $formData['sdg']            = $formData['sdg'] ?? '';
    $formData['offices']        = $formData['offices'] ?? '';
    $formData['programs']       = $formData['programs'] ?? '';
    $formData['beneficiaries']  = $formData['beneficiaries_json'] ?? '[]';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['confirm']) && isset($_SESSION['pending_proposal'])) {
        $d = $_SESSION['pending_proposal'];

        try {
            $stmt = $pdo->prepare("
                INSERT INTO research_proposals (
                    title, description, start_date, end_date,
                    type_of_extension_service_agenda, sdg_goals,
                    offices_involved, programs_involved, beneficiaries_json
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $d['title'], 
                $d['description'], 
                $d['start_date'], 
                $d['end_date'],
                $d['type'], 
                $d['sdg'], 
                $d['offices'], 
                $d['programs'], 
                $d['beneficiaries_json']
            ]);

            unset($_SESSION['pending_proposal']);
            header("Location: list_proposals.php?success=added");
            exit;
        } catch (PDOException $e) {
            $error = 'Failed to save proposal: ' . $e->getMessage();
        }
    } else {
        $title          = trim($_POST['title'] ?? '');
        $description    = trim($_POST['description'] ?? '');
        $start_date     = $_POST['start_date'] ?? '';
        $end_date       = $_POST['end_date'] ?? '';
        $type           = trim($_POST['type_of_extension_service_agenda'] ?? '');
        $sdg            = trim($_POST['sdg_goals'] ?? '');
        $offices        = trim($_POST['offices_involved'] ?? '');
        $programs       = trim($_POST['programs_involved'] ?? '');
        $beneficiaries  = trim($_POST['beneficiaries_json'] ?? '[]');

        $benefData = json_decode($beneficiaries, true) ?? [];
        $totalBenef = 0;
        foreach ($benefData as $b) {
            $totalBenef += ($b['male'] ?? 0) + ($b['female'] ?? 0);
        }

        if (empty($title) || empty($start_date) || empty($end_date) ||
            empty($type) || empty($sdg) || empty($offices) || empty($programs) ||
            $beneficiaries === '[]' || $totalBenef === 0) {
            $error = 'Please fill all required fields (at least one beneficiary with total > 0).';
        } elseif (strtotime($end_date) < strtotime($start_date)) {
            $error = 'End date cannot be before start date.';
        } else {
            $_SESSION['pending_proposal'] = [
                'title'          => $title,
                'description'    => $description,
                'start_date'     => $start_date,
                'end_date'       => $end_date,
                'type'           => $type,
                'sdg'            => $sdg,
                'offices'        => $offices,
                'programs'       => $programs,
                'beneficiaries_json' => $beneficiaries
            ];
            $show_confirmation = true;
        }
    }
}

$nav_links = [
    ['url' => 'index.php', 'label' => 'Home', 'active' => false],
    ['url' => '/opmm/list.php', 'label' => 'PPA', 'active' => false],
    ['url' => '/opmm/list_proposals.php', 'label' => 'Proposals', 'active' => false],
];
?>

<?php include '../includes/header.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Research Proposal</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        /* Description textarea in form */
        #description {
            width: 100%;
            min-height: 78px;
            max-height: 150px;
            resize: vertical;
            overflow-y: auto;
            padding: 12px 14px;
            font-family: inherit;
            font-size: 1rem;
            line-height: 1.5;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            box-sizing: border-box;
        }

        #description:focus {
            border-color: #c8102e;
            outline: none;
        }

        /* Full description modal - multiple lines with vertical scroll */
.full-desc-content {
    white-space: pre-wrap;        /* Preserves line breaks and wraps long lines */
    word-wrap: break-word;
    max-height: 420px;
    overflow-y: auto;             /* Scroll top to bottom when long */
    padding: 18px;
    background: #f9fafb;
    border-radius: 8px;
    border: 1px solid #e5e7eb;
    line-height: 1.65;
    font-size: 1rem;
}
        }
    </style>
</head>
<body>

    <main class="dashboard-content add-program-page">
        <h1>Add New Research Proposal</h1>

        <?php if ($error): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <?php if ($show_confirmation): $d = $_SESSION['pending_proposal']; ?>
            <div class="confirmation-box">
                <h2>Confirm Proposal Details</h2>

                <div class="confirmation-grid">
                    <div class="confirm-item">
                        <strong>Title</strong>
                        <p><?= htmlspecialchars($d['title']) ?></p>
                    </div>

                    <div class="confirm-item">
                        <strong>Start Date</strong>
                        <p><?= htmlspecialchars(date('M d, Y', strtotime($d['start_date']))) ?></p>
                    </div>

                    <div class="confirm-item">
                        <strong>End Date</strong>
                        <p><?= htmlspecialchars(date('M d, Y', strtotime($d['end_date']))) ?></p>
                    </div>

                    <div class="confirm-item">
                        <strong>Type of Extension Service Agenda</strong>
                        <p><?= htmlspecialchars($d['type']) ?: 'None' ?></p>
                    </div>

                    <div class="confirm-item">
                        <strong>SDG Goals</strong>
                        <p><?= htmlspecialchars($d['sdg']) ?: 'None' ?></p>
                    </div>

                    <div class="confirm-item">
                        <strong>Offices Involved</strong>
                        <p><?= htmlspecialchars($d['offices']) ?: 'None' ?></p>
                    </div>

                    <div class="confirm-item">
                        <strong>Programs Involved</strong>
                        <p><?= htmlspecialchars($d['programs']) ?: 'None' ?></p>
                    </div>

                    <!-- Clickable description (fixed) -->
                    <div class="confirm-item full-span">
                        <strong>Description</strong>
                        <p>
                            <a href="javascript:void(0)" onclick="showFullDescription()" 
                               style="color: #c8102e; text-decoration: underline; cursor: pointer; font-weight: 500;">
                                Click here to view full description
                            </a>
                        </p>
                    </div>

                    <div class="confirm-item full-span">
                        <strong>Beneficiaries</strong>
                        <p>
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
                                    if ($m > 0 || $f > 0) $line .= ": $m M, $f F";
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
                    </div>
                </div>

                <div class="confirm-actions">
                    <form method="POST" style="display:inline;">
                        <button type="submit" name="confirm">Confirm & Save</button>
                    </form>
                    <a href="add_proposals.php?cancel=1" class="cancel-link">Cancel</a>
                </div>
            </div>
        <?php else: ?>
            <form method="POST" class="program-form" id="add-proposal-form">
                <div class="form-group">
                    <label for="title">Proposal Title *</label>
                    <input type="text" id="title" name="title" 
                           value="<?= htmlspecialchars($formData['title'] ?? '') ?>" required>
                </div>

                <div class="form-group full-span">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="3" 
                              placeholder="Enter detailed description of the research proposal..."><?= htmlspecialchars($formData['description'] ?? '') ?></textarea>
                </div>

                <div class="form-group full-span">
                    <label>Duration *</label>
                    <div class="date-group">
                        <input type="date" name="start_date" 
                               value="<?= htmlspecialchars($formData['start_date'] ?? '') ?>" required>
                        <span>to</span>
                        <input type="date" name="end_date" 
                               value="<?= htmlspecialchars($formData['end_date'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Type of Extension Service Agenda *</label>
                    <button type="button" onclick="openModal('type-modal')">Select Types</button>
                    <div id="selected-types" class="compact-preview">
                        <?= htmlspecialchars($formData['type'] ?? 'None') ?>
                    </div>
                    <input type="hidden" name="type_of_extension_service_agenda" id="type-hidden" 
                           value="<?= htmlspecialchars($formData['type'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label>Sustainable Development Goals *</label>
                    <button type="button" onclick="openModal('sdg-modal')">Select SDGs</button>
                    <div id="selected-sdgs" class="compact-preview">
                        <?= htmlspecialchars($formData['sdg'] ?? 'None') ?>
                    </div>
                    <input type="hidden" name="sdg_goals" id="sdg-hidden" 
                           value="<?= htmlspecialchars($formData['sdg'] ?? '') ?>">
                </div>

                <div class="form-group full-span">
                    <label>Beneficiaries *</label>
                    <button type="button" onclick="openModal('beneficiaries-modal')">Manage Beneficiaries</button>
                    <div id="selected-beneficiaries" class="compact-preview">None</div>
                    <input type="hidden" name="beneficiaries_json" id="beneficiaries-json" 
                           value="<?= htmlspecialchars($formData['beneficiaries'] ?? '[]') ?>">
                </div>

                <div class="form-group">
                    <label for="offices_involved">Offices Involved *</label>
                    <input type="text" id="offices_involved" name="offices_involved" 
                           value="<?= htmlspecialchars($formData['offices'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label for="programs_involved">Programs Involved *</label>
                    <input type="text" id="programs_involved" name="programs_involved" 
                           value="<?= htmlspecialchars($formData['programs'] ?? '') ?>" required>
                </div>

                <div class="full-span" style="text-align: center; margin-top: 24px;">
                    <button type="submit">Review & Add</button>
                </div>
            </form>
        <?php endif; ?>
    </main>

    <!-- Type Modal -->
    <div id="type-modal" class="modal-overlay">
        <div class="modal-box">
            <span class="close-modal" onclick="closeModal('type-modal')">×</span>
            <h2>Select Type of Extension Service Agenda</h2>
            <div style="max-height: 400px; overflow-y: auto; padding: 12px;">
                <?php foreach ($fullTypeOptions as $opt): ?>
                    <label class="modal-checkbox-label">
                        <span><?= htmlspecialchars($opt) ?></span>
                        <input type="checkbox" value="<?= htmlspecialchars($opt) ?>">
                    </label>
                <?php endforeach; ?>
            </div>
            <div class="modal-actions">
                <button onclick="saveModalSelections('type')" 
                        style="padding: 10px 20px; background: #c8102e; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 500;">
                    Save
                </button>
                <button onclick="closeModal('type-modal')" 
                        style="padding: 10px 20px; background: #6b7280; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 500;">
                    Cancel
                </button>
            </div>
        </div>
    </div>

    <!-- SDG Modal -->
    <div id="sdg-modal" class="modal-overlay">
        <div class="modal-box">
            <span class="close-modal" onclick="closeModal('sdg-modal')">×</span>
            <h2>Select Sustainable Development Goals</h2>
            <div style="max-height: 400px; overflow-y: auto; padding: 12px;">
                <?php foreach ($fullSdgOptions as $opt): ?>
                    <label class="modal-checkbox-label">
                        <span><?= htmlspecialchars($opt) ?></span>
                        <input type="checkbox" value="<?= htmlspecialchars($opt) ?>">
                    </label>
                <?php endforeach; ?>
            </div>
            <div class="modal-actions">
                <button onclick="saveModalSelections('sdg')" 
                        style="padding: 10px 20px; background: #c8102e; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 500;">
                    Save
                </button>
                <button onclick="closeModal('sdg-modal')" 
                        style="padding: 10px 20px; background: #6b7280; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 500;">
                    Cancel
                </button>
            </div>
        </div>
    </div>

    <!-- Beneficiaries Modal -->
    <div id="beneficiaries-modal" class="modal-overlay">
        <div class="modal-box" style="max-width: 800px;">
            <span class="close-modal" onclick="closeModal('beneficiaries-modal')">×</span>
            <h2>Manage Beneficiaries</h2>
            <div id="beneficiary-rows" style="margin-bottom: 16px;"></div>
            <button type="button" onclick="addBeneficiaryRow()" 
                    style="margin-bottom: 12px; padding: 10px 16px; background: #c8102e; color: white; border: none; border-radius: 4px; cursor: pointer;">
                + Add Beneficiary Type
            </button>
            <div class="modal-actions" style="margin-top: 12px; display: flex; gap: 12px; justify-content: flex-end;">
                <button onclick="saveBeneficiaries()" 
                        style="padding: 10px 20px; background: #c8102e; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 500;">
                    Save
                </button>
                <button onclick="closeModal('beneficiaries-modal')" 
                        style="padding: 10px 20px; background: #6b7280; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 500;">
                    Cancel
                </button>
            </div>
        </div>
    </div>

    <!-- Full Description Pop-up Modal (Improved) -->
    <div id="description-modal" class="modal-overlay">
        <div class="modal-box" style="max-width: 720px; max-height: 85vh;">
            <span class="close-modal" onclick="closeModal('description-modal')">×</span>
            <h2>Full Description</h2>
            <div id="full-description-content" class="full-desc-content"></div>
            <div style="text-align: center; margin-top: 20px;">
                <button onclick="closeModal('description-modal')" 
                        style="padding: 10px 24px; background: #6b7280; color: white; border: none; border-radius: 6px; cursor: pointer;">
                    Close
                </button>
            </div>
        </div>
    </div>

    <script>
    function openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) modal.classList.add('active');
        document.body.classList.add('modal-open');
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
        let displayId = '';
        if (type === 'type') displayId = 'selected-types';
        if (type === 'sdg') displayId = 'selected-sdgs';

        const display = document.getElementById(displayId);

        if (hidden) hidden.value = values.join(', ');
        if (display) display.textContent = values.length ? values.join(', ') : 'None';

        closeModal(type + '-modal');
    }

    // Improved showFullDescription function
    function showFullDescription() {
        const desc = <?= json_encode(isset($d) && $show_confirmation ? $d['description'] : '') ?> || 
                     (document.getElementById('description') ? document.getElementById('description').value.trim() : 'No description provided.');
        
        const contentDiv = document.getElementById('full-description-content');
        contentDiv.textContent = desc;   // This preserves line breaks naturally
        openModal('description-modal');
    }

    function addBeneficiaryRow(type = '', male = 0, female = 0) {
        const container = document.getElementById('beneficiary-rows');
        if (!container) return;

        const row = document.createElement('div');
        row.className = 'beneficiary-row';
        row.style.display = 'flex';
        row.style.alignItems = 'center';
        row.style.gap = '10px';
        row.style.marginBottom = '10px';
        row.style.flexWrap = 'wrap';

        row.innerHTML = `
            <input type="text" placeholder="e.g., Farmers, Students, PWDs" 
                   value="${type}" class="beneficiary-type" required style="flex: 2; min-width: 180px;">
            <input type="number" placeholder="Male" value="${male}" min="0" 
                   class="beneficiary-male" required style="flex: 1; max-width: 80px;">
            <input type="number" placeholder="Female" value="${female}" min="0" 
                   class="beneficiary-female" required style="flex: 1; max-width: 80px;">
            <button type="button" onclick="this.closest('.beneficiary-row').remove()" 
                    style="padding: 6px 10px; background: #c8102e; color: white; border: none; border-radius: 4px; cursor: pointer;">
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
            const type = inputs[0] ? inputs[0].value.trim() : '';
            const male = inputs[1] ? parseInt(inputs[1].value) || 0 : 0;
            const female = inputs[2] ? parseInt(inputs[2].value) || 0 : 0;

            if (type) {
                data.push({ type, male, female });
            }
        });

        const hidden = document.getElementById('beneficiaries-json');
        if (hidden) hidden.value = JSON.stringify(data);

        const preview = document.getElementById('selected-beneficiaries');
        if (preview) {
            let summary = '';
            let total = 0;
            data.forEach(b => {
                summary += `${b.type}: ${b.male} M, ${b.female} F | `;
                total += b.male + b.female;
            });
            summary += total > 0 ? `Total: ${total}` : 'None';
            preview.textContent = summary.trim();
        }

        closeModal('beneficiaries-modal');
    }

    window.addEventListener('load', function() {
        const hidden = document.getElementById('beneficiaries-json');
        if (hidden) {
            const json = hidden.value || '[]';
            try {
                const data = JSON.parse(json);
                data.forEach(b => addBeneficiaryRow(b.type || '', b.male || 0, b.female || 0));
                saveBeneficiaries();
            } catch (e) {
                console.error('Invalid beneficiaries JSON on load:', e);
            }
        }
    });
    </script>

</body>
</html>