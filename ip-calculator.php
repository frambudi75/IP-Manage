<?php
require_once 'includes/config.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

function to_unsigned_long($ip) {
    $long = ip2long($ip);
    if ($long === false) {
        return false;
    }
    return sprintf('%u', $long);
}

function long_to_ip($long) {
    if ($long < 0) {
        $long = $long + 4294967296;
    }
    return long2ip((int)$long);
}

function netmask_from_prefix($prefix) {
    $prefix = (int)$prefix;
    if ($prefix < 0 || $prefix > 32) {
        return null;
    }
    if ($prefix === 0) {
        return '0.0.0.0';
    }
    $mask = (0xFFFFFFFF << (32 - $prefix)) & 0xFFFFFFFF;
    return long2ip($mask);
}

function wildcard_from_prefix($prefix) {
    $prefix = (int)$prefix;
    if ($prefix < 0 || $prefix > 32) {
        return null;
    }
    $wildcard = (~((0xFFFFFFFF << (32 - $prefix)) & 0xFFFFFFFF)) & 0xFFFFFFFF;
    return long2ip($wildcard);
}

function calculate_subnet($ip, $prefix) {
    $prefix = (int)$prefix;
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) || $prefix < 0 || $prefix > 32) {
        return null;
    }

    $ip_u = (int)to_unsigned_long($ip);
    $mask_u = $prefix === 0 ? 0 : ((0xFFFFFFFF << (32 - $prefix)) & 0xFFFFFFFF);
    $network_u = $ip_u & $mask_u;
    $broadcast_u = $network_u | (~$mask_u & 0xFFFFFFFF);

    $total_hosts = (int)pow(2, (32 - $prefix));
    if ($prefix === 31) {
        $usable_hosts = 2;
        $first_usable = long2ip($network_u);
        $last_usable = long2ip($broadcast_u);
    } elseif ($prefix === 32) {
        $usable_hosts = 1;
        $first_usable = long2ip($network_u);
        $last_usable = long2ip($network_u);
    } else {
        $usable_hosts = max(0, $total_hosts - 2);
        $first_usable = long2ip($network_u + 1);
        $last_usable = long2ip($broadcast_u - 1);
    }

    return [
        'input_ip' => $ip,
        'prefix' => $prefix,
        'netmask' => netmask_from_prefix($prefix),
        'wildcard' => wildcard_from_prefix($prefix),
        'network' => long2ip($network_u),
        'broadcast' => long2ip($broadcast_u),
        'first_usable' => $first_usable,
        'last_usable' => $last_usable,
        'total_hosts' => $total_hosts,
        'usable_hosts' => $usable_hosts
    ];
}

function split_subnets($cidr, $target_prefix) {
    $parts = explode('/', trim((string)$cidr));
    if (count($parts) !== 2) {
        return ['error' => 'Format CIDR tidak valid.'];
    }

    $base_ip = trim($parts[0]);
    $base_prefix = (int)trim($parts[1]);
    $target_prefix = (int)$target_prefix;

    if (!filter_var($base_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return ['error' => 'IP network induk tidak valid.'];
    }
    if ($base_prefix < 0 || $base_prefix > 32 || $target_prefix < 0 || $target_prefix > 32) {
        return ['error' => 'Prefix harus antara 0 sampai 32.'];
    }
    if ($target_prefix < $base_prefix) {
        return ['error' => 'Target prefix harus lebih besar atau sama dengan prefix induk.'];
    }

    $base = calculate_subnet($base_ip, $base_prefix);
    if ($base === null) {
        return ['error' => 'Gagal menghitung network induk.'];
    }

    if ($target_prefix === $base_prefix) {
        return [
            'base' => $base,
            'target_prefix' => $target_prefix,
            'subnets' => [$base]
        ];
    }

    $base_network_u = (int)sprintf('%u', ip2long($base['network']));
    $subnet_size = (int)pow(2, 32 - $target_prefix);
    $count = (int)pow(2, $target_prefix - $base_prefix);
    if ($count > 4096) {
        return ['error' => 'Hasil subnet terlalu banyak. Gunakan target prefix yang lebih kecil.'];
    }

    $result = [];
    for ($i = 0; $i < $count; $i++) {
        $subnet_network_u = $base_network_u + ($i * $subnet_size);
        $subnet_ip = long2ip($subnet_network_u);
        $detail = calculate_subnet($subnet_ip, $target_prefix);
        if ($detail !== null) {
            $result[] = $detail;
        }
    }

    return [
        'base' => $base,
        'target_prefix' => $target_prefix,
        'subnets' => $result
    ];
}

$page_title = 'IP Calculator';
$error = '';
$result = null;
$input_ip = '';
$input_prefix = 24;
$input_cidr = '';
$split_error = '';
$split_result = null;
$split_input_cidr = '';
$split_target_prefix = 26;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mode = $_POST['mode'] ?? 'calc';

    if ($mode === 'split') {
        $split_input_cidr = trim($_POST['split_cidr'] ?? '');
        $split_target_prefix = (int)($_POST['split_target_prefix'] ?? 26);
        $split_result = split_subnets($split_input_cidr, $split_target_prefix);
        if (isset($split_result['error'])) {
            $split_error = $split_result['error'];
            $split_result = null;
        }
    } else {
        $input_cidr = trim($_POST['cidr'] ?? '');
        $input_ip = trim($_POST['ip_addr'] ?? '');
        $input_prefix = (int)($_POST['prefix'] ?? 24);

        if ($input_cidr !== '') {
            $parts = explode('/', $input_cidr);
            if (count($parts) === 2) {
                $input_ip = trim($parts[0]);
                $input_prefix = (int)trim($parts[1]);
            } else {
                $error = 'Format CIDR tidak valid. Gunakan contoh: 192.168.1.10/24';
            }
        }

        if ($error === '') {
            $result = calculate_subnet($input_ip, $input_prefix);
            if ($result === null) {
                $error = 'Input IP atau prefix tidak valid.';
            }
        }
    }
}

