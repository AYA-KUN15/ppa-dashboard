<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;

    if ($id) {
        // Hard delete - remove row completely
        $stmt = $pdo->prepare("DELETE FROM opmm_entries WHERE id = ?");
        $stmt->execute([$id]);

        header("Location: list.php");
        exit;
    } else {
        die("Invalid ID.");
    }
}