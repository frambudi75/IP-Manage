#!/bin/bash
set -e

# Start a background loop for automated tasks
(
  while true; do
    echo "[$(date)] Running Parallel Discovery..."
    php /var/www/html/cron_scanner.php
    
    echo "[$(date)] Polling Manageable Switches..."
    php /var/www/html/cron_switch_poll.php
    
    sleep 300
  done
) &

# Start Apache in the foreground
apache2-foreground
