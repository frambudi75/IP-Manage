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

// App URL inside Docker
define('APP_URL', 'http://localhost:8080');

// Environment flag
define('ENV_DOCKER', true);
