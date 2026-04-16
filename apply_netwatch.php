<?php
include 'includes/config.php';
$db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

$sql = file_get_contents('sql/netwatch.sql');

if ($db->multi_query($sql)) {
    echo "Tabel Netwatch berhasil dibuat!";
} else {
    echo "Gagal membuat tabel: " . $db->error;
}

$db->close();
?>
