<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/network.php';
require_once 'includes/audit.helper.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login');
    exit;
}

$db = get_db_connection();
$subnet_id = $_GET['id'] ?? 0;

// Fetch subnet info
$stmt = $db->prepare("SELECT s.*, v.number as vlan_number FROM subnets s LEFT JOIN vlans v ON s.vlan_id = v.id WHERE s.id = ?");
$stmt->execute([$subnet_id]);
$subnet = $stmt->fetch();

if (!$subnet) {
    die("Subnet not found");
}

$all_vlans = $db->query("SELECT id, number, name FROM vlans ORDER BY number ASC")->fetchAll();
$all_switches = $db->query("SELECT id, name, ip_addr FROM switches ORDER BY name ASC")->fetchAll();

$page_title = 'Subnet Details: ' . $subnet['subnet'] . '/' . $subnet['mask'];

// Handle IP allocation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_ip'])) {
    $ip_addr = $_POST['ip_addr'];
    $description = $_POST['description'];
    $hostname = $_POST['hostname'];
    $state = $_POST['state'] ?? 'active';
    $asset_tag = $_POST['asset_tag'] ?? null;
    $owner = $_POST['owner'] ?? null;

    // Fetch old info for logging
    $stmt = $db->prepare("SELECT * FROM ip_addresses WHERE subnet_id = ? AND ip_addr = ?");
    $stmt->execute([$subnet_id, $ip_addr]);
    $old_info = $stmt->fetch();

    try {
        $stmt = $db->prepare("INSERT INTO ip_addresses (subnet_id, ip_addr, description, hostname, state, asset_tag, owner) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE description=VALUES(description), hostname=VALUES(hostname), state=VALUES(state), asset_tag=VALUES(asset_tag), owner=VALUES(owner)");
        $stmt->execute([$subnet_id, $ip_addr, $description, $hostname, $state, $asset_tag, $owner]);
        
        // Log the change
        $new_info = ['hostname' => $hostname, 'description' => $description, 'state' => $state, 'asset_tag' => $asset_tag, 'owner' => $owner];
        AuditLogHelper::logIpUpdate($ip_addr, $old_info, $new_info);
    } catch (Exception $e) {}
}

// Handle Subnet Settings Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_subnet_settings'])) {
    $scan_interval = (int)$_POST['scan_interval'];
    $description = $_POST['description'] ?? '';
    $vlan_id = (isset($_POST['vlan_id']) && $_POST['vlan_id'] !== '') ? (int)$_POST['vlan_id'] : null;
    $utilization_threshold = (isset($_POST['utilization_threshold']) && $_POST['utilization_threshold'] !== '') ? (int)$_POST['utilization_threshold'] : null;
    $parent_switch_id = (isset($_POST['parent_switch_id']) && $_POST['parent_switch_id'] !== '') ? (int)$_POST['parent_switch_id'] : null;

    try {
        $stmt = $db->prepare("UPDATE subnets SET scan_interval = ?, description = ?, vlan_id = ?, utilization_threshold = ?, parent_switch_id = ? WHERE id = ?");
        $stmt->execute([$scan_interval, $description, $vlan_id, $utilization_threshold, $parent_switch_id, $subnet_id]);
        $stmt = $db->prepare("SELECT s.*, v.number as vlan_number FROM subnets s LEFT JOIN vlans v ON s.vlan_id = v.id WHERE s.id = ?");
        $stmt->execute([$subnet_id]);
        $subnet = $stmt->fetch();
    } catch (Exception $e) {}
}


// Fetch assigned IPs
$stmt = $db->prepare("SELECT * FROM ip_addresses WHERE subnet_id = ?");
$stmt->execute([$subnet_id]);
$assigned_ips = [];
while ($row = $stmt->fetch()) {
    $assigned_ips[$row['ip_addr']] = $row;
}

// Generate IP range (Display first 256 IPs for safety if subnet is larger)
list($start_long, $end_long) = cidr_to_range($subnet['subnet'] . '/' . $subnet['mask']);
$display_limit = 256;
$current_ips = [];
$stats = ['active' => 0, 'reserved' => 0, 'offline' => 0, 'dhcp' => 0, 'free' => 0];

for ($i = $start_long; $i <= min($end_long, $start_long + $display_limit - 1); $i++) {
    $ip = long2ip($i);
    $current_ips[] = $ip;
    if (isset($assigned_ips[$ip])) {
        $stats[$assigned_ips[$ip]['state']]++;
    } else {
        $stats['free']++;
    }
}

