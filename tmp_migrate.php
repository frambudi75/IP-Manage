<?php
require_once 'includes/db.php';
$db = get_db_connection();

echo "Running migrations...\n";

try {
    // 1. IP Addresses updates
    $db->exec("ALTER TABLE ip_addresses ADD COLUMN IF NOT EXISTS asset_tag VARCHAR(100) DEFAULT NULL");
    $db->exec("ALTER TABLE ip_addresses ADD COLUMN IF NOT EXISTS owner VARCHAR(100) DEFAULT NULL");
    echo "✔ ip_addresses table updated.\n";

    // 2. Subnets updates
    $db->exec("ALTER TABLE subnets ADD COLUMN IF NOT EXISTS utilization_threshold INT DEFAULT NULL");
    echo "✔ subnets table updated.\n";

    // 3. Settings update
    $stmt = $db->prepare("INSERT INTO settings (`key`, `value`) VALUES ('subnet_limit_threshold', '80') ON DUPLICATE KEY UPDATE `value`=`value` ");
    $stmt->execute();
    echo "✔ settings table updated.\n";

    echo "Migration complete!\n";
} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
}
?>
