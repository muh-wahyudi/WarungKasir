<?php
require __DIR__ . '/../includes/midtrans.php';
header('Content-Type: text/plain');
$id = $_GET['id'] ?? '';
echo "ORDER: $id\n\n";
print_r(midtrans_get_status($id));