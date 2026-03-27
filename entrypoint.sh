#!/bin/bash
set -e

# Start a background loop for automated scanning (every 5 minutes)
(
  while true; do
    echo "[$(date)] Running automated discovery..."
    php /var/www/html/api/cron.php
    sleep 300
  done
) &

# Start Apache in the foreground
apache2-foreground
