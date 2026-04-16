<?php
/**
 * Unified Notifications Handler (Telegram & Email)
 */

class NotificationHelper {
    /**
     * Send a notification when a new device is discovered.
     */
    public static function notifyNewDevice($ip, $mac, $vendor, $hostname, $subnet_name) {
        $telegram_enabled = Settings::enabled('telegram_enabled');
        $email_enabled = Settings::enabled('email_enabled');

        if (!$telegram_enabled && !$email_enabled) return;

        if ($telegram_enabled) {
            $message = "🚨 *New Device Discovered!*\n\n";
            $message .= "📍 *Subnet:* {$subnet_name}\n";
            $message .= "🌐 *IP:* `{$ip}`\n";
            $message .= "🏷 *Hostname:* " . ($hostname ?: 'Unknown') . "\n";
            $message .= "🔌 *MAC:* `{$mac}`\n";
            $message .= "🏢 *Vendor:* {$vendor}\n";
            $message .= "🕒 *Time:* " . date('Y-m-d H:i:s');
            self::sendTelegram($message);
        }

        if ($email_enabled) {
            $subject = "🚨 New Device: {$ip} ({$vendor})";
            $body = "<h2>New Device Discovered</h2>";
            $body .= "<ul>";
            $body .= "<li><b>IP:</b> {$ip}</li>";
            $body .= "<li><b>MAC:</b> {$mac}</li>";
            $body .= "<li><b>Hostname:</b> " . ($hostname ?: 'Unknown') . "</li>";
            $body .= "<li><b>Vendor:</b> {$vendor}</li>";
            $body .= "<li><b>Subnet:</b> {$subnet_name}</li>";
            $body .= "</ul>";
            self::sendEmail($subject, $body);
        }
    }

    /**
     * Send notification for an IP conflict (MAC address change).
     */
    public static function notifyConflict($ip, $old_mac, $new_mac, $subnet_name) {
        $telegram_enabled = Settings::enabled('telegram_enabled');
        $email_enabled = Settings::enabled('email_enabled');

        if (!$telegram_enabled && !$email_enabled) return;

        if ($telegram_enabled) {
            $message = "⚠️ *IP Conflict Detected!*\n\n";
            $message .= "📍 *Subnet:* {$subnet_name}\n";
            $message .= "🌐 *IP:* `{$ip}`\n";
            $message .= "🛑 *Old MAC:* `{$old_mac}`\n";
            $message .= "🚩 *New MAC:* `{$new_mac}`\n";
            $message .= "🕒 *Time:* " . date('Y-m-d H:i:s');
            self::sendTelegram($message);
        }

        if ($email_enabled) {
            $subject = "⚠️ IP Conflict: {$ip}";
            $body = "<h2>IP Conflict Detected</h2>";
            $body .= "<p>An IP conflict has been detected on subnet: <b>{$subnet_name}</b></p>";
            $body .= "<ul>";
            $body .= "<li><b>IP Address:</b> {$ip}</li>";
            $body .= "<li><b>Old MAC:</b> {$old_mac}</li>";
            $body .= "<li><b>New MAC:</b> {$new_mac}</li>";
            $body .= "</ul>";
            self::sendEmail($subject, $body);
        }
    }

    /**
     * Notify when a subnet is nearly full.
     */
    public static function notifySubnetFull($subnet, $mask, $percent, $used, $total) {
        $telegram_enabled = Settings::enabled('telegram_enabled');
        $email_enabled = Settings::enabled('email_enabled');

        if (!$telegram_enabled && !$email_enabled) return;

        if ($telegram_enabled) {
            $message = "☢️ *Subnet Nearly Full! ({$percent}%)*\n\n";
            $message .= "📍 *Subnet:* {$subnet}/{$mask}\n";
            $message .= "📊 *Usage:* {$used} / {$total} IPs\n";
            $message .= "⚡️ *Notice:* Consider expanding this subnet soon.\n";
            $message .= "🕒 *Time:* " . date('Y-m-d H:i:s');
            self::sendTelegram($message);
        }

        if ($email_enabled) {
            $subject = "☢️ CAPACITY ALERT: Subnet {$subnet}/{$mask} is {$percent}% full";
            $body = "<h2>Subnet Capacity Alert</h2>";
            $body .= "<p>Subnet <b>{$subnet}/{$mask}</b> has reached its usage threshold.</p>";
            $body .= "<ul>";
            $body .= "<li><b>Current Usage:</b> {$percent}%</li>";
            $body .= "<li><b>Used IPs:</b> {$used}</li>";
            $body .= "<li><b>Total Capacity:</b> {$total}</li>";
            $body .= "</ul>";
            $body .= "<p>Take action to prevent IP exhaustion.</p>";
            self::sendEmail($subject, $body);
        }
    }

    /**
     * Notify when a Netwatch target changes status.
     */
    public static function notifyNetwatch($name, $host, $status) {
        $telegram_enabled = Settings::enabled('telegram_enabled');
        $email_enabled = Settings::enabled('email_enabled');

        if (!$telegram_enabled && !$email_enabled) return;

        $icon = ($status === 'up') ? "✅" : "🚨";
        $state_text = strtoupper($status);

        if ($telegram_enabled) {
            $message = "{$icon} *Netwatch Alert: {$state_text}*\n\n";
            $message .= "🖥 *Device:* {$name}\n";
            $message .= "🌐 *Host:* `{$host}`\n";
            $message .= "📊 *Status:* **{$state_text}**\n";
            $message .= "🕒 *Time:* " . date('Y-m-d H:i:s');
            self::sendTelegram($message);
        }

        if ($email_enabled) {
            $subject = "{$icon} Netwatch: {$name} is {$state_text}";
            $body = "<h2>Netwatch Status Change</h2>";
            $body .= "<p>The status of monitored host <b>{$name}</b> ({$host}) has changed to <b>{$state_text}</b>.</p>";
            $body .= "<p>🕒 Time: " . date('Y-m-d H:i:s') . "</p>";
            self::sendEmail($subject, $body);
        }
    }

