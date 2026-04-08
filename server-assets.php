<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/audit.helper.php';

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login');
    exit;
}

$db = get_db_connection();
$message = $_GET['msg'] ?? '';
$error = '';

// Handle Server Asset Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action === 'add' || $action === 'edit') {
            $hostname = $_POST['hostname'];
            $ip_address = $_POST['ip_address'];
            $username = $_POST['username'];
            $password = $_POST['password'];
            $port = (int)$_POST['port'] ?: 22;
            $installed_apps = $_POST['installed_apps'];
            $missing_apps = $_POST['missing_apps'];
            $notes = $_POST['notes'];
            
            if ($action === 'add') {
                $stmt = $db->prepare("INSERT INTO server_assets (hostname, ip_address, username, password, port, installed_apps, missing_apps, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$hostname, $ip_address, $username, $password, $port, $installed_apps, $missing_apps, $notes]);
                AuditLogHelper::log("add_server_asset", "server_asset", $db->lastInsertId(), "Added server asset $hostname ($ip_address)");
                $message = "Server asset added successfully!";
            } else {
                $id = (int)$_POST['id'];
                $stmt = $db->prepare("UPDATE server_assets SET hostname = ?, ip_address = ?, username = ?, password = ?, port = ?, installed_apps = ?, missing_apps = ?, notes = ? WHERE id = ?");
                $stmt->execute([$hostname, $ip_address, $username, $password, $port, $installed_apps, $missing_apps, $notes, $id]);
                AuditLogHelper::log("edit_server_asset", "server_asset", $id, "Updated server asset $hostname ($ip_address)");
                $message = "Server asset updated successfully!";
            }
        } elseif ($action === 'delete') {
            $id = (int)$_POST['id'];
            $db->prepare("DELETE FROM server_assets WHERE id = ?")->execute([$id]);
            AuditLogHelper::log("delete_server_asset", "server_asset", $id, "Deleted server asset ID $id");
            $message = "Server asset removed.";
        } elseif ($action === 'import_csv') {
            if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0) {
                $file = $_FILES['csv_file']['tmp_name'];
                $handle = fopen($file, "r");
                $headers = fgetcsv($handle, 1000, ","); // Skip header
                $imported = 0;
                $updated = 0;

                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    if (count($data) < 9) continue;
                    
                    // Format: 0:id, 1:hostname, 2:ip, 3:username, 4:password, 5:port, 6:installed, 7:missing, 8:notes
                    $hostname = $data[1];
                    $ip = $data[2];
                    $username = $data[3];
                    $password = $data[4];
                    $port = (int)$data[5];
                    $installed = $data[6];
                    $missing = $data[7];
                    $notes = $data[8];

                    // Check if exists by hostname + IP
                    $check = $db->prepare("SELECT id FROM server_assets WHERE hostname = ? AND ip_address = ?");
                    $check->execute([$hostname, $ip]);
                    $existing_id = $check->fetchColumn();

                    if ($existing_id) {
                        $stmt = $db->prepare("UPDATE server_assets SET username = ?, password = ?, port = ?, installed_apps = ?, missing_apps = ?, notes = ? WHERE id = ?");
                        $stmt->execute([$username, $password, $port, $installed, $missing, $notes, $existing_id]);
                        $updated++;
                    } else {
                        $stmt = $db->prepare("INSERT INTO server_assets (hostname, ip_address, username, password, port, installed_apps, missing_apps, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$hostname, $ip, $username, $password, $port, $installed, $missing, $notes]);
                        $imported++;
                    }
                }
                fclose($handle);
                $message = "Import completed: $imported added, $updated updated.";
                AuditLogHelper::log("import_server_assets", "server_asset", 0, "Imported assets from CSV: $imported new, $updated updated");
            } else {
                $error = "Failed to upload CSV file.";
            }
        }
    }
}

$assets = $db->query("SELECT * FROM server_assets ORDER BY hostname ASC")->fetchAll();
$last_backup = Settings::get('last_server_backup', 0);

