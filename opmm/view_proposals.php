<?php
// view_proposals.php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php");
    exit;
}

require_once '../config/db.php';

$id = $_GET['id'] ?? null;
if (!$id || !is_numeric($id)) {
    header("Location: list_proposals.php");
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT id, title, description, start_date, end_date, status,
               type_of_extension_service_agenda, sdg_goals,
               offices_involved, programs_involved, beneficiaries_json,
               partner_agencies, source_of_fund, total_cost
        FROM research_proposals 
        WHERE id = ?
    ");
    $stmt->execute([$id]);
    $proposal = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$proposal) {
        header("Location: list_proposals.php");
        exit;
    }

} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
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
    <title>View Proposal - <?= htmlspecialchars($proposal['title'] ?? 'Proposal') ?></title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .program-details p { 
            margin: 12px 0; 
        }
        
        .full-desc-content {
            white-space: pre-wrap;
            word-wrap: break-word;
            word-break: break-word;
            max-height: 420px;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 18px;
            background: #f9fafb;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            line-height: 1.65;
            font-size: 1rem;
        }

        /* Status text colors (matching your reference style - text color only) */
        .status-text {
            font-weight: 600;
            font-size: 1rem;
        }

        .status-active    { color: #3b82f6; } /* blue   */
        .status-overdue   { color: #c8102e; } /* red    */
        .status-published { color: #10b981; } /* green  */
    </style>
</head>
<body>

    <main class="dashboard-content">
        <?php if (isset($error)): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php else: ?>
            <h1><?= htmlspecialchars($proposal['title']) ?></h1>

            <div class="program-details">
                <p><strong>Start Date:</strong> <?= htmlspecialchars(date('M d, Y', strtotime($proposal['start_date']))) ?></p>
                <p><strong>End Date:</strong> <?= htmlspecialchars(date('M d, Y', strtotime($proposal['end_date']))) ?></p>
                <p><strong>Type of Extension Service Agenda:</strong> <?= htmlspecialchars($proposal['type_of_extension_service_agenda'] ?: 'None') ?></p>
                <p><strong>SDG Goals:</strong> <?= htmlspecialchars($proposal['sdg_goals'] ?: 'None') ?></p>
                <p><strong>Offices Involved:</strong> <?= htmlspecialchars($proposal['offices_involved'] ?: 'None') ?></p>
                <p><strong>Programs Involved:</strong> <?= htmlspecialchars($proposal['programs_involved'] ?: 'None') ?></p>

                <!-- Description on same line -->
                <p>
                    <strong>Description:</strong> 
                    <a href="javascript:void(0)" onclick="showFullDescription()" 
                       style="color: #c8102e; text-decoration: underline; cursor: pointer; font-weight: 500; margin-left: 8px;">
                        Click here to view full description
                    </a>
                </p>

                <p><strong>Partner Agencies:</strong> <?= htmlspecialchars($proposal['partner_agencies'] ?: 'None') ?></p>
                <p><strong>Source of Fund:</strong> <?= htmlspecialchars($proposal['source_of_fund'] ?: 'None') ?></p>
                <p><strong>Total Cost:</strong> ₱<?= number_format($proposal['total_cost'] ?? 0, 2) ?></p>

                <p><strong>Beneficiaries:</strong> 
                    <?php
                    $benefs = json_decode($proposal['beneficiaries_json'] ?? '[]', true);
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
                        echo 'None added';
                    }
                    ?>
                </p>

                <p><strong>Status:</strong> 
                    <?php
                    $statusClass = 'status-active';
                    $statusText = 'Active';

                    switch ($proposal['status']) {
                        case 'active':
                            $statusClass = 'status-active';
                            $statusText = 'Active';
                            break;
                        case 'overdue':
                            $statusClass = 'status-overdue';
                            $statusText = 'Overdue';
                            break;
                        case 'published':
                            $statusClass = 'status-published';
                            $statusText = 'Published';
                            break;
                    }
                    ?>
                    <span class="status-text <?= $statusClass ?>"><?= $statusText ?></span>
                </p>
            </div>
        <?php endif; ?>
    </main>

    <!-- Full Description Pop-up Modal -->
    <div id="description-modal" class="modal-overlay">
        <div class="modal-box" style="max-width: 720px;">
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
    function showFullDescription() {
        const desc = <?= json_encode($proposal['description'] ?? 'No description provided.') ?>;
        document.getElementById('full-description-content').textContent = desc;
        openModal('description-modal');
    }

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