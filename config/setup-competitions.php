<?php
require_once '../includes/database.php';

$db = Database::getInstance()->getConnection();

try {
    // Create competition_registrations table
    $db->exec("
        CREATE TABLE IF NOT EXISTS `competition_registrations` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `competition_id` int(11) NOT NULL,
            `user_id` int(11) DEFAULT NULL,
            `team_name` varchar(255) DEFAULT NULL,
            `team_leader_name` varchar(255) NOT NULL,
            `team_leader_email` varchar(255) NOT NULL,
            `team_leader_phone` varchar(20) DEFAULT NULL,
            `team_leader_sapid` varchar(20) DEFAULT NULL,
            `team_leader_department` varchar(100) DEFAULT NULL,
            `team_members` text DEFAULT NULL,
            `registration_date` timestamp DEFAULT CURRENT_TIMESTAMP,
            `status` enum('pending','approved','rejected') DEFAULT 'pending',
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Create competition_results table
    $db->exec("
        CREATE TABLE IF NOT EXISTS `competition_results` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `competition_id` int(11) NOT NULL,
            `result_data` text DEFAULT NULL,
            `result_file` varchar(255) DEFAULT NULL,
            `published` tinyint(1) DEFAULT 0,
            `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    echo "Competition tables created successfully!";
    
} catch (PDOException $e) {
    echo "Error creating tables: " . $e->getMessage();
}
?>