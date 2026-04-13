<?php
require "../config.php";
$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 1;
$stmt = $pdo->prepare("SELECT id_ct, ten_cauhinh, loai_linhkien, ten_linhkien, so_serial, linhkien_chon, so_may FROM chitiet_donhang WHERE id_donhang = ? ORDER BY id_ct ASC");
$stmt->execute([$order_id]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<pre>";
echo "ID | Config String | Type | Name | Serial | Assigned To\n";
echo str_repeat("-", 80) . "\n";
foreach ($rows as $r) {
    printf("%2d | %-20s | %-5s | %-15s | %-15s | %s\n", 
        $r['id_ct'], 
        $r['ten_cauhinh'], 
        $r['loai_linhkien'], 
        $r['ten_linhkien'], 
        $r['so_serial'], 
        $r['linhkien_chon'] . " (M" . $r['so_may'] . ")"
    );
}
echo "</pre>";
