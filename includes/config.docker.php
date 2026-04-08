<?php
/**
 * IPManager Docker-Specific Configuration
 * This file is automatically loaded when running inside a Docker container.
 */

// Database Configuration for Docker
define('DB_HOST', 'db'); // Linked container name
define('DB_NAME', 'ipmanage');
define('DB_USER', 'ipmanager');
define('DB_PASS', 'ipmanager_pass');

// App URL is set in config.php to avoid redefinition

// Environment flag
define('ENV_DOCKER', true);

// Redis Configuration for Docker
define('REDIS_HOST', 'redis'); // Linked container name
if (!defined('ENCRYPTION_KEY')) define('ENCRYPTION_KEY', '27ffed91f93d4e8eaf12a66852b4a156');

// OPTIMIZATION: Use Redis for sessions if extension is loaded
if (extension_loaded('redis')) {
    ini_set('session.save_handler', 'redis');
    ini_set('session.save_path', 'tcp://redis:6379');
}