$total_displayed = count($current_ips);
$used_percentage = round((($total_displayed - $stats['free']) / $total_displayed) * 100, 1);

include 'includes/header.php';
?>

<div style="margin-bottom: 2.5rem;">
    <a href="subnets" class="text-muted back-link" style="font-size: 0.875rem; display: flex; align-items: center; gap: 5px; margin-bottom: 1rem;">
        <i data-lucide="arrow-left" style="width: 14px;"></i> Back to Subnets
    </a>
    <div style="display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 1rem;">
        <div>
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 0.5rem;">
                <h1 style="font-size: 2rem; font-weight: 700;"><?php echo $subnet['subnet']; ?>/<?php echo $subnet['mask']; ?></h1>
                <?php if ($subnet['vlan_number']): ?>
                    <span style="background: rgba(59, 130, 246, 0.1); color: var(--primary); padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; border: 1px solid rgba(59, 130, 246, 0.2);">
                        VLAN <?php echo $subnet['vlan_number']; ?>
                    </span>
                <?php endif; ?>
            </div>
            <p style="color: var(--text-muted); font-size: 1rem;"><?php echo $subnet['description'] ?: 'No description provided'; ?></p>
            <div class="no-print" style="display: flex; gap: 1rem; margin-top: 0.5rem; font-size: 0.75rem; color: var(--text-muted);">
                <span><i data-lucide="clock" style="width: 12px; vertical-align: middle;"></i> Auto-Scan: <?php echo ($subnet['scan_interval'] ?? 0) > 0 ? "Every {$subnet['scan_interval']} min" : "Disabled"; ?></span>
                <?php if ($subnet['last_scan'] ?? null): ?>
                    <span><i data-lucide="calendar" style="width: 12px; vertical-align: middle;"></i> Last: <?php echo date('d M Y H:i', strtotime($subnet['last_scan'])); ?></span>
                <?php endif; ?>
                <?php if (is_admin()): ?>
                    <a href="#" onclick="document.getElementById('settingsModal').style.display='flex'" style="color: var(--primary); text-decoration: none;">Change Settings</a>
                <?php endif; ?>
            </div>
        </div>
        
        <div style="display: flex; gap: 0.5rem; align-items: center;">
            <a href="export?type=subnet_details&id=<?php echo $subnet_id; ?>" class="btn btn-secondary" style="font-size: 0.8125rem;" title="Export IP list to CSV">
                <i data-lucide="download" style="width: 14px;"></i> Export CSV
            </a>
            <button class="btn btn-secondary" style="font-size: 0.8125rem;" onclick="window.print()" title="Generate PDF Report">
                <i data-lucide="printer" style="width: 14px;"></i> Print PDF
            </button>
            <?php if (is_admin()): ?>
            <button id="scanBtn" class="btn btn-primary" style="font-size: 0.8125rem;" onclick="scanSubnet(<?php echo $subnet_id; ?>)">
                <i data-lucide="search" style="width: 14px;"></i> Scan Subnet
            </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Usage Bar -->
<div class="no-print" style="margin-bottom: 3rem;">
    <div style="display: flex; justify-content: space-between; margin-bottom: 0.75rem; font-size: 0.875rem;">
        <span>IP Utilization Space (<?php echo ($total_displayed - $stats['free']); ?> / <?php echo $total_displayed; ?>)</span>
        <span class="text-muted">Total Available: <?php echo $stats['free']; ?></span>
    </div>
    <div style="height: 12px; background: var(--surface-light); border-radius: 6px; overflow: hidden; display: flex;">
        <div style="width: <?php echo ($stats['active'] / $total_displayed) * 100; ?>%; background: var(--success);" title="Active"></div>
        <div style="width: <?php echo ($stats['reserved'] / $total_displayed) * 100; ?>%; background: var(--warning);" title="Reserved"></div>
        <div style="width: <?php echo ($stats['dhcp'] / $total_displayed) * 100; ?>%; background: var(--primary);" title="DHCP"></div>
    </div>
    <div style="display: flex; gap: 1.5rem; margin-top: 1rem;">
        <div style="display: flex; align-items: center; gap: 6px; font-size: 0.75rem; color: var(--text-muted);">
            <div style="width: 10px; height: 10px; border-radius: 2px; background: var(--success);"></div> Active
        </div>
        <div style="display: flex; align-items: center; gap: 6px; font-size: 0.75rem; color: var(--text-muted);">
            <div style="width: 10px; height: 10px; border-radius: 2px; background: var(--warning);"></div> Reserved
        </div>
        <div style="display: flex; align-items: center; gap: 6px; font-size: 0.75rem; color: var(--text-muted);">
            <div style="width: 10px; height: 10px; border-radius: 2px; background: var(--primary);"></div> DHCP
        </div>
        <div style="display: flex; align-items: center; gap: 6px; font-size: 0.75rem; color: var(--text-muted);">
            <div style="width: 10px; height: 10px; border-radius: 2px; background: var(--surface-light);"></div> Free
        </div>
    </div>
