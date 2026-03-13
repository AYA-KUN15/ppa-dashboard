<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php");
    exit;
}

require_once '../config/db.php';

$error = '';
$show_confirmation = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['confirm']) && isset($_SESSION['pending_program'])) {
        $d = $_SESSION['pending_program'];

        try {
            $stmt = $pdo->prepare("
                INSERT INTO program_entries (
                    title, location, duration_start, duration_end,
                    type_of_extension_service_agenda, sdg_goals, offices_involved,
                    programs_involved, partner_agencies, beneficiaries_json,
                    total_cost, source_of_fund, monitoring_frequency
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $d['title'], $d['location'], $d['duration_start'], $d['duration_end'],
                $d['type'], $d['sdg'], $d['offices'], $d['programs'], $d['partners'],
                $d['beneficiaries_json'], $d['total_cost'], $d['source_fund'],
                $d['monitoring_frequency']
            ]);

            unset($_SESSION['pending_program']);
            header("Location: list.php?success=added");
            exit;
        } catch (PDOException $e) {
            $error = 'Failed to save: ' . $e->getMessage();
        }
    } else {
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
        $monitoring_frequency = trim($_POST['monitoring_frequency'] ?? '');

        if (empty($title) || empty($location) || empty($duration_start) || empty($duration_end) ||
            empty($type) || empty($sdg) || empty($offices) || empty($programs) ||
            empty($partners) || $total_cost <= 0 || empty($source_fund) || empty($monitoring_frequency)) {
            $error = 'Please fill all required fields.';
        } else {
            $_SESSION['pending_program'] = [
                'title' => $title, 'location' => $location, 'duration_start' => $duration_start,
                'duration_end' => $duration_end, 'type' => $type, 'sdg' => $sdg,
                'offices' => $offices, 'programs' => $programs, 'partners' => $partners,
                'beneficiaries_json' => $beneficiaries_json, 'total_cost' => $total_cost,
                'source_fund' => $source_fund, 'monitoring_frequency' => $monitoring_frequency
            ];
            $show_confirmation = true;
        }
    }
}

$nav_links = [
    ['url' => 'index.php', 'label' => 'Home',    'active' => false],
    ['url' => '/opmm/list.php',  'label' => 'PPA',     'active' => false],
];
?>

