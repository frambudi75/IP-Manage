<?php
/**
 * Auto-Migration Handler for Docker & Existing Installs
 * Checks and creates missing tables dynamically.
 */
require_once 'includes/config.php';
require_once 'includes/db.php';

echo "[$(date)] Checking for Database Upgrades...\n";

try {
    $db = get_db_connection();
    
    // Check if netwatch table exists
    $tableExists = $db->query("SHOW TABLES LIKE 'netwatch'")->rowCount() > 0;
    
    if (!$tableExists) {
        echo "Creating missing table: netwatch...\n";
        $sql = "CREATE TABLE `netwatch` (
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
        echo "Table 'netwatch' created successfully.\n";
    } else {
        echo "Database schema is up to date.\n";
    }

} catch (Exception $e) {
    echo "Migration Error: " . $e->getMessage() . "\n";
}
