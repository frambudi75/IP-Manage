<?php
/**
 * IP Network Helpers
 */

function cidr_to_range($cidr) {
    list($subnet, $mask) = explode('/', $cidr);
    $start = ip2long($subnet);
    $total = pow(2, (32 - $mask));
    $end = $start + $total - 1;
    return [$start, $end];
}

function long2ip_safe($long) {
    return long2ip($long);
}

function get_ip_usage_count($db, $subnet_id) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM ip_addresses WHERE subnet_id = ?");
    $stmt->execute([$subnet_id]);
    return $stmt->fetchColumn();
}

/**
 * Ping an IP address
 */
function ping_ip($ip, $attempts = 1, $timeout_ms = 200) {
    $ip = normalize_ipv4($ip);
    if (!$ip) {
        return false;
    }

    $attempts = max(1, (int)$attempts);
    $timeout_ms = max(100, (int)$timeout_ms);

    for ($i = 0; $i < $attempts; $i++) {
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $cmd = "ping -n 1 -w {$timeout_ms} {$ip}";
    } else {
            $timeout_seconds = max(1, (int)ceil($timeout_ms / 1000));
            $cmd = "ping -c 1 -W {$timeout_seconds} {$ip}";
        }

        exec($cmd, $output, $result);
        if ($result === 0) {
            return true;
        }

        if ($i < $attempts - 1) {
            usleep(120000);
        }
    }

    return false;
}

/**
 * Quick TCP port check.
 */
function check_port($ip, $port, $timeout = 0.3, $attempts = 1) {
    $ip = normalize_ipv4($ip);
    $port = (int)$port;
    if (!$ip || $port < 1 || $port > 65535) {
        return false;
    }

    $attempts = max(1, (int)$attempts);
    for ($i = 0; $i < $attempts; $i++) {
        $socket = @fsockopen($ip, $port, $errno, $errstr, $timeout);
        if ($socket) {
            fclose($socket);
            return true;
        }

        if ($i < $attempts - 1) {
            usleep(100000);
        }
    }

    return false;
}

/**
 * Validate and normalize IPv4 string.
 */
function normalize_ipv4($ip) {
    $normalized = filter_var(trim((string)$ip), FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
    return $normalized ?: null;
}

/**
 * Validate and normalize MAC into AA:BB:CC:DD:EE:FF
 */
function normalize_mac($mac) {
    if (!$mac) {
        return null;
    }

    $clean = strtoupper(preg_replace('/[^0-9A-F]/i', '', (string)$mac));
    if (strlen($clean) !== 12) {
        return null;
    }

    if (!preg_match('/^[0-9A-F]{12}$/', $clean)) {
        return null;
    }

    return implode(':', str_split($clean, 2));
}

/**
 * Parse arp -a output into IP => MAC map.
 */
function parse_arp_table($arp_output = null) {
    if ($arp_output === null) {
        exec("arp -a", $arp_output);
    }

    $entries = [];
    foreach ($arp_output as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }

        // Windows: 192.168.1.1          00-11-22-33-44-55     dynamic
        if (preg_match('/\b((?:\d{1,3}\.){3}\d{1,3})\b\s+([0-9a-fA-F:-]{17})\b/i', $line, $matches)) {
            $ip = normalize_ipv4($matches[1]);
            $mac = normalize_mac($matches[2]);
            if ($ip && $mac) {
                $entries[$ip] = $mac;
                continue;
            }
        }

        // Linux/macOS style: ? (192.168.1.1) at 00:11:22:33:44:55 [ether] on eth0
        if (preg_match('/\((?:\s*)?((?:\d{1,3}\.){3}\d{1,3})\)\s+at\s+([0-9a-fA-F:-]{17})\b/i', $line, $matches)) {
            $ip = normalize_ipv4($matches[1]);
            $mac = normalize_mac($matches[2]);
            if ($ip && $mac) {
                $entries[$ip] = $mac;
            }
        }
    }

    return $entries;
}

/**
 * Refresh ARP cache and return parsed map.
 */
