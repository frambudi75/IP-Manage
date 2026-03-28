<?php
/**
 * IPManager Pro - Switch SNMP Poller
 * Discovers MAC addresses and their physical port locations.
 */

require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/network.php';
require_once 'includes/audit.helper.php';

if (!extension_loaded('snmp')) {
    die("PHP SNMP extension is not loaded. Please enable it in php.ini.");
}

// Set SNMP Options for cleaner data
snmp_set_quick_print(1);
snmp_set_valueretrieval(SNMP_VALUE_PLAIN);

$db = get_db_connection();
$switch_id = (int)($_GET['id'] ?? 0);

$query = "SELECT * FROM switches";
if ($switch_id > 0) $query .= " WHERE id = $switch_id";
$switches = $db->query($query)->fetchAll();

foreach ($switches as $switch) {
    echo "Polling Switch: {$switch['name']} ({$switch['ip_addr']})...\n";
    $ip = $switch['ip_addr'];
    $community = $switch['community'];
    
    // --- Phase 0: System Info & Health ---
    $sys_descr = @snmp2_get($ip, $community, ".1.3.6.1.2.1.1.1.0");
    $sys_uptime = @snmp2_get($ip, $community, ".1.3.6.1.2.1.1.3.0");
    
    $model = "Generic";
    $cpu = 0;
    $mem = 0;
    $system_info = trim((string)$sys_descr);
    $uptime_str = trim((string)$sys_uptime);

    // Smart Vendor Detection for CPU/RAM
    if (stripos($system_info, 'Cisco') !== false) {
        $model = "Cisco";
        $cpu = (int)@snmp2_get($ip, $community, ".1.3.6.1.4.1.9.9.109.1.1.1.1.5.1");
        $mem_free = (int)@snmp2_get($ip, $community, ".1.3.6.1.4.1.9.9.48.1.1.1.6.1");
        $mem_used = (int)@snmp2_get($ip, $community, ".1.3.6.1.4.1.9.9.48.1.1.1.5.1");
        if ($mem_used > 0) $mem = round(($mem_used / ($mem_used + $mem_free)) * 100);
    } elseif (stripos($system_info, 'MikroTik') !== false || stripos($system_info, 'RouterOS') !== false) {
        $model = "MikroTik";
        // CPU Load (%) - mtxrHlProcessorLoad
        $cpu = (int)@snmp2_get($ip, $community, ".1.3.6.1.4.1.14988.1.1.3.11.0");
        
        // Memory (Bytes) - mtxrHlMemoryTotal / Used
        $total_mem = (int)@snmp2_get($ip, $community, ".1.3.6.1.4.1.14988.1.1.3.8.0");
        $used_mem = (int)@snmp2_get($ip, $community, ".1.3.6.1.4.1.14988.1.1.3.9.0");
        
        // Fallback for RAM if MikroTik OID fails
        if (!$total_mem) {
            $total_mem = (int)@snmp2_get($ip, $community, ".1.3.6.1.2.1.25.2.3.1.5.65536");
            $used_mem = (int)@snmp2_get($ip, $community, ".1.3.6.1.2.1.25.2.3.1.6.65536");
        }
        
        if ($total_mem > 0) $mem = round(($used_mem / $total_mem) * 100);
    } else {
        // Generic Fallback (Standard Host Resources MIB - RFC 2790)
        $cores = @snmp2_real_walk($ip, $community, ".1.3.6.1.2.1.25.3.3.1.2");
        if ($cores) {
            $cpu_sum = 0; $count = 0;
            foreach ($cores as $val) {
                $cpu_sum += (int)$val;
                $count++;
            }
            $cpu = $count > 0 ? round($cpu_sum / $count) : 0;
        }
        
        // Generic RAM
        $total_mem = (int)@snmp2_get($ip, $community, ".1.3.6.1.2.1.25.2.3.1.5.65536");
        $used_mem = (int)@snmp2_get($ip, $community, ".1.3.6.1.2.1.25.2.3.1.6.65536");
        if ($total_mem > 0) $mem = round(($used_mem / $total_mem) * 100);
    }

    // Safety Bounds
    $cpu = min(100, max(0, (int)$cpu));
    $mem = min(100, max(0, (int)$mem));
    
    // Save System Stats
    $db->prepare("UPDATE switches SET model = ?, uptime = ?, cpu_usage = ?, memory_usage = ?, system_info = ? WHERE id = ?")
       ->execute([$model, $uptime_str, $cpu, $mem, $system_info, $switch['id']]);

    // --- Phase 1: Port Mapping (Already Existing Logic) ---
    // OID: .1.3.6.1.2.1.17.1.4.1.2 (dot1basePortIfIndex)
    $port_to_ifindex = @snmprealwalk($ip, $community, ".1.3.6.1.2.1.17.1.4.1.2");
    if ($port_to_ifindex === false) {
        echo "Failed to poll bridge port mapping from {$ip}.\n";
        continue;
    }
    
    $ifindex_map = [];
    foreach ($port_to_ifindex as $oid => $val) {
        $parts = explode('.', $oid);
        $port_num = end($parts);
        $ifindex_map[$port_num] = trim(str_replace('INTEGER: ', '', $val));
    }
    
    // 2. Get ifIndex to ifName mapping
    // OID: .1.3.6.1.2.1.31.1.1.1.1 (ifName)
    $ifindex_to_name = @snmprealwalk($ip, $community, ".1.3.6.1.2.1.31.1.1.1.1");
    $name_map = [];
    if ($ifindex_to_name) {
        foreach ($ifindex_to_name as $oid => $val) {
            $parts = explode('.', $oid);
            $ifindex = end($parts);
            $name_map[$ifindex] = trim(str_replace('STRING: ', '', str_replace('"', '', $val)));
        }
    }
    
    // 3. Get FDB table (MAC to Bridge Port)
    // OID: .1.3.6.1.2.1.17.4.3.1.2 (dot1dTpFdbPort)
    $fdb_table = @snmprealwalk($ip, $community, ".1.3.6.1.2.1.17.4.3.1.2");
    if ($fdb_table) {
        $discovered_count = 0;
        foreach ($fdb_table as $oid => $val) {
            $parts = explode('.', $oid);
            // Last 6 parts of OID are the MAC address in decimal
            $mac_dec = array_slice($parts, -6);
            $mac_hex = [];
            foreach ($mac_dec as $dec) {
                $mac_hex[] = str_pad(dechex($dec), 2, '0', STR_PAD_LEFT);
            }
            $mac_addr = strtoupper(implode(':', $mac_hex));
            $bridge_port = trim(str_replace('INTEGER: ', '', $val));
            
            $ifindex = $ifindex_map[$bridge_port] ?? null;
            $port_name = $name_map[$ifindex] ?? "Port $bridge_port";
            
            if ($mac_addr && $port_name) {
                // Save to DB
                $stmt = $db->prepare("INSERT INTO switch_port_map (mac_addr, switch_id, port_name) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE port_name = VALUES(port_name), updated_at = CURRENT_TIMESTAMP");
                $stmt->execute([$mac_addr, $switch['id'], $port_name]);
                $discovered_count++;
            }
        }
        
        $db->prepare("UPDATE switches SET last_poll = CURRENT_TIMESTAMP WHERE id = ?")->execute([$switch['id']]);
        echo "Discovered $discovered_count MAC-Port mappings on {$switch['name']}.\n";
        AuditLogHelper::log("poll_switch", "switch", $switch['id'], "Discovered $discovered_count mappings on {$switch['name']}");
    }

    // --- Phase 4: L3 ARP Table Polling (ARP Discovery) ---
    // OID: .1.3.6.1.2.1.4.22.1.2 (ipNetToMediaPhysAddress)
    $arp_raw_macs = @snmprealwalk($ip, $community, ".1.3.6.1.2.1.4.22.1.2");
    if ($arp_raw_macs) {
        $arp_count = 0;
        foreach ($arp_raw_macs as $oid => $mac_bin) {
            // Extraction OID: .1.3.6.1.2.1.4.22.1.2.ifIndex.ipAddress (last 4 parts are IP)
            $parts = explode('.', $oid);
            $target_ip = implode('.', array_slice($parts, -4));
            
            // Convert binary/hex string to AA:BB:CC...
            $mac_hex = bin2hex($mac_bin);
            if (strlen($mac_hex) === 12) {
                $target_mac = strtoupper(implode(':', str_split($mac_hex, 2)));
                
                // Resolve Subnet ID (Mandatory for Foreign Key)
                $target_subnet_id = find_subnet_for_ip($db, $target_ip);

                if ($target_subnet_id) {
                    // Save to IPAM Discovery Table
                    $stmt = $db->prepare("
                        INSERT INTO ip_addresses (subnet_id, ip_addr, mac_addr, state, last_seen, data_sources, confidence_score) 
                        VALUES (?, ?, ?, 'active', CURRENT_TIMESTAMP, 'snmp_arp', 80)
                        ON DUPLICATE KEY UPDATE 
                            mac_addr = VALUES(mac_addr),
                            state = 'active',
                            last_seen = CURRENT_TIMESTAMP,
                            data_sources = IF(data_sources NOT LIKE '%snmp_arp%', CONCAT(data_sources, ',snmp_arp'), data_sources)
                    ");
                    $stmt->execute([$target_subnet_id, $target_ip, $target_mac]);
                    $arp_count++;
                }
            }
        }
        echo "Discovered $arp_count ARP entries from {$switch['name']}.\n";
    }
}

if ($switch_id > 0) {
    header('Location: switches.php?message=Poll completed');
}
