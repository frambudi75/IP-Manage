<?php
/**
 * Netwatch Notification Test Script
 */
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/notifications.php';

echo "--- Netwatch Telegram Test ---\n";

$test_name = "SIMULASI-DEVICE-TEST";
$test_host = "1.2.3.4";
$test_status = "down"; // Kita tes kirim status DOWN

echo "Mengirim notifikasi simulasi ke Telegram...\n";

try {
    NotificationHelper::notifyNetwatch($test_name, $test_host, $test_status);
    echo "✅ Berhasil! Silakan cek Telegram Anda.\n";
    echo "Jika tidak ada pesan masuk, pastikan Bot Token dan Chat ID di System Settings sudah benar.\n";
} catch (Exception $e) {
    echo "❌ Gagal mengirim: " . $e->getMessage() . "\n";
}
?>
