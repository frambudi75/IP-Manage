<?php
/**
 * IPManager Pro - Live Switch Health SSE Stream
 * 
 * Server-Sent Events endpoint that streams live CPU/Memory data
 * directly from SNMP to the browser in real-time.
 * 
 * Usage: GET /api/switch-health-stream.php?id=<switch_id>
 */

require_once '../includes/config.php';
require_once '../includes/db.php';

session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit;
}

// Fetch switch info
$db = get_db_connection();
$stmt = $db->prepare("SELECT * FROM switches WHERE id = ?");
$stmt->execute([$id]);
$switch = $stmt->fetch();

if (!$switch) {
    http_response_code(404);
    exit;
}

// SSE Headers
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // Disable Nginx buffering if behind proxy

// Disable output buffering
if (ob_get_level()) ob_end_clean();

$ip        = $switch['ip_addr'];
$community = $switch['community'];

if (!extension_loaded('snmp')) {
    // If SNMP not available, just stream DB data
    $send_db_data = true;
}

snmp_set_quick_print(1);
snmp_set_valueretrieval(SNMP_VALUE_PLAIN);

/**
 * Poll CPU and Memory directly via SNMP
 */
function poll_live_health(string $ip, string $community, string $model): array {
    $cpu = 0;
    $mem = 0;

    if ($model === 'MikroTik') {
        $cpu = (int)@snmp2_get($ip, $community, ".1.3.6.1.4.1.14988.1.1.3.11.0");

        $total_mem = (int)@snmp2_get($ip, $community, ".1.3.6.1.4.1.14988.1.1.3.8.0");
        $used_mem  = (int)@snmp2_get($ip, $community, ".1.3.6.1.4.1.14988.1.1.3.9.0");
        if (!$total_mem) {
            $total_mem = (int)@snmp2_get($ip, $community, ".1.3.6.1.2.1.25.2.3.1.5.65536");
            $used_mem  = (int)@snmp2_get($ip, $community, ".1.3.6.1.2.1.25.2.3.1.6.65536");
        }
        if ($total_mem > 0) $mem = round(($used_mem / $total_mem) * 100);

    } elseif ($model === 'Cisco') {
        $cpu = (int)@snmp2_get($ip, $community, ".1.3.6.1.4.1.9.9.109.1.1.1.1.5.1");

        $mem_used = (int)@snmp2_get($ip, $community, ".1.3.6.1.4.1.9.9.48.1.1.1.5.1");
        $mem_free = (int)@snmp2_get($ip, $community, ".1.3.6.1.4.1.9.9.48.1.1.1.6.1");
        if ($mem_used > 0) $mem = round(($mem_used / ($mem_used + $mem_free)) * 100);

    } else {
        // Generic MIB
        $cores = @snmp2_real_walk($ip, $community, ".1.3.6.1.2.1.25.3.3.1.2");
        if ($cores) {
            $cpu_sum = 0; $count = 0;
            foreach ($cores as $val) { $cpu_sum += (int)$val; $count++; }
            $cpu = $count > 0 ? round($cpu_sum / $count) : 0;
        }
        $total_mem = (int)@snmp2_get($ip, $community, ".1.3.6.1.2.1.25.2.3.1.5.65536");
        $used_mem  = (int)@snmp2_get($ip, $community, ".1.3.6.1.2.1.25.2.3.1.6.65536");
        if ($total_mem > 0) $mem = round(($used_mem / $total_mem) * 100);
    }

    return [
        'cpu' => min(100, max(0, $cpu)),
        'mem' => min(100, max(0, $mem)),
    ];
}

// Stream loop - send a new event every 5 seconds
$iteration = 0;
while (true) {
    if (connection_aborted()) break;

    if (!empty($send_db_data)) {
        // Fallback: read from DB
        $row = $db->prepare("SELECT cpu_usage, memory_usage, last_poll FROM switches WHERE id = ?");
        $row->execute([$id]);
        $data = $row->fetch();
        $payload = [
            'cpu'       => (int)($data['cpu_usage'] ?? 0),
            'mem'       => (int)($data['memory_usage'] ?? 0),
            'last_poll' => $data['last_poll'] ? date('H:i:s', strtotime($data['last_poll'])) : '-',
            'source'    => 'db',
        ];
    } else {
        // Live SNMP with Caching to support multiple concurrent viewers
        $redis = get_redis_connection();
        $cache_key = "switch_health_latest_{$id}";
        $cached_val = ($redis) ? $redis->get($cache_key) : null;
        
        if ($cached_val) {
            $health = json_decode($cached_val, true);
            $source = 'redis_cache';
        } else {
            $health = poll_live_health($ip, $community, $switch['model'] ?? 'Generic');
            // Cache results for 4 seconds (Stream polls every 5s)
            if ($redis) $redis->setex($cache_key, 4, json_encode($health));
            $source = 'live_snmp';
        }

        $payload = [
            'cpu'       => $health['cpu'],
            'mem'       => $health['mem'],
            'last_poll' => date('H:i:s'),
            'source'    => $source,
        ];
    }

    // SSE format: "data: <json>\n\n"
    echo "data: " . json_encode($payload) . "\n\n";
    flush();

    $iteration++;
    sleep(5); // Poll every 5 seconds
}
