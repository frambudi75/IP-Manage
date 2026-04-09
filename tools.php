<?php
require_once 'includes/config.php';
require_once 'includes/db.php';

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$page_title = 'Network Toolbox';
include 'includes/header.php';

$output = '';
$target = $_POST['target'] ?? '';
$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($target)) {
    $is_windows = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');
    
    // Sanitize target (must be IP or domain)
    if (filter_var($target, FILTER_VALIDATE_IP) || preg_match('/^[a-zA-Z0-9\.\-]+$/', $target)) {
        if ($action === 'ping') {
            $cmd = $is_windows ? "ping -n 4 " . escapeshellarg($target) : "ping -c 4 " . escapeshellarg($target);
            $output = shell_exec($cmd);
        } elseif ($action === 'trace') {
            $cmd = $is_windows ? "tracert " . escapeshellarg($target) : "traceroute " . escapeshellarg($target);
            $output = shell_exec($cmd . " 2>&1");
        } elseif ($action === 'oui') {
            // OUI Lookup via MacVendors (Fast)
            $mac = trim($target);
            $url = "https://api.macvendors.com/" . urlencode($mac);
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            $result = curl_exec($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($status == 200 && !empty($result)) {
                $output = "MAC: $mac\nVendor: $result";
            } else {
                $output = "MAC: $mac\nVendor Information Not Found (HTTP $status).";
            }
        }
    } else {
        $output = "Error: Invalid target format.";
    }
}
?>

<div style="display: flex; gap: 2rem;">
    <!-- Tools Sidebar/Selector -->
    <div style="width: 300px;">
        <div class="card" style="position: sticky; top: 1.5rem;">
            <h3 style="font-size: 1rem; margin-bottom: 1.5rem;">Network Tools</h3>
            <form action="" method="POST">
                <div class="input-group">
                    <label>Target Host (IP / MAC / Domain)</label>
                    <input type="text" name="target" value="<?php echo htmlspecialchars($target); ?>" class="input-control" placeholder="e.g. 192.168.1.1 or 00:11:22..." required>
                </div>
                
                <div style="display: flex; flex-direction: column; gap: 0.8rem; margin-top: 1.5rem;">
                    <?php 
                        $active_action = $_POST['action'] ?? 'ping'; 
                    ?>
                    <button type="submit" name="action" value="ping" class="btn <?php echo $active_action === 'ping' ? 'btn-primary' : ''; ?>" style="justify-content: flex-start; <?php echo $active_action !== 'ping' ? 'background: var(--surface-light);' : ''; ?>">
                        <i data-lucide="radio" style="width: 16px;"></i> Ping Utility
                    </button>
                    <button type="submit" name="action" value="trace" class="btn <?php echo $active_action === 'trace' ? 'btn-primary' : ''; ?>" style="justify-content: flex-start; <?php echo $active_action !== 'trace' ? 'background: var(--surface-light);' : ''; ?>">
                        <i data-lucide="git-merge" style="width: 16px;"></i> Traceroute
                    </button>
                    <button type="submit" name="action" value="oui" class="btn <?php echo $active_action === 'oui' ? 'btn-primary' : ''; ?>" style="justify-content: flex-start; <?php echo $active_action !== 'oui' ? 'background: var(--surface-light);' : ''; ?>">
                        <i data-lucide="search" style="width: 16px;"></i> OUI Lookup (MAC)
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Output Area -->
    <div style="flex: 1;">
        <?php if (!empty($output)): ?>
            <div class="card" style="background: #000; border-color: #333; min-height: 400px; display: flex; flex-direction: column;">
                <div style="display: flex; justify-content: space-between; border-bottom: 1px solid #333; padding-bottom: 0.75rem; margin-bottom: 1rem;">
                    <h3 style="font-size: 0.875rem; color: #aaa; text-transform: uppercase; letter-spacing: 1px;">Terminal Output</h3>
                    <span style="font-size: 0.75rem; color: #555;"><?php echo date('H:i:s'); ?></span>
                </div>
                <pre style="flex: 1; color: #0f0; font-family: 'DM Mono', monospace; font-size: 0.875rem; line-height: 1.6; overflow-x: auto;"><?php echo htmlspecialchars($output); ?></pre>
            </div>
        <?php else: ?>
            <div class="card" style="display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 400px; border-style: dashed; opacity: 0.5;">
                <i data-lucide="terminal" style="width: 48px; height: 48px; color: var(--text-muted); margin-bottom: 1rem;"></i>
                <h3 style="color: var(--text-muted);">Ready to Execute</h3>
                <p style="font-size: 0.875rem; color: var(--text-muted);">Select a tool and provide a target host above.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
