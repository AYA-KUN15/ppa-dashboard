<?php
session_start();
// Start session: used for authentication and temporarily storing form data (e.g., pending activity before confirmation)

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php");
    exit;
}

require_once '../config/db.php';
// Load database connection (PDO instance)

$id = $_GET['id'] ?? null;

if (!$id || !is_numeric($id)) {
    $_SESSION['error'] = "Invalid or missing activity ID.";
    header("Location: ../list.php");
    exit;
}

// Fetch activity name
// Prepare SQL query safely (prevents SQL injection)
$stmt = $pdo->prepare("SELECT activity_name FROM activity_entries WHERE id = ?");
$stmt->execute([$id]);
$activity = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$activity) {
    $_SESSION['error'] = "Activity not found.";
    header("Location: ../list.php");
    exit;
}

// Check existing count
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM activity_documents WHERE activity_id = ?");
$countStmt->execute([$id]);
$existingCount = $countStmt->fetchColumn();

$maxAllowed = 2;
$canUpload = $existingCount < $maxAllowed;
$remaining = $maxAllowed - $existingCount;

// Upload handling
$success = '';
$error = '';

// New upload path: opmm-dashboard/uploads/activity_{id}/
$baseUploadDir = dirname(__DIR__) . '/uploads/';
$activityUploadDir = $baseUploadDir . "activity_$id/";

if (!is_dir($activityUploadDir)) {
    if (!mkdir($activityUploadDir, 0755, true)) {
        error_log("Failed to create activity upload directory: $activityUploadDir");
        $error = "Server error: cannot create upload folder.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    if (!$canUpload) {
        $error = "You already have $maxAllowed images uploaded. Delete some first if you want to add/replace.";
    } else {
        $files = $_FILES['images'] ?? [];
        $fileCount = count($files['name'] ?? []);

        if ($fileCount > $remaining) {
            $error = "You can only upload up to $remaining more image(s).";
        } elseif ($fileCount === 0 || empty($files['name'][0])) {
            $error = "Please select at least one image.";
        } else {
            $uploaded = 0;
            for ($i = 0; $i < $fileCount && $uploaded < $remaining; $i++) {
                $fileName = $files['name'][$i];
                $tmpName  = $files['tmp_name'][$i];
                $fileSize = $files['size'][$i];
                $fileError = $files['error'][$i];
                $fileType = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                if ($fileError !== UPLOAD_ERR_OK) {
                    $error .= "Upload error for $fileName.<br>";
                    continue;
                }

                if (!in_array($fileType, ['jpg', 'jpeg', 'png'])) {
                    $error .= "$fileName is not a valid image (jpg, jpeg, png only).<br>";
                    continue;
                }

                if ($fileSize > 5 * 1024 * 1024) {
                    $error .= "$fileName exceeds 5MB limit.<br>";
                    continue;
                }

                $newName = uniqid('doc_') . '.' . $fileType;
                $destPath = $activityUploadDir . $newName;

                if (// Move uploaded file from temp storage to activity-specific folder
move_uploaded_file($tmpName, $destPath)) {
                    $dbPath = "uploads/activity_$id/$newName";
                    // Prepare SQL query safely (prevents SQL injection)
$stmt = $pdo->prepare("INSERT INTO activity_documents (activity_id, image_path) VALUES (?, ?)");
                    $stmt->execute([$id, $dbPath]);
                    $uploaded++;
                } else {
                    $error .= "Failed to save $fileName (permission issue?).<br>";
                }
            }

            if ($uploaded > 0) {
                $success = "$uploaded image(s) uploaded successfully!";
            }
        }
    }
}

$nav_links = [
    ['url' => 'index.php',          'label' => 'Home',    'active' => false],
    ['url' => '/opmm/view_activity.php?id=' . $id, 'label' => 'Activity', 'active' => false],
];

?>

<?php include '../includes/header.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Documentation - <?= htmlspecialchars($activity['activity_name']) ?></title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .upload-container {
            max-width: 600px;
            margin: 40px auto;
            padding: 30px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>

    <main class="dashboard-content">
        <h1>Add Documentation for <?= htmlspecialchars($activity['activity_name']) ?></h1>

        <?php if ($success): ?>
            <p style="background:#d1fae5; color:#065f46; padding:12px; border-radius:6px;"><?= $success ?></p>
        <?php endif; ?>

        <?php if ($error): ?>
            <p class="error"><?= $error ?></p>
        <?php endif; ?>

        <div class="upload-container">
            <?php if ($canUpload): ?>
                <form method="POST" enctype="multipart/form-data">
                    <label for="images">Upload up to <?= $remaining ?> more image(s) (jpg, jpeg, png only, max 5MB each)</label>
                    <input type="file" id="images" name="images[]" accept="image/jpeg,image/png" multiple required>

                    <button type="submit" class="action-btn add" style="margin-top:20px; background:#c8102e; color:white;">Upload Images</button>
                </form>
            <?php else: ?>
                <p style="color:#c8102e; font-weight:500;">
                    You already have <?= $maxAllowed ?> images uploaded.<br>
                    Go back to the Activity view to review or manage existing photos.
                </p>
            <?php endif; ?>
        </div>
    </main>

</body>
</html>