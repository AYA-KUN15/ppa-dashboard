<?php
// complete.php - Only for activities now
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

if (!$id || !is_numeric($id) || $mode !== 'activity') {
    echo json_encode(['success' => false, 'message' => 'Invalid request (only activity completion allowed)']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        UPDATE activity_entries 
        SET status = 'completed', updated_at = NOW() 
        WHERE id = ? AND status != 'completed'
    ");
    $stmt->execute([$id]);

    $affected = $stmt->rowCount();

    if ($affected > 0) {
        echo json_encode(['success' => true, 'message' => 'Activity marked as completed']);
    } else {
        echo json_encode(['success' => false, 'message' => 'No changes made']);
    }
} catch (PDOException $e) {
    error_log("complete.php error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}