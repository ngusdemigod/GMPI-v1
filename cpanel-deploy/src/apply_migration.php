<?php
/**
 * Apply database migration to fix transactions.user_id constraint
 */

require_once __DIR__ . '/config/database.php';

try {
    $db = Database::getInstance();
    
    echo "Applying migration: fix_transactions_user_id.sql\n";
    
    // Alter the transactions table to allow NULL for user_id
    $sql = "ALTER TABLE transactions MODIFY COLUMN user_id INT DEFAULT NULL";
    
    $db->execute($sql);
    
    echo "Migration completed successfully!\n";
    echo "The transactions.user_id column now allows NULL values.\n";
    
} catch (Exception $e) {
    echo "Error applying migration: " . $e->getMessage() . "\n";
    exit(1);
}