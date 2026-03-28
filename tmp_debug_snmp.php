<?php
/**
 * SNMP Diagnostic Script
 */
$ip = "192.168.5.1";
$community = "public";

echo "--- MikroTik OIDs ---\n";
echo "CPU Load (.11.0): " . var_export(@snmp2_get($ip, $community, ".1.3.6.1.4.1.14988.1.1.3.11.0"), true) . "\n";
echo "Mem Total (.8.0): " . var_export(@snmp2_get($ip, $community, ".1.3.6.1.4.1.14988.1.1.3.8.0"), true) . "\n";
echo "Mem Used (.9.0): " . var_export(@snmp2_get($ip, $community, ".1.3.6.1.4.1.14988.1.1.3.9.0"), true) . "\n";

echo "\n--- Generic Storage Walk ---\n";
$storage_desc = @snmprealwalk($ip, $community, ".1.3.6.1.2.1.25.2.3.1.3");
var_dump($storage_desc);
?>