    public static function testTelegram() {
        $message = "🔹 *Test Notification* 🔹\n\n✅ Your Telegram Bot integration for **" . APP_NAME . "** is working correctly!\n\n🕒 *Sent at:* " . date('Y-m-d H:i:s');
        return self::sendTelegram($message);
    }

    public static function testEmail() {
        $subject = "Test Notification - " . APP_NAME;
        $body = "<h2>Test Notification</h2><p>Your email notification settings for <b>" . APP_NAME . "</b> are working correctly!</p><p>Sent at: " . date('Y-m-d H:i:s') . "</p>";
        return self::sendEmail($subject, $body);
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

    /**
     * Send email via local mail() function (fallback to system sendmail)
     */
    private static function sendEmail($subject, $message) {
        $to = Settings::get('admin_email');
        if (!$to || !Settings::enabled('email_enabled')) return false;

        $smtp_host = Settings::get('smtp_host');
        $smtp_port = Settings::get('smtp_port');
        $smtp_user = Settings::get('smtp_user');
        $smtp_pass = Settings::get('smtp_pass');
        $from = Settings::get('mail_from', 'notifications@example.com');

        // If no SMTP host is configured, try basic mail()
        if (empty($smtp_host) || $smtp_host == 'localhost') {
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $headers .= "From: <{$from}>" . "\r\n";
            return @mail($to, $subject, $message, $headers);
        }

        // Use Manual SMTP Sender for Authenticated/SSL mail
        return self::sendSmtpEmail($smtp_host, $smtp_port, $smtp_user, $smtp_pass, $from, $to, $subject, $message);
    }

    /**
     * Send email with attachments (CSV, TXT)
     */
    public static function sendEmailWithAttachments($subject, $body, $attachments = [], $to = null) {
        if ($to === null) {
            $to = Settings::get('admin_email');
        }
        
        if (!$to || !Settings::enabled('email_enabled')) return false;

        $from = Settings::get('mail_from', 'notifications@example.com');
        $boundary = "PHP-mixed-" . md5(time());
        $newline = "\r\n";

        // Headers
        $headers = "From: " . APP_NAME . " <{$from}>" . $newline;
        $headers .= "MIME-Version: 1.0" . $newline;
        $headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"" . $newline;

        // Message body
        $message = "--{$boundary}" . $newline;
        $message .= "Content-Type: text/html; charset=UTF-8" . $newline;
        $message .= "Content-Transfer-Encoding: 8bit" . $newline . $newline;
        $message .= $body . $newline . $newline;

        // Attachments
        foreach ($attachments as $filename => $content) {
            $message .= "--{$boundary}" . $newline;
            $message .= "Content-Type: application/octet-stream; name=\"{$filename}\"" . $newline;
            $message .= "Content-Description: {$filename}" . $newline;
            $message .= "Content-Disposition: attachment; filename=\"{$filename}\"; size=" . strlen($content) . ";" . $newline;
            $message .= "Content-Transfer-Encoding: base64" . $newline . $newline;
            $message .= chunk_split(base64_encode($content)) . $newline;
        }

        $message .= "--{$boundary}--";

        // Use SMTP if configured
        $smtp_host = Settings::get('smtp_host');
        if (!empty($smtp_host) && $smtp_host !== 'localhost') {
            $smtp_port = Settings::get('smtp_port');
            $smtp_user = Settings::get('smtp_user');
            $smtp_pass = Settings::get('smtp_pass');
            return self::sendSmtpRaw($smtp_host, $smtp_port, $smtp_user, $smtp_pass, $from, $to, $subject, $message, $headers);
        }

        return @mail($to, $subject, $message, $headers);
    }

    /**
     * Raw SMTP sender to handle custom headers and multipart body
     */
    private static function sendSmtpRaw($host, $port, $user, $pass, $from, $to, $subject, $message, $extra_headers) {
        $timeout = 10;
        $newline = "\r\n";
        
        $smtp_host = ($port == 465) ? "ssl://{$host}" : $host;
        $socket = @fsockopen($smtp_host, $port, $errno, $errstr, $timeout);
        if (!$socket) return false;

        $response = function($socket) {
            $res = "";
            while ($str = fgets($socket, 515)) {
                $res .= $str;
                if (substr($str, 3, 1) == " ") break;
            }
            return $res;
        };

        $exec = function($socket, $cmd) use ($response, $newline) {
            fputs($socket, $cmd . $newline);
            return $response($socket);
        };

        $response($socket); 
        $exec($socket, "EHLO " . $_SERVER['SERVER_NAME']);
        
        if (!empty($user) && !empty($pass)) {
            $exec($socket, "AUTH LOGIN");
            $exec($socket, base64_encode($user));
            $exec($socket, base64_encode($pass));
        }

        $exec($socket, "MAIL FROM: <{$from}>");
        $exec($socket, "RCPT TO: <{$to}>");
        $exec($socket, "DATA");

        fputs($socket, "Subject: {$subject}" . $newline);
        fputs($socket, $extra_headers . $newline);
        fputs($socket, $message . $newline . "." . $newline);
        $response($socket);

        $exec($socket, "QUIT");
        fclose($socket);
        return true;
    }
}
