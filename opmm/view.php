<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php");
    exit;
}

require_once '../config/db.php';

$id = $_GET['id'] ?? null;
$mode = $_GET['mode'] ?? 'program';

if (!$id || !is_numeric($id) || $mode !== 'program') {
    header("Location: list.php");
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT id, title, location, duration_start, duration_end,
               type_of_extension_service_agenda, sdg_goals,
               offices_involved, programs_involved, partner_agencies,
               beneficiaries_json
        FROM program_entries
        WHERE id = ?
    ");
    $stmt->execute([$id]);
    $program = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$program) {
        header("Location: list.php");
        exit;
    }

    // Derive program status from projects
    $projStmt = $pdo->prepare("SELECT id FROM project_entries WHERE program_id = ?");
    $projStmt->execute([$id]);
    $projects = $projStmt->fetchAll(PDO::FETCH_ASSOC);

    $programStatus = 'active';
    if (!empty($projects)) {
        $allProjCompleted = true;
        foreach ($projects as $proj) {
            $actStmt = $pdo->prepare("
                SELECT COUNT(*) AS total, 
                       SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed
                FROM activity_entries WHERE project_id = ?
            ");
            $actStmt->execute([$proj['id']]);
            $counts = $actStmt->fetch(PDO::FETCH_ASSOC);

            if ($counts['total'] > 0 && $counts['completed'] != $counts['total']) {
                $allProjCompleted = false;
                break;
            }
        }
        if ($allProjCompleted) {
            $programStatus = 'completed';
        }
    }

    // Fetch projects for list
    $projListStmt = $pdo->prepare("
        SELECT id, project_title, implementation_start, implementation_end
        FROM project_entries WHERE program_id = ?
        ORDER BY implementation_start ASC
    ");
    $projListStmt->execute([$id]);
    $projectsList = $projListStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

$nav_links = [
    ['url' => '../index.php', 'label' => 'Home', 'active' => false],
    ['url' => 'list.php', 'label' => 'PPA', 'active' => false],
];

?>

<?php include '../includes/header.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Program - <?= htmlspecialchars($program['title'] ?? 'Program') ?></title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>

    <main class="dashboard-content">
        <?php if (isset($error)): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php else: ?>
            <h1><?= htmlspecialchars($program['title']) ?></h1>

            <div class="program-details">
                <p><strong>Location:</strong> <?= htmlspecialchars($program['location'] ?? 'N/A') ?></p>
                <p><strong>Duration:</strong> 
                    <?= htmlspecialchars(date('M d, Y', strtotime($program['duration_start']))) ?> – 
                    <?= htmlspecialchars(date('M d, Y', strtotime($program['duration_end']))) ?>
                </p>
                <p><strong>Type of Extension Service Agenda:</strong> <?= htmlspecialchars($program['type_of_extension_service_agenda'] ?? 'N/A') ?></p>
                <p><strong>SDG Goals:</strong> <?= htmlspecialchars($program['sdg_goals'] ?? 'N/A') ?></p>
                <p><strong>Offices Involved:</strong> <?= htmlspecialchars($program['offices_involved'] ?? 'N/A') ?></p>
                <p><strong>Programs Involved:</strong> <?= htmlspecialchars($program['programs_involved'] ?? 'N/A') ?></p>
                <p><strong>Partner Agencies:</strong> <?= htmlspecialchars($program['partner_agencies'] ?? 'N/A') ?></p>
                <p><strong>Beneficiaries:</strong> 
                    <?php
                    $benefs = json_decode($program['beneficiaries_json'] ?? '[]', true);
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
                    if ($programStatus !== 'active') {
                        echo '<span style="color: #10b981; font-weight: 600;">Completed</span>';
                    } else {
                        echo '<span style="color: #c8102e; font-weight: 600;">Active</span>';
                    }
                    ?>
                </p>
            </div>

            <div style="margin-top: 32px;">
                <h2>Projects under this Program</h2>

                <?php if (empty($projectsList)): ?>
                    <p>No projects added yet.</p>
                <?php else: ?>
                    <div class="quarter-scroll-container">
                        <div class="quarter-buttons">
                            <?php foreach ($projectsList as $proj): ?>
                                <div class="quarter-item">
                                    <button class="quarter-btn" 
                                            onclick="window.location.href='view_project.php?id=<?= $proj['id'] ?>'">
                                        <span class="quarter-btn-title"><?= htmlspecialchars($proj['project_title']) ?></span>
                                        <span class="quarter-btn-subtitle">
                                            <?= htmlspecialchars(date('M d, Y', strtotime($proj['implementation_start']))) ?> – 
                                            <?= htmlspecialchars(date('M d, Y', strtotime($proj['implementation_end']))) ?>
                                        </span>
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <a href="add_project.php?program_id=<?= $id ?>" class="action-btn add" style="margin-top: 16px;">Add New Project</a>
            </div>
        <?php endif; ?>
    </main>

</body>
</html>