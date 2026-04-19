<?php
$host = '192.168.1.156';
$output = [];
$result = -1;

if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    echo "OS: Windows\n";
    exec("ping -n 1 -w 1000 $host", $output, $result);
} else {
    echo "OS: Linux/Other\n";
    exec("ping -c 1 -W 1 $host", $output, $result);
}

echo "Result Code: $result\n";
echo "Output:\n";
print_r($output);