</div>

<div class="card no-print" style="margin-bottom: 2rem; border-left: 4px solid var(--primary);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
        <h3 style="font-size: 1.125rem; display: flex; align-items: center; gap: 8px;">
            <i data-lucide="activity" style="width: 18px;"></i> Network Analysis
        </h3>
        <button id="analyzeBtn" class="btn" style="padding: 6px 12px; font-size: 0.75rem; background: rgba(59, 130, 246, 0.1); color: var(--primary);" onclick="analyzeNetwork(<?php echo $subnet_id; ?>)">
            <i data-lucide="play" style="width: 12px;"></i> Quick Analysis
        </button>
    </div>
    <div id="analysisSummary" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
        <div class="analysis-box" style="background: rgba(255,255,255,0.03); padding: 1rem; border-radius: 8px;">
            <div style="font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.5rem;">Detected Gateway</div>
            <div id="gatewayResult" style="font-weight: 600; font-size: 0.9rem;">-</div>
        </div>
        <div class="analysis-box" style="background: rgba(255,255,255,0.03); padding: 1rem; border-radius: 8px;">
            <div style="font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.5rem;">DNS Resolvers (Local)</div>
            <div id="dnsResult" style="font-weight: 600; font-size: 0.9rem;">-</div>
        </div>
        <div class="analysis-box" style="background: rgba(255,255,255,0.03); padding: 1rem; border-radius: 8px;">
            <div style="font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.5rem;">Routing Info</div>
            <div id="routingResult" style="font-weight: 600; font-size: 0.9rem;">-</div>
        </div>
    </div>
</div>

<div id="scanStatus" style="display: none; padding: 1.25rem; background: rgba(59, 130, 246, 0.05); border: 1px dashed var(--primary); color: var(--text); border-radius: 12px; margin-bottom: 2rem; align-items: center; gap: 15px;">
    <div class="spinner" style="width: 20px; height: 20px; border: 3px solid var(--primary); border-top-color: transparent; border-radius: 50%; animation: spin 1s linear infinite;"></div>
    <span id="scanStatusText" style="font-weight: 500;">Scanning subnet IPs... please wait (this may take a minute)</span>
</div>

<!-- Visual Grid -->
<div class="card no-print" style="margin-bottom: 2rem;">
    <h3 style="font-size: 1.125rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 8px;">
        <i data-lucide="grid-3x3" style="width: 18px;"></i> Visual IP Grid
    </h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(42px, 1fr)); gap: 8px;">
        <?php foreach ($current_ips as $ip): ?>
            <?php 
                $info = $assigned_ips[$ip] ?? null; 
                $ip_parts = explode('.', $ip);
                $last_octet = end($ip_parts);
                
                $bg = 'var(--surface-light)';
                $color = 'var(--text-muted)';
                $border = 'rgba(255,255,255,0.05)';
                
                if ($info) {
                    if ($info['state'] == 'active') { 
                        $bg = ($info['conflict_detected'] ?? 0) == 1 ? 'var(--danger)' : 'var(--success)';
                        $color = 'white'; 
                    }
                    else if ($info['state'] == 'reserved') { $bg = 'var(--warning)'; $color = 'white'; }
                    else if ($info['state'] == 'dhcp') { $bg = 'var(--primary)'; $color = 'white'; }
                    $border = ($info['conflict_detected'] ?? 0) == 1 ? 'var(--danger)' : 'transparent';
                }
            ?>
            <div 
                onclick="openEditModal('<?php echo $ip; ?>', '<?php echo $info['hostname'] ?? ''; ?>', '<?php echo $info['description'] ?? ''; ?>', '<?php echo $info['state'] ?? 'active'; ?>')"
                style="aspect-ratio: 1; background: <?php echo $bg; ?>; border: 1px solid <?php echo $border; ?>; border-radius: 6px; cursor: pointer; transition: all 0.2s ease; display: flex; align-items: center; justify-content: center; font-size: 0.65rem; font-weight: 600; color: <?php echo $color; ?>; opacity: <?php echo $info ? '1' : '0.4'; ?>; <?php echo ($info['conflict_detected'] ?? 0) == 1 ? 'box-shadow: 0 0 10px rgba(239, 68, 68, 0.4); border: 2px solid white;' : ''; ?>"
                title="<?php echo $ip; ?> <?php echo $info ? '(' . $info['state'] . ') ' . (($info['conflict_detected'] ?? 0) == 1 ? '[CONFLICT!] ' : '') . ($info['mac_addr'] ?? '') . ' ' . ($info['vendor'] ?? '') : '(Free)'; ?>"
                onmouseover="this.style.transform='scale(1.2)'; this.style.zIndex='10'; this.style.opacity='1'; this.style.boxShadow='0 0 15px rgba(59, 130, 246, 0.3)';"
                onmouseout="this.style.transform='scale(1)'; this.style.zIndex='1'; this.style.opacity='<?php echo $info ? '1' : '0.4'; ?>'; this.style.boxShadow='none';"
            >
                <?php echo $last_octet; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>


