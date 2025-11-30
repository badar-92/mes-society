<?php
// fix-database.php
require_once 'includes/database.php';

$db = Database::getInstance()->getConnection();

try {
    echo "Fixing database structure...<br>";
    
    // Create competitions table if it doesn't exist
    $db->exec("
        CREATE TABLE IF NOT EXISTS `competitions` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `title` varchar(255) NOT NULL,
            `description` text,
            `competition_type` enum('individual','team') DEFAULT 'individual',
            `category` varchar(100) DEFAULT NULL,
            `start_date` datetime NOT NULL,
            `end_date` datetime NOT NULL,
            `venue` varchar(255) NOT NULL,
            `max_participants` int(11) DEFAULT NULL,
            `registration_deadline` datetime DEFAULT NULL,
            `prize` text,
            `rules` text,
            `eligibility` text,
            `banner_image` varchar(255) DEFAULT NULL,
            `registration_open` tinyint(1) DEFAULT '1',
            `status` enum('draft','published','cancelled') DEFAULT 'draft',
            `created_by` int(11) DEFAULT NULL,
            `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "✓ Competitions table created/verified<br>";
    
    // Add missing columns to events table
    $db->exec("ALTER TABLE events ADD COLUMN IF NOT EXISTS `registration_deadline` datetime DEFAULT NULL");
    $db->exec("ALTER TABLE events ADD COLUMN IF NOT EXISTS `category` varchar(100) DEFAULT NULL");
    echo "✓ Events table columns added<br>";
    
    // Create settings table
    $db->exec("
        CREATE TABLE IF NOT EXISTS `settings` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `setting_key` varchar(255) NOT NULL,
            `setting_value` text,
            `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `setting_key` (`setting_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "✓ Settings table created<br>";
    
    // Add priority to duties table
    $db->exec("ALTER TABLE duties ADD COLUMN IF NOT EXISTS `priority` ENUM('low', 'medium', 'high') DEFAULT 'medium'");
    echo "✓ Duties table priority column added<br>";
    
    echo "<br>Database fix completed successfully!";
    
} catch(PDOException $e) {
    echo "Database fix failed: " . $e->getMessage();
}