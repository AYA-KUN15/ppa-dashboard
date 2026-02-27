<?php
// complete.php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../config/db.php';

header('Content-Type: application/json');

$id = $_POST['id'] ?? null;
$mode = $_POST['mode'] ?? null;

if (!$id || !is_numeric($id) || !in_array($mode, ['program', 'project', 'activity'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

try {
    $table = $mode . '_entries';
    $stmt = $pdo->prepare("UPDATE $table SET status = 'completed', updated_at = NOW() WHERE id = ?");
    $stmt->execute([$id]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No rows affected (entry may not exist or already completed)']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}