function refresh_arp_map() {
    exec("arp -a", $arp_output);
    return parse_arp_table($arp_output);
}

/**
 * Detect whether nmap exists on scanner host.
 */
function has_nmap_binary() {
    static $checked = false;
    static $available = false;

    if ($checked) {
        return $available;
    }

    $checked = true;
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        exec("where nmap", $out, $code);
    } else {
        exec("command -v nmap", $out, $code);
    }
    $available = ($code === 0);
    return $available;
}

/**
 * Optional nmap host-up fallback (disabled by default).
 */
function nmap_detect_host($ip) {
    $ip = normalize_ipv4($ip);
    if (!$ip || !defined('ENABLE_NMAP_FALLBACK') || !ENABLE_NMAP_FALLBACK || !has_nmap_binary()) {
        return false;
    }

    $target = escapeshellarg($ip);
    // Keep it tight: host discovery only, no DNS, short timeout.
    $cmd = "nmap -sn -n --host-timeout 2s {$target}";
    exec($cmd, $output, $code);
    if ($code !== 0 || empty($output)) {
        return false;
    }

    foreach ($output as $line) {
        if (stripos($line, 'Host is up') !== false) {
            return true;
        }
    }
    return false;
}

/**
 * Multi-probe host activity detection to reduce misses.
 */
function detect_host_signals($ip, &$arp_map) {
    $ip = normalize_ipv4($ip);
    if (!$ip) {
        return ['ping' => false, 'arp' => false, 'port' => false, 'nmap' => false, 'active' => false];
    }

    $signals = [
        'ping' => ping_ip($ip, 2, 300),
        'arp' => isset($arp_map[$ip]),
        'port' => false,
        'nmap' => false,
        'active' => false
    ];

    $ports = [80, 443, 22, 445, 3389];
    if (defined('DISCOVERY_AGGRESSIVE_MODE') && DISCOVERY_AGGRESSIVE_MODE) {
        $ports = [80, 443, 22, 445, 3389, 53, 139, 161];
    }
    foreach ($ports as $port) {
        if (check_port($ip, $port, 0.28, 1)) {
            $signals['port'] = true;
            break;
        }
    }

    // If we got any positive signal but ARP is still missing, refresh ARP once.
    if (($signals['ping'] || $signals['port']) && !$signals['arp']) {
        $fresh = refresh_arp_map();
        if (!empty($fresh)) {
            $arp_map = $fresh;
            $signals['arp'] = isset($arp_map[$ip]);
        }
    }

    // Final lightweight recheck path for uncertain hosts.
    if (!$signals['ping'] && !$signals['arp'] && !$signals['port']) {
        $signals['ping'] = ping_ip($ip, 1, 650);
        if ($signals['ping']) {
            $fresh = refresh_arp_map();
            if (!empty($fresh)) {
                $arp_map = $fresh;
                $signals['arp'] = isset($arp_map[$ip]);
            }
        }
    }

    // Optional heavy fallback only for borderline negatives.
    if (!$signals['ping'] && !$signals['arp'] && !$signals['port']) {
        $signals['nmap'] = nmap_detect_host($ip);
        if ($signals['nmap']) {
            $fresh = refresh_arp_map();
            if (!empty($fresh)) {
                $arp_map = $fresh;
                $signals['arp'] = isset($arp_map[$ip]);
            }
        }
    }

    $signals['active'] = $signals['ping'] || $signals['arp'] || $signals['port'] || $signals['nmap'];
    return $signals;
}

/**
 * Resolve hostname using reverse DNS and normalize result.
 */
function resolve_hostname($ip) {
    return resolve_hostname_with_retry($ip, 2, 120000);
}

/**
 * Normalize hostname text to safe DB value.
 */
function normalize_hostname($hostname) {
    $hostname = trim((string)$hostname, " \t\n\r\0\x0B.");
    if ($hostname === '') {
        return '';
    }

    $hostname = strtolower($hostname);
    if (preg_match('/\s/', $hostname)) {
        return '';
    }

    // Keep host label + fqdn safe characters only.
    if (!preg_match('/^[a-z0-9._-]+$/', $hostname)) {
        return '';
    }

    return substr($hostname, 0, 100);
}

