<?php
/**
 * Emergency Encryption Fix Script - v2.14.1
 */
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/asset.helper.php';

$db = get_db_connection();
$assets = $db->query("SELECT * FROM server_assets")->fetchAll();

echo "Starting re-encryption fix...\n";

foreach ($assets as $a) {
    // Decrypt then re-encrypt to ensure valid format
    // AssetHelper::decrypt is now safe and returns original if not encrypted
    $raw_user = AssetHelper::decrypt($a['username']);
    $raw_pass = AssetHelper::decrypt($a['password']);
    $raw_notes = AssetHelper::decrypt($a['notes']);
    $raw_inst  = AssetHelper::decrypt($a['installed_apps']);
    $raw_miss  = AssetHelper::decrypt($a['missing_apps']);
    
    $enc_user = AssetHelper::encrypt($raw_user);
    $enc_pass = AssetHelper::encrypt($raw_pass);
    $enc_notes = AssetHelper::encrypt($raw_notes);
    $enc_inst  = AssetHelper::encrypt($raw_inst);
    $enc_miss  = AssetHelper::encrypt($raw_miss);
    
    $stmt = $db->prepare("UPDATE server_assets SET 
        username = ?, 
        password = ?, 
        notes = ?, 
        installed_apps = ?, 
        missing_apps = ?, 
        is_encrypted = 1 
        WHERE id = ?");
    
    $stmt->execute([$enc_user, $enc_pass, $enc_notes, $enc_inst, $enc_miss, $a['id']]);
    echo "- ID {$a['id']} [{$a['hostname']}] updated.\n";
}

echo "Fix completed successfully.\n";