<?php include '../includes/header.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Program</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>

    <main class="dashboard-content add-program-page">
        <h1>Add New Program</h1>

        <?php if ($error): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <?php if ($show_confirmation): $d = $_SESSION['pending_program']; ?>
            <div class="confirmation-box">
                <h2>Confirm Program Details</h2>

                <div class="confirmation-grid">
                    <div class="confirm-item">
                        <strong>Title</strong>
                        <p><?= htmlspecialchars($d['title']) ?></p>
                    </div>

                    <div class="confirm-item">
                        <strong>Location</strong>
                        <p><?= htmlspecialchars($d['location']) ?></p>
                    </div>

                    <div class="confirm-item">
                        <strong>Duration</strong>
                        <p><?= htmlspecialchars($d['duration_start']) ?> to <?= htmlspecialchars($d['duration_end']) ?></p>
                    </div>

                    <div class="confirm-item">
                        <strong>Frequency of Monitoring</strong>
                        <p><?= htmlspecialchars($d['monitoring_frequency']) ?></p>
                    </div>

                    <div class="confirm-item">
                        <strong>Type of Extension Service Agenda</strong>
                        <p><?= htmlspecialchars($d['type']) ?: 'None' ?></p>
                    </div>

                    <div class="confirm-item">
                        <strong>SDG Goals</strong>
                        <p><?= htmlspecialchars($d['sdg']) ?: 'None' ?></p>
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
                                if ($total > 0) echo " | <strong>Total:</strong> $total";
                            } else {
                                echo 'None';
                            }
                            ?>
                        </p>
                    </div>

                    <div class="confirm-item">
                        <strong>Offices Involved</strong>
                        <p><?= htmlspecialchars($d['offices']) ?: 'None' ?></p>
                    </div>

                    <div class="confirm-item">
                        <strong>Programs Involved</strong>
                        <p><?= htmlspecialchars($d['programs']) ?: 'None' ?></p>
                    </div>

                    <div class="confirm-item">
                        <strong>Partner Agencies</strong>
                        <p><?= htmlspecialchars($d['partners']) ?: 'None' ?></p>
                    </div>

                    <div class="confirm-item">
                        <strong>Total Cost</strong>
                        <p>₱<?= number_format($d['total_cost'], 2) ?></p>
                    </div>

                    <div class="confirm-item">
                        <strong>Source of Fund</strong>
                        <p><?= htmlspecialchars($d['source_fund']) ?: 'None' ?></p>
                    </div>
                </div>

                <div class="confirm-actions">
                    <form method="POST" style="display:inline;">
                        <button type="submit" name="confirm">Confirm & Save</button>
                    </form>
                    <a href="add.php" class="cancel-link">Cancel</a>
                </div>
            </div>
        <?php else: ?>
            <form method="POST" class="program-form">
                <div class="form-group">
                    <label for="title">Title *</label>
                    <input type="text" id="title" name="title" required>
                </div>

                <div class="form-group">
                    <label for="location">Location *</label>
                    <input type="text" id="location" name="location" required>
                </div>

                <div class="form-group full-span">
                    <label>Duration & Frequency of Monitoring *</label>
                    <div class="date-group">
                        <input type="date" name="duration_start" required>
                        <span>to</span>
                        <input type="date" name="duration_end" required>
                        <select name="monitoring_frequency" required>
                            <option value="">Frequency</option>
                            <option value="Monthly">Monthly</option>
                            <option value="Quarterly">Quarterly</option>
                            <option value="Semi-Annually">Semi-Annually</option>
                            <option value="Annually">Annually</option>
                        </select>
                    </div>
                    <small class="hint">
                        Frequency applies during monitoring & evaluation (typically last 2 years).
                    </small>
                </div>

                <div class="form-group">
                    <label>Type of Extension Service Agenda *</label>
                    <button type="button" onclick="openModal('type-modal')">Select Types</button>
                    <div id="selected-types" class="compact-preview">None</div>
                    <input type="hidden" name="type_of_extension_service_agenda" id="type-hidden">
                </div>

                <div class="form-group">
                    <label>Sustainable Development Goals *</label>
                    <button type="button" onclick="openModal('sdg-modal')">Select SDGs</button>
                    <div id="selected-sdgs" class="compact-preview">None</div>
                    <input type="hidden" name="sdg_goals" id="sdg-hidden">
                </div>

                <div class="form-group full-span">
                    <label>Beneficiaries *</label>
                    <button type="button" onclick="openModal('beneficiaries-modal')">Manage Beneficiaries</button>
                    <div id="selected-beneficiaries" class="compact-preview">None</div>
                    <input type="hidden" name="beneficiaries_json" id="beneficiaries-json" value="[]">
                </div>

                <div class="form-group">
                    <label for="offices_involved">Offices Involved *</label>
                    <input type="text" id="offices_involved" name="offices_involved" required>
                </div>

                <div class="form-group">
                    <label for="programs_involved">Programs Involved *</label>
                    <input type="text" id="programs_involved" name="programs_involved" required>
                </div>

                <div class="form-group">
                    <label for="partner_agencies">Partner Agencies *</label>
                    <input type="text" id="partner_agencies" name="partner_agencies" required>
                </div>

                <div class="form-group">
                    <label>Source of Fund *</label>
                    <button type="button" onclick="openModal('source-modal')">Select Sources</button>
                    <div id="selected-source" class="compact-preview">None</div>
                    <input type="hidden" name="source_of_fund" id="source-hidden">
                </div>

                <div class="form-group">
                    <label for="total_cost">Total Cost *</label>
                    <input type="number" id="total_cost" name="total_cost" step="0.01" min="0" required>
                </div>

                <div class="full-span" style="text-align: center; margin-top: 16px;">
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
                <div style="display: flex; flex-direction: column; gap: 8px;">
                    <label class="modal-checkbox-label">
                        <span>BatStateU Inclusive Social Innovation for Regional Growth (BISIG) Program</span>
                        <input type="checkbox" value="BatStateU Inclusive Social Innovation for Regional Growth (BISIG) Program">
                    </label>
                    <label class="modal-checkbox-label">
                        <span>Livelihood and other Entrepreneurship related on Agri-Fisheries (LEAF)</span>
                        <input type="checkbox" value="Livelihood and other Entrepreneurship related on Agri-Fisheries (LEAF)">
                    </label>
                    <label class="modal-checkbox-label">
                        <span>Environment and Natural resources Conservation, Protection and Rehabilitation Program</span>
                        <input type="checkbox" value="Environment and Natural resources Conservation, Protection and Rehabilitation Program">
                    </label>
                    <label class="modal-checkbox-label">
                        <span>Smart Analytics and Engineering Innovation</span>
                        <input type="checkbox" value="Smart Analytics and Engineering Innovation">
                    </label>
                    <label class="modal-checkbox-label">
                        <span>Adopt-a Municipality/Barangay/School/Social Development Thru BIDANI Implementation</span>
                        <input type="checkbox" value="Adopt-a Municipality/Barangay/School/Social Development Thru BIDANI Implementation">
                    </label>
                    <label class="modal-checkbox-label">
                        <span>Community Outreach</span>
                        <input type="checkbox" value="Community Outreach">
                    </label>
                    <label class="modal-checkbox-label">
                        <span>Technical-Vocational Education and Training (TVET) Program</span>
                        <input type="checkbox" value="Technical-Vocational Education and Training (TVET) Program">
                    </label>
                    <label class="modal-checkbox-label">
                        <span>Technology Transfer and Adoption/Utilization Program</span>
                        <input type="checkbox" value="Technology Transfer and Adoption/Utilization Program">
                    </label>
                    <label class="modal-checkbox-label">
                        <span>Technical Assistance and Advisory Services Program</span>
                        <input type="checkbox" value="Technical Assistance and Advisory Services Program">
                    </label>
                    <label class="modal-checkbox-label">
                        <span>Parents' Empowerment through Social Development (PESODEV)</span>
                        <input type="checkbox" value="Parents' Empowerment through Social Development (PESODEV)">
                    </label>
                    <label class="modal-checkbox-label">
                        <span>Gender and Development</span>
                        <input type="checkbox" value="Gender and Development">
                    </label>
                    <label class="modal-checkbox-label">
                        <span>Disaster Risk Reduction and Management and Disaster Preparedness and Response/Climate Change Adaptation (DRMM and DPR/CCA)</span>
                        <input type="checkbox" value="Disaster Risk Reduction and Management and Disaster Preparedness and Response/Climate Change Adaptation (DRMM and DPR/CCA)">
                    </label>
                </div>
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
                <div style="display: flex; flex-direction: column; gap: 8px;">
                    <label class="modal-checkbox-label">
                        <span>No Poverty</span>
                        <input type="checkbox" value="No Poverty">
                    </label>
                    <label class="modal-checkbox-label">
                        <span>Zero Hunger</span>
                        <input type="checkbox" value="Zero Hunger">
                    </label>
                    <label class="modal-checkbox-label">
                        <span>Good Health and Well-Being</span>
                        <input type="checkbox" value="Good Health and Well-Being">
                    </label>
                    <label class="modal-checkbox-label">
                        <span>Quality Education</span>
                        <input type="checkbox" value="Quality Education">
                    </label>
                    <label class="modal-checkbox-label">
                        <span>Gender Equality</span>
                        <input type="checkbox" value="Gender Equality">
                    </label>
                    <label class="modal-checkbox-label">
                        <span>Clean Water and Sanitation</span>
                        <input type="checkbox" value="Clean Water and Sanitation">
                    </label>
                    <label class="modal-checkbox-label">
                        <span>Affordable and Clean Energy</span>
                        <input type="checkbox" value="Affordable and Clean Energy">
                    </label>
                    <label class="modal-checkbox-label">
                        <span>Decent Work and Economic Growth</span>
                        <input type="checkbox" value="Decent Work and Economic Growth">
                    </label>
                    <label class="modal-checkbox-label">
                        <span>Industry, Innovation, and Infrastructure</span>
                        <input type="checkbox" value="Industry, Innovation, and Infrastructure">
                    </label>
                    <label class="modal-checkbox-label">
                        <span>Reduced Inequalities</span>
                        <input type="checkbox" value="Reduced Inequalities">
                    </label>
                    <label class="modal-checkbox-label">
                        <span>Sustainable Cities and Communities</span>
                        <input type="checkbox" value="Sustainable Cities and Communities">
                    </label>
                    <label class="modal-checkbox-label">
                        <span>Responsible Consumption and Production</span>
                        <input type="checkbox" value="Responsible Consumption and Production">
                    </label>
                    <label class="modal-checkbox-label">
                        <span>Climate Action</span>
                        <input type="checkbox" value="Climate Action">
                    </label>
                    <label class="modal-checkbox-label">
                        <span>Life Below Water</span>
                        <input type="checkbox" value="Life Below Water">
                    </label>
                    <label class="modal-checkbox-label">
                        <span>Life on Land</span>
                        <input type="checkbox" value="Life on Land">
                    </label>
                    <label class="modal-checkbox-label">
                        <span>Peace, Justice and Strong Institutions</span>
                        <input type="checkbox" value="Peace, Justice and Strong Institutions">
                    </label>
                    <label class="modal-checkbox-label">
                        <span>Partnerships for the Goals</span>
                        <input type="checkbox" value="Partnerships for the Goals">
                    </label>
                </div>
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

    <!-- Source of Fund Modal -->
    <div id="source-modal" class="modal-overlay">
        <div class="modal-box">
            <span class="close-modal" onclick="closeModal('source-modal')">×</span>
            <h2>Select Source of Fund</h2>
            <div style="max-height: 400px; overflow-y: auto; padding: 12px;">
                <div style="display: flex; flex-direction: column; gap: 8px;">
                    <label class="modal-checkbox-label">
                        <span>MDS</span>
                        <input type="checkbox" value="MDS">
                    </label>
                    <label class="modal-checkbox-label">
                        <span>STF</span>
                        <input type="checkbox" value="STF">
                    </label>
                    <label class="modal-checkbox-label">
                        <span>Others</span>
                        <input type="checkbox" value="Others">
                    </label>
                </div>
            </div>
            <div class="modal-actions">
                <button onclick="saveModalSelections('source')" 
                        style="padding: 10px 20px; background: #c8102e; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 500;">
                    Save
                </button>
                <button onclick="closeModal('source-modal')" 
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
        display.textContent = values.length ? values.join(', ') : 'None';
    }

    closeModal(type + '-modal');
}

