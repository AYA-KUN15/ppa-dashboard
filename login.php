<?php
// login.php

session_start();

// If already logged in, redirect to dashboard
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header("Location: index.php");
    exit;
}

// Include database connection
require_once 'config/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validation
    if (strlen($email) < 1 || strlen($email) > 50) {
        $error = 'Email must be between 1 and 50 characters.';
    } elseif (strlen($password) < 1 || strlen($password) > 100) {
        $error = 'Password must be between 1 and 100 characters.';
    } elseif (!str_ends_with(strtolower($email), '@g.batstate-u.edu.ph')) {
        $error = 'Only @g.batstate-u.edu.ph email addresses are allowed.';
    } else {
        // Query the user
        $stmt = $pdo->prepare("SELECT id, password_hash FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            // Success
            $_SESSION['logged_in'] = true;
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['email']     = $email;
            header("Location: index.php");
            exit;
        } else {
            $error = 'Invalid email or password.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - OPMM Dashboard</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="login-container">
        <h1>PPA Dashboard Login</h1>

        <?php if ($error): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" 
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" 
                   required maxlength="50" placeholder="yourname@g.batstate-u.edu.ph">

            <label for="password">Password</label>
            <div class="password-wrapper">
                <input type="password" id="password" name="password" 
                       required maxlength="100">
                <button type="button" class="toggle-password" aria-label="Toggle password visibility">
                    <span class="material-icons" id="toggle-icon">visibility</span>
                </button>
            </div>

            <button type="submit">Login</button>
        </form>

        <p class="info">
            Use your @g.batstate-u.edu.ph email<br>
            (Test account: test@g.batstate-u.edu.ph)
        </p>
    </div>

    <script>
        function togglePassword() {
            const input = document.getElementById('password');
            const icon = document.getElementById('toggle-icon');

            if (input.type === 'password') {
                input.type = 'text';
                icon.textContent = 'visibility_off';
            } else {
                input.type = 'password';
                icon.textContent = 'visibility';
            }
        }

        document.querySelector('.toggle-password').addEventListener('click', togglePassword);
    </script>
</body>
</html>