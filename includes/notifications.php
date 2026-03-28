<?php
/**
 * Unified Notifications Handler (Telegram & Email)
 */

class NotificationHelper {
    /**
     * Send a notification when a new device is discovered.
     */
    public static function notifyNewDevice($ip, $mac, $vendor, $hostname, $subnet_name) {
        if (!Settings::enabled('telegram_enabled')) return;

        $message = "🚨 *New Device Discovered!*\n\n";
        $message .= "📍 *Subnet:* {$subnet_name}\n";
        $message .= "🌐 *IP:* `{$ip}`\n";
        $message .= "🏷 *Hostname:* " . ($hostname ?: 'Unknown') . "\n";
        $message .= "🔌 *MAC:* `{$mac}`\n";
        $message .= "🏢 *Vendor:* {$vendor}\n";
        $message .= "🕒 *Time:* " . date('Y-m-d H:i:s');

        self::sendTelegram($message);
    }

    /**
     * Send notification for an IP conflict (MAC address change).
     */
    public static function notifyConflict($ip, $old_mac, $new_mac, $subnet_name) {
        if (!Settings::enabled('telegram_enabled')) return;

        $message = "⚠️ *IP Conflict Detected!*\n\n";
        $message .= "📍 *Subnet:* {$subnet_name}\n";
        $message .= "🌐 *IP:* `{$ip}`\n";
        $message .= "🛑 *Old MAC:* `{$old_mac}`\n";
        $message .= "🚩 *New MAC:* `{$new_mac}`\n";
        $message .= "🕒 *Time:* " . date('Y-m-d H:i:s');

        self::sendTelegram($message);
    }

    /**
     * Send message via Telegram Bot API
     */
    private static function sendTelegram($text) {
        $token = Settings::get('telegram_bot_token');
        $chat_id = Settings::get('telegram_chat_id');

        if (!$token || !$chat_id) return false;

        $url = "https://api.telegram.org/bot{$token}/sendMessage";
        $data = [
            'chat_id' => $chat_id,
            'text' => $text,
            'parse_mode' => 'Markdown'
        ];

        $options = [
            'http' => [
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => http_build_query($data),
                'timeout' => 5
            ]
        ];

        $context  = stream_context_create($options);
        return @file_get_contents($url, false, $context);
    }
}
