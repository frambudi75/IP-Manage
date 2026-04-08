<?php
/**
 * IPManager Pro - About Page
 * Application info, developer details, and support links.
 */

require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/updater.php';

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login');
    exit;
}

$db = get_db_connection();
Updater::check(); // Check for updates (cached 24h)

// Pull some live stats for display
$total_subnets  = $db->query("SELECT COUNT(*) FROM subnets")->fetchColumn();
$total_devices  = $db->query("SELECT COUNT(*) FROM ip_addresses")->fetchColumn();
$total_switches = $db->query("SELECT COUNT(*) FROM switches")->fetchColumn();
$total_users    = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();

define('APP_AUTHOR', 'Habib Frambudi');
define('APP_AUTHOR_EMAIL', 'habibframbudi@gmail.com');
define('APP_GITHUB', GITHUB_URL);
define('APP_SAWERIA', 'https://saweria.co/Habibframbudi');
define('APP_PAYPAL', 'https://paypal.me/habibframbudi');

$page_title = 'About';
include 'includes/header.php';
?>

<!-- Hero Section -->
<div style="text-align: center; padding: 3rem 1rem 2rem; position: relative; overflow: hidden;">
    <div style="position: absolute; inset: 0; background: radial-gradient(ellipse at 50% 0%, rgba(99,102,241,0.15) 0%, transparent 70%); pointer-events: none;"></div>
    
    <?php if (Updater::isUpdateAvailable()): ?>
    <!-- Update Alert Banner -->
    <div style="max-width: 600px; margin: 0 auto 2rem; background: linear-gradient(135deg, rgba(99,102,241,0.1), rgba(139,92,246,0.1)); border: 1px solid rgba(99,102,241,0.3); border-radius: 16px; padding: 1.25rem; display: flex; align-items: center; gap: 1.25rem; text-align: left; animation: slideIn 0.5s ease-out;">
        <div style="background: var(--primary); color: white; width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; box-shadow: 0 4px 12px rgba(99,102,241,0.3);">
            <i data-lucide="arrow-up-circle"></i>
        </div>
        <div style="flex-grow: 1;">
            <div style="font-weight: 700; color: white; font-size: 1rem;">Update Tersedia: v<?php echo Updater::getLatestVersion(); ?></div>
            <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 2px;">Versi baru telah dirilis dengan peningkatan fitur dan stabilitas.</div>
        </div>
        <a href="<?php echo Updater::getUpdateUrl(); ?>" target="_blank" class="btn btn-primary" style="font-size: 0.8rem; padding: 8px 16px; border-radius: 8px; white-space: nowrap;">
            Update Sekarang
        </a>
    </div>
    <style>
        @keyframes slideIn { from { transform: translateY(-20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    </style>
    <?php endif; ?>

    <div style="display: inline-flex; align-items: center; justify-content: center; width: 80px; height: 80px; background: linear-gradient(135deg, var(--primary), #8b5cf6); border-radius: 20px; margin-bottom: 1.5rem; box-shadow: 0 8px 32px rgba(99,102,241,0.4);">
        <i data-lucide="network" style="width: 40px; height: 40px; color: white;"></i>
    </div>
    <h1 style="font-size: 2.5rem; font-weight: 900; margin-bottom: 0.5rem; background: linear-gradient(135deg, #fff 0%, rgba(255,255,255,0.6) 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">IPManager Pro</h1>
    <p style="color: var(--text-muted); font-size: 1rem; margin-bottom: 1.5rem;">High-Performance IP Address Management & Network Monitoring</p>
    <div style="display: inline-flex; gap: 0.75rem; flex-wrap: wrap; justify-content: center;">
        <span style="background: rgba(99,102,241,0.15); border: 1px solid rgba(99,102,241,0.3); color: var(--primary); padding: 6px 16px; border-radius: 20px; font-size: 0.85rem; font-weight: 600;">
            v<?php echo APP_VERSION; ?>
        </span>
        <span style="background: rgba(34,197,94,0.1); border: 1px solid rgba(34,197,94,0.3); color: var(--success, #22c55e); padding: 6px 16px; border-radius: 20px; font-size: 0.85rem; font-weight: 600;">
            Released <?php echo date('d M Y', strtotime(APP_RELEASE_DATE)); ?>
        </span>
        <span style="background: rgba(255,255,255,0.05); border: 1px solid var(--border); color: var(--text-muted); padding: 6px 16px; border-radius: 20px; font-size: 0.85rem;">
            Open Source · MIT License
        </span>
    </div>
</div>

<!-- Live Stats -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
    <?php
    $stats = [
        ['icon' => 'layers',  'value' => $total_subnets,  'label' => 'Subnets',  'color' => 'var(--primary)'],
        ['icon' => 'monitor', 'value' => $total_devices,  'label' => 'Devices',  'color' => 'var(--success, #22c55e)'],
        ['icon' => 'server',  'value' => $total_switches, 'label' => 'Switches', 'color' => 'var(--warning, #f59e0b)'],
        ['icon' => 'users',   'value' => $total_users,    'label' => 'Users',    'color' => '#8b5cf6'],
    ];
    foreach ($stats as $s): ?>
    <div class="card" style="text-align: center; padding: 1.5rem 1rem;">
        <i data-lucide="<?php echo $s['icon']; ?>" style="width: 28px; height: 28px; color: <?php echo $s['color']; ?>; margin-bottom: 0.75rem;"></i>
        <div style="font-size: 2.25rem; font-weight: 900; color: <?php echo $s['color']; ?>; line-height: 1;"><?php echo number_format((int)$s['value']); ?></div>
        <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.25rem;"><?php echo $s['label']; ?> in Database</div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Main Grid -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">

    <!-- Developer Card -->
    <div class="card" style="position: relative; overflow: hidden;">
        <div style="position: absolute; top: -40px; right: -40px; width: 150px; height: 150px; background: radial-gradient(circle, rgba(99,102,241,0.1), transparent); border-radius: 50%; pointer-events: none;"></div>
        <h3 style="font-size: 1rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
            <i data-lucide="code-2" style="width: 18px; color: var(--primary);"></i> Developer
        </h3>
        <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem;">
            <img src="https://github.com/frambudi75.png" 
                 style="width: 60px; height: 60px; border-radius: 50%; object-fit: cover; border: 2px solid var(--primary); flex-shrink: 0;"
                 alt="Habib Frambudi">
            <div>
                <div style="font-size: 1.1rem; font-weight: 700;"><?php echo APP_AUTHOR; ?></div>
                <div style="font-size: 0.8rem; color: var(--text-muted);">Network Engineer & Developer</div>
                <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 2px;">
                    <a href="mailto:<?php echo APP_AUTHOR_EMAIL; ?>" style="color: var(--primary); text-decoration: none;"><?php echo APP_AUTHOR_EMAIL; ?></a>
                </div>
            </div>
        </div>
        <div style="display: flex; flex-direction: column; gap: 0.75rem; font-size: 0.875rem; border-top: 1px solid var(--border); padding-top: 1.25rem;">
            <a href="<?php echo APP_GITHUB; ?>" target="_blank" style="display: flex; align-items: center; gap: 0.75rem; color: var(--text); text-decoration: none; padding: 8px; border-radius: 8px; transition: background 0.2s;" onmouseover="this.style.background='var(--surface-light)'" onmouseout="this.style.background='transparent'">
                <i data-lucide="github" style="width: 18px; color: var(--text-muted);"></i>
                <span>GitHub Repository</span>
                <i data-lucide="external-link" style="width: 14px; color: var(--text-muted); margin-left: auto;"></i>
            </a>
        </div>
    </div>

    <!-- Tech Stack Card -->
    <div class="card">
        <h3 style="font-size: 1rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
            <i data-lucide="cpu" style="width: 18px; color: var(--primary);"></i> Tech Stack
        </h3>
        <?php
        $stack = [
            ['name' => 'PHP 8.2',         'desc' => 'Backend engine',               'color' => '#7c3aed'],
            ['name' => 'Apache 2.4',       'desc' => 'Web server',                   'color' => '#dc2626'],
            ['name' => 'MariaDB 10.11',    'desc' => 'Database',                     'color' => '#0284c7'],
            ['name' => 'Vanilla CSS',      'desc' => 'Styling & dark-mode design',   'color' => '#0891b2'],
            ['name' => 'Chart.js 4',       'desc' => 'Performance graphs',           'color' => '#f97316'],
            ['name' => 'Lucide Icons',     'desc' => 'UI iconography',               'color' => '#10b981'],
            ['name' => 'SNMP v2c',         'desc' => 'Device health monitoring',     'color' => '#6366f1'],
            ['name' => 'Server-Sent Events','desc' => 'Realtime data streaming',     'color' => '#f59e0b'],
            ['name' => 'Docker + Opcache', 'desc' => 'Container performance optimization', 'color' => '#2563eb'],
            ['name' => 'Redis 7',          'desc' => 'Advanced data caching (NEW)',  'color' => '#dc2626'],
        ];
        foreach ($stack as $t): ?>
        <div style="display: flex; align-items: center; gap: 0.75rem; padding: 10px 0; border-bottom: 1px solid rgba(255,255,255,0.04); flex-wrap: wrap;">
            <span style="width: 10px; height: 10px; border-radius: 50%; background: <?php echo $t['color']; ?>; flex-shrink: 0;"></span>
            <span style="font-weight: 600; font-size: 0.875rem; min-width: 140px; flex: 1;"><?php echo $t['name']; ?></span>
            <span style="font-size: 0.8rem; color: var(--text-muted); width: 100%; margin-left: 20px;"><?php echo $t['desc']; ?></span>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Support Section -->
<div class="card" style="margin-bottom: 1.5rem; background: linear-gradient(135deg, rgba(99,102,241,0.08) 0%, rgba(139,92,246,0.05) 100%); border: 1px solid rgba(99,102,241,0.2);">
    <div style="text-align: center; max-width: 600px; margin: 0 auto; padding: 1rem 0;">
        <div style="font-size: 2rem; margin-bottom: 0.5rem;">☕</div>
        <h3 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 0.5rem;">Dukung Pengembangan IPManager Pro</h3>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1.75rem; line-height: 1.6;">
            IPManager Pro dikembangkan dan dikelola secara independen. Jika aplikasi ini membantu pekerjaan Anda, 
            pertimbangkan untuk mendukung pengembangan fitur-fitur baru.
        </p>
        <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
            <!-- Saweria -->
            <a href="<?php echo APP_SAWERIA; ?>" target="_blank"
               style="display: inline-flex; align-items: center; gap: 0.6rem; padding: 12px 24px; border-radius: 12px; font-weight: 700; font-size: 0.9rem; text-decoration: none; transition: transform 0.2s, box-shadow 0.2s; background: linear-gradient(135deg, #ff6b35, #ff8c42); color: white; box-shadow: 0 4px 15px rgba(255,107,53,0.4);"
               onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 8px 25px rgba(255,107,53,0.5)'"
               onmouseout="this.style.transform='translateY(0)';this.style.boxShadow='0 4px 15px rgba(255,107,53,0.4)'">
                ☕ Saweria (IDR)
                <i data-lucide="external-link" style="width: 14px;"></i>
            </a>
            <!-- PayPal -->
            <a href="<?php echo APP_PAYPAL; ?>" target="_blank"
               style="display: inline-flex; align-items: center; gap: 0.6rem; padding: 12px 24px; border-radius: 12px; font-weight: 700; font-size: 0.9rem; text-decoration: none; transition: transform 0.2s, box-shadow 0.2s; background: linear-gradient(135deg, #003087, #009cde); color: white; box-shadow: 0 4px 15px rgba(0,48,135,0.4);"
               onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 8px 25px rgba(0,48,135,0.5)'"
               onmouseout="this.style.transform='translateY(0)';this.style.boxShadow='0 4px 15px rgba(0,48,135,0.4)'">
                💳 PayPal (USD)
                <i data-lucide="external-link" style="width: 14px;"></i>
            </a>
            <!-- GitHub -->
            <a href="<?php echo APP_GITHUB; ?>" target="_blank"
               style="display: inline-flex; align-items: center; gap: 0.6rem; padding: 12px 24px; border-radius: 12px; font-weight: 700; font-size: 0.9rem; text-decoration: none; transition: transform 0.2s, box-shadow 0.2s; background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.15); color: white;"
               onmouseover="this.style.transform='translateY(-2px)';this.style.background='rgba(255,255,255,0.12)'"
               onmouseout="this.style.transform='translateY(0)';this.style.background='rgba(255,255,255,0.08)'">
                <i data-lucide="github" style="width: 16px;"></i> Star di GitHub
            </a>
        </div>
    </div>
</div>

<!-- Changelog Preview -->
<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
        <h3 style="font-size: 1rem; display: flex; align-items: center; gap: 0.5rem;">
            <i data-lucide="history" style="width: 18px; color: var(--primary);"></i> Recent Changes
        </h3>
    </div>
    <?php
    $versions = [
        ['ver' => '2.12.0', 'date' => '2026-04-08', 'changes' => ['Modul Server Assets Management (Full CRUD)', 'Automated Email Backup (CSV & TXT Summary)', 'Personalized User Backup Recipients', 'Smart CSV Restore & Data Migration Tool', 'Sidebar UI refinement & Fix undefined variables']],
        ['ver' => '2.11.2', 'date' => '2026-04-02', 'changes' => ['Memory Optimization for large subnets (/16)', 'INET_ATON-based IP statistics calculation', 'Improved rendering transitions for Mermaid.js']],
        ['ver' => '2.11.0', 'date' => '2026-04-02', 'changes' => ['Block-based Subnet Pagination for large networks', 'Global Subnet Utilization stats calculation', 'Smart Chunked Scanning for improved performance']],
        ['ver' => '2.10.0', 'date' => '2026-04-01', 'changes' => ['Manual Network Topology Manager', 'Mermaid.js Hierarchical Visualization', 'Smart Link Filtering (No Self-Link)', 'CSP Compliance (External JS Assets)']],
        ['ver' => '2.9.0', 'date' => '2026-03-31', 'changes' => ['Smart Offline Detection (Fail Counter)', 'Intensive Verification Probe (Multi-Signal)', 'Customizable Fail Threshold Settings', 'Fix Subdirectory URL Routing (.htaccess)']],
    ];
    foreach ($versions as $v): ?>
    <div style="display: flex; gap: 1.25rem; padding: 1rem 0; border-bottom: 1px solid var(--border); flex-direction: row; flex-wrap: wrap;">
        <div style="flex-shrink: 0; text-align: left; min-width: 100px;">
            <span style="display: inline-block; background: rgba(99,102,241,0.15); color: var(--primary); font-weight: 700; font-size: 0.8rem; padding: 4px 10px; border-radius: 6px; border: 1px solid rgba(99,102,241,0.3);">v<?php echo $v['ver']; ?></span>
            <span style="display: block; font-size: 0.7rem; color: var(--text-muted); margin-top: 4px; white-space: nowrap;"><?php echo date('d M Y', strtotime($v['date'])); ?></span>
        </div>
        <ul style="margin: 0; padding: 0 0 0 1rem; display: flex; flex-direction: column; gap: 4px; flex: 1; min-width: 250px;">
            <?php foreach ($v['changes'] as $c): ?>
            <li style="font-size: 0.875rem; color: var(--text-muted);"><?php echo htmlspecialchars($c); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endforeach; ?>
    <div style="text-align: center; margin-top: 1rem;">
        <a href="<?php echo APP_GITHUB; ?>/blob/main/CHANGELOG.md" target="_blank" style="font-size: 0.8rem; color: var(--primary); text-decoration: none;">View full changelog on GitHub →</a>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