$page_title = "Server Assets Management";
include 'includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
    <div>
        <h1 style="font-size: 1.5rem;">Server Assets & Access</h1>
        <p style="color: var(--text-muted); font-size: 0.875rem;">Manage server login credentials and software inventory</p>
    </div>
    <div style="display: flex; gap: 0.75rem;">
        <button class="btn btn-secondary" onclick="document.getElementById('importModal').style.display='flex'">
            <i data-lucide="upload" style="width: 14px;"></i> Import CSV
        </button>
        <button class="btn btn-secondary" onclick="location.href='cron_backup?force=1'" title="Last backup: <?php echo $last_backup ? date('d M Y H:i', $last_backup) : 'Never'; ?>">
            <i data-lucide="mail" style="width: 14px;"></i> Backup Now
        </button>
        <button class="btn btn-primary" onclick="openAddModal()">
            <i data-lucide="plus"></i> Add Server
        </button>
    </div>
</div>

<?php if ($message): ?>
    <div class="card" style="background: rgba(16, 185, 129, 0.1); color: var(--success); margin-bottom: 1.5rem; padding: 1rem; display: flex; align-items: center; gap: 10px;">
        <i data-lucide="check-circle" style="width: 18px;"></i>
        <?php echo $message; ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="card" style="background: rgba(239, 68, 68, 0.1); color: var(--danger); margin-bottom: 1.5rem; padding: 1rem; display: flex; align-items: center; gap: 10px;">
        <i data-lucide="alert-circle" style="width: 18px;"></i>
        <?php echo $error; ?>
    </div>
<?php endif; ?>

