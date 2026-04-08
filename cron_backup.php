<?php
/**
 * IPManager Pro - Server Assets Backup Service
 * Run this script via Windows Task Scheduler or Cron
 * Example: php.exe c:\xampp\htdocs\ipmanage\cron_backup.php
 */

define('IS_CRON', true);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/settings.helper.php';
require_once __DIR__ . '/includes/notifications.php';

$db = get_db_connection();

// Security: Allow CLI, session-based auth (for UI buttons), or secret key
if (php_sapi_name() !== 'cli') {
    session_start();
    $key = $_GET['key'] ?? '';
    $secret = Settings::get('cron_key', 'your-secret-key-change-me');
    
    if (!isset($_SESSION['user_id']) && $key !== $secret) {
        die("Unauthorized.");
    }
}

echo "[ " . date('Y-m-d H:i:s') . " ] Server Assets Backup Started\n";

$last_backup = (int)Settings::get('last_server_backup', 0);
$force = isset($_GET['force']) || (isset($argv) && in_array('--force', $argv));

// Check if 3 days (259200 seconds) have passed
if (!$force && (time() - $last_backup < 259200)) {
    echo "Backup skipped. Last backup was " . date('Y-m-d H:i:s', $last_backup) . "\n";
    exit;
}

// 1. Fetch Assets
$stmt = $db->query("SELECT * FROM server_assets ORDER BY hostname ASC");
$assets = $stmt->fetchAll();

if (empty($assets)) {
    echo "No assets found to backup.\n";
    exit;
}

// 2. Generate CSV
$csv_content = "ID,Hostname,IP Address,Username,Password,Port,Installed Apps,Missing Apps,Notes,Updated At\n";
foreach ($assets as $a) {
    $csv_content .= '"' . str_replace('"', '""', $a['id']) . '",';
    $csv_content .= '"' . str_replace('"', '""', $a['hostname']) . '",';
    $csv_content .= '"' . str_replace('"', '""', $a['ip_address']) . '",';
    $csv_content .= '"' . str_replace('"', '""', $a['username']) . '",';
    $csv_content .= '"' . str_replace('"', '""', $a['password']) . '",';
    $csv_content .= '"' . str_replace('"', '""', $a['port']) . '",';
    $csv_content .= '"' . str_replace('"', '""', $a['installed_apps']) . '",';
    $csv_content .= '"' . str_replace('"', '""', $a['missing_apps']) . '",';
    $csv_content .= '"' . str_replace('"', '""', $a['notes']) . '",';
    $csv_content .= '"' . str_replace('"', '""', $a['updated_at']) . "\"\n";
}

// 3. Generate Text Summary (Human Readable)
$txt_content = "SERVER ASSETS BACKUP - " . date('Y-m-d H:i:s') . "\n";
$txt_content .= "==========================================\n\n";
foreach ($assets as $a) {
    $txt_content .= "Server: " . $a['hostname'] . " (" . $a['ip_address'] . ":" . $a['port'] . ")\n";
    $txt_content .= "Login: " . $a['username'] . " / " . $a['password'] . "\n";
    $txt_content .= "Installed: " . str_replace("\n", ", ", $a['installed_apps']) . "\n";
    $txt_content .= "Missing: " . str_replace("\n", ", ", $a['missing_apps']) . "\n";
    $txt_content .= "Notes: " . $a['notes'] . "\n";
    $txt_content .= "------------------------------------------\n\n";
}

// 4. Determine Recipient
$to = Settings::get('admin_email');
if (php_sapi_name() !== 'cli' && isset($_SESSION['user_id'])) {
    $stmt = $db->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $u_email = $check_email = $stmt->fetchColumn();
    if (!empty($u_email)) $to = $u_email;
}

$subject = "📁 Server Assets Backup - " . date('d M Y');
$body = "<h2>Server Assets Automated Backup</h2>";
$body .= "<p>Hello, here are the backup files you requested for your server assets records.</p>";
$body .= "<p><b>CSV File:</b> Use this to restore or import data back into the system.</p>";
$body .= "<p><b>TXT File:</b> Human-readable summary of all server access details.</p>";
$body .= "<br><p>Sent from: " . APP_NAME . "</p>";

$attachments = [
    'server_assets_backup_' . date('Y-m-d') . '.csv' => $csv_content,
    'server_assets_summary_' . date('Y-m-d') . '.txt' => $txt_content
];

$success = NotificationHelper::sendEmailWithAttachments($subject, $body, $attachments, $to); 

if ($success) {
    Settings::set('last_server_backup', time());
    $msg = "Backup successful and email sent!";
    echo $msg . "\n";
} else {
    $msg = "ERROR: Failed to send backup email. Check SMTP settings.";
    echo $msg . "\n";
}

// Redirect back if triggered via browser
if (php_sapi_name() !== 'cli') {
    header("Location: server-assets?msg=" . urlencode($msg));
    exit;
}
?>
