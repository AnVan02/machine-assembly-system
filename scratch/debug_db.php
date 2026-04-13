<?php
require "config.php";
$order_id = 5; // Assuming from list_orders.php
$stmt = $pdo->prepare("SELECT id_ct, loai_linhkien, ten_linhkien, so_serial, linhkien_chon, so_may, ten_cauhinh FROM chitiet_donhang WHERE id_donhang = ?");
$stmt->execute([$order_id]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
