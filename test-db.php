<?php
// test-db.php - test database connection

require_once 'config/db.php';  // your existing db.php file

try {
    // Simple test query
    $stmt = $pdo->query("SELECT 1 AS test");
    $result = $stmt->fetch();

    echo "<h1>Database Connection Successful!</h1>";
    echo "<p>Connected to: oppm_db</p>";
    echo "<p>Test query result: " . $result['test'] . "</p>";

    // Show list of tables (optional)
    echo "<h3>Tables in oppm_db:</h3>";
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "<ul>";
    foreach ($tables as $table) {
        echo "<li>$table</li>";
    }
    echo "</ul>";

} catch (PDOException $e) {
    echo "<h1 style='color: red;'>Connection Failed</h1>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
}