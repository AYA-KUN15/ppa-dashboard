<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$doc_id = $_POST['doc_id'] ?? null;
$activity_id = $_POST['activity_id'] ?? null;

if (!$doc_id || !$activity_id || !is_numeric($doc_id) || !is_numeric($activity_id)) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT image_path FROM activity_documents WHERE id = ? AND activity_id = ?");
    $stmt->execute([$doc_id, $activity_id]);
    $doc = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$doc) {
        echo json_encode(['success' => false, 'message' => 'Document not found']);
        exit;
    }

    // Delete file from opmm-dashboard/uploads/...
    $filePath = dirname(__DIR__) . '/' . $doc['image_path'];
    if (file_exists($filePath)) {
        unlink($filePath);
    }

    // Delete from DB
    $deleteStmt = $pdo->prepare("DELETE FROM activity_documents WHERE id = ?");
    $deleteStmt->execute([$doc_id]);

    echo json_encode(['success' => true, 'message' => 'Photo deleted']);
} catch (PDOException $e) {
    error_log("Delete error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}