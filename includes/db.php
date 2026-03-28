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
 * Ensures database structure is up to date
 */
function run_auto_migrations($db) {
    // 1. Check Subnets table for new columns
    $cols = $db->query("SHOW COLUMNS FROM subnets")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('scan_interval', $cols)) {
        $db->exec("ALTER TABLE subnets ADD COLUMN scan_interval int(11) DEFAULT 0");
    }
    if (!in_array('last_scan', $cols)) {
        $db->exec("ALTER TABLE subnets ADD COLUMN last_scan timestamp NULL DEFAULT NULL");
    }

    // 2. Check IP Addresses table for OS column
    $ip_cols = $db->query("SHOW COLUMNS FROM ip_addresses")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('os', $ip_cols)) {
        $db->exec("ALTER TABLE ip_addresses ADD COLUMN os varchar(100) DEFAULT NULL AFTER vendor");
    }

    if (!in_array('conflict_detected', $ip_cols)) {
        $db->exec("ALTER TABLE ip_addresses ADD COLUMN conflict_detected tinyint(1) NOT NULL DEFAULT 0 AFTER os");
    }

    // 3. Settings table handled by settings.helper.php
}
