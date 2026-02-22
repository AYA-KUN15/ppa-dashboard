<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id          = $_POST['id'] ?? null;
    $quarter     = $_POST['quarter'] ?? null;
    $fiscal_year = $_POST['fiscal_year'] ?? null;

    if ($id && $quarter && preg_match('/^\d{4}$/', $fiscal_year)) {
        $stmt = $pdo->prepare("
            UPDATE opmm_entries 
            SET quarter = ?, fiscal_year = ?, updated_at = NOW()
            WHERE id = ? AND status = 'active'
        ");
        $stmt->execute([$quarter, $fiscal_year, $id]);

        header("Location: list.php");
        exit;
    } else {
        // Error handling (can improve later)
        die("Invalid input.");
    }
}