function addBeneficiaryRow(type = '', male = 0, female = 0) {
    const container = document.getElementById('beneficiary-rows');
    const row = document.createElement('div');
    row.className = 'beneficiary-row';
    row.style.display = 'flex';
    row.style.alignItems = 'center';
    row.style.gap = '10px';
    row.style.marginBottom = '10px';
    row.style.flexWrap = 'wrap';

    row.innerHTML = `
        <input type="text" 
               placeholder="e.g., Farmers, Students, PWDs" 
               value="${type}" 
               class="beneficiary-type"
               required
               style="flex: 2; min-width: 180px;">

        <input type="number" 
               placeholder="Male" 
               value="${male}" 
               min="0" 
               class="beneficiary-male"
               required
               style="flex: 1; max-width: 80px;">

        <input type="number" 
               placeholder="Female" 
               value="${female}" 
               min="0" 
               class="beneficiary-female"
               required
               style="flex: 1; max-width: 80px;">

        <button type="button" 
                onclick="this.closest('.beneficiary-row').remove();"
                style="padding: 6px 10px; background: #c8102e; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 0.9rem;">
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
        summary += `${b.type}: ${b.male} M, ${b.female} F | `;
        total += b.male + b.female;
    });

    summary += total > 0 ? `Total: ${total}` : 'None';

    document.getElementById('selected-beneficiaries').textContent = summary.trim();

    closeModal('beneficiaries-modal');
}

window.addEventListener('load', function() {
    const typeHidden = document.getElementById('type-hidden');
    if (typeHidden && typeHidden.value) {
        document.getElementById('selected-types').textContent = typeHidden.value;
    } else {
        document.getElementById('selected-types').textContent = 'None';
    }

    const sdgHidden = document.getElementById('sdg-hidden');
    if (sdgHidden && sdgHidden.value) {
        document.getElementById('selected-sdgs').textContent = sdgHidden.value;
    } else {
        document.getElementById('selected-sdgs').textContent = 'None';
    }

    const sourceHidden = document.getElementById('source-hidden');
    if (sourceHidden && sourceHidden.value) {
        document.getElementById('selected-source').textContent = sourceHidden.value;
    } else {
        document.getElementById('selected-source').textContent = 'None';
    }

    const json = document.getElementById('beneficiaries-json')?.value || '[]';
    try {
        const data = JSON.parse(json);
        data.forEach(b => addBeneficiaryRow(b.type, b.male, b.female));
        saveBeneficiaries();
    } catch (e) {
        console.error('Invalid beneficiaries JSON on load:', e);
        document.getElementById('selected-beneficiaries').textContent = 'None';
    }
});
    </script>
</body>
</html>