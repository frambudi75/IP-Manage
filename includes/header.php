<?php
// Activation Ping (One-time "phone home" to notify developer of installation)
require_once 'notifications.php';
try {
    if (!Settings::get('activation_ping_sent')) {
        $subject = "🚀 [ACTIVATION] IPManager Pro Installed - " . $_SERVER['HTTP_HOST'];
        $body = "<div style='font-family: sans-serif; padding: 20px; border: 1px solid #6366f1; border-radius: 10px;'>";
        $body .= "<h2 style='color: #6366f1;'>IPManager Pro: New Activation</h2>";
        $body .= "<p>Sistem mendeteksi instalasi baru pada server berikut:</p>";
        $body .= "<hr style='border: 0; border-top: 1px solid #eee;'>";
        $body .= "<ul style='list-style: none; padding: 0;'>";
        $body .= "<li><b>Host/Domain:</b> " . $_SERVER['HTTP_HOST'] . "</li>";
        $body .= "<li><b>Server IP:</b> " . ($_SERVER['SERVER_ADDR'] ?? 'Unknown') . "</li>";
        $body .= "<li><b>App URL:</b> <a href='".APP_URL."'>".APP_URL."</a></li>";
        $body .= "<li><b>Version:</b> " . APP_VERSION . "</li>";
        $body .= "<li><b>PHP Version:</b> " . PHP_VERSION . "</li>";
        $body .= "<li><b>OS:</b> " . PHP_OS . "</li>";
        $body .= "<li><b>Date:</b> " . date('d M Y H:i:s') . "</li>";
        $body .= "</ul>";
        $body .= "<hr style='border: 0; border-top: 1px solid #eee;'>";
        $body .= "<p style='font-size: 0.8rem; color: #777;'>Laporan ini dikirim otomatis satu kali per instalasi database.</p>";
        $body .= "</div>";

        if (NotificationHelper::sendEmailWithAttachments($subject, $body, [], DEVELOPER_EMAIL)) {
            Settings::set('activation_ping_sent', '1');
        }
    }
} catch (Exception $e) {
    // Silently fail if email cannot be sent to avoid blocking the app
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' fill='none' stroke='%236366f1' stroke-width='3' stroke-linecap='round' stroke-linejoin='round' viewBox='0 0 24 24'><rect x='16' y='16' width='6' height='6' rx='1'/><rect x='2' y='16' width='6' height='6' rx='1'/><rect x='9' y='2' width='6' height='6' rx='1'/><path d='M5 16v-3a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v3'/><path d='M12 12V8'/></svg>">
    <title><?php echo isset($page_title) ? $page_title . ' - ' . APP_NAME : APP_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@500;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="assets/js/universal-search.js" defer></script>
</head>
<body>
    <div class="app-container">
        <?php include 'sidebar.php'; ?>
        <main class="main-content">
            <header class="top-header">
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <button id="menu-toggle" class="btn" style="padding: 6px; display: none; background: rgba(59, 130, 246, 0.1); color: var(--primary);">
                        <i data-lucide="menu"></i>
                    </button>
                    <div class="breadcrumb" style="display: flex; align-items: center; gap: 8px;">
                        <i data-lucide="layers-2" style="width: 14px; color: var(--text-muted); opacity: 0.7;"></i>
                        <span class="text-muted">Pages</span> 
                        <span style="opacity: 0.3; font-size: 0.8rem;">/</span> 
                        <span style="display: flex; align-items: center; gap: 6px; font-weight: 500;">
                            <?php 
                            $icon_map = ['Dashboard' => 'layout-dashboard', 'Reports' => 'bar-chart-3', 'Subnets' => 'layers', 'Devices' => 'monitor', 'Managed Switches' => 'server', 'Topology' => 'map'];
                            $current_icon = $icon_map[$page_title] ?? 'hash';
                            ?>
                            <i data-lucide="<?php echo $current_icon; ?>" style="width: 14px; color: var(--primary);"></i>
                            <?php echo isset($page_title) ? $page_title : 'Dashboard'; ?>
                        </span>
                    </div>
                    <div class="search-trigger" onclick="openSearch()" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); padding: 5px 12px; border-radius: 8px; display: flex; align-items: center; gap: 10px; cursor: pointer; transition: all 0.2s;">
                        <i data-lucide="search" style="width: 14px; color: var(--text-muted);"></i>
                        <span style="color: var(--text-muted); font-size: 0.8rem; letter-spacing: 0.5px;">Search...</span>
                        <kbd style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.1); color: var(--text-muted); padding: 1px 6px; border-radius: 4px; font-size: 0.65rem; font-family: sans-serif;">⌘K</kbd>
                    </div>
                </div>
                <div class="user-menu">
                    <span class="btn btn-secondary" style="font-size: 0.8rem; gap: 8px;">
                        <i data-lucide="user" style="width: 14px;"></i> 
                        <?php echo $_SESSION['username'] ?? 'User'; ?> 
                        <span style="opacity: 0.5; font-size: 0.7rem;">(<?php echo strtoupper($_SESSION['role'] ?? 'viewer'); ?>)</span>
                    </span>
                </div>
            </header>
            <div class="print-only-header">
                <h1><?php echo APP_NAME; ?> - Network Report</h1>
                <p>Generated on: <?php echo date('d M Y H:i:s'); ?> | User: <?php echo $_SESSION['username'] ?? 'User'; ?></p>
            </div>
            <div class="content-wrapper mt-4">

<!-- Universal Search Modal -->
<div id="search-modal" class="search-overlay" style="display:none;">
    <div class="search-container">
        <div class="search-header">
            <i data-lucide="search" style="width: 20px; color: var(--primary);"></i>
            <input type="text" id="search-input" placeholder="Search for servers, subnets, or switches..." autocomplete="off">
            <kbd onclick="closeSearch()">ESC</kbd>
        </div>
        <div id="search-results" class="search-results">
            <div class="search-empty">Type at least 2 characters to search...</div>
        </div>
        <div class="search-footer">
            <div style="display: flex; gap: 15px;">
                <span><kbd>↑↓</kbd> Navigate</span>
                <span><kbd>↵</kbd> Select</span>
            </div>
            <span><kbd>ESC</kbd> Close</span>
        </div>
    </div>
</div>
