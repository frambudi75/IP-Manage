<?php
/**
 * Database connection helper using PDO
 */

require_once 'config.php';

function get_db_connection() {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        run_auto_migrations($pdo);
        return $pdo;
    } catch (\PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}

/**
 * IPManager - Global Redis Client Getter
 * Returns a Redis instance if extension is loaded and connection works.
 */
function get_redis_connection() {
    static $redis_instance = null;
    
    // Check if extension is loaded
    if (!extension_loaded('redis')) return null;
    
    // Return existing instance if available
    if ($redis_instance !== null) return $redis_instance;
    
    try {
        $redis = new Redis();
        $host = defined('REDIS_HOST') ? REDIS_HOST : '127.0.0.1';
        $redis->connect($host, 6379, 1.5); // 1.5s timeout
        $redis_instance = $redis;
        return $redis_instance;
    } catch (Exception $e) {
        return null; // Silent fail if redis is down
    }
}

/**
 * Ensures database structure is up to date
 */
function run_auto_migrations($db) {
    // Check if subnets table exists first
    $tableExists = $db->query("SHOW TABLES LIKE 'subnets'")->rowCount() > 0;
    if (!$tableExists) {
        return; // Skip migrations if table hasn't been imported yet
    }

    // 1. Check Subnets table for new columns
    $cols = $db->query("SHOW COLUMNS FROM subnets")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('scan_interval', $cols)) {
        $db->exec("ALTER TABLE subnets ADD COLUMN scan_interval int(11) DEFAULT 0");
    }
    if (!in_array('last_scan', $cols)) {
        $db->exec("ALTER TABLE subnets ADD COLUMN last_scan timestamp NULL DEFAULT NULL");
    }
    if (!in_array('last_limit_alert', $cols)) {
        $db->exec("ALTER TABLE subnets ADD COLUMN last_limit_alert timestamp NULL DEFAULT NULL AFTER last_scan");
    }

    // 2. Check IP Addresses table for OS column
    $ip_cols = $db->query("SHOW COLUMNS FROM ip_addresses")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('os', $ip_cols)) {
        $db->exec("ALTER TABLE ip_addresses ADD COLUMN os varchar(100) DEFAULT NULL AFTER vendor");
    }

    if (!in_array('conflict_detected', $ip_cols)) {
        $db->exec("ALTER TABLE ip_addresses ADD COLUMN conflict_detected tinyint(1) NOT NULL DEFAULT 0 AFTER os");
    }

    // 3. Settings table
    try {
        $db->query("SELECT 1 FROM settings LIMIT 1");
    } catch (Exception $e) {
        $db->exec("
            CREATE TABLE IF NOT EXISTS `settings` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `key` varchar(50) NOT NULL,
            `value` text DEFAULT NULL,
            `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `key` (`key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ");
        // Insert default values
        $db->exec("
            INSERT IGNORE INTO `settings` (`key`, `value`) VALUES 
            ('telegram_enabled', '0'),
            ('telegram_bot_token', ''),
            ('telegram_chat_id', ''),
            ('email_enabled', '0'),
            ('admin_email', 'admin@example.com'),
            ('smtp_host', 'localhost'),
            ('smtp_port', '25'),
            ('smtp_user', ''),
            ('smtp_pass', ''),
            ('mail_from', ''),
            ('nmap_enabled', '0'),
            ('discovery_aggressive', '1'),
            ('subnet_limit_threshold', '80');
        ");
    }

    // 4. Create Audit Logs table
    $db->exec("CREATE TABLE IF NOT EXISTS audit_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        action VARCHAR(100) NOT NULL,
        target_type VARCHAR(50) NULL,
        target_id INT NULL,
        details TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

    // 5. Build Performance Indexes
    $db->exec("CREATE INDEX IF NOT EXISTS idx_mac ON ip_addresses(mac_addr)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_host ON ip_addresses(hostname)");

    // 6. Create Switches Table
    $db->exec("CREATE TABLE IF NOT EXISTS switches (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        ip_addr VARCHAR(45) NOT NULL,
        community VARCHAR(100) DEFAULT 'public',
        snmp_version ENUM('1', '2c', '3') DEFAULT '2c',
        model VARCHAR(100),
        uptime VARCHAR(100),
        cpu_usage INT DEFAULT 0,
        memory_usage INT DEFAULT 0,
        system_info TEXT,
        last_poll TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

    // 7. Create Switch Port Mapping Table
    $db->exec("CREATE TABLE IF NOT EXISTS switch_port_map (
        id INT AUTO_INCREMENT PRIMARY KEY,
        mac_addr VARCHAR(100) NOT NULL,
        switch_id INT NOT NULL,
        port_name VARCHAR(100) NOT NULL,
        vlan_id INT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `mac_switch` (`mac_addr`, `switch_id`),
        FOREIGN KEY (switch_id) REFERENCES switches(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

    // 8. Create Stats History Table
    $db->exec("CREATE TABLE IF NOT EXISTS stats_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        snapshot_date DATE NOT NULL,
        total_active INT NOT NULL,
        UNIQUE KEY `unique_date` (`snapshot_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

    // 9. Add missing columns to switches (Migration)
    try {
        $db->exec("ALTER TABLE switches ADD COLUMN IF NOT EXISTS model VARCHAR(100)");
        $db->exec("ALTER TABLE switches ADD COLUMN IF NOT EXISTS uptime VARCHAR(100)");
        $db->exec("ALTER TABLE switches ADD COLUMN IF NOT EXISTS cpu_usage INT DEFAULT 0");
        $db->exec("ALTER TABLE switches ADD COLUMN IF NOT EXISTS memory_usage INT DEFAULT 0");
        $db->exec("ALTER TABLE switches ADD COLUMN IF NOT EXISTS system_info TEXT");
    } catch(Exception $e) { /* Already exists or not supported */ }

    // 10. Create Switch Health History Table
    $db->exec("CREATE TABLE IF NOT EXISTS switch_health_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        switch_id INT NOT NULL,
        cpu_usage INT NOT NULL DEFAULT 0,
        memory_usage INT NOT NULL DEFAULT 0,
        recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_switch_time (switch_id, recorded_at),
        FOREIGN KEY (switch_id) REFERENCES switches(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
}
