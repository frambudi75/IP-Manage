<?php
require_once 'includes/config.php';
require_once 'includes/db.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$db = get_db_connection();
$page_title = 'VLANs';

// Handle Add VLAN
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_vlan'])) {
    $number = $_POST['number'] ?? '';
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';

    try {
        $stmt = $db->prepare("INSERT INTO vlans (number, name, description) VALUES (?, ?, ?)");
        $stmt->execute([$number, $name, $description]);
        $message = 'VLAN added successfully!';
    } catch (Exception $e) {
        $message = 'Error: ' . $e->getMessage();
    }
}

// Fetch VLANs
$vlans = $db->query("SELECT * FROM vlans ORDER BY number ASC")->fetchAll();

include 'includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
    <h1 style="font-size: 1.5rem;">VLAN Management</h1>
    <button class="btn btn-primary" onclick="document.getElementById('vlanModal').style.display='flex'">
        <i data-lucide="plus"></i> Add VLAN
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
                    <th style="padding: 1rem; color: var(--text-muted); width: 100px;">Number</th>
                    <th style="padding: 1rem; color: var(--text-muted);">Name</th>
                    <th style="padding: 1rem; color: var(--text-muted);">Description</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($vlans)): ?>
                    <tr>
                        <td colspan="3" style="padding: 2rem; text-align: center; color: var(--text-muted);">No VLANs configured yet.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($vlans as $v): ?>
                        <tr style="border-bottom: 1px solid var(--border);">
                            <td style="padding: 1rem; font-weight: 600; color: var(--primary);">#<?php echo $v['number']; ?></td>
                            <td style="padding: 1rem; font-weight: 500;"><?php echo $v['name']; ?></td>
                            <td style="padding: 1rem; color: var(--text-muted);"><?php echo $v['description']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="vlanModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); align-items: center; justify-content: center; z-index: 1000;">
    <div class="card" style="width: 100%; max-width: 500px; padding: 2.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h3>Add New VLAN</h3>
            <button onclick="document.getElementById('vlanModal').style.display='none'" style="background: none; border: none; color: var(--text-muted); cursor: pointer;">
                <i data-lucide="x"></i>
            </button>
        </div>
        <form method="POST">
            <input type="hidden" name="add_vlan" value="1">
            <div class="input-group">
                <label>VLAN Number (1-4094)</label>
                <input type="number" name="number" class="input-control" min="1" max="4094" required>
            </div>
            <div class="input-group">
                <label>VLAN Name</label>
                <input type="text" name="name" class="input-control" required>
            </div>
            <div class="input-group">
                <label>Description</label>
                <input type="text" name="description" class="input-control">
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center;">Save VLAN</button>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
