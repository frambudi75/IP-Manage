<?php
/**
 * IPManager Pro - Switch Details
 * Displays full hardware info and port-to-IP mapping for a specific switch.
 */

require_once 'includes/config.php';
require_once 'includes/db.php';

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$db = get_db_connection();
$id = (int)($_GET['id'] ?? 0);

// Fetch switch data
$stmt = $db->prepare("SELECT * FROM switches WHERE id = ?");
$stmt->execute([$id]);
$switch = $stmt->fetch();

if (!$switch) {
    header('Location: switches.php');
    exit;
}

// Fetch port mapping joined with IP addresses
$query = "
    SELECT 
        m.mac_addr, 
        m.port_name, 
        m.updated_at as last_seen_on_port,
        ip.ip_addr,
        ip.hostname,
        ip.vendor
    FROM switch_port_map m
    LEFT JOIN ip_addresses ip ON m.mac_addr = ip.mac_addr
    WHERE m.switch_id = ?
    ORDER BY m.port_name ASC, m.mac_addr ASC
";
$stmt = $db->prepare($query);
$stmt->execute([$id]);
$ports = $stmt->fetchAll();

$page_title = "Switch: " . $switch['name'];
include 'includes/header.php';
?>

<div style="margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: flex-end;">
    <div>
        <nav style="font-size: 0.875rem; color: var(--text-muted); margin-bottom: 0.5rem;">
            <a href="switches.php" style="color: var(--primary); text-decoration: none;">Switches</a> / <?php echo htmlspecialchars($switch['name']); ?>
        </nav>
        <h1 style="font-size: 1.75rem;"><?php echo htmlspecialchars($switch['name']); ?></h1>
        <p style="color: var(--text-muted); font-family: monospace;"><?php echo htmlspecialchars($switch['ip_addr']); ?></p>
    </div>
    <div class="header-actions" style="display: flex; gap: 0.75rem;">
        <button class="btn" onclick="location.href='cron_switch_poll.php?id=<?php echo $id; ?>'" style="background: var(--surface-light);">
            <i data-lucide="refresh-cw" style="width: 16px;"></i> Force Poll
        </button>
        <button class="btn btn-primary" onclick="window.print()">
            <i data-lucide="printer" style="width: 16px;"></i> Export Report
        </button>
    </div>
</div>

