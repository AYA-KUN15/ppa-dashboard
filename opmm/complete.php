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
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        UPDATE program_entries 
        SET status = 'completed', 
            updated_at = NOW()
        WHERE id = ? 
        AND status = 'active'
    ");
    $stmt->execute([$id]);

    if ($stmt->rowCount() === 1) {
        echo json_encode(['success' => true, 'message' => 'Marked as completed']);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'No change - program not found or already completed'
        ]);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'DB error: ' . $e->getMessage()]);
}
?>