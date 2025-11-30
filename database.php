<?php
require_once 'config.php';

class Database {
    private $connection;
    private static $instance = null;
    
    private function __construct() {
        try {
            $this->connection = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", 
                DB_USER, 
                DB_PASS
            );
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            // Create required tables if they don't exist
            $this->createRequiredTables();
        } catch(PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    // Helper method for prepared statements
    public function query($sql, $params = []) {
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
    
    private function createRequiredTables() {
        try {
            // Feed posts table
            $this->connection->exec("
                CREATE TABLE IF NOT EXISTS `feed_posts` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `user_id` INT NOT NULL,
                    `content_type` ENUM('event', 'competition', 'gallery', 'announcement', 'post') NOT NULL,
                    `content_id` INT DEFAULT NULL,
                    `title` VARCHAR(255) NOT NULL,
                    `description` TEXT,
                    `image` VARCHAR(255) DEFAULT NULL,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
                )
            ");
            
            // Feed likes table
            $this->connection->exec("
                CREATE TABLE IF NOT EXISTS `feed_likes` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `post_id` INT NOT NULL,
                    `user_id` INT NOT NULL,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (`post_id`) REFERENCES `feed_posts`(`id`) ON DELETE CASCADE,
                    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`),
                    UNIQUE KEY `unique_like` (`post_id`, `user_id`)
                )
            ");
            
            // Feed comments table
            $this->connection->exec("
                CREATE TABLE IF NOT EXISTS `feed_comments` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `post_id` INT NOT NULL,
                    `user_id` INT NOT NULL,
                    `comment` TEXT NOT NULL,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (`post_id`) REFERENCES `feed_posts`(`id`) ON DELETE CASCADE,
                    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
                )
            ");
            
            // Team members table
            $this->connection->exec("
                CREATE TABLE IF NOT EXISTS `team_members` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `user_id` INT DEFAULT NULL,
                    `name` VARCHAR(255) NOT NULL,
                    `position` VARCHAR(255) NOT NULL,
                    `bio` TEXT,
                    `contact_info` JSON,
                    `profile_picture` VARCHAR(255),
                    `display_order` INT DEFAULT 0,
                    `is_active` BOOLEAN DEFAULT TRUE,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )
            ");
            
        } catch(PDOException $e) {
            error_log("Table creation error: " . $e->getMessage());
        }
    }
}
?>
