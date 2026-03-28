<?php
/**
 * IPManager Pro - Switch Details
 * Displays full hardware info and port-to-IP mapping for a specific switch.
 */

require_once 'includes/config.php';
require_once 'includes/db.php';

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$db = get_db_connection();
$id = (int)($_GET['id'] ?? 0);

// Fetch switch data
$stmt = $db->prepare("SELECT * FROM switches WHERE id = ?");
$stmt->execute([$id]);
$switch = $stmt->fetch();

if (!$switch) {
    header('Location: switches.php');
    exit;
}

// Fetch port mapping joined with IP addresses
$query = "
    SELECT 
        m.mac_addr, 
        m.port_name, 
        m.updated_at as last_seen_on_port,
        ip.ip_addr,
        ip.hostname,
        ip.vendor
    FROM switch_port_map m
    LEFT JOIN ip_addresses ip ON m.mac_addr = ip.mac_addr
    WHERE m.switch_id = ?
    ORDER BY m.port_name ASC, m.mac_addr ASC
";
$stmt = $db->prepare($query);
$stmt->execute([$id]);
$ports = $stmt->fetchAll();

$page_title = "Switch: " . $switch['name'];
include 'includes/header.php';
?>

<div style="margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: flex-end;">
    <div>
        <nav style="font-size: 0.875rem; color: var(--text-muted); margin-bottom: 0.5rem;">
            <a href="switches.php" style="color: var(--primary); text-decoration: none;">Switches</a> / <?php echo htmlspecialchars($switch['name']); ?>
        </nav>
        <h1 style="font-size: 1.75rem;"><?php echo htmlspecialchars($switch['name']); ?></h1>
        <p style="color: var(--text-muted); font-family: monospace;"><?php echo htmlspecialchars($switch['ip_addr']); ?></p>
    </div>
    <div style="display: flex; gap: 0.75rem;">
        <button class="btn" onclick="location.href='cron_switch_poll.php?id=<?php echo $id; ?>'" style="background: var(--surface-light);">
            <i data-lucide="refresh-cw" style="width: 16px;"></i> Force Poll
        </button>
        <button class="btn btn-primary" onclick="window.print()">
            <i data-lucide="printer" style="width: 16px;"></i> Export Report
        </button>
    </div>
</div>

<div style="display: grid; grid-template-columns: 350px 1fr; gap: 1.5rem; align-items: start;">
    <!-- Hardware Status Sidebar -->
    <div class="card">
        <h3 style="font-size: 1rem; margin-bottom: 1.5rem; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem;">Hardware Health</h3>
        
        <div style="margin-bottom: 1.5rem;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem; font-size: 0.875rem;">
                <span>CPU Load</span>
                <span style="font-weight: 700;"><?php echo (int)($switch['cpu_usage'] ?? 0); ?>%</span>
            </div>
            <div style="height: 10px; background: var(--border); border-radius: 5px; overflow: hidden;">
                <div style="width: <?php echo (int)($switch['cpu_usage'] ?? 0); ?>%; height: 100%; background: <?php echo (int)($switch['cpu_usage'] ?? 0) > 80 ? 'var(--danger)' : 'var(--primary)'; ?>;"></div>
            </div>
        </div>

        <div style="margin-bottom: 2rem;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem; font-size: 0.875rem;">
                <span>Memory Usage</span>
                <span style="font-weight: 700;"><?php echo (int)($switch['memory_usage'] ?? 0); ?>%</span>
            </div>
            <div style="height: 10px; background: var(--border); border-radius: 5px; overflow: hidden;">
                <div style="width: <?php echo (int)($switch['memory_usage'] ?? 0); ?>%; height: 100%; background: <?php echo (int)($switch['memory_usage'] ?? 0) > 80 ? 'var(--warning)' : 'var(--success)'; ?>;"></div>
            </div>
        </div>

        <div style="display: flex; flex-direction: column; gap: 1rem; font-size: 0.875rem;">
            <div style="display: flex; justify-content: space-between;">
                <span style="color:var(--text-muted)">Model:</span>
                <span style="font-weight: 600;"><?php echo htmlspecialchars($switch['model'] ?? 'Unknown'); ?></span>
            </div>
            <div style="display: flex; justify-content: space-between;">
                <span style="color:var(--text-muted)">Uptime:</span>
                <span style="font-weight: 600;"><?php echo htmlspecialchars($switch['uptime'] ?? '-'); ?></span>
            </div>
            <div style="display: flex; justify-content: space-between;">
                <span style="color:var(--text-muted)">Last Updated:</span>
                <span style="font-weight: 600;"><?php echo $switch['last_poll'] ? date('H:i:s, d M', strtotime($switch['last_poll'])) : 'Never'; ?></span>
            </div>
        </div>

        <?php if (!empty($switch['system_info'])): ?>
            <div style="margin-top: 2rem;">
                <h4 style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); letter-spacing: 1px; margin-bottom: 0.5rem;">System Information</h4>
                <div style="font-size: 0.75rem; color: var(--text-muted); background: var(--surface-light); padding: 1rem; border-radius: 8px; line-height: 1.5; white-space: pre-wrap;"><?php echo htmlspecialchars($switch['system_info']); ?></div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Port Mapping Table -->
    <div class="card">
        <h3 style="font-size: 1rem; margin-bottom: 1.5rem; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem;">Device Port Mapping</h3>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--border); text-align: left;">
                        <th style="padding: 1rem; color: var(--text-muted); font-size: 0.8rem;">Interface</th>
                        <th style="padding: 1rem; color: var(--text-muted); font-size: 0.8rem;">MAC Address</th>
                        <th style="padding: 1rem; color: var(--text-muted); font-size: 0.8rem;">Mapped IP</th>
                        <th style="padding: 1rem; color: var(--text-muted); font-size: 0.8rem;">Hostname / Vendor</th>
                        <th style="padding: 1rem; color: var(--text-muted); font-size: 0.8rem; text-align: right;">Seen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($ports)): ?>
                        <tr>
                            <td colspan="5" style="padding: 2rem; text-align: center; color: var(--text-muted);">No devices discovered on this switch yet. Run a poll to start.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($ports as $port): ?>
                            <tr style="border-bottom: 1px solid var(--border);">
                                <td style="padding: 1rem; font-weight: 700; color: var(--primary);">
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <i data-lucide="zap" style="width: 14px;"></i>
                                        <?php echo htmlspecialchars($port['port_name']); ?>
                                    </div>
                                </td>
                                <td style="padding: 1rem; font-family: monospace; font-size: 0.85rem;">
                                    <?php echo $port['mac_addr']; ?>
                                </td>
                                <td style="padding: 1rem;">
                                    <?php if ($port['ip_addr']): ?>
                                        <a href="devices.php?search=<?php echo urlencode($port['ip_addr']); ?>" style="color: var(--text); text-decoration: none; font-weight: 600; border-bottom: 1px dashed var(--primary);">
                                            <?php echo $port['ip_addr']; ?>
                                        </a>
                                    <?php else: ?>
                                        <span style="opacity: 0.3; font-size: 0.75rem;">Not in IPAM</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 1rem;">
                                    <div style="font-size: 0.875rem; font-weight: 600;"><?php echo htmlspecialchars($port['hostname'] ?: '-'); ?></div>
                                    <div style="font-size: 0.75rem; color: var(--text-muted);"><?php echo htmlspecialchars($port['vendor'] ?: ''); ?></div>
                                </td>
                                <td style="padding: 1rem; text-align: right; font-size: 0.75rem; color: var(--text-muted);">
                                    <?php echo date('H:i', strtotime($port['last_seen_on_port'])); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
