<?php
// ../includes/header.php

define('BASE_URL', '/opmm-dashboard/');   // ← your project root path

if (!defined('APP_VERSION')) {
    define('APP_VERSION', '1.0.1');
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
        // Each page defines $nav_links before include
        if (!isset($nav_links)) $nav_links = [];

        foreach ($nav_links as $link) {
            // Make EVERY link absolute using BASE_URL
            $url = BASE_URL . ltrim($link['url'], '/');
            $class = $link['active'] ? 'nav-button active' : 'nav-button';
            echo '<a href="' . htmlspecialchars($url) . '" class="' . $class . '">' 
                 . htmlspecialchars($link['label']) . '</a>';
        }

        // Logout is always absolute
        echo '<a href="' . BASE_URL . 'logout.php" class="nav-button logout">Logout</a>';
        ?>
    </nav>
</header>