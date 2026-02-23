<?php
// edit.php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php");
    exit;
}

require_once '../config/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $quarter = $_POST['quarter'] ?? null;
    $fiscal_year = trim($_POST['fiscal_year'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $date_duration = trim($_POST['date_duration'] ?? '');
    $beneficiaries_male = (int)($_POST['beneficiaries_male'] ?? 0);
    $beneficiaries_female = (int)($_POST['beneficiaries_female'] ?? 0);
    $beneficiaries_department = trim($_POST['beneficiaries_department'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $extensionists = trim($_POST['extensionists'] ?? '');
    $partner_agencies = trim($_POST['partner_agencies'] ?? '');
    $budget_allocation = (float)($_POST['budget_allocation'] ?? 0);
    $source_of_fund = trim($_POST['source_of_fund'] ?? '');
    $frequency_monitoring = trim($_POST['frequency_monitoring'] ?? '');

    if (!$id || !is_numeric($id)) {
        $error = 'Invalid entry ID.';
    } elseif (!in_array($quarter, ['1st', '2nd', '3rd', '4th'])) {
        $error = 'Invalid quarter.';
    } elseif (!preg_match('/^\d{4}$/', $fiscal_year)) {
        $error = 'Fiscal year must be 4 digits.';
    } elseif (empty($title)) {
        $error = 'Title is required.';
    } elseif (empty($date_duration)) {
        $error = 'Date/Duration is required.';
    } elseif (empty($location)) {
        $error = 'Location is required.';
    } elseif (empty($extensionists)) {
        $error = 'Extensionists is required.';
    } elseif ($budget_allocation < 0) {
        $error = 'Budget cannot be negative.';
    }

    if (!$error) {
        $stmt = $pdo->prepare("
            UPDATE ppa_entries 
            SET quarter = ?, fiscal_year = ?, title = ?, date_duration = ?,
                beneficiaries_male = ?, beneficiaries_female = ?, beneficiaries_department = ?,
                location = ?, extensionists = ?, partner_agencies = ?,
                budget_allocation = ?, source_of_fund = ?, frequency_monitoring = ?,
                updated_at = NOW()
            WHERE id = ? AND status = 'active'
        ");

        $stmt->execute([
            $quarter, $fiscal_year, $title, $date_duration,
            $beneficiaries_male, $beneficiaries_female, $beneficiaries_department,
            $location, $extensionists, $partner_agencies,
            $budget_allocation, $source_of_fund, $frequency_monitoring,
            $id
        ]);

        header("Location: list.php?success=updated");
        exit;
    }
}

// Should not reach here normally
header("Location: list.php");
exit;