<?php
require_once 'includes/config.php';
require_once 'includes/db.php';

session_start();

// Auth check
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
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

    $active_count = $db->query("SELECT COUNT(*) FROM ip_addresses WHERE state = 'active'")->fetchColumn() ?: 0;
    $offline_count = $db->query("SELECT COUNT(*) FROM ip_addresses WHERE state = 'offline'")->fetchColumn() ?: 0;
    $avg_confidence = $db->query("SELECT ROUND(AVG(confidence_score), 1) FROM ip_addresses WHERE state IN ('active', 'reserved', 'dhcp')")->fetchColumn();
    $avg_confidence = $avg_confidence !== null ? $avg_confidence : 0;
    $low_confidence_count = $db->query("SELECT COUNT(*) FROM ip_addresses WHERE confidence_score < 60 AND state IN ('active', 'reserved', 'dhcp')")->fetchColumn() ?: 0;

    $recent_subnets = $db->query("
        SELECT s.id, s.subnet, s.mask, s.description,
               COUNT(ip.id) AS used_ips
        FROM subnets s
        LEFT JOIN ip_addresses ip ON ip.subnet_id = s.id
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
    $recent_subnets = []; $needs_attention = [];
}
?>

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

<div class="grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
    <div class="card" style="border-left: 4px solid var(--success);">
        <p style="color: var(--text-muted); font-size: 0.8rem;">Discovery Health - Active Hosts</p>
        <h4 style="font-size: 1.5rem; margin-top: 0.3rem;"><?php echo (int)$active_count; ?></h4>
    </div>
    <div class="card" style="border-left: 4px solid var(--text-muted);">
        <p style="color: var(--text-muted); font-size: 0.8rem;">Marked Offline (TTL)</p>
        <h4 style="font-size: 1.5rem; margin-top: 0.3rem;"><?php echo (int)$offline_count; ?></h4>
    </div>
    <div class="card" style="border-left: 4px solid var(--primary);">
        <p style="color: var(--text-muted); font-size: 0.8rem;">Average Confidence</p>
        <h4 style="font-size: 1.5rem; margin-top: 0.3rem;"><?php echo htmlspecialchars($avg_confidence); ?>%</h4>
    </div>
    <div class="card" style="border-left: 4px solid var(--warning);">
        <p style="color: var(--text-muted); font-size: 0.8rem;">Low Confidence (&lt;60)</p>
        <h4 style="font-size: 1.5rem; margin-top: 0.3rem;"><?php echo (int)$low_confidence_count; ?></h4>
    </div>
</div>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem;">
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h3 style="font-size: 1.125rem;">Recent Subnets</h3>
            <a href="subnets.php" class="text-primary" style="font-size: 0.875rem;">View All</a>
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
                                    <a href="subnet-details.php?id=<?php echo (int)$subnet['id']; ?>" class="text-primary" style="text-decoration: none;">
                                        <?php echo htmlspecialchars($subnet['subnet']); ?>/<?php echo (int)$subnet['mask']; ?>
                                    </a>
                                </td>
                                <td style="padding: 0.75rem; color: var(--text-muted);">
                                    <?php echo htmlspecialchars($subnet['description'] ?: 'No description'); ?>
                                </td>
                                <td style="padding: 0.75rem;">
                                    <span style="font-size: 0.75rem; color: var(--text-muted);"><?php echo (int)$subnet['used_ips']; ?> used</span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <h3 style="font-size: 1.125rem; margin-bottom: 1.5rem;">Quick Links</h3>
        <div style="display: flex; flex-direction: column; gap: 1rem;">
            <a href="add-subnet.php" class="btn btn-primary" style="justify-content: flex-start;">
                <i data-lucide="plus-circle"></i> Add New Subnet
            </a>
            <a href="vlans.php" class="btn" style="justify-content: flex-start; background: var(--surface-light);">
                <i data-lucide="vibrate"></i> Manage VLANs
            </a>
            <div style="margin-top: 1rem; padding: 1rem; background: rgba(59, 130, 246, 0.05); border-radius: 8px; border: 1px dashed var(--primary);">
                <p style="font-size: 0.75rem; color: var(--text-muted);">
                    <i data-lucide="info" style="width: 14px; height: 14px; vertical-align: middle;"></i> 
                    IPManager Pro automatically calculates address usage and availability for all scanned subnets.
                </p>
            </div>
        </div>
    </div>
</div>

<div class="card" style="margin-top: 1.5rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
        <h3 style="font-size: 1.125rem;">Needs Attention</h3>
        <a href="devices.php" class="text-primary" style="font-size: 0.875rem;">Open Devices</a>
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