<div class="grid-side-detail">
    <!-- Hardware Status Sidebar -->
    <div class="card">
        <h3 style="font-size: 1rem; margin-bottom: 1.5rem; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem; display: flex; justify-content: space-between; align-items: center;">
            Hardware Health
            <span id="live-badge" style="font-size: 0.65rem; font-weight: 700; padding: 2px 8px; border-radius: 20px; background: var(--border); color: var(--text-muted); letter-spacing: 0.5px;">LOADING...</span>
        </h3>
        
        <div style="margin-bottom: 1.5rem;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem; font-size: 0.875rem;">
                <span>CPU Load</span>
                <span id="cpu-val" style="font-weight: 700;"><?php echo (int)($switch['cpu_usage'] ?? 0); ?>%</span>
            </div>
            <div style="height: 10px; background: var(--border); border-radius: 5px; overflow: hidden;">
                <div id="cpu-bar" style="width: <?php echo (int)($switch['cpu_usage'] ?? 0); ?>%; height: 100%; background: var(--primary); transition: width 0.8s ease, background 0.5s ease;"></div>
            </div>
        </div>

        <div style="margin-bottom: 2rem;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem; font-size: 0.875rem;">
                <span>Memory Usage</span>
                <span id="mem-val" style="font-weight: 700;"><?php echo (int)($switch['memory_usage'] ?? 0); ?>%</span>
            </div>
            <div style="height: 10px; background: var(--border); border-radius: 5px; overflow: hidden;">
                <div id="mem-bar" style="width: <?php echo (int)($switch['memory_usage'] ?? 0); ?>%; height: 100%; background: var(--success); transition: width 0.8s ease, background 0.5s ease;"></div>
            </div>
        </div>

        <div style="display: flex; flex-direction: column; gap: 1rem; font-size: 0.875rem;">
            <div style="display: flex; justify-content: space-between;">
                <span style="color:var(--text-muted)">Model:</span>
                <span style="font-weight: 600;"><?php echo htmlspecialchars($switch['model'] ?? 'Unknown'); ?></span>
            </div>
            <div style="display: flex; justify-content: space-between;">
                <span style="color:var(--text-muted)">Uptime:</span>
                <span style="font-weight: 600;"><?php echo htmlspecialchars($switch['uptime'] ?? '-'); ?></span>
            </div>
            <div style="display: flex; justify-content: space-between;">
                <span style="color:var(--text-muted)">Last Updated:</span>
                <span id="last-poll-val" style="font-weight: 600;"><?php echo $switch['last_poll'] ? date('H:i:s, d M', strtotime($switch['last_poll'])) : 'Never'; ?></span>
            </div>
        </div>

        <?php if (!empty($switch['system_info'])): ?>
            <div style="margin-top: 2rem;">
                <h4 style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); letter-spacing: 1px; margin-bottom: 0.5rem;">System Information</h4>
                <div style="font-size: 0.75rem; color: var(--text-muted); background: var(--surface-light); padding: 1rem; border-radius: 8px; line-height: 1.5; white-space: pre-wrap;"><?php echo htmlspecialchars($switch['system_info']); ?></div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Port Mapping Table -->
    <div class="card">
        <h3 style="font-size: 1rem; margin-bottom: 1.5rem; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem;">Device Port Mapping</h3>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--border); text-align: left;">
                        <th style="padding: 1rem; color: var(--text-muted); font-size: 0.8rem;">Interface</th>
                        <th style="padding: 1rem; color: var(--text-muted); font-size: 0.8rem;">MAC Address</th>
                        <th style="padding: 1rem; color: var(--text-muted); font-size: 0.8rem;">Mapped IP</th>
                        <th style="padding: 1rem; color: var(--text-muted); font-size: 0.8rem;">Hostname / Vendor</th>
                        <th style="padding: 1rem; color: var(--text-muted); font-size: 0.8rem; text-align: right;">Seen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($ports)): ?>
                        <tr>
                            <td colspan="5" style="padding: 2rem; text-align: center; color: var(--text-muted);">No devices discovered on this switch yet. Run a poll to start.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($ports as $port): ?>
                            <tr style="border-bottom: 1px solid var(--border);">
                                <td style="padding: 1rem; font-weight: 700; color: var(--primary);">
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <i data-lucide="zap" style="width: 14px;"></i>
                                        <?php echo htmlspecialchars($port['port_name']); ?>
                                    </div>
                                </td>
                                <td style="padding: 1rem; font-family: monospace; font-size: 0.85rem;">
                                    <?php echo $port['mac_addr']; ?>
                                </td>
                                <td style="padding: 1rem;">
                                    <?php if ($port['ip_addr']): ?>
                                        <a href="devices.php?search=<?php echo urlencode($port['ip_addr']); ?>" style="color: var(--text); text-decoration: none; font-weight: 600; border-bottom: 1px dashed var(--primary);">
                                            <?php echo $port['ip_addr']; ?>
                                        </a>
                                    <?php else: ?>
                                        <span style="opacity: 0.3; font-size: 0.75rem;">Not in IPAM</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 1rem;">
                                    <div style="font-size: 0.875rem; font-weight: 600;"><?php echo htmlspecialchars($port['hostname'] ?: '-'); ?></div>
                                    <div style="font-size: 0.75rem; color: var(--text-muted);"><?php echo htmlspecialchars($port['vendor'] ?: ''); ?></div>
                                </td>
                                <td style="padding: 1rem; text-align: right; font-size: 0.75rem; color: var(--text-muted);">
                                    <?php echo date('H:i', strtotime($port['last_seen_on_port'])); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- History Charts Section -->
