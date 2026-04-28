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
    }

    // --- Switch SNMP Port Monitoring Updates ---
    
    // Check and update `switch_port_map` columns
    $portMapCols = $db->query("SHOW COLUMNS FROM `switch_port_map` LIKE 'port_status'")->rowCount();
    if ($portMapCols === 0) {
        echo "Updating switch_port_map table with new columns...\n";
        $sql = "ALTER TABLE `switch_port_map`
                ADD COLUMN `port_status` VARCHAR(20) DEFAULT NULL AFTER `vlan_id`,
                ADD COLUMN `port_type` VARCHAR(30) DEFAULT NULL AFTER `port_status`,
                ADD COLUMN `port_speed` VARCHAR(10) DEFAULT NULL AFTER `port_type`,
                ADD COLUMN `port_alias` VARCHAR(200) DEFAULT NULL AFTER `port_speed`;";
        $db->exec($sql);
        echo "switch_port_map table updated.\n";
    }
    
    // Check and update `switches` columns
    $switchCols = $db->query("SHOW COLUMNS FROM `switches` LIKE 'total_ports'")->rowCount();
    if ($switchCols === 0) {
        echo "Updating switches table with new columns...\n";
        $sql = "ALTER TABLE `switches`
                ADD COLUMN `total_ports` INT(11) DEFAULT 0 AFTER `system_info`,
                ADD COLUMN `active_ports` INT(11) DEFAULT 0 AFTER `total_ports`;";
        $db->exec($sql);
        echo "switches table updated.\n";
    }

    echo "Database schema is up to date.\n";

} catch (Exception $e) {
    echo "Migration Error: " . $e->getMessage() . "\n";
}
