<?php
/**
 * IPManager Pro - Background Scanner Service
 * Run this script via Windows Task Scheduler or Cron
 * Example: php.exe c:\xampp\htdocs\ipmanage\cron_scanner.php
 */

// Disable frontend dependencies
define('IS_CRON', true);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/network.php';
require_once __DIR__ . '/includes/snmp.php';
require_once __DIR__ . '/includes/notifications.php';

// Security: Only allow CLI or a specific key if via HTTP
if (php_sapi_name() !== 'cli') {
    $key = $_GET['key'] ?? '';
    if ($key !== 'your-secret-key-change-me') {
        die("Unauthorized. Run via CLI or provide valid key.");
    }
}

$db = get_db_connection();

echo "[ " . date('Y-m-d H:i:s') . " ] Background Scan Started\n";

// Find subnets that need scanning (limit to 3 subnets per run to avoid timeout)
$stmt = $db->query("
    SELECT * FROM subnets 
    WHERE scan_interval > 0 
    AND (last_scan IS NULL OR last_scan < DATE_SUB(NOW(), INTERVAL scan_interval MINUTE))
    ORDER BY last_scan ASC 
    LIMIT 3
");

$subnets = $stmt->fetchAll();
if (empty($subnets)) {
    echo "No subnets due for scanning.\n";
    exit;
}

foreach ($subnets as $subnet) {
    echo "Processing Subnet: {$subnet['subnet']}/{$subnet['mask']}...\n";
    
    // Calculate range
    list($start_long, $end_long) = cidr_to_range($subnet['subnet'] . '/' . $subnet['mask']);
    
    // Pre-fetch ARP
    exec("arp -a", $arp_cache);
    $arp_map = parse_arp_table($arp_cache);
    
    $active_count = 0;
    
    // Iterate through first 256 IPs (limit for performance in cron)
    for ($i = $start_long; $i <= min($end_long, $start_long + 254); $i++) {
        if (!is_usable_host_long($i, $start_long, $end_long, (int)$subnet['mask'])) continue;
        
        $ip = long2ip($i);
        $signals = detect_host_signals($ip, $arp_map);
        
        if ($signals['active']) {
            $active_count++;
            
            // Basic discovery
            $hostname = resolve_hostname($ip);
            $mac = $arp_map[$ip] ?? null;
            $mac = normalize_mac($mac);
            $vendor = get_vendor_by_mac($mac);
            $os = ''; // Nmap fingerprinting can be added here if needed
            
            // Check for conflict/notification
            $stmt_check = $db->prepare("SELECT mac_addr FROM ip_addresses WHERE subnet_id = ? AND ip_addr = ?");
            $stmt_check->execute([$subnet['id'], $ip]);
            $current = $stmt_check->fetch();
            
            $conflict = 0;
            if ($current) {
                if ($current['mac_addr'] && $mac && $current['mac_addr'] !== $mac) {
                    $conflict = 1;
                    NotificationHelper::notifyConflict($ip, $current['mac_addr'], $mac, $subnet['subnet']);
                }
            } else {
                NotificationHelper::notifyNewDevice($ip, $mac, $vendor, $hostname, $subnet['subnet']);
            }
            
            // Update DB
            $save = $db->prepare("
                INSERT INTO ip_addresses (subnet_id, ip_addr, hostname, mac_addr, vendor, state, last_seen, conflict_detected)
                VALUES (?, ?, ?, ?, ?, 'active', CURRENT_TIMESTAMP, ?)
                ON DUPLICATE KEY UPDATE 
                    hostname = IF(VALUES(hostname) != '', VALUES(hostname), hostname),
                    mac_addr = IF(VALUES(mac_addr) != '', VALUES(mac_addr), mac_addr),
                    state = 'active',
                    last_seen = CURRENT_TIMESTAMP,
                    conflict_detected = VALUES(conflict_detected)
            ");
            $save->execute([$subnet['id'], $ip, $hostname, $mac, $vendor, $conflict]);
            
        } else {
            // Mark as offline if it was previously active
            $db->prepare("UPDATE ip_addresses SET state = 'offline' WHERE subnet_id = ? AND ip_addr = ? AND state = 'active'")
               ->execute([$subnet['id'], $ip]);
        }
    }
    
    // Update last_scan timestamp
    $db->prepare("UPDATE subnets SET last_scan = CURRENT_TIMESTAMP WHERE id = ?")
       ->execute([$subnet['id']]);
       
    echo "Finished Subnet. Found $active_count active devices.\n";
}

echo "All tasks completed.\n";