<div class="grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(400px, 1fr)); gap: 1.5rem;">
    <?php if (empty($assets)): ?>
        <div class="card" style="grid-column: 1/-1; text-align: center; padding: 4rem; opacity: 0.5;">
            <i data-lucide="database" style="width: 48px; height: 48px; margin-bottom: 1rem;"></i>
            <h3>No server assets recorded.</h3>
            <p>Add your servers to keep track of access credentials and installed software.</p>
        </div>
    <?php endif; ?>
    
    <?php foreach ($assets as $asset): ?>
    <div class="card" style="position: relative; display: flex; flex-direction: column;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
            <div>
                <h3 style="font-size: 1.125rem;"><?php echo htmlspecialchars($asset['hostname']); ?></h3>
                <code style="color: var(--primary); font-weight: 700;"><?php echo htmlspecialchars($asset['ip_address']); ?>:<?php echo $asset['port']; ?></code>
            </div>
            <div style="display: flex; gap: 0.5rem;">
                <button class="btn" style="background: var(--surface-light); padding: 5px 8px;" onclick='openEditModal(<?php echo json_encode($asset); ?>)'>
                    <i data-lucide="edit-3" style="width: 14px;"></i>
                </button>
                <form action="" method="POST" onsubmit="return confirm('Remove this server asset?');" style="display: inline;">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?php echo $asset['id']; ?>">
                    <button type="submit" class="btn" style="background: var(--surface-light); color: var(--danger); padding: 5px 8px;">
                        <i data-lucide="trash-2" style="width: 14px;"></i>
                    </button>
                </form>
            </div>
        </div>

        <div style="background: rgba(0,0,0,0.03); border-radius: 8px; padding: 1rem; margin-bottom: 1rem;">
            <p style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); margin-bottom: 0.5rem; letter-spacing: 0.5px;">Login Credentials</p>
            <div style="display: flex; flex-direction: column; gap: 8px;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <i data-lucide="user" style="width: 14px; color: var(--text-muted);"></i>
                    <span style="font-size: 0.875rem; font-family: monospace;"><?php echo htmlspecialchars($asset['username'] ?: '-'); ?></span>
                </div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <i data-lucide="key" style="width: 14px; color: var(--text-muted);"></i>
                    <span style="font-size: 0.875rem; font-family: monospace; display: flex; align-items: center; gap: 8px;">
                        <span class="pw-hidden" id="pw-<?php echo $asset['id']; ?>">••••••••</span>
                        <span class="pw-visible" id="pw-real-<?php echo $asset['id']; ?>" style="display: none;"><?php echo htmlspecialchars($asset['password'] ?: '-'); ?></span>
                        <button type="button" style="background: none; border: none; cursor: pointer; color: var(--primary); padding: 0;" onclick="togglePassword(<?php echo $asset['id']; ?>)">
                            <i data-lucide="eye" id="eye-icon-<?php echo $asset['id']; ?>" style="width: 14px;"></i>
                        </button>
                    </span>
                </div>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
            <div>
                <p style="font-size: 0.75rem; text-transform: uppercase; color: var(--success); margin-bottom: 0.5rem; letter-spacing: 0.5px; display: flex; align-items: center; gap: 4px;">
                    <i data-lucide="check-circle-2" style="width: 12px;"></i> Installed
                </p>
                <div style="font-size: 0.8rem; color: var(--text); background: rgba(16, 185, 129, 0.05); padding: 0.5rem; border-radius: 6px; min-height: 50px; white-space: pre-wrap;"><?php echo htmlspecialchars($asset['installed_apps'] ?: '-'); ?></div>
            </div>
            <div>
                <p style="font-size: 0.75rem; text-transform: uppercase; color: var(--warning); margin-bottom: 0.5rem; letter-spacing: 0.5px; display: flex; align-items: center; gap: 4px;">
                    <i data-lucide="circle-dashed" style="width: 12px;"></i> Missing/Pending
                </p>
                <div style="font-size: 0.8rem; color: var(--text); background: rgba(245, 158, 11, 0.05); padding: 0.5rem; border-radius: 6px; min-height: 50px; white-space: pre-wrap;"><?php echo htmlspecialchars($asset['missing_apps'] ?: '-'); ?></div>
            </div>
        </div>

        <?php if (!empty($asset['notes'])): ?>
        <div style="margin-top: auto;">
            <p style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); margin-bottom: 0.5rem; letter-spacing: 0.5px;">Notes</p>
            <div style="font-size: 0.8rem; color: var(--text-muted); font-style: italic; background: var(--surface-light); padding: 0.75rem; border-radius: 6px;"><?php echo htmlspecialchars($asset['notes']); ?></div>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>

<!-- Add/Edit Modal -->
<div id="assetModal" class="modal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.8); z-index: 2000; align-items: center; justify-content: center;">
    <div class="card" style="width: 600px; max-height: 90vh; overflow-y: auto; padding: 2.5rem; position: relative;">
        <button onclick="closeModal()" style="position: absolute; top: 1rem; right: 1rem; background: none; border: none; color: var(--text-muted); cursor: pointer;">
            <i data-lucide="x"></i>
        </button>
        <h2 id="modalTitle" style="margin-bottom: 1.5rem;">Add Server Asset</h2>
        <form action="" method="POST">
            <input type="hidden" name="action" id="modalAction" value="add">
            <input type="hidden" name="id" id="assetId" value="">
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="input-group">
                    <label>Hostname / Server Name</label>
                    <input type="text" name="hostname" id="f_hostname" class="input-control" placeholder="e.g. Production Web" required>
                </div>
                <div class="input-group">
                    <label>IP Address</label>
                    <input type="text" name="ip_address" id="f_ip_address" class="input-control" placeholder="192.168.1.10" required>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr 80px; gap: 1rem;">
                <div class="input-group">
                    <label>Username</label>
                    <input type="text" name="username" id="f_username" class="input-control" placeholder="root">
                </div>
                <div class="input-group">
                    <label>Password</label>
                    <input type="text" name="password" id="f_password" class="input-control" placeholder="••••••••">
                </div>
                <div class="input-group">
                    <label>Port</label>
                    <input type="number" name="port" id="f_port" class="input-control" value="22">
                </div>
            </div>

            <div class="input-group">
                <label>Installed Applications / Services</label>
                <textarea name="installed_apps" id="f_installed_apps" class="input-control" style="height: 80px;" placeholder="Apache, PHP 8.2, MySQL..."></textarea>
            </div>

            <div class="input-group">
                <label>Missing / Missing Apps (To-Do)</label>
                <textarea name="missing_apps" id="f_missing_apps" class="input-control" style="height: 80px;" placeholder="Redis, Docker, Fail2Ban..."></textarea>
            </div>

            <div class="input-group">
                <label>Other Notes</label>
                <textarea name="notes" id="f_notes" class="input-control" style="height: 60px;" placeholder="Server location, backup schedule, etc."></textarea>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem; padding: 1rem;">
                <span id="submitBtnText">Add Server Asset</span>
            </button>
        </form>
    </div>
