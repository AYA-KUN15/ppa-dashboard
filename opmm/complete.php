<?php
session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$id = $_POST['id'] ?? null;
$mode = $_POST['mode'] ?? '';

if (!$id || !is_numeric($id) || $mode !== 'program') {
    echo json_encode(['success' => false, 'message' => 'Invalid request - missing/invalid ID or mode']);
    exit;
}

try {
    // Force update without status condition
    $stmt = $pdo->prepare("
        UPDATE program_entries 
        SET status = 'completed', 
            updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$id]);

    $affected = $stmt->rowCount();

    if ($affected === 1) {
        // Verify what was actually set
        $check = $pdo->prepare("SELECT status FROM program_entries WHERE id = ?");
        $check->execute([$id]);
        $actualStatus = $check->fetchColumn();

        echo json_encode([
            'success' => true,
            'message' => 'Marked as completed',
            'actual_status' => $actualStatus
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No rows updated - program ID not found'
        ]);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'DB error: ' . $e->getMessage()]);
}
?>