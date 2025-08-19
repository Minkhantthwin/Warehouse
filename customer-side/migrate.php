<?php
// Migration script to add password_hash column to Customer table
require_once '../includes/config.php';

try {
    // Check if password_hash column already exists
    $stmt = $pdo->query("SHOW COLUMNS FROM Customer LIKE 'password_hash'");
    $column_exists = $stmt->fetch();
    
    if (!$column_exists) {
        // Add password_hash column
        $pdo->exec("ALTER TABLE Customer ADD COLUMN password_hash VARCHAR(255) AFTER email");
        echo "Added password_hash column to Customer table.\n";
    } else {
        echo "password_hash column already exists in Customer table.\n";
    }
    
    echo "Migration completed successfully.\n";
    
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
?>
