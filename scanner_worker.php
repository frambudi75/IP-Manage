<?php
/**
 * IPManager Pro - Parallel Scanner Worker
 * This script is executed in the background to scan specific IP chunks.
 */

if (php_sapi_name() !== 'cli') {
    die("This script must be run via CLI.");
}

require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/network.php';

// Arguments: php scanner_worker.php [subnet_id] [start_ip_long] [end_ip_long]
$subnet_id = (int)($argv[1] ?? 0);
$start_long = (int)($argv[2] ?? 0);
$end_long = (int)($argv[3] ?? 0);

if (!$subnet_id || !$start_long || !$end_long) {
    die("Invalid arguments.\n");
}

$db = get_db_connection();

// Fetch subnet info
$stmt = $db->prepare("SELECT * FROM subnets WHERE id = ?");
$stmt->execute([$subnet_id]);
$subnet = $stmt->fetch();
if (!$subnet) die("Subnet not found.\n");

$is_local = !is_remote_subnet($subnet['subnet'], $subnet['mask']);
$nmap_enabled = Settings::enabled('nmap_enabled');

for ($i = $start_long; $i <= $end_long; $i++) {
    $ip = long2ip($i);
    
    // Perform discovery
    $signals = detect_host_signals($ip, $is_local, $nmap_enabled);
    
    if ($signals['active']) {
        // Fetch existing record
        $stmt = $db->prepare("SELECT * FROM ip_addresses WHERE subnet_id = ? AND ip_addr = ?");
        $stmt->execute([$subnet_id, $ip]);
        $existing = $stmt->fetch();
        
        $new_mac = $signals['mac'];
        $conflict_detected = 0;
        
        if ($existing && !empty($existing['mac_addr']) && !empty($new_mac)) {
            if (strtolower($existing['mac_addr']) !== strtolower($new_mac)) {
                $conflict_detected = 1;
                NotificationHelper::notifyConflict($ip, $existing['mac_addr'], $new_mac, $subnet['subnet'] . '/' . $subnet['mask']);
            }
        }
        
        // Update DB
        $stmt = $db->prepare("
            INSERT INTO ip_addresses (subnet_id, ip_addr, mac_addr, vendor, os, state, confidence_score, data_sources, conflict_detected, last_seen) 
            VALUES (?, ?, ?, ?, ?, 'active', ?, ?, ?, CURRENT_TIMESTAMP)
            ON DUPLICATE KEY UPDATE 
                mac_addr = IF(VALUES(mac_addr) IS NOT NULL, VALUES(mac_addr), mac_addr),
                vendor = IF(VALUES(vendor) IS NOT NULL, VALUES(vendor), vendor),
                os = IF(VALUES(os) IS NOT NULL, VALUES(os), os),
                state = 'active',
                confidence_score = VALUES(confidence_score),
                data_sources = VALUES(data_sources),
                conflict_detected = VALUES(conflict_detected),
                last_seen = CURRENT_TIMESTAMP
        ");
        
        $stmt->execute([
            $subnet_id, 
            $ip, 
            $new_mac, 
            $signals['vendor'], 
            $signals['os'] ?? null,
            $signals['confidence'],
            implode(',', $signals['sources']),
            $conflict_detected
        ]);
        
        if (!$existing) {
            NotificationHelper::notifyNewDevice($ip, $new_mac, $signals['vendor'], $subnet['subnet'] . '/' . $subnet['mask']);
        }
    }
}
echo "Worker finished chunk $start_long - $end_long\n";
