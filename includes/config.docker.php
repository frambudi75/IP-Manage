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
