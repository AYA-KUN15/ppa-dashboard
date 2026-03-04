<?php
// complete.php - Mark project or activity as completed
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../config/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$id   = $_POST['id']   ?? null;
$mode = $_POST['mode'] ?? null;

if (!$id || !is_numeric($id) || !in_array($mode, ['project', 'activity'])) {
    echo json_encode(['success' => false, 'message' => 'Missing or invalid parameters']);
    exit;
}

try {
    $table = ($mode === 'project') ? 'project_entries' : 'activity_entries';

    // Optional: check if record exists and is not already completed
    $checkStmt = $pdo->prepare("SELECT status FROM $table WHERE id = ?");
    $checkStmt->execute([$id]);
    $record = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$record) {
        echo json_encode(['success' => false, 'message' => 'Record not found']);
        exit;
    }

    if ($record['status'] === 'completed') {
        echo json_encode(['success' => false, 'message' => 'Already completed']);
        exit;
    }

    // Update status
    $stmt = $pdo->prepare("UPDATE $table SET status = 'completed', updated_at = NOW() WHERE id = ?");
    $stmt->execute([$id]);

    $affected = $stmt->rowCount();

    if ($affected > 0) {
        // If we just completed an ACTIVITY, check if the parent PROJECT is now fully completed
        if ($mode === 'activity') {
            // Get the project_id of this activity
            $projStmt = $pdo->prepare("SELECT project_id FROM activity_entries WHERE id = ?");
            $projStmt->execute([$id]);
            $activity = $projStmt->fetch(PDO::FETCH_ASSOC);
            $project_id = $activity['project_id'] ?? null;

            if ($project_id) {
                // Count total activities for this project
                $totalStmt = $pdo->prepare("SELECT COUNT(*) AS total FROM activity_entries WHERE project_id = ?");
                $totalStmt->execute([$project_id]);
                $total = $totalStmt->fetch(PDO::FETCH_ASSOC)['total'];

                // Count completed activities
                $compStmt = $pdo->prepare("SELECT COUNT(*) AS completed FROM activity_entries WHERE project_id = ? AND status = 'completed'");
                $compStmt->execute([$project_id]);
                $completed = $compStmt->fetch(PDO::FETCH_ASSOC)['completed'];

                // If all activities are completed, mark the project as completed
                if ($total > 0 && $total === $completed) {
                    $updateProj = $pdo->prepare("UPDATE project_entries SET status = 'completed', updated_at = NOW() WHERE id = ?");
                    $updateProj->execute([$project_id]);
                }
            }
        }

        echo json_encode(['success' => true, 'message' => ucfirst($mode) . ' marked as completed']);
    } else {
        echo json_encode(['success' => false, 'message' => 'No changes made']);
    }
} catch (PDOException $e) {
    error_log("complete.php error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}