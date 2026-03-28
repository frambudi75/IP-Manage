<?php
/**
 * Global Settings Helper
 */
class Settings {
    private static $cached_settings = null;
    private static $db = null;

    private static function init() {
        if (self::$cached_settings === null) {
            require_once 'db.php';
            self::$db = get_db_connection();
            
            // Auto-check for table existence and migrate if missing
            try {
                $stmt = self::$db->query("SELECT * FROM settings");
                $list = $stmt->fetchAll();
            } catch (Exception $e) {
                // Table doesn't exist, create it
                self::$db->exec("
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
                self::$db->exec("
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
                    ('discovery_aggressive', '1');
                ");
                $stmt = self::$db->query("SELECT * FROM settings");
                $list = $stmt->fetchAll();
            }

            self::$cached_settings = [];
            foreach ($list as $s) {
                self::$cached_settings[$s['key']] = $s['value'];
            }
        }
    }

    public static function get($key, $default = '') {
        try {
            self::init();
            return self::$cached_settings[$key] ?? $default;
        } catch (Exception $e) {
            return $default;
        }
    }

    public static function enabled($key) {
        return self::get($key, '0') === '1';
    }
}
