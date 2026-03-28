<aside class="sidebar">
    <div class="sidebar-logo mb-4" style="display: flex; align-items: center; gap: 10px;">
        <div style="background: var(--primary); padding: 8px; border-radius: 8px;">
            <i data-lucide="network" style="color: white;"></i>
        </div>
        <h2 style="font-size: 1.25rem; font-weight: 700; color: var(--primary);">IPManager <span style="color: white;">Pro</span></h2>
    </div>
    
    <nav class="sidebar-nav">
        <p style="text-transform: uppercase; font-size: 0.75rem; color: var(--text-muted); margin-bottom: 1rem; letter-spacing: 1px;">Menu</p>
        <ul style="display: flex; flex-direction: column; gap: 0.5rem;">
            <li>
                <a href="index.php" class="btn" style="width: 100%; justify-content: flex-start; background: <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'var(--surface-light)' : 'transparent'; ?>">
                    <i data-lucide="layout-dashboard"></i> Dashboard
                </a>
            </li>
            <li>
                <a href="subnets.php" class="btn" style="width: 100%; justify-content: flex-start; background: <?php echo basename($_SERVER['PHP_SELF']) == 'subnets.php' ? 'var(--surface-light)' : 'transparent'; ?>">
                    <i data-lucide="layers"></i> Subnets
                </a>
            </li>
            <li>
                <a href="vlans.php" class="btn" style="width: 100%; justify-content: flex-start; background: <?php echo basename($_SERVER['PHP_SELF']) == 'vlans.php' ? 'var(--surface-light)' : 'transparent'; ?>">
                    <i data-lucide="vibrate"></i> VLANs
                </a>
            </li>
            <li>
                <a href="devices.php" class="btn" style="width: 100%; justify-content: flex-start;">
                    <i data-lucide="server"></i> Devices
                </a>
            </li>
            <li>
                <a href="ip-calculator.php" class="btn" style="width: 100%; justify-content: flex-start; background: <?php echo basename($_SERVER['PHP_SELF']) == 'ip-calculator.php' ? 'var(--surface-light)' : 'transparent'; ?>">
                    <i data-lucide="calculator"></i> IP Calculator
                </a>
            </li>
        </ul>

        <?php if (is_admin()): ?>
        <p style="text-transform: uppercase; font-size: 0.75rem; color: var(--text-muted); margin: 2rem 0 1rem; letter-spacing: 1px;">Admin</p>
        <ul style="display: flex; flex-direction: column; gap: 0.5rem;">
            <li>
                <a href="users.php" class="btn" style="width: 100%; justify-content: flex-start; background: <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'var(--surface-light)' : 'transparent'; ?>">
                    <i data-lucide="users"></i> User Management
                </a>
            </li>
            <li>
                <a href="settings.php" class="btn" style="width: 100%; justify-content: flex-start; background: <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'var(--surface-light)' : 'transparent'; ?>">
                    <i data-lucide="settings"></i> System Settings
                </a>
            </li>
        <?php endif; ?>
        <ul style="display: flex; flex-direction: column; gap: 0.5rem; <?php echo !is_admin() ? 'margin-top: 2rem;' : ''; ?>">
            <li>
                <a href="change-password.php" class="btn" style="width: 100%; justify-content: flex-start;">
                    <i data-lucide="key"></i> Change Password
                </a>
            </li>
            <li>
                <a href="logout.php" class="btn" style="width: 100%; justify-content: flex-start; color: var(--danger);">
                    <i data-lucide="log-out"></i> Logout
                </a>
            </li>
        </ul>
    </nav>
</aside>