<div style="margin-top: 2rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
        <h2 style="font-size: 1.25rem;">📈 Performance History</h2>
        <div style="display: flex; gap: 0.5rem;" id="history-btns">
            <?php foreach ([1, 6, 24, 48] as $h): ?>
            <button onclick="loadHistory(<?php echo $h; ?>)"
                    id="btn-h<?php echo $h; ?>"
                    style="padding: 4px 12px; border-radius: 6px; font-size: 0.8rem; cursor: pointer;
                           background: <?php echo $h == 6 ? 'var(--primary)' : 'var(--surface-light)'; ?>;
                           color: <?php echo $h == 6 ? '#fff' : 'var(--text-muted)'; ?>;
                           border: 1px solid var(--border);">
                <?php echo $h; ?>h
            </button>
            <?php endforeach; ?>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
        <div class="card">
            <h3 style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 1rem; text-transform: uppercase; letter-spacing: 0.5px;">CPU Load History</h3>
            <div style="position: relative; height: 200px;"><canvas id="cpuChart"></canvas></div>
        </div>
        <div class="card">
            <h3 style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 1rem; text-transform: uppercase; letter-spacing: 0.5px;">Memory Usage History</h3>
            <div style="position: relative; height: 200px;"><canvas id="memChart"></canvas></div>
        </div>
    </div>

    <div class="card">
        <h3 style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 1.5rem; text-transform: uppercase; letter-spacing: 0.5px;">Period Summary</h3>
        <div style="display: flex; gap: 3rem; flex-wrap: wrap;">
            <div><div style="font-size: 2rem; font-weight: 800; color: var(--primary);" id="stat-ports">—</div><div style="font-size: 0.8rem; color: var(--text-muted);">Active Interfaces</div></div>
            <div><div style="font-size: 2rem; font-weight: 800; color: var(--success);" id="stat-devices">—</div><div style="font-size: 0.8rem; color: var(--text-muted);">Mapped Devices</div></div>
            <div><div style="font-size: 2rem; font-weight: 800; color: var(--warning);" id="stat-avg-cpu">—</div><div style="font-size: 0.8rem; color: var(--text-muted);">Avg CPU</div></div>
            <div><div style="font-size: 2rem; font-weight: 800; color: var(--danger);" id="stat-peak-cpu">—</div><div style="font-size: 0.8rem; color: var(--text-muted);">Peak CPU</div></div>
        </div>
    </div>
</div>

<script>
(function() {
    const switchId = <?php echo $id; ?>;
    const badge    = document.getElementById('live-badge');
    const cpuVal   = document.getElementById('cpu-val');
    const cpuBar   = document.getElementById('cpu-bar');
    const memVal   = document.getElementById('mem-val');
    const memBar   = document.getElementById('mem-bar');
    const lastPoll = document.getElementById('last-poll-val');

    function setBar(bar, val, dangerThreshold, dangerColor, normalColor) {
        bar.style.width = val + '%';
        bar.style.background = val > dangerThreshold ? dangerColor : normalColor;
    }

    if (typeof EventSource === 'undefined') {
        badge.textContent = 'NO SSE';
        return;
    }

    // Open SSE connection to our stream endpoint
    const es = new EventSource('api/switch-health-stream.php?id=' + switchId);

    es.onopen = function() {
        badge.textContent = 'LIVE';
        badge.style.background = 'rgba(34,197,94,0.15)';
        badge.style.color = 'var(--success, #22c55e)';
    };

    es.onmessage = function(e) {
        try {
            const d = JSON.parse(e.data);

            // Update CPU
            cpuVal.textContent = d.cpu + '%';
            setBar(cpuBar, d.cpu, 80, 'var(--danger, #ef4444)', 'var(--primary, #6366f1)');

            // Update Memory
            memVal.textContent = d.mem + '%';
            setBar(memBar, d.mem, 90, 'var(--danger, #ef4444)', 'var(--success, #22c55e)');

            // Update last poll time
            lastPoll.textContent = d.last_poll;

            // Pulse animation on update
            [cpuVal, memVal].forEach(el => {
                el.style.opacity = '0.4';
                setTimeout(() => el.style.opacity = '1', 200);
            });
        } catch(err) { console.warn('SSE parse error', err); }
    };

    es.onerror = function() {
        badge.textContent = 'OFFLINE';
        badge.style.background = 'rgba(239,68,68,0.15)';
        badge.style.color = 'var(--danger, #ef4444)';
    };

    // Close SSE when user leaves the page
    window.addEventListener('beforeunload', () => es.close());
})();

