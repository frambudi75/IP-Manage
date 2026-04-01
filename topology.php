<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/network.php';

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login');
    exit;
}

$db = get_db_connection();
$page_title = "Network Topology Map";

// Fetch switches 
$switches = $db->query("SELECT id, name, ip_addr FROM switches ORDER BY name ASC")->fetchAll();

// Fetch subnets
$subnets = $db->query("
    SELECT s.id, s.subnet, s.mask, s.vlan_id, v.number as vlan_number, v.name as vlan_name 
    FROM subnets s 
    LEFT JOIN vlans v ON s.vlan_id = v.id 
    ORDER BY v.number ASC, s.subnet ASC
")->fetchAll();

// Fetch Manual Links from the manager
$manual_links = $db->query("SELECT * FROM topology_links")->fetchAll();

include 'includes/header.php';

// Prepare Mermaid Diagram Code
$mermaid_logic = "graph TD\n";
$mermaid_logic .= "    subgraph \"Infrastructure Hierarchy\"\n";

// 1. Initial VLAN Rendering
$vlan_data = [];
foreach ($subnets as $s) {
    if ($s['vlan_id']) {
        $vlan_data[$s['vlan_id']] = ['num' => $s['vlan_number'], 'name' => $s['vlan_name']];
    }
}
foreach ($vlan_data as $v_id => $v_info) {
    $mermaid_logic .= "    VLAN_" . $v_id . "(\"VLAN " . $v_info['num'] . "<br/><small>" . addslashes($v_info['name'] ?? '') . "</small>\"):::vlan\n";
}

// 2. Render Switches
foreach ($switches as $sw) {
    $sw_node = "SW_" . $sw['id'];
    $mermaid_logic .= "    " . $sw_node . "[\"&nbsp;&nbsp; " . addslashes($sw['name']) . "&nbsp;&nbsp;\"]:::switch\n";
}

// 3. Render Subnets
foreach ($subnets as $sub) {
    $s_node = "SUB_" . $sub['id'];
    $mermaid_logic .= "    " . $s_node . "[\"" . $sub['subnet'] . "/" . $sub['mask'] . "\"]:::subnet\n";
}

// 4. Draw Links with Hierarchy
$rendered_sw_vlan_links = [];
$rendered_vlan_sub_links = [];

foreach ($manual_links as $link) {
    $source = "SW_" . $link['parent_switch_id'];
    
    if ($link['target_type'] == 'switch') {
        $mermaid_logic .= "    " . $source . " ==> SW_" . $link['target_id'] . "\n";
    } else {
        $sub_id = $link['target_id'];
        $target_sub = null;
        foreach ($subnets as $s) if ($s['id'] == $sub_id) { $target_sub = $s; break; }

        if ($target_sub && $target_sub['vlan_id']) {
            $v_id = $target_sub['vlan_id'];
            if (!isset($rendered_sw_vlan_links[$source . "_" . $v_id])) {
                $mermaid_logic .= "    " . $source . " --- VLAN_" . $v_id . "\n";
                $rendered_sw_vlan_links[$source . "_" . $v_id] = true;
            }
            $mermaid_logic .= "    VLAN_" . $v_id . " --- SUB_" . $sub_id . "\n";
            $rendered_vlan_sub_links[$v_id . "_" . $sub_id] = true;
        } else {
            $mermaid_logic .= "    " . $source . " --- SUB_" . $sub_id . "\n";
        }
    }
}

// 5. Fallback for unlinked subnets to their VLANs
foreach ($subnets as $sub) {
    if ($sub['vlan_id'] && !isset($rendered_vlan_sub_links[$sub['vlan_id'] . "_" . $sub['id']])) {
        $mermaid_logic .= "    VLAN_" . $sub['vlan_id'] . " --- SUB_" . $sub['id'] . "\n";
    }
}

$mermaid_logic .= "    end\n\n";

// 6. Click interactions
foreach($switches as $sw) {
    $mermaid_logic .= "    click SW_" . $sw['id'] . " \"switch-details?id=" . $sw['id'] . "\" \"View details of " . addslashes($sw['name']) . "\"\n";
}
foreach($subnets as $sub) {
    $mermaid_logic .= "    click SUB_" . $sub['id'] . " \"subnet-details?id=" . $sub['id'] . "\" \"View details of " . $sub['subnet'] . "\"\n";
}

// 7. Styling
$mermaid_logic .= "\n    classDef switch fill:#3b82f6,stroke:#2563eb,stroke-width:2px,color:#fff;\n";
$mermaid_logic .= "    classDef vlan fill:#10b981,stroke:#059669,stroke-width:2px,color:#fff;\n";
$mermaid_logic .= "    classDef subnet fill:#f59e0b,stroke:#d97706,stroke-width:2px,color:#fff;\n";
?>

<div class="page-header" style="margin-bottom: 2rem;">
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 0.5rem;">Network Topology Map</h1>
        <p class="text-muted">Interactive view showing manually defined connections from the Link Manager.</p>
    </div>
</div>

<div class="card" style="min-height: 600px; background: var(--surface); position: relative; display: flex; flex-direction: column; align-items: center; justify-content: center;">
    
    <!-- Legend -->
    <div style="position: absolute; top: 1rem; right: 1.5rem; display: flex; gap: 1.5rem; font-size: 0.75rem; background: rgba(0,0,0,0.2); padding: 8px 15px; border-radius: 20px; border: 1px solid var(--border);">
        <div style="display: flex; align-items: center; gap: 8px;"><div style="width:12px; height:12px; background:#3b82f6; border-radius:3px;"></div> Switch (L2/L3)</div>
        <div style="display: flex; align-items: center; gap: 8px;"><div style="width:12px; height:12px; background:#10b981; border-radius:3px;"></div> VLAN Group</div>
        <div style="display: flex; align-items: center; gap: 8px;"><div style="width:12px; height:12px; background:#f59e0b; border-radius:3px;"></div> Subnet / IP Block</div>
    </div>

    <!-- Loading State -->
    <div id="topo-loader" style="display: flex; flex-direction: column; align-items: center; gap: 1rem;">
        <div class="spinner-blue" style="width: 48px; height: 48px; border: 4px solid rgba(59, 130, 246, 0.1); border-top-color: var(--primary); border-radius: 50%; animation: spin 0.8s linear infinite;"></div>
        <span class="text-muted" style="letter-spacing: 1px; font-size: 0.9rem;">RENDERING TOPOLOGY...</span>
    </div>

    <!-- The Diagram -->
    <div id="topo-container" style="width: 100%; height: 100%; visibility: hidden; opacity: 0; transition: opacity 0.5s ease; padding: 2rem;">
        <div class="mermaid" style="text-align: center;">
            <?php echo $mermaid_logic; ?>
        </div>
    </div>
</div>

<style>
    @keyframes spin { to { transform: rotate(360deg); } }
    .mermaid { background: transparent !important; }
    .mermaid svg { max-width: 100%; height: auto; }
</style>

<script src="https://cdn.jsdelivr.net/npm/mermaid@10/dist/mermaid.min.js"></script>
<script>
    mermaid.initialize({
        startOnLoad: true,
        theme: 'dark',
        securityLevel: 'loose',
        flowchart: { useMaxWidth: true, htmlLabels: true, curve: 'basis' }
    });

    document.addEventListener('DOMContentLoaded', () => {
        // Wait for rendering to complete
        setTimeout(() => {
            document.getElementById('topo-loader').style.display = 'none';
            const container = document.getElementById('topo-container');
            container.style.visibility = 'visible';
            container.style.opacity = '1';
        }, 1200);
    });
</script>

<?php include 'includes/footer.php'; ?>