<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <h3 style="font-size: 1.125rem; display: flex; align-items: center; gap: 8px;">
            <i data-lucide="list" class="no-print" style="width: 18px;"></i> Detailed Allocation
        </h3>
    </div>
    
    <div class="table-container" style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse; text-align: left;">
            <thead>
                <tr style="border-bottom: 1px solid var(--border);">
                    <th style="padding: 1rem; color: var(--text-muted); font-size: 0.875rem;">IP Address</th>
                    <th style="padding: 1rem; color: var(--text-muted); font-size: 0.875rem;">Status</th>
                    <th style="padding: 1rem; color: var(--text-muted); font-size: 0.875rem;">Hostname</th>
                    <th style="padding: 1rem; color: var(--text-muted); font-size: 0.875rem;">Asset / Owner</th>
                    <th style="padding: 1rem; color: var(--text-muted); font-size: 0.875rem;">MAC / Vendor</th>
                    <th style="padding: 1rem; color: var(--text-muted); font-size: 0.875rem;">Switch Port</th>
                    <th style="padding: 1rem; color: var(--text-muted); font-size: 0.875rem;">OS / Device</th>
                    <th style="padding: 1rem; color: var(--text-muted); font-size: 0.875rem;">Description</th>
                    <th class="no-print" style="padding: 1rem; color: var(--text-muted); font-size: 0.875rem; text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                // Pre-fetch switch ports for this subnet
                $port_map = $db->query("SELECT m.mac_addr, m.port_name, s.name as switch_name FROM switch_port_map m JOIN switches s ON m.switch_id = s.id")->fetchAll(PDO::FETCH_GROUP|PDO::FETCH_ASSOC);
                
                foreach ($current_ips as $ip): 
                    $info = $assigned_ips[$ip] ?? null;
                    $mac = $info['mac_addr'] ?? null;
                    $port_info = ($mac && isset($port_map[$mac])) ? $port_map[$mac][0] : null;
                ?>
                    <?php
                        $confidence = $info ? (int)($info['confidence_score'] ?? 0) : 0;
                        $source_labels = [];
                        if ($info && !empty($info['data_sources'])) {
                            foreach (explode(',', $info['data_sources']) as $src) {
                                $src = strtoupper(trim($src));
                                if ($src !== '') {
                                    $source_labels[] = $src;
                                }
                            }
                        }
                        if ($confidence >= 80) {
                            $confidence_color = 'var(--success)';
                        } elseif ($confidence >= 60) {
                            $confidence_color = 'var(--primary)';
                        } elseif ($confidence >= 40) {
                            $confidence_color = 'var(--warning)';
                        } else {
                            $confidence_color = 'var(--text-muted)';
                        }
                    ?>
                    <tr class="<?php echo $info ? 'ip-assigned' : 'ip-free'; ?>" style="border-bottom: 1px solid var(--border); transition: background 0.2s ease;" onmouseover="this.style.background='rgba(59, 130, 246, 0.03)'" onmouseout="this.style.background='transparent'">
                        <td style="padding: 1rem; font-family: monospace; font-size: 0.9375rem; font-weight: 500; color: <?php echo $info ? 'var(--text)' : 'var(--text-muted)'; ?>;">
                            <?php echo $ip; ?>
                        </td>
                        <td style="padding: 1rem;">
                            <?php if ($info): ?>
                                <span style="font-size: 0.7rem; padding: 4px 10px; border-radius: 6px; background: <?php 
                                    echo $info['state'] == 'active' ? 'rgba(16, 185, 129, 0.1)' : 
                                        ($info['state'] == 'reserved' ? 'rgba(245, 158, 11, 0.1)' : 'rgba(100, 116, 139, 0.1)'); 
                                    ?>; color: <?php 
                                    echo $info['state'] == 'active' ? 'var(--success)' : 
                                        ($info['state'] == 'reserved' ? 'var(--warning)' : 'var(--text-muted)'); 
                                    ?>; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; border: 1px solid currentColor; border-opacity: 0.1;">
                                    <?php echo $info['state']; ?>
                                </span>
                                <?php if (($info['conflict_detected'] ?? 0) == 1): ?>
                                    <span style="font-size: 0.6rem; padding: 2px 6px; border-radius: 4px; background: rgba(239, 68, 68, 0.1); color: var(--danger); font-weight: 800; border: 1px solid var(--danger); margin-left: 5px;">
                                        CONFLICT
                                    </span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span style="font-size: 0.7rem; color: var(--text-muted); opacity: 0.4; font-weight: 600;">FREE</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 1rem; font-size: 0.875rem;"><?php echo ($info['hostname'] ?? '') ?: '<span style="opacity: 0.3">-</span>'; ?></td>
                        <td style="padding: 1rem; font-size: 0.875rem;">
                            <?php if ($info): ?>
                                <div style="display: flex; flex-direction: column; gap: 2px;">
                                    <div style="font-weight: 600; color: var(--primary);"><?php echo $info['asset_tag'] ?? '<span style="opacity: 0.3">-</span>'; ?></div>
                                    <div style="font-size: 0.75rem; color: var(--text-muted);"><?php echo $info['owner'] ?? ''; ?></div>
                                </div>
                            <?php else: ?>
                                <span style="opacity: 0.3">-</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 1rem; font-size: 0.875rem;">
                            <?php if ($info): ?>
                                <div style="display: flex; flex-direction: column; gap: 4px;">
                                    <span style="font-weight: 700; color: <?php echo $confidence_color; ?>;"><?php echo $confidence; ?>%</span>
                                    <div style="display: flex; gap: 4px; flex-wrap: wrap;">
                                        <?php if (!empty($source_labels)): ?>
                                            <?php foreach ($source_labels as $label): ?>
                                                <span style="font-size: 0.65rem; padding: 2px 6px; border-radius: 999px; border: 1px solid rgba(148, 163, 184, 0.3); color: var(--text-muted);"><?php echo htmlspecialchars($label); ?></span>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <span style="font-size: 0.72rem; color: var(--text-muted);">-</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <span style="opacity: 0.3">-</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 1rem; font-size: 0.875rem;">
                            <?php if ($port_info): ?>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <i data-lucide="map-pin" style="width: 14px; color: var(--primary);"></i>
                                    <div>
                                        <div style="font-weight: 600;"><?php echo htmlspecialchars($port_info['port_name']); ?></div>
                                        <div style="font-size: 0.7rem; color: var(--text-muted);"><?php echo htmlspecialchars($port_info['switch_name']); ?></div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <span style="opacity: 0.3">-</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 1rem; font-size: 0.8125rem;">
                            <div style="font-family: monospace; font-size: 0.8rem;"><?php echo $info['mac_addr'] ?? '<span style="opacity: 0.3">-</span>'; ?></div>
                            <div style="font-size: 0.7rem; color: var(--primary); font-weight: 500;"><?php echo $info['vendor'] ?? ''; ?></div>
                        </td>
                        <td style="padding: 1rem; font-size: 0.825rem;">
                            <?php if (!empty($info['os'])): ?>
                                <div style="display: flex; align-items: center; gap: 6px;">
                                    <?php 
                                        $os = strtolower($info['os']);
                                        $icon = 'monitor';
                                        $os_color = 'var(--text-muted)';
                                        if (strpos($os, 'windows') !== false) { $icon = 'layout'; $os_color = '#00a4ef'; }
                                        else if (strpos($os, 'linux') !== false) { $icon = 'terminal'; $os_color = '#fcc624'; }
                                        else if (strpos($os, 'apple') !== false || strpos($os, 'ios') !== false) { $icon = 'apple'; $os_color = '#a2aaad'; }
                                        else if (strpos($os, 'android') !== false) { $icon = 'smartphone'; $os_color = '#3ddc84'; }
                                    ?>
                                    <i data-lucide="<?php echo $icon; ?>" style="width: 14px; color: <?php echo $os_color; ?>;"></i>
                                    <span style="font-size: 0.75rem;"><?php echo htmlspecialchars($info['os']); ?></span>
                                </div>
                            <?php else: ?>
                                <span style="opacity: 0.3">-</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 1rem; font-size: 0.875rem; color: var(--text-muted);"><?php echo ($info['description'] ?? '') ?: '<span style="opacity: 0.3">-</span>'; ?></td>
                        <td class="no-print" style="padding: 1rem; text-align: right;">
                            <?php if (is_admin()): ?>
                            <button class="btn" style="padding: 6px; background: var(--surface-light); color: var(--text-muted);" onclick="openEditModal('<?php echo $ip; ?>', '<?php echo $info['hostname'] ?? ''; ?>', '<?php echo $info['description'] ?? ''; ?>', '<?php echo $info['state'] ?? 'active'; ?>', '<?php echo $info['asset_tag'] ?? ''; ?>', '<?php echo $info['owner'] ?? ''; ?>')">
                                <i data-lucide="edit-3" style="width: 14px;"></i>
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>


