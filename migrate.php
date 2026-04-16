<?php
/**
 * Quick Migration to create Netwatch Table
 * Visit this file in your browser on the Docker server
 */
require_once 'includes/config.php';
require_once 'includes/db.php';

try {
    $db = get_db_connection();
    
    $sql = "CREATE TABLE IF NOT EXISTS `netwatch` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `name` varchar(100) NOT NULL,
      `host` varchar(100) NOT NULL,
      `ping_interval` int(11) NOT NULL DEFAULT 60,
      `timeout` int(11) NOT NULL DEFAULT 2,
      `status` enum('up', 'down', 'unknown') NOT NULL DEFAULT 'unknown',
      `fail_count` int(11) NOT NULL DEFAULT 0,
      `fail_threshold` int(11) NOT NULL DEFAULT 3,
      `last_up` timestamp NULL DEFAULT NULL,
      `last_down` timestamp NULL DEFAULT NULL,
      `last_check` timestamp NULL DEFAULT NULL,
      `notify` tinyint(1) NOT NULL DEFAULT 0,
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;";

    $db->exec($sql);
    
    echo "<h1>✅ Migration Success!</h1>";
    echo "<p>Table 'netwatch' has been created successfully.</p>";
    echo "<p><a href='index'>Go to Dashboard</a></p>";
    
} catch (Exception $e) {
    echo "<h1>❌ Migration Failed</h1>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
}
?>
