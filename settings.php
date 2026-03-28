<?php
require_once 'includes/config.php';
require_once 'includes/db.php';

session_start();

if (!isset($_SESSION['user_id']) || !is_admin()) {
    header('Location: login.php');
    exit;
}

$db = get_db_connection();
$page_title = 'System Settings';
$message = '';

// Handle save settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $to_save = [
        'telegram_enabled' => isset($_POST['telegram_enabled']) ? '1' : '0',
        'telegram_bot_token' => $_POST['telegram_bot_token'] ?? '',
        'telegram_chat_id' => $_POST['telegram_chat_id'] ?? '',
        'email_enabled' => isset($_POST['email_enabled']) ? '1' : '0',
        'admin_email' => $_POST['admin_email'] ?? '',
        'nmap_enabled' => isset($_POST['nmap_enabled']) ? '1' : '0',
        'discovery_aggressive' => isset($_POST['discovery_aggressive']) ? '1' : '0'
    ];

    try {
        $db->beginTransaction();
        foreach ($to_save as $key => $value) {
            $stmt = $db->prepare("INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)");
            $stmt->execute([$key, trim($value)]);
        }
        $db->commit();
        $message = 'Settings saved successfully!';
    } catch (Exception $e) {
        $db->rollBack();
        $message = 'Error saving settings: ' . $e->getMessage();
    }
}

// Fetch current settings
$stmt = $db->query("SELECT * FROM settings");
$settings_list = $stmt->fetchAll();
$settings = [];
foreach ($settings_list as $s) {
    $settings[$s['key']] = $s['value'];
}

include 'includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
    <h1 style="font-size: 1.5rem;">System Configuration</h1>
</div>

<?php if ($message): ?>
    <div style="padding: 1rem; background: rgba(16, 185, 129, 0.1); border: 1px solid var(--success); color: var(--success); border-radius: 8px; margin-bottom: 1.5rem;">
        <?php echo $message; ?>
    </div>
<?php endif; ?>

<form method="POST">
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 2rem;">
        
        <!-- Telegram Settings -->
        <div class="card" style="border-top: 4px solid #0088cc;">
            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 1.5rem;">
                <div style="background: rgba(0, 136, 204, 0.1); padding: 8px; border-radius: 50%; color: #0088cc;">
                    <i data-lucide="send"></i>
                </div>
                <h3>Telegram Notifications</h3>
            </div>
            <div class="input-group">
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                    <input type="checkbox" name="telegram_enabled" value="1" <?php echo ($settings['telegram_enabled'] ?? '0') == '1' ? 'checked' : ''; ?>> Enable Telegram Alerts
                </label>
            </div>
            <div class="input-group">
                <label>Bot Token</label>
                <input type="text" name="telegram_bot_token" class="input-control" value="<?php echo htmlspecialchars($settings['telegram_bot_token'] ?? ''); ?>" placeholder="1234567890:ABCdef...">
            </div>
            <div class="input-group">
                <label>Chat ID</label>
                <input type="text" name="telegram_chat_id" class="input-control" value="<?php echo htmlspecialchars($settings['telegram_chat_id'] ?? ''); ?>" placeholder="-100123456789">
            </div>
            <p style="font-size: 0.75rem; color: var(--text-muted);">Get token from <strong>@BotFather</strong> and ID from <strong>@userinfobot</strong>.</p>
        </div>

        <!-- Scan Settings -->
        <div class="card" style="border-top: 4px solid var(--primary);">
            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 1.5rem;">
                <div style="background: rgba(59, 130, 246, 0.1); padding: 8px; border-radius: 50%; color: var(--primary);">
                    <i data-lucide="search"></i>
                </div>
                <h3>Discovery Settings</h3>
            </div>
            <div class="input-group">
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                    <input type="checkbox" name="nmap_enabled" value="1" <?php echo ($settings['nmap_enabled'] ?? '0') == '1' ? 'checked' : ''; ?>> Enable Nmap OS Detection
                </label>
                <p style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">Requires <code>nmap</code> installed on the server.</p>
            </div>
            <div class="input-group">
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                    <input type="checkbox" name="discovery_aggressive" value="1" <?php echo ($settings['discovery_aggressive'] ?? '1') == '1' ? 'checked' : ''; ?>> Aggressive Mode (More Ports)
                </label>
            </div>
        </div>

        <!-- Email Settings -->
        <div class="card" style="border-top: 4px solid var(--warning);">
            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 1.5rem;">
                <div style="background: rgba(245, 158, 11, 0.1); padding: 8px; border-radius: 50%; color: var(--warning);">
                    <i data-lucide="mail"></i>
                </div>
                <h3>Email Settings</h3>
            </div>
            <div class="input-group">
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                    <input type="checkbox" name="email_enabled" value="1" <?php echo ($settings['email_enabled'] ?? '0') == '1' ? 'checked' : ''; ?>> Enable Email Alerts
                </label>
            </div>
            <div class="input-group">
                <label>Admin Email Address</label>
                <input type="email" name="admin_email" class="input-control" value="<?php echo htmlspecialchars($settings['admin_email'] ?? 'admin@example.com'); ?>">
            </div>
        </div>
    </div>

    <div style="margin-top: 2rem; display: flex; justify-content: flex-end;">
        <button type="submit" name="save_settings" class="btn btn-primary" style="padding: 1rem 3rem;">
            <i data-lucide="save"></i> Save Configuration
        </button>
    </div>
</form>

<?php include 'includes/footer.php'; ?>