// ── History Charts ──────────────────────────────────────────────────────────
</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function() {
    const SWITCH_ID = <?php echo $id; ?>;

    const chartDefaults = {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: { display: false },
            tooltip: { backgroundColor: 'rgba(15,15,25,0.9)', titleColor: '#aaa', bodyColor: '#fff', padding: 10 }
        },
        scales: {
            x: { ticks: { color: '#888', maxTicksLimit: 8, font: { size: 11 } }, grid: { color: 'rgba(255,255,255,0.05)' } },
            y: { min: 0, max: 100, ticks: { color: '#888', callback: v => v + '%', font: { size: 11 } }, grid: { color: 'rgba(255,255,255,0.05)' } }
        }
    };

    function makeGradient(ctx, r, g, b) {
        const grad = ctx.chart.ctx.createLinearGradient(0, 0, 0, 200);
        grad.addColorStop(0,   `rgba(${r},${g},${b},0.4)`);
        grad.addColorStop(1,   `rgba(${r},${g},${b},0)`);
        return grad;
    }

    const cpuCtx = document.getElementById('cpuChart').getContext('2d');
    const memCtx = document.getElementById('memChart').getContext('2d');

    const cpuChart = new Chart(cpuCtx, {
        type: 'line',
        data: { labels: [], datasets: [{ label: 'CPU %', data: [], borderColor: '#6366f1', borderWidth: 2, pointRadius: 0, fill: true, backgroundColor: (ctx) => makeGradient(ctx, 99, 102, 241), tension: 0.4 }] },
        options: JSON.parse(JSON.stringify(chartDefaults))
    });

    const memChart = new Chart(memCtx, {
        type: 'line',
        data: { labels: [], datasets: [{ label: 'RAM %', data: [], borderColor: '#22c55e', borderWidth: 2, pointRadius: 0, fill: true, backgroundColor: (ctx) => makeGradient(ctx, 34, 197, 94), tension: 0.4 }] },
        options: JSON.parse(JSON.stringify(chartDefaults))
    });

    let activeHours = 6;

    window.loadHistory = function(hours) {
        activeHours = hours;
        // Update button styles
        [1,6,24,48].forEach(h => {
            const btn = document.getElementById('btn-h' + h);
            if (!btn) return;
            btn.style.background = (h === hours) ? 'var(--primary)' : 'var(--surface-light)';
            btn.style.color      = (h === hours) ? '#fff' : 'var(--text-muted)';
        });

        fetch(`api/switch-history.php?id=${SWITCH_ID}&hours=${hours}`)
            .then(r => r.json())
            .then(d => {
                // Update charts
                cpuChart.data.labels   = d.labels;
                cpuChart.data.datasets[0].data = d.cpu;
                cpuChart.update('active');

                memChart.data.labels   = d.labels;
                memChart.data.datasets[0].data = d.mem;
                memChart.update('active');

                // Update summary stats
                document.getElementById('stat-ports').textContent   = d.port_count   || '0';
                document.getElementById('stat-devices').textContent = d.device_count || '0';

                if (d.cpu.length > 0) {
                    const avg  = Math.round(d.cpu.reduce((a,b) => a+b, 0) / d.cpu.length);
                    const peak = Math.max(...d.cpu);
                    document.getElementById('stat-avg-cpu').textContent  = avg  + '%';
                    document.getElementById('stat-peak-cpu').textContent = peak + '%';
                } else {
                    document.getElementById('stat-avg-cpu').textContent  = 'N/A';
                    document.getElementById('stat-peak-cpu').textContent = 'N/A';
                }
            })
            .catch(() => {
                document.getElementById('stat-avg-cpu').textContent = 'Error';
            });
    };

    // Initial load
    loadHistory(6);
})();
</script>

<?php include 'includes/footer.php'; ?>
