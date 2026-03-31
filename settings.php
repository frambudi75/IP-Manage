<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/notifications.php';

session_start();

if (!isset($_SESSION['user_id']) || !is_admin()) {
    header('Location: login');
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
        'smtp_host' => $_POST['smtp_host'] ?? 'localhost',
        'smtp_port' => $_POST['smtp_port'] ?? '25',
        'smtp_user' => $_POST['smtp_user'] ?? '',
        'smtp_pass' => $_POST['smtp_pass'] ?? '',
        'mail_from' => $_POST['mail_from'] ?? '',
        'nmap_enabled' => isset($_POST['nmap_enabled']) ? '1' : '0',
        'discovery_aggressive' => isset($_POST['discovery_aggressive']) ? '1' : '0',
        'offline_fail_threshold' => $_POST['offline_fail_threshold'] ?? '3'
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

// Handle Test Telegram
if (isset($_POST['test_telegram'])) {
    if (NotificationHelper::testTelegram()) {
        $message = 'Test Telegram message sent!';
    } else {
        $message = '❌ Failed to send Telegram message. Check your Token and Chat ID.';
    }
}

// Handle Test Email
if (isset($_POST['test_email'])) {
    if (NotificationHelper::testEmail()) {
        $message = 'Test Email sent!';
    } else {
        $message = '❌ Error sending test email. If using SSL (port 465), ensure your server supports it.';
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

<style>
    .settings-tabs {
        display: flex;
        gap: 10px;
        margin-bottom: 2rem;
        background: var(--surface);
        padding: 5px;
        border-radius: 12px;
        display: inline-flex;
    }
    .tab-btn {
        padding: 10px 20px;
        border-radius: 8px;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 8px;
        font-weight: 600;
        font-size: 0.8rem;
        text-transform: uppercase;
        color: var(--text-muted);
        transition: all 0.2s;
    }
    .tab-btn.active {
        background: var(--primary);
        color: white;
    }
    .tab-content {
        display: none;
    }
    .tab-content.active {
        display: block;
    }
</style>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
    <h1 style="font-size: 1.5rem;">System Configuration</h1>
</div>

<?php if ($message): ?>
    <div style="padding: 1rem; background: rgba(16, 185, 129, 0.1); border: 1px solid var(--success); color: var(--success); border-radius: 8px; margin-bottom: 1.5rem;">
        <?php echo $message; ?>
    </div>
<?php endif; ?>

<div class="settings-tabs">
    <div class="tab-btn active" onclick="showTab('tab-umum')"><i data-lucide="layout"></i> UMUM</div>
    <div class="tab-btn" onclick="showTab('tab-notif')"><i data-lucide="send"></i> NOTIFIKASI</div>
    <div class="tab-btn" onclick="showTab('tab-email')"><i data-lucide="mail"></i> EMAIL</div>
</div>

<form method="POST">
    <!-- UMUM TAB -->
    <div id="tab-umum" class="tab-content active">
        <div class="card" style="max-width: 600px;">
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
            </div>
            <div class="input-group">
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                    <input type="checkbox" name="discovery_aggressive" value="1" <?php echo ($settings['discovery_aggressive'] ?? '1') == '1' ? 'checked' : ''; ?>> Aggressive Mode (More Ports)
                </label>
            </div>
            <div class="input-group" style="margin-top: 1.5rem;">
                <label>Offline Fail Threshold</label>
                <input type="number" name="offline_fail_threshold" class="input-control" value="<?php echo htmlspecialchars($settings['offline_fail_threshold'] ?? '3'); ?>" min="1" max="10">
                <p style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.5rem;">Jumlah kegagalan scan berturut-turut sebelum IP ditandai sebagai OFFLINE.</p>
            </div>
        </div>
    </div>

    <!-- NOTIFIKASI TAB -->
    <div id="tab-notif" class="tab-content">
        <div class="card" style="max-width: 600px; border-top: 4px solid #0088cc;">
            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 1.5rem;">
                <div style="background: rgba(0, 136, 204, 0.1); padding: 8px; border-radius: 50%; color: #0088cc;">
                    <i data-lucide="send"></i>
                </div>
                <h3>Telegram Bot</h3>
            </div>
            <div class="input-group">
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                    <input type="checkbox" name="telegram_enabled" value="1" <?php echo ($settings['telegram_enabled'] ?? '0') == '1' ? 'checked' : ''; ?>> Activate Telegram Alerts
                </label>
            </div>
            <div class="input-group">
                <label>Bot Token</label>
                <input type="text" name="telegram_bot_token" class="input-control" value="<?php echo htmlspecialchars($settings['telegram_bot_token'] ?? ''); ?>">
            </div>
            <div class="input-group">
                <label>Chat ID</label>
                <input type="text" name="telegram_chat_id" class="input-control" value="<?php echo htmlspecialchars($settings['telegram_chat_id'] ?? ''); ?>">
            </div>
            <button type="submit" name="test_telegram" class="btn btn-secondary" style="width: 100%; margin-top: 1rem;">
                <i data-lucide="zap"></i> Test Telegram Connection
            </button>
        </div>
    </div>

    <!-- EMAIL TAB -->
    <div id="tab-email" class="tab-content">
        <div class="card" style="max-width: 600px; border-top: 4px solid var(--success);">
            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 2rem;">
                <div style="background: rgba(16, 185, 129, 0.1); padding: 8px; border-radius: 50%; color: var(--success);">
                    <i data-lucide="mail"></i>
                </div>
                <h3>SMTP Email Configuration</h3>
            </div>
            
            <div class="input-group" style="margin-bottom: 2rem;">
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                    <input type="checkbox" name="email_enabled" value="1" <?php echo ($settings['email_enabled'] ?? '0') == '1' ? 'checked' : ''; ?>> Activate Email Alerts
                </label>
            </div>

            <div class="input-group">
                <label>FROM EMAIL (PENGIRIM)</label>
                <input type="text" name="mail_from" class="input-control" value="<?php echo htmlspecialchars($settings['mail_from'] ?? ''); ?>" placeholder="sender@yourdomain.com">
            </div>

            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1rem;">
                <div class="input-group">
                    <label>SMTP HOST</label>
                    <input type="text" name="smtp_host" class="input-control" value="<?php echo htmlspecialchars($settings['smtp_host'] ?? ''); ?>" placeholder="mail.yourdomain.com">
                </div>
                <div class="input-group">
                    <label>SMTP PORT</label>
                    <input type="text" name="smtp_port" class="input-control" value="<?php echo htmlspecialchars($settings['smtp_port'] ?? ''); ?>" placeholder="465">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="input-group">
                    <label>SMTP USER (EMAIL)</label>
                    <input type="text" name="smtp_user" class="input-control" value="<?php echo htmlspecialchars($settings['smtp_user'] ?? ''); ?>">
                </div>
                <div class="input-group">
                    <label>SMTP PASSWORD</label>
                    <input type="password" name="smtp_pass" class="input-control" value="<?php echo htmlspecialchars($settings['smtp_pass'] ?? ''); ?>">
                </div>
            </div>

            <div class="input-group">
                <label>RECEIVER EMAIL (ADMIN)</label>
                <input type="email" name="admin_email" class="input-control" value="<?php echo htmlspecialchars($settings['admin_email'] ?? 'admin@example.com'); ?>">
            </div>

            <button type="submit" name="test_email" class="btn btn-secondary" style="width: 100%; margin-top: 1rem;">
                <i data-lucide="mail-check"></i> Test Email Discovery
            </button>
        </div>
    </div>

    <div style="margin-top: 2rem; display: flex; justify-content: flex-end; gap: 1rem;">
        <button type="submit" name="save_settings" class="btn btn-primary" style="padding: 1rem 3rem;">
            <i data-lucide="check-circle-2"></i> Simpan Semua Pengaturan
        </button>
    </div>
</form>

<script>
    function showTab(tabId) {
        document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
        
        document.getElementById(tabId).classList.add('active');
        event.currentTarget.classList.add('active');
    }
</script>

<?php include 'includes/footer.php'; ?>
