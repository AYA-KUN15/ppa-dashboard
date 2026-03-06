<?php
// ../includes/header.php

// Define once here – change if you ever move the project folder
define('BASE_URL', '/opmm-dashboard/');

// Make sure version is defined (you can move this to config if preferred)
if (!defined('APP_VERSION')) {
    define('APP_VERSION', '10.4 Alpha');
}
?>

<header class="top-bar">
    <div class="logo-container">
        <img src="<?= BASE_URL ?>assets/bsu-logo.jpg" alt="BatStateU Logo" class="logo">
        <span class="logo-text">
            PPA Dashboard
            <small style="font-size: 0.72rem; color: #ffffff; margin-left: 8px; vertical-align: middle; font-weight: 400;">
                v<?= APP_VERSION ?>
            </small>
        </span>
    </div>

    <nav class="main-nav">
        <?php
        // Each page can define $nav_links before including header.php
        // Example structure: array of [url, label, active?]
        if (!isset($nav_links)) {
            $nav_links = []; // fallback
        }

        foreach ($nav_links as $link) {
            $class = $link['active'] ? 'nav-button active' : 'nav-button';
            echo '<a href="' . htmlspecialchars($link['url']) . '" class="' . $class . '">' 
                 . htmlspecialchars($link['label']) . '</a>';
        }

        // Always show Logout
        echo '<a href="../logout.php" class="nav-button logout">Logout</a>';
        ?>
    </nav>
</header>