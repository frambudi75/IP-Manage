<?php
require_once 'includes/config.php';
require_once 'includes/db.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login');
    exit;
}

$db = get_db_connection();
$page_title = 'Add New Subnet';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subnet = $_POST['subnet'] ?? '';
    $mask = $_POST['mask'] ?? '';
    $description = $_POST['description'] ?? '';
    $vlan_id = (!empty($_POST['vlan_id'])) ? $_POST['vlan_id'] : null;
    $scan_interval = (int)($_POST['scan_interval'] ?? 0);
    $section_id = 1; // Default section

    if ($subnet && $mask) {
        try {
            $stmt = $db->prepare("INSERT INTO subnets (subnet, mask, description, vlan_id, section_id, scan_interval) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$subnet, $mask, $description, $vlan_id, $section_id, $scan_interval]);
            header('Location: subnets?msg=added');
            exit;
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    } else {
        $error = 'Please provide both subnet and mask.';
    }
}

// Fetch VLANs for dropdown
$vlans = $db->query("SELECT id, number, name FROM vlans ORDER BY number ASC")->fetchAll();

include 'includes/header.php';
?>

<div style="max-width: 600px; margin: 0 auto;">
    <div style="margin-bottom: 2rem;">
        <a href="index" class="text-muted" style="font-size: 0.875rem; display: flex; align-items: center; gap: 5px; margin-bottom: 1rem;">
            <i data-lucide="arrow-left" style="width: 14px;"></i> Back to Dashboard
        </a>
        <h1 style="font-size: 1.75rem;">Add New Subnet</h1>
        <p style="color: var(--text-muted);">Create a new network prefix to manage.</p>
    </div>

    <?php if ($error): ?>
        <div style="padding: 1rem; background: rgba(239, 68, 68, 0.1); border: 1px solid var(--danger); color: var(--danger); border-radius: 8px; margin-bottom: 1.5rem;">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <div class="card" style="padding: 2.5rem;">
        <form method="POST">
            <div class="input-group">
                <label>Subnet Address (e.g., 192.168.1.0)</label>
                <input type="text" name="subnet" class="input-control" placeholder="10.0.0.0" required>
            </div>
            
            <div class="input-group">
                <label>Subnet Mask (CIDR, e.g., 24)</label>
                <input type="number" name="mask" class="input-control" min="0" max="32" placeholder="24" required>
            </div>

            <div class="input-group">
                <label>VLAN (Optional)</label>
                <select name="vlan_id" class="input-control" style="appearance: none;">
                    <option value="">No VLAN</option>
                    <?php foreach ($vlans as $v): ?>
                        <option value="<?php echo $v['id']; ?>">VLAN <?php echo $v['number']; ?> - <?php echo $v['name']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="input-group">
                <label>Description</label>
                <input type="text" name="description" class="input-control" placeholder="Corporate LAN">
            </div>

            <div class="input-group">
                <label>Auto-Scan Interval</label>
                <select name="scan_interval" class="input-control" style="appearance: none;">
                    <option value="0">Manual Only</option>
                    <option value="30">Every 30 Minutes</option>
                    <option value="60">Every 1 Hour</option>
                    <option value="360">Every 6 Hours</option>
                    <option value="720">Every 12 Hours</option>
                    <option value="1440">Every 24 Hours</option>
                </select>
            </div>

            <div style="margin-top: 2.5rem; display: flex; gap: 1rem;">
                <a href="subnets" class="btn" style="flex: 1; justify-content: center; background: var(--surface-light);">Cancel</a>
                <button type="submit" class="btn btn-primary" style="flex: 1; justify-content: center;">Create Subnet</button>
            </div>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
