<?php
/**
 * Netwatch Background Scanner
 * Runs as a cron job to check host availability
 */
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/notifications.php';

// Set execution time limit to 5 minutes to allow for multiple pings
set_time_limit(300);

$db = get_db_connection();

// Fetch all targets that need checking based on interval
// Formula: (NOW - last_check) >= ping_interval
$targets = $db->query("SELECT * FROM netwatch")->fetchAll();

echo "Starting Netwatch Scan at " . date('Y-m-d H:i:s') . "\n";

foreach ($targets as $t) {
    $host = $t['host'];
    $id = $t['id'];
    $current_status = $t['status'];
    $fail_count = (int)$t['fail_count'];
    $threshold = (int)$t['fail_threshold'];

    // Basic Ping check
    $is_up = false;
    
    // Windows-specific ping (XAMPP usually on Windows)
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        exec("ping -n 1 -w 1000 $host", $output, $result);
    } else {
        exec("ping -c 1 -W 1 $host", $output, $result);
    }

    if ($result === 0) {
        $is_up = true;
    }

    $new_status = $current_status;
    $new_fail_count = $fail_count;
    $update_fields = [];
    $update_fields[] = "last_check = NOW()";

    if ($is_up) {
        $new_status = 'up';
        $new_fail_count = 0;
        $update_fields[] = "last_up = NOW()";
        
        // If it was down before, log it (State Change UP)
        if ($current_status === 'down') {
            log_netwatch_change($id, $host, 'up');
            send_netwatch_notification($t, 'up');
        }
    } else {
        $new_fail_count++;
        if ($new_fail_count >= $threshold) {
            $new_status = 'down';
            
            // If it was up or unknown before, log it (State Change DOWN)
            if ($current_status !== 'down') {
                $update_fields[] = "last_down = NOW()";
                log_netwatch_change($id, $host, 'down');
                send_netwatch_notification($t, 'down');
            }
        }
    }

    $update_fields[] = "status = '$new_status'";
    $update_fields[] = "fail_count = $new_fail_count";

    $sql = "UPDATE netwatch SET " . implode(', ', $update_fields) . " WHERE id = $id";
    $db->exec($sql);

    echo "Target $host: ". strtoupper($new_status) . " (Fails: $new_fail_count)\n";
}

/**
 * Helper to log status changes in audit_logs
 */
function log_netwatch_change($id, $host, $status) {
    global $db;
    try {
        $message = "Netwatch: Host $host is now " . strtoupper($status);
        $stmt = $db->prepare("INSERT INTO audit_logs (action, target_type, target_id, details) VALUES (?, 'netwatch', ?, ?)");
        $stmt->execute(['STATUS_CHANGE', $id, $message]);
    } catch (Exception $e) {
        // Silently fail
    }
}

/**
 * Helper to send notifications
 */
function send_netwatch_notification($target, $status) {
    if ($target['notify'] == 1) {
        NotificationHelper::notifyNetwatch($target['name'], $target['host'], $status);
    }
}
