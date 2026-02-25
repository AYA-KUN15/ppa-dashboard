<?php
// delete.php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php");
    exit;
}

require_once '../config/db.php';

$id = $_POST['id'] ?? null;
$mode = $_POST['mode'] ?? '';

$valid_modes = ['program', 'project', 'activity'];

if (!$id || !is_numeric($id) || !in_array($mode, $valid_modes)) {
    header("Location: list.php");
    exit;
}

try {
    if ($mode === 'program') {
        $table = 'program_entries';
    } elseif ($mode === 'project') {
        $table = 'project_entries';
    } elseif ($mode === 'activity') {
        $table = 'activity_entries';
    }

    $stmt = $pdo->prepare("
        UPDATE $table 
        SET status = 'deleted', updated_at = NOW()
        WHERE id = ? AND status = 'active'
    ");
    $stmt->execute([$id]);

    header("Location: list.php?success=deleted");
    exit;
} catch (PDOException $e) {
    // In production, log error and show user-friendly message
    die("Delete failed: " . $e->getMessage());
}
?>