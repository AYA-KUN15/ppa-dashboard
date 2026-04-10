<?php
// list_proposals.php - Research Proposals List (active + overdue only)
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php");
    exit;
}

require_once '../config/db.php';

// Hardcoded options for filters
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

try {
    $where = "WHERE status IN ('active', 'overdue')";
    $params = [];
    $orderBy = "ORDER BY start_date DESC, title ASC";

    // Type filter
    if (!empty($_GET['type'])) {
        $where .= " AND type_of_extension_service_agenda LIKE ?";
        $params[] = '%' . trim($_GET['type']) . '%';
    }
    // SDG filter
    if (!empty($_GET['sdg'])) {
        $where .= " AND sdg_goals LIKE ?";
        $params[] = '%' . trim($_GET['sdg']) . '%';
    }
    // Source of Fund filter
    if (!empty($_GET['fund'])) {
        $where .= " AND source_of_fund = ?";
        $params[] = trim($_GET['fund']);
    }

    $query = "
        SELECT id, title, start_date, end_date, status,
               type_of_extension_service_agenda, sdg_goals
        FROM research_proposals
        $where
        $orderBy
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $proposals = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
    $proposals = [];
}

$nav_links = [
    ['url' => 'index.php', 'label' => 'Home', 'active' => false],
    ['url' => '/opmm/list.php', 'label' => 'PPA', 'active' => false],
    ['url' => '/opmm/list_proposals.php', 'label' => 'Proposals', 'active' => true],
];
?>

<?php include '../includes/header.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Research Proposals</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">

    <style>
        .quarter-item {
            display: flex;
            align-items: center;
            gap: 8px;
            width: 100%;
        }

        .quarter-btn {
            flex: 1;
            padding: 14px 20px;
            background: #ffffff;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            text-align: left;
            box-sizing: border-box;
        }

        .quarter-btn-title {
            font-weight: 600;
            font-size: 1.1rem;
            display: block;
            margin-bottom: 4px;
        }

        .quarter-btn-subtitle {
            font-size: 0.9rem;
            color: #6b7280;
        }

        .edit-icon-btn {
            flex: 0 0 42px;
            width: 42px;
            height: 42px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: none;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            transition: all 0.2s;
        }

        .edit-icon-btn .material-icons {
            font-size: 1.6rem;
            color: #6b7280;
        }

        .edit-icon-btn:hover {
            color: #c8102e;
            background: rgba(200, 16, 46, 0.1);
        }

        /* Overdue styling */
        .quarter-btn.overdue {
            background: #fef2f2 !important;
            border: 2px solid #ef4444 !important;
            color: #b91c1c !important;
        }

        .quarter-btn.overdue:hover {
            background: #fee2e2 !important;
            border-color: #dc2626 !important;
        }
    </style>
</head>
<body>

    <main class="dashboard-content">

        <div class="filter-actions">
            <button class="filter-button" onclick="openModal('filter-modal')">Filter</button>

            <div class="action-buttons" style="display:flex; gap:12px; justify-content:flex-end; align-items:center;">
                <a href="add_proposals.php" class="action-btn add">Add New Proposal</a>
            </div>
        </div>

        <div class="quarter-scroll-container">
            <div class="quarter-buttons">

                <?php if (!empty($error)): ?>
                    <p class="error"><?= htmlspecialchars($error) ?></p>
                <?php elseif (empty($proposals)): ?>
                    <p>No research proposals found.</p>
                <?php else: ?>
                    <?php foreach ($proposals as $prop): ?>
                        <div class="quarter-item">
                            <button class="quarter-btn <?= ($prop['status'] === 'overdue') ? 'overdue' : '' ?>"
                                    onclick="window.location.href='view_proposals.php?id=<?= $prop['id'] ?>'">
                                <span class="quarter-btn-title"><?= htmlspecialchars($prop['title']) ?></span>
                                <span class="quarter-btn-subtitle">
                                    <?= date('M d, Y', strtotime($prop['start_date'])) ?> – 
                                    <?= date('M d, Y', strtotime($prop['end_date'])) ?>
                                </span>
                            </button>

                            <button class="action-icon edit-icon-btn"
                                    onclick="window.location.href='edit_proposals.php?id=<?= $prop['id'] ?>'"
                                    title="Edit proposal">
                                <span class="material-icons">edit</span>
                            </button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

            </div>
        </div>

        <div class="content-placeholder">
            <p>Select a proposal to view its details.</p>
        </div>

    </main>

    <!-- Filter Modal -->
    <div id="filter-modal" class="modal-overlay">
        <div class="modal-box">
            <span class="close-modal" onclick="closeModal('filter-modal')">×</span>
            <h2>Filter Proposals</h2>
            <form id="filter-form" method="GET" action="list_proposals.php">

                <label for="filter-type">Type of Extension Service Agenda</label>
                <select id="filter-type" name="type">
                    <option value="">All Types</option>
                    <?php foreach ($fullTypeOptions as $opt): ?>
                        <option value="<?= htmlspecialchars($opt) ?>" 
                            <?= (($_GET['type'] ?? '') === $opt) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($opt) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="filter-sdg">Sustainable Development Goals</label>
                <select id="filter-sdg" name="sdg">
                    <option value="">All SDGs</option>
                    <?php foreach ($fullSdgOptions as $opt): ?>
                        <option value="<?= htmlspecialchars($opt) ?>" 
                            <?= (($_GET['sdg'] ?? '') === $opt) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($opt) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="filter-fund">Source of Fund</label>
                <select id="filter-fund" name="fund">
                    <option value="">All Sources</option>
                    <option value="MDS" <?= ($_GET['fund'] ?? '') === 'MDS' ? 'selected' : '' ?>>MDS</option>
                    <option value="STF" <?= ($_GET['fund'] ?? '') === 'STF' ? 'selected' : '' ?>>STF</option>
                    <option value="Others" <?= ($_GET['fund'] ?? '') === 'Others' ? 'selected' : '' ?>>Others</option>
                </select>

                <div class="modal-actions" style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 24px;">
                    <button type="submit"
                            style="flex: 1; padding: 14px 24px; background: #c8102e; color: white;
                                   border: none; border-radius: 8px; cursor: pointer; font-size: 1rem;
                                   font-weight: 600; height: 52px; box-sizing: border-box;">
                        Apply
                    </button>
                    <button type="button" onclick="closeModal('filter-modal')"
                            style="flex: 1; padding: 14px 24px; background: #c8102e; color: white;
                                   border: none; border-radius: 8px; cursor: pointer; font-size: 1rem;
                                   font-weight: 600; height: 52px; box-sizing: border-box;">
                        Cancel
                    </button>
                    <button type="button" onclick="window.location.href='list_proposals.php'"
                            style="flex: 1; padding: 14px 24px; background: #c8102e; color: white;
                                   border: none; border-radius: 8px; cursor: pointer; font-size: 1rem;
                                   font-weight: 600; height: 52px; box-sizing: border-box;">
                        Clear
                    </button>
                </div>
            </form>
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
    </script>

</body>
</html>