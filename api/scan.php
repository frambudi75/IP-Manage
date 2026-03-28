<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/network.php';
require_once '../includes/snmp.php';
require_once '../includes/notifications.php';


session_start();

if (!isset($_SESSION['user_id'])) {
    json_response(['error' => 'Unauthorized'], 401);
}

$subnet_id = $_GET['id'] ?? 0;
if (!$subnet_id) {
    json_response(['error' => 'Invalid Subnet ID'], 400);
}

$db = get_db_connection();

// Fetch subnet info
$stmt = $db->prepare("SELECT * FROM subnets WHERE id = ?");
$stmt->execute([$subnet_id]);
$subnet = $stmt->fetch();

if (!$subnet) {
    json_response(['error' => 'Subnet not found'], 404);
}

// Increase execution time for scanning
set_time_limit(300);

// Calculate full subnet range
list($start_long, $end_long) = cidr_to_range($subnet['subnet'] . '/' . $subnet['mask']);

// Support chunked scanning for parallelism
$range_start = isset($_GET['start']) ? (int)$_GET['start'] : $start_long;
$range_end = isset($_GET['end']) ? (int)$_GET['end'] : min($end_long, $start_long + 63);

$results = [
    'scanned' => 0,
    'found' => 0,
    'ips' => [] // Return found IPs for live UI update
];

// Pre-fetch ARP table for efficiency
exec("arp -a", $arp_cache);
$arp_map = parse_arp_table($arp_cache);

for ($i = $range_start; $i <= $range_end; $i++) {
    if (!is_usable_host_long($i, $start_long, $end_long, (int)$subnet['mask'])) {
        continue;
    }

    $ip = long2ip($i);
    $ip = normalize_ipv4($ip);
    if (!$ip) {
        continue;
    }
    $results['scanned']++;
    
    // Multi-probe host detection to reduce false negatives.
    $signals = detect_host_signals($ip, $arp_map);
    $has_ping = $signals['ping'];
    $has_arp = $signals['arp'];
    $has_port = $signals['port'];
    $has_nmap = !empty($signals['nmap']);
    $is_active = $signals['active'];

    if ($is_active) {
        $found_ip_data = ['ip' => $ip, 'state' => 'active'];
        $results['found']++;
        $description = '';
        
        // Try to resolve hostname with normalization
        $hostname = resolve_hostname($ip);
        $has_dns = $hostname !== '';

        // Detect MAC and Vendor
        $mac = $arp_map[$ip] ?? null;
        if (!$mac) {
            $arp_map = refresh_arp_map();
            $mac = $arp_map[$ip] ?? null;
        }
        if (!$mac) {
            $mac = get_mac_from_arp($ip, $arp_cache);
        }
        $mac = normalize_mac($mac);
        $vendor = get_vendor_by_mac($mac);
        $has_snmp = false;

        // SNMP Discovery (Optional)
        $snmp_info = SNMPHelper::getInfo($ip, $subnet['snmp_community'] ?? 'public', $subnet['snmp_version'] ?? '2c');
        if ($snmp_info) {
            $has_snmp = true;
            if (!empty($snmp_info['name'])) {
                $snmp_hostname = normalize_hostname($snmp_info['name']);
                if ($snmp_hostname !== '') {
                    $hostname = $snmp_hostname;
                }
            }
            if (!empty($snmp_info['description'])) $description = $snmp_info['description'];
        }

        // OS Fingerprinting (Nmap)
        $os = '';
        if ($has_nmap || ($is_active && defined('DISCOVERY_AGGRESSIVE_MODE') && DISCOVERY_AGGRESSIVE_MODE)) {
            $os = nmap_fingerprint_os($ip);
        }

        $confidence = calculate_discovery_confidence([
            'ping' => $has_ping,
            'arp' => $has_arp,
            'nmap' => $has_nmap,
            'port' => $has_port,
            'dns' => $has_dns,
            'snmp' => $has_snmp
        ]);

        try {
            // Check current status for notifications
            $stmt = $db->prepare("SELECT state, mac_addr FROM ip_addresses WHERE subnet_id = ? AND ip_addr = ?");
            $stmt->execute([$subnet_id, $ip]);
            $current_data = $stmt->fetch();

            if (!$current_data) {
                // New device discovered
                NotificationHelper::notifyNewDevice($ip, $mac, $vendor, $hostname, $subnet['subnet']);
            } elseif ($current_data['state'] !== 'active' && $current_data['state'] !== 'reserved') {
                 // Re-discovered device (previously offline)
                 // NotificationHelper::notifyNewDevice($ip, $mac, $vendor, $hostname, $subnet['subnet']);
            } elseif ($current_data['mac_addr'] && $mac && $current_data['mac_addr'] !== $mac) {
                // IP Conflict (MAC changed)
                NotificationHelper::notifyConflict($ip, $current_data['mac_addr'], $mac, $subnet['subnet']);
            }

            // Update or Insert IP record
            $stmt = $db->prepare("
                INSERT INTO ip_addresses (subnet_id, ip_addr, hostname, mac_addr, vendor, os, description, state, last_seen, confidence_score, data_sources) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'active', CURRENT_TIMESTAMP, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    hostname = IF(VALUES(hostname) != '', VALUES(hostname), hostname),
                    mac_addr = IF(VALUES(mac_addr) != '', VALUES(mac_addr), mac_addr),
                    vendor = IF(VALUES(vendor) != '', VALUES(vendor), vendor),
                    os = IF(VALUES(os) != '', VALUES(os), os),
                    description = IF(VALUES(description) != '', VALUES(description), description),
                    confidence_score = VALUES(confidence_score),
                    data_sources = VALUES(data_sources),
                    state = 'active', 
                    last_seen = CURRENT_TIMESTAMP
            ");
            $stmt->execute([$subnet_id, $ip, $hostname, $mac, $vendor, $os, $description, $confidence['score'], $confidence['sources']]);
            
            $found_ip_data['hostname'] = $hostname;
            $found_ip_data['mac'] = $mac;
            $found_ip_data['vendor'] = $vendor;
            $found_ip_data['os'] = $os;
            $found_ip_data['confidence'] = $confidence['score'];
            $results['ips'][] = $found_ip_data;
        } catch (Exception $e) {
            // Log error
        }
    } else {
        // GHOST PREVENTION: If IP was active before but now not detected, mark as offline
        $stmt = $db->prepare("UPDATE ip_addresses SET state = 'offline' WHERE subnet_id = ? AND ip_addr = ? AND state = 'active'");
        $stmt->execute([$subnet_id, $ip]);
    }
}

json_response([
    'success' => true, 
    'message' => "Scanning complete. Scanned {$results['scanned']} IPs, found {$results['found']} active hosts.",
    'data' => $results
]);