</div>

<script>
function openAddModal() {
    document.getElementById('modalTitle').innerText = 'Add Server Asset';
    document.getElementById('modalAction').value = 'add';
    document.getElementById('submitBtnText').innerText = 'Add Server Asset';
    document.getElementById('assetId').value = '';
    
    // Clear form
    document.getElementById('f_hostname').value = '';
    document.getElementById('f_ip_address').value = '';
    document.getElementById('f_username').value = '';
    document.getElementById('f_password').value = '';
    document.getElementById('f_port').value = '22';
    document.getElementById('f_installed_apps').value = '';
    document.getElementById('f_missing_apps').value = '';
    document.getElementById('f_notes').value = '';
    
    document.getElementById('assetModal').style.display = 'flex';
}

function openEditModal(data) {
    document.getElementById('modalTitle').innerText = 'Edit Server Asset';
    document.getElementById('modalAction').value = 'edit';
    document.getElementById('submitBtnText').innerText = 'Update Server Asset';
    document.getElementById('assetId').value = data.id;
    
    document.getElementById('f_hostname').value = data.hostname;
    document.getElementById('f_ip_address').value = data.ip_address;
    document.getElementById('f_username').value = data.username;
    document.getElementById('f_password').value = data.password;
    document.getElementById('f_port').value = data.port;
    document.getElementById('f_installed_apps').value = data.installed_apps;
    document.getElementById('f_missing_apps').value = data.missing_apps;
    document.getElementById('f_notes').value = data.notes;
    
    document.getElementById('assetModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('assetModal').style.display = 'none';
}

function togglePassword(id) {
    const hidden = document.getElementById('pw-' + id);
    const visible = document.getElementById('pw-real-' + id);
    const icon = document.getElementById('eye-icon-' + id);
    
    if (hidden.style.display === 'none') {
        hidden.style.display = 'inline';
        visible.style.display = 'none';
        icon.setAttribute('data-lucide', 'eye');
    } else {
        hidden.style.display = 'none';
        visible.style.display = 'inline';
        icon.setAttribute('data-lucide', 'eye-off');
    }
    lucide.createIcons();
}
</script>

<!-- Import Modal -->
<div id="importModal" class="modal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.8); z-index: 2000; align-items: center; justify-content: center;">
    <div class="card" style="width: 450px; padding: 2.5rem; position: relative;">
        <button onclick="document.getElementById('importModal').style.display='none'" style="position: absolute; top: 1rem; right: 1rem; background: none; border: none; color: var(--text-muted); cursor: pointer;">
            <i data-lucide="x"></i>
        </button>
        <h2 style="margin-bottom: 1.5rem;">Import from CSV</h2>
        <p style="font-size: 0.875rem; color: var(--text-muted); margin-bottom: 1.5rem;">Upload a CSV file exported from this system to restore or bulk-add server assets.</p>
        <form action="" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="import_csv">
            <div class="input-group">
                <label>Select CSV File</label>
                <input type="file" name="csv_file" class="input-control" accept=".csv" required>
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem; padding: 1rem;">
                Start Import
            </button>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