/**
 * Resolve hostname with lightweight retry.
 */
function resolve_hostname_with_retry($ip, $attempts = 2, $sleep_microseconds = 120000) {
    $attempts = max(1, (int)$attempts);
    for ($try = 1; $try <= $attempts; $try++) {
        $hostname = @gethostbyaddr($ip);
        if ($hostname && $hostname !== $ip) {
            return normalize_hostname($hostname);
        }

        if ($try < $attempts) {
            usleep(max(0, (int)$sleep_microseconds));
        }
    }

    return '';
}

/**
 * Determine if address is usable host (skip network/broadcast on /30 and larger).
 */
function is_usable_host_long($ip_long, $start_long, $end_long, $mask) {
    $mask = (int)$mask;
    if ($mask <= 30 && ($ip_long === $start_long || $ip_long === $end_long)) {
        return false;
    }
    return true;
}

/**
 * Calculate confidence score and return source labels.
 */
function calculate_discovery_confidence($flags) {
    $weights = [
        'snmp' => 35,
        'arp' => 30,
        'nmap' => 25,
        'ping' => 20,
        'port' => 10,
        'dns' => 5
    ];

    $score = 0;
    $sources = [];
    foreach ($weights as $key => $weight) {
        if (!empty($flags[$key])) {
            $score += $weight;
            $sources[] = $key;
        }
    }

    if ($score < 5) {
        $score = 5;
    } elseif ($score > 100) {
        $score = 100;
    }

    return [
        'score' => $score,
        'sources' => implode(',', $sources)
    ];
}

/**
 * Check if IP is in ARP table (fallback for ICMP-blocking hosts)
 */
function is_ip_in_arp($ip, $arp_output = null) {
    $ip = normalize_ipv4($ip);
    if (!$ip) {
        return false;
    }

    $arp_map = parse_arp_table($arp_output);
    return isset($arp_map[$ip]);
}

/**
 * Get MAC address from ARP table for a given IP
 */
function get_mac_from_arp($ip, $arp_output = null) {
    $ip = normalize_ipv4($ip);
    if (!$ip) {
        return null;
    }

    $arp_map = parse_arp_table($arp_output);
    return $arp_map[$ip] ?? null;
}

/**
 * Get Vendor from MAC address prefix
 */
function get_vendor_by_mac($mac) {
    $mac = normalize_mac($mac);
    if (!$mac) return null;
    $prefix = substr(str_replace(':', '', $mac), 0, 6);
    
    // Simple local mapping of common vendors
    $vendors = [
        '000C29' => 'VMware', '000569' => 'VMware', '005056' => 'VMware',
        '00249B' => 'Google', 'BCF5AC' => 'Google', '20DFB9' => 'Google',
        '0017F2' => 'Apple', 'D8D1CB' => 'Apple', 'F8E903' => 'Apple',
        'B827EB' => 'Raspberry Pi', 'DCDECA' => 'Raspberry Pi',
        '00155D' => 'Microsoft (Hyper-V)',
        '000A19' => 'Cisco', '000142' => 'Cisco', '000143' => 'Cisco',
        '000E7F' => 'Hewlett Packard', '00110A' => 'Hewlett Packard',
        '001C23' => 'Dell', '00219B' => 'Dell', '000AC7' => 'Dell',
        '000D0B' => 'Tp-Link', '30B5C2' => 'Tp-Link',
        '00156D' => 'Ubiquiti', '24A43C' => 'Ubiquiti'
    ];

    if (isset($vendors[strtoupper($prefix)])) {
        return $vendors[strtoupper($prefix)];
    }

    // Optional: Try public API (only if you have internet access and it's fast)
    // $vendor = @file_get_contents("https://api.macvendors.com/" . urlencode($mac));
    // return $vendor ? $vendor : 'Unknown';
    
    return 'Generic / Unknown';
}