<!-- Settings Modal -->
<div id="settingsModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); align-items: center; justify-content: center; z-index: 1000;">
    <div class="card" style="width: 100%; max-width: 450px; padding: 2rem;">
        <h3 style="margin-bottom: 1.5rem;">Subnet Settings</h3>
        <form method="POST">
            <input type="hidden" name="update_subnet_settings" value="1">
            <div class="input-group">
                <label>Description</label>
                <input type="text" name="description" class="input-control" value="<?php echo htmlspecialchars($subnet['description'] ?? ''); ?>">
            </div>
            <div class="input-group">
                <label>VLAN (optional)</label>
                <select name="vlan_id" class="input-control" style="appearance: none;">
                    <option value="" <?php echo empty($subnet['vlan_id']) ? 'selected' : ''; ?>>No VLAN</option>
                    <?php foreach ($all_vlans as $v): ?>
                        <option value="<?php echo (int)$v['id']; ?>" <?php echo (isset($subnet['vlan_id']) && (int)$subnet['vlan_id'] === (int)$v['id']) ? 'selected' : ''; ?>>
                            VLAN <?php echo htmlspecialchars((string)$v['number']); ?> — <?php echo htmlspecialchars($v['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="input-group">
                <label>Auto-Scan Interval</label>
                <select name="scan_interval" class="input-control" style="appearance: none;">
                    <option value="0" <?php echo ($subnet['scan_interval'] ?? 0) == 0 ? 'selected' : ''; ?>>Manual Only</option>
                    <option value="30" <?php echo ($subnet['scan_interval'] ?? 0) == 30 ? 'selected' : ''; ?>>Every 30 Minutes</option>
                    <option value="60" <?php echo ($subnet['scan_interval'] ?? 0) == 60 ? 'selected' : ''; ?>>Every 1 Hour</option>
                    <option value="360" <?php echo ($subnet['scan_interval'] ?? 0) == 360 ? 'selected' : ''; ?>>Every 6 Hours</option>
                    <option value="720" <?php echo ($subnet['scan_interval'] ?? 0) == 720 ? 'selected' : ''; ?>>Every 12 Hours</option>
                    <option value="1440" <?php echo ($subnet['scan_interval'] ?? 0) == 1440 ? 'selected' : ''; ?>>Every 24 Hours</option>
                </select>
            </div>
            <div class="input-group">
                <label>Utilization Alert Threshold (%)</label>
                <input type="number" name="utilization_threshold" class="input-control" value="<?php echo htmlspecialchars($subnet['utilization_threshold'] ?? ''); ?>" placeholder="System Default (<?php echo Settings::get('subnet_limit_threshold', 80); ?>%)" min="1" max="100">
            </div>
            <div class="input-group">
                <label>Upstream / Gateway Switch (Manual Topology)</label>
                <select name="parent_switch_id" class="input-control" style="appearance: none;">
                    <option value="" <?php echo empty($subnet['parent_switch_id']) ? 'selected' : ''; ?>>-- Automatic (via VLAN) --</option>
                    <?php foreach ($all_switches as $sw): ?>
                        <option value="<?php echo (int)$sw['id']; ?>" <?php echo (isset($subnet['parent_switch_id']) && (int)$subnet['parent_switch_id'] === (int)$sw['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($sw['name']); ?> (<?php echo $sw['ip_addr']; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                <button type="button" class="btn" style="flex: 1; justify-content: center; background: var(--surface-light);" onclick="document.getElementById('settingsModal').style.display='none'">Cancel</button>
                <button type="submit" class="btn btn-primary" style="flex: 1; justify-content: center;">Save Settings</button>
            </div>
        </form>
    </div>
</div>

<!-- Assign Modal -->
<div id="editModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); align-items: center; justify-content: center; z-index: 1000;">
    <div class="card" style="width: 100%; max-width: 450px; padding: 2rem;">
        <h3 id="modalTitle" style="margin-bottom: 1.5rem;">Manage IP</h3>
        <form method="POST">
            <input type="hidden" name="assign_ip" value="1">
            <input type="hidden" name="ip_addr" id="modalIp">
            <div class="input-group">
                <label>Hostname</label>
                <input type="text" name="hostname" id="modalHostname" class="input-control">
            </div>
            <div class="input-group">
                <label>Description</label>
                <input type="text" name="description" id="modalDescription" class="input-control">
            </div>
            <div class="input-group">
                <label>Status</label>
                <select name="state" id="modalState" class="input-control" style="appearance: none;">
                    <option value="active">Active</option>
                    <option value="reserved">Reserved</option>
                    <option value="offline">Offline</option>
                    <option value="dhcp">DHCP Pool</option>
                </select>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="input-group">
                    <label>Asset Tag</label>
                    <input type="text" name="asset_tag" id="modalAssetTag" class="input-control" placeholder="E.g. SRV-001">
                </div>
                <div class="input-group">
                    <label>Owner / Dept</label>
                    <input type="text" name="owner" id="modalOwner" class="input-control" placeholder="E.g. IT Department">
                </div>
            </div>
            <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                <button type="button" class="btn" style="flex: 1; justify-content: center; background: var(--surface-light);" onclick="document.getElementById('editModal').style.display='none'">Cancel</button>
                <button type="submit" class="btn btn-primary" style="flex: 1; justify-content: center;">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
async function scanSubnet(id) {
    const btn = document.getElementById('scanBtn');
    const status = document.getElementById('scanStatus');
    const statusText = document.getElementById('scanStatusText');
    
    // Subnet range info (simplified for first 256 IPs)
    const subnetAddr = "<?php echo $subnet['subnet']; ?>";
    const mask = <?php echo $subnet['mask']; ?>;
    
    // Convert IP to long for chunking (simplified)
    const ipParts = subnetAddr.split('.').map(Number);
    const startLong = (ipParts[0] << 24) | (ipParts[1] << 16) | (ipParts[2] << 8) | ipParts[3];
    const totalToScan = Math.min(256, Math.pow(2, 32 - mask));
    
    btn.disabled = true;
    status.style.display = 'flex';
    statusText.innerText = `Starting parallel scan of ${totalToScan} IPs...`;

    const chunkSize = 16;
    const chunks = [];
    for (let i = 0; i < totalToScan; i += chunkSize) {
        chunks.push({
            start: startLong + i,
            end: Math.min(startLong + i + chunkSize - 1, startLong + totalToScan - 1)
        });
    }

    let completedChunks = 0;
    let foundCount = 0;

    const scanChunk = async (chunk) => {
        try {
            const response = await fetch(`api/scan?id=${id}&start=${chunk.start}&end=${chunk.end}`);
            const data = await response.json();
            if (data.success) {
                foundCount += data.data.found;
                // Update Grid Live
                data.data.ips.forEach(ipInfo => {
                    const lastOctet = ipInfo.ip.split('.').pop();
                    const gridItem = document.querySelector(`div[title*="${ipInfo.ip}"]`);
                    if (gridItem) {
                        gridItem.style.background = 'var(--success)';
                        gridItem.style.opacity = '1';
                        gridItem.style.boxShadow = '0 0 10px rgba(16, 185, 129, 0.4)';
                        gridItem.title = `${ipInfo.ip} (active) - Confidence: ${ipInfo.confidence}% | ${ipInfo.mac || ''} ${ipInfo.vendor || ''}`;
                    }
                    // Update Grid Live for Offline/Ghosts
                    if (data.data.offline_ips) {
                        data.data.offline_ips.forEach(ipAddr => {
                            const gridItem = document.querySelector(`div[title*="${ipAddr} (active)"]`) || document.querySelector(`div[title^="${ipAddr} "]`);
                            if (gridItem) {
                                gridItem.style.background = 'var(--surface-light)';
                                gridItem.style.opacity = '0.4';
                                gridItem.style.boxShadow = 'none';
                                gridItem.title = `${ipAddr} (offline)`;
                            }
                        });
                    }
                });
            }
        } catch (err) {
            console.error("Chunk error", err);
        } finally {
            completedChunks++;
            statusText.innerText = `Scanning... ${(completedChunks/chunks.length * 100).toFixed(0)}% (${foundCount} found)`;
        }
    };

    // Run 8 chunks in parallel at a time (controlled concurrency)
    const concurrentLimit = 8;
    for (let i = 0; i < chunks.length; i += concurrentLimit) {
        const batch = chunks.slice(i, i + concurrentLimit).map(scanChunk);
        await Promise.all(batch);
    }

    statusText.innerText = `Scan Complete! Found ${foundCount} active hosts. Reloading...`;
    setTimeout(() => location.reload(), 1500);
}

async function analyzeNetwork(id) {
    const btn = document.getElementById('analyzeBtn');
    const gatewayEl = document.getElementById('gatewayResult');
    const dnsEl = document.getElementById('dnsResult');
    const routingEl = document.getElementById('routingResult');
    
    btn.disabled = true;
    gatewayEl.innerHTML = '<span class="text-muted">Analyzing...</span>';
    dnsEl.innerHTML = '<span class="text-muted">Scanning...</span>';
    routingEl.innerHTML = '<span class="text-muted">Checking...</span>';

    try {
        const response = await fetch(`api/analyze?id=${id}`);
        const data = await response.json();
        if (data.success) {
            const results = data.data;
            
            // Gateway display
            if (results.gateways.length > 0) {
                gatewayEl.innerHTML = results.gateways.map(gw => 
                    `<div style="color: var(--success); display: flex; align-items: center; gap: 6px;">
                        <span style="width: 8px; height: 8px; border-radius: 50%; background: currentColor;"></span>
                        ${gw.ip} <span style="font-size: 0.7rem; color: var(--text-muted);">(${gw.vendor})</span>
                    </div>`
                ).join('');
            } else {
                gatewayEl.innerHTML = '<span style="color: var(--danger);">Not detected</span>';
            }

            // DNS display
            if (results.dns_resolvers.length > 0) {
                dnsEl.innerHTML = results.dns_resolvers.map(dns => 
                    `<div style="color: var(--primary); display: flex; align-items: center; gap: 6px;">
                        <i data-lucide="shield" style="width: 12px;"></i> ${dns.ip}
                    </div>`
                ).join('');
                if (window.lucide) window.lucide.createIcons();
            } else {
                dnsEl.innerHTML = '<span class="text-muted">None found in subnet</span>';
            }

            // Routing display
            routingEl.innerHTML = `
                <div style="font-size: 0.85rem;">
                    Local Interface: ${results.routing.local_interface}<br>
                    Range: ${results.routing.is_local_range ? '<span style="color: var(--success);">Inside local scope</span>' : '<span style="color: var(--warning);">External/Remote</span>'}
                </div>
            `;
        }
    } catch (err) {
        console.error("Analysis error", err);
    } finally {
        btn.disabled = false;
    }
}

function openEditModal(ip, hostname, desc, state, asset, owner) {
    document.getElementById('modalTitle').innerText = 'Manage IP: ' + ip;
    document.getElementById('modalIp').value = ip;
    document.getElementById('modalHostname').value = hostname || '';
    document.getElementById('modalDescription').value = desc || '';
    document.getElementById('modalState').value = state || 'active';
    document.getElementById('modalAssetTag').value = asset || '';
    document.getElementById('modalOwner').value = owner || '';
    document.getElementById('editModal').style.display = 'flex';
}
</script>

<?php include 'includes/footer.php'; ?>
