<?php
try {
    $pdo = new PDO('mysql:host=mysql;port=3306;dbname=church_partnership', 'church_user', 'church_password123', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    echo "Database connection successful!\n";
    $stmt = $pdo->query("SELECT project_id, title FROM projects LIMIT 5");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "Project {$row['project_id']}: {$row['title']}\n";
    }
} catch (Exception $e) {
    echo "Connection failed: " . $e->getMessage() . "\n";
}