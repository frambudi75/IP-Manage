<?php
require_once 'includes/config.php';
require_once 'includes/db.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$db = get_db_connection();
$page_title = 'Subnets';

// Handle Add Subnet
$message = '';
if (isset($_GET['msg']) && $_GET['msg'] === 'added') {
    $message = 'Subnet added successfully!';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_subnet'])) {
    $subnet = $_POST['subnet'] ?? '';
    $mask = $_POST['mask'] ?? '';
    $description = $_POST['description'] ?? '';
    $section_id = $_POST['section_id'] ?? 1;

    try {
        $stmt = $db->prepare("INSERT INTO subnets (subnet, mask, description, section_id) VALUES (?, ?, ?, ?)");
        $stmt->execute([$subnet, $mask, $description, $section_id]);
        $message = 'Subnet added successfully!';
    } catch (Exception $e) {
        $message = 'Error: ' . $e->getMessage();
    }
}

// Handle Delete Subnet
if (isset($_GET['delete']) && is_admin()) {
    $sid = (int)$_GET['delete'];
    try {
        $db->beginTransaction();
        // Delete associated IPs first
        $stmt = $db->prepare("DELETE FROM ip_addresses WHERE subnet_id = ?");
        $stmt->execute([$sid]);
        // Delete subnet
        $stmt = $db->prepare("DELETE FROM subnets WHERE id = ?");
        $stmt->execute([$sid]);
        $db->commit();
        header('Location: subnets.php?msg=deleted');
        exit;
    } catch (Exception $e) {
        $db->rollBack();
        $message = 'Error deleting subnet: ' . $e->getMessage();
    }
}

if (isset($_GET['msg']) && $_GET['msg'] === 'deleted') {
    $message = 'Subnet deleted successfully!';
}

// Fetch subnets
$subnets = $db->query("SELECT s.*, v.number as vlan_number FROM subnets s LEFT JOIN vlans v ON s.vlan_id = v.id ORDER BY s.subnet ASC")->fetchAll();

include 'includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
    <h1 style="font-size: 1.5rem;">Subnet Management</h1>
    <button class="btn btn-primary" onclick="document.getElementById('addModal').style.display='flex'">
        <i data-lucide="plus"></i> Add Subnet
    </button>
</div>

<?php if ($message): ?>
    <div style="padding: 1rem; background: rgba(16, 185, 129, 0.1); border: 1px solid var(--success); color: var(--success); border-radius: 8px; margin-bottom: 1.5rem;">
        <?php echo $message; ?>
    </div>
<?php endif; ?>

<div class="card">
    <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse; text-align: left;">
            <thead>
                <tr style="border-bottom: 1px solid var(--border);">
                    <th style="padding: 1rem; color: var(--text-muted);">Subnet</th>
                    <th style="padding: 1rem; color: var(--text-muted);">Description</th>
                    <th style="padding: 1rem; color: var(--text-muted);">VLAN</th>
                    <th style="padding: 1rem; color: var(--text-muted);">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($subnets)): ?>
                    <tr>
                        <td colspan="4" style="padding: 2rem; text-align: center; color: var(--text-muted);">No subnets configured yet.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($subnets as $s): ?>
                        <tr style="border-bottom: 1px solid var(--border);">
                            <td style="padding: 1rem; font-weight: 500;"><?php echo $s['subnet']; ?>/<?php echo $s['mask']; ?></td>
                            <td style="padding: 1rem; color: var(--text-muted);"><?php echo $s['description']; ?></td>
                            <td style="padding: 1rem;">
                                <?php echo $s['vlan_number'] ? '<span class="text-primary">VLAN '.$s['vlan_number'].'</span>' : '-'; ?>
                            </td>
                            <td style="padding: 1rem; display: flex; gap: 0.5rem; align-items: center;">
                                <a href="subnet-details.php?id=<?php echo $s['id']; ?>" class="btn" style="padding: 6px; background: rgba(59, 130, 246, 0.1); color: var(--primary);" title="View Details">
                                    <i data-lucide="external-link" style="width: 16px;"></i>
                                </a>
                                <?php if (is_admin()): ?>
                                <a href="?delete=<?php echo $s['id']; ?>" class="btn" style="padding: 6px; background: rgba(239, 68, 68, 0.1); color: var(--danger);" onclick="return confirm('Are you sure you want to delete this subnet and ALL its IP records?')" title="Delete">
                                    <i data-lucide="trash-2" style="width: 16px;"></i>
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Simple Add Modal (Hidden by default) -->
<div id="addModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); align-items: center; justify-content: center; z-index: 1000;">
    <div class="card" style="width: 100%; max-width: 500px; padding: 2.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h3>Add New Subnet</h3>
            <button onclick="document.getElementById('addModal').style.display='none'" style="background: none; border: none; color: var(--text-muted); cursor: pointer;">
                <i data-lucide="x"></i>
            </button>
        </div>
        <form method="POST">
            <input type="hidden" name="add_subnet" value="1">
            <div class="input-group">
                <label>Subnet Address (e.g. 192.168.1.0)</label>
                <input type="text" name="subnet" class="input-control" required>
            </div>
            <div class="input-group">
                <label>Mask (CIDR, e.g. 24)</label>
                <input type="number" name="mask" class="input-control" min="0" max="32" required>
            </div>
            <div class="input-group">
                <label>Description</label>
                <input type="text" name="description" class="input-control">
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center;">Save Subnet</button>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
