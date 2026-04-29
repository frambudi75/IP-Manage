<?php
/**
 * IPManager Pro - Switch SNMP Poller
 * Discovers MAC addresses and their physical port locations.
 * 
 * Enhanced with:
 * - Multi-OID interface name resolution (ifName → ifDescr → ifAlias)
 * - Interface status tracking (ifOperStatus)
 * - Interface type detection (ifType)
 * - Interface speed detection (ifHighSpeed / ifSpeed)
 * - Vendor-specific OID support (Alcatel-Lucent, Cisco, MikroTik)
 * - Human-readable uptime formatting
 */

require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/network.php';
require_once 'includes/audit.helper.php';

ob_start(); // Buffer output to prevent "Headers already sent" errors

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

/**
 * Convert SNMP timeticks to human-readable uptime string
 * SNMP sysUpTime is in hundredths of seconds (timeticks)
 */
function format_uptime_ticks($ticks) {
    $ticks = (int)$ticks;
    if ($ticks <= 0) return '-';
    
    $seconds = $ticks / 100;
    $days = floor($seconds / 86400);
    $hours = floor(($seconds % 86400) / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    
    $parts = [];
    if ($days > 0) $parts[] = $days . 'd';
    if ($hours > 0) $parts[] = $hours . 'h';
    if ($minutes > 0) $parts[] = $minutes . 'm';
    
    return implode(' ', $parts) ?: '< 1m';
}

/**
 * Walk an SNMP OID and return a map of [index => value]
 * Cleans string prefixes that some devices return.
 */
function snmp_walk_indexed($ip, $community, $oid) {
    $result = @snmp2_real_walk($ip, $community, $oid);
    if (!$result || !is_array($result)) return [];
    
    $map = [];
    foreach ($result as $full_oid => $val) {
        $parts = explode('.', $full_oid);
        $index = end($parts);
        // Clean any SNMP type prefixes
        $val = trim(str_replace(['STRING: ', 'INTEGER: ', 'Gauge32: ', 'Counter32: ', '"'], '', $val));
        $map[$index] = $val;
    }
    return $map;
}

/**
 * Detect interface type name from IANA ifType integer
 * See: https://www.iana.org/assignments/ianaiftype-mib/ianaiftype-mib
 */
function get_iftype_name($type_id) {
    $types = [
        1 => 'other',
        6 => 'ethernet',       // ethernetCsmacd
        24 => 'loopback',
        53 => 'propVirtual',   // Virtual/VLAN interface
        131 => 'tunnel',
        135 => 'l2vlan',       // Layer 2 VLAN (802.1Q)
        136 => 'l3ipvlan',
        150 => 'mplsTunnel',
        161 => 'ieee8023adLag', // Link Aggregation (LACP)
        209 => 'bridge',
    ];
    return $types[(int)$type_id] ?? 'other';
}

/**
 * Format interface speed to human-readable
 */
function format_speed($speed_mbps) {
    $speed_mbps = (int)$speed_mbps;
    if ($speed_mbps <= 0) return null;
    if ($speed_mbps >= 1000) {
        $gbps = $speed_mbps / 1000;
        // Show clean integers (1G, 10G, 40G) or one decimal (2.5G)
        return (floor($gbps) == $gbps ? (int)$gbps : round($gbps, 1)) . 'G';
    }
    return $speed_mbps . 'M';
}

/**
 * Convert raw ifDescr/ifName from Alcatel-Lucent to a friendlier port name
 * Alcatel typically returns names like "1/1", "1/1/1", "Alcatel-Lucent 1/1" etc.
 * The bridge port numbers on AOS are often 1001, 1002... = slot*1000 + port
 */
function normalize_port_name($raw_name, $bridge_port, $ifindex, $vendor) {
    // If we got a valid name from SNMP, use it
    if (!empty($raw_name) && $raw_name !== 'Port ' . $bridge_port) {
        return $raw_name;
    }
    
    // Alcatel-Lucent AOS: bridge port mapping
    // Port IDs typically: 1001 = 1/1, 1002 = 1/2, ..., 1024 = 1/24
    // For chassis: 2001 = 2/1, etc.
    if (stripos($vendor, 'Alcatel') !== false || stripos($vendor, 'Nokia') !== false || stripos($vendor, 'AOS') !== false) {
        $bp = (int)$bridge_port;
        if ($bp > 1000) {
            $slot = floor($bp / 1000);
            $port = $bp % 1000;
            return "$slot/$port";
        }
    }
    
    return "Port $bridge_port";
}

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
    $uptime_raw = trim((string)$sys_uptime);
    $uptime_str = format_uptime_ticks($uptime_raw);

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
        $cpu = @snmp2_get($ip, $community, ".1.3.6.1.4.1.14988.1.1.3.11.0");
        
        // Memory (Bytes) - mtxrHlMemoryTotal / Used
        $total_mem = @snmp2_get($ip, $community, ".1.3.6.1.4.1.14988.1.1.3.8.0");
        $used_mem = @snmp2_get($ip, $community, ".1.3.6.1.4.1.14988.1.1.3.9.0");
        
        // Fallback for RAM/CPU if MikroTik OIDs fail (common on ARM/some RouterOS versions)
        if (!$total_mem || $total_mem == 0) {
            $storage_types = @snmp2_real_walk($ip, $community, ".1.3.6.1.2.1.25.2.3.1.2");
            if ($storage_types) {
                foreach ($storage_types as $oid => $type) {
                    if (strpos($type, ".1.3.6.1.2.1.25.2.1.2") !== false) {
                        $parts = explode('.', $oid);
                        $idx = end($parts);
                        $total_mem = @snmp2_get($ip, $community, ".1.3.6.1.2.1.25.2.3.1.5.$idx");
                        $used_mem  = @snmp2_get($ip, $community, ".1.3.6.1.2.1.25.2.3.1.6.$idx");
                        break;
                    }
                }
            }
        }

        if ($cpu === false || $cpu === "") {
            $cores = @snmp2_real_walk($ip, $community, ".1.3.6.1.2.1.25.3.3.1.2");
            if ($cores) {
                $cpu_sum = 0; $count = 0;
                foreach ($cores as $val) { $cpu_sum += (int)$val; $count++; }
                $cpu = $count > 0 ? round($cpu_sum / $count) : 0;
            }
        }
        
        if ($total_mem > 0) $mem = round(((int)$used_mem / (int)$total_mem) * 100);
    } elseif (stripos($system_info, 'Alcatel') !== false || stripos($system_info, 'AOS') !== false || stripos($system_info, 'OmniSwitch') !== false) {
        $model = "Alcatel-Lucent";
        // Alcatel-Lucent OmniSwitch CPU/Memory
        // healthDeviceCpuLatest (1min avg): .1.3.6.1.4.1.6486.800.1.2.1.16.1.1.1.13.0
        $cpu = (int)@snmp2_get($ip, $community, ".1.3.6.1.4.1.6486.800.1.2.1.16.1.1.1.13.0");
        if (!$cpu) {
            // Alternative: healthModuleCpu1MinAvg
            $cpu = (int)@snmp2_get($ip, $community, ".1.3.6.1.4.1.6486.800.1.2.1.16.1.1.1.14.0");
        }
        
        // healthDeviceMemoryLatest
        $mem = (int)@snmp2_get($ip, $community, ".1.3.6.1.4.1.6486.800.1.2.1.16.1.1.1.10.0");
        if (!$mem) {
            // Fallback generic
            $total_mem = (int)@snmp2_get($ip, $community, ".1.3.6.1.2.1.25.2.3.1.5.65536");
            $used_mem = (int)@snmp2_get($ip, $community, ".1.3.6.1.2.1.25.2.3.1.6.65536");
            if ($total_mem > 0) $mem = round(($used_mem / $total_mem) * 100);
        }
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

    // Save to History (for graphs) - keep last 24h only
    $db->prepare("INSERT INTO switch_health_history (switch_id, cpu_usage, memory_usage) VALUES (?, ?, ?)")
       ->execute([$switch['id'], $cpu, $mem]);
    $db->prepare("DELETE FROM switch_health_history WHERE switch_id = ? AND recorded_at < DATE_SUB(NOW(), INTERVAL 48 HOUR)")
       ->execute([$switch['id']]);

    // --- Phase 1: Interface Discovery & Port Mapping ---
    echo "  Phase 1: Discovering interfaces...\n";
    
    // 1. Get Bridge Port → ifIndex mapping
    // OID: .1.3.6.1.2.1.17.1.4.1.2 (dot1dBasePortIfIndex)
    $port_to_ifindex = @snmprealwalk($ip, $community, ".1.3.6.1.2.1.17.1.4.1.2");
    if ($port_to_ifindex !== false) {
        $ifindex_map = [];
        foreach ($port_to_ifindex as $oid => $val) {
            $parts = explode('.', $oid);
            $port_num = end($parts);
            $ifindex_map[$port_num] = trim(str_replace('INTEGER: ', '', $val));
        }
        
        // 2. Get interface names using multiple OID sources for maximum compatibility
        // Priority: ifName (.1.3.6.1.2.1.31.1.1.1.1) → ifDescr (.1.3.6.1.2.1.2.2.1.2) → ifAlias (.1.3.6.1.2.1.31.1.1.1.18)
        
        echo "  Fetching interface names (ifName)...\n";
        $name_map_ifname = snmp_walk_indexed($ip, $community, ".1.3.6.1.2.1.31.1.1.1.1");
        echo "    ifName entries: " . count($name_map_ifname) . "\n";
        
        echo "  Fetching interface descriptions (ifDescr)...\n";
        $name_map_ifdescr = snmp_walk_indexed($ip, $community, ".1.3.6.1.2.1.2.2.1.2");
        echo "    ifDescr entries: " . count($name_map_ifdescr) . "\n";
        
        echo "  Fetching interface aliases (ifAlias)...\n";
        $name_map_ifalias = snmp_walk_indexed($ip, $community, ".1.3.6.1.2.1.31.1.1.1.18");
        echo "    ifAlias entries: " . count($name_map_ifalias) . "\n";
        
        // 3. Get interface operational status
        // OID: .1.3.6.1.2.1.2.2.1.8 (ifOperStatus) — 1=up, 2=down, 3=testing, ...
        echo "  Fetching interface status (ifOperStatus)...\n";
        $oper_status_map = snmp_walk_indexed($ip, $community, ".1.3.6.1.2.1.2.2.1.8");
        echo "    ifOperStatus entries: " . count($oper_status_map) . "\n";
        
        // 4. Get interface types
        // OID: .1.3.6.1.2.1.2.2.1.3 (ifType)
        $iftype_map = snmp_walk_indexed($ip, $community, ".1.3.6.1.2.1.2.2.1.3");
        
        // 4.5 Get VLAN Names
        // Standard OID: dot1qVlanStaticName (.1.3.6.1.2.1.17.7.1.4.3.1.1)
        echo "  Fetching VLAN names...\n";
        $vlan_names = snmp_walk_indexed($ip, $community, ".1.3.6.1.2.1.17.7.1.4.3.1.1");
        if (empty($vlan_names) && stripos($system_info, 'Cisco') !== false) {
            // Cisco VTP VLAN names
            $vlan_names = snmp_walk_indexed($ip, $community, ".1.3.6.1.4.1.9.9.46.1.3.1.1.4.1");
        }
        
        // 5. Get interface speed (ifHighSpeed in Mbps, fallback ifSpeed in bps)
        $ifhighspeed_map = snmp_walk_indexed($ip, $community, ".1.3.6.1.2.1.31.1.1.1.15");
        $ifspeed_map = [];
        if (empty($ifhighspeed_map)) {
            $ifspeed_raw = snmp_walk_indexed($ip, $community, ".1.3.6.1.2.1.2.2.1.5");
            foreach ($ifspeed_raw as $idx => $bps) {
                $ifspeed_map[$idx] = round((int)$bps / 1000000); // Convert bps to Mbps
            }
        } else {
            $ifspeed_map = $ifhighspeed_map;
        }

        // Build consolidated name map with smart fallback
        $name_map = [];
        foreach ($ifindex_map as $bridge_port => $ifindex) {
            // Priority: ifName (short) → ifDescr (longer) → ifAlias (custom description)
            $if_name = $name_map_ifname[$ifindex] ?? null;
            $if_descr = $name_map_ifdescr[$ifindex] ?? null;
            $if_alias = $name_map_ifalias[$ifindex] ?? null;
            
            // Use the best available name
            if (!empty($if_name) && strlen($if_name) > 0) {
                $name_map[$ifindex] = $if_name;
            } elseif (!empty($if_descr) && strlen($if_descr) > 0) {
                $name_map[$ifindex] = $if_descr;
            } elseif (!empty($if_alias) && strlen($if_alias) > 0) {
                $name_map[$ifindex] = $if_alias;
            }
            // If none found, will use normalized fallback later
        }
        
        // 6. Get FDB table (MAC to Bridge Port + VLAN)
        // Primary: dot1qTpFdbPort (.1.3.6.1.2.1.17.7.1.2.2.1.2) - VLAN Aware (802.1Q)
        // Fallback: dot1dTpFdbPort (.1.3.6.1.2.1.17.4.3.1.2) - Generic
        // Cisco IOS fallback: per-VLAN community polling (community@vlan)
        $fdb_table = @snmprealwalk($ip, $community, ".1.3.6.1.2.1.17.7.1.2.2.1.2");
        $is_vlan_aware = ($fdb_table !== false && count($fdb_table) > 0);
        
        if (!$is_vlan_aware) {
            $fdb_table = @snmprealwalk($ip, $community, ".1.3.6.1.2.1.17.4.3.1.2");
        }
        
        // Cisco IOS per-VLAN community polling fallback
        // Some Cisco IOS switches require community@vlan to access FDB per VLAN context
        if ((!$fdb_table || count($fdb_table) === 0) && stripos($system_info, 'Cisco') !== false) {
            echo "  Cisco detected: trying per-VLAN community polling...\n";
            $vlan_list = snmp_walk_indexed($ip, $community, ".1.3.6.1.4.1.9.9.46.1.3.1.1.2"); // vtpVlanState
            if (empty($vlan_list)) {
                // Fallback: try dot1qVlanStaticRowStatus
                $vlan_list = snmp_walk_indexed($ip, $community, ".1.3.6.1.2.1.17.7.1.4.3.1.5");
            }
            
            $fdb_table = [];
            $cisco_vlans = !empty($vlan_list) ? array_keys($vlan_list) : [1]; // Default VLAN 1
            foreach ($cisco_vlans as $vlan_num) {
                $vlan_num = (int)$vlan_num;
                if ($vlan_num >= 1002 && $vlan_num <= 1005) continue; // Skip reserved VLANs
                
                $vlan_community = $community . '@' . $vlan_num;
                $vlan_fdb = @snmprealwalk($ip, $vlan_community, ".1.3.6.1.2.1.17.4.3.1.2");
                if ($vlan_fdb && is_array($vlan_fdb)) {
                    // Tag each entry with its VLAN for later extraction
                    foreach ($vlan_fdb as $oid => $val) {
                        $fdb_table[$oid . '.__vlan__.' . $vlan_num] = $val;
                    }
                    echo "    VLAN $vlan_num: " . count($vlan_fdb) . " entries\n";
                }
            }
            $is_vlan_aware = false; // Use dot1d parsing but with tagged VLANs
        }

        if ($fdb_table) {
            $discovered_count = 0;
            foreach ($fdb_table as $oid => $val) {
                // Check for Cisco per-VLAN tagged entries
                $cisco_vlan_tag = null;
                if (strpos($oid, '.__vlan__.') !== false) {
                    [$oid, , $cisco_vlan_tag] = explode('.__vlan__.', $oid . '.__vlan__.');
                    $cisco_vlan_tag = (int)$cisco_vlan_tag;
                }
                
                $parts = explode('.', $oid);
                
                if ($is_vlan_aware && !$cisco_vlan_tag) {
                    // Structure: ...1.2.2.1.2.<VLAN>.<MAC_6_PARTS>
                    $vlan_id = (int)$parts[count($parts) - 7];
                    $mac_dec = array_slice($parts, -6);
                } else {
                    // Structure: ...4.3.1.2.<MAC_6_PARTS>
                    $vlan_id = $cisco_vlan_tag ?: null;
                    $mac_dec = array_slice($parts, -6);
                }

                $mac_hex = [];
                foreach ($mac_dec as $dec) {
                    $mac_hex[] = str_pad(dechex((int)$dec), 2, '0', STR_PAD_LEFT);
                }
                $mac_addr = strtoupper(implode(':', $mac_hex));
                
                // Validate MAC: must be 17 chars (AA:BB:CC:DD:EE:FF) and not broadcast/multicast
                if (strlen($mac_addr) !== 17 || $mac_addr === 'FF:FF:FF:FF:FF:FF' || $mac_addr === '00:00:00:00:00:00') {
                    continue;
                }
                
                $bridge_port = trim(str_replace('INTEGER: ', '', $val));
                
                // For Cisco per-VLAN polling, bridge-to-ifIndex might need VLAN context too
                $ifindex = $ifindex_map[$bridge_port] ?? null;
                if (!$ifindex && $cisco_vlan_tag) {
                    // Try fetching bridge port mapping with VLAN community
                    $vlan_ifindex = @snmp2_get($ip, $community . '@' . $cisco_vlan_tag, ".1.3.6.1.2.1.17.1.4.1.2." . $bridge_port);
                    if ($vlan_ifindex !== false) {
                        $ifindex = trim(str_replace('INTEGER: ', '', $vlan_ifindex));
                        $ifindex_map[$bridge_port] = $ifindex; // Cache for future lookups
                    }
                }
                
                // Fallback: If still no ifIndex, assume ifIndex = bridge_port
                // Many switches (like TP-Link, HP, Huawei) map bridge port directly to ifIndex 1:1
                if (!$ifindex) {
                    $ifindex = $bridge_port;
                }
                
                // Smart port name resolution with vendor-aware fallback
                $raw_name = $name_map[$ifindex] ?? null;
                $port_name = normalize_port_name($raw_name, $bridge_port, $ifindex, $system_info);
                
                // Get interface status for this port
                $port_status = null;
                if ($ifindex && isset($oper_status_map[$ifindex])) {
                    $status_int = (int)$oper_status_map[$ifindex];
                    $port_status = match($status_int) {
                        1 => 'up',
                        2 => 'down',
                        3 => 'testing',
                        5 => 'dormant',
                        6 => 'notPresent',
                        7 => 'lowerLayerDown',
                        default => 'unknown'
                    };
                }
                
                // Get interface type
                $port_type = null;
                if ($ifindex && isset($iftype_map[$ifindex])) {
                    $port_type = get_iftype_name($iftype_map[$ifindex]);
                }
                
                // Get interface speed
                $port_speed = null;
                if ($ifindex && isset($ifspeed_map[$ifindex])) {
                    $port_speed = format_speed($ifspeed_map[$ifindex]);
                }
                
                // Resolve VLAN Name
                $vlan_name = null;
                if ($vlan_id && isset($vlan_names[$vlan_id])) {
                    $vlan_name = trim($vlan_names[$vlan_id], '" ');
                }
                
                // Build alias info (stored as description for additional context)
                $port_alias = $name_map_ifalias[$ifindex] ?? null;
                
                if ($mac_addr && $port_name) {
                    $stmt = $db->prepare("INSERT INTO switch_port_map (mac_addr, switch_id, port_name, vlan_id, vlan_name, port_status, port_type, port_speed, port_alias) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE port_name = VALUES(port_name), vlan_id = VALUES(vlan_id), vlan_name = VALUES(vlan_name), port_status = VALUES(port_status), port_type = VALUES(port_type), port_speed = VALUES(port_speed), port_alias = VALUES(port_alias), updated_at = CURRENT_TIMESTAMP");
                    $stmt->execute([$mac_addr, $switch['id'], $port_name, $vlan_id, $vlan_name, $port_status, $port_type, $port_speed, $port_alias]);
                    $discovered_count++;
                }
            }
            
            $db->prepare("UPDATE switches SET last_poll = CURRENT_TIMESTAMP WHERE id = ?")->execute([$switch['id']]);
            echo "Discovered $discovered_count MAC-Port mappings (VLAN ".($is_vlan_aware ? "ON" : "OFF").") on {$switch['name']}.\n";
            AuditLogHelper::log("poll_switch", "switch", $switch['id'], "Discovered $discovered_count mappings on {$switch['name']}");
        }
    } else {
        echo "Note: Bridge port mapping (L2) not supported on {$ip}. Skipping L2, proceeding to L3 ARP...\n";
    }

    // --- Phase 2: Standalone Interface Inventory ---
    // Even if FDB is empty, discover all physical interfaces for visibility
    echo "  Phase 2: Interface inventory...\n";
    
    $if_names_all = snmp_walk_indexed($ip, $community, ".1.3.6.1.2.1.31.1.1.1.1");
    if (empty($if_names_all)) {
        $if_names_all = snmp_walk_indexed($ip, $community, ".1.3.6.1.2.1.2.2.1.2");
    }
    $if_oper_all = snmp_walk_indexed($ip, $community, ".1.3.6.1.2.1.2.2.1.8");
    $if_type_all = snmp_walk_indexed($ip, $community, ".1.3.6.1.2.1.2.2.1.3");
    $if_speed_all = snmp_walk_indexed($ip, $community, ".1.3.6.1.2.1.31.1.1.1.15");
    if (empty($if_speed_all)) {
        $if_speed_raw = snmp_walk_indexed($ip, $community, ".1.3.6.1.2.1.2.2.1.5");
        foreach ($if_speed_raw as $idx => $bps) {
            $if_speed_all[$idx] = round((int)$bps / 1000000);
        }
    }

    // Count total physical interfaces (ethernet type=6)
    $phys_interfaces = 0;
    $up_interfaces = 0;
    foreach ($if_type_all as $ifidx => $type_val) {
        if ((int)$type_val === 6) { // ethernetCsmacd
            $phys_interfaces++;
            if (isset($if_oper_all[$ifidx]) && (int)$if_oper_all[$ifidx] === 1) {
                $up_interfaces++;
            }
        }
    }
    
    // Save interface counts
    $db->prepare("UPDATE switches SET total_ports = ?, active_ports = ? WHERE id = ?")
       ->execute([$phys_interfaces, $up_interfaces, $switch['id']]);
    echo "  Interface inventory: $up_interfaces/$phys_interfaces ports up.\n";

    // --- Phase 3: L3 ARP Table Polling (ARP Discovery) ---
    // OID: .1.3.6.1.2.1.4.22.1.2 (ipNetToMediaPhysAddress)
    $arp_raw_macs = @snmprealwalk($ip, $community, ".1.3.6.1.2.1.4.22.1.2");
    if ($arp_raw_macs) {
        $arp_count = 0;
        foreach ($arp_raw_macs as $oid => $mac_bin) {
            // Extraction OID: .1.3.6.1.2.1.4.22.1.2.ifIndex.ipAddress (last 4 parts are IP)
            $parts = explode('.', $oid);
            $target_ip = implode('.', array_slice($parts, -4));
            
            // Normalize MAC from SNMP — handles multiple return formats:
            // 1. Raw binary (6 bytes)                    → bin2hex
            // 2. Hex-string with spaces ("AA BB CC...")  → strip spaces
            // 3. Hex-string with colons ("AA:BB:CC...")   → strip colons
            // 4. Quoted strings ('"XX XX..."')            → trim quotes first
            $mac_raw = trim($mac_bin, '" ');
            $target_mac = null;
            
            if (strlen($mac_raw) === 6) {
                // Raw binary: 6 bytes → convert to hex
                $target_mac = strtoupper(implode(':', str_split(bin2hex($mac_raw), 2)));
            } elseif (preg_match('/^([0-9A-Fa-f]{2}[: ]){5}[0-9A-Fa-f]{2}$/', $mac_raw)) {
                // Already formatted hex-string with colons or spaces
                $target_mac = strtoupper(str_replace(' ', ':', $mac_raw));
            } elseif (preg_match('/^[0-9A-Fa-f]{12}$/', $mac_raw)) {
                // Plain 12 hex characters without separators
                $target_mac = strtoupper(implode(':', str_split($mac_raw, 2)));
            }
            
            // Validate MAC format (AA:BB:CC:DD:EE:FF = 17 chars)
            if ($target_mac && strlen($target_mac) === 17 
                && $target_mac !== 'FF:FF:FF:FF:FF:FF' 
                && $target_mac !== '00:00:00:00:00:00') {
                
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
    if (!headers_sent()) {
        header('Location: switches.php?message=Poll completed');
    } else {
        echo "<hr><p>Poll completed. <a href='switches.php'>Click here to return</a></p>";
        echo "<script>setTimeout(() => { window.location.href = 'switches.php?message=Poll completed'; }, 2000);</script>";
    }
}
ob_end_flush();