include 'includes/header.php';
?>

<div style="display: grid; grid-template-columns: 1.1fr 1.9fr; gap: 1.5rem;">
    <div class="card">
        <h3 style="font-size: 1.125rem; margin-bottom: 1rem; display: flex; align-items: center; gap: 8px;">
            <i data-lucide="calculator" style="width: 18px;"></i> Input
        </h3>
        <form method="POST">
            <input type="hidden" name="mode" value="calc">
            <div class="input-group">
                <label>CIDR (opsional)</label>
                <input type="text" class="input-control" name="cidr" placeholder="192.168.1.10/24" value="<?php echo htmlspecialchars($input_cidr); ?>">
            </div>
            <div style="text-align: center; color: var(--text-muted); margin-bottom: 0.75rem;">atau</div>
            <div class="input-group">
                <label>IP Address</label>
                <input type="text" class="input-control" name="ip_addr" placeholder="192.168.1.10" value="<?php echo htmlspecialchars($input_ip); ?>">
            </div>
            <div class="input-group">
                <label>Prefix</label>
                <select class="input-control" name="prefix" style="appearance: none;">
                    <?php for ($p = 0; $p <= 32; $p++): ?>
                        <option value="<?php echo $p; ?>" <?php echo $p === (int)$input_prefix ? 'selected' : ''; ?>>/<?php echo $p; ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <?php if ($error !== ''): ?>
                <div style="padding: 0.8rem 1rem; border: 1px solid rgba(239,68,68,0.4); background: rgba(239,68,68,0.08); border-radius: 8px; color: #fecaca; margin-bottom: 1rem;">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center;">
                <i data-lucide="play"></i> Calculate
            </button>
        </form>
    </div>

    <div class="card">
        <h3 style="font-size: 1.125rem; margin-bottom: 1rem; display: flex; align-items: center; gap: 8px;">
            <i data-lucide="network" style="width: 18px;"></i> Result
        </h3>

        <?php if (!$result): ?>
            <div style="padding: 1.25rem; border: 1px dashed var(--border); border-radius: 10px; color: var(--text-muted);">
                Masukkan CIDR atau IP + prefix untuk menghitung detail subnet.
            </div>
        <?php else: ?>
            <div style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.85rem;">
                <div style="padding: 0.8rem; background: var(--surface-light); border-radius: 8px;">
                    <div style="font-size: 0.75rem; color: var(--text-muted);">CIDR</div>
                    <div style="font-family: monospace; font-weight: 600;"><?php echo htmlspecialchars($result['input_ip']); ?>/<?php echo (int)$result['prefix']; ?></div>
                </div>
                <div style="padding: 0.8rem; background: var(--surface-light); border-radius: 8px;">
                    <div style="font-size: 0.75rem; color: var(--text-muted);">Netmask</div>
                    <div style="font-family: monospace; font-weight: 600;"><?php echo htmlspecialchars($result['netmask']); ?></div>
                </div>
                <div style="padding: 0.8rem; background: var(--surface-light); border-radius: 8px;">
                    <div style="font-size: 0.75rem; color: var(--text-muted);">Wildcard</div>
                    <div style="font-family: monospace; font-weight: 600;"><?php echo htmlspecialchars($result['wildcard']); ?></div>
                </div>
                <div style="padding: 0.8rem; background: var(--surface-light); border-radius: 8px;">
                    <div style="font-size: 0.75rem; color: var(--text-muted);">Total Hosts</div>
                    <div style="font-weight: 700;"><?php echo number_format((int)$result['total_hosts']); ?></div>
                </div>
                <div style="padding: 0.8rem; background: var(--surface-light); border-radius: 8px;">
                    <div style="font-size: 0.75rem; color: var(--text-muted);">Usable Hosts</div>
                    <div style="font-weight: 700; color: var(--success);"><?php echo number_format((int)$result['usable_hosts']); ?></div>
                </div>
                <div style="padding: 0.8rem; background: var(--surface-light); border-radius: 8px;">
                    <div style="font-size: 0.75rem; color: var(--text-muted);">Network Address</div>
                    <div style="font-family: monospace; font-weight: 600;"><?php echo htmlspecialchars($result['network']); ?></div>
                </div>
                <div style="padding: 0.8rem; background: var(--surface-light); border-radius: 8px;">
                    <div style="font-size: 0.75rem; color: var(--text-muted);">First Usable</div>
                    <div style="font-family: monospace; font-weight: 600;"><?php echo htmlspecialchars($result['first_usable']); ?></div>
                </div>
                <div style="padding: 0.8rem; background: var(--surface-light); border-radius: 8px;">
                    <div style="font-size: 0.75rem; color: var(--text-muted);">Last Usable</div>
                    <div style="font-family: monospace; font-weight: 600;"><?php echo htmlspecialchars($result['last_usable']); ?></div>
                </div>
            </div>

            <div style="padding: 0.8rem; background: rgba(59,130,246,0.08); border: 1px dashed rgba(59,130,246,0.4); border-radius: 8px; margin-top: 0.9rem;">
                <div style="font-size: 0.75rem; color: var(--text-muted);">Broadcast Address</div>
                <div style="font-family: monospace; font-weight: 600;"><?php echo htmlspecialchars($result['broadcast']); ?></div>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card" style="margin-top: 1.5rem;">
    <h3 style="font-size: 1.125rem; margin-bottom: 1rem; display: flex; align-items: center; gap: 8px;">
        <i data-lucide="split" style="width: 18px;"></i> Subnet Splitter (VLSM Ringan)
    </h3>
    <form method="POST" style="display: grid; grid-template-columns: 1.5fr 1fr auto; gap: 0.75rem; align-items: end; margin-bottom: 1rem;">
        <input type="hidden" name="mode" value="split">
        <div class="input-group" style="margin-bottom: 0;">
            <label>Network Induk (CIDR)</label>
            <input type="text" class="input-control" name="split_cidr" placeholder="10.10.0.0/24" value="<?php echo htmlspecialchars($split_input_cidr); ?>">
        </div>
        <div class="input-group" style="margin-bottom: 0;">
            <label>Target Prefix</label>
            <select class="input-control" name="split_target_prefix" style="appearance: none;">
                <?php for ($p = 0; $p <= 32; $p++): ?>
                    <option value="<?php echo $p; ?>" <?php echo $p === (int)$split_target_prefix ? 'selected' : ''; ?>>/<?php echo $p; ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary" style="height: 44px;">
            <i data-lucide="wand-sparkles"></i> Split
        </button>
    </form>

    <?php if ($split_error !== ''): ?>
        <div style="padding: 0.8rem 1rem; border: 1px solid rgba(239,68,68,0.4); background: rgba(239,68,68,0.08); border-radius: 8px; color: #fecaca;">
            <?php echo htmlspecialchars($split_error); ?>
        </div>
    <?php elseif ($split_result): ?>
        <div style="margin-bottom: 0.75rem; color: var(--text-muted); font-size: 0.85rem;">
            <?php echo htmlspecialchars($split_result['base']['network']); ?>/<?php echo (int)$split_result['base']['prefix']; ?>
            dipecah menjadi
            <strong><?php echo count($split_result['subnets']); ?></strong>
            subnet dengan prefix
            <strong>/<?php echo (int)$split_result['target_prefix']; ?></strong>.
        </div>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--border);">
                        <th style="padding: 0.7rem; text-align: left; color: var(--text-muted);">Subnet</th>
                        <th style="padding: 0.7rem; text-align: left; color: var(--text-muted);">Network</th>
                        <th style="padding: 0.7rem; text-align: left; color: var(--text-muted);">First</th>
                        <th style="padding: 0.7rem; text-align: left; color: var(--text-muted);">Last</th>
                        <th style="padding: 0.7rem; text-align: left; color: var(--text-muted);">Broadcast</th>
                        <th style="padding: 0.7rem; text-align: right; color: var(--text-muted);">Usable</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($split_result['subnets'] as $idx => $sub): ?>
                        <tr style="border-bottom: 1px solid var(--border);">
                            <td style="padding: 0.7rem; font-family: monospace;">#<?php echo $idx + 1; ?> /<?php echo (int)$sub['prefix']; ?></td>
                            <td style="padding: 0.7rem; font-family: monospace;"><?php echo htmlspecialchars($sub['network']); ?></td>
                            <td style="padding: 0.7rem; font-family: monospace;"><?php echo htmlspecialchars($sub['first_usable']); ?></td>
                            <td style="padding: 0.7rem; font-family: monospace;"><?php echo htmlspecialchars($sub['last_usable']); ?></td>
                            <td style="padding: 0.7rem; font-family: monospace;"><?php echo htmlspecialchars($sub['broadcast']); ?></td>
                            <td style="padding: 0.7rem; text-align: right;"><?php echo number_format((int)$sub['usable_hosts']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div style="padding: 0.9rem; border: 1px dashed var(--border); border-radius: 8px; color: var(--text-muted);">
            Contoh: pecah `10.10.0.0/24` ke target `/26` untuk mendapatkan 4 subnet.
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
