<?php
require "config.php";
$ma = 'RS-1774796810';
$stmt = $pdo->prepare("SELECT id_donhang FROM donhang WHERE ma_don_hang = ?");
$stmt->execute([$ma]);
$ord = $stmt->fetch(PDO::FETCH_ASSOC);

if ($ord) {
    $id = $ord['id_donhang'];
    echo "ID: $id\n";
    $stmt2 = $pdo->prepare("SELECT * FROM chitiet_donhang WHERE id_donhang = ?");
    $stmt2->execute([$id]);
    $items = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    echo "COUNT: " . count($items) . "\n";
    foreach ($items as $it) {
        echo "ID_CT: {$it['id_ct']} | CFG: {$it['ten_cauhinh']} | MARK: {$it['linhkien_chon']}\n";
    }
} else {
    echo "Order $ma not found\n";
}
?>