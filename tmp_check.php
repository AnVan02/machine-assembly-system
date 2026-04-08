<?php
require "config.php";
$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 210; 
$sql = "SELECT ten_donhang, ten_cauhinh, loai_linhkien, ten_linhkien, count(*) as qty 
        FROM chitiet_donhang 
        WHERE id_donhang = ? 
        GROUP BY ten_donhang, ten_cauhinh, loai_linhkien, ten_linhkien";
$stmt = $pdo->prepare($sql);
$stmt->execute([$order_id]);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT);
?>
