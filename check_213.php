<?php
require "config.php";
$order_id = 213; 
$sql = "SELECT id_ct, ten_donhang, ten_cauhinh, loai_linhkien, ten_linhkien 
        FROM chitiet_donhang 
        WHERE id_donhang = ? 
        ORDER BY ten_cauhinh, loai_linhkien";
$stmt = $pdo->prepare($sql);
$stmt->execute([$order_id]);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT);
?>
