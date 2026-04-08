<?php
require_once 'includes/config.php';
require_once 'includes/db.php';

session_start();

// Auth check
if (!isset($_SESSION['user_id'])) {
    header('Location: login');
    exit;
}

$page_title = 'Dashboard';
include 'includes/header.php';

// Fetch stats (with dummy fallbacks if DB tables are empty/missing)
$db = get_db_connection();
try {
    $subnet_count = $db->query("SELECT COUNT(*) FROM subnets")->fetchColumn() ?: 0;
    $ip_count = $db->query("SELECT COUNT(*) FROM ip_addresses")->fetchColumn() ?: 0;
    $vlan_count = $db->query("SELECT COUNT(*) FROM vlans")->fetchColumn() ?: 0;

    // Server Assets Stats
    $asset_count = $db->query("SELECT COUNT(*) FROM server_assets")->fetchColumn() ?: 0;
    $asset_online = $db->query("SELECT COUNT(*) FROM server_assets WHERE status = 'ONLINE'")->fetchColumn() ?: 0;
    $asset_offline = $db->query("SELECT COUNT(*) FROM server_assets WHERE status = 'OFFLINE'")->fetchColumn() ?: 0;
    
    // Asset Category distribution
    $asset_categories = $db->query("
        SELECT COALESCE(category, 'General') as cat, COUNT(*) as count 
        FROM server_assets 
        GROUP BY category 
        ORDER BY count DESC
    ")->fetchAll(PDO::FETCH_KEY_PAIR);

    $active_count = $db->query("SELECT COUNT(*) FROM ip_addresses WHERE state = 'active'")->fetchColumn() ?: 0;
    $offline_count = $db->query("SELECT COUNT(*) FROM ip_addresses WHERE state = 'offline'")->fetchColumn() ?: 0;
    $avg_confidence = $db->query("SELECT ROUND(AVG(confidence_score), 1) FROM ip_addresses WHERE state IN ('active', 'reserved', 'dhcp')")->fetchColumn();
    $avg_confidence = $avg_confidence !== null ? $avg_confidence : 0;
    $low_confidence_count = $db->query("SELECT COUNT(*) FROM ip_addresses WHERE confidence_score < 60 AND state IN ('active', 'reserved', 'dhcp')")->fetchColumn() ?: 0;

    // Vendor Distribution Data
    $vendor_data = $db->query("
        SELECT COALESCE(vendor, 'Unknown') as vendor, COUNT(*) as count 
        FROM ip_addresses 
        WHERE state = 'active' 
        GROUP BY vendor 
        ORDER BY count DESC 
        LIMIT 6
    ")->fetchAll();

    // Network Health Data
    $health_stats = $db->query("
        SELECT state, COUNT(*) as count 
        FROM ip_addresses 
        GROUP BY state
    ")->fetchAll(PDO::FETCH_KEY_PAIR);

    $recent_logs = $db->query("
        SELECT a.*, u.username 
        FROM audit_logs a 
        LEFT JOIN users u ON a.user_id = u.id 
        ORDER BY a.created_at DESC 
        LIMIT 5
    ")->fetchAll();

    // Usage Trends (Last 7 Days)
    $usage_trends = $db->query("SELECT snapshot_date, total_active FROM stats_history ORDER BY snapshot_date DESC LIMIT 7")->fetchAll();
    $usage_trends = array_reverse($usage_trends);
    
    // Fallback if empty
    if (empty($usage_trends)) {
        $usage_trends = [['snapshot_date' => date('Y-m-d'), 'total_active' => $active_count]];
    }

    // Densest Subnets
    $dense_subnets = $db->query("
        SELECT s.subnet, s.mask, 
               (COUNT(ip.id) * 100.0 / (POW(2, (32 - s.mask)) - (CASE WHEN s.mask < 31 THEN 2 ELSE 0 END))) as usage_percent
        FROM subnets s
        LEFT JOIN ip_addresses ip ON ip.subnet_id = s.id AND ip.state = 'active'
        GROUP BY s.id
        ORDER BY usage_percent DESC
        LIMIT 5
    ")->fetchAll();

    $recent_subnets = $db->query("
        SELECT s.id, s.subnet, s.mask, s.description,
               COUNT(ip.id) AS used_ips
        FROM subnets s
        LEFT JOIN ip_addresses ip ON ip.subnet_id = s.id AND ip.state = 'active'
        GROUP BY s.id, s.subnet, s.mask, s.description
        ORDER BY s.id DESC
        LIMIT 8
    ")->fetchAll();

    $needs_attention = $db->query("
        SELECT ip.ip_addr, ip.hostname, ip.mac_addr, ip.state, ip.last_seen, ip.confidence_score, ip.data_sources,
               s.subnet, s.mask
        FROM ip_addresses ip
        JOIN subnets s ON s.id = ip.subnet_id
        WHERE ip.state IN ('active', 'reserved', 'dhcp')
          AND (
                ip.confidence_score < 60
                OR COALESCE(ip.hostname, '') = ''
                OR COALESCE(ip.mac_addr, '') = ''
              )
        ORDER BY ip.confidence_score ASC, ip.last_seen DESC
        LIMIT 10
    ")->fetchAll();
} catch (Exception $e) {
    $subnet_count = 0; $ip_count = 0; $vlan_count = 0;
    $active_count = 0; $offline_count = 0; $avg_confidence = 0; $low_confidence_count = 0;
    $asset_count = 0; $asset_online = 0; $asset_offline = 0; $asset_categories = [];
    $recent_subnets = []; $needs_attention = [];
}
?>

<!-- Asset Monitoring Overview -->
<div style="margin-bottom: 2rem;">
    <h2 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.75rem;">
        <i data-lucide="server" style="color: var(--primary);"></i> Server Asset Health
    </h2>
    <div class="grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem;">
        <div class="card" style="display: flex; align-items: center; gap: 1.5rem; border-left: 4px solid var(--primary); background: linear-gradient(to right, rgba(99,102,241,0.05), transparent);">
            <div style="background: rgba(99, 102, 241, 0.1); padding: 1rem; border-radius: 12px; color: var(--primary);">
                <i data-lucide="database" style="width: 32px; height: 32px;"></i>
            </div>
            <div>
                <p style="color: var(--text-muted); font-size: 0.875rem;">Total Assets</p>
                <h3 style="font-size: 1.875rem; font-weight: 700;"><?php echo $asset_count; ?></h3>
            </div>
        </div>

        <div class="card" style="display: flex; align-items: center; gap: 1.5rem; border-left: 4px solid #10b981;">
            <div style="background: rgba(16, 185, 129, 0.1); padding: 1rem; border-radius: 12px; color: #10b981;">
                <i data-lucide="check-circle" style="width: 32px; height: 32px;"></i>
            </div>
            <div>
                <p style="color: var(--text-muted); font-size: 0.875rem;">Servers Online</p>
                <h3 style="font-size: 1.875rem; font-weight: 700;"><?php echo $asset_online; ?></h3>
            </div>
        </div>

        <div class="card" style="display: flex; align-items: center; gap: 1.5rem; border-left: 4px solid #f87171;">
            <div style="background: rgba(248, 113, 113, 0.1); padding: 1rem; border-radius: 12px; color: #f87171;">
                <i data-lucide="alert-triangle" style="width: 32px; height: 32px;"></i>
            </div>
            <div>
                <p style="color: var(--text-muted); font-size: 0.875rem;">Servers Offline</p>
                <h3 style="font-size: 1.875rem; font-weight: 700;"><?php echo $asset_offline; ?></h3>
            </div>
        </div>
    </div>
</div>

<hr style="border: 0; border-top: 1px solid var(--border); margin-bottom: 2rem; opacity: 0.5;">

<h2 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.75rem;">
    <i data-lucide="network" style="color: var(--success);"></i> Network & IPAM Overview
</h2>

<div class="grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
    <!-- Stat Cards -->
    <div class="card" style="display: flex; align-items: center; gap: 1.5rem; border-left: 4px solid var(--primary);">
        <div style="background: rgba(59, 130, 246, 0.1); padding: 1rem; border-radius: 12px; color: var(--primary);">
            <i data-lucide="layers" style="width: 32px; height: 32px;"></i>
        </div>
        <div>
            <p style="color: var(--text-muted); font-size: 0.875rem;">Total Subnets</p>
            <h3 style="font-size: 1.875rem; font-weight: 700;"><?php echo $subnet_count; ?></h3>
        </div>
    </div>

    <div class="card" style="display: flex; align-items: center; gap: 1.5rem; border-left: 4px solid var(--success);">
        <div style="background: rgba(16, 185, 129, 0.1); padding: 1rem; border-radius: 12px; color: var(--success);">
            <i data-lucide="network" style="width: 32px; height: 32px;"></i>
        </div>
        <div>
            <p style="color: var(--text-muted); font-size: 0.875rem;">Allocated IPs</p>
            <h3 style="font-size: 1.875rem; font-weight: 700;"><?php echo $ip_count; ?></h3>
        </div>
    </div>

    <div class="card" style="display: flex; align-items: center; gap: 1.5rem; border-left: 4px solid var(--warning);">
        <div style="background: rgba(245, 158, 11, 0.1); padding: 1rem; border-radius: 12px; color: var(--warning);">
            <i data-lucide="vibrate" style="width: 32px; height: 32px;"></i>
        </div>
        <div>
            <p style="color: var(--text-muted); font-size: 0.875rem;">Total VLANs</p>
            <h3 style="font-size: 1.875rem; font-weight: 700;"><?php echo $vlan_count; ?></h3>
        </div>
    </div>
</div>

<div class="grid-2-1" style="margin-bottom: 2rem;">
    <!-- Usage Trend Chart (Wider) -->
    <div class="card" style="height: 350px; display: flex; flex-direction: column;">
        <h3 style="font-size: 0.875rem; margin-bottom: 1rem; color: var(--text-muted); text-transform: uppercase;">7-Day Usage Trend</h3>
        <div style="flex-grow: 1; position: relative;"><canvas id="trendChart"></canvas></div>
    </div>
    
    <!-- Network Health (Smaller) -->
    <div class="card" style="height: 350px; display: flex; flex-direction: column;">
        <h3 style="font-size: 0.875rem; margin-bottom: 1rem; color: var(--text-muted); text-transform: uppercase;">Network Health</h3>
        <div style="flex-grow: 1; position: relative;"><canvas id="healthChart"></canvas></div>
    </div>
</div>

<div class="grid-2-1" style="margin-bottom: 2rem;">
    <!-- Asset Category Distribution (New) -->
    <div class="card" style="height: 350px; display: flex; flex-direction: column;">
        <h3 style="font-size: 0.875rem; margin-bottom: 1rem; color: var(--text-muted); text-transform: uppercase;">Asset Category Distribution</h3>
        <div style="flex-grow: 1; position: relative;"><canvas id="assetCategoryChart"></canvas></div>
    </div>

    <div class="card" style="height: 350px; display: flex; flex-direction: column;">
        <h3 style="font-size: 0.875rem; margin-bottom: 1rem; color: var(--text-muted); text-transform: uppercase;">Densest Subnets (%)</h3>
        <div style="flex-grow: 1; position: relative;"><canvas id="densityChart"></canvas></div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const chartDefaults = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { 
            legend: { labels: { color: '#94a3b8', font: { size: 10 } } } 
        },
        scales: {
            x: { grid: { display: false }, ticks: { color: '#94a3b8', font: { size: 10 } } },
            y: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#94a3b8', font: { size: 10 } } }
        }
    };

    // Trend Chart
    new Chart(document.getElementById('trendChart'), {
        type: 'line',
        data: {
            labels: [<?php echo implode(',', array_map(function($t) { return "'".date('d M', strtotime($t['snapshot_date']))."'"; }, $usage_trends)); ?>],
            datasets: [{
                label: 'Active Hosts',
                data: [<?php echo implode(',', array_map(function($t) { return $t['total_active']; }, $usage_trends)); ?>],
                borderColor: '#3b82f6',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            ...chartDefaults,
            scales: {
                ...chartDefaults.scales,
                y: { ...chartDefaults.scales.y, beginAtZero: true }
            }
        }
    });

    // Health Chart
    new Chart(document.getElementById('healthChart'), {
        type: 'bar',
        data: {
            labels: ['Active', 'Offline', 'Reserved', 'DHCP'],
            datasets: [{
                data: [<?php echo $health_stats['active']??0;?>, <?php echo $health_stats['offline']??0;?>, <?php echo $health_stats['reserved']??0;?>, <?php echo $health_stats['dhcp']??0;?>],
                backgroundColor: ['#10b981', '#94a3b8', '#f59e0b', '#3b82f6'],
                borderRadius: 4
            }]
        },
        options: { 
            ...chartDefaults, 
            plugins: { ...chartDefaults.plugins, legend: { display: false } },
            scales: {
                ...chartDefaults.scales,
                y: { ...chartDefaults.scales.y, beginAtZero: true }
            }
        }
    });

    // Density Chart
    new Chart(document.getElementById('densityChart'), {
        type: 'bar',
        data: {
            labels: [<?php echo implode(',', array_map(function($s) { return "'".$s['subnet']."/".$s['mask']."'"; }, $dense_subnets)); ?>],
            datasets: [{
                label: 'Usage %',
                data: [<?php echo implode(',', array_map(function($s) { return round($s['usage_percent'], 1); }, $dense_subnets)); ?>],
                backgroundColor: '#f59e0b',
                borderRadius: 4
            }]
        },
        options: { 
            ...chartDefaults, 
            indexAxis: 'y', 
            plugins: { ...chartDefaults.plugins, legend: { display: false } },
            scales: {
                x: { 
                    grid: { color: 'rgba(255,255,255,0.05)' }, 
                    ticks: { color: '#94a3b8' }, 
                    beginAtZero: true,
                    max: 100
                },
                y: { 
                    grid: { display: false }, 
                    ticks: { color: '#94a3b8' }
                }
            }
        }
    });
    // Asset Category Chart
    new Chart(document.getElementById('assetCategoryChart'), {
        type: 'doughnut',
        data: {
            labels: [<?php echo implode(',', array_map(function($c) { return "'".$c."'"; }, array_keys($asset_categories))); ?>],
            datasets: [{
                data: [<?php echo implode(',', array_values($asset_categories)); ?>],
                backgroundColor: ['#6366f1', '#8b5cf6', '#a855f7', '#d946ef', '#ec4899', '#f43f5e'],
                borderColor: 'rgba(30, 41, 59, 0.5)',
                borderWidth: 2
            }]
        },
        options: {
            ...chartDefaults,
            cutout: '70%',
            plugins: {
                ...chartDefaults.plugins,
                legend: {
                    ...chartDefaults.plugins.legend,
                    position: 'bottom'
                }
            }
        }
    });
});
</script>

<div class="grid-2-1">
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h3 style="font-size: 1.125rem;">Recent Subnets</h3>
            <a href="subnets" class="text-primary" style="font-size: 0.875rem;">View All</a>
        </div>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; text-align: left;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--border);">
                        <th style="padding: 0.75rem; color: var(--text-muted); font-weight: 500;">Subnet</th>
                        <th style="padding: 0.75rem; color: var(--text-muted); font-weight: 500;">Description</th>
                        <th style="padding: 0.75rem; color: var(--text-muted); font-weight: 500;">Usage</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recent_subnets)): ?>
                        <tr>
                            <td colspan="3" style="padding: 2rem; text-align: center; color: var(--text-muted);">No subnets found. Add one to get started!</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recent_subnets as $subnet): ?>
                            <tr style="border-bottom: 1px solid var(--border);">
                                <td style="padding: 0.75rem; font-family: monospace;">
                                    <a href="subnet-details?id=<?php echo (int)$subnet['id']; ?>" class="text-primary" style="text-decoration: none;">
                                        <?php echo htmlspecialchars($subnet['subnet']); ?>/<?php echo (int)$subnet['mask']; ?>
                                    </a>
                                </td>
                                <td style="padding: 0.75rem; color: var(--text-muted);">
                                    <?php echo htmlspecialchars($subnet['description'] ?: 'No description'); ?>
                                </td>
                                <td style="padding: 0.75rem; vertical-align: middle;">
                                    <?php 
                                        $capacity = pow(2, (32 - (int)$subnet['mask']));
                                        if ((int)$subnet['mask'] < 31) $capacity -= 2;
                                        $percent = round(($subnet['used_ips'] / max(1, $capacity)) * 100, 1);
                                        $bar_color = 'var(--success)';
                                        if ($percent >= 90) $bar_color = 'var(--danger)';
                                        elseif ($percent >= 70) $bar_color = 'var(--warning)';
                                    ?>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <div style="flex-grow: 1; height: 8px; background: rgba(0,0,0,0.05); border-radius: 4px; overflow: hidden;">
                                            <div style="width: <?php echo min(100, $percent); ?>%; height: 100%; background: <?php echo $bar_color; ?>; border-radius: 4px;"></div>
                                        </div>
                                        <span style="font-size: 0.75rem; font-weight: 600; min-width: 35px;"><?php echo $percent; ?>%</span>
                                    </div>
                                    <p style="font-size: 0.65rem; color: var(--text-muted); margin-top: 4px;">
                                        <?php echo (int)$subnet['used_ips']; ?> / <?php echo $capacity; ?> IPs
                                    </p>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <h3 style="font-size: 1.125rem; margin-bottom: 1.5rem;">Recent System Activity</h3>
        <div style="display: flex; flex-direction: column; gap: 0.8rem;">
            <?php if (empty($recent_logs)): ?>
                <p style="text-align: center; color: var(--text-muted); font-size: 0.875rem; padding: 1rem;">No recent activity.</p>
            <?php else: ?>
                <?php foreach ($recent_logs as $log): ?>
                <div style="border-left: 3px solid var(--primary); padding-left: 12px; margin-bottom: 4px;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                        <span style="font-size: 0.75rem; font-weight: 600; color: var(--text);"><?php echo str_replace('_', ' ', strtoupper($log['action'])); ?></span>
                        <span style="font-size: 0.65rem; color: var(--text-muted); font-family: monospace;"><?php echo date('H:i', strtotime($log['created_at'])); ?></span>
                    </div>
                    <p style="font-size: 0.75rem; color: var(--text-muted); margin-top: 2px; line-height: 1.3;"><?php echo htmlspecialchars(substr($log['details'], 0, 80)) . (strlen($log['details']) > 80 ? '...' : ''); ?></p>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
            <a href="logs" class="btn btn-secondary" style="margin-top: 0.5rem; font-size: 0.75rem; background: var(--surface-light);">
                <i data-lucide="scroll" style="width: 14px;"></i> View Full Audit Log
            </a>
        </div>
    </div>
</div>

<div class="grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; margin-top: 1.5rem;">
    <div class="card">
        <h3 style="font-size: 1.125rem; margin-bottom: 1.5rem;">System Quick Links</h3>
        <div style="display: flex; flex-wrap: wrap; gap: 0.8rem;">
            <a href="add-subnet" class="btn btn-primary" style="font-size: 0.875rem;">
                <i data-lucide="plus-circle" style="width: 16px;"></i> New Subnet
            </a>
            <a href="vlans" class="btn" style="background: var(--surface-light); font-size: 0.875rem;">
                <i data-lucide="vibrate" style="width: 16px;"></i> VLANs
            </a>
            <a href="settings" class="btn" style="background: var(--surface-light); font-size: 0.875rem;">
                <i data-lucide="settings" style="width: 16px;"></i> Settings
            </a>
        </div>
    </div>

<div class="card" style="margin-top: 1.5rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
        <h3 style="font-size: 1.125rem;">Needs Attention</h3>
        <a href="devices" class="text-primary" style="font-size: 0.875rem;">Open Devices</a>
    </div>
    <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse; text-align: left;">
            <thead>
                <tr style="border-bottom: 1px solid var(--border);">
                    <th style="padding: 0.75rem; color: var(--text-muted); font-weight: 500;">IP</th>
                    <th style="padding: 0.75rem; color: var(--text-muted); font-weight: 500;">Subnet</th>
                    <th style="padding: 0.75rem; color: var(--text-muted); font-weight: 500;">Hostname</th>
                    <th style="padding: 0.75rem; color: var(--text-muted); font-weight: 500;">MAC</th>
                    <th style="padding: 0.75rem; color: var(--text-muted); font-weight: 500;">Confidence</th>
                    <th style="padding: 0.75rem; color: var(--text-muted); font-weight: 500;">Last Seen</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($needs_attention)): ?>
                    <tr>
                        <td colspan="6" style="padding: 1.5rem; text-align: center; color: var(--text-muted);">No flagged devices. Discovery quality looks good.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($needs_attention as $item): ?>
                        <tr style="border-bottom: 1px solid var(--border);">
                            <td style="padding: 0.75rem; font-family: monospace;"><?php echo htmlspecialchars($item['ip_addr']); ?></td>
                            <td style="padding: 0.75rem; color: var(--text-muted);"><?php echo htmlspecialchars($item['subnet']); ?>/<?php echo (int)$item['mask']; ?></td>
                            <td style="padding: 0.75rem;"><?php echo htmlspecialchars($item['hostname'] ?: '-'); ?></td>
                            <td style="padding: 0.75rem; font-family: monospace;"><?php echo htmlspecialchars($item['mac_addr'] ?: '-'); ?></td>
                            <td style="padding: 0.75rem;">
                                <span style="font-weight: 700; color: <?php echo ((int)$item['confidence_score'] < 60) ? 'var(--warning)' : 'var(--success)'; ?>;">
                                    <?php echo (int)$item['confidence_score']; ?>%
                                </span>
                            </td>
                            <td style="padding: 0.75rem; color: var(--text-muted);"><?php echo htmlspecialchars($item['last_seen'] ?: 'Never'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
